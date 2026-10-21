```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'retur';
$pesan = '';
$dataPenerimaan = [];
$detailPenerimaan = [];
$idpenerimaanDipilih = 0;

// Ambil daftar penerimaan
$stmt = $pdo->query("
    SELECT idpenerimaan, created_at, idpengadaan
    FROM penerimaan
    ORDER BY idpenerimaan DESC
");
$dataPenerimaan = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idpenerimaanDipilih = filter_input(
        INPUT_POST,
        'idpenerimaan',
        FILTER_VALIDATE_INT
    ) ?: 0;

    $aksi = $_POST['aksi'] ?? 'periksa';

    if ($idpenerimaanDipilih <= 0) {
        $pesan = 'Silakan pilih penerimaan terlebih dahulu.';
    } elseif ($aksi === 'simpan') {
        $jumlahRetur = $_POST['jumlah_retur'] ?? [];
        $alasanRetur = $_POST['alasan_retur'] ?? [];

        try {
            if (!is_array($jumlahRetur) || !is_array($alasanRetur)) {
                throw new Exception('Data retur tidak valid.');
            }

            $pdo->beginTransaction();

            // Kunci header penerimaan dan periksa statusnya.
            $stmt = $pdo->prepare("
                SELECT idpenerimaan, status
                FROM penerimaan
                WHERE idpenerimaan = ?
                FOR UPDATE
            ");
            $stmt->execute([$idpenerimaanDipilih]);
            $penerimaan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$penerimaan) {
                throw new Exception('Penerimaan tidak ditemukan.');
            }

            // Status 1 digunakan sebagai penerimaan yang sudah diproses.
            if ((string) $penerimaan['status'] !== '1') {
                throw new Exception(
                    'Retur hanya dapat dilakukan pada penerimaan yang sudah diproses.'
                );
            }

            // Ambil detail penerimaan beserta jumlah retur sebelumnya.
            $stmt = $pdo->prepare("
                SELECT
                    dp.iddetail_penerimaan,
                    dp.barang_idbarang,
                    dp.jumlah_terima,
                    COALESCE((
                        SELECT SUM(dr.jumlah)
                        FROM detail_retur dr
                        INNER JOIN retur r
                            ON r.idretur = dr.idretur
                        WHERE dr.iddetail_penerimaan =
                              dp.iddetail_penerimaan
                    ), 0) AS jumlah_sudah_retur
                FROM detail_penerimaan dp
                WHERE dp.idpenerimaan = ?
                ORDER BY dp.iddetail_penerimaan ASC
                FOR UPDATE
            ");
            $stmt->execute([$idpenerimaanDipilih]);
            $barisPenerimaan = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($barisPenerimaan)) {
                throw new Exception(
                    'Penerimaan ini tidak memiliki detail barang.'
                );
            }

            $detailUntukDisimpan = [];
            $totalKeluarPerBarang = [];

            foreach ($barisPenerimaan as $baris) {
                $idDetail = (int) $baris['iddetail_penerimaan'];
                $idBarang = (int) $baris['barang_idbarang'];

                $nilaiJumlah = $jumlahRetur[$idDetail] ?? '0';

                if (is_array($nilaiJumlah)) {
                    throw new Exception('Jumlah retur tidak valid.');
                }

                $jumlah = filter_var(
                    $nilaiJumlah,
                    FILTER_VALIDATE_INT
                );

                $nilaiAlasan = $alasanRetur[$idDetail] ?? '';

                if (is_array($nilaiAlasan)) {
                    throw new Exception('Alasan retur tidak valid.');
                }

                $alasan = trim((string) $nilaiAlasan);

                $sisa = (int) $baris['jumlah_terima']
                    - (int) $baris['jumlah_sudah_retur'];

                if ($jumlah === false || $jumlah < 0) {
                    throw new Exception(
                        'Jumlah retur harus berupa bilangan bulat nol atau lebih.'
                    );
                }

                if ($jumlah > $sisa) {
                    throw new Exception(
                        "Jumlah retur detail #$idDetail melebihi sisa barang ($sisa)."
                    );
                }

                if ($jumlah > 0) {
                    if ($alasan === '') {
                        throw new Exception(
                            "Alasan retur untuk detail #$idDetail wajib diisi."
                        );
                    }

                    if (mb_strlen($alasan) > 200) {
                        throw new Exception(
                            "Alasan retur untuk detail #$idDetail maksimal 200 karakter."
                        );
                    }

                    $detailUntukDisimpan[] = [
                        'iddetail_penerimaan' => $idDetail,
                        'idbarang' => $idBarang,
                        'jumlah' => $jumlah,
                        'alasan' => $alasan
                    ];

                    // Gabungkan jumlah retur jika barang yang sama
                    // muncul pada beberapa detail penerimaan.
                    if (!isset($totalKeluarPerBarang[$idBarang])) {
                        $totalKeluarPerBarang[$idBarang] = 0;
                    }

                    $totalKeluarPerBarang[$idBarang] += $jumlah;
                }
            }

            if (empty($detailUntukDisimpan)) {
                throw new Exception(
                    'Isi jumlah retur minimal satu barang.'
                );
            }

            // Kunci dan validasi saldo stok setiap barang.
            $saldoStokPerBarang = [];

            foreach ($totalKeluarPerBarang as $idBarang => $jumlahKeluar) {
                $stmt = $pdo->prepare("
                    SELECT idkartu_stok, stock
                    FROM kartu_stok
                    WHERE idbarang = ?
                    ORDER BY idkartu_stok DESC
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmt->execute([$idBarang]);
                $stokTerakhir = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$stokTerakhir) {
                    throw new Exception(
                        "Barang #$idBarang belum memiliki catatan kartu stok. Retur dibatalkan."
                    );
                }

                $stokSekarang = (int) $stokTerakhir['stock'];

                if ($stokSekarang < $jumlahKeluar) {
                    throw new Exception(
                        "Stok barang #$idBarang tidak mencukupi. Stok tersedia: $stokSekarang, jumlah retur: $jumlahKeluar."
                    );
                }

                $saldoStokPerBarang[$idBarang] = $stokSekarang;
            }

            // Simpan header retur.
            $stmt = $pdo->prepare("
                INSERT INTO retur (idpenerimaan, iduser)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $idpenerimaanDipilih,
                (int) $_SESSION['iduser']
            ]);

            $idReturBaru = (int) $pdo->lastInsertId();

            // Simpan detail retur.
            $stmtDetail = $pdo->prepare("
                INSERT INTO detail_retur
                    (jumlah, alasan, idretur, iddetail_penerimaan)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($detailUntukDisimpan as $detail) {
                $stmtDetail->execute([
                    $detail['jumlah'],
                    $detail['alasan'],
                    $idReturBaru,
                    $detail['iddetail_penerimaan']
                ]);
            }

            // Catat pengeluaran stok akibat retur.
            $stmtKartuStok = $pdo->prepare("
                INSERT INTO kartu_stok
                    (
                        jenis_transaksi,
                        masuk,
                        keluar,
                        stock,
                        idtransaksi,
                        idbarang
                    )
                VALUES ('K', 0, ?, ?, ?, ?)
            ");

            foreach ($totalKeluarPerBarang as $idBarang => $jumlahKeluar) {
                $stokBaru = $saldoStokPerBarang[$idBarang]
                    - $jumlahKeluar;

                $stmtKartuStok->execute([
                    $jumlahKeluar,
                    $stokBaru,
                    $idReturBaru,
                    $idBarang
                ]);
            }

            $pdo->commit();

            $_SESSION['pesan_retur'] =
                "Retur berhasil disimpan dengan ID #$idReturBaru. Kartu stok juga berhasil diperbarui.";

            header('Location: data_retur.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof PDOException) {
                error_log('Gagal menyimpan retur: ' . $e->getMessage());
                $pesan = 'Retur gagal disimpan karena terjadi kesalahan database. Periksa konfigurasi dan struktur tabel.';
            } else {
                $pesan = $e->getMessage();
            }
        }
    }

    // Muat ulang detail agar form tetap terlihat.
    if ($idpenerimaanDipilih > 0) {
        $stmt = $pdo->prepare("
            SELECT idpenerimaan
            FROM penerimaan
            WHERE idpenerimaan = ?
        ");
        $stmt->execute([$idpenerimaanDipilih]);

        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("
                SELECT
                    dp.iddetail_penerimaan,
                    b.nama AS nama_barang,
                    dp.jumlah_terima,
                    dp.harga_satuan_terima,
                    COALESCE((
                        SELECT SUM(dr.jumlah)
                        FROM detail_retur dr
                        INNER JOIN retur r
                            ON r.idretur = dr.idretur
                        WHERE dr.iddetail_penerimaan =
                              dp.iddetail_penerimaan
                    ), 0) AS jumlah_sudah_retur
                FROM detail_penerimaan dp
                INNER JOIN barang b
                    ON b.idbarang = dp.barang_idbarang
                WHERE dp.idpenerimaan = ?
                ORDER BY dp.iddetail_penerimaan ASC
            ");
            $stmt->execute([$idpenerimaanDipilih]);
            $detailPenerimaan = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Retur | ATK STORE</title>
    <link rel="stylesheet" href="assets/style.css?v=113">
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="page-header">
        <div>
            <h1>Tambah Retur</h1>
            <p>Kelola pengembalian barang ATK STORE.</p>
        </div>
    </header>

    <?php if ($pesan !== ''): ?>
        <div class="alert">
            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <h2>Informasi Retur</h2>
        </div>

        <div style="padding: 20px;">
            <form method="POST" action="tambah_retur.php">
                <div class="form-group">
                    <label for="idpenerimaan">Penerimaan</label>

                    <select name="idpenerimaan" id="idpenerimaan" required>
                        <option value="">-- Pilih Penerimaan --</option>

                        <?php foreach ($dataPenerimaan as $p): ?>
                            <option
                                value="<?= (int) $p['idpenerimaan'] ?>"
                                <?= $idpenerimaanDipilih === (int) $p['idpenerimaan']
                                    ? 'selected' : '' ?>
                            >
                                Penerimaan #<?= (int) $p['idpenerimaan'] ?>
                                — Pengadaan #<?= (int) $p['idpengadaan'] ?>
                                — <?= htmlspecialchars(
                                    (string) $p['created_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button
                    type="submit"
                    name="aksi"
                    value="periksa"
                    class="btn btn-primary"
                >
                    Periksa Penerimaan
                </button>

                <a href="data_retur.php" class="btn btn-secondary">
                    Kembali
                </a>
            </form>

            <?php if (!empty($detailPenerimaan)): ?>
                <h3 style="margin-top: 28px;">
                    Detail Barang yang Diterima
                </h3>

                <form method="POST" action="tambah_retur.php">
                    <input
                        type="hidden"
                        name="idpenerimaan"
                        value="<?= $idpenerimaanDipilih ?>"
                    >

                    <input type="hidden" name="aksi" value="simpan">

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Nama Barang</th>
                                    <th>Jumlah Diterima</th>
                                    <th>Sudah Diretur</th>
                                    <th>Sisa Bisa Diretur</th>
                                    <th>Jumlah Retur</th>
                                    <th>Alasan Retur</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($detailPenerimaan as $index => $d): ?>
                                    <?php
                                    $sisa = (int) $d['jumlah_terima']
                                        - (int) $d['jumlah_sudah_retur'];

                                    $idDetail = (int) $d['iddetail_penerimaan'];
                                    ?>

                                    <tr>
                                        <td><?= $index + 1 ?></td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) $d['nama_barang'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td><?= (int) $d['jumlah_terima'] ?></td>
                                        <td><?= (int) $d['jumlah_sudah_retur'] ?></td>
                                        <td><?= max(0, $sisa) ?></td>

                                        <td>
                                            <input
                                                type="number"
                                                name="jumlah_retur[<?= $idDetail ?>]"
                                                min="0"
                                                max="<?= max(0, $sisa) ?>"
                                                value="<?= htmlspecialchars(
                                                    (string) (
                                                        $_POST['jumlah_retur'][$idDetail] ?? '0'
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                <?= $sisa <= 0 ? 'disabled' : '' ?>
                                            >
                                        </td>

                                        <td>
                                            <input
                                                type="text"
                                                name="alasan_retur[<?= $idDetail ?>]"
                                                maxlength="200"
                                                placeholder="Contoh: barang rusak"
                                                value="<?= htmlspecialchars(
                                                    (string) (
                                                        $_POST['alasan_retur'][$idDetail] ?? ''
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                <?= $sisa <= 0 ? 'disabled' : '' ?>
                                            >
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            Simpan Retur
                        </button>

                        <a href="data_retur.php" class="btn btn-secondary">
                            Batal
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>

</body>
</html>
```