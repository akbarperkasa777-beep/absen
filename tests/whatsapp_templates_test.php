<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/whatsapp_templates.php';

$registration = wa_template_register_employee([
    'nama' => "Budi\nAdmin",
    'nik' => 'DEMO001',
    'jabatan' => 'Staf',
    'email' => 'budi@example.test',
    'no_hp' => '6281234567890'
]);
$attendanceIn = wa_template_attendance_employee([
    'nama' => 'Budi',
    'nik' => 'N1',
    'jabatan' => 'Staf',
    'tanggal' => 'Rabu, 21 Oktober 2026',
    'jam_masuk' => '08:12 WIB'
]);
$attendanceOut = wa_template_attendance_employee([
    'nama' => 'Budi',
    'nik' => 'N1',
    'tanggal' => 'Rabu, 21 Oktober 2026',
    'jam_masuk' => '08:12 WIB',
    'jam_pulang' => '17:03 WIB'
], true);
$employeeUpdate = wa_template_employee_update('Budi', ['Email' => 'baru@example.test'], 'Waktu');
$deactivation = wa_template_account_status('Budi', 'N1', 'nonaktif', 'Waktu');
$permanentRemoval = wa_template_permanent_removal('Budi', 'N1', 'Waktu');
$emailChange = wa_template_email_change('Budi', 'baru@example.test', 'Waktu');
$passwordChange = wa_template_password_change('Budi', 'Waktu');
$adminRegistration = wa_template_register_admin([
    'nama' => 'Budi',
    'nik' => 'N1',
    'jabatan' => 'Staf',
    'waktu' => 'Waktu'
]);

$checks = [
    str_contains($registration, '📧 Email: budi@example.test'),
    str_contains($registration, '📱 WhatsApp: 6281234567890'),
    str_contains($registration, 'Halo, *Budi Admin*!'),
    str_contains($attendanceIn, '📅 Tanggal: Rabu, 21 Oktober 2026')
        && str_contains($attendanceIn, '🕐 Jam Masuk: 08:12 WIB'),
    str_contains($attendanceOut, '🕔 Jam Pulang: 17:03 WIB'),
    str_contains($employeeUpdate, '📧 Email: baru@example.test'),
    str_contains($deactivation, 'telah dinonaktifkan')
        && !str_contains($deactivation, 'dihapus secara permanen'),
    str_contains($permanentRemoval, 'dihapus secara permanen')
        && !str_contains($permanentRemoval, 'telah dinonaktifkan'),
    str_contains($emailChange, '📧 Email Baru: baru@example.test')
        && !str_contains($emailChange, 'terverifikasi'),
    str_contains($passwordChange, 'Jangan membagikan password')
        && !str_contains($passwordChange, 'password-baru'),
    str_contains($adminRegistration, 'Karyawan baru berhasil didaftarkan.')
];

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "WhatsApp template checks failed.\n");
    exit(1);
}

echo 'WhatsApp template checks passed: ' . count($checks) . PHP_EOL;
