```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'role';

$sql = "SELECT
            idrole,
            nama_role
        FROM v_data_role
        ORDER BY idrole ASC";

$daftarRole = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Role | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=106">

    <style>
        /* Latar belakang modal */
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

        /* Kotak modal */
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
            <h1>Data Role</h1>
            <p>Daftar role atau hak akses pengguna sistem.</p>
        </div>

        <a href="tambah_role.php" class="btn btn-primary">
            <span class="plus">+</span> Tambah Role
        </a>
    </header>

    <?php if (!empty($_SESSION['pesan_role'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars(
                $_SESSION['pesan_role'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

        <?php unset($_SESSION['pesan_role']); ?>
    <?php endif; ?>

    <section class="card">

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Role</th>
                        <th>Nama Role</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($daftarRole) > 0): ?>

                        <?php foreach ($daftarRole as $i => $role): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>

                                <td>
                                    <?= (int) $role['idrole'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $role['nama_role'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <div class="action-buttons">

                                        <a
                                            href="edit_role.php?id=<?= (int) $role['idrole'] ?>"
                                            class="btn btn-edit"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            action="hapus_role.php"
                                            method="POST"
                                            class="form-hapus-role"
                                            style="display:inline"
                                        >
                                            <input
                                                type="hidden"
                                                name="idrole"
                                                value="<?= (int) $role['idrole'] ?>"
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
                            <td colspan="4" class="empty-state">
                                Belum ada data role.
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
            Yakin ingin menghapus role ini?
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
                onclick="konfirmasiHapusRole()"
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

    function konfirmasiHapusRole() {
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