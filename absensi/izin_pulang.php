<?php
/**
 * Handler pengajuan izin pulang lebih awal oleh karyawan.
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
if (($user['role'] ?? '') !== 'karyawan') {
    set_flash('danger', 'Fitur izin pulang awal hanya tersedia untuk akun karyawan.', 'Akses Ditolak');
    header('Location: ' . BASE_URL . 'absensi/index.php');
    exit;
}
$karyawanId = (int)$user['id'];
$today = date('Y-m-d');
$requestedDate = trim($_POST['tanggal_izin'] ?? '');
$requestedTime = trim($_POST['jam_pulang_diajukan'] ?? '');
$category = trim($_POST['kategori_alasan'] ?? '');
$details = trim($_POST['keterangan'] ?? '');
$allowedCategories = [
    'Sakit',
    'Keperluan berobat',
    'Keperluan keluarga',
    'Keperluan pendidikan',
    'Keperluan mendesak',
    'Keperluan pribadi lainnya'
];

if ($requestedDate !== $today) {
    set_flash('warning', 'Pengajuan hanya dapat dibuat untuk tanggal hari ini.', 'Tanggal Tidak Valid');
    header('Location: ' . BASE_URL . 'absensi/index.php');
    exit;
}
if (!in_array($category, $allowedCategories, true) || ($category === 'Keperluan pribadi lainnya' && $details === '')) {
    set_flash('warning', 'Pilih kategori alasan yang valid dan isi penjelasan untuk keperluan pribadi lainnya.', 'Form Belum Lengkap');
    header('Location: ' . BASE_URL . 'absensi/index.php');
    exit;
}
if (strlen($details) > 1000) {
    set_flash('warning', 'Penjelasan tambahan maksimal 1000 karakter.', 'Keterangan Terlalu Panjang');
    header('Location: ' . BASE_URL . 'absensi/index.php');
    exit;
}
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $requestedTime)) {
    set_flash('warning', 'Format jam pulang tidak valid.', 'Waktu Tidak Valid');
    header('Location: ' . BASE_URL . 'absensi/index.php');
    exit;
}

$now = date('H:i:s');
if (!attendance_checkout_is_early($requestedTime . ':00') || strtotime('1970-01-01 ' . $requestedTime . ':00') <= strtotime('1970-01-01 ' . $now)) {
    set_flash('warning', 'Waktu yang diajukan harus lebih awal dari pukul ' . EARLIEST_CHECKOUT_TIME . ' dan belum terlewati.', 'Waktu Tidak Valid');
    header('Location: ' . BASE_URL . 'absensi/index.php');
    exit;
}

$db = getDBConnection();
try {
    $db->beginTransaction();
    $stmtEmployeeLock = $db->prepare('SELECT id FROM karyawan WHERE id = :id FOR UPDATE');
    $stmtEmployeeLock->execute([':id' => $karyawanId]);
    if (!$stmtEmployeeLock->fetchColumn()) {
        throw new RuntimeException('Akun karyawan tidak ditemukan.');
    }

    $stmtAttendance = $db->prepare("
        SELECT id, jam_masuk, jam_pulang
        FROM absensi
        WHERE id_karyawan = :id AND tanggal = :tanggal
        LIMIT 1
        FOR UPDATE
    ");
    $stmtAttendance->execute([':id' => $karyawanId, ':tanggal' => $today]);
    $attendance = $stmtAttendance->fetch();
    if (!$attendance || empty($attendance['jam_masuk'])) {
        throw new DomainException('Anda harus melakukan absen masuk terlebih dahulu sebelum mengajukan izin pulang awal.');
    }
    if (!empty($attendance['jam_pulang'])) {
        throw new DomainException('Pengajuan tidak dapat dibuat karena presensi pulang hari ini sudah tercatat.');
    }

    $stmtExisting = $db->prepare("
        SELECT id
        FROM izin_pulang_awal
        WHERE id_karyawan = :id AND tanggal_izin = :tanggal AND status IN ('pending', 'approved')
        LIMIT 1
        FOR UPDATE
    ");
    $stmtExisting->execute([':id' => $karyawanId, ':tanggal' => $today]);
    if ($stmtExisting->fetch()) {
        throw new DomainException('Anda sudah memiliki pengajuan izin yang menunggu atau telah disetujui untuk hari ini.');
    }

    $stmtInsert = $db->prepare("
        INSERT INTO izin_pulang_awal
            (id_karyawan, tanggal_izin, jam_pulang_diajukan, kategori_alasan, keterangan, status, created_at)
        VALUES (:id_karyawan, :tanggal, :jam, :kategori, :keterangan, 'pending', NOW())
    ");
    $stmtInsert->execute([
        ':id_karyawan' => $karyawanId,
        ':tanggal' => $today,
        ':jam' => $requestedTime . ':00',
        ':kategori' => $category,
        ':keterangan' => $details !== '' ? $details : null
    ]);
    $requestId = (int)$db->lastInsertId();
    $db->commit();

    catat_aktivitas($karyawanId, $user['nama'], $user['role'], 'AJUKAN_IZIN_PULANG_AWAL', "Mengajukan izin pulang awal #{$requestId} untuk {$today} pukul {$requestedTime}");
    $employee = [
        'nama' => $user['nama'],
        'nik' => $user['nik'],
        'jabatan' => $user['jabatan'],
        'tanggal' => format_tanggal_indo($today),
        'jam_pulang' => $requestedTime . ' WIB',
        'kategori_alasan' => $category
    ];
    $waResult = notifikasi_pengajuan_izin_pulang($employee, $user['no_hp']);
    $notificationsSucceeded = !empty($waResult['success'])
        && !empty($waResult['admin_notification']['success']);
    $notificationInfo = $notificationsSucceeded
        ? ' Notifikasi WhatsApp telah dikirim kepada Anda dan admin.'
        : ' Pengajuan tetap tersimpan, tetapi satu atau lebih notifikasi WhatsApp gagal dikirim.';
    set_flash('success', 'Pengajuan izin pulang lebih awal berhasil dikirim dan menunggu persetujuan admin.' . $notificationInfo, 'Pengajuan Terkirim');
} catch (DomainException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    set_flash('warning', $e->getMessage(), 'Pengajuan Ditolak');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Gagal mengajukan izin pulang awal: ' . $e->getMessage());
    set_flash('danger', 'Terjadi kesalahan sistem saat mengirim pengajuan izin.', 'Gagal');
}

header('Location: ' . BASE_URL . 'absensi/index.php');
exit;
