<?php
/**
 * Handler POST Absensi Masuk Karyawan - Premium
 * Menyimpan jam masuk berdasarkan waktu server dan mengirim notifikasi WhatsApp Fonnte
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/attendance.php';
require_once __DIR__ . '/../api/fonnte.php';

require_non_demo_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Metode permintaan tidak valid.', 'Akses Ditolak');
    header('Location: ' . BASE_URL . 'absensi/index.php');
    exit;
}

if (!validate_csrf()) {
    set_flash('danger', 'Token keamanan CSRF tidak valid atau telah kedaluwarsa.', 'Operasi Dibatalkan');
    header('Location: ' . BASE_URL . 'absensi/index.php');
    exit;
}

$user = current_user();
$karyawanId = (int)$user['id'];
$today = date('Y-m-d');
$nowTime = date('H:i:s');
$lateMinutes = attendance_minutes_late($nowTime);
$checkInStatus = $lateMinutes > 0 ? 'TERLAMBAT' : 'TEPAT_WAKTU';
$db = getDBConnection();

try {
    // 1. Periksa apakah sudah ada catatan absensi hari ini (Mencegah absensi ganda)
    $stmtCheck = $db->prepare("
        SELECT id, jam_masuk, status
        FROM absensi
        WHERE id_karyawan = :id_karyawan AND tanggal = :tanggal
        LIMIT 1
    ");
    $stmtCheck->execute([
        ':id_karyawan' => $karyawanId,
        ':tanggal'     => $today
    ]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        set_flash('warning', 'Anda sudah melakukan absensi masuk pada hari ini. Tidak dapat melakukan absen masuk dua kali.', 'Absen Ditolak');
        header('Location: ' . BASE_URL . 'absensi/index.php');
        exit;
    }

    // 2. Simpan Catatan Absensi Masuk
    $statusMasuk = 'SUDAH ABSEN MASUK';
    $stmtInsert = $db->prepare("
        INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, jam_pulang, status, status_masuk, menit_terlambat, created_at)
        VALUES (:id_karyawan, :tanggal, :jam_masuk, NULL, :status, :status_masuk, :menit_terlambat, NOW())
    ");
    $stmtInsert->execute([
        ':id_karyawan' => $karyawanId,
        ':tanggal'     => $today,
        ':jam_masuk'   => $nowTime,
        ':status'      => $statusMasuk,
        ':status_masuk' => $checkInStatus,
        ':menit_terlambat' => $lateMinutes
    ]);
    $attendanceId = (int)$db->lastInsertId();

    // Catat log aktivitas audit
    catat_aktivitas($karyawanId, $user['nama'], $user['role'], 'ABSEN_MASUK', "Presensi masuk pada pukul {$nowTime} WIB");

    // Read the persisted timestamp and employee profile used by both notifications.
    $stmtSaved = $db->prepare("
        SELECT a.tanggal, a.jam_masuk, k.nama, k.nik, k.jabatan, k.no_hp
        FROM absensi a
        JOIN karyawan k ON k.id = a.id_karyawan
        WHERE a.id = :id
        LIMIT 1
    ");
    $stmtSaved->execute([':id' => $attendanceId]);
    $savedAttendance = $stmtSaved->fetch();
    if (!$savedAttendance) {
        throw new RuntimeException('Catatan presensi masuk tersimpan tetapi tidak dapat dibaca kembali.');
    }
    $waData = [
        'nama' => $savedAttendance['nama'],
        'nik' => $savedAttendance['nik'],
        'jabatan' => $savedAttendance['jabatan'],
        'tanggal' => format_tanggal_indo($savedAttendance['tanggal']),
        'jam_masuk' => format_jam($savedAttendance['jam_masuk']),
        'status_masuk' => $checkInStatus,
        'menit_terlambat' => $lateMinutes
    ];
    $jamDisplay = format_jam($savedAttendance['jam_masuk']);
    $waResult = notifikasi_absen_masuk($waData, $savedAttendance['no_hp']);

    $waDetailInfo = $waResult['success']
        ? " Notifikasi WhatsApp berhasil dikirim ke nomor Anda."
        : " (Status WhatsApp: " . e($waResult['message']) . ")";

    $lateInfo = $lateMinutes > 0 ? " Terlambat {$lateMinutes} menit dari jadwal " . ATTENDANCE_START_TIME . "." : " Status tepat waktu (jadwal " . ATTENDANCE_START_TIME . ").";
    set_flash('success', "Absensi masuk berhasil dicatat pada pukul {$jamDisplay}.{$lateInfo}{$waDetailInfo}", 'Presensi Masuk Berhasil');

} catch (Exception $e) {
    error_log("Error saat absensi masuk: " . $e->getMessage());
    set_flash('danger', 'Terjadi kesalahan sistem saat mencatat absensi masuk.', 'Gagal');
}

header('Location: ' . BASE_URL . 'absensi/index.php');
exit;
