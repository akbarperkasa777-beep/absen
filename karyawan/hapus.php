<?php
/**
 * Modul Hapus Data Karyawan - Premium
 * Mengirimkan notifikasi WhatsApp Fonnte SEBELUM data dihapus dari database
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/fonnte.php';

require_admin_write();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Metode permintaan tidak sah. Operasi penghapusan harus menggunakan POST.', 'Akses Ditolak');
    header('Location: ' . BASE_URL . 'karyawan/index.php');
    exit;
}

if (!validate_csrf()) {
    set_flash('danger', 'Token keamanan CSRF tidak valid atau telah kedaluwarsa.', 'Operasi Dibatalkan');
    header('Location: ' . BASE_URL . 'karyawan/index.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID karyawan tidak valid.', 'Gagal');
    header('Location: ' . BASE_URL . 'karyawan/index.php');
    exit;
}

$currentUser = current_user();
if ($currentUser['id'] == $id) {
    set_flash('warning', 'Anda tidak dapat menghapus akun Anda sendiri saat sedang login.', 'Peringatan');
    header('Location: ' . BASE_URL . 'karyawan/index.php');
    exit;
}

$db = getDBConnection();

try {
    // 1. Ambil data karyawan SEBELUM dihapus
    $stmt = $db->prepare("SELECT id, nik, nama, no_hp, email FROM karyawan WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $karyawan = $stmt->fetch();

    if (!$karyawan) {
        set_flash('danger', 'Data karyawan tidak ditemukan atau sudah dihapus sebelumnya.', 'Gagal');
        header('Location: ' . BASE_URL . 'karyawan/index.php');
        exit;
    }

    $nama = $karyawan['nama'];
    $noHp = $karyawan['no_hp'];

    // Delete first so the notice only describes an action that actually succeeded.
    $stmtDelete = $db->prepare("DELETE FROM karyawan WHERE id = :id");
    $stmtDelete->execute([':id' => $id]);
    $waResult = notifikasi_hapus_karyawan(
        $nama,
        $karyawan['nik'],
        $noHp,
        wa_template_time($db->query('SELECT NOW()')->fetchColumn() ?: null)
    );

    // Catat log aktivitas
    catat_aktivitas($currentUser['id'], $currentUser['nama'], 'admin', 'HAPUS_KARYAWAN', "Menghapus karyawan: {$nama} (NIK: {$karyawan['nik']})");

    $waDetailInfo = info_status_wa(
        $waResult,
        ' Notifikasi WhatsApp berhasil dikirim ke nomor karyawan.',
        ' Notifikasi WhatsApp gagal; status tercatat pada log notifikasi.'
    );

    set_flash('success', "Data karyawan {$nama} telah berhasil dihapus dari database.{$waDetailInfo}", "Penghapusan Berhasil");

} catch (Exception $e) {
    error_log("Error saat menghapus karyawan: " . $e->getMessage());
    set_flash('danger', 'Terjadi kesalahan sistem saat menghapus data karyawan.', 'Kesalahan Sistem');
}

header('Location: ' . BASE_URL . 'karyawan/index.php');
exit;
