```php
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'penjualan';

$pesan = $_SESSION['pesan_penjualan'] ?? '';
unset($_SESSION['pesan_penjualan']);

$sql = "SELECT
            p.idpenjualan,
            p.created_at,
            p.subtotal_nilai,
            p.ppn,
            p.total_nilai,
            p.status,
            p.updated_at,
            u.username,
            mp.persen
        FROM penjualan AS p
        LEFT JOIN user AS u
            ON p.iduser = u.iduser
        LEFT JOIN margin_penjualan AS mp
            ON p.idmargin_penjualan = mp.idmargin_penjualan
        ORDER BY p.idpenjualan DESC";

$stmt = $pdo->query($sql);
$dataPenjualan = $stmt->fetchAll(PDO::FETCH_ASSOC);

function rupiahPenjualan($nilai)
{
    return 'Rp ' . number_format((float) $nilai, 0, ',', '.');
}

function statusPenjualan($status)
{
    switch ((string) $status) {
        case '0':
            return 'Dibatalkan';

        case '1':
            return 'Selesai';

        default:
            return 'Status ' . $status;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Penjualan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=100">

    <style>
        .status-penjualan {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 5px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-penjualan.batal {
            background: #fee2e2;
            color: #991b1b;
        }

        .aksi-penjualan {
            white-space: nowrap;
        }

        .aksi-penjualan form {
            display: inline;
            margin-left: 4px;
        }

        .tabel-penjualan {
            min-width: 950px;
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
            <h1>Penjualan</h1>
            <p>Daftar transaksi penjualan barang ATK STORE.</p>
        </div>

        <a href="tambah_penjualan.php" class="btn btn-primary">
            <span class="plus">+</span> Tambah Penjualan
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

        <div class="card-header">
            <h2>Data Transaksi Penjualan</h2>
        </div>

        <div class="table-wrapper">

            <table class="tabel-penjualan">

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Penjualan</th>
                        <th>Tanggal</th>
                        <th>Kasir</th>
                        <th>Margin</th>
                        <th>Subtotal</th>
                        <th>PPN</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($dataPenjualan) > 0): ?>

                    <?php foreach ($dataPenjualan as $i => $penjualan): ?>

                        <tr>
                            <td>
                                <?= $i + 1 ?>
                            </td>

                            <td>
                                <?= (int) $penjualan['idpenjualan'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $penjualan['created_at'] ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $penjualan['username'] ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) ($penjualan['persen'] ?? '-'),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?><?= $penjualan['persen'] !== null ? '%' : '' ?>
                            </td>

                            <td>
                                <?= rupiahPenjualan(
                                    $penjualan['subtotal_nilai']
                                ) ?>
                            </td>

                            <td>
                                <?= rupiahPenjualan(
                                    $penjualan['ppn']
                                ) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= rupiahPenjualan(
                                        $penjualan['total_nilai']
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="status-penjualan <?= (string) $penjualan['status'] === '0' ? 'batal' : '' ?>">
                                    <?= htmlspecialchars(
                                        statusPenjualan(
                                            $penjualan['status']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <td class="aksi-penjualan">

                                <a
                                    href="detail_penjualan.php?id=<?= (int) $penjualan['idpenjualan'] ?>"
                                    class="btn btn-edit"
                                >
                                    Detail
                                </a>

                                <form
                                    action="hapus_penjualan.php"
                                    method="POST"
                                    class="form-hapus-penjualan"
                                >
                                    <input
                                        type="hidden"
                                        name="idpenjualan"
                                        value="<?= (int) $penjualan['idpenjualan'] ?>"
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
                            Belum ada transaksi penjualan.
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
            Yakin ingin menghapus transaksi penjualan ini beserta detailnya?
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
                onclick="konfirmasiHapusPenjualan()"
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

    function konfirmasiHapusPenjualan() {
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