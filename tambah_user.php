
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'user';
$error = '';
$username = '';
$idrole = '';

$roles = $pdo->query(
    "SELECT idrole, nama_role FROM role ORDER BY idrole ASC"
)->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $idrole = filter_input(INPUT_POST, 'idrole', FILTER_VALIDATE_INT);

    if ($username === '' || mb_strlen($username, 'UTF-8') > 45) {
        $error = 'Username wajib diisi dan maksimal 45 karakter.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($idrole === false || $idrole === null || $idrole <= 0) {
        $error = 'Silakan pilih role yang valid.';
    } else {
        try {
            $cek = $pdo->prepare(
                "SELECT COUNT(*) FROM `user` WHERE username = ?"
            );
            $cek->execute([$username]);

            $cekRole = $pdo->prepare(
                "SELECT COUNT(*) FROM role WHERE idrole = ?"
            );
            $cekRole->execute([$idrole]);

            if ((int) $cek->fetchColumn() > 0) {
                $error = 'Username sudah digunakan.';
            } elseif ((int) $cekRole->fetchColumn() === 0) {
                $error = 'Role tidak ditemukan.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    "INSERT INTO `user` (username, password, idrole)
                     VALUES (?, ?, ?)"
                );

                $stmt->execute([$username, $hash, $idrole]);

                $_SESSION['pesan_user'] = 'User berhasil ditambahkan.';

                header('Location: data_user.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'User gagal ditambahkan. Periksa kembali data dan koneksi database.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah User | ATK STORE</title>
    <link rel="stylesheet" href="assets/style.css?v=101">
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="page-header">
        <div>
            <h1>Tambah User</h1>
            <p>Buat akun pengguna baru.</p>
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
                <label for="username">Username</label>
                <input
                    class="form-control"
                    type="text"
                    id="username"
                    name="username"
                    maxlength="45"
                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    class="form-control"
                    type="password"
                    id="password"
                    name="password"
                    minlength="6"
                    required
                >
            </div>

            <div class="form-group">
                <label for="idrole">Role</label>
                <select class="form-control" id="idrole" name="idrole" required>
                    <option value="">Pilih role</option>

                    <?php foreach ($roles as $role): ?>
                        <option
                            value="<?= (int) $role['idrole'] ?>"
                            <?= (string) $idrole === (string) $role['idrole'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($role['nama_role'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Tambah</button>
                <a class="btn btn-secondary" href="data_user.php">Batal</a>
            </div>
        </form>
    </section>
</main>

</body>
</html>
