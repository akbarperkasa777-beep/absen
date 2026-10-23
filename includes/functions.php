<?php
/**
 * Helper Functions & Utilitas Keamanan Aplikasi
 * Portal Presensi Karyawan - Premium Edition
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// -------------------------------------------------------------
// 1. KEAMANAN CSRF (Cross-Site Request Forgery)
// -------------------------------------------------------------

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function validate_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $postToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($postToken) || empty($sessionToken) || !hash_equals($sessionToken, $postToken)) {
        return false;
    }
    return true;
}

// -------------------------------------------------------------
// 2. FLASH MESSAGES (Perbaikan Bug HTML Mentah)
// -------------------------------------------------------------

/**
 * Menetapkan pesan flash antar halaman
 *
 * @param string $type Jenis notifikasi: 'success', 'danger', 'warning', 'info'
 * @param string $message Isi pesan
 * @param string $title Judul notifikasi
 * @param bool $isHtmlFlag Apakah pesan berisi markup HTML yang sudah di-escape variabelnya
 */
function set_flash(string $type, string $message, string $title = '', bool $isHtmlFlag = false): void {
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message,
        'title'   => $title,
        'is_html' => $isHtmlFlag
    ];
}

function has_flash(): bool {
    return !empty($_SESSION['flash_message']);
}

/**
 * Menampilkan pesan flash dengan styling toast/alert modern
 * Menghilangkan bug penampilan tag HTML mentah seperti <strong>
 */
function display_flash(): string {
    if (!has_flash()) {
        return '';
    }
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    $type  = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
    $title = !empty($flash['title']) ? htmlspecialchars($flash['title'], ENT_QUOTES, 'UTF-8') : '';
    
    // Jika is_html bernilai true, tampilkan pesan terpercaya (variabel dinamis sudah disanitasi saat pemanggilan)
    // Jika false, escape secara penuh untuk mencegah XSS
    $message = !empty($flash['is_html']) 
        ? $flash['message'] 
        : htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');

    $iconMap = [
        'success' => 'bi-check-circle-fill text-success',
        'danger'  => 'bi-exclamation-triangle-fill text-danger',
        'warning' => 'bi-exclamation-circle-fill text-warning',
        'info'    => 'bi-info-circle-fill text-info'
    ];
    $icon = $iconMap[$type] ?? 'bi-info-circle-fill text-primary';

    $titlePart = $title !== '' ? "<strong>{$title}</strong> &mdash; " : "";

    return "
    <div class='alert alert-{$type} alert-dismissible fade show shadow-sm border-0 border-start border-4 border-{$type} bg-white d-flex align-items-center p-3 mb-4 rounded-3' role='alert'>
        <i class='bi {$icon} fs-4 me-3 flex-shrink-0'></i>
        <div class='flex-grow-1 text-dark small'>
            {$titlePart}{$message}
        </div>
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Tutup'></button>
    </div>";
}

// -------------------------------------------------------------
// 3. OTENTIKASI & OTORISASI ROLE
// -------------------------------------------------------------

function is_logged_in(): bool {
    return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.', 'Akses Ditolak');
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    $user = current_user();
    if (($user['role'] ?? '') !== 'admin') {
        set_flash('danger', 'Halaman ini hanya dapat diakses oleh Administrator.', 'Akses Terbatas');
        header('Location: ' . BASE_URL . 'karyawan/dashboard.php');
        exit;
    }
}

function is_demo_user(): bool {
    return !empty($_SESSION['user']['is_demo']);
}

function authenticate_user_session(array $user, bool $isDemo = false): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'          => (int)$user['id'],
        'nik'         => $user['nik'],
        'nama'        => $user['nama'],
        'jabatan'     => $user['jabatan'],
        'no_hp'       => $user['no_hp'],
        'email'       => $user['email'],
        'role'        => $user['role'],
        'status_akun' => $user['status_akun'] ?? 'aktif',
        'foto'        => $user['foto'] ?? null,
        'is_demo'     => $isDemo
    ];
}

function require_admin_write(): void {
    require_admin();
    if (is_demo_user()) {
        set_flash('warning', 'Akun demo hanya dapat melihat data dan tidak diizinkan mengubah data.', 'Akses Demo Terbatas');
        header('Location: ' . BASE_URL . 'admin/index.php');
        exit;
    }
}

function require_non_demo_user(): void {
    require_login();
    if (is_demo_user()) {
        set_flash('warning', 'Akun demo bersifat hanya-baca; tindakan ini tidak tersedia.', 'Akses Demo Terbatas');
        $destination = (current_user()['role'] ?? '') === 'admin' ? 'admin/index.php' : 'karyawan/dashboard.php';
        header('Location: ' . BASE_URL . $destination);
        exit;
    }
}

function require_guest(): void {
    if (is_logged_in()) {
        $user = current_user();
        if ($user['role'] === 'admin') {
            header('Location: ' . BASE_URL . 'admin/index.php');
        } else {
            header('Location: ' . BASE_URL . 'karyawan/dashboard.php');
        }
        exit;
    }
}

// -------------------------------------------------------------
// 4. SANITASI & FORMAT DATA
// -------------------------------------------------------------

function e(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function normalize_phone(string $phone): string {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '62')) {
        return $clean;
    } elseif (str_starts_with($clean, '0')) {
        return '62' . substr($clean, 1);
    } elseif (str_starts_with($clean, '8')) {
        return '62' . $clean;
    }
    return $clean;
}

function format_phone_display(string $phone): string {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '62')) {
        return '0' . substr($clean, 2);
    }
    return $clean;
}

function format_tanggal_indo(?string $dateStr): string {
    if (empty($dateStr)) return '-';
    $timestamp = strtotime($dateStr);
    if (!$timestamp) return '-';

    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $namaHari = $hari[date('w', $timestamp)];
    $tgl = date('d', $timestamp);
    $bln = $bulan[(int)date('m', $timestamp)];
    $thn = date('Y', $timestamp);

    return "{$namaHari}, {$tgl} {$bln} {$thn}";
}

function format_jam(?string $timeStr): string {
    if (empty($timeStr)) return '-';
    return date('H:i', strtotime($timeStr)) . ' WIB';
}

function badge_status_absensi(string $status): string {
    switch (strtoupper(trim($status))) {
        case 'BELUM ABSEN':
            return '<span class="badge badge-status-belum rounded-pill"><i class="bi bi-clock me-1"></i>Belum Absen</span>';
        case 'SUDAH ABSEN MASUK':
            return '<span class="badge badge-status-masuk rounded-pill"><i class="bi bi-box-arrow-in-right me-1"></i>Sudah Masuk</span>';
        case 'SELESAI':
        case 'PULANG':
            return '<span class="badge badge-status-selesai rounded-pill"><i class="bi bi-check2-circle me-1"></i>Selesai</span>';
        default:
            return '<span class="badge bg-light text-dark border border-secondary px-2 py-1 rounded-pill">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

function greeting_waktu(): string {
    $jam = (int)date('H');
    if ($jam >= 4 && $jam < 11) {
        return 'Selamat Pagi';
    } elseif ($jam >= 11 && $jam < 15) {
        return 'Selamat Siang';
    } elseif ($jam >= 15 && $jam < 18) {
        return 'Selamat Sore';
    } else {
        return 'Selamat Malam';
    }
}

// -------------------------------------------------------------
// 5. AUDIT TRAIL LOG AKTIVITAS SISTEM
// -------------------------------------------------------------

function catat_aktivitas(?int $idUser, string $namaUser, string $role, string $aksi, ?string $keterangan = null): void {
    try {
        $db = getDBConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("
            INSERT INTO log_aktivitas (id_user, nama_user, role, aksi, keterangan, ip_address, created_at)
            VALUES (:id_user, :nama_user, :role, :aksi, :keterangan, :ip, NOW())
        ");
        $stmt->execute([
            ':id_user'    => $idUser,
            ':nama_user'  => $namaUser,
            ':role'       => $role,
            ':aksi'       => $aksi,
            ':keterangan' => $keterangan,
            ':ip'         => $ip
        ]);
    } catch (Exception $e) {
        error_log("Gagal mencatat log aktivitas: " . $e->getMessage());
    }
}
