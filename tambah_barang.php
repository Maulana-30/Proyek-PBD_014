
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'barang';
$error = '';

$jenisTerpilih = '';
$namaTerpilih = '';
$idsatuanTerpilih = '';
$hargaTerpilih = '';

function h($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Mengambil daftar satuan yang aktif.
$satuanList = $pdo->query("
    SELECT idsatuan, nama_satuan
    FROM satuan
    WHERE status = 1
    ORDER BY nama_satuan
")->fetchAll(PDO::FETCH_ASSOC);

// Memproses formulir tambah barang.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenisInput = $_POST['jenis'] ?? '';
    $namaInput = $_POST['nama'] ?? '';
    $idsatuanInput = $_POST['idsatuan'] ?? '';
    $hargaInput = $_POST['harga'] ?? '';

    // Mempertahankan nilai input jika validasi gagal.
    $jenisTerpilih = is_scalar($jenisInput)
        ? trim((string) $jenisInput)
        : '';

    $namaTerpilih = is_scalar($namaInput)
        ? trim((string) $namaInput)
        : '';

    $idsatuanTerpilih = is_scalar($idsatuanInput)
        ? trim((string) $idsatuanInput)
        : '';

    $hargaTerpilih = is_scalar($hargaInput)
        ? trim((string) $hargaInput)
        : '';

    try {
        // Validasi jenis barang.
        if (!in_array($jenisTerpilih, ['A', 'B'], true)) {
            throw new RuntimeException(
                'Jenis barang harus dipilih.'
            );
        }

        // Validasi nama barang.
        if ($namaTerpilih === '') {
            throw new RuntimeException(
                'Nama barang tidak boleh kosong.'
            );
        }

        if (mb_strlen($namaTerpilih) > 100) {
            throw new RuntimeException(
                'Nama barang maksimal 100 karakter.'
            );
        }

        // Validasi ID satuan.
        $idsatuan = filter_var(
            $idsatuanTerpilih,
            FILTER_VALIDATE_INT
        );

        if (
            $idsatuan === false
            || $idsatuan === null
            || $idsatuan < 1
        ) {
            throw new RuntimeException(
                'Silakan pilih satuan yang valid.'
            );
        }

        // Validasi harga sebelum dikonversi.
        if (
            $hargaTerpilih === ''
            || !is_numeric($hargaTerpilih)
        ) {
            throw new RuntimeException(
                'Harga harus berupa angka.'
            );
        }

        $harga = (float) $hargaTerpilih;

        if (
            !is_finite($harga)
            || $harga < 0
        ) {
            throw new RuntimeException(
                'Harga tidak boleh negatif atau tidak valid.'
            );
        }

        // Memastikan satuan tersedia dan aktif.
        $cek = $pdo->prepare("
            SELECT COUNT(*)
            FROM satuan
            WHERE idsatuan = ?
              AND status = 1
        ");

        $cek->execute([$idsatuan]);

        if ((int) $cek->fetchColumn() === 0) {
            throw new RuntimeException(
                'Satuan tidak ditemukan atau tidak aktif.'
            );
        }

        // Menambahkan barang baru dengan status Aktif.
        $stmt = $pdo->prepare("
            INSERT INTO barang (
                jenis,
                nama,
                idsatuan,
                status,
                harga
            )
            VALUES (?, ?, ?, 1, ?)
        ");

        $stmt->execute([
            $jenisTerpilih,
            $namaTerpilih,
            $idsatuan,
            $harga
        ]);

        $_SESSION['pesan_barang'] =
            'Barang berhasil ditambahkan.';

        header('Location: data_barang.php');
        exit;

    } catch (RuntimeException $e) {
        $error = $e->getMessage();

    } catch (Throwable $e) {
        error_log('Tambah barang: ' . $e->getMessage());

        $error =
            'Barang gagal ditambahkan. Periksa database.';
    }
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

    <title>Tambah Barang | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=101">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Tambah Barang</h1>
            <p>Masukkan data barang baru.</p>
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
                    <option value="">
                        Pilih jenis
                    </option>

                    <option
                        value="A"
                        <?= $jenisTerpilih === 'A'
                            ? 'selected'
                            : '' ?>
                    >
                        A
                    </option>

                    <option
                        value="B"
                        <?= $jenisTerpilih === 'B'
                            ? 'selected'
                            : '' ?>
                    >
                        B
                    </option>
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
                    value="<?= h($namaTerpilih) ?>"
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
                    <option value="">
                        Pilih satuan
                    </option>

                    <?php foreach ($satuanList as $s): ?>

                        <option
                            value="<?= (int) $s['idsatuan'] ?>"
                            <?= $idsatuanTerpilih ===
                                (string) $s['idsatuan']
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
                    value="<?= h($hargaTerpilih) ?>"
                    required
                >
            </div>

            <div class="form-actions">

                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Tambah
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
