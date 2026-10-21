
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'vendor';
$error = '';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header('Location: data_vendor.php');
    exit;
}

// Mengambil data vendor.
$stmt = $pdo->prepare("
    SELECT *
    FROM vendor
    WHERE idvendor = ?
");

$stmt->execute([$id]);

$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    $_SESSION['pesan_vendor'] = 'Vendor tidak ditemukan.';
    header('Location: data_vendor.php');
    exit;
}

// Nilai awal formulir.
$nama = $vendor['nama_vendor'];
$badanHukum = $vendor['badan_hukum'];
$status = (string) $vendor['status'];

function h($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Memproses pembaruan vendor.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaInput = $_POST['nama_vendor'] ?? '';
    $badanHukumInput = $_POST['badan_hukum'] ?? '';
    $statusInput = $_POST['status'] ?? '';

    // Mempertahankan nilai input apabila validasi gagal.
    $nama = is_scalar($namaInput)
        ? trim((string) $namaInput)
        : '';

    $badanHukum = is_scalar($badanHukumInput)
        ? trim((string) $badanHukumInput)
        : '';

    $status = is_scalar($statusInput)
        ? trim((string) $statusInput)
        : '';

    $statusValidasi = filter_var(
        $status,
        FILTER_VALIDATE_INT
    );

    // Validasi nama vendor.
    if (
        $nama === ''
        || mb_strlen($nama) > 100
    ) {
        $error = 'Nama vendor wajib diisi dan maksimal 100 karakter.';

    // Kolom badan_hukum menggunakan CHAR(1).
    } elseif (
        $badanHukum === ''
        || mb_strlen($badanHukum) !== 1
    ) {
        $error = 'Badan hukum harus diisi dengan satu karakter sesuai format database.';

    // Status hanya menerima 0 atau 1.
    } elseif (
        $statusValidasi === false
        || !in_array($statusValidasi, [0, 1], true)
    ) {
        $error = 'Status vendor tidak valid.';

    } else {
        try {
            // Memastikan vendor masih tersedia.
            $cek = $pdo->prepare("
                SELECT idvendor
                FROM vendor
                WHERE idvendor = ?
            ");

            $cek->execute([$id]);

            if (!$cek->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException(
                    'Vendor tidak ditemukan.'
                );
            }

            // Memperbarui data vendor.
            $update = $pdo->prepare("
                UPDATE vendor
                SET
                    nama_vendor = ?,
                    badan_hukum = ?,
                    status = ?
                WHERE idvendor = ?
            ");

            $update->execute([
                $nama,
                $badanHukum,
                (string) $statusValidasi,
                $id
            ]);

            $_SESSION['pesan_vendor'] =
                'Vendor berhasil diperbarui.';

            header('Location: data_vendor.php');
            exit;

        } catch (RuntimeException $e) {
            $error = $e->getMessage();

        } catch (Throwable $e) {
            error_log('Edit vendor: ' . $e->getMessage());

            $error =
                'Vendor gagal diperbarui. Periksa database.';
        }
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

    <title>Edit Vendor | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=101">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Edit Vendor</h1>
            <p>Perbarui informasi pemasok.</p>
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
                <label for="nama_vendor">Nama Vendor</label>

                <input
                    class="form-control"
                    type="text"
                    id="nama_vendor"
                    name="nama_vendor"
                    maxlength="100"
                    value="<?= h($nama) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="badan_hukum">Badan Hukum</label>

                <input
                    class="form-control"
                    type="text"
                    id="badan_hukum"
                    name="badan_hukum"
                    maxlength="1"
                    value="<?= h($badanHukum) ?>"
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
                        <?= $status === '1' ? 'selected' : '' ?>
                    >
                        Aktif
                    </option>

                    <option
                        value="0"
                        <?= $status === '0' ? 'selected' : '' ?>
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
                    href="data_vendor.php"
                >
                    Batal
                </a>
            </div>

        </form>

    </section>

</main>

</body>
</html>
