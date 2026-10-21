```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'role';
$error = '';
$namaRole = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaRole = trim($_POST['nama_role'] ?? '');

    if ($namaRole === '') {
        $error = 'Nama role wajib diisi.';
    } else {
        // Memeriksa apakah nama role sudah tersedia
        $cek = $pdo->prepare(
            "SELECT COUNT(*) FROM role WHERE nama_role = ?"
        );
        $cek->execute([$namaRole]);

        if ($cek->fetchColumn() > 0) {
            $error = 'Nama role tersebut sudah tersedia.';
        } else {
            $sql = "INSERT INTO role (nama_role) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$namaRole]);

            $_SESSION['pesan_role'] = 'Role berhasil ditambahkan.';
            header('Location: data_role.php');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Role | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=106">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Tambah Role</h1>
            <p>Tambahkan role atau hak akses pengguna.</p>
        </div>
    </header>

    <section class="card">

        <?php if ($error !== ''): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="tambah_role.php">

            <div class="form-group">
                <label for="nama_role">Nama Role</label>

                <input
                    type="text"
                    id="nama_role"
                    name="nama_role"
                    class="form-control"
                    placeholder="Contoh: Admin"
                    value="<?= htmlspecialchars(
                        $namaRole,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    maxlength="100"
                    required
                >
            </div>

            <div class="form-actions">
                <a href="data_role.php" class="btn btn-secondary">
                    Batal
                </a>

                <button type="submit" class="btn btn-primary">
                    Tambah
                </button>
            </div>

        </form>

    </section>

</main>

</body>
</html>
```