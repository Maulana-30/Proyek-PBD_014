
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_vendor.php');
    exit;
}

require 'config/database.php';

$id = filter_input(
    INPUT_POST,
    'idvendor',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    $_SESSION['pesan_vendor'] = 'ID vendor tidak valid.';
    header('Location: data_vendor.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Memastikan vendor tersedia dan mengunci data selama pemeriksaan.
    $stmt = $pdo->prepare("
        SELECT idvendor, nama_vendor
        FROM vendor
        WHERE idvendor = ?
        FOR UPDATE
    ");

    $stmt->execute([$id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        throw new RuntimeException(
            'Vendor tidak ditemukan.'
        );
    }

    // Memeriksa apakah vendor sudah digunakan dalam pengadaan.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM pengadaan
        WHERE vendor_idvendor = ?
    ");

    $stmt->execute([$id]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'Vendor tidak dapat dihapus karena sudah digunakan dalam transaksi pengadaan. Ubah status vendor menjadi Tidak Aktif jika sudah tidak digunakan.'
        );
    }

    // Menghapus vendor hanya jika tidak digunakan dalam pengadaan.
    $stmt = $pdo->prepare("
        DELETE FROM vendor
        WHERE idvendor = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Vendor gagal dihapus.'
        );
    }

    $pdo->commit();

    $_SESSION['pesan_vendor'] =
        'Vendor berhasil dihapus.';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof RuntimeException) {
        $_SESSION['pesan_vendor'] = $e->getMessage();
    } else {
        error_log('Hapus vendor: ' . $e->getMessage());

        $_SESSION['pesan_vendor'] =
            'Vendor tidak dapat dihapus karena masih digunakan oleh data terkait atau terjadi kesalahan database.';
    }
}

header('Location: data_vendor.php');
exit;
