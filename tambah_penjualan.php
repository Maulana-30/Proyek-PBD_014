
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'penjualan';
$error = '';
$ppnPersen = 11;

$barangList = $pdo->query(
    "SELECT idbarang, nama, harga
     FROM barang
     WHERE status = 1
     ORDER BY nama"
)->fetchAll(PDO::FETCH_ASSOC);

$marginList = $pdo->query(
    "SELECT idmargin_penjualan, persen
     FROM margin_penjualan
     WHERE status = 1
     ORDER BY idmargin_penjualan DESC"
)->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idmargin = filter_input(
        INPUT_POST,
        'idmargin_penjualan',
        FILTER_VALIDATE_INT
    );

    $idbarangList = $_POST['idbarang'] ?? [];
    $jumlahList = $_POST['jumlah'] ?? [];

    if (
        !$idmargin ||
        !is_array($idbarangList) ||
        !is_array($jumlahList) ||
        count($idbarangList) === 0 ||
        count($idbarangList) !== count($jumlahList)
    ) {
        $error = 'Lengkapi data penjualan dengan benar.';
    } else {
        try {
            $pdo->beginTransaction();

            // Periksa margin aktif.
            $stmtMargin = $pdo->prepare(
                "SELECT persen
                 FROM margin_penjualan
                 WHERE idmargin_penjualan = ?
                   AND status = 1
                 FOR UPDATE"
            );
            $stmtMargin->execute([$idmargin]);
            $margin = $stmtMargin->fetch(PDO::FETCH_ASSOC);

            if (!$margin) {
                throw new Exception(
                    'Margin penjualan tidak tersedia atau tidak aktif.'
                );
            }

            $persenMargin = (float) $margin['persen'];

            if ($persenMargin < 0) {
                throw new Exception(
                    'Persentase margin tidak valid.'
                );
            }

            // Gabungkan barang yang dipilih lebih dari satu kali.
            $jumlahPerBarang = [];

            foreach ($idbarangList as $i => $idbarangInput) {
                $idbarang = filter_var(
                    $idbarangInput,
                    FILTER_VALIDATE_INT
                );

                $jumlah = filter_var(
                    $jumlahList[$i] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (
                    !$idbarang ||
                    $jumlah === false ||
                    $jumlah === null ||
                    $jumlah < 1
                ) {
                    throw new Exception(
                        'Barang dan jumlah harus diisi dengan benar.'
                    );
                }

                if (!isset($jumlahPerBarang[$idbarang])) {
                    $jumlahPerBarang[$idbarang] = 0;
                }

                $jumlahPerBarang[$idbarang] += $jumlah;
            }

            $items = [];
            $subtotalNilai = 0;

            // Periksa barang dan saldo stok.
            foreach ($jumlahPerBarang as $idbarang => $jumlah) {
                $stmtBarang = $pdo->prepare(
                    "SELECT idbarang, nama, harga
                     FROM barang
                     WHERE idbarang = ?
                       AND status = 1
                     FOR UPDATE"
                );
                $stmtBarang->execute([$idbarang]);
                $barang = $stmtBarang->fetch(PDO::FETCH_ASSOC);

                if (!$barang) {
                    throw new Exception(
                        'Barang tidak ditemukan atau tidak aktif.'
                    );
                }

                // Ambil saldo dari catatan stok terakhir.
                $stmtStok = $pdo->prepare(
                    "SELECT stock
                     FROM kartu_stok
                     WHERE idbarang = ?
                     ORDER BY idkartu_stok DESC
                     LIMIT 1
                     FOR UPDATE"
                );
                $stmtStok->execute([$idbarang]);
                $stokTerakhir = $stmtStok->fetchColumn();

                if ($stokTerakhir === false) {
                    throw new Exception(
                        'Stok ' . $barang['nama'] .
                        ' belum memiliki catatan stok.'
                    );
                }

                $stokTersedia = (int) $stokTerakhir;

                if ($jumlah > $stokTersedia) {
                    throw new Exception(
                        'Stok ' . $barang['nama'] .
                        ' tidak mencukupi. Stok tersedia: ' .
                        $stokTersedia . '.'
                    );
                }

                $hargaDasar = (int) $barang['harga'];

                $hargaJual = (int) round(
                    $hargaDasar * (1 + ($persenMargin / 100))
                );

                $subtotal = $hargaJual * $jumlah;
                $subtotalNilai += $subtotal;

                $items[$idbarang] = [
                    'idbarang' => (int) $idbarang,
                    'nama' => $barang['nama'],
                    'harga_satuan' => $hargaJual,
                    'jumlah' => $jumlah,
                    'subtotal' => $subtotal,
                    'stok_sesudah' => $stokTersedia - $jumlah
                ];
            }

            if ($subtotalNilai <= 0) {
                throw new Exception(
                    'Total penjualan harus lebih dari nol.'
                );
            }

            $ppn = (int) round(
                $subtotalNilai * ($ppnPersen / 100)
            );

            $totalNilai = $subtotalNilai + $ppn;

            // Simpan transaksi utama.
            $stmtPenjualan = $pdo->prepare(
                "INSERT INTO penjualan
                 (
                    subtotal_nilai,
                    ppn,
                    total_nilai,
                    iduser,
                    idmargin_penjualan,
                    status
                 )
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmtPenjualan->execute([
                $subtotalNilai,
                $ppn,
                $totalNilai,
                (int) $_SESSION['iduser'],
                $idmargin,
                1
            ]);

            $idPenjualan = (int) $pdo->lastInsertId();

            // Siapkan query detail penjualan.
            $stmtDetail = $pdo->prepare(
                "INSERT INTO detail_penjualan
                 (
                    harga_satuan,
                    jumlah,
                    subtotal,
                    penjualan_idpenjualan,
                    idbarang
                 )
                 VALUES (?, ?, ?, ?, ?)"
            );

            // Siapkan query kartu stok barang keluar.
            $stmtKartuStok = $pdo->prepare(
                "INSERT INTO kartu_stok
                 (
                    jenis_transaksi,
                    masuk,
                    keluar,
                    stock,
                    created_at,
                    idtransaksi,
                    idbarang
                 )
                 VALUES ('K', 0, ?, ?, NOW(), ?, ?)"
            );

            foreach ($items as $item) {
                $stmtDetail->execute([
                    $item['harga_satuan'],
                    $item['jumlah'],
                    $item['subtotal'],
                    $idPenjualan,
                    $item['idbarang']
                ]);

                $stmtKartuStok->execute([
                    $item['jumlah'],
                    $item['stok_sesudah'],
                    $idPenjualan,
                    $item['idbarang']
                ]);
            }

            $pdo->commit();

            $_SESSION['pesan_penjualan'] =
                'Penjualan berhasil ditambahkan dan stok diperbarui.';

            header('Location: data_penjualan.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e instanceof PDOException
                ? 'Penjualan gagal disimpan. Periksa data dan struktur database.'
                : $e->getMessage();
        }
    }
}

function rupiahPenjualan($nilai)
{
    return 'Rp ' . number_format((float) $nilai, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Penjualan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=105">

    <style>
        .item-penjualan {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(100px, 1fr) auto;
            gap: 12px;
            align-items: end;
            margin-bottom: 12px;
        }

        .item-penjualan .form-group {
            margin-bottom: 0;
        }

        .btn-hapus-item {
            border: none;
            border-radius: 6px;
            background: #dc2626;
            color: #fff;
            padding: 10px 12px;
            cursor: pointer;
            min-height: 40px;
        }

        .btn-hapus-item:hover {
            background: #b91c1c;
        }

        .ringkasan-penjualan {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .ringkasan-baris {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 10px;
        }

        .ringkasan-baris.total {
            font-size: 18px;
            font-weight: 700;
        }

        @media (max-width: 600px) {
            .item-penjualan {
                grid-template-columns: 1fr 1fr;
            }

            .item-penjualan .pilihan-barang,
            .item-penjualan .btn-hapus-item {
                grid-column: 1 / -1;
            }
        }
    </style>
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Tambah Penjualan</h1>
            <p>Masukkan data transaksi penjualan barang.</p>
        </div>
    </header>

    <section class="card">

        <?php if ($error !== ''): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>

        <?php if (count($barangList) === 0): ?>
            <div class="alert alert-error">
                Belum ada barang aktif. Tambahkan barang terlebih dahulu.
            </div>

        <?php elseif (count($marginList) === 0): ?>
            <div class="alert alert-error">
                Belum ada margin penjualan aktif.
            </div>

        <?php else: ?>

            <form method="POST" id="formPenjualan">

                <div class="form-group">
                    <label for="idmargin_penjualan">
                        Margin Penjualan
                    </label>

                    <select
                        class="form-control"
                        id="idmargin_penjualan"
                        name="idmargin_penjualan"
                        required
                    >
                        <option value="">Pilih margin</option>

                        <?php foreach ($marginList as $m): ?>
                            <option
                                value="<?= (int) $m['idmargin_penjualan'] ?>"
                                <?= (string) ($_POST['idmargin_penjualan'] ?? '') ===
                                    (string) $m['idmargin_penjualan']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars(
                                    (string) $m['persen'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>%
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h3>Daftar Barang</h3>

                <div id="daftarBarang">

                    <?php
                    $jumlahBaris = max(
                        1,
                        count($_POST['idbarang'] ?? [])
                    );

                    for ($i = 0; $i < $jumlahBaris; $i++):
                        $idTerpilih = $_POST['idbarang'][$i] ?? '';
                        $jumlahTerpilih = $_POST['jumlah'][$i] ?? '1';
                    ?>
                        <div class="item-penjualan">

                            <div class="form-group pilihan-barang">
                                <label>Barang</label>

                                <select
                                    class="form-control pilih-barang"
                                    name="idbarang[]"
                                    required
                                >
                                    <option value="">Pilih barang</option>

                                    <?php foreach ($barangList as $b): ?>
                                        <option
                                            value="<?= (int) $b['idbarang'] ?>"
                                            data-harga="<?= (int) $b['harga'] ?>"
                                            <?= (string) $idTerpilih ===
                                                (string) $b['idbarang']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= htmlspecialchars(
                                                $b['nama'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                            — <?= rupiahPenjualan($b['harga']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Jumlah</label>

                                <input
                                    class="form-control input-jumlah"
                                    type="number"
                                    name="jumlah[]"
                                    min="1"
                                    step="1"
                                    value="<?= htmlspecialchars(
                                        (string) $jumlahTerpilih,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    required
                                >
                            </div>

                            <button
                                type="button"
                                class="btn-hapus-item"
                                onclick="hapusBaris(this)"
                            >
                                Hapus
                            </button>

                        </div>
                    <?php endfor; ?>

                </div>

                <button
                    type="button"
                    class="btn btn-secondary"
                    id="tambahBaris"
                >
                    + Tambah Barang
                </button>

                <div class="ringkasan-penjualan">
                    <div class="ringkasan-baris">
                        <span>Subtotal</span>
                        <strong id="tampilanSubtotal">Rp 0</strong>
                    </div>

                    <div class="ringkasan-baris">
                        <span>PPN (<?= $ppnPersen ?>%)</span>
                        <strong id="tampilanPpn">Rp 0</strong>
                    </div>

                    <div class="ringkasan-baris total">
                        <span>Total</span>
                        <strong id="tampilanTotal">Rp 0</strong>
                    </div>

                    <p>
                        <small>
                            Ringkasan merupakan perkiraan berdasarkan harga dasar.
                            Nilai akhir dihitung ulang di server setelah margin.
                        </small>
                    </p>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        Tambah
                    </button>

                    <a class="btn btn-secondary" href="data_penjualan.php">
                        Batal
                    </a>
                </div>

            </form>

        <?php endif; ?>

    </section>

</main>

<script>
const daftarBarang = document.getElementById('daftarBarang');
const tombolTambah = document.getElementById('tambahBaris');

const formatRupiah = nilai =>
    'Rp ' + Math.round(nilai).toLocaleString('id-ID');

function hapusBaris(tombol) {
    const baris = daftarBarang.querySelectorAll('.item-penjualan');

    if (baris.length <= 1) {
        baris[0].querySelector('.pilih-barang').value = '';
        baris[0].querySelector('.input-jumlah').value = 1;
    } else {
        tombol.closest('.item-penjualan').remove();
    }

    hitungTotal();
}

function hitungTotal() {
    let subtotal = 0;

    daftarBarang.querySelectorAll('.item-penjualan').forEach(baris => {
        const select = baris.querySelector('.pilih-barang');
        const jumlahInput = baris.querySelector('.input-jumlah');
        const opsi = select.options[select.selectedIndex];

        const hargaDasar = Number(opsi?.dataset.harga || 0);
        const jumlah = Math.max(0, Number(jumlahInput.value || 0));

        subtotal += hargaDasar * jumlah;
    });

    const ppn = Math.round(subtotal * <?= $ppnPersen ?> / 100);

    document.getElementById('tampilanSubtotal').textContent =
        formatRupiah(subtotal);

    document.getElementById('tampilanPpn').textContent =
        formatRupiah(ppn);

    document.getElementById('tampilanTotal').textContent =
        formatRupiah(subtotal + ppn);
}

tombolTambah?.addEventListener('click', () => {
    const barisAwal = daftarBarang.querySelector('.item-penjualan');
    const barisBaru = barisAwal.cloneNode(true);

    barisBaru.querySelector('.pilih-barang').value = '';
    barisBaru.querySelector('.input-jumlah').value = 1;

    daftarBarang.appendChild(barisBaru);
    hitungTotal();
});

daftarBarang?.addEventListener('input', hitungTotal);
daftarBarang?.addEventListener('change', hitungTotal);

document.getElementById('formPenjualan')?.addEventListener(
    'submit',
    event => {
        const baris = daftarBarang.querySelectorAll('.item-penjualan');

        if (baris.length === 0) {
            event.preventDefault();
            alert('Tambahkan minimal satu barang.');
        }
    }
);

if (daftarBarang) {
    hitungTotal();
}
</script>

</body>
</html>
