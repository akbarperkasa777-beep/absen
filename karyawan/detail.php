<?php
/**
 * Modul Detail Karyawan untuk Administrator - Premium UI
 * Menampilkan profil lengkap, ringkasan presensi, toggle status akun aktif/nonaktif,
 * dan riwayat kehadiran karyawan
 */

$pageTitle = 'Detail Profil Karyawan';
$activeMenu = 'karyawan';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/fonnte.php';

require_admin();

$db = getDBConnection();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    set_flash('danger', 'ID karyawan tidak valid.', 'Data Tidak Ditemukan');
    header('Location: ' . BASE_URL . 'karyawan/index.php');
    exit;
}

// Handler POST: Toggle Status Akun (Aktif / Nonaktif)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    require_admin_write();
    if (!validate_csrf()) {
        set_flash('danger', 'Token CSRF tidak sah.', 'Operasi Dibatalkan');
    } else {
        $statusBaru = ($_POST['status_tujuan'] === 'aktif') ? 'aktif' : 'nonaktif';
        $stmtBefore = $db->prepare('SELECT id, nik, nama, no_hp, status_akun FROM karyawan WHERE id = :id LIMIT 1');
        $stmtBefore->execute([':id' => $id]);
        $beforeStatus = $stmtBefore->fetch();

        $stmtStatus = $db->prepare("UPDATE karyawan SET status_akun = :st, updated_at = NOW() WHERE id = :id");
        $stmtStatus->execute([':st' => $statusBaru, ':id' => $id]);

        $adminUser = current_user();
        catat_aktivitas($adminUser['id'], $adminUser['nama'], 'admin', 'STATUS_AKUN', "Mengubah status akun ID {$id} menjadi {$statusBaru}");

        if ($beforeStatus && $beforeStatus['status_akun'] !== $statusBaru) {
            $stmtUpdated = $db->prepare('SELECT updated_at FROM karyawan WHERE id = :id LIMIT 1');
            $stmtUpdated->execute([':id' => $id]);
            $waResult = notifikasi_status_akun(
                $beforeStatus['nama'],
                $beforeStatus['nik'],
                $statusBaru,
                wa_template_time($stmtUpdated->fetchColumn() ?: null),
                $beforeStatus['no_hp']
            );
            $waStatus = info_status_wa(
                $waResult,
                ' Notifikasi WhatsApp berhasil dikirim.',
                ' Notifikasi WhatsApp gagal; status tercatat pada log notifikasi.'
            );
        } else {
            $waStatus = '';
        }
        set_flash('success', "Status akun berhasil diubah menjadi " . ucfirst($statusBaru) . ".{$waStatus}", "Status Diperbarui");
        header('Location: ' . BASE_URL . 'karyawan/detail.php?id=' . $id);
        exit;
    }
}

// Ambil data profil karyawan
$stmt = $db->prepare("SELECT * FROM karyawan WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$karyawan = $stmt->fetch();

if (!$karyawan) {
    set_flash('danger', 'Data karyawan tidak ditemukan dalam database.', 'Data Tidak Ada');
    header('Location: ' . BASE_URL . 'karyawan/index.php');
    exit;
}
if (is_demo_user() && $karyawan['nik'] !== DEMO_ACCOUNTS['karyawan']['nik']) {
    set_flash('warning', 'Akun demo hanya dapat melihat data contoh demo.', 'Akses Demo Terbatas');
    header('Location: ' . BASE_URL . 'karyawan/index.php');
    exit;
}

// Statistik Kehadiran Bulan Ini Karyawan Ini
$stmtStats = $db->prepare("
    SELECT 
        COUNT(*) AS total_hadir,
        COUNT(CASE WHEN jam_pulang IS NOT NULL THEN 1 END) AS total_selesai
    FROM absensi
    WHERE id_karyawan = :id AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())
");
$stmtStats->execute([':id' => $id]);
$stats = $stmtStats->fetch();

// 10 Catatan Presensi Terbaru Karyawan Ini
$stmtAbsen = $db->prepare("
    SELECT tanggal, jam_masuk, jam_pulang, status, keterangan, alasan_koreksi
    FROM absensi
    WHERE id_karyawan = :id
    ORDER BY tanggal DESC
    LIMIT 10
");
$stmtAbsen->execute([':id' => $id]);
$riwayatAbsensi = $stmtAbsen->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-slate-900"><i class="bi bi-person-vcard text-primary me-2"></i>Detail Karyawan: <?= e($karyawan['nama']); ?></h4>
        <p class="text-slate-600 small mb-0">Informasi komprehensif profil kepegawaian dan performa kehadiran</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL; ?>karyawan/index.php" class="btn btn-outline-secondary btn-sm fw-medium">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar
        </a>
        <a href="<?= BASE_URL; ?>karyawan/edit.php?id=<?= $karyawan['id']; ?>" class="btn btn-primary btn-sm fw-medium shadow-xs">
            <i class="bi bi-pencil-square me-1"></i>Ubah Profil
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Kartu Informasi Profil -->
    <div class="col-lg-4">
        <div class="card card-profile-premium mb-4">
            <div class="card-profile-header-bar"></div>
            <div class="card-body text-center pt-0 px-4 pb-4">
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar-large">
                        <?= strtoupper(substr($karyawan['nama'], 0, 1)); ?>
                    </div>
                </div>
                <h4 class="profile-name"><?= e($karyawan['nama']); ?></h4>
                <div class="profile-role-title"><?= e($karyawan['jabatan']); ?></div>

                <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                    <span class="badge badge-nik-contrast">
                        <i class="bi bi-card-heading me-1 text-primary"></i>NIK: <?= e($karyawan['nik']); ?>
                    </span>
                    <?php if ($karyawan['status_akun'] === 'aktif'): ?>
                        <span class="badge badge-status-selesai">
                            <i class="bi bi-check-circle-fill me-1"></i>Akun Aktif
                        </span>
                    <?php else: ?>
                        <span class="badge badge-status-belum">
                            <i class="bi bi-slash-circle me-1"></i>Akun Nonaktif
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Baris Informasi dengan Kontras Tinggi -->
                <?php if ($karyawan['no_hp'] !== ''): ?>
                    <div class="profile-info-row">
                        <span class="profile-info-label"><i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp:</span>
                        <a href="https://wa.me/<?= e(normalize_phone($karyawan['no_hp'])); ?>" target="_blank" class="profile-info-value font-monospace text-success text-decoration-none">
                            <?= e(format_phone_display($karyawan['no_hp'])); ?> <i class="bi bi-box-arrow-up-right small"></i>
                        </a>
                    </div>
                <?php endif; ?>
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="bi bi-envelope me-1 text-primary"></i>Email:</span>
                    <span class="profile-info-value"><?= e($karyawan['email']); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="bi bi-person-badge me-1 text-primary"></i>Hak Akses:</span>
                    <span class="profile-info-value text-uppercase"><?= e($karyawan['role']); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="bi bi-calendar-event me-1 text-primary"></i>Terdaftar:</span>
                    <span class="profile-info-value"><?= format_tanggal_indo($karyawan['created_at']); ?></span>
                </div>

                <?php if (!is_demo_user()): ?>
                <form action="" method="POST" class="pt-3 mt-3 border-top border-slate-200">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <?php if ($karyawan['status_akun'] === 'aktif'): ?>
                        <input type="hidden" name="status_tujuan" value="nonaktif">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 fw-semibold" onclick="return confirm('Apakah Anda yakin ingin menonaktifkan akun karyawan ini? Karyawan tidak akan dapat login.');">
                            <i class="bi bi-person-x-fill me-1"></i> Nonaktifkan Akun
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="status_tujuan" value="aktif">
                        <button type="submit" class="btn btn-outline-success btn-sm w-100 fw-semibold" onclick="return confirm('Aktifkan kembali akun karyawan ini?');">
                            <i class="bi bi-person-check-fill me-1"></i> Aktifkan Akun
                        </button>
                    <?php endif; ?>
                </form>
                <?php else: ?>
                    <div class="alert alert-info small mt-3 mb-0">Akun demo hanya dapat melihat data contoh.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ringkasan Presensi Bulan Ini -->
        <div class="card card-custom p-4">
            <h6 class="fw-bold text-slate-900 mb-3"><i class="bi bi-calendar-check text-primary me-2"></i>Presensi Bulan <?= date('F Y'); ?></h6>
            <div class="row g-2 text-center">
                <div class="col-6">
                    <div class="p-3 border border-slate-200 rounded-3 bg-slate-50">
                        <span class="text-slate-600 fw-semibold d-block small mb-1">Total Hadir</span>
                        <span class="fs-3 fw-bold text-primary font-monospace"><?= (int)($stats['total_hadir'] ?? 0); ?></span>
                        <small class="text-slate-500 d-block" style="font-size: 0.72rem;">Hari Kerja</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 border border-slate-200 rounded-3 bg-slate-50">
                        <span class="text-slate-600 fw-semibold d-block small mb-1">Selesai Pulang</span>
                        <span class="fs-3 fw-bold text-emerald-600 font-monospace"><?= (int)($stats['total_selesai'] ?? 0); ?></span>
                        <small class="text-slate-500 d-block" style="font-size: 0.72rem;">Lengkap</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: 10 Catatan Presensi Terbaru -->
    <div class="col-lg-8">
        <div class="card card-custom h-100">
            <div class="card-header bg-white py-3 border-bottom border-slate-200 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-clock-history text-primary me-2"></i>10 Riwayat Presensi Terakhir</h6>
                <span class="badge badge-nik-contrast">Log Kehadiran</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th class="text-center">Jam Masuk</th>
                                <th class="text-center">Jam Pulang</th>
                                <th class="text-center">Status</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($riwayatAbsensi)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-slate-500">
                                        <i class="bi bi-calendar-x fs-2 d-block mb-1 text-slate-400"></i>
                                        Belum ada riwayat absensi tercatat untuk karyawan ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($riwayatAbsensi as $r): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-slate-900"><?= format_tanggal_indo($r['tanggal']); ?></div>
                                            <small class="text-slate-500 font-monospace"><?= date('d/m/Y', strtotime($r['tanggal'])); ?></small>
                                        </td>
                                        <td class="text-center font-monospace fw-semibold text-slate-800">
                                            <?= !empty($r['jam_masuk']) ? date('H:i', strtotime($r['jam_masuk'])) . ' WIB' : '-'; ?>
                                        </td>
                                        <td class="text-center font-monospace fw-semibold text-slate-800">
                                            <?= !empty($r['jam_pulang']) ? date('H:i', strtotime($r['jam_pulang'])) . ' WIB' : '-'; ?>
                                        </td>
                                        <td class="text-center">
                                            <?= badge_status_absensi($r['status']); ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['alasan_koreksi'])): ?>
                                                <span class="badge bg-warning-subtle text-amber-800 border border-warning" title="<?= e($r['alasan_koreksi']); ?>">
                                                    <i class="bi bi-info-circle me-1"></i>Dikoreksi
                                                </span>
                                            <?php else: ?>
                                                <span class="small text-slate-600"><?= e($r['keterangan'] ?? '-'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
