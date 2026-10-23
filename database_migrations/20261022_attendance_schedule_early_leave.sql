-- Jalankan sekali pada database aplikasi yang sudah ada.
-- Migrasi ini menambahkan struktur tanpa menghapus atau mengubah riwayat lama.
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

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `migration_id` VARCHAR(100) PRIMARY KEY,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @schema_name = DATABASE();

SET @migration_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'absensi' AND COLUMN_NAME = 'status_masuk') = 0,
  'ALTER TABLE absensi ADD COLUMN status_masuk VARCHAR(20) NOT NULL DEFAULT ''TEPAT_WAKTU'' AFTER status',
  'SELECT 1'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'absensi' AND COLUMN_NAME = 'menit_terlambat') = 0,
  'ALTER TABLE absensi ADD COLUMN menit_terlambat INT UNSIGNED NOT NULL DEFAULT 0 AFTER status_masuk',
  'SELECT 1'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
  (SELECT COUNT(*) FROM schema_migrations WHERE migration_id = '20261022_attendance_schedule_early_leave') = 0,
  'UPDATE absensi SET status_masuk = IF(jam_masuk > ''07:00:00'', ''TERLAMBAT'', ''TEPAT_WAKTU''), menit_terlambat = IF(jam_masuk > ''07:00:00'', CEIL(TIME_TO_SEC(TIMEDIFF(jam_masuk, ''07:00:00'')) / 60), 0) WHERE jam_masuk IS NOT NULL AND status_masuk = ''TEPAT_WAKTU'' AND menit_terlambat = 0',
  'SELECT 1'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
INSERT IGNORE INTO schema_migrations (migration_id) VALUES ('20261022_attendance_schedule_early_leave');

SET @migration_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'absensi' AND COLUMN_NAME = 'status_pulang') = 0,
  'ALTER TABLE absensi ADD COLUMN status_pulang VARCHAR(40) DEFAULT NULL AFTER menit_terlambat',
  'SELECT 1'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'absensi' AND COLUMN_NAME = 'id_izin_pulang') = 0,
  'ALTER TABLE absensi ADD COLUMN id_izin_pulang INT DEFAULT NULL AFTER status_pulang',
  'SELECT 1'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = @schema_name AND TABLE_NAME = 'absensi' AND CONSTRAINT_NAME = 'fk_absensi_izin_pulang') = 0,
  'ALTER TABLE absensi ADD CONSTRAINT fk_absensi_izin_pulang FOREIGN KEY (id_izin_pulang) REFERENCES izin_pulang_awal (id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
