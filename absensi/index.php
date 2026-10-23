<?php
/**
 * Halaman Presensi Mandiri Karyawan - Premium UI (High Contrast & Sharp Readability)
 * Portal Presensi Karyawan
 */

$pageTitle = 'Portal Presensi Karyawan';
$activeMenu = 'absensi_mandiri';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/attendance.php';

require_login();

$user = current_user();
$karyawanId = (int)$user['id'];
$today = date('Y-m-d');
$nowTime = date('H:i:s');
$db = getDBConnection();

// Ambil catatan absensi hari ini milik karyawan yang login
$stmtToday = $db->prepare("
    SELECT id, id_karyawan, tanggal, jam_masuk, jam_pulang, status, status_masuk, menit_terlambat, status_pulang, keterangan, created_at
    FROM absensi
    WHERE id_karyawan = :id_karyawan AND tanggal = :tanggal
    LIMIT 1
");
$stmtToday->execute([
    ':id_karyawan' => $karyawanId,
    ':tanggal'     => $today
]);
$absenHariIni = $stmtToday->fetch();

$stmtEarlyLeave = $db->prepare("
    SELECT id, jam_pulang_diajukan, kategori_alasan, status, alasan_penolakan
    FROM izin_pulang_awal
    WHERE id_karyawan = :id AND tanggal_izin = :tanggal
    ORDER BY id DESC
    LIMIT 1
");
$stmtEarlyLeave->execute([':id' => $karyawanId, ':tanggal' => $today]);
$earlyLeaveRequest = $stmtEarlyLeave->fetch();
$stmtRequestHistory = $db->prepare("
    SELECT tanggal_izin, jam_pulang_diajukan, kategori_alasan, keterangan, status, alasan_penolakan, created_at
    FROM izin_pulang_awal
    WHERE id_karyawan = :id
    ORDER BY created_at DESC
    LIMIT 10
");
$stmtRequestHistory->execute([':id' => $karyawanId]);
$earlyLeaveHistory = $stmtRequestHistory->fetchAll();

$sudahMasuk = false;
$sudahPulang = false;
$statusTeks = 'BELUM ABSEN';

if ($absenHariIni) {
    if (!empty($absenHariIni['jam_masuk'])) {
        $sudahMasuk = true;
        $statusTeks = 'SUDAH ABSEN MASUK';
    }
    if (!empty($absenHariIni['jam_pulang'])) {
        $sudahPulang = true;
        $statusTeks = 'SELESAI';
    }
}
$normalCheckoutOpen = attendance_time_reached($nowTime, EARLIEST_CHECKOUT_TIME);
$approvedEarlyLeaveReady = $earlyLeaveRequest
    && $earlyLeaveRequest['status'] === 'approved'
    && attendance_time_reached($nowTime, substr($earlyLeaveRequest['jam_pulang_diajukan'], 0, 5));
$canCheckout = $sudahMasuk && !$sudahPulang && ($normalCheckoutOpen || $approvedEarlyLeaveReady);
$canRequestEarlyLeave = $sudahMasuk
    && ($user['role'] ?? '') === 'karyawan'
    && !$sudahPulang
    && !$normalCheckoutOpen
    && (!$earlyLeaveRequest || !in_array($earlyLeaveRequest['status'], ['pending', 'approved'], true));

// Ambil 7 riwayat absensi terakhir karyawan
$stmtHistory = $db->prepare("
    SELECT tanggal, jam_masuk, jam_pulang, status, status_masuk, menit_terlambat, status_pulang, keterangan
    FROM absensi
    WHERE id_karyawan = :id_karyawan
    ORDER BY tanggal DESC
    LIMIT 7
");
$stmtHistory->execute([':id_karyawan' => $karyawanId]);
$historyList = $stmtHistory->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <!-- Kolom Kiri: Kartu Profil Karyawan & Panel Aksi Presensi -->
    <div class="col-lg-5">
        <!-- Kartu Identitas Profil Premium - High Contrast -->
        <div class="card card-profile-premium mb-4 text-center">
            <div class="card-profile-header-bar"></div>
            <div class="p-4 pt-0">
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar-large mx-auto">
                        <?= strtoupper(substr($user['nama'], 0, 1)); ?>
                    </div>
                </div>
                <h4 class="profile-name"><?= e($user['nama']); ?></h4>
                <div class="profile-role-title"><?= e($user['jabatan']); ?></div>
                
                <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                    <span class="badge badge-nik-contrast">
                        <i class="bi bi-card-heading me-1 text-primary"></i>NIK: <?= e($user['nik']); ?>
                    </span>
                    <span class="badge badge-wa-contrast">
                        <i class="bi bi-whatsapp me-1 text-success"></i><?= e(format_phone_display($user['no_hp'])); ?>
                    </span>
                </div>

                <!-- Baris Informasi dengan Kontras Tinggi -->
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="bi bi-calendar3 me-1 text-primary"></i>Hari & Tanggal:</span>
                    <span class="profile-info-value"><?= format_tanggal_indo($today); ?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label"><i class="bi bi-clock-history me-1 text-primary"></i>Waktu Server (WIB):</span>
                    <span class="profile-info-value text-primary font-monospace" id="cardServerClock"><?= date('H:i:s'); ?> WIB</span>
                </div>
            </div>
        </div>

        <!-- Kartu Panel Presensi Hari Ini -->
        <div class="card card-custom">
            <div class="card-header bg-white py-3 border-bottom border-slate-200">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-fingerprint text-primary me-2"></i>Status & Presensi Hari Ini</h6>
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-4 p-3 bg-slate-50 border border-slate-200 rounded-3">
                    <div class="small text-slate-700 mb-2 text-uppercase fw-bold" style="letter-spacing: 0.05em;">Status Kehadiran Anda</div>
                    <div>
                        <?= badge_status_absensi($statusTeks); ?>
                    </div>
                </div>

                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <div class="p-3 rounded-3 text-center border <?= $sudahMasuk ? 'bg-primary-subtle border-primary' : 'bg-white border-slate-300'; ?>">
                            <small class="text-slate-700 fw-bold d-block mb-1"><i class="bi bi-box-arrow-in-right me-1"></i>Jam Masuk</small>
                            <span class="fw-bold fs-5 font-monospace text-slate-900">
                                <?= $sudahMasuk ? date('H:i', strtotime($absenHariIni['jam_masuk'])) . ' WIB' : '--:--'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 text-center border <?= $sudahPulang ? 'bg-success-subtle border-success' : 'bg-white border-slate-300'; ?>">
                            <small class="text-slate-700 fw-bold d-block mb-1"><i class="bi bi-box-arrow-left me-1"></i>Jam Pulang</small>
                            <span class="fw-bold fs-5 font-monospace text-slate-900">
                                <?= $sudahPulang ? date('H:i', strtotime($absenHariIni['jam_pulang'])) . ' WIB' : '--:--'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="small text-muted mb-3">
                    Jadwal masuk: <strong><?= e(ATTENDANCE_START_TIME); ?> WIB</strong>
                    &bull; Absen pulang normal mulai <strong><?= e(EARLIEST_CHECKOUT_TIME); ?> WIB</strong>
                </div>
                <?php if ($sudahMasuk && (int)($absenHariIni['menit_terlambat'] ?? 0) > 0): ?>
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i>Terlambat <?= (int)$absenHariIni['menit_terlambat']; ?> menit dari jadwal masuk.
                    </div>
                <?php elseif ($sudahMasuk): ?>
                    <div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i>Presensi masuk tepat waktu.</div>
                <?php endif; ?>
                <?php if ($earlyLeaveRequest): ?>
                    <?php
                    $requestBadge = [
                        'pending' => ['warning', 'Menunggu Persetujuan'],
                        'approved' => ['success', 'Disetujui'],
                        'rejected' => ['danger', 'Ditolak'],
                        'cancelled' => ['secondary', 'Dibatalkan']
                    ][$earlyLeaveRequest['status']] ?? ['secondary', 'Status tidak diketahui'];
                    ?>
                    <div class="alert alert-<?= e($requestBadge[0]); ?> py-2 small">
                        Izin pulang awal: <strong><?= e($requestBadge[1]); ?></strong>
                        untuk pukul <?= date('H:i', strtotime($earlyLeaveRequest['jam_pulang_diajukan'])); ?> WIB.
                        <?php if ($earlyLeaveRequest['status'] === 'rejected' && !empty($earlyLeaveRequest['alasan_penolakan'])): ?>
                            <div class="mt-1">Alasan penolakan: <?= e($earlyLeaveRequest['alasan_penolakan']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!is_demo_user()): ?>
                <div class="d-grid gap-3">
                    <!-- Form Absen Masuk -->
                    <form action="<?= BASE_URL; ?>absensi/masuk.php" method="POST" class="protect-submit">
                        <?= csrf_field(); ?>
                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" <?= $sudahMasuk ? 'disabled' : ''; ?>>
                            <i class="bi bi-box-arrow-in-right fs-4"></i>
                            <span><?= $sudahMasuk ? 'SUDAH ABSEN MASUK' : 'ABSEN MASUK SEKARANG'; ?></span>
                        </button>
                    </form>

                    <!-- Form Absen Pulang -->
                    <form action="<?= BASE_URL; ?>absensi/pulang.php" method="POST" class="protect-submit">
                        <?= csrf_field(); ?>
                        <button type="submit" class="btn btn-outline-success w-100 py-3 fw-bold d-flex align-items-center justify-content-center gap-2" <?= !$canCheckout ? 'disabled' : ''; ?>>
                            <i class="bi bi-box-arrow-left fs-4"></i>
                            <span><?= $sudahPulang ? 'PRESENSI HARI INI SELESAI' : ($canCheckout && $approvedEarlyLeaveReady && !$normalCheckoutOpen ? 'ABSEN PULANG SESUAI IZIN' : 'ABSEN PULANG SEKARANG'); ?></span>
                        </button>
                    </form>
                    <?php if ($canRequestEarlyLeave): ?>
                        <div class="border rounded-3 p-3 bg-light">
                            <h6 class="fw-bold"><i class="bi bi-pencil-square text-primary me-1"></i>Ajukan Izin Pulang Lebih Awal</h6>
                            <p class="small text-muted mb-3">Pengajuan harus disetujui admin. Absen pulang hanya aktif setelah waktu yang diminta tiba.</p>
                            <form action="<?= BASE_URL; ?>absensi/izin_pulang.php" method="POST" class="protect-submit">
                                <?= csrf_field(); ?>
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <label for="tanggal_izin" class="form-label small">Tanggal izin</label>
                                        <input type="date" class="form-control form-control-sm" id="tanggal_izin" name="tanggal_izin" value="<?= e($today); ?>" min="<?= e($today); ?>" max="<?= e($today); ?>" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="jam_pulang_diajukan" class="form-label small">Jam pulang diajukan</label>
                                        <input type="time" class="form-control form-control-sm" id="jam_pulang_diajukan" name="jam_pulang_diajukan" max="<?= e(EARLIEST_CHECKOUT_TIME); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label for="kategori_alasan" class="form-label small">Kategori alasan</label>
                                        <select class="form-select form-select-sm" id="kategori_alasan" name="kategori_alasan" required>
                                            <option value="">Pilih alasan</option>
                                            <option>Sakit</option><option>Keperluan berobat</option><option>Keperluan keluarga</option>
                                            <option>Keperluan pendidikan</option><option>Keperluan mendesak</option><option>Keperluan pribadi lainnya</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label for="keterangan_izin" class="form-label small">Penjelasan tambahan</label>
                                        <textarea class="form-control form-control-sm" id="keterangan_izin" name="keterangan" rows="2" maxlength="1000" placeholder="Wajib diisi untuk keperluan pribadi lainnya"></textarea>
                                    </div>
                                    <div class="col-12 text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Kirim Pengajuan</button></div>
                                </div>
                            </form>
                        </div>
                    <?php elseif ($sudahMasuk && !$sudahPulang && !$normalCheckoutOpen && !$approvedEarlyLeaveReady): ?>
                        <div class="alert alert-warning small mb-0">
                            <?php if ($earlyLeaveRequest && $earlyLeaveRequest['status'] === 'approved'): ?>
                                Izin pulang awal disetujui. Tombol absen akan aktif mulai pukul <?= date('H:i', strtotime($earlyLeaveRequest['jam_pulang_diajukan'])); ?> WIB.
                            <?php elseif ($earlyLeaveRequest && $earlyLeaveRequest['status'] === 'pending'): ?>
                                Pengajuan menunggu persetujuan admin. Absen pulang normal baru dapat dilakukan mulai pukul <?= e(EARLIEST_CHECKOUT_TIME); ?> WIB.
                            <?php else: ?>
                                <strong>Belum Waktunya Absen Pulang.</strong><br>Absen pulang normal baru dapat dilakukan mulai pukul <?= e(EARLIEST_CHECKOUT_TIME); ?> WIB.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle-fill me-2"></i>Mode demo hanya-baca. Presensi tidak akan dicatat dan notifikasi WhatsApp tidak dikirim.
                    </div>
                <?php endif; ?>

                <?php if (!is_demo_user()): ?>
                <div class="alert alert-light border border-slate-300 small text-slate-700 mt-4 mb-0 fw-medium" role="alert">
                    <i class="bi bi-whatsapp text-success me-1"></i>
                    Sistem otomatis mengirim konfirmasi ke nomor WhatsApp Anda setiap kali presensi berhasil dicatat.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Riwayat Presensi Terbaru -->
    <div class="col-lg-7">
        <div class="card card-custom h-100">
            <div class="card-header bg-white py-3 border-bottom border-slate-200 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-slate-900"><i class="bi bi-calendar-check text-primary me-2"></i>Riwayat Kehadiran Terakhir</h6>
                <a href="<?= BASE_URL; ?>karyawan/riwayat.php" class="btn btn-sm btn-outline-primary fw-semibold">Lihat Selengkapnya</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th class="text-center">Jam Masuk</th>
                                <th class="text-center">Jam Pulang</th>
                                <th class="text-center">Status Masuk</th>
                                <th class="text-center">Terlambat</th>
                                <th class="text-center">Status Pulang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($historyList)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-slate-600">
                                        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-slate-400"></i>
                                        Belum ada riwayat absensi yang tercatat.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historyList as $h): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-slate-900"><?= format_tanggal_indo($h['tanggal']); ?></div>
                                            <small class="text-slate-600 font-monospace"><?= date('d/m/Y', strtotime($h['tanggal'])); ?></small>
                                        </td>
                                        <td class="text-center font-monospace fw-bold text-slate-800">
                                            <?= !empty($h['jam_masuk']) ? date('H:i', strtotime($h['jam_masuk'])) . ' WIB' : '-'; ?>
                                        </td>
                                        <td class="text-center font-monospace fw-bold text-slate-800">
                                            <?= !empty($h['jam_pulang']) ? date('H:i', strtotime($h['jam_pulang'])) . ' WIB' : '-'; ?>
                                        </td>
                                        <td class="text-center"><?= e($h['status_masuk'] ?? '-'); ?></td>
                                        <td class="text-center"><?= (int)($h['menit_terlambat'] ?? 0) > 0 ? (int)$h['menit_terlambat'] . ' menit' : '-'; ?></td>
                                        <td class="text-center"><?= e($h['status_pulang'] === 'PULANG_LEBIH_AWAL_IZIN' ? 'Pulang awal (izin)' : (!empty($h['jam_pulang']) ? 'Normal' : '-')); ?></td>
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

<?php if ($earlyLeaveHistory): ?>
    <div class="card card-custom mt-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clipboard2-check text-primary me-2"></i>Riwayat Pengajuan Izin Pulang Awal</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead><tr><th>Tanggal Pengajuan</th><th>Waktu Diminta</th><th>Alasan</th><th>Status</th><th>Keputusan</th></tr></thead>
                <tbody>
                    <?php foreach ($earlyLeaveHistory as $request): ?>
                        <?php $requestStatusClass = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'][$request['status']] ?? 'secondary'; ?>
                        <tr>
                            <td><?= format_tanggal_indo($request['tanggal_izin']); ?><br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($request['created_at'])); ?> WIB</small></td>
                            <td class="font-monospace"><?= date('H:i', strtotime($request['jam_pulang_diajukan'])); ?> WIB</td>
                            <td><?= e($request['kategori_alasan']); ?><?php if ($request['keterangan']): ?><div class="small text-muted"><?= e($request['keterangan']); ?></div><?php endif; ?></td>
                            <td><span class="badge bg-<?= e($requestStatusClass); ?>-subtle text-<?= e($requestStatusClass); ?>"><?= e(ucfirst($request['status'])); ?></span></td>
                            <td class="small text-muted"><?= $request['status'] === 'rejected' ? e($request['alasan_penolakan'] ?: '-'): ($request['status'] === 'approved' ? 'Disetujui admin' : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
const reasonCategory = document.getElementById('kategori_alasan');
const reasonDetails = document.getElementById('keterangan_izin');
if (reasonCategory && reasonDetails) {
    const updateReasonRequirement = () => {
        reasonDetails.required = reasonCategory.value === 'Keperluan pribadi lainnya';
    };
    reasonCategory.addEventListener('change', updateReasonRequirement);
    updateReasonRequirement();
}
setInterval(() => {
    const el = document.getElementById('cardServerClock');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }) + ' WIB';
    }
}, 1000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
