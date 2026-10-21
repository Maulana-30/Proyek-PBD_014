
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_satuan.php');
    exit;
}

require 'config/database.php';

$id = filter_input(
    INPUT_POST,
    'idsatuan',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    $_SESSION['pesan_satuan'] = 'ID satuan tidak valid.';
    header('Location: data_satuan.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Pastikan satuan tersedia dan kunci selama pemeriksaan.
    $stmt = $pdo->prepare("
        SELECT idsatuan, nama_satuan
        FROM satuan
        WHERE idsatuan = ?
        FOR UPDATE
    ");

    $stmt->execute([$id]);
    $satuan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$satuan) {
        throw new RuntimeException(
            'Satuan tidak ditemukan.'
        );
    }

    // Periksa apakah satuan masih digunakan oleh barang.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM barang
        WHERE idsatuan = ?
    ");

    $stmt->execute([$id]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'Satuan tidak dapat dihapus karena masih digunakan oleh barang. Ubah status satuan menjadi Tidak Aktif jika sudah tidak digunakan.'
        );
    }

    // Hapus satuan hanya jika tidak digunakan oleh barang.
    $stmt = $pdo->prepare("
        DELETE FROM satuan
        WHERE idsatuan = ?
    ");

    $stmt->execute([$id]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Satuan gagal dihapus.'
        );
    }

    $pdo->commit();

    $_SESSION['pesan_satuan'] =
        'Satuan berhasil dihapus.';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof RuntimeException) {
        $_SESSION['pesan_satuan'] = $e->getMessage();
    } else {
        error_log('Hapus satuan: ' . $e->getMessage());

        $_SESSION['pesan_satuan'] =
            'Satuan tidak dapat dihapus karena masih digunakan oleh data terkait atau terjadi kesalahan database.';
    }
}

header('Location: data_satuan.php');
exit;
