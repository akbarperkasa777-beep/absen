<?php
/**
 * Konfigurasi Utama Aplikasi - Portal Presensi Karyawan
 * Membaca seluruh variabel lingkungan dari file .env
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Inisialisasi Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// 1. Zona Waktu Aplikasi
$appTimezone = $_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta';
date_default_timezone_set($appTimezone);

// 2. Mulai Session dengan Aman
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// 3. Konfigurasi Dasar Aplikasi dari .env
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Portal Presensi Karyawan');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'local');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_VERSION', '2.0.0 Premium');

// 4. Konfigurasi Base URL
$appUrl = $_ENV['APP_URL'] ?? 'http://localhost/karyawan-wa';
define('BASE_URL', rtrim($appUrl, '/') . '/');

// 5. Konfigurasi WhatsApp Gateway Fonnte dari .env
define('FONNTE_TOKEN', $_ENV['FONNTE_TOKEN'] ?? '');
define('FONNTE_API_URL', 'https://api.fonnte.com/send');
define('ADMIN_WHATSAPP', $_ENV['FONNTE_ADMIN_NUMBER'] ?? '');
define('ATTENDANCE_START_TIME', $_ENV['ATTENDANCE_START_TIME'] ?? '07:00');
define('EARLIEST_CHECKOUT_TIME', $_ENV['EARLIEST_CHECKOUT_TIME'] ?? '12:00');

// Login demo stays disabled unless explicitly enabled in the local environment.
define('DEMO_LOGIN_ENABLED', filter_var($_ENV['DEMO_LOGIN_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('DEMO_ACCOUNTS', [
    'admin' => [
        'nik' => 'DEMO-ADMIN-001',
        'email' => 'admin.demo@presensiwa.local',
        'nama' => 'Admin Demo',
        'jabatan' => 'Administrator Demo',
        'role' => 'admin'
    ],
    'karyawan' => [
        'nik' => 'DEMO-KARYAWAN-001',
        'email' => 'karyawan.demo@presensiwa.local',
        'nama' => 'Karyawan Demo',
        'jabatan' => 'Staf Demo',
        'role' => 'karyawan'
    ]
]);
