
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'penerimaan';
$pesanError = '';

$idpengadaanDipilih = (int) (
    $_POST['idpengadaan']
    ?? $_GET['idpengadaan']
    ?? 0
);

function e($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($nilai)
{
    return 'Rp ' . number_format(
        (float) $nilai,
        0,
        ',',
        '.'
    );
}

// Mengambil daftar pengadaan yang belum selesai.
$stmt = $pdo->query("
    SELECT
        p.idpengadaan,
        p.`timestamp`,
        v.nama_vendor
    FROM pengadaan AS p
    LEFT JOIN vendor AS v
        ON v.idvendor = p.vendor_idvendor
    WHERE p.status <> '2'
    ORDER BY p.idpengadaan DESC
");

$daftarPengadaan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proses menyimpan penerimaan.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['simpan_penerimaan'])
) {
    $idpengadaan = filter_var(
        $_POST['idpengadaan'] ?? null,
        FILTER_VALIDATE_INT
    );

    $jumlahTerima = $_POST['jumlah_terima'] ?? [];

    if (
        $idpengadaan === false
        || $idpengadaan === null
        || $idpengadaan < 1
        || !is_array($jumlahTerima)
    ) {
        $pesanError =
            'Pengadaan atau jumlah penerimaan tidak valid.';
    } else {
        try {
            $pdo->beginTransaction();

            // Mengunci pengadaan agar penerimaan bersamaan
            // untuk pengadaan yang sama diproses secara berurutan.
            $stmt = $pdo->prepare("
                SELECT idpengadaan
                FROM pengadaan
                WHERE idpengadaan = ?
                  AND status <> '2'
                FOR UPDATE
            ");

            $stmt->execute([$idpengadaan]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Pengadaan tidak ditemukan atau sudah selesai.'
                );
            }

            // Mengambil seluruh detail pengadaan.
            $stmt = $pdo->prepare("
                SELECT
                    iddetail_pengadaan,
                    idbarang,
                    jumlah,
                    harga_satuan
                FROM detail_pengadaan
                WHERE idpengadaan = ?
                ORDER BY iddetail_pengadaan ASC
            ");

            $stmt->execute([$idpengadaan]);

            $detailPengadaan = $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

            if (!$detailPengadaan) {
                throw new RuntimeException(
                    'Pengadaan ini belum memiliki detail barang.'
                );
            }

            // Menghitung total pesanan per barang.
            $jumlahPesanPerBarang = [];

            foreach ($detailPengadaan as $detail) {
                $idbarang = (int) $detail['idbarang'];
                $jumlahPesan = (int) $detail['jumlah'];

                if (!isset($jumlahPesanPerBarang[$idbarang])) {
                    $jumlahPesanPerBarang[$idbarang] = 0;
                }

                $jumlahPesanPerBarang[$idbarang]
                    += $jumlahPesan;
            }

            // Mengumpulkan jumlah yang akan diterima.
            $items = [];
            $jumlahBaruPerBarang = [];

            foreach ($detailPengadaan as $detail) {
                $idDetailPengadaan = (int)
                    $detail['iddetail_pengadaan'];

                $idbarang = (int) $detail['idbarang'];
                $harga = (int) $detail['harga_satuan'];

                $input = $jumlahTerima[
                    $idDetailPengadaan
                ] ?? '0';

                if (
                    !is_scalar($input)
                    || filter_var(
                        $input,
                        FILTER_VALIDATE_INT
                    ) === false
                    || (int) $input < 0
                ) {
                    throw new RuntimeException(
                        'Jumlah yang diterima harus berupa bilangan bulat nonnegatif.'
                    );
                }

                $jumlah = (int) $input;

                if ($jumlah === 0) {
                    continue;
                }

                $items[] = [
                    'idbarang' => $idbarang,
                    'jumlah' => $jumlah,
                    'harga' => $harga,
                    'subtotal' => $jumlah * $harga
                ];

                if (
                    !isset(
                        $jumlahBaruPerBarang[$idbarang]
                    )
                ) {
                    $jumlahBaruPerBarang[$idbarang] = 0;
                }

                $jumlahBaruPerBarang[$idbarang]
                    += $jumlah;
            }

            if (!$items) {
                throw new RuntimeException(
                    'Masukkan jumlah penerimaan minimal satu barang.'
                );
            }

            // Menghitung barang yang sudah diterima sebelumnya.
            $stmtSudahTerima = $pdo->prepare("
                SELECT COALESCE(
                    SUM(dp.jumlah_terima),
                    0
                )
                FROM detail_penerimaan AS dp
                INNER JOIN penerimaan AS p
                    ON p.idpenerimaan = dp.idpenerimaan
                WHERE p.idpengadaan = ?
                  AND dp.barang_idbarang = ?
            ");

            // Memastikan total penerimaan tidak melebihi pesanan.
            foreach (
                $jumlahBaruPerBarang as $idbarang => $jumlahBaru
            ) {
                $stmtSudahTerima->execute([
                    $idpengadaan,
                    $idbarang
                ]);

                $sudahTerima = (int)
                    $stmtSudahTerima->fetchColumn();

                $jumlahPesan = (int)
                    $jumlahPesanPerBarang[$idbarang];

                if (
                    $sudahTerima + $jumlahBaru
                    > $jumlahPesan
                ) {
                    throw new RuntimeException(
                        'Jumlah penerimaan melebihi sisa pesanan untuk barang ID '
                        . $idbarang . '.'
                    );
                }
            }

            // Membuat header penerimaan.
            $stmt = $pdo->prepare("
                INSERT INTO penerimaan (
                    status,
                    idpengadaan,
                    iduser
                )
                VALUES ('1', ?, ?)
            ");

            $stmt->execute([
                $idpengadaan,
                (int) $_SESSION['iduser']
            ]);

            $idpenerimaan = (int) $pdo->lastInsertId();

            // Menyimpan detail penerimaan.
            // Trigger trg_detail_penerimaan_stok akan
            // mencatat barang masuk ke kartu_stok otomatis.
            $stmtDetail = $pdo->prepare("
                INSERT INTO detail_penerimaan (
                    idpenerimaan,
                    barang_idbarang,
                    jumlah_terima,
                    harga_satuan_terima,
                    sub_total_terima
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $stmtDetail->execute([
                    $idpenerimaan,
                    $item['idbarang'],
                    $item['jumlah'],
                    $item['harga'],
                    $item['subtotal']
                ]);
            }

            // Menghitung ulang status pengadaan
            // berdasarkan jumlah pesanan dan penerimaan kumulatif.
            $stmtStatus = $pdo->prepare("
                SELECT
                    pesanan.idbarang,
                    pesanan.jumlah_pesan,
                    COALESCE(
                        terima.jumlah_terima,
                        0
                    ) AS jumlah_terima
                FROM (
                    SELECT
                        idbarang,
                        SUM(jumlah) AS jumlah_pesan
                    FROM detail_pengadaan
                    WHERE idpengadaan = ?
                    GROUP BY idbarang
                ) AS pesanan
                LEFT JOIN (
                    SELECT
                        dp.barang_idbarang,
                        SUM(dp.jumlah_terima)
                            AS jumlah_terima
                    FROM detail_penerimaan AS dp
                    INNER JOIN penerimaan AS p
                        ON p.idpenerimaan = dp.idpenerimaan
                    WHERE p.idpengadaan = ?
                    GROUP BY dp.barang_idbarang
                ) AS terima
                    ON terima.barang_idbarang = pesanan.idbarang
            ");

            $stmtStatus->execute([
                $idpengadaan,
                $idpengadaan
            ]);

            $statusBarang = $stmtStatus->fetchAll(
                PDO::FETCH_ASSOC
            );

            $semuaSelesai = !empty($statusBarang);
            $adaPenerimaan = false;

            foreach ($statusBarang as $barang) {
                $jumlahPesan = (int)
                    $barang['jumlah_pesan'];

                $jumlahDiterima = (int)
                    $barang['jumlah_terima'];

                if ($jumlahDiterima > 0) {
                    $adaPenerimaan = true;
                }

                if ($jumlahDiterima < $jumlahPesan) {
                    $semuaSelesai = false;
                }
            }

            if ($semuaSelesai) {
                $statusPengadaanBaru = '2';
            } elseif ($adaPenerimaan) {
                $statusPengadaanBaru = '1';
            } else {
                $statusPengadaanBaru = '0';
            }

            $stmtUpdateStatus = $pdo->prepare("
                UPDATE pengadaan
                SET status = ?
                WHERE idpengadaan = ?
            ");

            $stmtUpdateStatus->execute([
                $statusPengadaanBaru,
                $idpengadaan
            ]);

            // Menyimpan seluruh perubahan secara bersamaan.
            $pdo->commit();

            $_SESSION['pesan_penerimaan'] =
                'Penerimaan berhasil disimpan dan stok masuk dicatat.';

            header('Location: data_penerimaan.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $pesanError = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Penerimaan gagal disimpan. Periksa struktur tabel database.';
        }
    }
}

// Mengambil detail pengadaan untuk ditampilkan.
$detailTampil = [];

if ($idpengadaanDipilih > 0) {
    $stmt = $pdo->prepare("
        SELECT
            d.iddetail_pengadaan,
            d.idbarang,
            b.nama,
            d.jumlah,
            d.harga_satuan
        FROM detail_pengadaan AS d
        INNER JOIN barang AS b
            ON b.idbarang = d.idbarang
        WHERE d.idpengadaan = ?
        ORDER BY d.iddetail_pengadaan ASC
    ");

    $stmt->execute([$idpengadaanDipilih]);

    $detailTampil = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    $stmtSudahTerimaTampil = $pdo->prepare("
        SELECT COALESCE(
            SUM(dp.jumlah_terima),
            0
        )
        FROM detail_penerimaan AS dp
        INNER JOIN penerimaan AS p
            ON p.idpenerimaan = dp.idpenerimaan
        WHERE p.idpengadaan = ?
          AND dp.barang_idbarang = ?
    ");

    foreach ($detailTampil as &$d) {
        $stmtSudahTerimaTampil->execute([
            $idpengadaanDipilih,
            (int) $d['idbarang']
        ]);

        $d['sudah_terima'] = (int)
            $stmtSudahTerimaTampil->fetchColumn();

        $d['sisa'] = max(
            0,
            (int) $d['jumlah'] - $d['sudah_terima']
        );
    }

    unset($d);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Penerimaan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=105">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Tambah Penerimaan</h1>
            <p>Catat barang yang diterima dari pengadaan.</p>
        </div>
    </header>

    <?php if ($pesanError !== ''): ?>

        <div class="alert alert-danger">
            <?= e($pesanError) ?>
        </div>

    <?php endif; ?>

    <section class="card">

        <form method="POST">

            <div class="form-group">
                <label for="idpengadaan">Pengadaan</label>

                <select
                    name="idpengadaan"
                    id="idpengadaan"
                    class="form-control"
                    required
                    onchange="this.form.submit();"
                >
                    <option value="">-- Pilih Pengadaan --</option>

                    <?php foreach ($daftarPengadaan as $p): ?>

                        <option
                            value="<?= (int) $p['idpengadaan'] ?>"
                            <?= $idpengadaanDipilih ===
                                (int) $p['idpengadaan']
                                ? 'selected'
                                : '' ?>
                        >
                            Pengadaan #<?= (int) $p['idpengadaan'] ?>
                            - <?= e($p['nama_vendor'] ?? '-') ?>
                        </option>

                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($detailTampil): ?>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Jumlah Pesan</th>
                                <th>Sudah Diterima</th>
                                <th>Sisa</th>
                                <th>Jumlah Diterima Sekarang</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($detailTampil as $d): ?>

                                <tr>
                                    <td><?= e($d['nama']) ?></td>

                                    <td>
                                        <?= (int) $d['jumlah'] ?>
                                    </td>

                                    <td>
                                        <?= (int) $d['sudah_terima'] ?>
                                    </td>

                                    <td>
                                        <?= (int) $d['sisa'] ?>
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            class="form-control"
                                            name="jumlah_terima[<?= (int) $d['iddetail_pengadaan'] ?>]"
                                            min="0"
                                            max="<?= (int) $d['sisa'] ?>"
                                            value="<?= e(
                                                $_POST['jumlah_terima'][$d['iddetail_pengadaan']]
                                                ?? '0'
                                            ) ?>"
                                            <?= $d['sisa'] === 0
                                                ? 'disabled'
                                                : '' ?>
                                        >
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <a
                        href="data_penerimaan.php"
                        class="btn btn-secondary"
                    >
                        Kembali
                    </a>

                    <button
                        type="submit"
                        name="simpan_penerimaan"
                        value="1"
                        class="btn btn-primary"
                    >
                        Simpan Penerimaan
                    </button>
                </div>

            <?php elseif ($idpengadaanDipilih > 0): ?>

                <p>Detail pengadaan tidak ditemukan.</p>

                <a
                    href="data_penerimaan.php"
                    class="btn btn-secondary"
                >
                    Kembali
                </a>

            <?php else: ?>

                <p>Pilih pengadaan untuk menampilkan barang yang dipesan.</p>

                <a
                    href="data_penerimaan.php"
                    class="btn btn-secondary"
                >
                    Kembali
                </a>

            <?php endif; ?>

        </form>

    </section>

</main>

</body>
</html>
