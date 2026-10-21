```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'kartu_stok';
$error = '';

$stokAwal = [
    1 => 1, // Buku Tulis
    2 => 1, // Pulpen Hitam
    3 => 1, // Pensil
    4 => 1, // Buku Gambar
    5 => 1  // Spidol Permanent
];

try {
    $stmtBarang = $pdo->query(
        "SELECT idbarang, nama
         FROM barang
         WHERE status = 1
         ORDER BY idbarang ASC"
    );

    $barangList = $stmtBarang->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $barangList = [];
    $error = 'Data barang gagal dimuat.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    try {
        $pdo->beginTransaction();

        foreach ($stokAwal as $idbarang => $jumlah) {
            // Kunci data barang selama proses pencatatan.
            $stmtBarang = $pdo->prepare(
                "SELECT idbarang
                 FROM barang
                 WHERE idbarang = ?
                   AND status = 1
                 FOR UPDATE"
            );
            $stmtBarang->execute([$idbarang]);

            if (!$stmtBarang->fetchColumn()) {
                throw new Exception(
                    "Barang dengan ID {$idbarang} tidak ditemukan atau tidak aktif."
                );
            }

            // Stok awal memakai idtransaksi = 0.
            // ID transaksi otomatis normal dimulai dari 1.
            $stmtCek = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM kartu_stok
                 WHERE idbarang = ?
                   AND idtransaksi = 0
                   AND jenis_transaksi = 'M'"
            );
            $stmtCek->execute([$idbarang]);

            if ((int) $stmtCek->fetchColumn() > 0) {
                throw new Exception(
                    "Stok awal barang ID {$idbarang} sudah pernah dicatat. Tidak ada data yang disimpan."
                );
            }

            // Jangan membuat stok awal jika barang sudah mempunyai
            // riwayat stok lain.
            $stmtRiwayat = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM kartu_stok
                 WHERE idbarang = ?"
            );
            $stmtRiwayat->execute([$idbarang]);

            if ((int) $stmtRiwayat->fetchColumn() > 0) {
                throw new Exception(
                    "Barang ID {$idbarang} sudah memiliki riwayat kartu stok. Periksa riwayatnya terlebih dahulu."
                );
            }

            $stmtInsert = $pdo->prepare(
                "INSERT INTO kartu_stok
                 (
                    jenis_transaksi,
                    masuk,
                    keluar,
                    stock,
                    idtransaksi,
                    idbarang
                 )
                 VALUES ('M', ?, 0, ?, 0, ?)"
            );

            $stmtInsert->execute([
                $jumlah,
                $jumlah,
                $idbarang
            ]);
        }

        $pdo->commit();

        $_SESSION['pesan_stok'] =
            'Stok awal untuk lima barang berhasil dicatat.';

        header('Location: data_kartu_stok.php');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e instanceof PDOException
            ? 'Stok awal gagal disimpan. Periksa struktur database.'
            : $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Stok Awal | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=105">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Tambah Stok Awal</h1>
            <p>
                Catat persediaan awal barang sebelum transaksi stok berjalan.
            </p>
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

        <p>
            Stok awal pengujian ditetapkan sebanyak satu unit
            untuk setiap barang aktif berikut.
        </p>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Barang</th>
                        <th>Stok Awal</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($barangList) > 0): ?>
                        <?php foreach ($barangList as $i => $barang): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>

                                <td>
                                    <?= htmlspecialchars(
                                        $barang['nama'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= isset($stokAwal[(int) $barang['idbarang']])
                                        ? (int) $stokAwal[(int) $barang['idbarang']]
                                        : 0 ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="empty-state">
                                Tidak ada barang aktif.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($barangList) === 5 && $error === ''): ?>
            <form method="POST"
                  onsubmit="return confirm('Yakin mencatat stok awal untuk lima barang? Pastikan jumlahnya sudah benar.');">

                <div class="form-actions">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Catat Stok Awal
                    </button>

                    <a
                        href="data_barang.php"
                        class="btn btn-secondary"
                    >
                        Batal
                    </a>
                </div>

            </form>
        <?php else: ?>
            <p>
                <small>
                    Pastikan kelima barang aktif tersedia sebelum mencatat stok awal.
                </small>
            </p>
        <?php endif; ?>

    </section>

</main>

</body>
</html>
```