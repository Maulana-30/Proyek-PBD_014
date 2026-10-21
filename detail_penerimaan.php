
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'penerimaan';
$pesanError = '';
$dataPenerimaan = null;
$detailBarang = [];

$idpenerimaan = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$idpenerimaan || $idpenerimaan <= 0) {
    header('Location: data_penerimaan.php');
    exit;
}

try {
    // Mengambil informasi utama penerimaan.
    // Function fn_total_penerimaan menghitung total nilai barang.
    $stmt = $pdo->prepare("
        SELECT
            p.idpenerimaan,
            p.created_at,
            p.status,
            p.idpengadaan,
            u.username,
            v.nama_vendor,
            fn_total_penerimaan(p.idpenerimaan) AS total_penerimaan
        FROM penerimaan AS p
        LEFT JOIN `user` AS u
            ON u.iduser = p.iduser
        LEFT JOIN pengadaan AS pg
            ON pg.idpengadaan = p.idpengadaan
        LEFT JOIN vendor AS v
            ON v.idvendor = pg.vendor_idvendor
        WHERE p.idpenerimaan = ?
    ");

    $stmt->execute([$idpenerimaan]);

    $dataPenerimaan = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$dataPenerimaan) {
        throw new Exception('Data penerimaan tidak ditemukan.');
    }

    // Mengambil rincian barang melalui Stored Procedure.
    $stmtDetail = $pdo->prepare(
        "CALL sp_detail_penerimaan(?)"
    );

    $stmtDetail->execute([$idpenerimaan]);

    $detailBarang = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

    // Membersihkan hasil tambahan dari pemanggilan procedure.
    while ($stmtDetail->nextRowset()) {
        // Tidak ada hasil tambahan yang perlu ditampilkan.
    }

    $stmtDetail->closeCursor();

} catch (Throwable $e) {
    $pesanError = $e instanceof PDOException
        ? 'Data penerimaan gagal dimuat. Periksa database.'
        : $e->getMessage();
}

function rupiahPenerimaan($nilai)
{
    return 'Rp ' . number_format(
        (float) $nilai,
        0,
        ',',
        '.'
    );
}

function statusPenerimaanDetail($status)
{
    $status = (string) $status;

    if ($status === '0') {
        return 'Menunggu';
    }

    if ($status === '1') {
        return 'Selesai';
    }

    return 'Status ' . $status;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Penerimaan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=115">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Detail Penerimaan</h1>
            <p>Informasi penerimaan barang dari pengadaan.</p>
        </div>
    </header>

    <?php if ($pesanError !== ''): ?>

        <div class="alert alert-error">
            <?= htmlspecialchars(
                $pesanError,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

        <a href="data_penerimaan.php" class="btn btn-secondary">
            Kembali ke Data Penerimaan
        </a>

    <?php elseif ($dataPenerimaan): ?>

        <section class="card">
            <div class="card-header">
                <h2>Informasi Penerimaan</h2>
            </div>

            <div class="info-pengadaan">
                <p>
                    <strong>ID Penerimaan:</strong>
                    <?= (int) $dataPenerimaan['idpenerimaan'] ?>
                </p>

                <p>
                    <strong>ID Pengadaan:</strong>
                    <?= (int) $dataPenerimaan['idpengadaan'] ?>
                </p>

                <p>
                    <strong>Vendor:</strong>
                    <?= htmlspecialchars(
                        $dataPenerimaan['nama_vendor'] ?? '-',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <p>
                    <strong>Petugas:</strong>
                    <?= htmlspecialchars(
                        $dataPenerimaan['username'] ?? '-',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <p>
                    <strong>Tanggal Penerimaan:</strong>
                    <?= htmlspecialchars(
                        $dataPenerimaan['created_at'] ?? '-',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <?= htmlspecialchars(
                        statusPenerimaanDetail(
                            $dataPenerimaan['status']
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2>Rincian Barang Diterima</h2>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Barang</th>
                            <th>Satuan</th>
                            <th>Jumlah Diterima</th>
                            <th>Harga Satuan</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!empty($detailBarang)): ?>

                            <?php foreach ($detailBarang as $i => $barang): ?>

                                <tr>
                                    <td><?= $i + 1 ?></td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $barang['nama_barang'] ?? '-',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $barang['nama_satuan'] ?? '-',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int) $barang['jumlah_terima'] ?>
                                    </td>

                                    <td>
                                        <?= rupiahPenerimaan(
                                            $barang['harga_satuan_terima']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= rupiahPenerimaan(
                                            $barang['sub_total_terima']
                                        ) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            <tr>
                                <td colspan="5" style="text-align: right;">
                                    <strong>Total Penerimaan</strong>
                                </td>

                                <td>
                                    <strong>
                                        <?= rupiahPenerimaan(
                                            $dataPenerimaan['total_penerimaan']
                                        ) ?>
                                    </strong>
                                </td>
                            </tr>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="empty-state">
                                    Belum ada rincian barang pada penerimaan ini.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <!-- Posisi tombol kembali dipertahankan seperti kode awal -->
            <div class="form-actions">
                <a
                    href="data_penerimaan.php"
                    class="btn btn-secondary"
                >
                    Kembali ke Data Penerimaan
                </a>
            </div>
        </section>

    <?php endif; ?>

</main>

</body>
</html>
