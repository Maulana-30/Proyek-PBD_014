
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'margin_penjualan';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $persen = filter_input(INPUT_POST, 'persen', FILTER_VALIDATE_FLOAT);
    $status = $_POST['status'] ?? '';

    if ($persen === false || $persen === null || $persen < 0 || $persen > 100) {
        $error = 'Persentase margin harus antara 0 sampai 100.';
    } elseif (!in_array($status, ['0', '1'], true)) {
        $error = 'Status margin tidak valid.';
    } else {
        try {
            $sql = "INSERT INTO margin_penjualan
                        (created_at, persen, status, iduser, updated_at)
                    VALUES
                        (NOW(), :persen, :status, :iduser, NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':persen' => $persen,
                ':status' => (int) $status,
                ':iduser' => (int) $_SESSION['iduser']
            ]);

            $_SESSION['pesan_margin'] = 'Margin penjualan berhasil ditambahkan.';
            header('Location: data_margin_penjualan.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Margin penjualan gagal disimpan. Periksa kembali data pengguna dan database.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Margin Penjualan | ATK STORE</title>
    <link rel="stylesheet" href="assets/style.css?v=100">
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Tambah Margin Penjualan</h1>
            <p>Masukkan persentase margin penjualan baru.</p>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <form method="POST" action="">

            <div class="form-group">
                <label for="persen">Persentase Margin (%)</label>
                <input
                    type="number"
                    id="persen"
                    name="persen"
                    class="form-control"
                    min="0"
                    max="100"
                    step="0.01"
                    value="<?= htmlspecialchars(
                        (string) ($_POST['persen'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="Contoh: 10 atau 7.5"
                    required
                >
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select
                    id="status"
                    name="status"
                    class="form-control"
                    required
                >
                    <option value="1"
                        <?= ($_POST['status'] ?? '1') === '1' ? 'selected' : '' ?>>
                        Aktif
                    </option>
                    <option value="0"
                        <?= ($_POST['status'] ?? '') === '0' ? 'selected' : '' ?>>
                        Nonaktif
                    </option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Simpan
                </button>

                <a href="data_margin_penjualan.php" class="btn btn-secondary">
                    Batal
                </a>
            </div>

        </form>
    </section>

</main>

</body>
</html>
