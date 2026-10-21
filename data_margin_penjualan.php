```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'margin_penjualan';

// Ambil pesan notifikasi
$pesan = $_SESSION['pesan_margin'] ?? '';
unset($_SESSION['pesan_margin']);

// Ambil data margin penjualan dari VIEW
$sql = "SELECT
            idmargin_penjualan,
            created_at,
            persen,
            status,
            username,
            updated_at
        FROM v_data_margin_penjualan
        ORDER BY idmargin_penjualan ASC";

$stmt = $pdo->query($sql);
$dataMargin = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatTanggalMargin($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $waktu = strtotime($tanggal);

    return $waktu === false
        ? $tanggal
        : date('d-m-Y H:i', $waktu);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Margin Penjualan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=100">

    <style>
        .status-margin {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-margin-aktif {
            background: #dcfce7;
            color: #166534;
        }

        .status-margin-nonaktif {
            background: #fee2e2;
            color: #991b1b;
        }

        .margin-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-margin-edit {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 5px;
            background: #e8f1ff;
            color: #174a91;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-margin-edit:hover {
            background: #d8e7ff;
        }

        .margin-table {
            min-width: 900px;
        }
    </style>
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Margin Penjualan</h1>
            <p>Daftar dan pengelolaan margin penjualan ATK STORE.</p>
        </div>

        <a href="tambah_margin_penjualan.php" class="btn btn-primary">
            <span class="plus">+</span> Tambah Margin
        </a>
    </header>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="card">

        <div class="card-header">
            <h2>Data Margin Penjualan</h2>
        </div>

        <div class="table-wrapper">
            <table class="margin-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Margin</th>
                        <th>Persentase</th>
                        <th>Status</th>
                        <th>Dibuat Oleh</th>
                        <th>Tanggal Dibuat</th>
                        <th>Terakhir Diperbarui</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($dataMargin) > 0): ?>

                        <?php foreach ($dataMargin as $i => $margin): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>

                                <td>
                                    <?= (int) $margin['idmargin_penjualan'] ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            rtrim(
                                                rtrim(
                                                    number_format(
                                                        (float) $margin['persen'],
                                                        2,
                                                        '.',
                                                        ''
                                                    ),
                                                    '0'
                                                ),
                                                '.'
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>%
                                    </strong>
                                </td>

                                <td>
                                    <?php if ((int) $margin['status'] === 1): ?>
                                        <span class="status-margin status-margin-aktif">
                                            Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="status-margin status-margin-nonaktif">
                                            Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $margin['username'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        formatTanggalMargin(
                                            $margin['created_at']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        formatTanggalMargin(
                                            $margin['updated_at']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <div class="margin-actions">
                                        <a
                                            href="edit_margin_penjualan.php?id=<?= (int) $margin['idmargin_penjualan'] ?>"
                                            class="btn-margin-edit"
                                        >
                                            Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="empty-state">
                                Belum ada data margin penjualan.
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