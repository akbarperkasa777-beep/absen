<?php
/**
 * Idempotently create the fixed demo users and isolated sample attendance data.
 * Run from the project root with: php scripts/seed_demo_accounts.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not Found');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/attendance.php';

try {
    $db = getDBConnection();
    $demoIds = [];

    foreach (DEMO_ACCOUNTS as $definition) {
        $byNik = $db->prepare('SELECT id, email, role FROM karyawan WHERE nik = :nik LIMIT 1');
        $byNik->execute([':nik' => $definition['nik']]);
        $existing = $byNik->fetch();

        if ($existing) {
            if ($existing['email'] !== $definition['email'] || $existing['role'] !== $definition['role']) {
                throw new RuntimeException('Identitas demo bertabrakan dengan akun yang sudah ada. Seeder tidak mengubah data tersebut.');
            }
            $demoIds[$definition['role']] = (int)$existing['id'];
            echo $definition['nama'] . ": sudah tersedia; tidak ada perubahan akun.\n";
            continue;
        }

        $byEmail = $db->prepare('SELECT id FROM karyawan WHERE email = :email LIMIT 1');
        $byEmail->execute([':email' => $definition['email']]);
        if ($byEmail->fetch()) {
            throw new RuntimeException('Email demo sudah digunakan oleh akun lain. Seeder tidak mengubah data tersebut.');
        }

        $randomHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $insert = $db->prepare("
            INSERT INTO karyawan (nik, nama, jabatan, no_hp, email, password, role, status_akun, created_at)
            VALUES (:nik, :nama, :jabatan, '', :email, :password, :role, 'aktif', NOW())
        ");
        $insert->execute([
            ':nik' => $definition['nik'],
            ':nama' => $definition['nama'],
            ':jabatan' => $definition['jabatan'],
            ':email' => $definition['email'],
            ':password' => $randomHash,
            ':role' => $definition['role']
        ]);
        $demoIds[$definition['role']] = (int)$db->lastInsertId();
        echo $definition['nama'] . ": akun demo berhasil dibuat.\n";
    }

    $employeeId = $demoIds['karyawan'] ?? null;
    if ($employeeId !== null) {
        $demoLateMinutes = attendance_minutes_late('08:00:00');
        $insertAttendance = $db->prepare("
            INSERT IGNORE INTO absensi
                (id_karyawan, tanggal, jam_masuk, jam_pulang, status, status_masuk, menit_terlambat, status_pulang, created_at)
            VALUES (:id_karyawan, :tanggal, '08:00:00', '17:00:00', 'SELESAI', :status_masuk, :menit_terlambat, 'NORMAL', :created_at)
        ");
        $created = 0;
        for ($offset = 1; $created < 5; $offset++) {
            $date = new DateTimeImmutable("-{$offset} days");
            if ((int)$date->format('N') > 5) {
                continue;
            }
            $dateValue = $date->format('Y-m-d');
            $insertAttendance->execute([
                ':id_karyawan' => $employeeId,
                ':tanggal' => $dateValue,
                ':status_masuk' => $demoLateMinutes > 0 ? 'TERLAMBAT' : 'TEPAT_WAKTU',
                ':menit_terlambat' => $demoLateMinutes,
                ':created_at' => $dateValue . ' 17:00:00'
            ]);
            $created++;
        }
        echo "Data contoh presensi hanya untuk Karyawan Demo telah disiapkan (baris duplikat tidak diubah).\n";
    }
} catch (PDOException | RuntimeException $e) {
    fwrite(STDERR, 'Seeder gagal: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
