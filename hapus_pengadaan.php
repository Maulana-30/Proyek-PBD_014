
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_pengadaan.php');
    exit;
}

$idpengadaan = filter_input(
    INPUT_POST,
    'idpengadaan',
    FILTER_VALIDATE_INT
);

if (!$idpengadaan || $idpengadaan < 1) {
    $_SESSION['pesan_pengadaan'] = 'ID pengadaan tidak valid.';
    header('Location: data_pengadaan.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Pastikan transaksi pengadaan tersedia.
    $stmt = $pdo->prepare(
        'SELECT idpengadaan, status
         FROM pengadaan
         WHERE idpengadaan = ?
         FOR UPDATE'
    );
    $stmt->execute([$idpengadaan]);
    $pengadaan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pengadaan) {
        throw new RuntimeException('Transaksi pengadaan tidak ditemukan.');
    }

    // Jangan hapus pengadaan yang sudah memiliki penerimaan.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM penerimaan
         WHERE idpengadaan = ?'
    );
    $stmt->execute([$idpengadaan]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'Pengadaan tidak dapat dihapus karena sudah memiliki penerimaan.'
        );
    }

    // Pastikan pengadaan belum digunakan dalam kartu stok.
    // Nama kolom idtransaksi saja tidak cukup untuk memastikan
    // bahwa suatu baris kartu stok berasal dari pengadaan ini.
    // Karena itu, jangan menghapus transaksi yang sudah diproses
    // atau selesai sebelum aturan status proyek dipastikan.
    if ((string) $pengadaan['status'] !== '0') {
        throw new RuntimeException(
            'Hanya pengadaan berstatus Menunggu yang dapat dihapus.'
        );
    }

    // Hapus detail terlebih dahulu karena memiliki foreign key
    // yang mengacu pada tabel pengadaan.
    $stmt = $pdo->prepare(
        'DELETE FROM detail_pengadaan WHERE idpengadaan = ?'
    );
    $stmt->execute([$idpengadaan]);

    // Hapus transaksi pengadaan setelah detailnya terhapus.
    $stmt = $pdo->prepare(
        'DELETE FROM pengadaan WHERE idpengadaan = ?'
    );
    $stmt->execute([$idpengadaan]);

    $pdo->commit();

    $_SESSION['pesan_pengadaan'] = 'Transaksi pengadaan berhasil dihapus.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['pesan_pengadaan'] = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Pengadaan gagal dihapus karena masih digunakan atau terjadi kesalahan.';
}

header('Location: data_pengadaan.php');
exit;
