<?php
/**
 * Modul Login Autentikasi Pengguna - Premium UI
 * Portal Presensi Karyawan
 */

$isAuthPage = true;
$pageTitle = 'Login Pengguna';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_guest();

$error = '';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Permintaan tidak valid atau sesi telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $emailInput = trim($_POST['email'] ?? '');
        $passwordInput = $_POST['password'] ?? '';

        if (empty($emailInput) || empty($passwordInput)) {
            $error = 'Silakan masukkan alamat email dan password Anda.';
        } else {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("
                    SELECT id, nik, nama, jabatan, no_hp, email, password, role, status_akun, foto
                    FROM karyawan
                    WHERE email = :email
                    LIMIT 1
                ");
                $stmt->execute([':email' => strtolower($emailInput)]);
                $user = $stmt->fetch();

                if ($user && password_verify($passwordInput, $user['password'])) {
                    // Cek Status Akun Karyawan
                    if (($user['status_akun'] ?? 'aktif') === 'nonaktif') {
                        $error = 'Akun Anda sedang dinonaktifkan oleh Administrator. Silakan hubungi bagian IT atau HR.';
                    } else {
                        authenticate_user_session($user);

                        // Catat log audit aktivitas
                        catat_aktivitas($user['id'], $user['nama'], $user['role'], 'LOGIN', 'Berhasil masuk ke dalam sistem');

                        // Flash message yang bersih tanpa tag mentah
                        set_flash('success', 'Selamat datang kembali, ' . $user['nama'] . '!', 'Login Berhasil');

                        if ($user['role'] === 'admin') {
                            header('Location: ' . BASE_URL . 'admin/index.php');
                        } else {
                            header('Location: ' . BASE_URL . 'karyawan/dashboard.php');
                        }
                        exit;
                    }
                } else {
                    $error = 'Alamat email atau password yang Anda masukkan salah.';
                }
            } catch (Exception $e) {
                error_log("Database error on login: " . $e->getMessage());
                $error = 'Terjadi kesalahan sistem saat memproses login. Silakan coba sesaat lagi.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-body">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-brand-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h4 class="fw-bold mb-1 text-dark">Portal Presensi Karyawan</h4>
            <p class="text-muted small mb-0">Workforce Management & WhatsApp Gateway</p>
        </div>

        <?= display_flash(); ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4 p-3 rounded-3 border-0 border-start border-4 border-danger bg-white shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-danger me-2 flex-shrink-0"></i>
                <div class="small text-danger fw-medium"><?= e($error); ?></div>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="protect-submit" novalidate>
            <?= csrf_field(); ?>

            <div class="mb-3">
                <label for="email" class="form-label text-muted small fw-semibold">Alamat Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" value="<?= e($emailInput); ?>" placeholder="nama@domain.com" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label text-muted small fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-key"></i></span>
                    <input type="password" class="form-control border-start-0 border-end-0 ps-0" id="password" name="password" placeholder="Masukkan password Anda" required>
                    <button class="btn btn-outline-secondary border-start-0 btn-toggle-password" type="button" data-target="password" aria-label="Toggle password">
                        <i class="bi bi-eye text-muted"></i>
                    </button>
                </div>
            </div>

            <div class="d-grid mb-4">
                <button type="submit" class="btn btn-primary py-2 fw-semibold shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
                </button>
            </div>
        </form>

        <?php if (DEMO_LOGIN_ENABLED): ?>
            <div class="auth-demo-section">
                <div class="auth-demo-divider"><span>atau coba akun demo</span></div>
                <p class="text-center text-muted small mb-3">Pilih peran untuk menjelajahi data contoh. Akun demo bersifat hanya-baca.</p>
                <div class="d-grid gap-2">
                    <form action="<?= BASE_URL; ?>auth/demo_login.php" method="POST">
                        <?= csrf_field(); ?>
                        <button type="submit" name="demo_account" value="admin" class="btn auth-demo-button auth-demo-admin w-100">
                            <i class="bi bi-shield-lock-fill me-2"></i>Masuk sebagai Admin Demo
                        </button>
                    </form>
                    <form action="<?= BASE_URL; ?>auth/demo_login.php" method="POST">
                        <?= csrf_field(); ?>
                        <button type="submit" name="demo_account" value="karyawan" class="btn auth-demo-button auth-demo-employee w-100">
                            <i class="bi bi-person-badge-fill me-2"></i>Masuk sebagai Karyawan Demo
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center pt-2 border-top">
            <p class="text-muted small mb-0">
                Belum terdaftar sebagai karyawan?
                <a href="<?= BASE_URL; ?>auth/register.php" class="text-primary text-decoration-none fw-semibold">Daftar Akun Baru</a>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
