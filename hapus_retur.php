
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$idretur = filter_input(INPUT_POST, 'idretur', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$idretur || $idretur <= 0) {
    $_SESSION['pesan_retur'] = 'Permintaan hapus retur tidak valid.';
    header('Location: data_retur.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Pastikan retur tersedia dan kunci barisnya
    $stmt = $pdo->prepare("
        SELECT idretur
        FROM retur
        WHERE idretur = ?
        FOR UPDATE
    ");
    $stmt->execute([$idretur]);

    if (!$stmt->fetch()) {
        throw new Exception('Data retur tidak ditemukan.');
    }

    // Hapus detail terlebih dahulu
    $stmt = $pdo->prepare("
        DELETE FROM detail_retur
        WHERE idretur = ?
    ");
    $stmt->execute([$idretur]);

    // Hapus data utama retur
    $stmt = $pdo->prepare("
        DELETE FROM retur
        WHERE idretur = ?
    ");
    $stmt->execute([$idretur]);

    $pdo->commit();

    $_SESSION['pesan_retur'] = 'Data retur berhasil dihapus.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['pesan_retur'] = $e instanceof PDOException
        ? 'Data retur gagal dihapus. Periksa relasi foreign key database.'
        : $e->getMessage();
}

header('Location: data_retur.php');
exit;
