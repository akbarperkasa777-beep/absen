<?php
/**
 * Modul Profil Pengguna & Keamanan Akun - Premium UI
 * Menampilkan data profil, fitur ganti email, dan fitur ganti password
 */

$pageTitle = 'Profil Saya & Pengaturan Keamanan';
$activeMenu = 'profil';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/fonnte.php';

require_login();

$user = current_user();
$karyawanId = (int)$user['id'];
$db = getDBConnection();

// Ambil data profil terbaru dari database
$stmtUser = $db->prepare("SELECT * FROM karyawan WHERE id = :id LIMIT 1");
$stmtUser->execute([':id' => $karyawanId]);
$profil = $stmtUser->fetch();

if (!$profil) {
    set_flash('danger', 'Data akun tidak ditemukan.', 'Kesalahan');
    header('Location: ' . BASE_URL . 'auth/logout.php');
    exit;
}

$errorEmail = '';
$errorPass = '';
$errorPhone = '';
$phoneInput = $profil['no_hp'];

// 1. PROSES GANTI NOMOR WHATSAPP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ganti_whatsapp') {
    require_non_demo_user();
    $phoneInput = trim($_POST['whatsapp_baru'] ?? '');

    if (!validate_csrf()) {
        $errorPhone = 'Token CSRF tidak sah atau telah kedaluwarsa.';
    } else {
        $passwordSaat = $_POST['password_verifikasi_whatsapp'] ?? '';
        $normalizedPhone = normalize_phone($phoneInput);

        if ($phoneInput === '') {
            $errorPhone = 'Nomor WhatsApp baru wajib diisi.';
        } elseif (!preg_match('/^62[0-9]{8,13}$/', $normalizedPhone)) {
            $errorPhone = 'Format nomor WhatsApp tidak valid. Gunakan nomor Indonesia yang aktif.';
        } elseif ($normalizedPhone === normalize_phone($profil['no_hp'])) {
            $errorPhone = 'Nomor WhatsApp baru sama dengan nomor saat ini.';
        } elseif ($passwordSaat === '') {
            $errorPhone = 'Password saat ini wajib diisi untuk verifikasi identitas.';
        } elseif (!password_verify($passwordSaat, $profil['password'])) {
            $errorPhone = 'Password verifikasi yang Anda masukkan salah.';
        } else {
            try {
                $stmtPhone = $db->prepare('UPDATE karyawan SET no_hp = :no_hp, updated_at = NOW() WHERE id = :id');
                $stmtPhone->execute([':no_hp' => $normalizedPhone, ':id' => $karyawanId]);

                $_SESSION['user']['no_hp'] = $normalizedPhone;
                catat_aktivitas($karyawanId, $profil['nama'], $profil['role'], 'UPDATE_WHATSAPP', 'Memperbarui nomor WhatsApp akun');

                set_flash('success', 'Nomor WhatsApp berhasil diperbarui. Notifikasi berikutnya akan dikirim ke nomor baru.', 'Pembaruan Berhasil');
                header('Location: ' . BASE_URL . 'karyawan/profil.php');
                exit;
            } catch (Exception $e) {
                error_log('Error ubah nomor WhatsApp: ' . $e->getMessage());
                $errorPhone = 'Terjadi kesalahan sistem saat memperbarui nomor WhatsApp.';
            }
        }
    }
}

// 2. PROSES GANTI EMAIL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ganti_email') {
    require_non_demo_user();
    if (!validate_csrf()) {
        $errorEmail = 'Token CSRF tidak sah atau telah kedaluwarsa.';
    } else {
        $emailBaru    = trim($_POST['email_baru'] ?? '');
        $passwordSaat = $_POST['password_verifikasi'] ?? '';

        if (empty($emailBaru)) {
            $errorEmail = 'Alamat email baru wajib diisi.';
        } elseif (!filter_var($emailBaru, FILTER_VALIDATE_EMAIL)) {
            $errorEmail = 'Format alamat email baru tidak valid.';
        } elseif (strtolower($emailBaru) === strtolower($profil['email'])) {
            $errorEmail = 'Email baru tidak boleh sama dengan email saat ini.';
        } elseif (empty($passwordSaat)) {
            $errorEmail = 'Password saat ini wajib diisi untuk verifikasi identitas.';
        } elseif (!password_verify($passwordSaat, $profil['password'])) {
            $errorEmail = 'Password verifikasi yang Anda masukkan salah.';
        } else {
            // Cek keunikan email baru
            $stmtCheck = $db->prepare("SELECT id FROM karyawan WHERE email = :email AND id != :id LIMIT 1");
            $stmtCheck->execute([':email' => strtolower($emailBaru), ':id' => $karyawanId]);
            if ($stmtCheck->fetch()) {
                $errorEmail = 'Alamat email tersebut sudah digunakan oleh akun lain.';
            } else {
                try {
                    $stmtUpd = $db->prepare("UPDATE karyawan SET email = :email, updated_at = NOW() WHERE id = :id");
                    $stmtUpd->execute([':email' => strtolower($emailBaru), ':id' => $karyawanId]);

                    // Perbarui sesi
                    $_SESSION['user']['email'] = strtolower($emailBaru);

                    // Catat log aktivitas
                    catat_aktivitas($karyawanId, $profil['nama'], $profil['role'], 'UPDATE_EMAIL', 'Mengubah email dari ' . $profil['email'] . ' ke ' . $emailBaru);

                    // Notifikasi WhatsApp
                    $stmtUpdated = $db->prepare('SELECT updated_at FROM karyawan WHERE id = :id LIMIT 1');
                    $stmtUpdated->execute([':id' => $karyawanId]);
                    $waResult = notifikasi_ubah_email(
                        $profil['nama'],
                        strtolower($emailBaru),
                        wa_template_time($stmtUpdated->fetchColumn() ?: null),
                        $profil['no_hp']
                    );

                    $waStatus = info_status_wa(
                        $waResult,
                        ' Notifikasi WhatsApp keamanan berhasil dikirim.',
                        ' Notifikasi WhatsApp gagal dikirim; status tercatat pada log notifikasi.'
                    );
                    set_flash('success', 'Alamat email berhasil diperbarui.' . $waStatus, 'Pembaruan Berhasil');
                    header('Location: ' . BASE_URL . 'karyawan/profil.php');
                    exit;

                } catch (Exception $e) {
                    error_log("Error ubah email: " . $e->getMessage());
                    $errorEmail = 'Terjadi kesalahan sistem saat memperbarui email.';
                }
            }
        }
    }
}

// 3. PROSES GANTI PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ganti_password') {
    require_non_demo_user();
    if (!validate_csrf()) {
        $errorPass = 'Token CSRF tidak sah atau telah kedaluwarsa.';
    } else {
        $passLama = $_POST['password_lama'] ?? '';
        $passBaru = $_POST['password_baru'] ?? '';
        $konfBaru = $_POST['konfirmasi_password_baru'] ?? '';

        if (empty($passLama) || empty($passBaru) || empty($konfBaru)) {
            $errorPass = 'Seluruh kolom password wajib diisi.';
        } elseif (!password_verify($passLama, $profil['password'])) {
            $errorPass = 'Password saat ini yang Anda masukkan salah.';
        } elseif (strlen($passBaru) < 6) {
            $errorPass = 'Password baru minimal terdiri dari 6 karakter.';
        } elseif ($passBaru !== $konfBaru) {
            $errorPass = 'Konfirmasi password baru tidak cocok.';
        } else {
            try {
                $hashedNew = password_hash($passBaru, PASSWORD_DEFAULT);
                $stmtPass = $db->prepare("UPDATE karyawan SET password = :pass, updated_at = NOW() WHERE id = :id");
                $stmtPass->execute([':pass' => $hashedNew, ':id' => $karyawanId]);

                // Regenerasi session ID untuk keamanan
                session_regenerate_id(true);

                // Catat log aktivitas
                catat_aktivitas($karyawanId, $profil['nama'], $profil['role'], 'UPDATE_PASSWORD', 'Memperbarui kata sandi akun');

                $stmtUpdated = $db->prepare('SELECT updated_at FROM karyawan WHERE id = :id LIMIT 1');
                $stmtUpdated->execute([':id' => $karyawanId]);
                $waResult = notifikasi_ubah_password(
                    $profil['nama'],
                    wa_template_time($stmtUpdated->fetchColumn() ?: null),
                    $profil['no_hp']
                );
                $waStatus = info_status_wa(
                    $waResult,
                    ' Notifikasi WhatsApp keamanan berhasil dikirim.',
                    ' Notifikasi WhatsApp gagal dikirim; status tercatat pada log notifikasi.'
                );
                set_flash('success', 'Password Anda telah berhasil diperbarui dengan aman.' . $waStatus, 'Password Berhasil Diubah');
                header('Location: ' . BASE_URL . 'karyawan/profil.php');
                exit;

            } catch (Exception $e) {
                error_log("Error ganti password: " . $e->getMessage());
                $errorPass = 'Terjadi kesalahan sistem saat memperbarui password.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <!-- Kolom Kiri: Informasi Identitas Akun -->
    <div class="col-lg-4">
        <div class="card card-profile-premium mb-4">
            <div class="card-profile-header-bar"></div>
            <div class="card-body text-center pt-0 px-4 pb-4">
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar-large">
                        <?= strtoupper(substr($profil['nama'], 0, 1)); ?>
                    </div>
                </div>
                <h4 class="profile-name"><?= e($profil['nama']); ?></h4>
                <div class="profile-role-title"><?= e($profil['jabatan']); ?></div>

                <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                    <span class="badge badge-nik-contrast">
                        <i class="bi bi-card-heading me-1 text-primary"></i>NIK: <?= e($profil['nik']); ?>
                    </span>
                    <span class="badge <?= $profil['status_akun'] === 'aktif' ? 'badge-status-selesai' : 'badge-status-belum'; ?>">
                        <i class="bi bi-shield-check me-1"></i>Akun <?= ucfirst(e($profil['status_akun'])); ?>
                    </span>
                </div>

                <!-- Baris Informasi dengan Kontras Tinggi -->
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp:</span>
                    <span class="profile-info-value font-monospace"><?= e(format_phone_display($profil['no_hp'])); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="bi bi-envelope me-1 text-primary"></i>Email:</span>
                    <span class="profile-info-value"><?= e($profil['email']); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="bi bi-person-badge me-1 text-primary"></i>Peran Sistem:</span>
                    <span class="profile-info-value text-uppercase"><?= e($profil['role']); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="bi bi-calendar-event me-1 text-primary"></i>Terdaftar:</span>
                    <span class="profile-info-value"><?= format_tanggal_indo($profil['created_at']); ?></span>
                </div>
            </div>
        </div>

        <div class="alert alert-light border border-slate-300 small text-slate-700 shadow-xs">
            <i class="bi bi-info-circle-fill text-primary me-2"></i>
            <strong>Kebijakan Kepegawaian:</strong> NIK dan Jabatan dikelola secara terpusat oleh Administrator untuk menjaga konsistensi data.
        </div>
    </div>

    <!-- Kolom Kanan: Form Ganti WhatsApp, Email & Password -->
    <div class="col-lg-8">
        <?php if (is_demo_user()): ?>
            <div class="alert alert-info border-0 border-start border-4 border-info bg-white shadow-sm">
                <i class="bi bi-info-circle-fill me-2"></i>Profil demo hanya-baca. Perubahan WhatsApp, email, dan password dinonaktifkan.
            </div>
        <?php else: ?>
        <!-- Form Ganti Nomor WhatsApp -->
        <div class="card card-custom mb-4">
            <div class="card-header bg-white py-3 border-bottom border-slate-200">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-whatsapp text-success me-2"></i>Perbarui Nomor WhatsApp</h6>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errorPhone)): ?>
                    <div class="alert alert-danger small mb-3 p-3 rounded-3 border-0 border-start border-4 border-danger bg-white shadow-xs">
                        <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i><?= e($errorPhone); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="protect-submit" novalidate>
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="ganti_whatsapp">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="whatsapp_saat_ini" class="form-label small fw-semibold text-slate-700">Nomor WhatsApp Saat Ini:</label>
                            <input type="text" class="form-control form-control-sm bg-slate-100 text-slate-800 fw-medium" id="whatsapp_saat_ini" value="<?= e(format_phone_display($profil['no_hp'])); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="whatsapp_baru" class="form-label small fw-semibold text-slate-700">Nomor WhatsApp Baru: <span class="text-danger">*</span></label>
                            <input type="tel" inputmode="tel" class="form-control form-control-sm text-slate-900" id="whatsapp_baru" name="whatsapp_baru" value="<?= e($phoneInput); ?>" placeholder="08xxxxxxxxxx atau 628xxxxxxxxxx" required>
                        </div>
                        <div class="col-12">
                            <label for="password_verifikasi_whatsapp" class="form-label small fw-semibold text-slate-700">Password Verifikasi: <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-sm text-slate-900" id="password_verifikasi_whatsapp" name="password_verifikasi_whatsapp" placeholder="Masukkan password saat ini untuk konfirmasi" required>
                            <div class="form-text text-slate-500" style="font-size: 0.75rem;">Nomor ini akan digunakan untuk notifikasi WhatsApp berikutnya.</div>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Nomor WhatsApp
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Form Ganti Email -->
        <div class="card card-custom mb-4">
            <div class="card-header bg-white py-3 border-bottom border-slate-200">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-envelope-at text-primary me-2"></i>Perbarui Alamat Email</h6>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errorEmail)): ?>
                    <div class="alert alert-danger small mb-3 p-3 rounded-3 border-0 border-start border-4 border-danger bg-white shadow-xs">
                        <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i><?= e($errorEmail); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="protect-submit" novalidate>
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="ganti_email">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-slate-700">Email Saat Ini:</label>
                            <input type="text" class="form-control form-control-sm bg-slate-100 text-slate-800 fw-medium" value="<?= e($profil['email']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="email_baru" class="form-label small fw-semibold text-slate-700">Email Baru: <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-sm text-slate-900" id="email_baru" name="email_baru" placeholder="emailbaru@domain.com" required>
                        </div>
                        <div class="col-12">
                            <label for="password_verifikasi" class="form-label small fw-semibold text-slate-700">Password Verifikasi: <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-sm text-slate-900" id="password_verifikasi" name="password_verifikasi" placeholder="Masukkan password saat ini untuk konfirmasi" required>
                            <div class="form-text text-slate-500" style="font-size: 0.75rem;">Diperlukan untuk memverifikasi bahwa perubahan dilakukan oleh pemilik akun sah.</div>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Email Baru
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Form Ganti Password -->
        <div class="card card-custom">
            <div class="card-header bg-white py-3 border-bottom border-slate-200">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-shield-lock text-primary me-2"></i>Perbarui Password Akun</h6>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errorPass)): ?>
                    <div class="alert alert-danger small mb-3 p-3 rounded-3 border-0 border-start border-4 border-danger bg-white shadow-xs">
                        <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i><?= e($errorPass); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="protect-submit" novalidate>
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="ganti_password">

                    <div class="mb-3">
                        <label for="password_lama" class="form-label small fw-semibold text-slate-700">Password Saat Ini: <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="password" class="form-control text-slate-900" id="password_lama" name="password_lama" placeholder="Password lama Anda" required>
                            <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="password_lama">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="password_baru" class="form-label small fw-semibold text-slate-700">Password Baru: <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control text-slate-900" id="password_baru" name="password_baru" placeholder="Minimal 6 karakter" required>
                                <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="password_baru">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="konfirmasi_password_baru" class="form-label small fw-semibold text-slate-700">Ulangi Password Baru: <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control text-slate-900" id="konfirmasi_password_baru" name="konfirmasi_password_baru" placeholder="Konfirmasi password baru" required>
                                <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="konfirmasi_password_baru">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">
                            <i class="bi bi-key-fill me-1"></i> Perbarui Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
