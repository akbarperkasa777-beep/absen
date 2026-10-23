<?php
/**
 * Modul Logout
 * Menghapus sesi dan mencatat log aktivitas pengguna
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
if ($user) {
    catat_aktivitas($user['id'], $user['nama'], $user['role'], 'LOGOUT', 'Keluar dari aplikasi');
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

session_start();
set_flash('info', 'Anda telah berhasil keluar dari sistem. Terima kasih atas kerja keras Anda!', 'Sampai Jumpa');

header('Location: ' . BASE_URL . 'auth/login.php');
exit;
