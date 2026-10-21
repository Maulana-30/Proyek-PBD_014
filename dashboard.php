
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'dashboard';

$totalBarang = (int) $pdo->query(
    "SELECT COUNT(*) FROM barang"
)->fetchColumn();

$totalSatuan = (int) $pdo->query(
    "SELECT COUNT(*) FROM satuan"
)->fetchColumn();

$totalVendor = (int) $pdo->query(
    "SELECT COUNT(*) FROM vendor"
)->fetchColumn();

$username = $_SESSION['username'] ?? 'Pengguna';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=101">

    <style>
        .welcome-title {
            margin: 0 0 10px;
            color: #0c1b48;
            font-size: 32px;
            font-weight: 700;
            line-height: 1.4;
        }

        .welcome-subtitle {
            margin: 0;
            color: #64748b;
            font-size: 15px;
        }

        @media (max-width: 600px) {
            .welcome-title {
                font-size: 27px;
            }
        }
    </style>
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1 class="welcome-title">
                Selamat datang, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>.
            </h1>

            <p class="welcome-subtitle">
                Berikut ringkasan data ATK STORE.
            </p>
        </div>
    </header>

    <section class="stats-grid">

        <div class="stat-card">
            <p>Total Barang</p>
            <h2><?= $totalBarang ?></h2>
        </div>

        <div class="stat-card">
            <p>Total Satuan</p>
            <h2><?= $totalSatuan ?></h2>
        </div>

        <div class="stat-card">
            <p>Total Vendor</p>
            <h2><?= $totalVendor ?></h2>
        </div>

    </section>

</main>

</body>
</html>
