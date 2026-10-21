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

$idrole = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idrole || $idrole < 1) {
    header('Location: data_role.php');
    exit;
}

// Ambil data role
$stmt = $pdo->prepare(
    "SELECT idrole, nama_role FROM role WHERE idrole = ?"
);
$stmt->execute([$idrole]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    $_SESSION['pesan_role'] = 'Data role tidak ditemukan.';
    header('Location: data_role.php');
    exit;
}

$namaRole = $role['nama_role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaRole = trim($_POST['nama_role'] ?? '');

    if ($namaRole === '') {
        $error = 'Nama role wajib diisi.';
    } else {
        // Cek nama role agar tidak duplikat
        $cek = $pdo->prepare(
            "SELECT COUNT(*) FROM role
             WHERE nama_role = ? AND idrole <> ?"
        );
        $cek->execute([$namaRole, $idrole]);

        if ($cek->fetchColumn() > 0) {
            $error = 'Nama role tersebut sudah digunakan.';
        } else {
            $update = $pdo->prepare(
                "UPDATE role SET nama_role = ? WHERE idrole = ?"
            );
            $update->execute([$namaRole, $idrole]);

            $_SESSION['pesan_role'] = 'Role berhasil diperbarui.';
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

    <title>Edit Role | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=106">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Edit Role</h1>
            <p>Ubah nama role yang tersimpan.</p>
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

        <form method="POST" action="edit_role.php?id=<?= (int) $idrole ?>">

            <div class="form-group">
                <label for="nama_role">Nama Role</label>

                <input
                    type="text"
                    id="nama_role"
                    name="nama_role"
                    class="form-control"
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
                    Perbarui
                </button>
            </div>

        </form>

    </section>

</main>

</body>
</html>
```