<?php
/**
 * One-click login for fixed, read-only demo accounts.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_guest();

$redirectToLogin = static function (): void {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
};

if (!DEMO_LOGIN_ENABLED) {
    set_flash('warning', 'Login demo sedang dinonaktifkan.', 'Mode Demo Tidak Aktif');
    $redirectToLogin();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Login demo hanya dapat diproses melalui formulir yang sah.', 'Akses Ditolak');
    $redirectToLogin();
}

if (!validate_csrf()) {
    set_flash('danger', 'Token keamanan tidak valid atau telah kedaluwarsa.', 'Login Demo Ditolak');
    $redirectToLogin();
}

$demoKey = $_POST['demo_account'] ?? '';
if (!is_string($demoKey) || !isset(DEMO_ACCOUNTS[$demoKey])) {
    set_flash('danger', 'Pilihan akun demo tidak valid.', 'Login Demo Ditolak');
    $redirectToLogin();
}

try {
    $account = DEMO_ACCOUNTS[$demoKey];
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT id, nik, nama, jabatan, no_hp, email, role, status_akun, foto
        FROM karyawan
        WHERE nik = :nik AND email = :email AND role = :role
        LIMIT 1
    ");
    $stmt->execute([
        ':nik' => $account['nik'],
        ':email' => $account['email'],
        ':role' => $account['role']
    ]);
    $user = $stmt->fetch();

    if (!$user || ($user['status_akun'] ?? '') !== 'aktif') {
        set_flash('warning', 'Akun demo belum disiapkan atau sedang dinonaktifkan. Hubungi administrator aplikasi.', 'Login Demo Tidak Tersedia');
        $redirectToLogin();
    }

    authenticate_user_session($user, true);
    catat_aktivitas((int)$user['id'], $user['nama'], $user['role'], 'LOGIN_DEMO', 'Masuk menggunakan akun demonstrasi');
    set_flash('success', 'Anda masuk menggunakan akun demo yang bersifat hanya-baca.', 'Selamat Datang');

    $destination = $user['role'] === 'admin' ? 'admin/index.php' : 'karyawan/dashboard.php';
    header('Location: ' . BASE_URL . $destination);
    exit;
} catch (PDOException $e) {
    error_log('Kesalahan database pada login demo.');
    set_flash('danger', 'Terjadi kesalahan saat memproses login demo. Silakan coba kembali.', 'Gagal Masuk');
    $redirectToLogin();
}
