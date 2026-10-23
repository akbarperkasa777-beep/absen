<?php
/**
 * Fonnte WhatsApp delivery service.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/whatsapp_templates.php';

function hasil_wa_gagal(string $status, string $message, ?array $response = null): array {
    return [
        'success' => false,
        'simulated' => false,
        'status' => $status,
        'message' => $message,
        'detail' => $message,
        'response' => $response
    ];
}

function info_status_wa(array $result, string $successMessage, string $failureMessage): string {
    if (!empty($result['success'])) {
        return $successMessage;
    }
    if (!empty($result['simulated'])) {
        return ' Notifikasi WhatsApp tidak dikirim karena token Fonnte belum dikonfigurasi.';
    }
    return $failureMessage;
}

function kirim_wa_fonnte(string $target, string $message, string $notificationType = 'GENERAL'): array {
    $cleanTarget = normalize_phone($target);
    if (!preg_match('/^62[0-9]{8,13}$/', $cleanTarget)) {
        $safeTarget = $cleanTarget !== '' ? $cleanTarget : '-';
        simpan_log_notifikasi($safeTarget, $notificationType, 'INVALID_TARGET', 'Nomor tujuan tidak tersedia atau tidak valid.', $message);
        return hasil_wa_gagal('INVALID_TARGET', 'Nomor WhatsApp tujuan tidak tersedia atau tidak valid.');
    }

    $token = FONNTE_TOKEN;
    $isPlaceholderToken = $token === '' || in_array($token, [
        'ISI_TOKEN_FONNTE_ANDA',
        'FONNTE_TOKEN_ANDA',
        'YOUR_FONNTE_TOKEN_HERE'
    ], true);
    if ($isPlaceholderToken) {
        $response = [
            'status' => false,
            'reason' => 'ENV_TOKEN_PLACEHOLDER',
            'detail' => 'Token Fonnte belum dikonfigurasi.'
        ];
        simpan_log_notifikasi($cleanTarget, $notificationType, 'SIMULATED', 'Token Fonnte belum dikonfigurasi.', $message);
        return [
            'success' => false,
            'simulated' => true,
            'status' => 'SIMULATED',
            'message' => 'Pengiriman tidak dilakukan karena token Fonnte belum dikonfigurasi.',
            'detail' => $response['detail'],
            'response' => $response
        ];
    }

    if (!function_exists('curl_init')) {
        simpan_log_notifikasi($cleanTarget, $notificationType, 'FAILED', 'Ekstensi cURL tidak tersedia.', $message);
        return hasil_wa_gagal('CONNECTION_ERROR', 'Ekstensi cURL tidak tersedia pada server.');
    }

    $curl = curl_init();
    if ($curl === false) {
        simpan_log_notifikasi($cleanTarget, $notificationType, 'FAILED', 'Inisialisasi cURL gagal.', $message);
        return hasil_wa_gagal('CONNECTION_ERROR', 'Tidak dapat menyiapkan koneksi WhatsApp Gateway.');
    }

    curl_setopt_array($curl, [
        CURLOPT_URL => FONNTE_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'target' => $cleanTarget,
            'message' => $message,
            'countryCode' => '62'
        ],
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $responseRaw = curl_exec($curl);
    $curlErrorNumber = curl_errno($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = $curlErrorNumber !== 0 ? curl_error($curl) : '';
    curl_close($curl);

    if ($curlErrorNumber !== 0 || !is_string($responseRaw)) {
        $safeError = 'Koneksi ke Fonnte gagal' . ($curlErrorNumber ? " (cURL #{$curlErrorNumber})." : '.');
        error_log($safeError . ($curlError !== '' ? ' ' . $curlError : ''));
        simpan_log_notifikasi($cleanTarget, $notificationType, 'FAILED', $safeError, $message);
        return hasil_wa_gagal('CONNECTION_ERROR', 'Gagal menghubungi WhatsApp Gateway.');
    }

    $responseData = json_decode($responseRaw, true);
    $isSuccess = $httpCode >= 200
        && $httpCode < 300
        && is_array($responseData)
        && ($responseData['status'] ?? false) === true;
    $status = $isSuccess ? 'SUCCESS' : 'FAILED';
    simpan_log_notifikasi($cleanTarget, $notificationType, $status, "HTTP {$httpCode}; " . ($isSuccess ? 'gateway menerima pesan.' : 'gateway tidak menerima pesan.'), $message);

    return [
        'success' => $isSuccess,
        'simulated' => false,
        'status' => $status,
        'message' => $isSuccess
            ? 'Pesan WhatsApp berhasil diterima oleh gateway Fonnte.'
            : 'Fonnte tidak menerima pesan. Periksa konfigurasi dan riwayat pengiriman.',
        'detail' => $isSuccess ? 'Gateway mengonfirmasi penerimaan pesan.' : 'Respons gateway tidak menyatakan pengiriman berhasil.',
        'response' => $responseData
    ];
}

function simpan_log_notifikasi(string $target, string $type, string $status, ?string $summary, string $message): void {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            INSERT INTO log_notifikasi (penerima, tipe_notifikasi, pesan, status, response_api, created_at)
            VALUES (:penerima, :tipe, :pesan, :status, :response_api, NOW())
        ");
        $stmt->execute([
            ':penerima' => substr($target, 0, 30),
            ':tipe' => substr($type, 0, 50),
            ':pesan' => $message,
            ':status' => substr($status, 0, 30),
            ':response_api' => $summary
        ]);
    } catch (PDOException $e) {
        error_log('Gagal mencatat status notifikasi WhatsApp ke database: ' . $e->getCode());
    }
}

function notifikasi_register_karyawan(string $nama, string $nik, string $jabatan, string $email, string $noHp): array {
    return kirim_wa_fonnte($noHp, wa_template_register_employee([
        'nama' => $nama,
        'nik' => $nik,
        'jabatan' => $jabatan,
        'email' => $email,
        'no_hp' => $noHp
    ]), 'REGISTER_KARYAWAN');
}

function notifikasi_register_admin(string $nama, string $nik, string $jabatan, string $waktu): array {
    return kirim_wa_fonnte(ADMIN_WHATSAPP, wa_template_register_admin([
        'nama' => $nama,
        'nik' => $nik,
        'jabatan' => $jabatan,
        'waktu' => $waktu
    ]), 'REGISTER_ADMIN');
}

function notifikasi_absen_masuk(array $data, string $noHp): array {
    $resultEmployee = kirim_wa_fonnte($noHp, wa_template_attendance_employee($data), 'ABSEN_MASUK');
    $resultAdmin = kirim_wa_fonnte(ADMIN_WHATSAPP, wa_template_attendance_admin($data), 'ADMIN_ABSEN_MASUK');
    $resultEmployee['admin_notification'] = $resultAdmin;
    return $resultEmployee;
}

function notifikasi_absen_pulang(array $data, string $noHp): array {
    $resultEmployee = kirim_wa_fonnte($noHp, wa_template_attendance_employee($data, true), 'ABSEN_PULANG');
    $resultAdmin = kirim_wa_fonnte(ADMIN_WHATSAPP, wa_template_attendance_admin($data, true), 'ADMIN_ABSEN_PULANG');
    $resultEmployee['admin_notification'] = $resultAdmin;
    return $resultEmployee;
}

function notifikasi_pengajuan_izin_pulang(array $data, string $noHp): array {
    $employeeResult = kirim_wa_fonnte($noHp, wa_template_early_leave_request($data), 'IZIN_PULANG_AWAL');
    $adminResult = kirim_wa_fonnte(ADMIN_WHATSAPP, wa_template_early_leave_request($data, true), 'ADMIN_IZIN_PULANG_AWAL');
    $employeeResult['admin_notification'] = $adminResult;
    return $employeeResult;
}

function notifikasi_keputusan_izin_pulang(array $data, string $noHp, bool $approved): array {
    return kirim_wa_fonnte(
        $noHp,
        wa_template_early_leave_decision($data, $approved),
        $approved ? 'IZIN_PULANG_DISETUJUI' : 'IZIN_PULANG_DITOLAK'
    );
}

function notifikasi_update_karyawan(string $nama, array $changes, string $waktu, string $noHp): array {
    return kirim_wa_fonnte($noHp, wa_template_employee_update($nama, $changes, $waktu), 'UPDATE_KARYAWAN');
}

function notifikasi_update_admin(string $nama, string $nik, array $changedLabels, string $waktu): array {
    return kirim_wa_fonnte(ADMIN_WHATSAPP, wa_template_admin_update($nama, $nik, $changedLabels, $waktu), 'UPDATE_KARYAWAN_ADMIN');
}

function notifikasi_status_akun(string $nama, string $nik, string $status, string $waktu, string $noHp): array {
    return kirim_wa_fonnte($noHp, wa_template_account_status($nama, $nik, $status, $waktu), 'STATUS_AKUN');
}

function notifikasi_hapus_karyawan(string $nama, string $nik, string $noHp, string $waktu): array {
    return kirim_wa_fonnte($noHp, wa_template_permanent_removal($nama, $nik, $waktu), 'DELETE_KARYAWAN');
}

function notifikasi_ubah_email(string $nama, string $emailBaru, string $waktu, string $noHp): array {
    return kirim_wa_fonnte($noHp, wa_template_email_change($nama, $emailBaru, $waktu), 'UPDATE_EMAIL');
}

function notifikasi_ubah_password(string $nama, string $waktu, string $noHp): array {
    return kirim_wa_fonnte($noHp, wa_template_password_change($nama, $waktu), 'UPDATE_PASSWORD');
}
