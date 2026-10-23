<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/attendance.php';
require_once __DIR__ . '/../includes/whatsapp_templates.php';

$checks = [
    attendance_minutes_late('06:55:00') === 0,
    attendance_minutes_late('07:00:00') === 0,
    attendance_minutes_late('07:01:00') === 1,
    attendance_minutes_late('07:08:00') === 8,
    attendance_minutes_late('07:25:00') === 25,
    attendance_checkout_is_early('11:59:00'),
    !attendance_checkout_is_early('12:00:00'),
    !attendance_time_reached('11:59:59', '12:00'),
    attendance_time_reached('12:00:00', '12:00')
];

$lateMessage = wa_template_attendance_employee([
    'nama' => 'Budi',
    'nik' => 'N1',
    'jabatan' => 'Staf',
    'tanggal' => 'Kamis, 22 Oktober 2026',
    'jam_masuk' => '07:08 WIB',
    'status_masuk' => 'TERLAMBAT',
    'menit_terlambat' => 8
]);
$earlyLeaveMessage = wa_template_early_leave_decision([
    'nama' => 'Budi',
    'tanggal' => 'Kamis, 22 Oktober 2026',
    'jam_pulang' => '11:30 WIB',
    'kategori_alasan' => 'Keperluan keluarga',
    'alasan_penolakan' => 'Contoh alasan'
], true);
$checks[] = str_contains($lateMessage, 'Keterlambatan: 8 menit');
$checks[] = str_contains($lateMessage, 'Jadwal Masuk: 07:00 WIB');
$checks[] = str_contains($earlyLeaveMessage, 'DISETUJUI');

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "Attendance rule checks failed.\n");
    exit(1);
}

echo 'Attendance rule checks passed: ' . count($checks) . PHP_EOL;
