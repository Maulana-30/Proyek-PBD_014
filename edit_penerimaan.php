
<?php
session_start();

if (!isset($_SESSION['iduser'])) {
    header('Location: login.php');
    exit;
}

require 'config/database.php';

$halamanAktif = 'penerimaan';
$idpenerimaan = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$idpenerimaan || $idpenerimaan < 1) {
    $_SESSION['pesan_penerimaan'] = 'ID penerimaan tidak valid.';
    header('Location: data_penerimaan.php');
    exit;
}

$error = '';
$detail = [];
$penerimaan = null;
$adaRetur = false;

function e($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}

try {
    // Mengambil informasi utama penerimaan.
    $stmt = $pdo->prepare("
        SELECT
            p.idpenerimaan,
            p.created_at,
            p.status,
            p.idpengadaan,
            v.nama_vendor
        FROM penerimaan AS p
        LEFT JOIN pengadaan AS pg
            ON pg.idpengadaan = p.idpengadaan
        LEFT JOIN vendor AS v
            ON v.idvendor = pg.vendor_idvendor
        WHERE p.idpenerimaan = ?
    ");

    $stmt->execute([$idpenerimaan]);

    $penerimaan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$penerimaan) {
        $_SESSION['pesan_penerimaan'] =
            'Data penerimaan tidak ditemukan.';

        header('Location: data_penerimaan.php');
        exit;
    }

    // Memeriksa apakah penerimaan memiliki retur.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM retur
        WHERE idpenerimaan = ?
    ");

    $stmt->execute([$idpenerimaan]);

    $adaRetur = (int) $stmt->fetchColumn() > 0;

    // Mengambil detail barang.
    $stmt = $pdo->prepare("
        SELECT
            dp.iddetail_penerimaan,
            dp.barang_idbarang,
            dp.jumlah_terima,
            dp.harga_satuan_terima,
            dp.sub_total_terima,
            b.nama AS nama_barang
        FROM detail_penerimaan AS dp
        INNER JOIN barang AS b
            ON b.idbarang = dp.barang_idbarang
        WHERE dp.idpenerimaan = ?
        ORDER BY dp.iddetail_penerimaan ASC
    ");

    $stmt->execute([$idpenerimaan]);

    $detail = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Memproses formulir edit.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $statusBaru = $_POST['status'] ?? '';
        $jumlahInput = $_POST['jumlah_terima'] ?? [];

        if (!in_array(
            (string) $statusBaru,
            ['0', '1'],
            true
        )) {
            throw new RuntimeException(
                'Status penerimaan tidak valid.'
            );
        }

        if (!is_array($jumlahInput)) {
            throw new RuntimeException(
                'Format jumlah barang tidak valid.'
            );
        }

        $pdo->beginTransaction();

        // Mengunci header penerimaan selama proses edit.
        $stmt = $pdo->prepare("
            SELECT
                idpenerimaan,
                idpengadaan,
                status
            FROM penerimaan
            WHERE idpenerimaan = ?
            FOR UPDATE
        ");

        $stmt->execute([$idpenerimaan]);

        $header = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$header) {
            throw new RuntimeException(
                'Data penerimaan tidak ditemukan.'
            );
        }

        // Memeriksa ulang retur di dalam transaksi.
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM retur
            WHERE idpenerimaan = ?
        ");

        $stmt->execute([$idpenerimaan]);

        $adaReturSaatIni =
            (int) $stmt->fetchColumn() > 0;

        // Penerimaan dengan retur tidak boleh diubah statusnya.
        if (
            $adaReturSaatIni
            && (string) $statusBaru !== (string) $header['status']
        ) {
            throw new RuntimeException(
                'Status penerimaan tidak dapat diubah karena memiliki data retur.'
            );
        }

        // Mengunci detail penerimaan.
        $stmt = $pdo->prepare("
            SELECT
                iddetail_penerimaan,
                barang_idbarang,
                harga_satuan_terima,
                jumlah_terima
            FROM detail_penerimaan
            WHERE idpenerimaan = ?
            FOR UPDATE
        ");

        $stmt->execute([$idpenerimaan]);

        $detailLama = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $jumlahBaruPerBarang = [];
        $jumlahLamaPerBarang = [];

        foreach ($detailLama as $item) {
            $idDetail = (string)
                $item['iddetail_penerimaan'];

            $idBarang = (int)
                $item['barang_idbarang'];

            $jumlahLama = (int)
                $item['jumlah_terima'];

            $jumlahLamaPerBarang[$idBarang] =
                ($jumlahLamaPerBarang[$idBarang] ?? 0)
                + $jumlahLama;

            if (!$adaReturSaatIni) {
                if (!array_key_exists(
                    $idDetail,
                    $jumlahInput
                )) {
                    throw new RuntimeException(
                        'Jumlah barang tidak lengkap. Muat ulang halaman.'
                    );
                }

                $nilai = $jumlahInput[$idDetail];

                if (
                    !is_scalar($nilai)
                    || trim((string) $nilai) === ''
                    || !ctype_digit((string) $nilai)
                ) {
                    throw new RuntimeException(
                        'Jumlah barang harus berupa bilangan bulat nol atau lebih.'
                    );
                }

                $jumlahBaru = (int) $nilai;

                $jumlahBaruPerBarang[$idBarang] =
                    ($jumlahBaruPerBarang[$idBarang] ?? 0)
                    + $jumlahBaru;
            }
        }

        if (!$adaReturSaatIni) {
            // Mengambil total pesanan per barang.
            $stmt = $pdo->prepare("
                SELECT
                    idbarang,
                    SUM(jumlah) AS jumlah_dipesan
                FROM detail_pengadaan
                WHERE idpengadaan = ?
                GROUP BY idbarang
            ");

            $stmt->execute([$header['idpengadaan']]);

            $jumlahDipesan = [];

            foreach (
                $stmt->fetchAll(PDO::FETCH_ASSOC) as $item
            ) {
                $jumlahDipesan[(int) $item['idbarang']] =
                    (int) $item['jumlah_dipesan'];
            }

            // Menghitung penerimaan lain pada pengadaan yang sama.
            $stmt = $pdo->prepare("
                SELECT
                    dp.barang_idbarang,
                    SUM(dp.jumlah_terima) AS jumlah_diterima
                FROM detail_penerimaan AS dp
                INNER JOIN penerimaan AS p
                    ON p.idpenerimaan = dp.idpenerimaan
                WHERE p.idpengadaan = ?
                  AND p.idpenerimaan <> ?
                GROUP BY dp.barang_idbarang
            ");

            $stmt->execute([
                $header['idpengadaan'],
                $idpenerimaan
            ]);

            $jumlahLain = [];

            foreach (
                $stmt->fetchAll(PDO::FETCH_ASSOC) as $item
            ) {
                $jumlahLain[(int) $item['barang_idbarang']] =
                    (int) $item['jumlah_diterima'];
            }

            // Memastikan jumlah tidak melebihi pesanan.
            foreach (
                $jumlahBaruPerBarang as $idBarang => $jumlahBaru
            ) {
                $maksimal =
                    ($jumlahDipesan[$idBarang] ?? 0)
                    - ($jumlahLain[$idBarang] ?? 0);

                if ($jumlahBaru > $maksimal) {
                    throw new RuntimeException(
                        'Jumlah penerimaan barang ID '
                        . $idBarang
                        . ' melebihi sisa jumlah pengadaan.'
                    );
                }
            }

            // Memperbarui jumlah dan subtotal detail penerimaan.
            $stmtUpdate = $pdo->prepare("
                UPDATE detail_penerimaan
                SET
                    jumlah_terima = ?,
                    sub_total_terima =
                        ? * harga_satuan_terima
                WHERE iddetail_penerimaan = ?
                  AND idpenerimaan = ?
            ");

            foreach ($detailLama as $item) {
                $idDetail = (string)
                    $item['iddetail_penerimaan'];

                $jumlahBaru = (int)
                    $jumlahInput[$idDetail];

                $stmtUpdate->execute([
                    $jumlahBaru,
                    $jumlahBaru,
                    $item['iddetail_penerimaan'],
                    $idpenerimaan
                ]);
            }

            /*
             * Menyesuaikan kartu stok.
             *
             * Status penerimaan 1 berarti stok aktif.
             * Status 0 berarti stok dari penerimaan ini tidak aktif.
             */
            $statusLama = (string) $header['status'];

            $stokAktifLama = $statusLama === '1';
            $stokAktifBaru = (string) $statusBaru === '1';

            $semuaBarang = array_unique(array_merge(
                array_keys($jumlahLamaPerBarang),
                array_keys($jumlahBaruPerBarang)
            ));

            $stmtKartu = $pdo->prepare("
                SELECT
                    idkartu_stok,
                    masuk,
                    keluar
                FROM kartu_stok
                WHERE idtransaksi = ?
                  AND jenis_transaksi = 'M'
                  AND idbarang = ?
                ORDER BY idkartu_stok ASC
                FOR UPDATE
            ");

            $stmtUpdateKartu = $pdo->prepare("
                UPDATE kartu_stok
                SET masuk = ?
                WHERE idkartu_stok = ?
            ");

            $stmtInsertKartu = $pdo->prepare("
                INSERT INTO kartu_stok (
                    jenis_transaksi,
                    masuk,
                    keluar,
                    stock,
                    created_at,
                    idtransaksi,
                    idbarang
                )
                VALUES ('M', ?, 0, ?, CURRENT_TIMESTAMP, ?, ?)
            ");

            foreach ($semuaBarang as $idBarang) {
                $idBarang = (int) $idBarang;

                $jumlahLama =
                    $jumlahLamaPerBarang[$idBarang] ?? 0;

                $jumlahBaru =
                    $jumlahBaruPerBarang[$idBarang] ?? 0;

                $masukLama = $stokAktifLama
                    ? $jumlahLama
                    : 0;

                $masukBaru = $stokAktifBaru
                    ? $jumlahBaru
                    : 0;

                $selisih = $masukBaru - $masukLama;

                $stmtKartu->execute([
                    $idpenerimaan,
                    $idBarang
                ]);

                $kartu = $stmtKartu->fetch(
                    PDO::FETCH_ASSOC
                );

                if ($kartu) {
                    $masukTercatat = (int) $kartu['masuk'];

                    // Menghentikan perubahan jika kartu stok
                    // lama tidak sesuai dengan jumlah lama.
                    if ($masukTercatat !== $masukLama) {
                        throw new RuntimeException(
                            'Catatan kartu stok barang ID '
                            . $idBarang
                            . ' tidak sesuai dengan jumlah penerimaan lama. '
                            . 'Periksa kartu stok sebelum melanjutkan.'
                        );
                    }

                    $stmtUpdateKartu->execute([
                        $masukBaru,
                        $kartu['idkartu_stok']
                    ]);
                } elseif ($masukBaru > 0) {
                    // Membuat catatan masuk jika belum tersedia.
                    $stmtInsertKartu->execute([
                        $masukBaru,
                        0,
                        $idpenerimaan,
                        $idBarang
                    ]);
                }

                // Menghitung ulang saldo berjalan jika ada perubahan.
                if ($selisih !== 0) {
                    $stmtRiwayat = $pdo->prepare("
                        SELECT
                            idkartu_stok,
                            masuk,
                            keluar
                        FROM kartu_stok
                        WHERE idbarang = ?
                        ORDER BY idkartu_stok ASC
                        FOR UPDATE
                    ");

                    $stmtRiwayat->execute([$idBarang]);

                    $riwayat = $stmtRiwayat->fetchAll(
                        PDO::FETCH_ASSOC
                    );

                    $saldo = 0;

                    $stmtUpdateSaldo = $pdo->prepare("
                        UPDATE kartu_stok
                        SET stock = ?
                        WHERE idkartu_stok = ?
                    ");

                    foreach ($riwayat as $baris) {
                        $saldo += (int) $baris['masuk'];
                        $saldo -= (int) $baris['keluar'];

                        $stmtUpdateSaldo->execute([
                            $saldo,
                            $baris['idkartu_stok']
                        ]);
                    }
                }
            }
        }

        // Memperbarui status penerimaan.
        $stmt = $pdo->prepare("
            UPDATE penerimaan
            SET status = ?
            WHERE idpenerimaan = ?
        ");

        $stmt->execute([
            $statusBaru,
            $idpenerimaan
        ]);

        // Menghitung ulang status pengadaan berdasarkan seluruh detail.
        $stmtStatus = $pdo->prepare("
            SELECT
                pesanan.idbarang,
                pesanan.jumlah_pesan,
                COALESCE(
                    terima.jumlah_terima,
                    0
                ) AS jumlah_terima
            FROM (
                SELECT
                    idbarang,
                    SUM(jumlah) AS jumlah_pesan
                FROM detail_pengadaan
                WHERE idpengadaan = ?
                GROUP BY idbarang
            ) AS pesanan
            LEFT JOIN (
                SELECT
                    dp.barang_idbarang,
                    SUM(dp.jumlah_terima) AS jumlah_terima
                FROM detail_penerimaan AS dp
                INNER JOIN penerimaan AS p
                    ON p.idpenerimaan = dp.idpenerimaan
                WHERE p.idpengadaan = ?
                GROUP BY dp.barang_idbarang
            ) AS terima
                ON terima.barang_idbarang = pesanan.idbarang
        ");

        $stmtStatus->execute([
            $header['idpengadaan'],
            $header['idpengadaan']
        ]);

        $statusBarang = $stmtStatus->fetchAll(
            PDO::FETCH_ASSOC
        );

        $semuaSelesai = !empty($statusBarang);
        $adaPenerimaan = false;

        foreach ($statusBarang as $barang) {
            $jumlahPesan = (int) $barang['jumlah_pesan'];
            $jumlahDiterima = (int) $barang['jumlah_terima'];

            if ($jumlahDiterima > 0) {
                $adaPenerimaan = true;
            }

            if ($jumlahDiterima < $jumlahPesan) {
                $semuaSelesai = false;
            }
        }

        if ($semuaSelesai) {
            $statusPengadaanBaru = '2';
        } elseif ($adaPenerimaan) {
            $statusPengadaanBaru = '1';
        } else {
            $statusPengadaanBaru = '0';
        }

        $stmtUpdatePengadaan = $pdo->prepare("
            UPDATE pengadaan
            SET status = ?
            WHERE idpengadaan = ?
        ");

        $stmtUpdatePengadaan->execute([
            $statusPengadaanBaru,
            $header['idpengadaan']
        ]);

        // Seluruh perubahan disimpan bersamaan.
        $pdo->commit();

        $_SESSION['pesan_penerimaan'] =
            'Data penerimaan berhasil diperbarui.';

        header('Location: data_penerimaan.php');
        exit;
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Penerimaan | ATK STORE</title>

    <link rel="stylesheet" href="assets/style.css?v=115">
</head>

<body>

<?php require 'includes/sidebar.php'; ?>

<main class="main-content">

    <header class="page-header">
        <div>
            <h1>Edit Data Penerimaan</h1>
            <p>Perbarui status dan jumlah barang yang diterima.</p>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($adaRetur): ?>
        <div class="alert">
            Penerimaan ini memiliki data retur. Jumlah barang dan status
            penerimaan tidak dapat diubah agar data retur dan kartu stok
            tetap konsisten.
        </div>
    <?php endif; ?>

    <section class="card">

        <div class="card-header">
            <h2>Informasi Penerimaan</h2>
        </div>

        <div style="padding: 20px;">
            <p>
                <strong>ID Penerimaan:</strong>
                <?= (int) $penerimaan['idpenerimaan'] ?>
            </p>

            <p>
                <strong>Tanggal:</strong>
                <?= e($penerimaan['created_at']) ?>
            </p>

            <p>
                <strong>ID Pengadaan:</strong>
                <?= (int) $penerimaan['idpengadaan'] ?>
            </p>

            <p>
                <strong>Vendor:</strong>
                <?= e($penerimaan['nama_vendor'] ?? '-') ?>
            </p>
        </div>

    </section>

    <section class="card" style="margin-top: 20px;">

        <div class="card-header">
            <h2>Ubah Data</h2>
        </div>

        <form method="POST" style="padding: 20px;">

            <div class="form-group">
                <label for="status">Status Penerimaan</label>

                <select
                    name="status"
                    id="status"
                    class="form-control"
                    required
                    <?= $adaRetur ? 'disabled' : '' ?>
                >
                    <option
                        value="0"
                        <?= (string) $penerimaan['status'] === '0'
                            ? 'selected'
                            : '' ?>
                    >
                        Menunggu
                    </option>

                    <option
                        value="1"
                        <?= (string) $penerimaan['status'] === '1'
                            ? 'selected'
                            : '' ?>
                    >
                        Selesai
                    </option>
                </select>

                <?php if ($adaRetur): ?>
                    <input
                        type="hidden"
                        name="status"
                        value="<?= e($penerimaan['status']) ?>"
                    >
                <?php endif; ?>
            </div>

            <h3>Detail Barang</h3>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Barang</th>
                            <th>Harga Satuan</th>
                            <th>Jumlah Diterima</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if ($detail): ?>

                            <?php foreach ($detail as $i => $item): ?>

                                <tr>
                                    <td><?= $i + 1 ?></td>

                                    <td>
                                        <?= e($item['nama_barang']) ?>
                                    </td>

                                    <td>
                                        Rp <?= number_format(
                                            (int) $item['harga_satuan_terima'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            class="form-control"
                                            name="jumlah_terima[<?= (int) $item['iddetail_penerimaan'] ?>]"
                                            min="0"
                                            step="1"
                                            required
                                            value="<?= e(
                                                $_POST['jumlah_terima'][$item['iddetail_penerimaan']]
                                                ?? $item['jumlah_terima']
                                            ) ?>"
                                            <?= $adaRetur ? 'disabled' : '' ?>
                                        >
                                    </td>

                                    <td>
                                        Rp <?= number_format(
                                            (int) $item['sub_total_terima'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5" class="empty-state">
                                    Tidak ada detail barang.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <div class="form-actions" style="margin-top: 20px;">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Simpan Perubahan
                </button>

                <a
                    href="data_penerimaan.php"
                    class="btn btn-secondary"
                >
                    Batal
                </a>

            </div>

        </form>

    </section>

</main>

</body>
</html>
