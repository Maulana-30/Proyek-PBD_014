
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'retur';
$pesan = $_SESSION['pesan_retur'] ?? '';
unset($_SESSION['pesan_retur']);

$idretur = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idretur || $idretur <= 0) {
    $_SESSION['pesan_retur'] = 'ID retur tidak valid.';
    header('Location: data_retur.php');
    exit;
}

// Mengambil informasi utama retur
$stmt = $pdo->prepare("
    SELECT
        r.idretur,
        r.created_at,
        r.idpenerimaan,
        u.username
    FROM retur r
    LEFT JOIN `user` u
        ON u.iduser = r.iduser
    WHERE r.idretur = ?
");

$stmt->execute([$idretur]);
$retur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$retur) {
    $_SESSION['pesan_retur'] = 'Data retur tidak ditemukan.';
    header('Location: data_retur.php');
    exit;
}

// Mengambil daftar barang yang diretur
$stmt = $pdo->prepare("
    SELECT
        dr.iddetail_retur,
        dr.jumlah,
        dr.alasan,
        dp.iddetail_penerimaan,
        dp.barang_idbarang,
        b.nama AS nama_barang,
        dp.jumlah_terima,
        dp.harga_satuan_terima
    FROM detail_retur dr
    JOIN detail_penerimaan dp
        ON dp.iddetail_penerimaan = dr.iddetail_penerimaan
    JOIN barang b
        ON b.idbarang = dp.barang_idbarang
    WHERE dr.idretur = ?
    ORDER BY dr.iddetail_retur ASC
");

$stmt->execute([$idretur]);
$detailRetur = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Menghitung total unit barang yang diretur
$totalBarang = 0;

foreach ($detailRetur as $detail) {
    $totalBarang += (int) $detail['jumlah'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Retur | ATK STORE</title>
    <link rel="stylesheet" href="assets/style.css?v=113">
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="page-header">
        <div>
            <h1>Detail Retur</h1>
            <p>Informasi lengkap pengembalian barang ATK STORE.</p>
        </div>
    </header>

    <?php if ($pesan !== ''): ?>
        <div class="alert">
            <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <h2>Informasi Retur #<?= (int) $retur['idretur'] ?></h2>
        </div>

        <div style="padding: 20px;">
            <div class="table-responsive">
                <table>
                    <tbody>
                        <tr>
                            <th>ID Retur</th>
                            <td><?= (int) $retur['idretur'] ?></td>
                        </tr>
                        <tr>
                            <th>Tanggal Retur</th>
                            <td>
                                <?= htmlspecialchars(
                                    (string) $retur['created_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>ID Penerimaan</th>
                            <td><?= (int) $retur['idpenerimaan'] ?></td>
                        </tr>
                        <tr>
                            <th>Petugas</th>
                            <td>
                                <?= htmlspecialchars(
                                    $retur['username'] ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Barang Diretur</th>
                            <td><?= $totalBarang ?> unit</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h2>Daftar Barang Retur</h2>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Detail</th>
                        <th>Nama Barang</th>
                        <th>Jumlah Diterima</th>
                        <th>Jumlah Retur</th>
                        <th>Harga Satuan</th>
                        <th>Alasan Retur</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($detailRetur)): ?>
                        <?php foreach ($detailRetur as $index => $detail): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>

                                <td>
                                    <?= (int) $detail['iddetail_retur'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $detail['nama_barang'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) $detail['jumlah_terima'] ?>
                                </td>

                                <td>
                                    <?= (int) $detail['jumlah'] ?>
                                </td>

                                <td>
                                    Rp <?= number_format(
                                        (float) $detail['harga_satuan_terima'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $detail['alasan'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                Belum ada detail barang untuk retur ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="padding: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="data_retur.php" class="btn btn-secondary">
                Kembali
            </a>

            <a
                href="edit_retur.php?id=<?= (int) $retur['idretur'] ?>"
                class="btn btn-primary"
            >
                Edit Retur
            </a>
        </div>
    </section>
</main>

</body>
</html>
