
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'pengadaan';

$sql = "SELECT
            p.idpengadaan,
            p.`timestamp`,
            p.status,
            p.subtotal_nilai,
            p.ppn,
            p.total_nilai,
            v.nama_vendor,
            u.username
        FROM pengadaan AS p
        LEFT JOIN vendor AS v
            ON p.vendor_idvendor = v.idvendor
        LEFT JOIN `user` AS u
            ON p.user_iduser = u.iduser
        ORDER BY p.idpengadaan ASC";

$daftarPengadaan = $pdo->query($sql)->fetchAll();

function rupiah($nilai)
{
    return 'Rp ' . number_format((float) $nilai, 0, ',', '.');
}

function tanggalPengadaan($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $waktu = strtotime($tanggal);

    return $waktu === false
        ? htmlspecialchars(
            (string) $tanggal,
            ENT_QUOTES,
            'UTF-8'
        )
        : date('d-m-Y H:i', $waktu);
}

function labelStatus($status)
{
    $status = (string) $status;

    if ($status === '0') {
        return 'Menunggu';
    }

    if ($status === '1') {
        return 'Diproses';
    }

    if ($status === '2') {
        return 'Selesai';
    }

    return 'Status ' . $status;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengadaan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=105">

    <style>
        .btn-detail {
            display: inline-block;
            padding: 8px 12px;
            background: #e8f1ff;
            color: #174a91;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-detail:hover {
            background: #d8e7ff;
        }

        .status-label {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 5px;
            background: #edf1f7;
            color: #344054;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .aksi-cell {
            white-space: nowrap;
        }

        .aksi-cell form {
            display: inline;
            margin-left: 4px;
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
            <h1>Pengadaan</h1>
            <p>Daftar transaksi pengadaan barang dari vendor.</p>
        </div>

        <a href="tambah_pengadaan.php" class="btn btn-primary">
            <span class="plus">+</span> Tambah Pengadaan
        </a>
    </header>

    <?php if (!empty($_SESSION['pesan_pengadaan'])): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars(
                $_SESSION['pesan_pengadaan'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

        <?php unset($_SESSION['pesan_pengadaan']); ?>

    <?php endif; ?>

    <section class="card">

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Pengadaan</th>
                        <th>Tanggal</th>
                        <th>Vendor</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Subtotal</th>
                        <th>PPN</th>
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($daftarPengadaan) > 0): ?>

                    <?php foreach ($daftarPengadaan as $i => $pengadaan): ?>

                        <tr>
                            <td><?= $i + 1 ?></td>

                            <td>
                                <?= (int) $pengadaan['idpengadaan'] ?>
                            </td>

                            <td>
                                <?= tanggalPengadaan(
                                    $pengadaan['timestamp']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $pengadaan['nama_vendor'] ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $pengadaan['username'] ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <span class="status-label">
                                    <?= htmlspecialchars(
                                        labelStatus($pengadaan['status']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= rupiah(
                                    $pengadaan['subtotal_nilai']
                                ) ?>
                            </td>

                            <td>
                                <?= rupiah($pengadaan['ppn']) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= rupiah(
                                        $pengadaan['total_nilai']
                                    ) ?>
                                </strong>
                            </td>

                            <td class="aksi-cell">

                                <a
                                    class="btn-detail"
                                    href="detail_pengadaan.php?id=<?= (int) $pengadaan['idpengadaan'] ?>"
                                >
                                    Detail
                                </a>

                                <a
                                    class="btn-detail"
                                    href="edit_pengadaan.php?id=<?= (int) $pengadaan['idpengadaan'] ?>"
                                >
                                    Edit
                                </a>

                                <form
                                    action="hapus_pengadaan.php"
                                    method="POST"
                                    class="form-hapus-pengadaan"
                                >
                                    <input
                                        type="hidden"
                                        name="idpengadaan"
                                        value="<?= (int) $pengadaan['idpengadaan'] ?>"
                                    >

                                    <button
                                        type="button"
                                        class="btn btn-hapus"
                                        onclick="bukaModalHapus(this)"
                                    >
                                        Hapus
                                    </button>
                                </form>

                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="10" class="empty-state">
                            Belum ada transaksi pengadaan.
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
            Yakin ingin menghapus pengadaan ini?
            Pengadaan hanya dapat dihapus jika berstatus Menunggu
            dan belum memiliki transaksi penerimaan.
            Rincian barang yang terkait juga akan dihapus.
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
                onclick="konfirmasiHapusPengadaan()"
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

    function konfirmasiHapusPengadaan() {
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
