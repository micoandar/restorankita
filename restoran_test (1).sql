-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 06 Jan 2026 pada 17.22
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `restoran_test`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500');

-- --------------------------------------------------------

--
-- Struktur dari tabel `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `harga` int(11) NOT NULL,
  `kategori` enum('makanan','minuman','paket','dessert') NOT NULL DEFAULT 'makanan',
  `deskripsi` text NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `menu`
--

INSERT INTO `menu` (`id`, `nama`, `harga`, `kategori`, `deskripsi`, `gambar`, `created_at`, `updated_at`) VALUES
(1, 'Nasi Goreng', 26000, 'makanan', 'Nasi goreng spesial dengan telur mata sapi, ayam suwir, dan kerupuk udang.', '6957e2a96ebbd_20260102.jpg', '2026-01-02 14:59:56', '2026-01-02 16:35:11'),
(2, 'Ayam Bakar', 35000, 'makanan', 'Ayam bakar bumbu madu disajikan dengan sambal terasi dan lalapan segar.', '6957e225d41fc_20260102.jpg', '2026-01-02 14:59:56', '2026-01-02 15:20:05'),
(3, 'Es Teh', 5000, 'minuman', 'Minuman segar teh asli Indonesia dengan es batu kristal.', '6957d93d67214.jpg', '2026-01-02 14:59:56', '2026-01-02 16:07:28'),
(4, 'Bakso Urat', 20000, 'makanan', 'Bakso urat mantap', '6957e4110ccae_20260102.jpg', '2026-01-02 15:28:17', NULL),
(5, 'Mie Ayam', 18000, 'makanan', 'Mie Ayam', '6957e8b22c0c2_20260102.png', '2026-01-02 15:48:02', NULL),
(6, 'Es Jeruk', 10000, 'minuman', 'Segar', '6958a2b9e2da2_20260103.jpg', '2026-01-03 05:01:45', NULL),
(7, 'Es Teler ', 15000, 'dessert', 'Segar', '6958f0d70dcdc_20260103.jpg', '2026-01-03 10:35:03', '2026-01-04 10:45:28'),
(8, 'Mango Cheesecake', 25000, 'dessert', 'Creamy cheesecake berpadu mangga segar, lumer di setiap gigitan.', '6958fd5e2fe06_20260103.jpg', '2026-01-03 11:28:30', '2026-01-04 11:17:27'),
(9, 'Tiramisu', 40000, 'dessert', 'Lembut, creamy, dengan aroma kopi yang menggoda.”', '695a53b67a3a0_20260104.jpg', '2026-01-04 11:49:10', '2026-01-06 16:22:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `session_order_id` varchar(100) DEFAULT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `menu` varchar(100) NOT NULL,
  `harga` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `catatan` text DEFAULT NULL,
  `status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `metode_pembayaran` varchar(20) DEFAULT 'cash',
  `status_pembayaran` varchar(20) DEFAULT 'pending',
  `session_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id`, `session_order_id`, `nama_pelanggan`, `menu`, `harga`, `jumlah`, `total`, `catatan`, `status`, `created_at`, `metode_pembayaran`, `status_pembayaran`, `session_id`) VALUES
(1, 'ORD-20260102-1', 'Budi', 'Nasi Goreng', 0, 2, 50000, NULL, 'completed', '2026-01-02 14:12:46', 'cash', 'paid', '20260102211246_6e65a1899e9f8ed798345f8fd54da7bd'),
(2, 'ORD-20260102-2', 'Natpos', 'Es Teh', 0, 3, 15000, NULL, 'completed', '2026-01-02 14:18:07', 'cash', 'paid', '20260102211807_2759a6d3533414f3ab69661adaa77c81'),
(3, 'ORD-20260102-3', 'Natpos', 'Nasi Goreng', 0, 1, 25000, NULL, 'completed', '2026-01-02 16:11:51', 'cash', 'paid', '20260102231151_389d0b6b192ace54389b60091ed24430'),
(4, 'ORD-20260102-4', 'Test', 'Es Teh', 0, 1, 5000, NULL, 'completed', '2026-01-02 16:13:06', 'cash', 'paid', '20260102231306_92e9cff77847a137cccb6a3dcc52c896'),
(5, 'ORD-20260102-5', 'Test', 'Mie Ayam', 0, 2, 36000, NULL, 'completed', '2026-01-02 16:43:01', 'cash', 'paid', '20260102234301_a543c59365fff92a848b028bd9492cb3'),
(6, 'ORD-20260103-6', 'learning_', 'Mie Ayam', 0, 1, 18000, NULL, 'completed', '2026-01-02 17:18:45', 'cash', 'paid', '20260103001845_c901d1a30a04311b80108b2c759d23d8'),
(7, 'ORD-20260103-7', 'learning_', 'Bakso Urat', 0, 3, 60000, NULL, 'completed', '2026-01-02 17:26:43', 'cash', 'paid', '20260103002643_401672b482f5a6c23aa5e254dcaeaceb'),
(8, 'ORD-20260103-8', 'win', 'Mie Ayam', 18000, 1, 18000, 'tidak pedas', 'completed', '2026-01-02 18:28:09', 'cash', 'paid', '20260103012809_7376c5e9676bee759da1b09d5467eefe'),
(9, 'ORD-20260103-9', 'win', 'Bakso Urat', 20000, 1, 20000, 'tidak pedas', 'completed', '2026-01-02 18:28:09', 'cash', 'paid', '20260103012809_73a3527a685ebf5aaa10eee2da505fb2'),
(10, NULL, 'Los', 'Mie Ayam', 18000, 3, 54000, 'Pedas', 'completed', '2026-01-02 19:01:10', 'cash', 'paid', '20260103020110_6bf39115e595330ba9e603d7a0c5207a'),
(11, NULL, 'Los', 'Bakso Urat', 20000, 1, 20000, 'Pedas', 'completed', '2026-01-02 19:01:10', 'cash', 'paid', '20260103020110_604981fb2da3db9e5133f618b6057644'),
(12, NULL, 'Los', 'Nasi Goreng', 26000, 1, 26000, 'Pedas', 'completed', '2026-01-02 19:01:10', 'cash', 'paid', '20260103020110_fe3172f6e92c9de17f7d74c7dbba5e8c'),
(13, NULL, 'Natan', 'Mie Ayam', 18000, 3, 54000, 'pedas', 'completed', '2026-01-02 19:36:55', 'cash', 'paid', '20260103023655_ab0dd09a00d7133a88f26999cca36f98'),
(14, NULL, 'Natan', 'Bakso Urat', 20000, 1, 20000, 'pedas', 'completed', '2026-01-02 19:36:55', 'cash', 'paid', '20260103023655_48d2ff4f2bbb2b2a8c21ff2f10bebdb8'),
(15, NULL, 'Natan', 'Nasi Goreng', 26000, 1, 26000, 'pedas', 'completed', '2026-01-02 19:36:55', 'cash', 'paid', '20260103023655_a7916ff96e3fc2fc51c3b2c6bb647ee1'),
(16, NULL, 'Natan', 'Es Teh', 5000, 5, 25000, 'pedas', 'completed', '2026-01-02 19:36:55', 'cash', 'paid', '20260103023655_5cbb8b7203b77bf1be5d9f2953fdf2c6'),
(17, NULL, 'Natan', 'Mie Ayam', 18000, 3, 54000, 'pedas', 'completed', '2026-01-02 19:37:19', 'cash', 'paid', '20260103023719_8fbc8cba0bbbc8e4af33b5a46f647f0e'),
(18, NULL, 'Natan', 'Bakso Urat', 20000, 1, 20000, 'pedas', 'completed', '2026-01-02 19:37:19', 'cash', 'paid', '20260103023719_b4630dd94dfeb95293330550eccf8935'),
(19, NULL, 'Natan', 'Nasi Goreng', 26000, 1, 26000, 'pedas', 'completed', '2026-01-02 19:37:19', 'cash', 'paid', '20260103023719_6c9ef06f1ed87e5cfa3fbcdec745cd40'),
(20, NULL, 'Natan', 'Es Teh', 5000, 5, 25000, 'pedas', 'completed', '2026-01-02 19:37:19', 'cash', 'paid', '20260103023719_c96769cbbc6b08c501bae7a439e5699e'),
(21, NULL, 'Natans', 'Ayam Bakar', 35000, 1, 35000, 'pedas', 'completed', '2026-01-02 19:51:13', 'cash', 'paid', '20260103025113_273742f590b389d456e4685751d3f7e2'),
(22, NULL, 'Natans', 'Ayam Bakar', 35000, 1, 35000, 'pedas', 'completed', '2026-01-02 19:59:07', 'cash', 'paid', '20260103025907_23df6e962c29afe2d7d34b8292bb3fd9'),
(23, NULL, 'Natan', 'Mie Ayam', 18000, 1, 18000, 'pedas', 'completed', '2026-01-03 02:46:39', 'cash', 'paid', '20260103094639_2f221e2ade8dbf1e998e9c4b413ff93b'),
(24, NULL, 'natanp', 'Es Teh', 5000, 1, 5000, 'tidak pakai es', 'completed', '2026-01-03 03:16:25', 'qris', 'paid', '20260103101625_0da0e75f27d85b4217f37633a13bb4c2'),
(25, NULL, 'Yanto', 'Es Jeruk', 10000, 1, 10000, 'aokwoawo', 'completed', '2026-01-03 05:03:09', 'cash', 'paid', '20260103120309_e83e277a9b1624afa8fd4e7a2eae05d8'),
(26, NULL, 'Yanto', 'Mie Ayam', 18000, 1, 18000, 'aokwoawo', 'completed', '2026-01-03 05:03:09', 'cash', 'paid', '20260103120309_03ea8f31e993bc3640d18e83e27f0a84'),
(27, NULL, 'Messi', 'Bakso Urat', 20000, 1, 20000, 'pedas', 'completed', '2026-01-03 05:05:11', 'qris', 'paid', '20260103120511_5fd75624f026ff3d3f3f412d9c0180a0'),
(28, NULL, 'Lionel Messi', 'Es Jeruk', 10000, 1, 10000, '', 'completed', '2026-01-03 05:23:17', 'qris', 'paid', '20260103122317_6eff64ebef654eb2b996d71f27c91375'),
(29, NULL, 'Micoandar', 'Mie Ayam', 18000, 1, 18000, '', 'completed', '2026-01-03 05:29:05', 'cash', 'paid', '20260103122905_c41f16a03aa7fdc3ea9955b9ebd965b8'),
(30, NULL, 'Micoandar', 'Es Jeruk', 10000, 1, 10000, 'test', 'cancelled', '2026-01-03 10:29:28', 'cash', 'pending', '20260103172928_a52cfc56f749a06f1f106a3fd982804d'),
(31, NULL, 'Goo', 'Tiramisu', 35000, 1, 35000, '-', 'completed', '2026-01-04 12:42:45', 'cash', 'paid', '20260104194245_7da01638b7b85180459594e47012f2aa'),
(32, NULL, 'Goo', 'Mango Cheesecake', 25000, 1, 25000, '-', 'completed', '2026-01-04 13:40:31', 'cash', 'paid', '20260104204031_ea0f631e29fbf1eeb20d5db2c7a7dcbd'),
(33, NULL, 'Goo', 'Nasi Goreng', 26000, 1, 26000, 'Pedas', 'completed', '2026-01-04 14:00:56', 'cash', 'paid', '20260104210056_649731385861e060b5cb4ee4d007180f');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
