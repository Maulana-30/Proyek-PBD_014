```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'satuan';

$sql = "SELECT
            idsatuan,
            nama_satuan,
            status
        FROM v_data_satuan
        ORDER BY idsatuan ASC";

$daftarSatuan = $pdo->query($sql)->fetchAll();

$pesan = $_SESSION['pesan_satuan'] ?? '';
unset($_SESSION['pesan_satuan']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Satuan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=100">

    <style>
        .modal-konfirmasi {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(15, 23, 42, 0.55);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-konfirmasi.aktif {
            display: flex;
        }

        .modal-konfirmasi-content {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
            color: #263238;
            box-sizing: border-box;
        }

        .modal-konfirmasi-content h2 {
            margin: 0 0 12px;
            font-size: 20px;
            color: #263238;
        }

        .modal-konfirmasi-content p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .modal-konfirmasi-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 28px;
        }

        .btn-batal-modal {
            background: #ffffff;
            color: #475569;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            padding: 9px 16px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-hapus-modal {
            background: #c93636;
            color: #ffffff;
            border: none;
            border-radius: 7px;
            padding: 9px 16px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-hapus-modal:hover {
            background: #b91c1c;
        }

        .btn-batal-modal:hover {
            background: #f8fafc;
        }
    </style>
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Data Satuan</h1>
            <p>Kelola satuan barang yang digunakan di toko.</p>
        </div>

        <a href="tambah_satuan.php" class="btn btn-primary">
            <span class="plus">+</span> Tambah Satuan
        </a>
    </header>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars(
                $pesan,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <section class="card">

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Satuan</th>
                        <th>Nama Satuan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($daftarSatuan) > 0): ?>

                    <?php foreach ($daftarSatuan as $i => $satuan): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>

                            <td>
                                <?= (int) $satuan['idsatuan'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $satuan['nama_satuan'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?php if ((int) $satuan['status'] === 1): ?>
                                    <span class="badge badge-active">
                                        Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">
                                        Tidak Aktif
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="action-buttons">

                                    <a
                                        href="edit_satuan.php?id=<?= (int) $satuan['idsatuan'] ?>"
                                        class="btn btn-edit"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        action="hapus_satuan.php"
                                        method="POST"
                                        class="form-hapus-satuan"
                                    >
                                        <input
                                            type="hidden"
                                            name="idsatuan"
                                            value="<?= (int) $satuan['idsatuan'] ?>"
                                        >

                                        <button
                                            type="button"
                                            class="btn btn-hapus"
                                            onclick="bukaModalHapus(this)"
                                        >
                                            Hapus
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty-state">
                            Belum ada data satuan.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </section>

</main>

<!-- Modal Konfirmasi Penghapusan -->
<div
    class="modal-konfirmasi"
    id="modalKonfirmasi"
>
    <div
        class="modal-konfirmasi-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="judulKonfirmasi"
    >
        <h2 id="judulKonfirmasi">
            Konfirmasi Penghapusan
        </h2>

        <p>
            Yakin ingin menghapus satuan ini?
        </p>

        <div class="modal-konfirmasi-actions">

            <button
                type="button"
                class="btn-batal-modal"
                onclick="tutupModalHapus()"
            >
                Batal
            </button>

            <button
                type="button"
                class="btn-hapus-modal"
                onclick="konfirmasiHapusSatuan()"
            >
                Ya, Hapus
            </button>

        </div>
    </div>
</div>

<script>
    let formHapusAktif = null;

    function bukaModalHapus(tombol) {
        formHapusAktif = tombol.closest('form');

        document
            .getElementById('modalKonfirmasi')
            .classList.add('aktif');
    }

    function tutupModalHapus() {
        document
            .getElementById('modalKonfirmasi')
            .classList.remove('aktif');

        formHapusAktif = null;
    }

    function konfirmasiHapusSatuan() {
        if (formHapusAktif) {
            formHapusAktif.submit();
        }
    }

    document
        .getElementById('modalKonfirmasi')
        .addEventListener('click', function (event) {
            if (event.target === this) {
                tutupModalHapus();
            }
        });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            tutupModalHapus();
        }
    });
</script>

</body>
</html>
```