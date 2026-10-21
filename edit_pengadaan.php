
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';

$halamanAktif = 'pengadaan';

$idpengadaan = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$idpengadaan || $idpengadaan < 1) {
    $_SESSION['pesan_pengadaan'] = 'ID pengadaan tidak valid.';
    header('Location: data_pengadaan.php');
    exit;
}

$pesanError = '';
$vendorTerpilih = '';
$statusTerpilih = '0';
$ppnPersen = 11;
$barangTerpilih = [];
$jumlahTerpilih = [];
$daftarDetail = [];
$pengadaan = null;
$adaPenerimaan = false;

function h($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiahEdit($nilai)
{
    return 'Rp ' . number_format(
        (float) $nilai,
        0,
        ',',
        '.'
    );
}

try {
    // Mengambil data utama pengadaan.
    $stmt = $pdo->prepare("
        SELECT
            idpengadaan,
            `timestamp`,
            status,
            vendor_idvendor,
            subtotal_nilai,
            ppn,
            total_nilai,
            user_iduser
        FROM pengadaan
        WHERE idpengadaan = ?
    ");

    $stmt->execute([$idpengadaan]);

    $pengadaan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pengadaan) {
        $_SESSION['pesan_pengadaan'] =
            'Data pengadaan tidak ditemukan.';

        header('Location: data_pengadaan.php');
        exit;
    }

    $vendorTerpilih = (string) $pengadaan['vendor_idvendor'];
    $statusTerpilih = (string) $pengadaan['status'];

    // Mengambil detail barang pengadaan.
    $stmt = $pdo->prepare("
        SELECT
            dp.iddetail_pengadaan,
            dp.idbarang,
            dp.harga_satuan,
            dp.jumlah,
            dp.sub_total,
            b.nama,
            b.harga AS harga_barang
        FROM detail_pengadaan AS dp
        LEFT JOIN barang AS b
            ON b.idbarang = dp.idbarang
        WHERE dp.idpengadaan = ?
        ORDER BY dp.iddetail_pengadaan ASC
    ");

    $stmt->execute([$idpengadaan]);

    $daftarDetail = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($daftarDetail as $item) {
        $barangTerpilih[] = (string) $item['idbarang'];
        $jumlahTerpilih[] = (int) $item['jumlah'];
    }

    // Memeriksa apakah pengadaan sudah memiliki penerimaan.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM penerimaan
        WHERE idpengadaan = ?
    ");

    $stmt->execute([$idpengadaan]);

    $adaPenerimaan = (int) $stmt->fetchColumn() > 0;

    // Memproses formulir.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();

        // Mengunci header agar pengadaan tidak diubah bersamaan
        // melalui proses lain yang menggunakan penguncian yang sama.
        $stmt = $pdo->prepare("
            SELECT idpengadaan
            FROM pengadaan
            WHERE idpengadaan = ?
            FOR UPDATE
        ");

        $stmt->execute([$idpengadaan]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'Data pengadaan tidak ditemukan.'
            );
        }

        // Memeriksa ulang penerimaan setelah pengadaan dikunci.
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM penerimaan
            WHERE idpengadaan = ?
        ");

        $stmt->execute([$idpengadaan]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'Pengadaan tidak dapat diedit karena sudah memiliki transaksi penerimaan. Periksa data melalui menu Penerimaan.'
            );
        }

        // Mengambil input formulir.
        $vendorTerpilih = (string) (
            $_POST['vendor_idvendor'] ?? ''
        );

        $statusTerpilih = (string) (
            $_POST['status'] ?? ''
        );

        $barangTerpilih = $_POST['idbarang'] ?? [];
        $jumlahTerpilih = $_POST['jumlah'] ?? [];

        $ppnInput = $_POST['ppn_persen'] ?? '11';

        // Status pengadaan ditentukan oleh proses penerimaan.
        if ($statusTerpilih !== '0') {
            throw new RuntimeException(
                'Status pengadaan ditentukan melalui proses penerimaan barang.'
            );
        }

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

        // Validasi detail barang.
        if (
            !is_array($barangTerpilih)
            || !is_array($jumlahTerpilih)
            || count($barangTerpilih) < 1
            || count($barangTerpilih) !== count($jumlahTerpilih)
        ) {
            throw new RuntimeException(
                'Detail barang belum lengkap.'
            );
        }

        // Validasi PPN.
        if (
            !is_scalar($ppnInput)
            || trim((string) $ppnInput) === ''
            || !is_numeric($ppnInput)
        ) {
            throw new RuntimeException(
                'Persentase PPN tidak valid.'
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
        $stmt = $pdo->prepare("
            SELECT idvendor
            FROM vendor
            WHERE idvendor = ?
              AND status = 1
        ");

        $stmt->execute([(int) $vendorTerpilih]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'Vendor tidak ditemukan atau tidak aktif.'
            );
        }

        // Mengingat barang yang sudah terdapat pada pengadaan lama.
        // Barang lama boleh dipertahankan meskipun sudah tidak aktif.
        $idBarangLama = array_map(
            'intval',
            array_column($daftarDetail, 'idbarang')
        );

        $detailBaru = [];
        $barangSudahDipilih = [];
        $subtotal = 0;

        // Validasi setiap barang dan ambil harga dari database.
        foreach ($barangTerpilih as $i => $idbarangInput) {
            $jumlahInput = $jumlahTerpilih[$i] ?? null;

            if (
                !is_scalar($idbarangInput)
                || !ctype_digit((string) $idbarangInput)
                || (int) $idbarangInput < 1
            ) {
                throw new RuntimeException(
                    'Silakan pilih barang yang valid.'
                );
            }

            if (
                !is_scalar($jumlahInput)
                || filter_var(
                    $jumlahInput,
                    FILTER_VALIDATE_INT
                ) === false
                || (int) $jumlahInput < 1
            ) {
                throw new RuntimeException(
                    'Jumlah setiap barang minimal 1.'
                );
            }

            $idbarang = (int) $idbarangInput;
            $jumlah = (int) $jumlahInput;

            if (isset($barangSudahDipilih[$idbarang])) {
                throw new RuntimeException(
                    'Barang yang sama tidak boleh dipilih dua kali.'
                );
            }

            $barangSudahDipilih[$idbarang] = true;

            $stmt = $pdo->prepare("
                SELECT
                    idbarang,
                    nama,
                    harga,
                    status
                FROM barang
                WHERE idbarang = ?
            ");

            $stmt->execute([$idbarang]);

            $barang = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$barang) {
                throw new RuntimeException(
                    'Barang dengan ID ' . $idbarang .
                    ' tidak ditemukan.'
                );
            }

            if (
                (int) $barang['status'] !== 1
                && !in_array(
                    $idbarang,
                    $idBarangLama,
                    true
                )
            ) {
                throw new RuntimeException(
                    'Barang baru yang dipilih harus berstatus aktif.'
                );
            }

            $harga = (int) $barang['harga'];
            $subtotalDetail = $harga * $jumlah;

            $subtotal += $subtotalDetail;

            $detailBaru[] = [
                'idbarang' => $idbarang,
                'harga_satuan' => $harga,
                'jumlah' => $jumlah,
                'sub_total' => $subtotalDetail
            ];
        }

        // Hitung ulang seluruh nilai transaksi.
        $subtotal = (int) round($subtotal);
        $ppn = (int) round(
            $subtotal * $ppnPersen / 100
        );
        $total = $subtotal + $ppn;

        // Perbarui header pengadaan.
        // Status tetap Menunggu karena belum ada penerimaan.
        $stmt = $pdo->prepare("
            UPDATE pengadaan
            SET
                status = '0',
                vendor_idvendor = ?,
                subtotal_nilai = ?,
                ppn = ?,
                total_nilai = ?
            WHERE idpengadaan = ?
        ");

        $stmt->execute([
            (int) $vendorTerpilih,
            $subtotal,
            $ppn,
            $total,
            $idpengadaan
        ]);

        // Hapus detail lama.
        $stmt = $pdo->prepare("
            DELETE FROM detail_pengadaan
            WHERE idpengadaan = ?
        ");

        $stmt->execute([$idpengadaan]);

        // Masukkan detail pengadaan yang sudah divalidasi.
        $stmt = $pdo->prepare("
            INSERT INTO detail_pengadaan (
                harga_satuan,
                jumlah,
                sub_total,
                idbarang,
                idpengadaan
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($detailBaru as $item) {
            $stmt->execute([
                $item['harga_satuan'],
                $item['jumlah'],
                $item['sub_total'],
                $item['idbarang'],
                $idpengadaan
            ]);
        }

        // Simpan perubahan jika seluruh proses berhasil.
        $pdo->commit();

        $_SESSION['pesan_pengadaan'] =
            'Data pengadaan ID ' . $idpengadaan .
            ' berhasil diperbarui.';

        header('Location: data_pengadaan.php');
        exit;
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof RuntimeException) {
        $pesanError = $e->getMessage();
    } else {
        error_log('Edit pengadaan: ' . $e->getMessage());

        $pesanError =
            'Pengadaan gagal diperbarui. Periksa log PHP dan database.';
    }
}

// Mengambil vendor aktif.
$stmtVendor = $pdo->query("
    SELECT
        idvendor,
        nama_vendor
    FROM vendor
    WHERE status = 1
    ORDER BY nama_vendor
");

$daftarVendor = $stmtVendor->fetchAll(PDO::FETCH_ASSOC);

// Mengambil barang aktif dan barang lama pada transaksi ini.
// ID pengadaan menggunakan parameter agar query tetap aman.
$stmtBarang = $pdo->prepare("
    SELECT
        b.idbarang,
        b.nama,
        b.harga,
        b.status,
        s.nama_satuan
    FROM barang AS b
    LEFT JOIN satuan AS s
        ON s.idsatuan = b.idsatuan
    WHERE b.status = 1
       OR b.idbarang IN (
            SELECT dp.idbarang
            FROM detail_pengadaan AS dp
            WHERE dp.idpengadaan = ?
       )
    ORDER BY b.nama
");

$stmtBarang->execute([$idpengadaan]);

$daftarBarang = $stmtBarang->fetchAll(PDO::FETCH_ASSOC);

if (empty($barangTerpilih)) {
    $barangTerpilih = [''];
    $jumlahTerpilih = [1];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Pengadaan - ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=105">

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

        .info-penerimaan {
            padding: 12px 16px;
            margin-bottom: 18px;
            border-radius: 7px;
            background: #fff7e6;
            color: #854d0e;
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

        <h1>Edit Pengadaan</h1>
        <p>Ubah informasi dan detail barang pada transaksi pengadaan.</p>

        <?php if ($pesanError !== ''): ?>
            <div class="pesan-error">
                <?= h($pesanError) ?>
            </div>
        <?php endif; ?>

        <?php if ($adaPenerimaan): ?>
            <div class="info-penerimaan">
                Pengadaan ini sudah memiliki transaksi penerimaan.
                Vendor, status, dan rincian barang tidak dapat diubah
                melalui halaman ini. Silakan periksa transaksi terkait
                melalui menu Penerimaan.
            </div>
        <?php endif; ?>

        <form method="POST" id="formPengadaan">

            <section class="form-card">
                <h2>Informasi Pengadaan</h2>

                <div class="form-group">
                    <label>ID Pengadaan</label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= (int) $pengadaan['idpengadaan'] ?>"
                        disabled
                    >
                </div>

                <div class="form-group">
                    <label>Tanggal Pengadaan</label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= h($pengadaan['timestamp']) ?>"
                        disabled
                    >
                </div>

                <div class="form-group">
                    <label for="vendor_idvendor">Vendor</label>

                    <select
                        name="vendor_idvendor"
                        id="vendor_idvendor"
                        required
                        <?= $adaPenerimaan ? 'disabled' : '' ?>
                    >
                        <option value="">-- Pilih Vendor --</option>

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

                <div class="form-group">
                    <label for="status">Status Pengadaan</label>

                    <select
                        name="status"
                        id="status"
                        required
                        <?= $adaPenerimaan ? 'disabled' : '' ?>
                    >
                        <option value="0" selected>
                            Menunggu
                        </option>
                    </select>
                </div>
            </section>

            <section class="form-card">
                <h2>Detail Barang</h2>

                <div id="daftarDetail">

                    <?php foreach ($barangTerpilih as $i => $idDipilih): ?>

                        <div class="detail-row">

                            <div>
                                <label>Barang</label>

                                <select
                                    name="idbarang[]"
                                    class="pilihan-barang"
                                    required
                                    <?= $adaPenerimaan ? 'disabled' : '' ?>
                                >
                                    <option value="">
                                        -- Pilih Barang --
                                    </option>

                                    <?php foreach ($daftarBarang as $barang): ?>
                                        <option
                                            value="<?= (int) $barang['idbarang'] ?>"
                                            data-harga="<?= (int) $barang['harga'] ?>"
                                            <?= (string) $idDipilih ===
                                                (string) $barang['idbarang']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= h($barang['nama']) ?>
                                            (<?= h($barang['nama_satuan'] ?? '-') ?>)
                                            - <?= rupiahEdit($barang['harga']) ?>
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
                                    value="<?= (int) ($jumlahTerpilih[$i] ?? 1) ?>"
                                    required
                                    <?= $adaPenerimaan ? 'disabled' : '' ?>
                                >
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

                <?php if (!$adaPenerimaan): ?>
                    <button
                        type="button"
                        class="btn-tambah"
                        id="btnTambahBarang"
                    >
                        + Tambah Baris Barang
                    </button>
                <?php endif; ?>

                <div class="form-group" style="margin-top:24px">
                    <label for="ppn_persen">PPN (%)</label>

                    <input
                        type="number"
                        name="ppn_persen"
                        id="ppn_persen"
                        min="0"
                        max="100"
                        step="0.01"
                        value="<?= h($ppnPersen) ?>"
                        required
                        <?= $adaPenerimaan ? 'disabled' : '' ?>
                    >
                </div>

                <div class="ringkasan">
                    <p>
                        <span>Subtotal</span>
                        <strong id="tampilanSubtotal">
                            <?= rupiahEdit($pengadaan['subtotal_nilai']) ?>
                        </strong>
                    </p>

                    <p>
                        <span>PPN</span>
                        <strong id="tampilanPpn">
                            <?= rupiahEdit($pengadaan['ppn']) ?>
                        </strong>
                    </p>

                    <p class="total">
                        <span>Total Pengadaan</span>
                        <strong id="tampilanTotal">
                            <?= rupiahEdit($pengadaan['total_nilai']) ?>
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

                    <?php if (!$adaPenerimaan): ?>
                        <button
                            type="submit"
                            class="btn-simpan"
                        >
                            Simpan Perubahan
                        </button>
                    <?php endif; ?>

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

// Aktifkan perhitungan hanya jika form dapat diedit.
if (tombolTambah && inputPpn) {
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
}
</script>

</body>
</html>
