-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 22, 2026 at 12:52 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.5.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sistem_pengelolaan_barang`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_detail_penerimaan` (IN `p_idpenerimaan` BIGINT)   BEGIN
    SELECT
        dp.iddetail_penerimaan,
        dp.idpenerimaan,
        b.nama AS nama_barang,
        s.nama_satuan,
        dp.jumlah_terima,
        dp.harga_satuan_terima,
        dp.sub_total_terima
    FROM detail_penerimaan AS dp
    LEFT JOIN barang AS b
        ON dp.barang_idbarang = b.idbarang
    LEFT JOIN satuan AS s
        ON b.idsatuan = s.idsatuan
    WHERE dp.idpenerimaan = p_idpenerimaan
    ORDER BY dp.iddetail_penerimaan ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_detail_pengadaan` (IN `p_idpengadaan` BIGINT)   BEGIN
    SELECT
        dp.iddetail_pengadaan,
        dp.idpengadaan,
        b.nama AS nama_barang,
        s.nama_satuan,
        dp.harga_satuan,
        dp.jumlah,
        dp.sub_total
    FROM detail_pengadaan AS dp
    LEFT JOIN barang AS b
        ON dp.idbarang = b.idbarang
    LEFT JOIN satuan AS s
        ON b.idsatuan = s.idsatuan
    WHERE dp.idpengadaan = p_idpengadaan
    ORDER BY dp.iddetail_pengadaan ASC;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_total_penerimaan` (`p_idpenerimaan` BIGINT) RETURNS INT(11) READS SQL DATA BEGIN
    DECLARE v_total INT DEFAULT 0;

    SELECT COALESCE(SUM(sub_total_terima), 0)
    INTO v_total
    FROM detail_penerimaan
    WHERE idpenerimaan = p_idpenerimaan;

    RETURN v_total;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_total_pengadaan` (`p_idpengadaan` BIGINT) RETURNS INT(11) READS SQL DATA BEGIN
    DECLARE v_subtotal INT DEFAULT 0;

    SELECT COALESCE(SUM(sub_total), 0)
    INTO v_subtotal
    FROM detail_pengadaan
    WHERE idpengadaan = p_idpengadaan;

    RETURN v_subtotal;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `barang`
--

CREATE TABLE `barang` (
  `idbarang` int(11) NOT NULL,
  `jenis` char(1) DEFAULT NULL,
  `nama` varchar(45) DEFAULT NULL,
  `idsatuan` int(11) DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL,
  `harga` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barang`
--

INSERT INTO `barang` (`idbarang`, `jenis`, `nama`, `idsatuan`, `status`, `harga`) VALUES
(1, 'A', 'Buku Tulis', 1, 1, 6500),
(2, 'A', 'Pulpen Hitam', 1, 1, 5000),
(3, 'B', 'Pensil', 2, 1, 18000),
(4, 'B', 'Buku Gambar', 1, 1, 12000),
(5, 'B', 'Spidol Permanent', 3, 1, 35000);

-- --------------------------------------------------------

--
-- Table structure for table `detail_penerimaan`
--

CREATE TABLE `detail_penerimaan` (
  `iddetail_penerimaan` bigint(20) NOT NULL,
  `idpenerimaan` bigint(20) NOT NULL,
  `barang_idbarang` int(11) NOT NULL,
  `jumlah_terima` int(11) NOT NULL,
  `harga_satuan_terima` int(11) NOT NULL,
  `sub_total_terima` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_penerimaan`
--

INSERT INTO `detail_penerimaan` (`iddetail_penerimaan`, `idpenerimaan`, `barang_idbarang`, `jumlah_terima`, `harga_satuan_terima`, `sub_total_terima`) VALUES
(9, 9, 1, 1, 6500, 6500),
(10, 10, 1, 2, 6500, 13000),
(11, 11, 4, 1, 12000, 12000),
(13, 13, 1, 3, 6500, 19500),
(14, 14, 1, 1, 6500, 6500),
(15, 15, 4, 1, 12000, 12000),
(16, 16, 1, 1, 6500, 6500);

--
-- Triggers `detail_penerimaan`
--
DELIMITER $$
CREATE TRIGGER `trg_detail_penerimaan_stok` AFTER INSERT ON `detail_penerimaan` FOR EACH ROW BEGIN
    DECLARE v_idpengadaan BIGINT;
    DECLARE v_jumlah_pesan INT DEFAULT 0;
    DECLARE v_jumlah_diterima INT DEFAULT 0;
    DECLARE v_stok_terakhir INT DEFAULT 0;

    
    SELECT idpengadaan
    INTO v_idpengadaan
    FROM penerimaan
    WHERE idpenerimaan = NEW.idpenerimaan;

    
    SELECT COALESCE(SUM(jumlah), 0)
    INTO v_jumlah_pesan
    FROM detail_pengadaan
    WHERE idpengadaan = v_idpengadaan
      AND idbarang = NEW.barang_idbarang;

    
    SELECT COALESCE(SUM(dp.jumlah_terima), 0)
    INTO v_jumlah_diterima
    FROM detail_penerimaan AS dp
    INNER JOIN penerimaan AS p
        ON p.idpenerimaan = dp.idpenerimaan
    WHERE p.idpengadaan = v_idpengadaan
      AND dp.barang_idbarang = NEW.barang_idbarang;

    
    IF v_jumlah_diterima > v_jumlah_pesan THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT =
            'Jumlah barang diterima melebihi jumlah yang dipesan';
    END IF;

    SELECT COALESCE(
        (
            SELECT stock
            FROM kartu_stok
            WHERE idbarang = NEW.barang_idbarang
            ORDER BY idkartu_stok DESC
            LIMIT 1
        ),
        0
    )
    INTO v_stok_terakhir;

    
    INSERT INTO kartu_stok (
        jenis_transaksi,
        masuk,
        keluar,
        stock,
        created_at,
        idtransaksi,
        idbarang
    )
    VALUES (
        'M',
        NEW.jumlah_terima,
        0,
        v_stok_terakhir + NEW.jumlah_terima,
        CURRENT_TIMESTAMP,
        NEW.idpenerimaan,
        NEW.barang_idbarang
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `detail_pengadaan`
--

CREATE TABLE `detail_pengadaan` (
  `iddetail_pengadaan` bigint(20) NOT NULL,
  `harga_satuan` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `sub_total` int(11) NOT NULL,
  `idbarang` int(11) NOT NULL,
  `idpengadaan` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_pengadaan`
--

INSERT INTO `detail_pengadaan` (`iddetail_pengadaan`, `harga_satuan`, `jumlah`, `sub_total`, `idbarang`, `idpengadaan`) VALUES
(21, 6500, 2, 13000, 1, 8),
(31, 6500, 1, 6500, 1, 7),
(32, 12000, 1, 12000, 4, 7),
(33, 6500, 3, 19500, 1, 10),
(34, 6500, 1, 6500, 1, 11),
(35, 12000, 1, 12000, 4, 11),
(37, 6500, 1, 6500, 1, 13);

-- --------------------------------------------------------

--
-- Table structure for table `detail_penjualan`
--

CREATE TABLE `detail_penjualan` (
  `iddetail_penjualan` bigint(20) NOT NULL,
  `harga_satuan` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `subtotal` int(11) NOT NULL,
  `penjualan_idpenjualan` int(11) NOT NULL,
  `idbarang` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_penjualan`
--

INSERT INTO `detail_penjualan` (`iddetail_penjualan`, `harga_satuan`, `jumlah`, `subtotal`, `penjualan_idpenjualan`, `idbarang`) VALUES
(1, 6825, 1, 6825, 1, 1),
(2, 6825, 1, 6825, 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `detail_retur`
--

CREATE TABLE `detail_retur` (
  `iddetail_retur` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `alasan` varchar(200) NOT NULL,
  `idretur` bigint(20) NOT NULL,
  `iddetail_penerimaan` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_retur`
--

INSERT INTO `detail_retur` (`iddetail_retur`, `jumlah`, `alasan`, `idretur`, `iddetail_penerimaan`) VALUES
(15, 1, 'barang rusak', 8, 9);

-- --------------------------------------------------------

--
-- Table structure for table `kartu_stok`
--

CREATE TABLE `kartu_stok` (
  `idkartu_stok` bigint(20) NOT NULL,
  `jenis_transaksi` char(1) NOT NULL,
  `masuk` int(11) NOT NULL,
  `keluar` int(11) NOT NULL,
  `stock` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `idtransaksi` int(11) NOT NULL,
  `idbarang` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kartu_stok`
--

INSERT INTO `kartu_stok` (`idkartu_stok`, `jenis_transaksi`, `masuk`, `keluar`, `stock`, `created_at`, `idtransaksi`, `idbarang`) VALUES
(7, 'M', 1, 0, 1, '2026-10-19 02:53:15', 0, 1),
(8, 'M', 1, 0, 1, '2026-10-19 02:53:15', 0, 2),
(9, 'M', 1, 0, 1, '2026-10-19 02:53:15', 0, 3),
(10, 'M', 1, 0, 1, '2026-10-19 02:53:15', 0, 4),
(11, 'M', 1, 0, 1, '2026-10-19 02:53:15', 0, 5),
(12, 'K', 0, 1, 0, '2026-10-19 03:02:01', 2, 1),
(13, 'K', 0, 1, 0, '2026-10-19 09:46:48', 3, 2),
(14, 'M', 1, 0, 1, '2026-10-19 10:20:43', 0, 2),
(15, 'K', 0, 1, 0, '2026-10-19 10:26:53', 4, 2),
(16, 'K', 0, 1, 0, '2026-10-19 10:26:53', 4, 3),
(17, 'M', 1, 0, 1, '2026-10-19 10:32:28', 0, 2),
(18, 'M', 1, 0, 1, '2026-10-19 10:32:28', 0, 3),
(19, 'K', 0, 1, 0, '2026-10-19 10:53:55', 5, 3),
(20, 'M', 1, 0, 1, '2026-10-19 10:54:24', 0, 3),
(21, 'K', 0, 1, 0, '2026-10-19 12:51:21', 6, 3),
(22, 'M', 1, 0, 1, '2026-10-19 12:51:28', 0, 3),
(23, 'K', 0, 1, 0, '2026-10-19 12:52:25', 7, 3),
(24, 'M', 1, 0, 1, '2026-10-19 12:52:33', 0, 3),
(25, 'M', 1, 0, 1, '2026-10-19 12:53:31', 9, 1),
(26, 'K', 0, 1, 0, '2026-10-19 12:53:56', 8, 1),
(27, 'M', 2, 0, 2, '2026-10-19 12:57:11', 10, 1),
(28, 'K', 0, 2, 0, '2026-10-19 12:57:25', 8, 1),
(29, 'M', 2, 0, 2, '2026-10-19 12:57:29', 0, 1),
(30, 'M', 1, 0, 2, '2026-10-20 11:25:04', 11, 4),
(32, 'M', 3, 0, 5, '2026-10-21 02:36:37', 13, 1),
(33, 'M', 1, 0, 6, '2026-10-21 03:24:19', 14, 1),
(34, 'M', 1, 0, 3, '2026-10-21 03:26:29', 15, 4),
(35, 'M', 1, 0, 7, '2026-10-21 06:51:58', 16, 1);

-- --------------------------------------------------------

--
-- Table structure for table `margin_penjualan`
--

CREATE TABLE `margin_penjualan` (
  `idmargin_penjualan` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `persen` double DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL,
  `iduser` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `margin_penjualan`
--

INSERT INTO `margin_penjualan` (`idmargin_penjualan`, `created_at`, `persen`, `status`, `iduser`, `updated_at`) VALUES
(1, '2026-10-02 05:45:15', 5, 1, 1, '2026-10-19 03:46:08'),
(2, '2026-10-02 05:45:15', 7.5, 1, 2, '2026-10-02 05:45:15'),
(3, '2026-10-02 05:45:15', 10, 1, 3, '2026-10-02 05:45:15'),
(4, '2026-10-02 05:45:15', 12.5, 1, 4, '2026-10-02 05:45:15'),
(5, '2026-10-02 05:45:15', 15, 1, 5, '2026-10-02 05:45:15'),
(6, '2026-10-21 05:44:54', 2, 0, 1, '2026-10-21 05:45:18');

-- --------------------------------------------------------

--
-- Table structure for table `penerimaan`
--

CREATE TABLE `penerimaan` (
  `idpenerimaan` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` char(1) NOT NULL,
  `idpengadaan` bigint(20) NOT NULL,
  `iduser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penerimaan`
--

INSERT INTO `penerimaan` (`idpenerimaan`, `created_at`, `status`, `idpengadaan`, `iduser`) VALUES
(9, '2026-10-19 12:53:31', '1', 7, 1),
(10, '2026-10-19 12:57:11', '1', 8, 1),
(11, '2026-10-20 11:25:04', '1', 7, 1),
(13, '2026-10-21 02:36:37', '1', 10, 1),
(14, '2026-10-21 03:24:19', '1', 11, 1),
(15, '2026-10-21 03:26:29', '1', 11, 1),
(16, '2026-10-21 06:51:58', '1', 13, 1);

-- --------------------------------------------------------

--
-- Table structure for table `pengadaan`
--

CREATE TABLE `pengadaan` (
  `idpengadaan` bigint(20) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` char(1) NOT NULL,
  `vendor_idvendor` int(11) NOT NULL,
  `subtotal_nilai` int(11) NOT NULL,
  `ppn` int(11) NOT NULL,
  `total_nilai` int(11) NOT NULL,
  `user_iduser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengadaan`
--

INSERT INTO `pengadaan` (`idpengadaan`, `timestamp`, `status`, `vendor_idvendor`, `subtotal_nilai`, `ppn`, `total_nilai`, `user_iduser`) VALUES
(7, '2026-10-19 12:53:06', '2', 4, 18500, 2035, 20535, 1),
(8, '2026-10-19 12:57:01', '2', 2, 13000, 1430, 14430, 1),
(10, '2026-10-21 02:36:20', '2', 5, 19500, 2145, 21645, 1),
(11, '2026-10-21 03:20:10', '2', 2, 18500, 2035, 20535, 1),
(13, '2026-10-21 06:51:45', '2', 4, 6500, 715, 7215, 1);

-- --------------------------------------------------------

--
-- Table structure for table `penjualan`
--

CREATE TABLE `penjualan` (
  `idpenjualan` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `subtotal_nilai` int(11) NOT NULL,
  `ppn` int(11) NOT NULL,
  `total_nilai` int(11) NOT NULL,
  `iduser` int(11) NOT NULL,
  `idmargin_penjualan` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penjualan`
--

INSERT INTO `penjualan` (`idpenjualan`, `created_at`, `subtotal_nilai`, `ppn`, `total_nilai`, `iduser`, `idmargin_penjualan`, `status`, `updated_at`) VALUES
(1, '2026-10-19 00:02:34', 6825, 751, 7576, 1, 1, 1, '2026-10-19 00:02:34'),
(2, '2026-10-19 03:02:01', 6825, 751, 7576, 1, 1, 1, '2026-10-19 03:02:01');

-- --------------------------------------------------------

--
-- Table structure for table `retur`
--

CREATE TABLE `retur` (
  `idretur` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `idpenerimaan` bigint(20) NOT NULL,
  `iduser` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `retur`
--

INSERT INTO `retur` (`idretur`, `created_at`, `idpenerimaan`, `iduser`) VALUES
(8, '2026-10-19 12:53:56', 9, 1);

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `idrole` int(11) NOT NULL,
  `nama_role` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`idrole`, `nama_role`) VALUES
(1, 'Admin Toko'),
(2, 'Staff Gudang'),
(3, 'Kasir'),
(4, 'Supervisor'),
(5, 'Owner');

-- --------------------------------------------------------

--
-- Table structure for table `satuan`
--

CREATE TABLE `satuan` (
  `idsatuan` int(11) NOT NULL,
  `nama_satuan` varchar(45) DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `satuan`
--

INSERT INTO `satuan` (`idsatuan`, `nama_satuan`, `status`) VALUES
(1, 'Pcs', 1),
(2, 'Pack', 1),
(3, 'Box', 1),
(4, 'Lusin', 1),
(5, 'Botol', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `iduser` int(11) NOT NULL,
  `username` varchar(45) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `idrole` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`iduser`, `username`, `password`, `idrole`) VALUES
(1, 'admin_atk', '$2y$12$ZorsYsCX5mk2rUBf29uZBuv06Hp2C/AHoaQrAJZxm4Xyt.H5qYSoS', 1),
(2, 'staff_gudang', '$2y$12$fiWs70VhyM0wcqQwk2bpi.eCTOOvIzTmh593Nn.CDFfzmoOE5Wv/C', 2),
(3, 'kasir_atk', '$2y$12$4cF10y4qW3dHuU8RCNuTpuc5eg16UUjnefznk20rpzOOj/95zgk7.', 3),
(4, 'supervisor_atk', '$2y$12$fTxGzPcA6pZGawUtsxL8/.F3Kzg7P1Vqc8CRXTn/dkuJWQOdVvTbW', 4),
(5, 'owner_atk', '$2y$12$BXpivM3TCkXlq6LuXs8YLOY.7FzXNqGwW3i9ugEGH.A4F0Wj8ItlG', 5);

-- --------------------------------------------------------

--
-- Table structure for table `vendor`
--

CREATE TABLE `vendor` (
  `idvendor` int(11) NOT NULL,
  `nama_vendor` varchar(100) DEFAULT NULL,
  `badan_hukum` char(1) DEFAULT NULL,
  `status` char(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor`
--

INSERT INTO `vendor` (`idvendor`, `nama_vendor`, `badan_hukum`, `status`) VALUES
(1, 'PT Sinar Alat Tulis', 'Y', '1'),
(2, 'CV Edukasi Mandiri', 'Y', '1'),
(3, 'PT Cipta Karya Stationery', 'Y', '1'),
(4, 'CV Pelajar Sejahtera', 'Y', '1'),
(5, 'PT Prima Perlengkapan Sekolah', 'Y', '1');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_data_barang`
-- (See below for the actual view)
--
CREATE TABLE `v_data_barang` (
`idbarang` int(11)
,`jenis` char(1)
,`nama_barang` varchar(45)
,`nama_satuan` varchar(45)
,`status` tinyint(4)
,`harga` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_data_margin_penjualan`
-- (See below for the actual view)
--
CREATE TABLE `v_data_margin_penjualan` (
`idmargin_penjualan` int(11)
,`created_at` timestamp
,`persen` double
,`status` tinyint(4)
,`username` varchar(45)
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_data_role`
-- (See below for the actual view)
--
CREATE TABLE `v_data_role` (
`idrole` int(11)
,`nama_role` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_data_satuan`
-- (See below for the actual view)
--
CREATE TABLE `v_data_satuan` (
`idsatuan` int(11)
,`nama_satuan` varchar(45)
,`status` tinyint(4)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_data_user`
-- (See below for the actual view)
--
CREATE TABLE `v_data_user` (
`iduser` int(11)
,`username` varchar(45)
,`idrole` int(11)
,`nama_role` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_data_vendor`
-- (See below for the actual view)
--
CREATE TABLE `v_data_vendor` (
`idvendor` int(11)
,`nama_vendor` varchar(100)
,`badan_hukum` char(1)
,`status` char(1)
);

-- --------------------------------------------------------

--
-- Structure for view `v_data_barang`
--
DROP TABLE IF EXISTS `v_data_barang`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_data_barang`  AS SELECT `b`.`idbarang` AS `idbarang`, `b`.`jenis` AS `jenis`, `b`.`nama` AS `nama_barang`, `s`.`nama_satuan` AS `nama_satuan`, `b`.`status` AS `status`, `b`.`harga` AS `harga` FROM (`barang` `b` left join `satuan` `s` on(`b`.`idsatuan` = `s`.`idsatuan`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_data_margin_penjualan`
--
DROP TABLE IF EXISTS `v_data_margin_penjualan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_data_margin_penjualan`  AS SELECT `m`.`idmargin_penjualan` AS `idmargin_penjualan`, `m`.`created_at` AS `created_at`, `m`.`persen` AS `persen`, `m`.`status` AS `status`, `u`.`username` AS `username`, `m`.`updated_at` AS `updated_at` FROM (`margin_penjualan` `m` left join `user` `u` on(`m`.`iduser` = `u`.`iduser`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_data_role`
--
DROP TABLE IF EXISTS `v_data_role`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_data_role`  AS SELECT `role`.`idrole` AS `idrole`, `role`.`nama_role` AS `nama_role` FROM `role` ;

-- --------------------------------------------------------

--
-- Structure for view `v_data_satuan`
--
DROP TABLE IF EXISTS `v_data_satuan`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_data_satuan`  AS SELECT `satuan`.`idsatuan` AS `idsatuan`, `satuan`.`nama_satuan` AS `nama_satuan`, `satuan`.`status` AS `status` FROM `satuan` ;

-- --------------------------------------------------------

--
-- Structure for view `v_data_user`
--
DROP TABLE IF EXISTS `v_data_user`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_data_user`  AS SELECT `u`.`iduser` AS `iduser`, `u`.`username` AS `username`, `u`.`idrole` AS `idrole`, `r`.`nama_role` AS `nama_role` FROM (`user` `u` left join `role` `r` on(`u`.`idrole` = `r`.`idrole`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_data_vendor`
--
DROP TABLE IF EXISTS `v_data_vendor`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_data_vendor`  AS SELECT `vendor`.`idvendor` AS `idvendor`, `vendor`.`nama_vendor` AS `nama_vendor`, `vendor`.`badan_hukum` AS `badan_hukum`, `vendor`.`status` AS `status` FROM `vendor` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`idbarang`),
  ADD KEY `idsatuan` (`idsatuan`);

--
-- Indexes for table `detail_penerimaan`
--
ALTER TABLE `detail_penerimaan`
  ADD PRIMARY KEY (`iddetail_penerimaan`),
  ADD KEY `idpenerimaan` (`idpenerimaan`),
  ADD KEY `barang_idbarang` (`barang_idbarang`);

--
-- Indexes for table `detail_pengadaan`
--
ALTER TABLE `detail_pengadaan`
  ADD PRIMARY KEY (`iddetail_pengadaan`),
  ADD KEY `idbarang` (`idbarang`),
  ADD KEY `idpengadaan` (`idpengadaan`);

--
-- Indexes for table `detail_penjualan`
--
ALTER TABLE `detail_penjualan`
  ADD PRIMARY KEY (`iddetail_penjualan`),
  ADD KEY `penjualan_idpenjualan` (`penjualan_idpenjualan`),
  ADD KEY `idbarang` (`idbarang`);

--
-- Indexes for table `detail_retur`
--
ALTER TABLE `detail_retur`
  ADD PRIMARY KEY (`iddetail_retur`),
  ADD KEY `idretur` (`idretur`),
  ADD KEY `iddetail_penerimaan` (`iddetail_penerimaan`);

--
-- Indexes for table `kartu_stok`
--
ALTER TABLE `kartu_stok`
  ADD PRIMARY KEY (`idkartu_stok`),
  ADD KEY `idbarang` (`idbarang`);

--
-- Indexes for table `margin_penjualan`
--
ALTER TABLE `margin_penjualan`
  ADD PRIMARY KEY (`idmargin_penjualan`),
  ADD KEY `iduser` (`iduser`);

--
-- Indexes for table `penerimaan`
--
ALTER TABLE `penerimaan`
  ADD PRIMARY KEY (`idpenerimaan`),
  ADD KEY `idpengadaan` (`idpengadaan`),
  ADD KEY `iduser` (`iduser`);

--
-- Indexes for table `pengadaan`
--
ALTER TABLE `pengadaan`
  ADD PRIMARY KEY (`idpengadaan`),
  ADD KEY `vendor_idvendor` (`vendor_idvendor`),
  ADD KEY `user_iduser` (`user_iduser`);

--
-- Indexes for table `penjualan`
--
ALTER TABLE `penjualan`
  ADD PRIMARY KEY (`idpenjualan`),
  ADD KEY `iduser` (`iduser`),
  ADD KEY `idmargin_penjualan` (`idmargin_penjualan`);

--
-- Indexes for table `retur`
--
ALTER TABLE `retur`
  ADD PRIMARY KEY (`idretur`),
  ADD KEY `idpenerimaan` (`idpenerimaan`),
  ADD KEY `iduser` (`iduser`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`idrole`);

--
-- Indexes for table `satuan`
--
ALTER TABLE `satuan`
  ADD PRIMARY KEY (`idsatuan`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`iduser`),
  ADD KEY `idrole` (`idrole`);

--
-- Indexes for table `vendor`
--
ALTER TABLE `vendor`
  ADD PRIMARY KEY (`idvendor`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barang`
--
ALTER TABLE `barang`
  MODIFY `idbarang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `detail_penerimaan`
--
ALTER TABLE `detail_penerimaan`
  MODIFY `iddetail_penerimaan` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `detail_pengadaan`
--
ALTER TABLE `detail_pengadaan`
  MODIFY `iddetail_pengadaan` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `detail_penjualan`
--
ALTER TABLE `detail_penjualan`
  MODIFY `iddetail_penjualan` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `detail_retur`
--
ALTER TABLE `detail_retur`
  MODIFY `iddetail_retur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `kartu_stok`
--
ALTER TABLE `kartu_stok`
  MODIFY `idkartu_stok` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `margin_penjualan`
--
ALTER TABLE `margin_penjualan`
  MODIFY `idmargin_penjualan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `penerimaan`
--
ALTER TABLE `penerimaan`
  MODIFY `idpenerimaan` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `pengadaan`
--
ALTER TABLE `pengadaan`
  MODIFY `idpengadaan` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `penjualan`
--
ALTER TABLE `penjualan`
  MODIFY `idpenjualan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `retur`
--
ALTER TABLE `retur`
  MODIFY `idretur` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `idrole` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `satuan`
--
ALTER TABLE `satuan`
  MODIFY `idsatuan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `iduser` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `vendor`
--
ALTER TABLE `vendor`
  MODIFY `idvendor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barang`
--
ALTER TABLE `barang`
  ADD CONSTRAINT `barang_ibfk_1` FOREIGN KEY (`idsatuan`) REFERENCES `satuan` (`idsatuan`);

--
-- Constraints for table `detail_penerimaan`
--
ALTER TABLE `detail_penerimaan`
  ADD CONSTRAINT `detail_penerimaan_ibfk_1` FOREIGN KEY (`idpenerimaan`) REFERENCES `penerimaan` (`idpenerimaan`),
  ADD CONSTRAINT `detail_penerimaan_ibfk_2` FOREIGN KEY (`barang_idbarang`) REFERENCES `barang` (`idbarang`);

--
-- Constraints for table `detail_pengadaan`
--
ALTER TABLE `detail_pengadaan`
  ADD CONSTRAINT `detail_pengadaan_ibfk_1` FOREIGN KEY (`idbarang`) REFERENCES `barang` (`idbarang`),
  ADD CONSTRAINT `detail_pengadaan_ibfk_2` FOREIGN KEY (`idpengadaan`) REFERENCES `pengadaan` (`idpengadaan`);

--
-- Constraints for table `detail_penjualan`
--
ALTER TABLE `detail_penjualan`
  ADD CONSTRAINT `detail_penjualan_ibfk_1` FOREIGN KEY (`penjualan_idpenjualan`) REFERENCES `penjualan` (`idpenjualan`),
  ADD CONSTRAINT `detail_penjualan_ibfk_2` FOREIGN KEY (`idbarang`) REFERENCES `barang` (`idbarang`);

--
-- Constraints for table `detail_retur`
--
ALTER TABLE `detail_retur`
  ADD CONSTRAINT `detail_retur_ibfk_1` FOREIGN KEY (`idretur`) REFERENCES `retur` (`idretur`),
  ADD CONSTRAINT `detail_retur_ibfk_2` FOREIGN KEY (`iddetail_penerimaan`) REFERENCES `detail_penerimaan` (`iddetail_penerimaan`);

--
-- Constraints for table `kartu_stok`
--
ALTER TABLE `kartu_stok`
  ADD CONSTRAINT `kartu_stok_ibfk_1` FOREIGN KEY (`idbarang`) REFERENCES `barang` (`idbarang`);

--
-- Constraints for table `margin_penjualan`
--
ALTER TABLE `margin_penjualan`
  ADD CONSTRAINT `margin_penjualan_ibfk_1` FOREIGN KEY (`iduser`) REFERENCES `user` (`iduser`);

--
-- Constraints for table `penerimaan`
--
ALTER TABLE `penerimaan`
  ADD CONSTRAINT `penerimaan_ibfk_1` FOREIGN KEY (`idpengadaan`) REFERENCES `pengadaan` (`idpengadaan`),
  ADD CONSTRAINT `penerimaan_ibfk_2` FOREIGN KEY (`iduser`) REFERENCES `user` (`iduser`);

--
-- Constraints for table `pengadaan`
--
ALTER TABLE `pengadaan`
  ADD CONSTRAINT `pengadaan_ibfk_1` FOREIGN KEY (`vendor_idvendor`) REFERENCES `vendor` (`idvendor`),
  ADD CONSTRAINT `pengadaan_ibfk_2` FOREIGN KEY (`user_iduser`) REFERENCES `user` (`iduser`);

--
-- Constraints for table `penjualan`
--
ALTER TABLE `penjualan`
  ADD CONSTRAINT `penjualan_ibfk_1` FOREIGN KEY (`iduser`) REFERENCES `user` (`iduser`),
  ADD CONSTRAINT `penjualan_ibfk_2` FOREIGN KEY (`idmargin_penjualan`) REFERENCES `margin_penjualan` (`idmargin_penjualan`);

--
-- Constraints for table `retur`
--
ALTER TABLE `retur`
  ADD CONSTRAINT `retur_ibfk_1` FOREIGN KEY (`idpenerimaan`) REFERENCES `penerimaan` (`idpenerimaan`),
  ADD CONSTRAINT `retur_ibfk_2` FOREIGN KEY (`iduser`) REFERENCES `user` (`iduser`);

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`idrole`) REFERENCES `role` (`idrole`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
