
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_penerimaan.php');
    exit;
}

$idpenerimaan = filter_input(
    INPUT_POST,
    'idpenerimaan',
    FILTER_VALIDATE_INT
);

if (!$idpenerimaan || $idpenerimaan < 1) {
    $_SESSION['pesan_penerimaan'] =
        'ID penerimaan tidak valid.';

    header('Location: data_penerimaan.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Mengunci penerimaan selama proses penghapusan.
    $stmt = $pdo->prepare("
        SELECT idpenerimaan, status
        FROM penerimaan
        WHERE idpenerimaan = ?
        FOR UPDATE
    ");

    $stmt->execute([$idpenerimaan]);

    $penerimaan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$penerimaan) {
        throw new RuntimeException(
            'Data penerimaan tidak ditemukan.'
        );
    }

    // Hanya penerimaan berstatus Menunggu yang boleh dihapus.
    if ((string) $penerimaan['status'] !== '0') {
        throw new RuntimeException(
            'Penerimaan tidak dapat dihapus karena statusnya bukan Menunggu.'
        );
    }

    // Periksa apakah penerimaan memiliki retur.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM retur
        WHERE idpenerimaan = ?
    ");

    $stmt->execute([$idpenerimaan]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'Penerimaan tidak dapat dihapus karena sudah memiliki data retur.'
        );
    }

    // Periksa hubungan dengan detail retur.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM detail_retur AS dr
        INNER JOIN detail_penerimaan AS dp
            ON dp.iddetail_penerimaan = dr.iddetail_penerimaan
        WHERE dp.idpenerimaan = ?
    ");

    $stmt->execute([$idpenerimaan]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'Penerimaan tidak dapat dihapus karena detailnya digunakan dalam retur.'
        );
    }

    // Jangan hapus penerimaan yang masih memiliki rincian barang.
    // Penghapusan detail tidak otomatis membatalkan Trigger INSERT.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM detail_penerimaan
        WHERE idpenerimaan = ?
    ");

    $stmt->execute([$idpenerimaan]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'Penerimaan memiliki detail barang. Data tidak dapat dihapus melalui halaman ini agar catatan stok tetap konsisten.'
        );
    }

    // Pastikan tidak ada catatan barang masuk terkait transaksi ini.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM kartu_stok
        WHERE idtransaksi = ?
          AND jenis_transaksi = 'M'
    ");

    $stmt->execute([$idpenerimaan]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException(
            'Penerimaan masih memiliki catatan kartu stok. Periksa riwayat stok sebelum melakukan penghapusan.'
        );
    }

    // Hapus hanya header kosong yang memenuhi seluruh pemeriksaan.
    $stmt = $pdo->prepare("
        DELETE FROM penerimaan
        WHERE idpenerimaan = ?
          AND status = '0'
    ");

    $stmt->execute([$idpenerimaan]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Penerimaan gagal dihapus. Silakan periksa kembali datanya.'
        );
    }

    $pdo->commit();

    $_SESSION['pesan_penerimaan'] =
        'Data penerimaan berhasil dihapus.';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['pesan_penerimaan'] =
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Penerimaan gagal dihapus. Periksa hubungan data dan kartu stok.';
}

header('Location: data_penerimaan.php');
exit;
