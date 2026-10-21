
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'pengadaan';

$idPengadaan = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$idPengadaan || $idPengadaan < 1) {
    header('Location: data_pengadaan.php');
    exit;
}

// Mengambil informasi utama pengadaan.
// Function fn_total_pengadaan digunakan untuk menghitung subtotal.
$stmtPengadaan = $pdo->prepare("
    SELECT
        p.idpengadaan,
        p.`timestamp`,
        p.status,
        fn_total_pengadaan(p.idpengadaan) AS subtotal_nilai,
        p.ppn,
        p.total_nilai,
        v.nama_vendor,
        u.username
    FROM pengadaan AS p
    LEFT JOIN vendor AS v
        ON p.vendor_idvendor = v.idvendor
    LEFT JOIN `user` AS u
        ON p.user_iduser = u.iduser
    WHERE p.idpengadaan = ?
");

$stmtPengadaan->execute([$idPengadaan]);
$pengadaan = $stmtPengadaan->fetch(PDO::FETCH_ASSOC);
$stmtPengadaan->closeCursor();

if (!$pengadaan) {
    http_response_code(404);
    $tidakDitemukan = true;
    $daftarDetail = [];
} else {
    $tidakDitemukan = false;

    // Mengambil rincian barang melalui Stored Procedure.
    $stmtDetail = $pdo->prepare(
        "CALL sp_detail_pengadaan(?)"
    );

    $stmtDetail->execute([$idPengadaan]);

    $daftarDetail = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

    // Menutup cursor agar koneksi PDO siap digunakan kembali.
    $stmtDetail->closeCursor();
}

function rupiahDetail($nilai)
{
    return 'Rp ' . number_format((float) $nilai, 0, ',', '.');
}

function h($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

function statusPengadaan($status)
{
    $status = (string) $status;

    if ($status === '0') {
        return 'Menunggu';
    }

    if ($status === '1') {
        return 'Diproses';
    }

    if ($status === '2') {
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

    <title>Detail Pengadaan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=104">

    <style>
        .detail-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .info-item {
            padding: 14px;
            background: #f5f8fd;
            border-radius: 8px;
        }

        .info-item small {
            display: block;
            margin-bottom: 6px;
            color: #667085;
        }

        .info-item strong {
            color: #24324b;
        }

        .total-box {
            max-width: 400px;
            margin: 22px 0 0 auto;
            padding: 18px;
            background: #f5f8fd;
            border-radius: 8px;
        }

        .total-box p {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin: 10px 0;
        }

        .total-box .total {
            padding-top: 12px;
            border-top: 1px solid #d7dfeb;
            font-size: 18px;
            font-weight: 700;
        }

        @media (max-width: 600px) {
            .detail-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <?php if ($tidakDitemukan): ?>

        <section class="card">
            <h1>Pengadaan Tidak Ditemukan</h1>
            <p>Data pengadaan dengan ID tersebut tidak tersedia.</p>

            <div class="form-actions">
                <a href="data_pengadaan.php" class="btn btn-secondary">
                    Kembali ke Data Pengadaan
                </a>
            </div>
        </section>

    <?php else: ?>

        <header class="page-header">
            <div>
                <h1>
                    Detail Pengadaan #<?= (int) $pengadaan['idpengadaan'] ?>
                </h1>
                <p>Informasi transaksi dan rincian barang yang dipesan.</p>
            </div>
        </header>

        <section class="card">
            <h2>Informasi Pengadaan</h2>

            <div class="detail-info">

                <div class="info-item">
                    <small>ID Pengadaan</small>
                    <strong>
                        <?= (int) $pengadaan['idpengadaan'] ?>
                    </strong>
                </div>

                <div class="info-item">
                    <small>Tanggal</small>
                    <strong>
                        <?= h($pengadaan['timestamp']) ?>
                    </strong>
                </div>

                <div class="info-item">
                    <small>Vendor</small>
                    <strong>
                        <?= h($pengadaan['nama_vendor'] ?? '-') ?>
                    </strong>
                </div>

                <div class="info-item">
                    <small>Petugas</small>
                    <strong>
                        <?= h($pengadaan['username'] ?? '-') ?>
                    </strong>
                </div>

                <div class="info-item">
                    <small>Status</small>
                    <strong>
                        <?= h(statusPengadaan($pengadaan['status'])) ?>
                    </strong>
                </div>

            </div>
        </section>

        <section class="card">
            <h2>Rincian Barang</h2>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Barang</th>
                            <th>Satuan</th>
                            <th>Harga Satuan</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($daftarDetail) > 0): ?>

                        <?php foreach ($daftarDetail as $i => $detail): ?>

                            <tr>
                                <td><?= $i + 1 ?></td>

                                <td>
                                    <?= h($detail['nama_barang'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= h($detail['nama_satuan'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= rupiahDetail($detail['harga_satuan']) ?>
                                </td>

                                <td>
                                    <?= (int) $detail['jumlah'] ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= rupiahDetail($detail['sub_total']) ?>
                                    </strong>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="empty-state">
                                Belum ada rincian barang untuk pengadaan ini.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <div class="total-box">
                <p>
                    <span>Subtotal</span>
                    <strong>
                        <?= rupiahDetail($pengadaan['subtotal_nilai']) ?>
                    </strong>
                </p>

                <p>
                    <span>PPN</span>
                    <strong>
                        <?= rupiahDetail($pengadaan['ppn']) ?>
                    </strong>
                </p>

                <p class="total">
                    <span>Total</span>
                    <strong>
                        <?= rupiahDetail($pengadaan['total_nilai']) ?>
                    </strong>
                </p>
            </div>

            <div class="form-actions">
                <a href="data_pengadaan.php" class="btn btn-secondary">
                    Kembali ke Data Pengadaan
                </a>
            </div>

        </section>

    <?php endif; ?>

</main>

</body>
</html>
