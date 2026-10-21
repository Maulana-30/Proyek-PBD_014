
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'margin_penjualan';
$error = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    $_SESSION['pesan_margin'] = 'ID margin penjualan tidak valid.';
    header('Location: data_margin_penjualan.php');
    exit;
}

// Ambil data margin
$stmt = $pdo->prepare(
    "SELECT idmargin_penjualan, persen, status
     FROM margin_penjualan
     WHERE idmargin_penjualan = :id"
);
$stmt->execute([':id' => $id]);
$margin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$margin) {
    $_SESSION['pesan_margin'] = 'Data margin penjualan tidak ditemukan.';
    header('Location: data_margin_penjualan.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $persenInput = trim($_POST['persen'] ?? '');
    $status = $_POST['status'] ?? '';

    if (
        $persenInput === '' ||
        !is_numeric($persenInput) ||
        (float) $persenInput < 0 ||
        (float) $persenInput > 100
    ) {
        $error = 'Persentase margin harus berupa angka antara 0 sampai 100.';
    } elseif (!in_array($status, ['0', '1'], true)) {
        $error = 'Status margin tidak valid.';
    } else {
        try {
            $sql = "UPDATE margin_penjualan
                    SET persen = :persen,
                        status = :status,
                        updated_at = NOW()
                    WHERE idmargin_penjualan = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':persen' => (float) $persenInput,
                ':status' => (int) $status,
                ':id' => $id
            ]);

            $_SESSION['pesan_margin'] =
                'Margin penjualan berhasil diperbarui.';

            header('Location: data_margin_penjualan.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Margin gagal diperbarui. Silakan periksa database.';
        }
    }

    // Pertahankan input apabila validasi gagal
    $margin['persen'] = $persenInput;
    $margin['status'] = $status;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Margin Penjualan | ATK STORE</title>
    <link rel="stylesheet" href="assets/style.css?v=100">
</head>
<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Edit Margin Penjualan</h1>
            <p>Perbarui persentase dan status margin penjualan.</p>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="card">

        <form method="POST"
              action="edit_margin_penjualan.php?id=<?= (int) $id ?>">

            <div class="form-group">
                <label for="idmargin">ID Margin</label>
                <input
                    type="text"
                    id="idmargin"
                    class="form-control"
                    value="<?= (int) $margin['idmargin_penjualan'] ?>"
                    readonly
                >
            </div>

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
                        (string) $margin['persen'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
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
                        <?= (string) $margin['status'] === '1' ? 'selected' : '' ?>>
                        Aktif
                    </option>

                    <option value="0"
                        <?= (string) $margin['status'] === '0' ? 'selected' : '' ?>>
                        Nonaktif
                    </option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    Simpan Perubahan
                </button>

                <a href="data_margin_penjualan.php"
                   class="btn btn-secondary">
                    Batal
                </a>
            </div>

        </form>

    </section>

</main>

</body>
</html>
