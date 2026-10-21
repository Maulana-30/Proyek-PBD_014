```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'penerimaan';

$pesan = $_SESSION['pesan_penerimaan'] ?? '';
unset($_SESSION['pesan_penerimaan']);

$sql = "
    SELECT
        p.idpenerimaan,
        p.created_at,
        p.status,
        p.idpengadaan,
        u.username,
        v.nama_vendor
    FROM penerimaan p
    LEFT JOIN `user` u
        ON u.iduser = p.iduser
    LEFT JOIN pengadaan pg
        ON pg.idpengadaan = p.idpengadaan
    LEFT JOIN vendor v
        ON v.idvendor = pg.vendor_idvendor
    ORDER BY p.idpenerimaan ASC
";

$stmt = $pdo->query($sql);
$dataPenerimaan = $stmt->fetchAll();

function tampilStatusPenerimaan($status)
{
    if ((string) $status === '0') {
        return 'Menunggu';
    }

    if ((string) $status === '1') {
        return 'Selesai';
    }

    return 'Status ' . htmlspecialchars(
        (string) $status,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Penerimaan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=112">

    <style>
        .aksi-penerimaan {
            white-space: nowrap;
        }

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
            <h1>Data Penerimaan</h1>
            <p>Kelola penerimaan barang dari vendor.</p>
        </div>
    </header>

    <?php if ($pesan !== ''): ?>
        <div class="alert">
            <?= htmlspecialchars(
                $pesan,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <section class="card">

        <div class="card-header penerimaan-header">
            <h2>Daftar Penerimaan Barang</h2>

            <a
                href="tambah_penerimaan.php"
                class="btn btn-primary tombol-tambah-penerimaan"
            ><span class="plus">+</span> Tambah Penerimaan</a>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Penerimaan</th>
                        <th>Tanggal</th>
                        <th>ID Pengadaan</th>
                        <th>Vendor</th>
                        <th>Petugas</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($dataPenerimaan)): ?>

                        <?php foreach ($dataPenerimaan as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>

                                <td>
                                    <?= (int) $row['idpenerimaan'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $row['created_at'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) $row['idpengadaan'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['nama_vendor'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['username'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= tampilStatusPenerimaan(
                                        $row['status']
                                    ) ?>
                                </td>

                                <td class="aksi-penerimaan">
                                    <a
                                        href="detail_penerimaan.php?id=<?= (int) $row['idpenerimaan'] ?>"
                                        class="btn-edit"
                                    >Detail</a>

                                    <a
                                        href="edit_penerimaan.php?id=<?= (int) $row['idpenerimaan'] ?>"
                                        class="btn-edit"
                                    >Edit</a>

                                    <form
                                        action="hapus_penerimaan.php"
                                        method="POST"
                                        style="display: inline;"
                                        class="form-hapus-penerimaan"
                                    >
                                        <input
                                            type="hidden"
                                            name="idpenerimaan"
                                            value="<?= (int) $row['idpenerimaan'] ?>"
                                        >

                                        <button
                                            type="button"
                                            class="btn btn-hapus"
                                            onclick="bukaModalHapus(this)"
                                        >Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="empty-state">
                                Belum ada data penerimaan barang.
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
            Yakin ingin menghapus penerimaan ini?
            Penghapusan hanya diizinkan jika transaksi belum selesai
            dan tidak memiliki retur.
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
                onclick="konfirmasiHapusPenerimaan()"
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

    function konfirmasiHapusPenerimaan() {
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