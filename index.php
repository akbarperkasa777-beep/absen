<?php
/**
 * Root Index Router - Portal Presensi Karyawan
 * Mengarahkan pengunjung berdasarkan sesi dan hak akses role
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    $user = current_user();
    if (($user['role'] ?? '') === 'admin') {
        header('Location: ' . BASE_URL . 'admin/index.php');
    } else {
        header('Location: ' . BASE_URL . 'karyawan/dashboard.php');
    }
    exit;
} else {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}
