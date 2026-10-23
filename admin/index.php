<?php
/**
 * Dashboard Administrator Tingkat Lanjut - Premium UI
 * Menampilkan statistik presensi real-time dari database, grafik Chart.js 7 hari,
 * ringkasan absensi hari ini, dan widget Fonnte Gateway
 */

$pageTitle = 'Dashboard Administrator';
$activeMenu = 'dashboard';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = getDBConnection();
$today = date('Y-m-d');
$isDemo = is_demo_user();
$demoNik = DEMO_ACCOUNTS['karyawan']['nik'];

// 1. Total Karyawan Aktif
$stmtTotal = $db->prepare("
    SELECT COUNT(*) AS total
    FROM karyawan
    WHERE role = 'karyawan' AND status_akun = 'aktif'
    " . ($isDemo ? 'AND nik = :demo_nik' : ''));
if ($isDemo) {
    $stmtTotal->execute([':demo_nik' => $demoNik]);
} else {
    $stmtTotal->execute();
}
$totalKaryawan = (int)($stmtTotal->fetch()['total'] ?? 0);

// 2. Sudah Absen Masuk Hari Ini
$stmtMasuk = $db->prepare("
    SELECT COUNT(DISTINCT id_karyawan) AS total
    FROM absensi
    WHERE tanggal = :today AND jam_masuk IS NOT NULL
    " . ($isDemo ? 'AND id_karyawan = (SELECT id FROM karyawan WHERE nik = :demo_nik)' : '') . "
");
$stmtMasuk->execute($isDemo ? [':today' => $today, ':demo_nik' => $demoNik] : [':today' => $today]);
$sudahMasukHariIni = (int)($stmtMasuk->fetch()['total'] ?? 0);

// 3. Sudah Absen Pulang Hari Ini
$stmtPulang = $db->prepare("
    SELECT COUNT(DISTINCT id_karyawan) AS total
    FROM absensi
    WHERE tanggal = :today AND jam_pulang IS NOT NULL
    " . ($isDemo ? 'AND id_karyawan = (SELECT id FROM karyawan WHERE nik = :demo_nik)' : '') . "
");
$stmtPulang->execute($isDemo ? [':today' => $today, ':demo_nik' => $demoNik] : [':today' => $today]);
$sudahPulangHariIni = (int)($stmtPulang->fetch()['total'] ?? 0);

// 3a. Ringkasan keterlambatan dan pengajuan izin pulang awal.
$stmtLateStats = $db->prepare("
    SELECT COUNT(CASE WHEN menit_terlambat > 0 THEN 1 END) AS terlambat,
           COALESCE(SUM(menit_terlambat), 0) AS total_menit
    FROM absensi
    WHERE tanggal = :today AND jam_masuk IS NOT NULL
    " . ($isDemo ? 'AND id_karyawan = (SELECT id FROM karyawan WHERE nik = :demo_nik)' : '') . "
");
$stmtLateStats->execute($isDemo ? [':today' => $today, ':demo_nik' => $demoNik] : [':today' => $today]);
$lateStats = $stmtLateStats->fetch();

$stmtLeaveStats = $db->prepare("
    SELECT COUNT(CASE WHEN i.status = 'pending' THEN 1 END) AS pending,
           COUNT(CASE WHEN i.status = 'approved' THEN 1 END) AS approved,
           COUNT(CASE WHEN i.status = 'rejected' THEN 1 END) AS rejected
    FROM izin_pulang_awal i
    JOIN karyawan k ON k.id = i.id_karyawan
    WHERE i.tanggal_izin = :today " . ($isDemo ? 'AND k.nik = :demo_nik' : '') . "
");
$stmtLeaveStats->execute($isDemo ? [':today' => $today, ':demo_nik' => $demoNik] : [':today' => $today]);
$leaveStats = $stmtLeaveStats->fetch();

// 4. Belum Absen Hari Ini
$belumAbsenHariIni = max(0, $totalKaryawan - $sudahMasukHariIni);

// Persentase Kehadiran Hari Ini
$persenHadirHariIni = ($totalKaryawan > 0) ? round(($sudahMasukHariIni / $totalKaryawan) * 100) : 0;

// 5. Data Grafik Kehadiran 7 Hari Terakhir (Data Riil Database)
$trendLabels = [];
$trendDataMasuk = [];
$trendDataPulang = [];

for ($i = 6; $i >= 0; $i--) {
    $tglLoop = date('Y-m-d', strtotime("-{$i} days"));
    $trendLabels[] = date('d M', strtotime($tglLoop));

    $stmtL = $db->prepare("
        SELECT 
            COUNT(CASE WHEN jam_masuk IS NOT NULL THEN 1 END) AS masuk,
            COUNT(CASE WHEN jam_pulang IS NOT NULL THEN 1 END) AS pulang
        FROM absensi
        WHERE tanggal = :tgl
        " . ($isDemo ? 'AND id_karyawan = (SELECT id FROM karyawan WHERE nik = :demo_nik)' : '') . "
    ");
    $stmtL->execute($isDemo ? [':tgl' => $tglLoop, ':demo_nik' => $demoNik] : [':tgl' => $tglLoop]);
    $rowL = $stmtL->fetch();
    $trendDataMasuk[] = (int)($rowL['masuk'] ?? 0);
    $trendDataPulang[] = (int)($rowL['pulang'] ?? 0);
}

// 6. Daftar Presensi Hari Ini
$stmtTodayAbsensi = $db->prepare("
    SELECT a.id, a.tanggal, a.jam_masuk, a.jam_pulang, a.status, a.status_masuk, a.menit_terlambat, a.status_pulang, a.keterangan,
           k.id AS karyawan_id, k.nik, k.nama, k.jabatan, k.no_hp
    FROM absensi a
    JOIN karyawan k ON a.id_karyawan = k.id
    WHERE a.tanggal = :today
    " . ($isDemo ? 'AND k.nik = :demo_nik' : '') . "
    ORDER BY a.created_at DESC
    LIMIT 6
");
$stmtTodayAbsensi->execute($isDemo ? [':today' => $today, ':demo_nik' => $demoNik] : [':today' => $today]);
$recentAbsensi = $stmtTodayAbsensi->fetchAll();

// 7. Karyawan Baru Terdaftar
$stmtNewUsers = $db->prepare("
    SELECT id, nik, nama, jabatan, email, no_hp, created_at, status_akun
    FROM karyawan
    WHERE role = 'karyawan'
    " . ($isDemo ? 'AND nik = :demo_nik' : '') . "
    ORDER BY id DESC
    LIMIT 4
");
$stmtNewUsers->execute($isDemo ? [':demo_nik' => $demoNik] : []);
$newUsers = $stmtNewUsers->fetchAll();

// 8. Hitung Statistik Notifikasi WhatsApp Fonnte
if ($isDemo) {
    $waStats = ['total_notif' => 0, 'sukses' => 0, 'gagal' => 0, 'simulasi' => 0];
} else {
    $stmtWaStats = $db->query("
        SELECT
            COUNT(*) AS total_notif,
            COUNT(CASE WHEN status = 'SUCCESS' THEN 1 END) AS sukses,
            COUNT(CASE WHEN status IN ('FAILED', 'INVALID_TARGET') THEN 1 END) AS gagal,
            COUNT(CASE WHEN status = 'SIMULATED' THEN 1 END) AS simulasi
        FROM log_notifikasi
    ");
    $waStats = $stmtWaStats->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-slate-900"><i class="bi bi-grid-1x2-fill text-primary me-2"></i>Dashboard Administrator</h4>
        <p class="text-slate-600 small mb-0">Pemantauan data kehadiran per <strong><?= format_tanggal_indo($today); ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL; ?>admin/laporan.php" class="btn btn-outline-primary btn-sm fw-medium">
            <i class="bi bi-file-earmark-bar-graph me-1"></i>Laporan Presensi
        </a>
        <?php if (!$isDemo): ?>
            <a href="<?= BASE_URL; ?>karyawan/tambah.php" class="btn btn-primary btn-sm fw-medium shadow-xs">
                <i class="bi bi-person-plus-fill me-1"></i>Tambah Karyawan
            </a>
        <?php endif; ?>
    </div>

</div>

<?php if ($isDemo): ?>
    <div class="alert alert-info border-0 border-start border-4 border-info bg-white shadow-sm">
        <i class="bi bi-info-circle-fill me-2"></i><strong>Mode Admin Demo.</strong>
        Data yang ditampilkan hanya milik akun contoh demo. Seluruh fitur perubahan data dinonaktifkan.
    </div>
<?php endif; ?>

<!-- 4 KARTU STATISTIK UTAMA -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon primary">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Karyawan</div>
                <div class="stat-number"><?= $totalKaryawan; ?></div>
                <div class="stat-hint"><i class="bi bi-check-circle-fill text-emerald-600 me-1"></i>Status akun aktif</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon cyan">
                <i class="bi bi-box-arrow-in-right"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Hadir Hari Ini</div>
                <div class="stat-number text-primary"><?= $sudahMasukHariIni; ?></div>
                <div class="stat-hint"><strong class="text-slate-800"><?= $persenHadirHariIni; ?>%</strong> dari total karyawan</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon warning">
                <i class="bi bi-person-exclamation"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Belum Presensi</div>
                <div class="stat-number text-amber-600"><?= $belumAbsenHariIni; ?></div>
                <div class="stat-hint">Belum tercatat hari ini</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon success">
                <i class="bi bi-check2-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Sudah Pulang</div>
                <div class="stat-number text-emerald-600"><?= $sudahPulangHariIni; ?></div>
                <div class="stat-hint">Presensi lengkap</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card-stat"><div class="stat-content"><div class="stat-label">Terlambat Hari Ini</div><div class="stat-number text-warning"><?= (int)($lateStats['terlambat'] ?? 0); ?></div><div class="stat-hint"><?= (int)($lateStats['total_menit'] ?? 0); ?> menit akumulasi</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-stat"><div class="stat-content"><div class="stat-label">Izin Menunggu</div><div class="stat-number text-warning"><?= (int)($leaveStats['pending'] ?? 0); ?></div><div class="stat-hint"><a href="<?= BASE_URL; ?>admin/izin_pulang.php">Buka persetujuan izin</a></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-stat"><div class="stat-content"><div class="stat-label">Izin Disetujui</div><div class="stat-number text-success"><?= (int)($leaveStats['approved'] ?? 0); ?></div><div class="stat-hint">Hari ini</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-stat"><div class="stat-content"><div class="stat-label">Izin Ditolak</div><div class="stat-number text-danger"><?= (int)($leaveStats['rejected'] ?? 0); ?></div><div class="stat-hint">Hari ini</div></div></div></div>
</div>

<!-- GRAFIK TREN KEHADIRAN 7 HARI & STATUS GATEWAY FONNTE -->
<div class="row g-4 mb-4">
    <!-- Grafik Chart.js -->
    <div class="col-lg-8">
        <div class="card card-custom h-100">
            <div class="card-header bg-white py-3 border-bottom border-slate-200 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-graph-up text-primary me-2"></i>Tren Kehadiran 7 Hari Terakhir</h6>
                <span class="badge badge-nik-contrast"><?= $isDemo ? 'Database Demo' : 'Database Live'; ?></span>
            </div>
            <div class="card-body p-4">
                <div style="height: 260px;">
                    <canvas id="chartTrenKehadiran"></canvas>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$isDemo): ?>
    <!-- Status WhatsApp Gateway Fonnte -->
    <div class="col-lg-4">
        <div class="card card-custom h-100">
            <div class="card-header bg-white py-3 border-bottom border-slate-200 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-whatsapp text-success me-2"></i>Gateway Fonnte WA</h6>
                <?php if (!$isDemo): ?>
                    <a href="<?= BASE_URL; ?>admin/whatsapp.php" class="btn btn-sm btn-outline-secondary fw-medium">Detail</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-3 mb-3">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success p-2 fs-4">
                        <i class="bi bi-broadcast"></i>
                    </div>
                    <div>
                        <div class="small fw-bold text-slate-900">Status Gateway:</div>
                        <?php if (empty(FONNTE_TOKEN) || in_array(FONNTE_TOKEN, ['ISI_TOKEN_FONNTE_ANDA', 'FONNTE_TOKEN_ANDA'])): ?>
                            <span class="badge badge-status-belum">Mode Simulasi Aman (.env)</span>
                        <?php else: ?>
                            <span class="badge badge-status-selesai">Terkoneksi API Resmi</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="p-2 border border-slate-200 rounded-3 bg-slate-50">
                            <span class="text-slate-600 d-block fw-semibold" style="font-size: 0.72rem;">TOTAL</span>
                            <strong class="text-slate-900 fs-5 font-monospace"><?= (int)($waStats['total_notif'] ?? 0); ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border border-slate-200 rounded-3 bg-slate-50">
                            <span class="text-slate-600 d-block fw-semibold" style="font-size: 0.72rem;">SUKSES</span>
                            <strong class="text-emerald-600 fs-5 font-monospace"><?= (int)($waStats['sukses'] ?? 0); ?></strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 border border-slate-200 rounded-3 bg-slate-50">
                            <span class="text-slate-600 d-block fw-semibold" style="font-size: 0.72rem;">SIMULASI</span>
                            <strong class="text-primary fs-5 font-monospace"><?= (int)($waStats['simulasi'] ?? 0); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="d-grid">
                    <?php if (!$isDemo): ?>
                        <a href="<?= BASE_URL; ?>admin/whatsapp.php" class="btn btn-outline-success btn-sm fw-semibold">
                            <i class="bi bi-send-check me-1"></i> Buka Manajemen Gateway WA
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- TABEL PRESENSI HARI INI & KARYAWAN BARU -->
<div class="row g-4">
    <!-- Presensi Hari Ini -->
    <div class="col-lg-8">
        <div class="card card-custom h-100">
            <div class="card-header bg-white py-3 border-bottom border-slate-200 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-calendar2-check text-primary me-2"></i>Presensi Karyawan Hari Ini</h6>
                <a href="<?= BASE_URL; ?>admin/absensi.php" class="btn btn-sm btn-outline-primary fw-medium">Lihat Selengkapnya</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th class="text-center">Jam Masuk</th>
                                <th class="text-center">Status Masuk</th>
                                <th class="text-center">Jam Pulang</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentAbsensi)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-slate-500">
                                        <i class="bi bi-calendar-x fs-2 d-block mb-1 text-slate-400"></i>
                                        Belum ada presensi tercatat untuk hari ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentAbsensi as $ra): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-slate-900"><?= e($ra['nama']); ?></div>
                                            <small class="text-slate-500 font-monospace"><?= e($ra['nik']); ?> &bull; <?= e($ra['jabatan']); ?></small>
                                        </td>
                                        <td class="text-center font-monospace fw-semibold text-slate-800">
                                            <?= !empty($ra['jam_masuk']) ? date('H:i', strtotime($ra['jam_masuk'])) . ' WIB' : '-'; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ((int)$ra['menit_terlambat'] > 0): ?>
                                                <span class="badge bg-warning-subtle text-warning">Terlambat <?= (int)$ra['menit_terlambat']; ?> mnt</span>
                                            <?php elseif (!empty($ra['jam_masuk'])): ?>
                                                <span class="badge bg-success-subtle text-success">Tepat Waktu</span>
                                            <?php else: ?>-<?php endif; ?>
                                        </td>
                                        <td class="text-center font-monospace fw-semibold text-slate-800">
                                            <?= !empty($ra['jam_pulang']) ? date('H:i', strtotime($ra['jam_pulang'])) . ' WIB' : '-'; ?>
                                        </td>
                                        <td class="text-center">
                                            <?= badge_status_absensi($ra['status']); ?>
                                            <?php if (($ra['status_pulang'] ?? '') === 'PULANG_LEBIH_AWAL_IZIN'): ?><div class="small text-success">Pulang awal (izin)</div><?php endif; ?>
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

    <!-- Karyawan Baru Terdaftar -->
    <div class="col-lg-4">
        <div class="card card-custom h-100">
            <div class="card-header bg-white py-3 border-bottom border-slate-200 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-person-plus text-primary me-2"></i>Karyawan Baru</h6>
                <a href="<?= BASE_URL; ?>karyawan/index.php" class="btn btn-sm btn-outline-secondary fw-medium">Kelola</a>
            </div>
            <div class="card-body p-3">
                <?php if (empty($newUsers)): ?>
                    <div class="text-center py-4 text-slate-500 small">Belum ada karyawan terdaftar.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($newUsers as $nu): ?>
                            <div class="list-group-item px-0 py-2 border-bottom border-slate-200">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar-bubble" style="width: 34px; height: 34px; font-size: 0.82rem;">
                                            <?= strtoupper(substr($nu['nama'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <a href="<?= BASE_URL; ?>karyawan/detail.php?id=<?= $nu['id']; ?>" class="fw-semibold text-slate-900 text-decoration-none small d-block">
                                                <?= e($nu['nama']); ?>
                                            </a>
                                            <span class="text-slate-500 font-monospace" style="font-size: 0.72rem;"><?= e($nu['nik']); ?> &bull; <?= e($nu['jabatan']); ?></span>
                                        </div>
                                    </div>
                                    <span class="badge badge-nik-contrast small"><?= date('d/m', strtotime($nu['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('chartTrenKehadiran');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels); ?>,
                datasets: [
                    {
                        label: 'Hadir Masuk',
                        data: <?= json_encode($trendDataMasuk); ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointBackgroundColor: '#2563eb'
                    },
                    {
                        label: 'Selesai Pulang',
                        data: <?= json_encode($trendDataPulang); ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointBackgroundColor: '#10b981'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { family: 'Inter', size: 12 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
