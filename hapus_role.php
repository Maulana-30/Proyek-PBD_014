
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

// Penghapusan hanya boleh melalui POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_role.php');
    exit;
}

$idrole = filter_input(
    INPUT_POST,
    'idrole',
    FILTER_VALIDATE_INT
);

if (!$idrole || $idrole < 1) {
    $_SESSION['pesan_role'] = 'ID role tidak valid.';
    header('Location: data_role.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "DELETE FROM role WHERE idrole = ?"
    );

    $stmt->execute([$idrole]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['pesan_role'] = 'Role berhasil dihapus.';
    } else {
        $_SESSION['pesan_role'] = 'Data role tidak ditemukan.';
    }

} catch (PDOException $e) {
    // Role mungkin masih digunakan oleh data user
    if ($e->getCode() === '23000') {
        $_SESSION['pesan_role'] =
            'Role tidak dapat dihapus karena masih digunakan oleh data pengguna.';
    } else {
        $_SESSION['pesan_role'] =
            'Terjadi kesalahan saat menghapus role.';
    }
}

header('Location: data_role.php');
exit;