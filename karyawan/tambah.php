<?php
/**
 * Modul Tambah Karyawan oleh Admin - Premium UI
 * Portal Presensi Karyawan
 */

$pageTitle = 'Tambah Karyawan Baru';
$activeMenu = 'karyawan';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/fonnte.php';

require_admin_write();

$errors = [];
$old = [
    'nik'         => '',
    'nama'        => '',
    'jabatan'     => '',
    'no_hp'       => '',
    'email'       => '',
    'role'        => 'karyawan',
    'status_akun' => 'aktif'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors['general'] = 'Permintaan tidak sah atau sesi kedaluwarsa.';
    } else {
        $nik         = trim($_POST['nik'] ?? '');
        $nama        = trim($_POST['nama'] ?? '');
        $jabatan     = trim($_POST['jabatan'] ?? '');
        $no_hp       = trim($_POST['no_hp'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $password    = $_POST['password'] ?? '';
        $role        = in_array($_POST['role'] ?? '', ['admin', 'karyawan']) ? $_POST['role'] : 'karyawan';
        $status_akun = in_array($_POST['status_akun'] ?? '', ['aktif', 'nonaktif']) ? $_POST['status_akun'] : 'aktif';

        $old = [
            'nik'         => $nik,
            'nama'        => $nama,
            'jabatan'     => $jabatan,
            'no_hp'       => $no_hp,
            'email'       => $email,
            'role'        => $role,
            'status_akun' => $status_akun
        ];

        if (empty($nik)) $errors['nik'] = 'NIK wajib diisi.';
        if (empty($nama)) $errors['nama'] = 'Nama lengkap wajib diisi.';
        if (empty($jabatan)) $errors['jabatan'] = 'Jabatan wajib diisi.';
        if (empty($no_hp)) $errors['no_hp'] = 'Nomor WhatsApp wajib diisi.';
        if (empty($email)) {
            $errors['email'] = 'Email wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        }
        if (empty($password)) {
            $errors['password'] = 'Password awal wajib diisi.';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Password minimal 6 karakter.';
        }

        if (empty($errors)) {
            $db = getDBConnection();

            $stmt = $db->prepare("SELECT id FROM karyawan WHERE nik = :nik LIMIT 1");
            $stmt->execute([':nik' => $nik]);
            if ($stmt->fetch()) {
                $errors['nik'] = 'NIK sudah digunakan oleh karyawan lain.';
            }

            $stmt = $db->prepare("SELECT id FROM karyawan WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Alamat email sudah terdaftar.';
            }
        }

        if (empty($errors)) {
            try {
                $db = getDBConnection();
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $normalizedPhone = normalize_phone($no_hp);

                $stmt = $db->prepare("
                    INSERT INTO karyawan (nik, nama, jabatan, no_hp, email, password, role, status_akun, created_at)
                    VALUES (:nik, :nama, :jabatan, :no_hp, :email, :password, :role, :status_akun, NOW())
                ");
                $stmt->execute([
                    ':nik'         => $nik,
                    ':nama'        => $nama,
                    ':jabatan'     => $jabatan,
                    ':no_hp'       => $normalizedPhone,
                    ':email'       => strtolower($email),
                    ':password'    => $hashed,
                    ':role'        => $role,
                    ':status_akun' => $status_akun
                ]);

                $newId = (int)$db->lastInsertId();

                // Catat log aktivitas admin
                $adminUser = current_user();
                catat_aktivitas($adminUser['id'], $adminUser['nama'], 'admin', 'TAMBAH_KARYAWAN', "Menambahkan karyawan baru: {$nama} (NIK: {$nik})");

                $stmtCreated = $db->prepare('SELECT created_at FROM karyawan WHERE id = :id LIMIT 1');
                $stmtCreated->execute([':id' => $newId]);
                $registeredAt = wa_template_time($stmtCreated->fetchColumn() ?: null);
                $waEmployee = notifikasi_register_karyawan($nama, $nik, $jabatan, strtolower($email), $normalizedPhone);
                $waAdmin = notifikasi_register_admin($nama, $nik, $jabatan, $registeredAt);

                if ($waEmployee['success'] && $waAdmin['success']) {
                    $waStatus = ' Notifikasi WhatsApp berhasil dikirim.';
                } elseif (!empty($waEmployee['simulated']) && !empty($waAdmin['simulated'])) {
                    $waStatus = ' Notifikasi WhatsApp tidak dikirim karena token Fonnte belum dikonfigurasi.';
                } else {
                    $waStatus = ' Sebagian atau seluruh notifikasi WhatsApp gagal; status tercatat pada log notifikasi.';
                }
                set_flash('success', "Karyawan {$nama} berhasil ditambahkan.{$waStatus}", "Berhasil Menambahkan");
                header('Location: ' . BASE_URL . 'karyawan/index.php');
                exit;

            } catch (Exception $e) {
                error_log("Error insert karyawan: " . $e->getMessage());
                $errors['general'] = 'Terjadi kesalahan sistem saat menyimpan data.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-person-plus text-primary me-2"></i>Tambah Karyawan Baru</h4>
                <p class="text-muted small mb-0">Isi formulir untuk menambahkan karyawan baru ke sistem</p>
            </div>
            <a href="<?= BASE_URL; ?>karyawan/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3 p-3 rounded-3" role="alert"><?= e($errors['general']); ?></div>
        <?php endif; ?>

        <div class="card card-custom">
            <div class="card-body p-4">
                <form action="" method="POST" class="protect-submit" novalidate>
                    <?= csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nik" class="form-label text-muted small fw-semibold">NIK <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm <?= isset($errors['nik']) ? 'is-invalid' : ''; ?>" id="nik" name="nik" value="<?= e($old['nik']); ?>" placeholder="Contoh: KRY004" required>
                            <?php if (isset($errors['nik'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['nik']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="nama" class="form-label text-muted small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm <?= isset($errors['nama']) ? 'is-invalid' : ''; ?>" id="nama" name="nama" value="<?= e($old['nama']); ?>" placeholder="Nama lengkap" required>
                            <?php if (isset($errors['nama'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['nama']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="jabatan" class="form-label text-muted small fw-semibold">Jabatan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm <?= isset($errors['jabatan']) ? 'is-invalid' : ''; ?>" id="jabatan" name="jabatan" value="<?= e($old['jabatan']); ?>" placeholder="Contoh: Staff IT" required>
                            <?php if (isset($errors['jabatan'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['jabatan']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="no_hp" class="form-label text-muted small fw-semibold">Nomor WhatsApp <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control form-control-sm <?= isset($errors['no_hp']) ? 'is-invalid' : ''; ?>" id="no_hp" name="no_hp" value="<?= e($old['no_hp']); ?>" placeholder="08xxxxxxxxxx" required>
                            <?php if (isset($errors['no_hp'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['no_hp']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label text-muted small fw-semibold">Alamat Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-sm <?= isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?= e($old['email']); ?>" placeholder="email@perusahaan.com" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-3">
                            <label for="role" class="form-label text-muted small fw-semibold">Role Pengguna <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="role" name="role" required>
                                <option value="karyawan" <?= ($old['role'] === 'karyawan') ? 'selected' : ''; ?>>Karyawan</option>
                                <option value="admin" <?= ($old['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="status_akun" class="form-label text-muted small fw-semibold">Status Akun <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="status_akun" name="status_akun" required>
                                <option value="aktif" <?= ($old['status_akun'] === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                <option value="nonaktif" <?= ($old['status_akun'] === 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="password" class="form-label text-muted small fw-semibold">Password Awal Akun <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Minimal 6 karakter" required>
                                <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['password']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= BASE_URL; ?>karyawan/index.php" class="btn btn-secondary btn-sm">Batal</a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-save me-1"></i> Simpan Data Karyawan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
