<?php
/**
 * Modul Registrasi Karyawan Baru - Premium UI
 * Portal Presensi Karyawan
 */

$isAuthPage = true;
$pageTitle = 'Registrasi Karyawan';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/fonnte.php';

require_guest();

$errors = [];
$old = [
    'nik'      => '',
    'nama'     => '',
    'jabatan'  => '',
    'no_hp'    => '',
    'email'    => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors['general'] = 'Permintaan tidak valid atau token sesi telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $nik              = trim($_POST['nik'] ?? '');
        $nama             = trim($_POST['nama'] ?? '');
        $jabatan          = trim($_POST['jabatan'] ?? '');
        $no_hp            = trim($_POST['no_hp'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $konfirmasi_pass  = $_POST['konfirmasi_password'] ?? '';

        $old = [
            'nik'     => $nik,
            'nama'    => $nama,
            'jabatan' => $jabatan,
            'no_hp'   => $no_hp,
            'email'   => $email
        ];

        if (empty($nik)) {
            $errors['nik'] = 'Nomor Induk Karyawan (NIK) wajib diisi.';
        } elseif (strlen($nik) < 3 || strlen($nik) > 30) {
            $errors['nik'] = 'NIK harus berupa 3 hingga 30 karakter.';
        }

        if (empty($nama)) {
            $errors['nama'] = 'Nama lengkap wajib diisi.';
        }

        if (empty($jabatan)) {
            $errors['jabatan'] = 'Jabatan wajib diisi.';
        }

        if (empty($no_hp)) {
            $errors['no_hp'] = 'Nomor WhatsApp wajib diisi.';
        } else {
            $cleanPhone = preg_replace('/[^0-9]/', '', $no_hp);
            if (strlen($cleanPhone) < 9 || strlen($cleanPhone) > 16) {
                $errors['no_hp'] = 'Format nomor WhatsApp tidak valid (minimal 10 digit).';
            }
        }

        if (empty($email)) {
            $errors['email'] = 'Alamat email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format alamat email tidak valid.';
        }

        if (empty($password)) {
            $errors['password'] = 'Password wajib diisi.';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Password minimal terdiri dari 6 karakter.';
        }

        if (empty($konfirmasi_pass)) {
            $errors['konfirmasi_password'] = 'Konfirmasi password wajib diisi.';
        } elseif ($password !== $konfirmasi_pass) {
            $errors['konfirmasi_password'] = 'Konfirmasi password tidak cocok dengan password.';
        }

        if (empty($errors)) {
            $db = getDBConnection();

            $stmtNik = $db->prepare("SELECT id FROM karyawan WHERE nik = :nik LIMIT 1");
            $stmtNik->execute([':nik' => $nik]);
            if ($stmtNik->fetch()) {
                $errors['nik'] = 'NIK tersebut sudah terdaftar pada sistem.';
            }

            $stmtEmail = $db->prepare("SELECT id FROM karyawan WHERE email = :email LIMIT 1");
            $stmtEmail->execute([':email' => $email]);
            if ($stmtEmail->fetch()) {
                $errors['email'] = 'Email tersebut sudah terdaftar pada sistem.';
            }
        }

        if (empty($errors)) {
            try {
                $db = getDBConnection();
                $hashedPassword  = password_hash($password, PASSWORD_DEFAULT);
                $normalizedPhone = normalize_phone($no_hp);

                $stmtInsert = $db->prepare("
                    INSERT INTO karyawan (nik, nama, jabatan, no_hp, email, password, role, status_akun, created_at)
                    VALUES (:nik, :nama, :jabatan, :no_hp, :email, :password, 'karyawan', 'aktif', NOW())
                ");

                $stmtInsert->execute([
                    ':nik'      => $nik,
                    ':nama'     => $nama,
                    ':jabatan'  => $jabatan,
                    ':no_hp'    => $normalizedPhone,
                    ':email'    => strtolower($email),
                    ':password' => $hashedPassword
                ]);

                $newUserId = (int)$db->lastInsertId();

                // Catat log audit aktivitas
                catat_aktivitas($newUserId, $nama, 'karyawan', 'REGISTER', 'Pendaftaran mandiri akun karyawan baru');

                // Pengiriman Notifikasi WhatsApp Fonnte
                $stmtCreated = $db->prepare('SELECT created_at FROM karyawan WHERE id = :id LIMIT 1');
                $stmtCreated->execute([':id' => $newUserId]);
                $registeredAt = wa_template_time($stmtCreated->fetchColumn() ?: null);
                $resKaryawan = notifikasi_register_karyawan($nama, $nik, $jabatan, strtolower($email), $normalizedPhone);
                $resAdmin = notifikasi_register_admin($nama, $nik, $jabatan, $registeredAt);

                $waStatusMsg = "";
                if ($resKaryawan['success'] && $resAdmin['success']) {
                    $waStatusMsg = " Notifikasi WhatsApp berhasil dikirim ke nomor Anda dan Admin.";
                } elseif (!empty($resKaryawan['simulated']) && !empty($resAdmin['simulated'])) {
                    $waStatusMsg = " Notifikasi WhatsApp tidak dikirim karena token Fonnte belum dikonfigurasi.";
                } elseif (!$resKaryawan['success'] || !$resAdmin['success']) {
                    $waStatusMsg = " Sebagian atau seluruh notifikasi WhatsApp gagal dikirim; statusnya tercatat pada log notifikasi.";
                }

                set_flash('success', 'Pendaftaran akun berhasil!' . $waStatusMsg . ' Silakan masuk menggunakan email dan password Anda.', 'Registrasi Sukses');
                header('Location: ' . BASE_URL . 'auth/login.php');
                exit;

            } catch (Exception $e) {
                error_log("Kesalahan saat registrasi karyawan: " . $e->getMessage());
                $errors['general'] = 'Terjadi kesalahan sistem saat menyimpan data. Silakan coba sesaat lagi.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-body py-5">
    <div class="auth-card wide">
        <div class="auth-header">
            <div class="auth-brand-icon">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <h4 class="fw-bold mb-1 text-dark">Registrasi Karyawan Baru</h4>
            <p class="text-muted small mb-0">Lengkapi formulir untuk membuat akun Portal Presensi Karyawan</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3 p-3 rounded-3 border-0 border-start border-4 border-danger bg-white shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-danger me-2"></i>
                <div class="small text-danger fw-medium"><?= e($errors['general']); ?></div>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="protect-submit" novalidate>
            <?= csrf_field(); ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nik" class="form-label text-muted small fw-semibold">NIK (Nomor Induk Karyawan) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-card-heading"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0 <?= isset($errors['nik']) ? 'is-invalid' : ''; ?>" id="nik" name="nik" value="<?= e($old['nik']); ?>" placeholder="Contoh: KRY003" required>
                    </div>
                    <?php if (isset($errors['nik'])): ?>
                        <div class="text-danger small mt-1" style="font-size: 0.8rem;"><?= e($errors['nik']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="nama" class="form-label text-muted small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0 <?= isset($errors['nama']) ? 'is-invalid' : ''; ?>" id="nama" name="nama" value="<?= e($old['nama']); ?>" placeholder="Nama lengkap Anda" required>
                    </div>
                    <?php if (isset($errors['nama'])): ?>
                        <div class="text-danger small mt-1" style="font-size: 0.8rem;"><?= e($errors['nama']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="jabatan" class="form-label text-muted small fw-semibold">Jabatan / Posisi <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-briefcase"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0 <?= isset($errors['jabatan']) ? 'is-invalid' : ''; ?>" id="jabatan" name="jabatan" value="<?= e($old['jabatan']); ?>" placeholder="Contoh: Frontend Engineer" required>
                    </div>
                    <?php if (isset($errors['jabatan'])): ?>
                        <div class="text-danger small mt-1" style="font-size: 0.8rem;"><?= e($errors['jabatan']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="no_hp" class="form-label text-muted small fw-semibold">Nomor WhatsApp <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-success"><i class="bi bi-whatsapp"></i></span>
                        <input type="tel" class="form-control border-start-0 ps-0 <?= isset($errors['no_hp']) ? 'is-invalid' : ''; ?>" id="no_hp" name="no_hp" value="<?= e($old['no_hp']); ?>" placeholder="08xxxxxxxxxx" required>
                    </div>
                    <div class="form-text text-muted" style="font-size: 0.72rem;">Notifikasi konfirmasi akan dikirim via WhatsApp.</div>
                    <?php if (isset($errors['no_hp'])): ?>
                        <div class="text-danger small mt-1" style="font-size: 0.8rem;"><?= e($errors['no_hp']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label for="email" class="form-label text-muted small fw-semibold">Alamat Email <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control border-start-0 ps-0 <?= isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?= e($old['email']); ?>" placeholder="email@perusahaan.com" required>
                    </div>
                    <?php if (isset($errors['email'])): ?>
                        <div class="text-danger small mt-1" style="font-size: 0.8rem;"><?= e($errors['email']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label text-muted small fw-semibold">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control border-start-0 border-end-0 ps-0 <?= isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Minimal 6 karakter" required>
                        <button class="btn btn-outline-secondary border-start-0 btn-toggle-password" type="button" data-target="password">
                            <i class="bi bi-eye text-muted"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="text-danger small mt-1" style="font-size: 0.8rem;"><?= e($errors['password']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="konfirmasi_password" class="form-label text-muted small fw-semibold">Konfirmasi Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control border-start-0 border-end-0 ps-0 <?= isset($errors['konfirmasi_password']) ? 'is-invalid' : ''; ?>" id="konfirmasi_password" name="konfirmasi_password" placeholder="Ulangi password" required>
                        <button class="btn btn-outline-secondary border-start-0 btn-toggle-password" type="button" data-target="konfirmasi_password">
                            <i class="bi bi-eye text-muted"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['konfirmasi_password'])): ?>
                        <div class="text-danger small mt-1" style="font-size: 0.8rem;"><?= e($errors['konfirmasi_password']); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary py-2 fw-semibold shadow-sm">
                    <i class="bi bi-person-check-fill me-1"></i> Selesaikan Pendaftaran
                </button>
            </div>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <p class="text-muted small mb-0">
                Sudah memiliki akun?
                <a href="<?= BASE_URL; ?>auth/login.php" class="text-primary text-decoration-none fw-semibold">Masuk di sini</a>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
