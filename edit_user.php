
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'user';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: data_user.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT iduser, username, idrole
     FROM `user`
     WHERE iduser = ?"
);
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['pesan_user'] = 'User tidak ditemukan.';
    header('Location: data_user.php');
    exit;
}

$roles = $pdo->query(
    "SELECT idrole, nama_role
     FROM role
     ORDER BY idrole ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$username = $user['username'];
$idrole = (int) $user['idrole'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $idroleInput = filter_input(
        INPUT_POST,
        'idrole',
        FILTER_VALIDATE_INT
    );
    $passwordBaru = $_POST['password'] ?? '';

    if (
        $username === '' ||
        mb_strlen($username, 'UTF-8') > 45
    ) {
        $error = 'Username wajib diisi dan maksimal 45 karakter.';
    } elseif (
        $idroleInput === false ||
        $idroleInput === null ||
        $idroleInput <= 0
    ) {
        $error = 'Silakan pilih role yang valid.';
    } elseif (
        $passwordBaru !== '' &&
        strlen($passwordBaru) < 6
    ) {
        $error = 'Password baru minimal 6 karakter.';
    } else {
        try {
            $cek = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM `user`
                 WHERE username = ? AND iduser <> ?"
            );
            $cek->execute([$username, $id]);

            if ((int) $cek->fetchColumn() > 0) {
                $error = 'Username sudah digunakan oleh user lain.';
            } else {
                $cekRole = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM role
                     WHERE idrole = ?"
                );
                $cekRole->execute([$idroleInput]);

                if ((int) $cekRole->fetchColumn() === 0) {
                    $error = 'Role tidak ditemukan.';
                } else {
                    if ($passwordBaru !== '') {
                        $hash = password_hash(
                            $passwordBaru,
                            PASSWORD_DEFAULT
                        );

                        $update = $pdo->prepare(
                            "UPDATE `user`
                             SET username = ?, password = ?, idrole = ?
                             WHERE iduser = ?"
                        );

                        $update->execute([
                            $username,
                            $hash,
                            $idroleInput,
                            $id
                        ]);
                    } else {
                        $update = $pdo->prepare(
                            "UPDATE `user`
                             SET username = ?, idrole = ?
                             WHERE iduser = ?"
                        );

                        $update->execute([
                            $username,
                            $idroleInput,
                            $id
                        ]);
                    }

                    if ((int) $_SESSION['iduser'] === (int) $id) {
                        $_SESSION['username'] = $username;
                    }

                    $_SESSION['pesan_user'] =
                        'User berhasil diperbarui.';

                    header('Location: data_user.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = 'User gagal diperbarui. Periksa kembali data yang dimasukkan.';
        }
    }

    if (
        $idroleInput !== false &&
        $idroleInput !== null &&
        $idroleInput > 0
    ) {
        $idrole = $idroleInput;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit User | ATK STORE</title>
    <link rel="stylesheet" href="assets/style.css?v=101">
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="page-header">
        <div>
            <h1>Edit User</h1>
            <p>Perbarui akun dan hak akses pengguna.</p>
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
                <label for="password">Password Baru (opsional)</label>
                <input
                    class="form-control"
                    type="password"
                    id="password"
                    name="password"
                    minlength="6"
                    autocomplete="new-password"
                >
                <small>Kosongkan jika tidak ingin mengganti password.</small>
            </div>

            <div class="form-group">
                <label for="idrole">Role</label>
                <select
                    class="form-control"
                    id="idrole"
                    name="idrole"
                    required
                >
                    <option value="">Pilih role</option>

                    <?php foreach ($roles as $role): ?>
                        <option
                            value="<?= (int) $role['idrole'] ?>"
                            <?= (int) $idrole === (int) $role['idrole'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($role['nama_role'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">
                    Simpan Perubahan
                </button>
                <a class="btn btn-secondary" href="data_user.php">
                    Batal
                </a>
            </div>
        </form>
    </section>
</main>

</body>
</html>
