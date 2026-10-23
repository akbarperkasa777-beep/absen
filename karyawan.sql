-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 23, 2026 at 02:16 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_karyawan`
--

-- --------------------------------------------------------

--
-- Table structure for table `karyawan`
--

CREATE TABLE `karyawan` (
  `id` int(11) NOT NULL,
  `nik` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jabatan` varchar(100) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','karyawan') NOT NULL DEFAULT 'karyawan',
  `status_akun` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `foto` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `karyawan`
--

INSERT INTO `karyawan` (`id`, `nik`, `nama`, `jabatan`, `no_hp`, `email`, `password`, `role`, `status_akun`, `foto`, `created_at`, `updated_at`) VALUES
(1, 'ADM001', 'Administrator Utama', 'IT Administrator', '081234567890', 'admin@gmail.com', '$2y$10$FtXaS/L1J8lH0oHVRiV/ceLQNpm2kRIx8wNLmQ2ZdImbXgwugQuFO', 'admin', 'aktif', NULL, '2026-10-22 10:29:17', '2026-10-22 10:42:30'),
(2, 'KRY001', 'Budi Pratama', 'Senior Software Engineer', '081298765432', 'budi@karyawan.local', '$2y$10$H4mStTqXbKj8Axw1sophSuH63BzA9FTIKiYjZ2g9yJD4tV02RbcQ6', 'karyawan', 'aktif', NULL, '2026-10-22 10:29:17', '2026-10-22 10:29:17'),
(3, 'KRY002', 'Siti Rahma', 'Staff Keuangan', '081255566778', 'siti@karyawan.local', '$2y$10$H4mStTqXbKj8Axw1sophSuH63BzA9FTIKiYjZ2g9yJD4tV02RbcQ6', 'karyawan', 'aktif', NULL, '2026-10-22 10:29:17', '2026-10-22 10:29:17'),
(6, 'KRY-777', 'Muhammad Akbar Perkasa', 'CEOooo', '6285649565920', 'akbarperkasa777@gmail.com', '$2y$10$pUXLetrgk4Q.7BcKxPC6SODswdfUZvD3ybErGxeJ7s3Z2T8hHEyDq', 'karyawan', 'aktif', NULL, '2026-10-22 12:36:06', '2026-10-22 12:49:58'),
(7, 'KRY-099', 'akbar', 'CEO', '62881027499131', 'mikail@gmail.com', '$2y$10$WC.J0AsMxr.4NFADcUv1Du/cfArJAOzt4xMWgno5mpI7q7eRAxh5.', 'karyawan', 'aktif', NULL, '2026-10-22 13:03:49', '2026-10-22 13:03:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nik` (`nik`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_karyawan_email` (`email`),
  ADD KEY `idx_karyawan_nik` (`nik`),
  ADD KEY `idx_karyawan_role` (`role`),
  ADD KEY `idx_karyawan_status` (`status_akun`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
