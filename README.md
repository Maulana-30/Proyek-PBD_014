# ATK STORE — Sistem Pengelolaan Barang

**ATK STORE** adalah aplikasi berbasis web untuk mengelola data barang dan aktivitas operasional toko alat tulis kantor (ATK). Aplikasi ini dikembangkan menggunakan PHP, MySQL/MariaDB, HTML, dan CSS dengan tujuan membantu pengelolaan data barang, pemasok, pengguna, persediaan, serta transaksi toko secara terstruktur.

Proyek ini merupakan bagian dari tugas **Praktikum Pemrograman Basis Data (PBD)**.

## Fitur Utama

### 1. Autentikasi Pengguna
- Login menggunakan username dan password.
- Pengelolaan sesi pengguna.
- Logout untuk mengakhiri sesi.

### 2. Pengelolaan Data Master
- **Data Barang:** mengelola informasi barang, jenis, satuan, status, dan harga.
- **Data Satuan:** mengelola satuan barang.
- **Data Vendor:** mengelola data pemasok.
- **Data User:** mengelola akun pengguna.
- **Data Role:** mengelola peran pengguna.
- **Margin Penjualan:** mengelola margin penjualan barang.

### 3. Pengelolaan Transaksi
- **Pengadaan:** mencatat pengadaan atau pemesanan barang dari vendor.
- **Penerimaan:** mencatat barang yang diterima berdasarkan pengadaan.
- **Penjualan:** mencatat transaksi penjualan barang.
- **Retur:** mengelola pengembalian barang.
- **Detail Transaksi:** menampilkan informasi barang dan rincian pada transaksi terkait.

### 4. Pengelolaan Persediaan
- Pencatatan stok awal barang.
- Kartu stok untuk memantau pergerakan persediaan.
- Pencatatan barang masuk dan barang keluar.
- Pembaruan stok melalui mekanisme database yang tersedia.

### 5. Dashboard
- Halaman utama untuk mengakses modul pengelolaan barang dan transaksi.

## Teknologi yang Digunakan

| Teknologi | Kegunaan |
|---|---|
| PHP | Pemrosesan logika aplikasi dan server-side |
| MySQL / MariaDB | Penyimpanan dan pengelolaan basis data |
| PDO | Koneksi dan interaksi PHP dengan database |
| HTML | Struktur halaman web |
| CSS | Tampilan dan tata letak antarmuka |
| XAMPP | Lingkungan pengembangan lokal |
| Visual Studio Code | Editor kode sumber |
| phpMyAdmin | Pengelolaan dan impor database |

## Struktur Proyek

```text
Proyek-PBD_014-main/
├── assets/
│   └── style.css
├── config/
│   └── database.php
├── includes/
│   └── sidebar.php
├── index.php
├── login.php
├── logout.php
├── dashboard.php
├── cek_koneksi.php
├── data_barang.php
├── data_satuan.php
├── data_vendor.php
├── data_user.php
├── data_role.php
├── data_margin_penjualan.php
├── data_pengadaan.php
├── data_penerimaan.php
├── data_penjualan.php
├── data_retur.php
├── data_kartu_stok.php
├── tambah_barang.php
├── tambah_satuan.php
├── tambah_vendor.php
├── tambah_user.php
├── tambah_role.php
├── tambah_margin_penjualan.php
├── tambah_pengadaan.php
├── tambah_penerimaan.php
├── tambah_penjualan.php
├── tambah_retur.php
├── tambah_stok_awal.php
├── edit_barang.php
├── edit_satuan.php
├── edit_vendor.php
├── edit_user.php
├── edit_role.php
├── edit_margin_penjualan.php
├── edit_pengadaan.php
├── edit_penerimaan.php
├── edit_retur.php
├── hapus_barang.php
├── hapus_satuan.php
├── hapus_vendor.php
├── hapus_user.php
├── hapus_role.php
├── hapus_pengadaan.php
├── hapus_penerimaan.php
├── hapus_penjualan.php
├── hapus_retur.php
├── detail_pengadaan.php
├── detail_penerimaan.php
├── detail_penjualan.php
├── detail_retur.php
└── sistem_pengelolaan_barang (2).sql
```

*Catatan: daftar di atas merangkum file-file utama aplikasi. File SQL merupakan ekspor database yang disertakan dalam proyek.*

## ⚙️ Persyaratan Sistem

Sebelum menjalankan aplikasi, pastikan perangkat telah memiliki:

- XAMPP atau lingkungan server PHP dan MySQL/MariaDB yang setara.
- PHP dengan ekstensi PDO MySQL aktif.
- MySQL atau MariaDB.
- Browser web.
- Visual Studio Code (opsional, untuk mengedit kode sumber).

## 🚀 Cara Instalasi dan Menjalankan Aplikasi

### 1. Unduh Repository

Clone repository melalui terminal:

```bash
git clone https://github.com/USERNAME/Proyek-PBD_014.git
```

Ganti `USERNAME/Proyek-PBD_014` dengan alamat repository GitHub yang sebenarnya.

Alternatifnya, unduh repository melalui tombol **Code → Download ZIP**, kemudian ekstrak file ZIP tersebut.

### 2. Pindahkan Folder Proyek

Jika menggunakan XAMPP di Windows, letakkan folder proyek di direktori:

```text
C:\xampp\htdocs\Proyek-PBD_014
```

Pastikan file `index.php`, folder `config`, `assets`, dan `includes` berada di dalam folder proyek.

### 3. Jalankan XAMPP

Buka XAMPP Control Panel, kemudian aktifkan:

- **Apache**
- **MySQL**

Pastikan kedua layanan berhasil berjalan.

### 4. Buat Database

1. Buka phpMyAdmin melalui `http://localhost/phpmyadmin`.
2. Buat database baru dengan nama:

   ```text
   sistem_pengelolaan_barang
   ```

3. Pilih database tersebut.
4. Buka menu **Import**.
5. Pilih file `sistem_pengelolaan_barang (2).sql` dari folder proyek.
6. Jalankan proses impor dan pastikan tidak ada pesan kesalahan.

File SQL tersebut berisi struktur database beserta data contoh dan komponen database yang disertakan dalam ekspor.

### 5. Konfigurasi Koneksi Database

Buka file `config/database.php`, lalu sesuaikan konfigurasi koneksi dengan lingkungan lokal:

```php
<?php

$host = 'localhost';
$dbname = 'sistem_pengelolaan_barang';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
```

Konfigurasi di atas mengikuti konfigurasi lokal yang terdapat dalam proyek. Sesuaikan username dan password apabila konfigurasi MySQL pada perangkat berbeda.

### 6. Jalankan Aplikasi

Buka browser dan akses:

```text
http://localhost/Proyek-PBD_014/
```

Untuk memeriksa koneksi database, kamu juga dapat mencoba:

```text
http://localhost/Proyek-PBD_014/cek_koneksi.php
```

Pastikan nama folder pada URL sesuai dengan nama folder proyek di `htdocs`.

## 🗄️ Basis Data

Aplikasi menggunakan database `sistem_pengelolaan_barang` untuk menyimpan data operasional toko.

Database mencakup kelompok data berikut:

- **Data master:** barang, satuan, vendor, user, role, dan margin penjualan.
- **Data transaksi:** pengadaan, penerimaan, penjualan, dan retur.
- **Data detail transaksi:** detail pengadaan, detail penerimaan, detail penjualan, dan detail retur.
- **Data persediaan:** kartu stok.

Database juga menyertakan stored procedure, function, dan trigger untuk mendukung beberapa proses pengelolaan data dan persediaan.

## Catatan Keamanan

- Gunakan password database yang sesuai dengan konfigurasi server.
- Jangan mengunggah password asli, kredensial produksi, atau informasi rahasia ke repository publik.
- Gunakan password yang di-hash untuk akun aplikasi.
- Lakukan validasi input pengguna dan gunakan prepared statements ketika mengakses database.
- Untuk penggunaan di lingkungan produksi, sesuaikan penanganan error agar informasi internal database tidak ditampilkan kepada pengguna.

## Tujuan Proyek

Proyek ini bertujuan untuk menerapkan konsep pemrograman basis data dalam aplikasi pengelolaan toko ATK, meliputi:

- Penerapan operasi CRUD (*Create, Read, Update, Delete*).
- Pengelolaan relasi antartabel.
- Implementasi koneksi PHP dengan MySQL/MariaDB.
- Pengelolaan transaksi dan persediaan barang.
- Penerapan stored procedure, function, dan trigger pada database.
- Integrasi basis data dengan antarmuka aplikasi web.

## Pengembang

**Proyek:** ATK STORE — Sistem Pengelolaan Barang  
**Mata Kuliah:** Praktikum Pemrograman Basis Data (PBD)  
**Program Studi:** D4 Teknik Informatika  
**Institusi:** Universitas Airlangga

---

© 2026 ATK STORE. Dikembangkan untuk keperluan pembelajaran dan tugas praktikum.
