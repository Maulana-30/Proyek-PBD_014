
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'barang';
$error = '';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header('Location: data_barang.php');
    exit;
}

// Mengambil data barang yang akan diedit.
$stmt = $pdo->prepare("
    SELECT *
    FROM barang
    WHERE idbarang = ?
");

$stmt->execute([$id]);

$barang = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$barang) {
    $_SESSION['pesan_barang'] = 'Barang tidak ditemukan.';
    header('Location: data_barang.php');
    exit;
}

// Mengambil daftar satuan.
$satuanList = $pdo->query("
    SELECT idsatuan, nama_satuan
    FROM satuan
    ORDER BY nama_satuan
")->fetchAll(PDO::FETCH_ASSOC);

// Memproses formulir edit.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenisInput = $_POST['jenis'] ?? '';
    $namaInput = $_POST['nama'] ?? '';
    $satuanInput = $_POST['idsatuan'] ?? '';
    $hargaInput = $_POST['harga'] ?? '';
    $statusInput = $_POST['status'] ?? '';

    $jenis = is_scalar($jenisInput)
        ? trim((string) $jenisInput)
        : '';

    $nama = is_scalar($namaInput)
        ? trim((string) $namaInput)
        : '';

    $idsatuan = filter_var(
        $satuanInput,
        FILTER_VALIDATE_INT
    );

    $harga = filter_var(
        $hargaInput,
        FILTER_VALIDATE_FLOAT
    );

    $status = filter_var(
        $statusInput,
        FILTER_VALIDATE_INT
    );

    // Validasi masukan.
    if (
        !in_array($jenis, ['A', 'B'], true)
        || $nama === ''
        || mb_strlen($nama) > 100
        || $idsatuan === false
        || $idsatuan === null
        || $idsatuan < 1
        || $harga === false
        || $harga === null
        || !is_finite((float) $harga)
        || $harga < 0
        || !in_array($status, [0, 1], true)
    ) {
        $error = 'Periksa kembali semua isian.';
    } else {
        // Pastikan satuan tersedia.
        $cek = $pdo->prepare("
            SELECT COUNT(*)
            FROM satuan
            WHERE idsatuan = ?
        ");

        $cek->execute([$idsatuan]);

        if ((int) $cek->fetchColumn() === 0) {
            $error = 'Satuan tidak ditemukan.';
        } else {
            try {
                $pdo->beginTransaction();

                // Mengunci data barang selama pemeriksaan dan pembaruan.
                $stmtKunci = $pdo->prepare("
                    SELECT *
                    FROM barang
                    WHERE idbarang = ?
                    FOR UPDATE
                ");

                $stmtKunci->execute([$id]);

                $barangLama = $stmtKunci->fetch(
                    PDO::FETCH_ASSOC
                );

                if (!$barangLama) {
                    throw new RuntimeException(
                        'Barang tidak ditemukan.'
                    );
                }

                // Memeriksa riwayat pengadaan, penerimaan,
                // dan kartu stok.
                $cekRiwayat = $pdo->prepare("
                    SELECT
                        EXISTS (
                            SELECT 1
                            FROM detail_pengadaan
                            WHERE idbarang = ?
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM detail_penerimaan
                            WHERE barang_idbarang = ?
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM kartu_stok
                            WHERE idbarang = ?
                        ) AS pernah_dipakai
                ");

                $cekRiwayat->execute([
                    $id,
                    $id,
                    $id
                ]);

                $pernahDipakai =
                    (int) $cekRiwayat->fetchColumn() === 1;

                // Identitas barang tidak boleh berubah
                // jika barang sudah memiliki riwayat transaksi.
                if (
                    $pernahDipakai
                    && (
                        $jenis !== (string) $barangLama['jenis']
                        || $nama !== (string) $barangLama['nama']
                        || (int) $idsatuan
                            !== (int) $barangLama['idsatuan']
                    )
                ) {
                    throw new RuntimeException(
                        'Jenis, nama, dan satuan barang tidak dapat diubah karena sudah memiliki riwayat transaksi.'
                    );
                }

                // Memperbarui data barang.
                $update = $pdo->prepare("
                    UPDATE barang
                    SET
                        jenis = ?,
                        nama = ?,
                        idsatuan = ?,
                        harga = ?,
                        status = ?
                    WHERE idbarang = ?
                ");

                $update->execute([
                    $jenis,
                    $nama,
                    $idsatuan,
                    $harga,
                    $status,
                    $id
                ]);

                $pdo->commit();

                $_SESSION['pesan_barang'] =
                    'Barang berhasil diperbarui.';

                header('Location: data_barang.php');
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($e instanceof RuntimeException) {
                    $error = $e->getMessage();
                } else {
                    error_log(
                        'Edit barang: ' . $e->getMessage()
                    );

                    $error =
                        'Barang gagal diperbarui. Periksa database.';
                }
            }
        }
    }
}

// Mempertahankan nilai formulir apabila validasi gagal.
$nilai = $_POST ?: $barang;

// Fungsi untuk menampilkan nilai dengan aman.
function h($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Edit Barang | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=101">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Edit Barang</h1>
            <p>Perbarui informasi barang.</p>
        </div>
    </header>

    <section class="card">

        <?php if ($error !== ''): ?>

            <div class="alert alert-error">
                <?= h($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="form-group">
                <label for="jenis">Jenis Barang</label>

                <select
                    class="form-control"
                    id="jenis"
                    name="jenis"
                    required
                >
                    <?php foreach (['A', 'B'] as $opsiJenis): ?>

                        <option
                            value="<?= h($opsiJenis) ?>"
                            <?= ($nilai['jenis'] ?? '') === $opsiJenis
                                ? 'selected'
                                : '' ?>
                        >
                            <?= h($opsiJenis) ?>
                        </option>

                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="nama">Nama Barang</label>

                <input
                    class="form-control"
                    type="text"
                    id="nama"
                    name="nama"
                    maxlength="100"
                    value="<?= h($nilai['nama'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="idsatuan">Satuan</label>

                <select
                    class="form-control"
                    id="idsatuan"
                    name="idsatuan"
                    required
                >
                    <?php foreach ($satuanList as $s): ?>

                        <option
                            value="<?= (int) $s['idsatuan'] ?>"
                            <?= (string) ($nilai['idsatuan'] ?? '')
                                === (string) $s['idsatuan']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= h($s['nama_satuan']) ?>
                        </option>

                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="harga">Harga (Rp)</label>

                <input
                    class="form-control"
                    type="number"
                    id="harga"
                    name="harga"
                    min="0"
                    step="0.01"
                    value="<?= h($nilai['harga'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="status">Status</label>

                <select
                    class="form-control"
                    id="status"
                    name="status"
                    required
                >
                    <option
                        value="1"
                        <?= (string) ($nilai['status'] ?? '1') === '1'
                            ? 'selected'
                            : '' ?>
                    >
                        Aktif
                    </option>

                    <option
                        value="0"
                        <?= (string) ($nilai['status'] ?? '1') === '0'
                            ? 'selected'
                            : '' ?>
                    >
                        Tidak Aktif
                    </option>
                </select>
            </div>

            <div class="form-actions">
                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Simpan Perubahan
                </button>

                <a
                    class="btn btn-secondary"
                    href="data_barang.php"
                >
                    Batal
                </a>
            </div>

        </form>

    </section>

</main>

</body>
</html>
