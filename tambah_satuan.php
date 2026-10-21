
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'satuan';
$error = '';
$nama = '';

function h($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Memproses formulir tambah satuan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaInput = $_POST['nama_satuan'] ?? '';

    // Memastikan input berupa teks.
    $nama = is_scalar($namaInput)
        ? trim((string) $namaInput)
        : '';

    if ($nama === '' || mb_strlen($nama) > 45) {
        $error =
            'Nama satuan wajib diisi dan maksimal 45 karakter.';
    } else {
        try {
            // Satuan baru dibuat dengan status Aktif.
            $stmt = $pdo->prepare("
                INSERT INTO satuan (
                    nama_satuan,
                    status
                )
                VALUES (?, 1)
            ");

            $stmt->execute([$nama]);

            $_SESSION['pesan_satuan'] =
                'Satuan berhasil ditambahkan.';

            header('Location: data_satuan.php');
            exit;

        } catch (Throwable $e) {
            error_log('Tambah satuan: ' . $e->getMessage());

            $error =
                'Satuan gagal ditambahkan. Periksa database.';
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

    <title>Tambah Satuan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=101">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Tambah Satuan</h1>
            <p>Tambahkan satuan barang baru.</p>
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

            <div class="form-actions">

                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Tambah
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
