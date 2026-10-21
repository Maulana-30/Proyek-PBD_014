<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_penjualan.php');
    exit;
}

$idPenjualan = filter_input(
    INPUT_POST,
    'idpenjualan',
    FILTER_VALIDATE_INT
);

if (!$idPenjualan || $idPenjualan < 1) {
    $_SESSION['pesan_penjualan'] = 'ID penjualan tidak valid.';
    header('Location: data_penjualan.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Ambil transaksi dan kunci barisnya.
    $stmt = $pdo->prepare(
        "SELECT idpenjualan
         FROM penjualan
         WHERE idpenjualan = ?
         FOR UPDATE"
    );
    $stmt->execute([$idPenjualan]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException(
            'Transaksi penjualan tidak ditemukan.'
        );
    }

    // Ambil detail barang yang akan dikembalikan ke stok.
    $stmt = $pdo->prepare(
        "SELECT idbarang, SUM(jumlah) AS total_jumlah
         FROM detail_penjualan
         WHERE penjualan_idpenjualan = ?
         GROUP BY idbarang
         ORDER BY idbarang"
    );
    $stmt->execute([$idPenjualan]);
    $daftarBarang = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kunci baris barang agar transaksi stok tidak bertabrakan.
    $stmtBarang = $pdo->prepare(
        "SELECT idbarang
         FROM barang
         WHERE idbarang = ?
         FOR UPDATE"
    );

    // Kunci dan baca stok terakhir setiap barang.
    $stmtStok = $pdo->prepare(
        "SELECT stock
         FROM kartu_stok
         WHERE idbarang = ?
         ORDER BY idkartu_stok DESC
         LIMIT 1
         FOR UPDATE"
    );

    $stmtKoreksi = $pdo->prepare(
        "INSERT INTO kartu_stok
            (jenis_transaksi, masuk, keluar, stock,
             created_at, idtransaksi, idbarang)
         VALUES ('M', ?, 0, ?, NOW(), 0, ?)"
    );

    foreach ($daftarBarang as $barang) {
        $idBarang = (int) $barang['idbarang'];
        $jumlahKembali = (int) $barang['total_jumlah'];

        $stmtBarang->execute([$idBarang]);

        if (!$stmtBarang->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                "Barang ID {$idBarang} tidak ditemukan."
            );
        }

        $stmtStok->execute([$idBarang]);
        $stokTerakhir = $stmtStok->fetchColumn();

        if ($stokTerakhir === false) {
            throw new RuntimeException(
                "Catatan stok barang ID {$idBarang} tidak ditemukan."
            );
        }

        $stokBaru = (int) $stokTerakhir + $jumlahKembali;

        // Catat koreksi stok tanpa menghapus riwayat sebelumnya.
        $stmtKoreksi->execute([
            $jumlahKembali,
            $stokBaru,
            $idBarang
        ]);
    }

    // Hapus detail terlebih dahulu karena memiliki foreign key.
    $stmt = $pdo->prepare(
        "DELETE FROM detail_penjualan
         WHERE penjualan_idpenjualan = ?"
    );
    $stmt->execute([$idPenjualan]);

    // Setelah detail dihapus, hapus transaksi penjualan.
    $stmt = $pdo->prepare(
        "DELETE FROM penjualan
         WHERE idpenjualan = ?"
    );
    $stmt->execute([$idPenjualan]);

    $pdo->commit();

    $_SESSION['pesan_penjualan'] =
        'Transaksi penjualan berhasil dihapus dan stok telah dikoreksi.';

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Gagal menghapus penjualan ID ' .
        $idPenjualan . ': ' . $e->getMessage()
    );

    $_SESSION['pesan_penjualan'] =
        'Transaksi gagal dihapus. Periksa kembali data dan relasi database.';
}

header('Location: data_penjualan.php');
exit;