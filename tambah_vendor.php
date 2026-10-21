
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'vendor';
$error = '';
$nama = '';
$badanHukum = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_vendor'] ?? '');
    $badanHukum = trim($_POST['badan_hukum'] ?? '');

    if ($nama === '' || $badanHukum === '') {
        $error = 'Nama vendor dan badan hukum wajib diisi.';
    } elseif (mb_strlen($nama, 'UTF-8') > 100) {
        $error = 'Nama vendor maksimal 100 karakter.';
    } elseif (mb_strlen($badanHukum, 'UTF-8') !== 1) {
        $error = 'Badan hukum harus terdiri dari tepat 1 karakter.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO vendor (nama_vendor, badan_hukum, status)
                 VALUES (?, ?, 1)"
            );

            $stmt->execute([$nama, $badanHukum]);

            $_SESSION['pesan_vendor'] = 'Vendor berhasil ditambahkan.';

            header('Location: data_vendor.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Vendor gagal ditambahkan. Silakan periksa kembali data yang dimasukkan.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Vendor | ATK STORE</title>
    <link rel="stylesheet" href="assets/style.css?v=101">
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="page-header">
        <div>
            <h1>Tambah Vendor</h1>
            <p>Tambahkan pemasok baru.</p>
        </div>
    </header>

    <section class="card">
        <?php if ($error !== ''): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="nama_vendor">Nama Vendor</label>
                <input
                    class="form-control"
                    type="text"
                    id="nama_vendor"
                    name="nama_vendor"
                    maxlength="100"
                    value="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="badan_hukum">Badan Hukum</label>
                <input
                    class="form-control"
                    type="text"
                    id="badan_hukum"
                    name="badan_hukum"
                    maxlength="1"
                    value="<?= htmlspecialchars($badanHukum, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Tambah</button>
                <a class="btn btn-secondary" href="data_vendor.php">Batal</a>
            </div>
        </form>
    </section>
</main>

</body>
</html>
