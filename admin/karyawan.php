<?php
/**
 * Router / Alias untuk Manajemen Karyawan Admin
 * Mengarahkan navigasi admin ke modul karyawan utama
 */

require_once __DIR__ . '/../config/config.php';
header('Location: ' . BASE_URL . 'karyawan/index.php');
exit;

