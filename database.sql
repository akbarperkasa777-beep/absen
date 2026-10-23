-- ==============================================================
-- DATABASE: db_karyawan
-- Portal Presensi Karyawan - Premium Workforce Management
-- Integrasi WhatsApp Gateway Fonnte & Dotenv Environment
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `db_karyawan` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_karyawan`;

-- --------------------------------------------------------
-- 1. Tabel karyawan
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `karyawan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nik` VARCHAR(30) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `jabatan` VARCHAR(100) NOT NULL,
  `no_hp` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'karyawan') NOT NULL DEFAULT 'karyawan',
  `status_akun` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `foto` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_karyawan_email` (`email`),
  INDEX `idx_karyawan_nik` (`nik`),
  INDEX `idx_karyawan_role` (`role`),
  INDEX `idx_karyawan_status` (`status_akun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Tabel pengajuan izin pulang lebih awal
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `izin_pulang_awal` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_karyawan` INT NOT NULL,
  `tanggal_izin` DATE NOT NULL,
  `jam_pulang_diajukan` TIME NOT NULL,
  `kategori_alasan` VARCHAR(80) NOT NULL,
  `keterangan` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `alasan_penolakan` TEXT DEFAULT NULL,
  `diputuskan_oleh` INT DEFAULT NULL,
  `diputuskan_pada` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_izin_pulang_karyawan` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_izin_pulang_admin` FOREIGN KEY (`diputuskan_oleh`) REFERENCES `karyawan` (`id`) ON DELETE SET NULL,
  INDEX `idx_izin_pulang_status_tanggal` (`status`, `tanggal_izin`),
  INDEX `idx_izin_pulang_karyawan_tanggal` (`id_karyawan`, `tanggal_izin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Tabel absensi
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `absensi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_karyawan` INT NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_masuk` TIME DEFAULT NULL,
  `jam_pulang` TIME DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'BELUM ABSEN',
  `status_masuk` VARCHAR(20) NOT NULL DEFAULT 'TEPAT_WAKTU',
  `menit_terlambat` INT UNSIGNED NOT NULL DEFAULT 0,
  `status_pulang` VARCHAR(40) DEFAULT NULL,
  `id_izin_pulang` INT DEFAULT NULL,
  `keterangan` VARCHAR(255) DEFAULT NULL,
  `dikoreksi_oleh` INT DEFAULT NULL,
  `alasan_koreksi` TEXT DEFAULT NULL,
  `waktu_koreksi` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_absensi_karyawan` FOREIGN KEY (`id_karyawan`) REFERENCES `karyawan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_absensi_izin_pulang` FOREIGN KEY (`id_izin_pulang`) REFERENCES `izin_pulang_awal` (`id`) ON DELETE SET NULL,
  CONSTRAINT `uq_karyawan_tanggal` UNIQUE (`id_karyawan`, `tanggal`),
  INDEX `idx_absensi_tanggal` (`tanggal`),
  INDEX `idx_absensi_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Tabel log_notifikasi (Audit Trail Fonnte)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `log_notifikasi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `penerima` VARCHAR(30) NOT NULL,
  `tipe_notifikasi` VARCHAR(50) NOT NULL,
  `pesan` TEXT NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `response_api` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_log_penerima` (`penerima`),
  INDEX `idx_log_status` (`status`),
  INDEX `idx_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Tabel log_aktivitas (Audit Trail Aktivitas Admin & Pengguna)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `log_aktivitas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_user` INT DEFAULT NULL,
  `nama_user` VARCHAR(100) NOT NULL,
  `role` VARCHAR(30) NOT NULL,
  `aksi` VARCHAR(100) NOT NULL,
  `keterangan` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_act_user` (`id_user`),
  INDEX `idx_act_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data Awal Pengujian
-- --------------------------------------------------------
INSERT INTO `karyawan` (`nik`, `nama`, `jabatan`, `no_hp`, `email`, `password`, `role`, `status_akun`)
VALUES
('ADM001', 'Administrator Utama', 'IT Administrator', '081234567890', 'admin@karyawan.local', '$2y$10$FtXaS/L1J8lH0oHVRiV/ceLQNpm2kRIx8wNLmQ2ZdImbXgwugQuFO', 'admin', 'aktif'),
('KRY001', 'Budi Pratama', 'Senior Software Engineer', '081298765432', 'budi@karyawan.local', '$2y$10$H4mStTqXbKj8Axw1sophSuH63BzA9FTIKiYjZ2g9yJD4tV02RbcQ6', 'karyawan', 'aktif'),
('KRY002', 'Siti Rahma', 'Staff Keuangan', '081255566778', 'siti@karyawan.local', '$2y$10$H4mStTqXbKj8Axw1sophSuH63BzA9FTIKiYjZ2g9yJD4tV02RbcQ6', 'karyawan', 'aktif')
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`);
