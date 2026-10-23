<?php
/**
 * PresensiWA WhatsApp message templates.
 */

function wa_template_value(?string $value, string $fallback = '-'): string {
    $value = (string)($value ?? '');
    $value = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = strtr(trim($value), [
        '*' => '＊',
        '_' => '＿',
        '~' => '～',
        '`' => "'"
    ]);
    return $value !== '' ? $value : $fallback;
}

function wa_template_time(?string $datetime = null): string {
    $timestamp = $datetime !== null ? strtotime($datetime) : time();
    if ($timestamp === false) {
        $timestamp = time();
    }
    return format_tanggal_indo(date('Y-m-d', $timestamp)) . ' • ' . date('H:i', $timestamp) . ' WIB';
}

function wa_template_optional_line(string $emoji, string $label, ?string $value): ?string {
    if (trim((string)$value) === '') {
        return null;
    }
    return $emoji . ' ' . $label . ': ' . wa_template_value($value);
}

function wa_template_register_employee(array $data): string {
    $lines = [
        '🏢 *PRESENSIWA — REGISTRASI BERHASIL*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '👋 Halo, *' . wa_template_value($data['nama'] ?? null) . '*!',
        '',
        'Selamat bergabung di Portal Presensi Karyawan. Akun kamu berhasil didaftarkan.',
        '',
        '🪪 *Informasi Akun*',
        '👤 Nama: ' . wa_template_value($data['nama'] ?? null),
        '🪪 NIK: ' . wa_template_value($data['nik'] ?? null),
        '💼 Jabatan: ' . wa_template_value($data['jabatan'] ?? null)
    ];
    foreach ([
        wa_template_optional_line('📧', 'Email', $data['email'] ?? null),
        wa_template_optional_line('📱', 'WhatsApp', $data['no_hp'] ?? null)
    ] as $line) {
        if ($line !== null) {
            $lines[] = $line;
        }
    }
    $lines = array_merge($lines, [
        '',
        '━━━━━━━━━━━━━━',
        '',
        '🔐 *Keamanan Akun*',
        'Jaga kerahasiaan password dan jangan membagikannya kepada siapa pun.',
        '',
        '📲 Silakan login ke Portal PresensiWA untuk mulai menggunakan layanan presensi.',
        '',
        '🙏 Terima kasih telah bergabung!'
    ]);
    return implode("\n", $lines);
}

function wa_template_register_admin(array $data): string {
    return implode("\n", [
        '🏢 *PRESENSIWA — KARYAWAN BARU*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '✅ Karyawan baru berhasil didaftarkan.',
        '',
        '👤 Nama: ' . wa_template_value($data['nama'] ?? null),
        '🪪 NIK: ' . wa_template_value($data['nik'] ?? null),
        '💼 Jabatan: ' . wa_template_value($data['jabatan'] ?? null),
        '📅 Waktu Daftar: ' . wa_template_value($data['waktu'] ?? null),
        '',
        '━━━━━━━━━━━━━━',
        '',
        '📊 Silakan buka dashboard administrator untuk melihat detail.'
    ]);
}

function wa_template_attendance_employee(array $data, bool $isCheckout = false): string {
    $isLate = !$isCheckout && ($data['status_masuk'] ?? '') === 'TERLAMBAT';
    $isEarlyCheckout = $isCheckout && ($data['status_pulang'] ?? '') === 'PULANG_LEBIH_AWAL_IZIN';
    $lines = [
        $isLate
            ? '🏢 *PRESENSIWA — NOTIFIKASI KETERLAMBATAN*'
            : ($isCheckout ? '🏢 *PRESENSIWA — ABSEN PULANG*' : '🏢 *PRESENSIWA — ABSEN MASUK*'),
        '',
        '━━━━━━━━━━━━━━',
        '',
        '👋 Halo, *' . wa_template_value($data['nama'] ?? null) . '*!',
        '',
        $isCheckout
            ? ($isEarlyCheckout ? '✅ Presensi pulang berhasil dicatat berdasarkan izin yang disetujui.' : '✅ Presensi pulang kamu berhasil dicatat.')
            : ($isLate ? '⚠️ Presensi masuk berhasil dicatat, tetapi kamu datang terlambat.' : '✅ Presensi masuk kamu berhasil dicatat.'),
        '',
        $isCheckout ? '📋 *Ringkasan Presensi*' : '📋 *Detail Presensi*',
        '👤 Nama: ' . wa_template_value($data['nama'] ?? null),
        '🪪 NIK: ' . wa_template_value($data['nik'] ?? null)
    ];
    if (!$isCheckout) {
        $lines[] = '💼 Jabatan: ' . wa_template_value($data['jabatan'] ?? null);
    }
    $lines[] = '📅 Tanggal: ' . wa_template_value($data['tanggal'] ?? null);
    $lines[] = '🕐 Jam Masuk: ' . wa_template_value($data['jam_masuk'] ?? null);
    if (!$isCheckout && $isLate) {
        $lines[] = '⏰ Jadwal Masuk: ' . ATTENDANCE_START_TIME . ' WIB';
        $lines[] = '⌛ Keterlambatan: ' . (int)($data['menit_terlambat'] ?? 0) . ' menit';
        $lines[] = '📌 Status: *Terlambat*';
    } elseif (!$isCheckout) {
        $lines[] = '📌 Status: *Tepat Waktu*';
    }
    if ($isCheckout) {
        $lines[] = ($isEarlyCheckout ? '🕔 Jam Pulang Aktual: ' : '🕔 Jam Pulang: ') . wa_template_value($data['jam_pulang'] ?? null);
        if ($isEarlyCheckout) {
            $lines[] = '📝 Alasan Izin: ' . wa_template_value($data['kategori_alasan'] ?? null);
            $lines[] = '📌 Status: *Pulang Lebih Awal — Izin Disetujui*';
        }
    }
    $lines = array_merge($lines, [
        '',
        '━━━━━━━━━━━━━━',
        '',
        $isCheckout
            ? '🎉 Terima kasih atas kerja keras kamu hari ini.'
            : ($isLate ? '📝 Harap memperhatikan jadwal masuk kerja berikutnya.' : '🎯 Selamat bekerja dan semoga aktivitas hari ini berjalan lancar!'),
        '',
        $isCheckout
            ? '🙏 Selamat beristirahat dan sampai jumpa kembali!'
            : '🙏 Terima kasih telah melakukan presensi.'
    ]);
    return implode("\n", $lines);
}

function wa_template_attendance_admin(array $data, bool $isCheckout = false): string {
    $isLate = !$isCheckout && ($data['status_masuk'] ?? '') === 'TERLAMBAT';
    $isEarlyCheckout = $isCheckout && ($data['status_pulang'] ?? '') === 'PULANG_LEBIH_AWAL_IZIN';
    $lines = [
        $isLate ? '🏢 *PRESENSIWA — LAPORAN KETERLAMBATAN*' : '🏢 *PRESENSIWA — NOTIFIKASI ADMIN*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        $isCheckout
            ? ($isEarlyCheckout ? '📋 Karyawan melakukan presensi pulang berdasarkan izin yang disetujui.' : '✅ Karyawan berhasil melakukan presensi pulang.')
            : ($isLate ? '⚠️ Karyawan melakukan presensi masuk setelah jadwal kerja.' : '✅ Karyawan berhasil melakukan presensi masuk.'),
        '',
        '👤 Nama: ' . wa_template_value($data['nama'] ?? null),
        '🪪 NIK: ' . wa_template_value($data['nik'] ?? null),
        '💼 Jabatan: ' . wa_template_value($data['jabatan'] ?? null),
        '📅 Tanggal: ' . wa_template_value($data['tanggal'] ?? null),
        '🕐 Jam Masuk: ' . wa_template_value($data['jam_masuk'] ?? null)
    ];
    if ($isCheckout) {
        $lines[] = ($isEarlyCheckout ? '🕔 Jam Pulang Aktual: ' : '🕔 Jam Pulang: ') . wa_template_value($data['jam_pulang'] ?? null);
        if ($isEarlyCheckout) {
            $lines[] = '📝 Alasan Izin: ' . wa_template_value($data['kategori_alasan'] ?? null);
            $lines[] = '📌 Status: *Pulang Lebih Awal — Izin Disetujui*';
        }
    } elseif ($isLate) {
        $lines[] = '⏰ Jadwal Masuk: ' . ATTENDANCE_START_TIME . ' WIB';
        $lines[] = '⌛ Terlambat: ' . (int)($data['menit_terlambat'] ?? 0) . ' menit';
    }
    $lines = array_merge($lines, [
        '',
        '━━━━━━━━━━━━━━',
        '',
        '📊 Silakan buka dashboard administrator untuk melihat detail presensi.'
    ]);
    return implode("\n", $lines);
}

function wa_template_early_leave_request(array $data, bool $forAdmin = false): string {
    if ($forAdmin) {
        return implode("\n", [
            '🏢 *PRESENSIWA — PERMINTAAN PERSETUJUAN*',
            '',
            '━━━━━━━━━━━━━━',
            '',
            '📋 Ada pengajuan izin pulang lebih awal yang memerlukan pemeriksaan.',
            '👤 Nama: ' . wa_template_value($data['nama'] ?? null),
            '🪪 NIK: ' . wa_template_value($data['nik'] ?? null),
            '💼 Jabatan: ' . wa_template_value($data['jabatan'] ?? null),
            '📅 Tanggal Izin: ' . wa_template_value($data['tanggal'] ?? null),
            '🕐 Jam Pulang yang Diminta: ' . wa_template_value($data['jam_pulang'] ?? null),
            '📝 Alasan: ' . wa_template_value($data['kategori_alasan'] ?? null),
            '📌 Status: *Menunggu Persetujuan*',
            '',
            '━━━━━━━━━━━━━━',
            '',
            '🔎 Silakan buka dashboard administrator untuk memeriksa pengajuan.'
        ]);
    }

    return implode("\n", [
        '🏢 *PRESENSIWA — PENGAJUAN IZIN*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '👋 Halo, *' . wa_template_value($data['nama'] ?? null) . '*.',
        '',
        '📝 Pengajuan izin pulang lebih awal kamu berhasil dikirim dan sedang menunggu persetujuan administrator.',
        '📅 Tanggal: ' . wa_template_value($data['tanggal'] ?? null),
        '🕐 Jam Pulang yang Diajukan: ' . wa_template_value($data['jam_pulang'] ?? null),
        '📌 Alasan: ' . wa_template_value($data['kategori_alasan'] ?? null),
        '🔔 Status: *Menunggu Persetujuan*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '⚠️ Pengajuan belum disetujui. Kamu belum dapat pulang lebih awal sebelum ada keputusan administrator.'
    ]);
}

function wa_template_early_leave_decision(array $data, bool $approved): string {
    $lines = [
        $approved ? '🏢 *PRESENSIWA — IZIN DISETUJUI*' : '🏢 *PRESENSIWA — IZIN DITOLAK*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '👋 Halo, *' . wa_template_value($data['nama'] ?? null) . '*.',
        '',
        $approved
            ? '✅ Pengajuan izin pulang lebih awal kamu telah *DISETUJUI* oleh administrator.'
            : '❌ Pengajuan izin pulang lebih awal kamu belum disetujui oleh administrator.',
        '📅 Tanggal: ' . wa_template_value($data['tanggal'] ?? null),
        '🕐 Jam Pulang yang Diajukan: ' . wa_template_value($data['jam_pulang'] ?? null),
        '📝 Alasan Pengajuan: ' . wa_template_value($data['kategori_alasan'] ?? null),
        '📌 Status: *' . ($approved ? 'Disetujui' : 'Ditolak') . '*'
    ];
    if (!$approved) {
        $lines[] = '💬 Alasan Penolakan: ' . wa_template_value($data['alasan_penolakan'] ?? null);
    }
    $lines = array_merge($lines, [
        '',
        '━━━━━━━━━━━━━━',
        '',
        $approved
            ? '🙏 Silakan melakukan presensi pulang sesuai waktu yang disetujui. Jam pulang aktual dicatat saat kamu benar-benar presensi.'
            : '📲 Silakan periksa detail pengajuan atau hubungi administrator jika memerlukan penjelasan.'
    ]);
    return implode("\n", $lines);
}

function wa_template_employee_update(string $nama, array $changes, string $waktu): string {
    $icons = [
        'Nama' => '👤',
        'NIK' => '🪪',
        'Jabatan' => '💼',
        'Email' => '📧',
        'Nomor WhatsApp' => '📱',
        'Role' => '📝'
    ];
    $lines = [
        '🏢 *PRESENSIWA — PEMBARUAN DATA*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '👋 Halo, *' . wa_template_value($nama) . '*.',
        '',
        '✏️ Informasi akun kamu telah diperbarui oleh administrator.',
        '',
        '📋 *Informasi yang Diperbarui*'
    ];
    foreach ($changes as $label => $value) {
        $safeLabel = wa_template_value((string)$label);
        $lines[] = ($icons[$label] ?? '📝') . ' ' . $safeLabel . ': ' . wa_template_value((string)$value);
    }
    $lines = array_merge($lines, [
        '📅 Waktu Pembaruan: ' . wa_template_value($waktu),
        '',
        '━━━━━━━━━━━━━━',
        '',
        '📝 Jika kamu tidak mengetahui perubahan ini, silakan hubungi administrator.',
        '',
        '🔐 Demi keamanan akun, jangan bagikan password kepada siapa pun.'
    ]);
    return implode("\n", $lines);
}

function wa_template_admin_update(string $nama, string $nik, array $changedLabels, string $waktu): string {
    return implode("\n", [
        '🏢 *PRESENSIWA — PEMBARUAN DATA KARYAWAN*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '✏️ Data karyawan berhasil diperbarui.',
        '👤 Nama: ' . wa_template_value($nama),
        '🪪 NIK: ' . wa_template_value($nik),
        '📝 Data berubah: ' . wa_template_value(implode(', ', $changedLabels)),
        '📅 Waktu: ' . wa_template_value($waktu),
        '',
        '━━━━━━━━━━━━━━',
        '',
        '📊 Detail tersedia pada dashboard administrator.'
    ]);
}

function wa_template_account_status(string $nama, string $nik, string $status, string $waktu): string {
    $statusText = $status === 'nonaktif' ? 'dinonaktifkan' : 'diaktifkan kembali';
    $title = $status === 'nonaktif' ? 'INFORMASI AKUN' : 'AKUN DIAKTIFKAN';
    return implode("\n", [
        '🏢 *PRESENSIWA — ' . $title . '*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '👋 Halo, *' . wa_template_value($nama) . '*.',
        '',
        '⚠️ Akun karyawan kamu telah ' . $statusText . ' oleh administrator.',
        '',
        '📋 *Informasi Akun*',
        '👤 Nama: ' . wa_template_value($nama),
        '🪪 NIK: ' . wa_template_value($nik),
        '📅 Waktu: ' . wa_template_value($waktu),
        '',
        '━━━━━━━━━━━━━━',
        '',
        '📝 Jika kamu merasa tindakan ini tidak sesuai, silakan hubungi administrator.'
    ]);
}

function wa_template_permanent_removal(string $nama, string $nik, string $waktu): string {
    return implode("\n", [
        '🏢 *PRESENSIWA — INFORMASI AKUN*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '👋 Halo, *' . wa_template_value($nama) . '*.',
        '',
        '⚠️ Akun karyawan kamu telah dihapus secara permanen oleh administrator.',
        '',
        '📋 *Informasi Akun*',
        '👤 Nama: ' . wa_template_value($nama),
        '🪪 NIK: ' . wa_template_value($nik),
        '📅 Waktu: ' . wa_template_value($waktu),
        '',
        '━━━━━━━━━━━━━━',
        '',
        '📝 Jika kamu merasa tindakan ini tidak sesuai, silakan hubungi administrator.'
    ]);
}

function wa_template_email_change(string $nama, string $emailBaru, string $waktu): string {
    return implode("\n", [
        '🏢 *PRESENSIWA — PERUBAHAN EMAIL*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '🔐 Email akun kamu telah diperbarui.',
        '',
        '👤 Nama: ' . wa_template_value($nama),
        '📧 Email Baru: ' . wa_template_value($emailBaru),
        '📅 Waktu Perubahan: ' . wa_template_value($waktu),
        '',
        '━━━━━━━━━━━━━━',
        '',
        '⚠️ Jika kamu tidak melakukan perubahan ini, segera hubungi administrator untuk memeriksa keamanan akun.'
    ]);
}

function wa_template_password_change(string $nama, string $waktu): string {
    return implode("\n", [
        '🏢 *PRESENSIWA — KEAMANAN AKUN*',
        '',
        '━━━━━━━━━━━━━━',
        '',
        '🔐 Password akun kamu berhasil diubah.',
        '',
        '👤 Nama: ' . wa_template_value($nama),
        '📅 Waktu Perubahan: ' . wa_template_value($waktu),
        '',
        '━━━━━━━━━━━━━━',
        '',
        '🛡️ Demi keamanan akun:',
        '- Jangan membagikan password kepada siapa pun.',
        '- Gunakan password yang kuat dan unik.',
        '- Jika kamu tidak melakukan perubahan ini, segera hubungi administrator.',
        '🙏 Terima kasih telah menjaga keamanan akun.'
    ]);
}
