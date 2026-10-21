```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_user.php');
    exit;
}

require 'config/database.php';

$id = filter_input(INPUT_POST, 'iduser', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    $_SESSION['pesan_user'] = 'ID user tidak valid.';
    header('Location: data_user.php');
    exit;
}

if ((int) $id === (int) $_SESSION['iduser']) {
    $_SESSION['pesan_user'] = 'Akun yang sedang login tidak dapat dihapus.';
    header('Location: data_user.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $cekUser = $pdo->prepare(
        "SELECT iduser
         FROM `user`
         WHERE iduser = ?
         FOR UPDATE"
    );
    $cekUser->execute([$id]);

    if (!$cekUser->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        $_SESSION['pesan_user'] = 'User tidak ditemukan.';
        header('Location: data_user.php');
        exit;
    }

    $relasi = [
        'margin_penjualan' => 'iduser',
        'penerimaan'       => 'iduser',
        'pengadaan'        => 'user_iduser',
        'penjualan'        => 'iduser',
        'retur'            => 'iduser',
    ];

    $tabelTerkait = [];

    foreach ($relasi as $tabel => $kolom) {
        $sql = "SELECT COUNT(*) FROM `$tabel` WHERE `$kolom` = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);

        if ((int) $stmt->fetchColumn() > 0) {
            $tabelTerkait[] = $tabel;
        }
    }

    if (!empty($tabelTerkait)) {
        $pdo->rollBack();

        $_SESSION['pesan_user'] =
            'User tidak dapat dihapus karena masih terkait dengan tabel: '
            . implode(', ', $tabelTerkait)
            . '.';

        header('Location: data_user.php');
        exit;
    }

    $hapus = $pdo->prepare(
        "DELETE FROM `user` WHERE iduser = ?"
    );
    $hapus->execute([$id]);

    if ($hapus->rowCount() > 0) {
        $pdo->commit();
        $_SESSION['pesan_user'] = 'User berhasil dihapus.';
    } else {
        $pdo->rollBack();
        $_SESSION['pesan_user'] = 'User tidak ditemukan.';
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Gagal menghapus user: ' . $e->getMessage());

    $_SESSION['pesan_user'] =
        'User gagal dihapus karena masih memiliki relasi atau terjadi kesalahan database.';
}

header('Location: data_user.php');
exit;

