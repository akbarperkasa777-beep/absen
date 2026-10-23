<?php
/**
 * Modul Ubah / Edit Data Karyawan - Premium UI
 * Admin mengubah profil, kemudian sistem otomatis mengirimkan notifikasi WhatsApp Fonnte ke Karyawan
 */

$pageTitle = 'Ubah Data Karyawan';
$activeMenu = 'karyawan';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/fonnte.php';

require_admin_write();

$db = getDBConnection();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    set_flash('danger', 'ID karyawan tidak valid.', 'Data Tidak Ditemukan');
    header('Location: ' . BASE_URL . 'karyawan/index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM karyawan WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$karyawan = $stmt->fetch();

if (!$karyawan) {
    set_flash('danger', 'Data karyawan tidak ditemukan dalam sistem.', 'Gagal Mengambil Data');
    header('Location: ' . BASE_URL . 'karyawan/index.php');
    exit;
}

$errors = [];
$old = [
    'nik'         => $karyawan['nik'],
    'nama'        => $karyawan['nama'],
    'jabatan'     => $karyawan['jabatan'],
    'no_hp'       => $karyawan['no_hp'],
    'email'       => $karyawan['email'],
    'role'        => $karyawan['role'],
    'status_akun' => $karyawan['status_akun'] ?? 'aktif'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errors['general'] = 'Permintaan tidak sah atau sesi telah kedaluwarsa.';
    } else {
        $nik         = trim($_POST['nik'] ?? '');
        $nama        = trim($_POST['nama'] ?? '');
        $jabatan     = trim($_POST['jabatan'] ?? '');
        $no_hp       = trim($_POST['no_hp'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $role        = in_array($_POST['role'] ?? '', ['admin', 'karyawan']) ? $_POST['role'] : $karyawan['role'];
        $status_akun = in_array($_POST['status_akun'] ?? '', ['aktif', 'nonaktif']) ? $_POST['status_akun'] : 'aktif';
        $password    = $_POST['password'] ?? '';

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

        if (!empty($password) && strlen($password) < 6) {
            $errors['password'] = 'Jika diubah, password baru minimal terdiri dari 6 karakter.';
        }

        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM karyawan WHERE nik = :nik AND id != :id LIMIT 1");
            $stmt->execute([':nik' => $nik, ':id' => $id]);
            if ($stmt->fetch()) {
                $errors['nik'] = 'NIK tersebut sudah digunakan karyawan lain.';
            }

            $stmt = $db->prepare("SELECT id FROM karyawan WHERE email = :email AND id != :id LIMIT 1");
            $stmt->execute([':email' => $email, ':id' => $id]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email tersebut sudah digunakan karyawan lain.';
            }
        }

        if (empty($errors)) {
            try {
                $normalizedPhone = normalize_phone($no_hp);
                $email = strtolower($email);
                $employeeChanges = [];
                $adminChangedLabels = [];
                $changeMap = [
                    'nama' => ['Nama', $nama],
                    'nik' => ['NIK', $nik],
                    'jabatan' => ['Jabatan', $jabatan],
                    'no_hp' => ['Nomor WhatsApp', $normalizedPhone],
                    'email' => ['Email', $email],
                    'role' => ['Role', $role]
                ];
                foreach ($changeMap as $field => [$label, $newValue]) {
                    if ((string)$karyawan[$field] !== (string)$newValue) {
                        $adminChangedLabels[] = $label;
                        if ($field !== 'email') {
                            $employeeChanges[$label] = $newValue;
                        }
                    }
                }
                $statusChanged = (string)$karyawan['status_akun'] !== $status_akun;
                if ($statusChanged) {
                    $adminChangedLabels[] = 'Status akun menjadi ' . $status_akun;
                }
                $passwordChanged = $password !== '';

                if ($passwordChanged) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUpdate = $db->prepare("
                        UPDATE karyawan
                        SET nik = :nik, nama = :nama, jabatan = :jabatan, no_hp = :no_hp,
                            email = :email, role = :role, status_akun = :status_akun, password = :password, updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':nik'         => $nik,
                        ':nama'        => $nama,
                        ':jabatan'     => $jabatan,
                        ':no_hp'       => $normalizedPhone,
                        ':email'       => $email,
                        ':role'        => $role,
                        ':status_akun' => $status_akun,
                        ':password'    => $hashed,
                        ':id'          => $id
                    ]);
                } else {
                    $stmtUpdate = $db->prepare("
                        UPDATE karyawan
                        SET nik = :nik, nama = :nama, jabatan = :jabatan, no_hp = :no_hp,
                            email = :email, role = :role, status_akun = :status_akun, updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':nik'         => $nik,
                        ':nama'        => $nama,
                        ':jabatan'     => $jabatan,
                        ':no_hp'       => $normalizedPhone,
                        ':email'       => $email,
                        ':role'        => $role,
                        ':status_akun' => $status_akun,
                        ':id'          => $id
                    ]);
                }

                $adminUser = current_user();
                catat_aktivitas($adminUser['id'], $adminUser['nama'], 'admin', 'EDIT_KARYAWAN', "Memperbarui profil karyawan ID {$id} ({$nama})");

                $stmtUpdated = $db->prepare('SELECT updated_at FROM karyawan WHERE id = :id LIMIT 1');
                $stmtUpdated->execute([':id' => $id]);
                $waktuPembaruan = wa_template_time($stmtUpdated->fetchColumn() ?: null);
                $notificationResults = [];
                $oldPhone = (string)$karyawan['no_hp'];

                if (isset($changeMap['email']) && (string)$karyawan['email'] !== $email) {
                    $notificationResults[] = notifikasi_ubah_email($nama, $email, $waktuPembaruan, $oldPhone);
                }
                if (!empty($employeeChanges)) {
                    $notificationResults[] = notifikasi_update_karyawan($nama, $employeeChanges, $waktuPembaruan, $oldPhone);
                }
                if (!empty($adminChangedLabels) || $statusChanged) {
                    $notificationResults[] = notifikasi_update_admin($nama, $nik, $adminChangedLabels, $waktuPembaruan);
                }
                if ($statusChanged) {
                    $notificationResults[] = notifikasi_status_akun($nama, $nik, $status_akun, $waktuPembaruan, $oldPhone);
                }
                if ($passwordChanged) {
                    $notificationResults[] = notifikasi_ubah_password($nama, $waktuPembaruan, $oldPhone);
                }

                $allNotificationsSucceeded = !empty($notificationResults);
                $allNotificationsSimulated = !empty($notificationResults);
                foreach ($notificationResults as $notificationResult) {
                    if (!$notificationResult['success']) {
                        $allNotificationsSucceeded = false;
                    }
                    if (empty($notificationResult['simulated'])) {
                        $allNotificationsSimulated = false;
                    }
                }
                $waStatusInfo = empty($notificationResults)
                    ? ''
                    : ($allNotificationsSucceeded
                        ? ' Notifikasi WhatsApp terkait perubahan berhasil dikirim.'
                        : ($allNotificationsSimulated
                            ? ' Notifikasi WhatsApp tidak dikirim karena token Fonnte belum dikonfigurasi.'
                            : ' Sebagian atau seluruh notifikasi WhatsApp gagal; status tercatat pada log notifikasi.'));

                set_flash('success', "Data karyawan {$nama} berhasil diperbarui.{$waStatusInfo}", "Pembaruan Berhasil");
                header('Location: ' . BASE_URL . 'karyawan/index.php');
                exit;

            } catch (Exception $e) {
                error_log("Error update karyawan: " . $e->getMessage());
                $errors['general'] = 'Terjadi kesalahan sistem saat memperbarui data.';
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
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Ubah Data Karyawan</h4>
                <p class="text-muted small mb-0">Perbarui data profil karyawan <strong><?= e($karyawan['nama']); ?></strong></p>
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
                            <input type="text" class="form-control form-control-sm <?= isset($errors['nik']) ? 'is-invalid' : ''; ?>" id="nik" name="nik" value="<?= e($old['nik']); ?>" required>
                            <?php if (isset($errors['nik'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['nik']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="nama" class="form-label text-muted small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm <?= isset($errors['nama']) ? 'is-invalid' : ''; ?>" id="nama" name="nama" value="<?= e($old['nama']); ?>" required>
                            <?php if (isset($errors['nama'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['nama']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="jabatan" class="form-label text-muted small fw-semibold">Jabatan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm <?= isset($errors['jabatan']) ? 'is-invalid' : ''; ?>" id="jabatan" name="jabatan" value="<?= e($old['jabatan']); ?>" required>
                            <?php if (isset($errors['jabatan'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['jabatan']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="no_hp" class="form-label text-muted small fw-semibold">Nomor WhatsApp <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control form-control-sm <?= isset($errors['no_hp']) ? 'is-invalid' : ''; ?>" id="no_hp" name="no_hp" value="<?= e($old['no_hp']); ?>" required>
                            <?php if (isset($errors['no_hp'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['no_hp']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label text-muted small fw-semibold">Alamat Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-sm <?= isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?= e($old['email']); ?>" required>
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
                            <label for="password" class="form-label text-muted small fw-semibold">Password Baru <span class="text-muted fw-normal">(Kosongkan jika tidak ingin mengganti)</span></label>
                            <div class="input-group input-group-sm">
                                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Masukkan password baru jika ingin diganti">
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
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan & Kirim Notifikasi WA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
