<?php
/**
 * Handler POST Absensi Pulang Karyawan - Premium
 * Memperbarui catatan absensi dengan jam pulang dan status 'SELESAI'
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
$now = new DateTimeImmutable('now');
$today = $now->format('Y-m-d');
$nowTime = $now->format('H:i:s');
$db = getDBConnection();

try {
    $db->beginTransaction();
    $stmtCheck = $db->prepare("
        SELECT id, tanggal, jam_masuk, jam_pulang, status
        FROM absensi
        WHERE id_karyawan = :id_karyawan AND tanggal = :tanggal
        LIMIT 1
        FOR UPDATE
    ");
    $stmtCheck->execute([
        ':id_karyawan' => $karyawanId,
        ':tanggal'     => $today
    ]);
    $existing = $stmtCheck->fetch();

    if (!$existing || empty($existing['jam_masuk'])) {
        $db->rollBack();
        set_flash('warning', 'Anda belum melakukan absensi masuk hari ini. Silakan lakukan absensi masuk terlebih dahulu.', 'Absen Pulang Ditolak');
        header('Location: ' . BASE_URL . 'absensi/index.php');
        exit;
    }

    if (!empty($existing['jam_pulang'])) {
        $db->rollBack();
        set_flash('warning', 'Anda sudah melakukan absensi pulang hari ini pada pukul ' . date('H:i', strtotime($existing['jam_pulang'])) . ' WIB.', 'Sudah Absen Pulang');
        header('Location: ' . BASE_URL . 'absensi/index.php');
        exit;
    }

    $approvedRequest = null;
    $statusPulang = 'NORMAL';
    if (attendance_checkout_is_early($nowTime)) {
        $stmtRequest = $db->prepare("
            SELECT id, jam_pulang_diajukan, kategori_alasan
            FROM izin_pulang_awal
            WHERE id_karyawan = :id_karyawan AND tanggal_izin = :tanggal AND status = 'approved'
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmtRequest->execute([':id_karyawan' => $karyawanId, ':tanggal' => $today]);
        $approvedRequest = $stmtRequest->fetch();

        if (!$approvedRequest) {
            $db->rollBack();
            set_flash('warning', 'Absen pulang normal baru dapat dilakukan mulai pukul ' . EARLIEST_CHECKOUT_TIME . ' WIB. Untuk pulang lebih awal, ajukan izin dan tunggu persetujuan administrator.', 'Belum Waktunya Absen Pulang');
            header('Location: ' . BASE_URL . 'absensi/index.php');
            exit;
        }
        if (!attendance_time_reached($nowTime, substr($approvedRequest['jam_pulang_diajukan'], 0, 5))) {
            $db->rollBack();
            set_flash('warning', 'Waktu pulang yang disetujui belum tiba. Anda dapat absen mulai pukul ' . date('H:i', strtotime($approvedRequest['jam_pulang_diajukan'])) . ' WIB.', 'Belum Waktunya Absen Pulang');
            header('Location: ' . BASE_URL . 'absensi/index.php');
            exit;
        }
        $statusPulang = 'PULANG_LEBIH_AWAL_IZIN';
    } elseif (!attendance_time_reached($nowTime, EARLIEST_CHECKOUT_TIME)) {
        $db->rollBack();
        set_flash('warning', 'Absen pulang normal baru dapat dilakukan mulai pukul ' . EARLIEST_CHECKOUT_TIME . ' WIB.', 'Belum Waktunya Absen Pulang');
        header('Location: ' . BASE_URL . 'absensi/index.php');
        exit;
    }

    $stmtUpdate = $db->prepare("
        UPDATE absensi
        SET jam_pulang = :jam_pulang, status = 'SELESAI',
            status_pulang = :status_pulang, id_izin_pulang = :id_izin_pulang
        WHERE id = :id AND jam_pulang IS NULL
    ");
    $stmtUpdate->execute([
        ':jam_pulang' => $nowTime,
        ':status_pulang' => $statusPulang,
        ':id_izin_pulang' => $approvedRequest['id'] ?? null,
        ':id'         => $existing['id']
    ]);
    if ($stmtUpdate->rowCount() !== 1) {
        throw new RuntimeException('Catatan presensi pulang telah berubah oleh permintaan lain.');
    }
    $db->commit();

    // Catat log aktivitas audit
    catat_aktivitas($karyawanId, $user['nama'], $user['role'], 'ABSEN_PULANG', "Presensi pulang pada pukul {$nowTime} WIB");

    $stmtSaved = $db->prepare("
        SELECT a.tanggal, a.jam_masuk, a.jam_pulang, k.nama, k.nik, k.jabatan, k.no_hp
        FROM absensi a
        JOIN karyawan k ON k.id = a.id_karyawan
        WHERE a.id = :id
        LIMIT 1
    ");
    $stmtSaved->execute([':id' => (int)$existing['id']]);
    $savedAttendance = $stmtSaved->fetch();
    if (!$savedAttendance) {
        throw new RuntimeException('Catatan presensi pulang tersimpan tetapi tidak dapat dibaca kembali.');
    }
    $waData = [
        'nama' => $savedAttendance['nama'],
        'nik' => $savedAttendance['nik'],
        'jabatan' => $savedAttendance['jabatan'],
        'tanggal' => format_tanggal_indo($savedAttendance['tanggal']),
        'jam_masuk' => format_jam($savedAttendance['jam_masuk']),
        'jam_pulang' => format_jam($savedAttendance['jam_pulang'])
    ];
    $jamMasukDisplay = $waData['jam_masuk'];
    $jamPulangDisplay = $waData['jam_pulang'];
    $waData['status_pulang'] = $statusPulang;
    $waData['kategori_alasan'] = $approvedRequest['kategori_alasan'] ?? null;
    $waResult = notifikasi_absen_pulang($waData, $savedAttendance['no_hp']);

    $waDetailInfo = $waResult['success']
        ? " Notifikasi WhatsApp berhasil dikirim ke nomor Anda."
        : " (Status WhatsApp: " . e($waResult['message']) . ")";

    $checkoutDescription = $statusPulang === 'PULANG_LEBIH_AWAL_IZIN' ? ' Pulang lebih awal dengan izin disetujui.' : '';
    set_flash('success', "Absensi pulang berhasil dicatat pada pukul {$jamPulangDisplay}.{$checkoutDescription} Selamat beristirahat!{$waDetailInfo}", 'Presensi Pulang Berhasil');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error saat absensi pulang: " . $e->getMessage());
    set_flash('danger', 'Terjadi kesalahan sistem saat mencatat absensi pulang.', 'Gagal');
}

header('Location: ' . BASE_URL . 'absensi/index.php');
exit;
