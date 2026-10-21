
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$halamanAktif = 'pengadaan';

$pesanError = '';
$vendorTerpilih = '';
$ppnPersen = '11';
$barangTerpilih = [''];
$jumlahTerpilih = [1];

// Mengambil daftar vendor aktif.
$stmtVendor = $pdo->query("
    SELECT idvendor, nama_vendor
    FROM vendor
    WHERE status = 1
    ORDER BY nama_vendor
");

$daftarVendor = $stmtVendor->fetchAll(PDO::FETCH_ASSOC);

// Mengambil daftar barang aktif.
$stmtBarang = $pdo->query("
    SELECT
        b.idbarang,
        b.nama,
        b.harga,
        s.nama_satuan
    FROM barang AS b
    LEFT JOIN satuan AS s
        ON b.idsatuan = s.idsatuan
    WHERE b.status = 1
    ORDER BY b.nama
");

$daftarBarang = $stmtBarang->fetchAll(PDO::FETCH_ASSOC);

function h($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mengambil input untuk ditampilkan kembali jika validasi gagal.
    $vendorInput = $_POST['vendor_idvendor'] ?? '';
    $barangInput = $_POST['idbarang'] ?? [];
    $jumlahInput = $_POST['jumlah'] ?? [];
    $ppnInput = $_POST['ppn_persen'] ?? '11';

    $vendorTerpilih = is_scalar($vendorInput)
        ? trim((string) $vendorInput)
        : '';

    $barangTerpilih = is_array($barangInput)
        ? $barangInput
        : [''];

    $jumlahTerpilih = is_array($jumlahInput)
        ? $jumlahInput
        : [1];

    $ppnPersen = is_scalar($ppnInput)
        ? trim((string) $ppnInput)
        : '11';

    try {
        // Validasi vendor.
        if (
            $vendorTerpilih === ''
            || !ctype_digit($vendorTerpilih)
            || (int) $vendorTerpilih < 1
        ) {
            throw new RuntimeException(
                'Silakan pilih vendor.'
            );
        }

        // Validasi susunan detail barang.
        if (
            !is_array($barangInput)
            || !is_array($jumlahInput)
            || count($barangInput) === 0
            || count($barangInput) !== count($jumlahInput)
        ) {
            throw new RuntimeException(
                'Detail barang belum lengkap.'
            );
        }

        // Validasi PPN sebelum dikonversi menjadi angka.
        if (
            !is_scalar($ppnInput)
            || trim((string) $ppnInput) === ''
            || !is_numeric($ppnInput)
        ) {
            throw new RuntimeException(
                'Persentase PPN harus berupa angka.'
            );
        }

        $ppnPersen = (float) $ppnInput;

        if (
            !is_finite($ppnPersen)
            || $ppnPersen < 0
            || $ppnPersen > 100
        ) {
            throw new RuntimeException(
                'Persentase PPN harus antara 0 dan 100.'
            );
        }

        // Memastikan vendor masih aktif.
        $stmtCekVendor = $pdo->prepare("
            SELECT idvendor
            FROM vendor
            WHERE idvendor = ?
              AND status = 1
        ");

        $stmtCekVendor->execute([
            (int) $vendorTerpilih
        ]);

        if (!$stmtCekVendor->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'Vendor tidak ditemukan atau tidak aktif.'
            );
        }

        $detailBarang = [];
        $subtotal = 0;
        $barangSudahDipilih = [];

        // Memvalidasi barang dan mengambil harga dari database.
        foreach ($barangInput as $i => $idbarangInput) {
            $jumlahInputBaris = $jumlahInput[$i] ?? null;

            if (
                !is_scalar($idbarangInput)
                || trim((string) $idbarangInput) === ''
                || !ctype_digit((string) $idbarangInput)
                || (int) $idbarangInput < 1
            ) {
                throw new RuntimeException(
                    'Silakan pilih barang yang valid pada setiap baris.'
                );
            }

            if (
                !is_scalar($jumlahInputBaris)
                || filter_var(
                    $jumlahInputBaris,
                    FILTER_VALIDATE_INT
                ) === false
                || (int) $jumlahInputBaris < 1
            ) {
                throw new RuntimeException(
                    'Jumlah barang harus berupa bilangan bulat minimal 1.'
                );
            }

            $idbarang = (int) $idbarangInput;
            $jumlah = (int) $jumlahInputBaris;

            if (isset($barangSudahDipilih[$idbarang])) {
                throw new RuntimeException(
                    'Barang yang sama tidak boleh dipilih dua kali.'
                );
            }

            $barangSudahDipilih[$idbarang] = true;

            // Memastikan barang masih aktif.
            $stmtCekBarang = $pdo->prepare("
                SELECT idbarang, nama, harga
                FROM barang
                WHERE idbarang = ?
                  AND status = 1
            ");

            $stmtCekBarang->execute([$idbarang]);

            $barang = $stmtCekBarang->fetch(PDO::FETCH_ASSOC);

            if (!$barang) {
                throw new RuntimeException(
                    'Barang tidak ditemukan atau tidak aktif.'
                );
            }

            $harga = (int) $barang['harga'];
            $subtotalDetail = $harga * $jumlah;

            $subtotal += $subtotalDetail;

            $detailBarang[] = [
                'idbarang' => (int) $barang['idbarang'],
                'harga_satuan' => $harga,
                'jumlah' => $jumlah,
                'sub_total' => $subtotalDetail
            ];
        }

        // Menghitung subtotal, PPN, dan total transaksi.
        $subtotal = (int) round($subtotal);

        $ppn = (int) round(
            $subtotal * $ppnPersen / 100
        );

        $total = $subtotal + $ppn;

        // Menyimpan header dan detail sebagai satu transaksi.
        $pdo->beginTransaction();

        // Status awal pengadaan: 0 = Menunggu.
        $statusAwal = '0';

        $stmtPengadaan = $pdo->prepare("
            INSERT INTO pengadaan (
                `timestamp`,
                status,
                vendor_idvendor,
                subtotal_nilai,
                ppn,
                total_nilai,
                user_iduser
            )
            VALUES (
                CURRENT_TIMESTAMP,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmtPengadaan->execute([
            $statusAwal,
            (int) $vendorTerpilih,
            $subtotal,
            $ppn,
            $total,
            (int) $_SESSION['iduser']
        ]);

        $idPengadaan = (int) $pdo->lastInsertId();

        // Menyimpan setiap detail barang pengadaan.
        $stmtDetail = $pdo->prepare("
            INSERT INTO detail_pengadaan (
                harga_satuan,
                jumlah,
                sub_total,
                idbarang,
                idpengadaan
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($detailBarang as $detail) {
            $stmtDetail->execute([
                $detail['harga_satuan'],
                $detail['jumlah'],
                $detail['sub_total'],
                $detail['idbarang'],
                $idPengadaan
            ]);
        }

        // Simpan seluruh data jika semua proses berhasil.
        $pdo->commit();

        $_SESSION['pesan_pengadaan'] =
            'Pengadaan berhasil disimpan dengan ID '
            . $idPengadaan;

        header('Location: data_pengadaan.php');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof RuntimeException) {
            $pesanError = $e->getMessage();
        } else {
            error_log(
                'Tambah pengadaan: ' . $e->getMessage()
            );

            $pesanError =
                'Pengadaan gagal disimpan. Periksa pesan error PHP dan database.';
        }
    }
}

// Menyiapkan kembali data untuk formulir jika terjadi kesalahan.
foreach ($barangTerpilih as $i => $nilai) {
    $barangTerpilih[$i] = is_scalar($nilai)
        ? (string) $nilai
        : '';
}

foreach ($jumlahTerpilih as $i => $nilai) {
    if (
        !is_scalar($nilai)
        || filter_var(
            $nilai,
            FILTER_VALIDATE_INT
        ) === false
        || (int) $nilai < 1
    ) {
        $jumlahTerpilih[$i] = 1;
    } else {
        $jumlahTerpilih[$i] = (int) $nilai;
    }
}

if (empty($barangTerpilih)) {
    $barangTerpilih = [''];
    $jumlahTerpilih = [1];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Tambah Pengadaan - ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=103">

    <style>
        .pengadaan-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .form-card {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(12,27,72,.06);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label,
        .detail-row label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #24324b;
        }

        .form-group select,
        .form-group input,
        .detail-row select,
        .detail-row input {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 12px;
            border: 1px solid #d7dfeb;
            border-radius: 7px;
            background: #fff;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid #e8edf5;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .btn-tambah,
        .btn-simpan,
        .btn-kembali {
            display: inline-block;
            padding: 11px 16px;
            border: 0;
            border-radius: 7px;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-tambah {
            margin-top: 14px;
            background: #e8f1ff;
            color: #174a91;
        }

        .btn-simpan {
            background: #1e407b;
            color: #fff;
        }

        .btn-kembali {
            background: #edf1f7;
            color: #24324b;
        }

        .pesan-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .ringkasan {
            background: #f5f8fd;
            border-radius: 8px;
            padding: 16px;
            margin-top: 22px;
        }

        .ringkasan p {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 10px 0;
        }

        .ringkasan .total {
            border-top: 1px solid #d7dfeb;
            padding-top: 12px;
            font-size: 18px;
            font-weight: 700;
        }

        .aksi-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 22px;
        }

        @media (max-width: 600px) {
            .form-card {
                padding: 16px;
            }

            .detail-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <div class="pengadaan-container">

        <h1>Tambah Pengadaan</h1>

        <p>Masukkan vendor dan barang yang akan dipesan.</p>

        <?php if ($pesanError !== ''): ?>
            <div class="pesan-error">
                <?= h($pesanError) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="formPengadaan">

            <section class="form-card">

                <h2>Informasi Pengadaan</h2>

                <div class="form-group">

                    <label for="vendor_idvendor">
                        Vendor
                    </label>

                    <select
                        name="vendor_idvendor"
                        id="vendor_idvendor"
                        required
                    >
                        <option value="">
                            -- Pilih Vendor --
                        </option>

                        <?php foreach ($daftarVendor as $vendor): ?>

                            <option
                                value="<?= (int) $vendor['idvendor'] ?>"
                                <?= $vendorTerpilih ===
                                    (string) $vendor['idvendor']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= h($vendor['nama_vendor']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </section>

            <section class="form-card">

                <h2>Detail Barang</h2>

                <div id="daftarDetail">

                    <?php foreach (
                        $barangTerpilih as $i => $idDipilih
                    ): ?>

                        <div class="detail-row">

                            <div>

                                <label>Barang</label>

                                <select
                                    name="idbarang[]"
                                    class="pilihan-barang"
                                    required
                                >
                                    <option value="">
                                        -- Pilih Barang --
                                    </option>

                                    <?php foreach (
                                        $daftarBarang as $barang
                                    ): ?>

                                        <option
                                            value="<?= (int) $barang['idbarang'] ?>"
                                            data-harga="<?= (int) $barang['harga'] ?>"
                                            <?= (string) $idDipilih ===
                                                (string) $barang['idbarang']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= h($barang['nama']) ?>

                                            (<?= h(
                                                $barang['nama_satuan'] ?? '-'
                                            ) ?>)

                                            - Rp <?= number_format(
                                                (int) $barang['harga'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div>

                                <label>Jumlah</label>

                                <input
                                    type="number"
                                    name="jumlah[]"
                                    class="jumlah-barang"
                                    min="1"
                                    step="1"
                                    value="<?= (int) (
                                        $jumlahTerpilih[$i] ?? 1
                                    ) ?>"
                                    required
                                >

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

                <button
                    type="button"
                    class="btn-tambah"
                    id="btnTambahBarang"
                >
                    + Tambah Baris Barang
                </button>

                <div
                    class="form-group"
                    style="margin-top:24px"
                >

                    <label for="ppn_persen">
                        PPN (%)
                    </label>

                    <input
                        type="number"
                        name="ppn_persen"
                        id="ppn_persen"
                        min="0"
                        max="100"
                        step="0.01"
                        value="<?= h($ppnPersen) ?>"
                        required
                    >

                </div>

                <div class="ringkasan">

                    <p>
                        <span>Subtotal</span>

                        <strong id="tampilanSubtotal">
                            Rp 0
                        </strong>
                    </p>

                    <p>
                        <span>PPN</span>

                        <strong id="tampilanPpn">
                            Rp 0
                        </strong>
                    </p>

                    <p class="total">
                        <span>Total Pengadaan</span>

                        <strong id="tampilanTotal">
                            Rp 0
                        </strong>
                    </p>

                </div>

                <div class="aksi-form">

                    <a
                        href="data_pengadaan.php"
                        class="btn-kembali"
                    >
                        Kembali
                    </a>

                    <button
                        type="submit"
                        class="btn-simpan"
                    >
                        Simpan Pengadaan
                    </button>

                </div>

            </section>

        </form>

    </div>

</main>

<script>
const daftarDetail = document.getElementById('daftarDetail');
const tombolTambah = document.getElementById('btnTambahBarang');
const inputPpn = document.getElementById('ppn_persen');

function formatRupiah(nilai) {
    return 'Rp ' + Math.round(nilai).toLocaleString('id-ID');
}

function hitungTotal() {
    let subtotal = 0;

    document.querySelectorAll('.detail-row').forEach(function(baris) {
        const select = baris.querySelector('.pilihan-barang');
        const jumlahInput = baris.querySelector('.jumlah-barang');
        const opsi = select.options[select.selectedIndex];

        const harga = opsi && opsi.dataset.harga
            ? Number(opsi.dataset.harga)
            : 0;

        const jumlah = Math.max(
            0,
            Number(jumlahInput.value) || 0
        );

        subtotal += harga * jumlah;
    });

    const persen = Math.max(
        0,
        Number(inputPpn.value) || 0
    );

    const ppn = Math.round(subtotal * persen / 100);
    const total = subtotal + ppn;

    document.getElementById('tampilanSubtotal').textContent =
        formatRupiah(subtotal);

    document.getElementById('tampilanPpn').textContent =
        formatRupiah(ppn);

    document.getElementById('tampilanTotal').textContent =
        formatRupiah(total);
}

function perbaruiPilihanBarang() {
    const semuaSelect = document.querySelectorAll('.pilihan-barang');

    const pilihanTerpakai = Array.from(semuaSelect)
        .map(select => select.value)
        .filter(value => value !== '');

    semuaSelect.forEach(function(select) {
        Array.from(select.options).forEach(function(opsi) {
            if (opsi.value === '') {
                return;
            }

            opsi.disabled =
                pilihanTerpakai.includes(opsi.value) &&
                opsi.value !== select.value;
        });
    });
}

function pasangEvent(baris) {
    baris.querySelector('.pilihan-barang').addEventListener(
        'change',
        function() {
            perbaruiPilihanBarang();
            hitungTotal();
        }
    );

    baris.querySelector('.jumlah-barang').addEventListener(
        'input',
        hitungTotal
    );
}

document.querySelectorAll('.detail-row').forEach(pasangEvent);

tombolTambah.addEventListener('click', function() {
    const barisAwal = document.querySelector('.detail-row');

    if (!barisAwal) {
        return;
    }

    const barisBaru = barisAwal.cloneNode(true);

    barisBaru.querySelector('.pilihan-barang').selectedIndex = 0;
    barisBaru.querySelector('.jumlah-barang').value = 1;

    daftarDetail.appendChild(barisBaru);

    pasangEvent(barisBaru);
    perbaruiPilihanBarang();
    hitungTotal();
});

inputPpn.addEventListener('input', hitungTotal);

document.getElementById('formPengadaan').addEventListener(
    'submit',
    function(event) {
        const pilihan = Array.from(
            document.querySelectorAll('.pilihan-barang')
        ).map(select => select.value);

        if (pilihan.some(id => id === '')) {
            event.preventDefault();
            alert('Silakan pilih barang pada setiap baris.');
            return;
        }

        if (new Set(pilihan).size !== pilihan.length) {
            event.preventDefault();
            alert('Barang yang sama tidak boleh dipilih dua kali.');
        }
    }
);

perbaruiPilihanBarang();
hitungTotal();
</script>

</body>
</html>
