
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'retur';
$pesan = '';

$idretur = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idretur = filter_input(INPUT_POST, 'idretur', FILTER_VALIDATE_INT);
}

if (!$idretur || $idretur <= 0) {
    $_SESSION['pesan_retur'] = 'ID retur tidak valid.';
    header('Location: data_retur.php');
    exit;
}

// Ambil data utama retur
$stmt = $pdo->prepare("
    SELECT idretur, idpenerimaan, iduser, created_at
    FROM retur
    WHERE idretur = ?
");
$stmt->execute([$idretur]);
$retur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$retur) {
    $_SESSION['pesan_retur'] = 'Data retur tidak ditemukan.';
    header('Location: data_retur.php');
    exit;
}

$idpenerimaan = (int) $retur['idpenerimaan'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jumlahRetur = $_POST['jumlah_retur'] ?? [];
    $alasanRetur = $_POST['alasan_retur'] ?? [];

    try {
        $pdo->beginTransaction();

        // Kunci data penerimaan agar validasi konsisten
        $stmt = $pdo->prepare("
            SELECT idpenerimaan
            FROM penerimaan
            WHERE idpenerimaan = ?
            FOR UPDATE
        ");
        $stmt->execute([$idpenerimaan]);

        if (!$stmt->fetch()) {
            throw new Exception('Data penerimaan tidak ditemukan.');
        }

        // Ambil detail penerimaan beserta jumlah retur dari transaksi lain
        $stmt = $pdo->prepare("
            SELECT
                dp.iddetail_penerimaan,
                dp.jumlah_terima,
                COALESCE((
                    SELECT SUM(dr.jumlah)
                    FROM detail_retur dr
                    JOIN retur r2 ON r2.idretur = dr.idretur
                    WHERE dr.iddetail_penerimaan = dp.iddetail_penerimaan
                      AND r2.idretur <> ?
                ), 0) AS jumlah_retur_lain
            FROM detail_penerimaan dp
            WHERE dp.idpenerimaan = ?
            FOR UPDATE
        ");
        $stmt->execute([$idretur, $idpenerimaan]);
        $dataPenerimaan = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $detailUntukDisimpan = [];

        foreach ($dataPenerimaan as $baris) {
            $idDetail = (int) $baris['iddetail_penerimaan'];

            $jumlah = filter_var(
                $jumlahRetur[$idDetail] ?? 0,
                FILTER_VALIDATE_INT
            );

            $alasan = trim($alasanRetur[$idDetail] ?? '');

            $sisa = (int) $baris['jumlah_terima']
                - (int) $baris['jumlah_retur_lain'];

            if ($jumlah === false || $jumlah < 0) {
                throw new Exception('Jumlah retur harus berupa bilangan bulat nol atau lebih.');
            }

            if ($jumlah > $sisa) {
                throw new Exception(
                    "Jumlah retur detail #$idDetail melebihi sisa barang ($sisa)."
                );
            }

            if ($jumlah > 0) {
                if ($alasan === '') {
                    throw new Exception(
                        "Alasan retur detail #$idDetail wajib diisi."
                    );
                }

                if (mb_strlen($alasan) > 200) {
                    throw new Exception(
                        "Alasan retur maksimal 200 karakter."
                    );
                }

                $detailUntukDisimpan[] = [
                    'iddetail_penerimaan' => $idDetail,
                    'jumlah' => $jumlah,
                    'alasan' => $alasan
                ];
            }
        }

        if (empty($detailUntukDisimpan)) {
            throw new Exception('Isi jumlah retur minimal satu barang.');
        }

        // Hapus detail lama, kemudian simpan detail terbaru
        $stmt = $pdo->prepare("
            DELETE FROM detail_retur
            WHERE idretur = ?
        ");
        $stmt->execute([$idretur]);

        $stmt = $pdo->prepare("
            INSERT INTO detail_retur
                (jumlah, alasan, idretur, iddetail_penerimaan)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($detailUntukDisimpan as $detail) {
            $stmt->execute([
                $detail['jumlah'],
                $detail['alasan'],
                $idretur,
                $detail['iddetail_penerimaan']
            ]);
        }

        $pdo->commit();

        $_SESSION['pesan_retur'] = 'Data retur berhasil diperbarui.';
        header('Location: detail_retur.php?id=' . $idretur);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $pesan = $e instanceof PDOException
            ? 'Retur gagal diperbarui. Periksa struktur tabel dan koneksi database.'
            : $e->getMessage();
    }
}

// Ambil kembali detail untuk ditampilkan pada form
$stmt = $pdo->prepare("
    SELECT
        dp.iddetail_penerimaan,
        b.nama AS nama_barang,
        dp.jumlah_terima,
        COALESCE((
            SELECT SUM(dr.jumlah)
            FROM detail_retur dr
            JOIN retur r2 ON r2.idretur = dr.idretur
            WHERE dr.iddetail_penerimaan = dp.iddetail_penerimaan
              AND r2.idretur <> ?
        ), 0) AS jumlah_retur_lain,
        dr_saat_ini.jumlah AS jumlah_retur_saat_ini,
        dr_saat_ini.alasan AS alasan_saat_ini
    FROM detail_penerimaan dp
    JOIN barang b
        ON b.idbarang = dp.barang_idbarang
    LEFT JOIN detail_retur dr_saat_ini
        ON dr_saat_ini.iddetail_penerimaan = dp.iddetail_penerimaan
       AND dr_saat_ini.idretur = ?
    WHERE dp.idpenerimaan = ?
    ORDER BY dp.iddetail_penerimaan ASC
");

$stmt->execute([$idretur, $idretur, $idpenerimaan]);
$detailPenerimaan = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Retur | ATK STORE</title>
    <link rel="stylesheet" href="assets/style.css?v=113">
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="page-header">
        <div>
            <h1>Edit Retur</h1>
            <p>Ubah jumlah dan alasan pengembalian barang.</p>
        </div>
    </header>

    <?php if ($pesan !== ''): ?>
        <div class="alert">
            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <h2>Retur #<?= (int) $retur['idretur'] ?></h2>
        </div>

        <div style="padding: 20px;">
            <p>
                <strong>ID Penerimaan:</strong>
                <?= $idpenerimaan ?>
            </p>

            <form method="POST" action="edit_retur.php">
                <input type="hidden" name="idretur"
                       value="<?= (int) $idretur ?>">

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Barang</th>
                                <th>Jumlah Diterima</th>
                                <th>Retur Lain</th>
                                <th>Jumlah Retur</th>
                                <th>Alasan Retur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailPenerimaan as $d): ?>
                                <?php
                                $idDetail = (int) $d['iddetail_penerimaan'];
                                $sisa = (int) $d['jumlah_terima']
                                    - (int) $d['jumlah_retur_lain'];
                                ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars(
                                            (string) $d['nama_barang'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                    <td><?= (int) $d['jumlah_terima'] ?></td>
                                    <td><?= (int) $d['jumlah_retur_lain'] ?></td>
                                    <td>
                                        <input
                                            type="number"
                                            name="jumlah_retur[<?= $idDetail ?>]"
                                            min="0"
                                            max="<?= max(0, $sisa) ?>"
                                            value="<?= htmlspecialchars(
                                                (string) (
                                                    $_POST['jumlah_retur'][$idDetail]
                                                    ?? $d['jumlah_retur_saat_ini']
                                                    ?? 0
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="alasan_retur[<?= $idDetail ?>]"
                                            maxlength="200"
                                            value="<?= htmlspecialchars(
                                                (string) (
                                                    $_POST['alasan_retur'][$idDetail]
                                                    ?? $d['alasan_saat_ini']
                                                    ?? ''
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            placeholder="Alasan retur"
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        Simpan Perubahan
                    </button>

                    <a href="detail_retur.php?id=<?= (int) $idretur ?>"
                       class="btn btn-secondary">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </section>
</main>

</body>
</html>
