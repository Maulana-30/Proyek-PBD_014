
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'satuan';
$error = '';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    header('Location: data_satuan.php');
    exit;
}

// Mengambil data satuan.
$stmt = $pdo->prepare("
    SELECT *
    FROM satuan
    WHERE idsatuan = ?
");

$stmt->execute([$id]);

$satuan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$satuan) {
    $_SESSION['pesan_satuan'] = 'Satuan tidak ditemukan.';
    header('Location: data_satuan.php');
    exit;
}

// Nilai awal formulir.
$nama = $satuan['nama_satuan'];
$status = (string) $satuan['status'];

function h($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Memproses pembaruan satuan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaInput = $_POST['nama_satuan'] ?? '';
    $statusInput = $_POST['status'] ?? '';

    // Mempertahankan input jika validasi gagal.
    $nama = is_scalar($namaInput)
        ? trim((string) $namaInput)
        : '';

    $status = is_scalar($statusInput)
        ? trim((string) $statusInput)
        : '';

    $statusValidasi = filter_var(
        $status,
        FILTER_VALIDATE_INT
    );

    if (
        $nama === ''
        || mb_strlen($nama) > 45
        || $statusValidasi === false
        || !in_array($statusValidasi, [0, 1], true)
    ) {
        $error = 'Periksa kembali nama dan status satuan.';
    } else {
        try {
            // Pastikan data satuan masih tersedia.
            $cek = $pdo->prepare("
                SELECT idsatuan
                FROM satuan
                WHERE idsatuan = ?
            ");

            $cek->execute([$id]);

            if (!$cek->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException(
                    'Satuan tidak ditemukan.'
                );
            }

            // Memperbarui nama dan status satuan.
            $update = $pdo->prepare("
                UPDATE satuan
                SET
                    nama_satuan = ?,
                    status = ?
                WHERE idsatuan = ?
            ");

            $update->execute([
                $nama,
                $statusValidasi,
                $id
            ]);

            $_SESSION['pesan_satuan'] =
                'Satuan berhasil diperbarui.';

            header('Location: data_satuan.php');
            exit;

        } catch (RuntimeException $e) {
            $error = $e->getMessage();

        } catch (Throwable $e) {
            error_log('Edit satuan: ' . $e->getMessage());

            $error =
                'Satuan gagal diperbarui. Periksa database.';
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

    <title>Edit Satuan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=101">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Edit Satuan</h1>
            <p>Perbarui informasi satuan.</p>
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
                <label for="nama_satuan">Nama Satuan</label>

                <input
                    class="form-control"
                    type="text"
                    id="nama_satuan"
                    name="nama_satuan"
                    maxlength="45"
                    value="<?= h($nama) ?>"
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
                    href="data_satuan.php"
                >
                    Batal
                </a>
            </div>

        </form>

    </section>

</main>

</body>
</html>
