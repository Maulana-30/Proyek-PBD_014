
<?php
$halamanAktif = $halamanAktif ?? '';
?>

<aside class="sidebar">

    <div class="brand">ATK STORE</div>

    <nav class="sidebar-menu">

        <a href="dashboard.php"
           class="menu <?= $halamanAktif === 'dashboard' ? 'active' : '' ?>">
            Dashboard
        </a>

        <a href="data_barang.php"
           class="menu <?= $halamanAktif === 'barang' ? 'active' : '' ?>">
            Data Barang
        </a>

        <a href="data_satuan.php"
           class="menu <?= $halamanAktif === 'satuan' ? 'active' : '' ?>">
            Data Satuan
        </a>

        <a href="data_vendor.php"
           class="menu <?= $halamanAktif === 'vendor' ? 'active' : '' ?>">
            Data Vendor
        </a>

        <a href="data_user.php"
           class="menu <?= $halamanAktif === 'user' ? 'active' : '' ?>">
            Data User
        </a>

        <a href="data_role.php"
           class="menu <?= $halamanAktif === 'role' ? 'active' : '' ?>">
            Data Role
        </a>

        <a href="data_margin_penjualan.php"
           class="menu <?= $halamanAktif === 'margin_penjualan' ? 'active' : '' ?>">
            Margin Penjualan
        </a>

        <a href="data_pengadaan.php"
           class="menu <?= $halamanAktif === 'pengadaan' ? 'active' : '' ?>">
            Pengadaan
        </a>

        <a href="data_penerimaan.php"
           class="menu <?= $halamanAktif === 'penerimaan' ? 'active' : '' ?>">
            Penerimaan Barang
        </a>

        <a href="data_retur.php"
           class="menu <?= $halamanAktif === 'retur' ? 'active' : '' ?>">
            Retur Barang
        </a>

        <a href="data_kartu_stok.php"
           class="menu <?= $halamanAktif === 'kartu_stok' ? 'active' : '' ?>">
            Kartu Stok
        </a>

        <a href="data_penjualan.php"
           class="menu <?= $halamanAktif === 'penjualan' ? 'active' : '' ?>">
            Penjualan
        </a>

    </nav>

    <div class="sidebar-bottom">
        <a href="logout.php" class="logout-sidebar">
            Logout
        </a>
    </div>

</aside>
