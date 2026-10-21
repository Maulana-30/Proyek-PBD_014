
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'kartu_stok';

$sql = "SELECT
            ks.idkartu_stok,
            ks.jenis_transaksi,
            ks.masuk,
            ks.keluar,
            ks.stock,
            ks.created_at,
            ks.idtransaksi,
            b.nama AS nama_barang
        FROM kartu_stok AS ks
        LEFT JOIN barang AS b
            ON ks.idbarang = b.idbarang
        ORDER BY ks.idkartu_stok DESC";

$stmt = $pdo->query($sql);
$dataStok = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kartu Stok | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=100">

    <style>
        .jenis-stok {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }

        .stok-masuk {
            background: #dcfce7;
            color: #166534;
        }

        .stok-keluar {
            background: #fee2e2;
            color: #991b1b;
        }

        .stok-table {
            min-width: 850px;
        }
    </style>
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Kartu Stok</h1>
            <p>Riwayat barang masuk, barang keluar, dan saldo stok.</p>
        </div>
    </header>

    <section class="card">

        <div class="card-header">
            <h2>Riwayat Stok Barang</h2>
        </div>

        <div class="table-wrapper">
            <table class="stok-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal</th>
                        <th>Nama Barang</th>
                        <th>Jenis Transaksi</th>
                        <th>Barang Masuk</th>
                        <th>Barang Keluar</th>
                        <th>Saldo Stok</th>
                        <th>ID Transaksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($dataStok) > 0): ?>

                        <?php foreach ($dataStok as $i => $stok): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>

                                <td>
                                    <?= htmlspecialchars(
                                        $stok['created_at'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $stok['nama_barang'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?php if ($stok['jenis_transaksi'] === 'M'): ?>
                                        <span class="jenis-stok stok-masuk">
                                            Masuk
                                        </span>
                                    <?php elseif ($stok['jenis_transaksi'] === 'K'): ?>
                                        <span class="jenis-stok stok-keluar">
                                            Keluar
                                        </span>
                                    <?php else: ?>
                                        <?= htmlspecialchars(
                                            $stok['jenis_transaksi'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    <?php endif; ?>
                                </td>

                                <td><?= (int) $stok['masuk'] ?></td>
                                <td><?= (int) $stok['keluar'] ?></td>
                                <td><strong><?= (int) $stok['stock'] ?></strong></td>
                                <td><?= (int) $stok['idtransaksi'] ?></td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="empty-state">
                                Belum ada riwayat pergerakan stok.
                            </td>
                        </tr>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

</main>

</body>
</html>
