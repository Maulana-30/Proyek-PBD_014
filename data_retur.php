```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'retur';

$sql = "
    SELECT
        r.idretur,
        r.created_at,
        r.idpenerimaan,
        u.username,
        COUNT(dr.iddetail_retur) AS jumlah_detail,
        COALESCE(SUM(dr.jumlah), 0) AS total_barang
    FROM retur AS r
    LEFT JOIN `user` AS u
        ON r.iduser = u.iduser
    LEFT JOIN detail_retur AS dr
        ON r.idretur = dr.idretur
    GROUP BY
        r.idretur,
        r.created_at,
        r.idpenerimaan,
        u.username
    ORDER BY r.idretur DESC
";

$retur = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

function tanggalRetur($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $waktu = strtotime($tanggal);

    return $waktu === false
        ? htmlspecialchars((string) $tanggal, ENT_QUOTES, 'UTF-8')
        : date('d-m-Y H:i', $waktu);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Retur | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=105">

    <style>
        .btn-detail {
            display: inline-block;
            padding: 8px 12px;
            background: #e8f1ff;
            color: #174a91;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-detail:hover {
            background: #d8e7ff;
        }

        .aksi-cell {
            white-space: nowrap;
        }
    </style>
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Data Retur</h1>
            <p>Daftar pengembalian barang dari proses penerimaan.</p>
        </div>

        <a href="tambah_retur.php" class="btn btn-primary">
            <span class="plus">+</span> Tambah Retur
        </a>
    </header>

    <section class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Retur</th>
                        <th>Tanggal</th>
                        <th>ID Penerimaan</th>
                        <th>Petugas</th>
                        <th>Jumlah Detail</th>
                        <th>Total Barang</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!empty($retur)): ?>

                    <?php foreach ($retur as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>

                            <td>
                                <?= (int) $row['idretur'] ?>
                            </td>

                            <td>
                                <?= tanggalRetur($row['created_at']) ?>
                            </td>

                            <td>
                                <?= (int) $row['idpenerimaan'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['username'] ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= (int) $row['jumlah_detail'] ?>
                            </td>

                            <td>
                                <?= (int) $row['total_barang'] ?>
                            </td>

                            <td class="aksi-cell">
                                <a
                                    class="btn-detail"
                                    href="detail_retur.php?id=<?= (int) $row['idretur'] ?>"
                                >
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            Belum ada data retur.
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
```