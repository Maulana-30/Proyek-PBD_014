
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_barang.php');
    exit;
}

require 'config/database.php';

$id = filter_input(
    INPUT_POST,
    'idbarang',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    $_SESSION['pesan_barang'] = 'ID barang tidak valid.';
    header('Location: data_barang.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Pastikan barang tersedia dan kunci data selama pemeriksaan.
    $stmt = $pdo->prepare("
        SELECT idbarang, nama
        FROM barang
        WHERE idbarang = ?
        FOR UPDATE
    ");

    $stmt->execute([$id]);
    $barang = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$barang) {
        throw new RuntimeException(
            'Barang tidak ditemukan.'
        );
    }

    // Periksa riwayat pengadaan.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM detail_pengadaan
        WHERE idbarang = ?
    ");

    $stmt->execute([$id]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'Barang tidak dapat dihapus karena sudah digunakan dalam transaksi pengadaan.'
        );
    }

    // Periksa riwayat penerimaan.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM detail_penerimaan
        WHERE barang_idbarang = ?
    ");

    $stmt->execute([$id]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'Barang tidak dapat dihapus karena sudah memiliki riwayat penerimaan.'
        );
    }

    // Periksa riwayat kartu stok.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM kartu_stok
        WHERE idbarang = ?
    ");

    $stmt->execute([$id]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'Barang tidak dapat dihapus karena sudah memiliki riwayat kartu stok.'
        );
    }

    // Hapus hanya jika tidak ditemukan riwayat terkait.
    $stmt = $pdo->prepare("
        DELETE FROM barang
        WHERE idbarang = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Barang gagal dihapus.'
        );
    }

    $pdo->commit();

    $_SESSION['pesan_barang'] =
        'Barang berhasil dihapus.';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof RuntimeException) {
        $_SESSION['pesan_barang'] = $e->getMessage();
    } else {
        error_log('Hapus barang: ' . $e->getMessage());

        $_SESSION['pesan_barang'] =
            'Barang tidak dapat dihapus karena masih digunakan dalam data terkait atau terjadi kesalahan database.';
    }
}

header('Location: data_barang.php');
exit;
