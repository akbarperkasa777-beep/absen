<?php
/**
 * Dashboard Khusus Karyawan - Premium UI (High Contrast & Sharp Readability)
 * Portal Presensi Karyawan
 */

$pageTitle = 'Dashboard Karyawan';
$activeMenu = 'dashboard_karyawan';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user = current_user();
$karyawanId = (int)$user['id'];
$db = getDBConnection();

$today = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');

// 1. Status Absensi Hari Ini
$stmtToday = $db->prepare("
    SELECT id, jam_masuk, jam_pulang, status, status_masuk, menit_terlambat, status_pulang, keterangan
    FROM absensi
    WHERE id_karyawan = :id AND tanggal = :today
    LIMIT 1
");
$stmtToday->execute([':id' => $karyawanId, ':today' => $today]);
$absenHariIni = $stmtToday->fetch();

$sudahMasuk = !empty($absenHariIni['jam_masuk']);
$sudahPulang = !empty($absenHariIni['jam_pulang']);
$statusTeks = $absenHariIni['status'] ?? 'BELUM ABSEN';
$stmtTodayRequest = $db->prepare("
    SELECT status, jam_pulang_diajukan, alasan_penolakan
    FROM izin_pulang_awal
    WHERE id_karyawan = :id AND tanggal_izin = :tanggal
    ORDER BY id DESC
    LIMIT 1
");
$stmtTodayRequest->execute([':id' => $karyawanId, ':tanggal' => $today]);
$todayEarlyLeaveRequest = $stmtTodayRequest->fetch();

// 2. Statistik Presensi Bulan Ini
$stmtMonth = $db->prepare("
    SELECT 
        COUNT(*) AS total_hadir,
        COUNT(CASE WHEN jam_pulang IS NOT NULL THEN 1 END) AS total_selesai,
        COUNT(CASE WHEN menit_terlambat > 0 THEN 1 END) AS total_terlambat,
        COALESCE(SUM(menit_terlambat), 0) AS total_menit_terlambat
    FROM absensi
    WHERE id_karyawan = :id 
      AND MONTH(tanggal) = :bulan 
      AND YEAR(tanggal) = :tahun
");
$stmtMonth->execute([
    ':id'    => $karyawanId,
    ':bulan' => $currentMonth,
    ':tahun' => $currentYear
]);
$stats = $stmtMonth->fetch();
$totalHadirBulanIni = (int)($stats['total_hadir'] ?? 0);
$totalSelesaiBulanIni = (int)($stats['total_selesai'] ?? 0);

// Hitung hari kerja terlalui bulan ini (Senin - Jumat hingga hari ini)
$startDay = new DateTime(date('Y-m-01'));
$endDay = new DateTime($today);
$workingDaysPassed = 0;
while ($startDay <= $endDay) {
    if ((int)$startDay->format('N') <= 5) {
        $workingDaysPassed++;
    }
    $startDay->modify('+1 day');
}
$workingDaysPassed = max(1, $workingDaysPassed);
$persentaseKehadiran = min(100, round(($totalHadirBulanIni / $workingDaysPassed) * 100));

// 3. 5 Riwayat Presensi Terakhir
$stmtRecent = $db->prepare("
    SELECT tanggal, jam_masuk, jam_pulang, status, status_masuk, menit_terlambat, status_pulang, keterangan
    FROM absensi
    WHERE id_karyawan = :id
    ORDER BY tanggal DESC
    LIMIT 5
");
$stmtRecent->execute([':id' => $karyawanId]);
$recentAbsensi = $stmtRecent->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (is_demo_user()): ?>
    <div class="alert alert-info border-0 border-start border-4 border-info bg-white shadow-sm">
        <i class="bi bi-info-circle-fill me-2"></i>Data contoh Karyawan Demo. Akun ini hanya-baca dan tidak memengaruhi data karyawan lain.
    </div>
<?php endif; ?>

<!-- Header Sapaan Pengguna - High Contrast Hero Card -->
<div class="dashboard-hero-card mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="profile-avatar-large flex-shrink-0" style="width: 64px; height: 64px; font-size: 1.65rem; border: 3px solid #ffffff;">
                <?= strtoupper(substr($user['nama'], 0, 1)); ?>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <span class="hero-greeting-pill">
                        <i class="bi bi-stars me-1 text-warning"></i> <?= greeting_waktu(); ?>, Rekan Kerja!
                    </span>
                    <span class="hero-badge-meta">NIK: <?= e($user['nik']); ?></span>
                </div>
                <h2 class="fw-bold text-white mb-1" style="letter-spacing: -0.025em;"><?= e($user['nama']); ?></h2>
                <div class="hero-subtitle d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-white text-primary fw-bold px-2 py-1 shadow-xs"><?= e($user['jabatan']); ?></span>
                    <span class="text-slate-200 small"><i class="bi bi-shield-check text-info me-1"></i>Portal Presensi Karyawan</span>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="<?= BASE_URL; ?>absensi/index.php" class="btn btn-hero-action px-3 py-2 d-flex align-items-center gap-2">
                <i class="bi bi-fingerprint fs-5 text-primary"></i>
                <span>Presensi Hari Ini</span>
            </a>
            <a href="<?= BASE_URL; ?>karyawan/profil.php" class="btn btn-hero-outline px-3 py-2 d-flex align-items-center gap-2">
                <i class="bi bi-person-gear"></i>
                <span>Profil Saya</span>
            </a>
        </div>
    </div>
</div>

<!-- 4 KARTU STATISTIK BULANAN - HIGH CONTRAST & SHARP BORDER -->
<div class="row g-3 mb-4">
    <!-- Status Hari Ini -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon primary">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Status Hari Ini</div>
                <div class="mt-1">
                    <?= badge_status_absensi($statusTeks); ?>
                </div>
                <div class="stat-hint mt-2">
                    <?= $sudahMasuk ? 'Masuk: ' . date('H:i', strtotime($absenHariIni['jam_masuk'])) . ' WIB' : 'Belum absen masuk'; ?>
                </div>
                <?php if ($sudahMasuk): ?>
                    <div class="stat-hint">
                        <?= (int)($absenHariIni['menit_terlambat'] ?? 0) > 0
                            ? 'Terlambat ' . (int)$absenHariIni['menit_terlambat'] . ' menit'
                            : 'Tepat waktu (jadwal ' . e(ATTENDANCE_START_TIME) . ')'; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kehadiran Bulan Ini -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon success">
                <i class="bi bi-calendar-check-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Kehadiran Bulan Ini</div>
                <div class="stat-number text-success"><?= $totalHadirBulanIni; ?> <span class="fs-6 text-slate-600 fw-semibold">Hari</span></div>
                <div class="stat-hint">Periode <?= date('F Y'); ?></div>
            </div>
        </div>
    </div>

    <!-- Presensi Selesai -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon cyan">
                <i class="bi bi-check2-all"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Absen Pulang Tuntas</div>
                <div class="stat-number text-cyan-600"><?= $totalSelesaiBulanIni; ?> <span class="fs-6 text-slate-600 fw-semibold">Kali</span></div>
                <div class="stat-hint">Masuk & pulang lengkap</div>
            </div>
        </div>
    </div>

    <!-- Persentase Kehadiran -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon warning">
                <i class="bi bi-pie-chart-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Tingkat Kehadiran</div>
                <div class="stat-number text-slate-900"><?= $persentaseKehadiran; ?>%</div>
                <div class="stat-hint">Dari <?= $workingDaysPassed; ?> hari kerja</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Presensi Hari Ini & Aksi Cepat -->
    <div class="col-lg-5">
        <div class="card card-custom h-100">
            <div class="card-header bg-white py-3 border-bottom border-slate-200">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-stopwatch text-primary me-2"></i>Aksi Presensi Cepat Hari Ini</h6>
            </div>
            <div class="card-body p-4">
                <div class="text-center p-3 bg-white border border-slate-300 rounded-3 mb-4 shadow-xs">
                    <span class="text-slate-600 small fw-semibold d-block mb-1">Waktu Server Sekarang</span>
                    <span class="fs-3 fw-bold text-slate-900 font-monospace" id="dashboardClock"><?= date('H:i:s'); ?> WIB</span>
                    <div class="text-slate-700 small fw-medium mt-1"><i class="bi bi-calendar3 text-primary me-1"></i><?= format_tanggal_indo($today); ?></div>
                </div>

                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <div class="p-3 text-center border rounded-3 <?= $sudahMasuk ? 'bg-primary-subtle border-primary-subtle' : 'bg-slate-50 border-slate-300'; ?>">
                            <small class="text-slate-700 fw-bold d-block mb-1">Jam Masuk</small>
                            <span class="fw-bold font-monospace text-slate-900 fs-5">
                                <?= $sudahMasuk ? date('H:i', strtotime($absenHariIni['jam_masuk'])) . ' WIB' : '--:--'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 text-center border rounded-3 <?= $sudahPulang ? 'bg-success-subtle border-success-subtle' : 'bg-slate-50 border-slate-300'; ?>">
                            <small class="text-slate-700 fw-bold d-block mb-1">Jam Pulang</small>
                            <span class="fw-bold font-monospace text-slate-900 fs-5">
                                <?= $sudahPulang ? date('H:i', strtotime($absenHariIni['jam_pulang'])) . ' WIB' : '--:--'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="small text-muted mb-3">
                    Jadwal masuk <?= e(ATTENDANCE_START_TIME); ?> WIB · Absen pulang normal mulai <?= e(EARLIEST_CHECKOUT_TIME); ?> WIB.
                    <?php if ($sudahMasuk): ?>
                        <strong class="<?= (int)$absenHariIni['menit_terlambat'] > 0 ? 'text-warning' : 'text-success'; ?>">
                            <?= (int)$absenHariIni['menit_terlambat'] > 0 ? 'Terlambat ' . (int)$absenHariIni['menit_terlambat'] . ' menit' : 'Tepat waktu'; ?>
                        </strong>
                    <?php endif; ?>
                </div>
                <?php if ($todayEarlyLeaveRequest): ?>
                    <div class="alert alert-<?= $todayEarlyLeaveRequest['status'] === 'approved' ? 'success' : ($todayEarlyLeaveRequest['status'] === 'rejected' ? 'danger' : 'warning'); ?> small">
                        Pengajuan izin pulang awal: <strong><?= e(ucfirst($todayEarlyLeaveRequest['status'])); ?></strong>,
                        pukul <?= date('H:i', strtotime($todayEarlyLeaveRequest['jam_pulang_diajukan'])); ?> WIB.
                        <?php if (!empty($todayEarlyLeaveRequest['alasan_penolakan'])): ?>
                            Alasan penolakan: <?= e($todayEarlyLeaveRequest['alasan_penolakan']); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL; ?>absensi/index.php" class="btn btn-primary py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-fingerprint fs-5"></i> Buka Layar Presensi Mandiri
                    </a>
                    <a href="<?= BASE_URL; ?>karyawan/riwayat.php" class="btn btn-outline-secondary py-2 fw-semibold text-slate-700">
                        <i class="bi bi-clock-history me-1"></i> Lihat Seluruh Riwayat Absensi
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 5 Riwayat Presensi Terbaru -->
    <div class="col-lg-7">
        <div class="card card-custom h-100">
            <div class="card-header bg-white py-3 border-bottom border-slate-200 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-journal-text text-primary me-2"></i>Riwayat Kehadiran Terbaru</h6>
                <a href="<?= BASE_URL; ?>karyawan/riwayat.php" class="btn btn-sm btn-outline-primary fw-semibold">Lihat Semua</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th class="text-center">Jam Masuk</th>
                                <th class="text-center">Jam Pulang</th>
                                <th class="text-center">Terlambat</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentAbsensi)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-slate-600">
                                        <i class="bi bi-calendar-x fs-2 text-slate-400 d-block mb-2"></i>
                                        Belum ada riwayat presensi tercatat bulan ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentAbsensi as $r): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-slate-900"><?= format_tanggal_indo($r['tanggal']); ?></div>
                                            <small class="text-slate-600 font-monospace"><?= date('d/m/Y', strtotime($r['tanggal'])); ?></small>
                                        </td>
                                        <td class="text-center font-monospace fw-bold text-slate-800">
                                            <?= !empty($r['jam_masuk']) ? date('H:i', strtotime($r['jam_masuk'])) . ' WIB' : '-'; ?>
                                        </td>
                                        <td class="text-center font-monospace fw-bold text-slate-800">
                                            <?= !empty($r['jam_pulang']) ? date('H:i', strtotime($r['jam_pulang'])) . ' WIB' : '-'; ?>
                                        </td>
                                        <td class="text-center">
                                            <?= (int)($r['menit_terlambat'] ?? 0) > 0 ? (int)$r['menit_terlambat'] . ' menit' : 'Tepat waktu'; ?>
                                        </td>
                                        <td class="text-center">
                                            <?= badge_status_absensi($r['status']); ?>
                                            <?php if (($r['status_pulang'] ?? '') === 'PULANG_LEBIH_AWAL_IZIN'): ?><div class="small text-success">Pulang awal (izin)</div><?php endif; ?>
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

<script>
setInterval(() => {
    const el = document.getElementById('dashboardClock');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }) + ' WIB';
    }
}, 1000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
