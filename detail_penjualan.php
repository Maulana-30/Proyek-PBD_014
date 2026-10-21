```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'penjualan';

$idPenjualan = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$idPenjualan || $idPenjualan < 1) {
    $_SESSION['pesan_penjualan'] = 'ID penjualan tidak valid.';
    header('Location: data_penjualan.php');
    exit;
}

// Ambil informasi utama transaksi.
$sqlPenjualan = "SELECT
                    p.idpenjualan,
                    p.created_at,
                    p.subtotal_nilai,
                    p.ppn,
                    p.total_nilai,
                    p.status,
                    u.username
                 FROM penjualan AS p
                 LEFT JOIN `user` AS u
                    ON p.iduser = u.iduser
                 WHERE p.idpenjualan = ?";

$stmtPenjualan = $pdo->prepare($sqlPenjualan);
$stmtPenjualan->execute([$idPenjualan]);
$penjualan = $stmtPenjualan->fetch(PDO::FETCH_ASSOC);

if (!$penjualan) {
    $_SESSION['pesan_penjualan'] = 'Transaksi penjualan tidak ditemukan.';
    header('Location: data_penjualan.php');
    exit;
}

// Ambil rincian barang dalam transaksi.
$sqlDetail = "SELECT
                d.iddetail_penjualan,
                d.harga_satuan,
                d.jumlah,
                d.subtotal,
                b.idbarang,
                b.nama AS nama_barang,
                b.jenis
              FROM detail_penjualan AS d
              INNER JOIN barang AS b
                ON d.idbarang = b.idbarang
              WHERE d.penjualan_idpenjualan = ?
              ORDER BY d.iddetail_penjualan ASC";

$stmtDetail = $pdo->prepare($sqlDetail);
$stmtDetail->execute([$idPenjualan]);
$daftarDetail = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

function rupiahDetailPenjualan($nilai)
{
    return 'Rp ' . number_format((float) $nilai, 0, ',', '.');
}

function tanggalDetailPenjualan($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $waktu = strtotime($tanggal);

    return $waktu === false
        ? htmlspecialchars((string) $tanggal, ENT_QUOTES, 'UTF-8')
        : date('d-m-Y H:i', $waktu);
}

function labelStatusDetailPenjualan($status)
{
    if ((string) $status === '0') {
        return 'Dibatalkan';
    }

    if ((string) $status === '1') {
        return 'Selesai';
    }

    return 'Status ' . (string) $status;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Penjualan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=105">

    <style>
        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .detail-header h1 {
            margin-bottom: 6px;
        }

        .detail-header p {
            margin: 0;
        }

        .btn-kembali {
            display: inline-block;
            padding: 9px 14px;
            border-radius: 6px;
            background: #edf1f7;
            color: #344054;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-kembali:hover {
            background: #dfe5ee;
        }

        .informasi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .informasi-item {
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .informasi-item span {
            display: block;
            margin-bottom: 8px;
            color: #667085;
            font-size: 13px;
        }

        .informasi-item strong {
            color: #1e293b;
            overflow-wrap: anywhere;
        }

        .status-label {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 5px;
            background: #edf1f7;
            color: #344054;
            font-size: 12px;
            font-weight: 600;
        }

        .ringkasan-total {
            width: 100%;
            max-width: 360px;
            margin: 24px 0 0 auto;
        }

        .ringkasan-baris {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .ringkasan-baris.total {
            border-bottom: none;
            font-size: 18px;
            font-weight: 700;
        }

        .kosong-detail {
            padding: 24px;
            text-align: center;
            color: #667085;
        }

        @media (max-width: 768px) {
            .informasi-grid {
                grid-template-columns: 1fr;
            }

            .ringkasan-total {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="detail-header">
        <div>
            <h1>Detail Penjualan</h1>
            <p>Rincian transaksi penjualan barang.</p>
        </div>

        <a href="data_penjualan.php" class="btn-kembali">
            Kembali
        </a>
    </header>

    <section class="card">
        <h2>Informasi Transaksi</h2>

        <div class="informasi-grid">
            <div class="informasi-item">
                <span>ID Penjualan</span>
                <strong>
                    <?= (int) $penjualan['idpenjualan'] ?>
                </strong>
            </div>

            <div class="informasi-item">
                <span>Tanggal Penjualan</span>
                <strong>
                    <?= tanggalDetailPenjualan($penjualan['created_at']) ?>
                </strong>
            </div>

            <div class="informasi-item">
                <span>Kasir</span>
                <strong>
                    <?= htmlspecialchars(
                        $penjualan['username'] ?? '-',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <div class="informasi-item">
                <span>Status</span>
                <strong>
                    <span class="status-label">
                        <?= htmlspecialchars(
                            labelStatusDetailPenjualan($penjualan['status']),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>
                </strong>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Daftar Barang</h2>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Barang</th>
                        <th>Nama Barang</th>
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
                                    <?= (int) $detail['idbarang'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $detail['nama_barang'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= rupiahDetailPenjualan(
                                        $detail['harga_satuan']
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) $detail['jumlah'] ?>
                                </td>

                                <td>
                                    <?= rupiahDetailPenjualan(
                                        $detail['subtotal']
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="kosong-detail">
                                Belum ada detail barang untuk transaksi ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="ringkasan-total">
            <div class="ringkasan-baris">
                <span>Subtotal</span>
                <strong>
                    <?= rupiahDetailPenjualan(
                        $penjualan['subtotal_nilai']
                    ) ?>
                </strong>
            </div>

            <div class="ringkasan-baris">
                <span>PPN</span>
                <strong>
                    <?= rupiahDetailPenjualan($penjualan['ppn']) ?>
                </strong>
            </div>

            <div class="ringkasan-baris total">
                <span>Total</span>
                <strong>
                    <?= rupiahDetailPenjualan(
                        $penjualan['total_nilai']
                    ) ?>
                </strong>
            </div>
        </div>
    </section>

</main>

</body>
</html>
```