<?php
/**
 * Modul Data & Koreksi Presensi Karyawan oleh Admin - Premium UI
 * Menampilkan catatan absensi harian dan menyediakan fitur koreksi data absensi yang sah
 */

$pageTitle = 'Data & Koreksi Absensi Karyawan';
$activeMenu = 'absensi_admin';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/attendance.php';

require_admin();

$db = getDBConnection();
$filterTanggal = trim($_GET['tanggal'] ?? date('Y-m-d'));

// HANDLER POST: KOREKSI PRESENSI OLEH ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'koreksi_presensi') {
    require_admin_write();
    if (!validate_csrf()) {
        set_flash('danger', 'Token CSRF tidak valid.', 'Operasi Ditolak');
    } else {
        $absenId       = filter_input(INPUT_POST, 'absen_id', FILTER_VALIDATE_INT);
        $jamMasukBaru  = trim($_POST['jam_masuk'] ?? '');
        $jamPulangBaru = trim($_POST['jam_pulang'] ?? '');
        $statusBaru    = trim($_POST['status'] ?? 'SUDAH ABSEN MASUK');
        $alasanKoreksi = trim($_POST['alasan_koreksi'] ?? '');

        if (!$absenId || empty($alasanKoreksi)) {
            set_flash('warning', 'Alasan koreksi presensi wajib diisi demi akuntabilitas data.', 'Validasi Gagal');
        } elseif (($jamMasukBaru !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $jamMasukBaru))
            || ($jamPulangBaru !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $jamPulangBaru))) {
            set_flash('warning', 'Format jam masuk atau pulang tidak valid.', 'Validasi Gagal');
        } else {
            try {
                $jamMasukVal  = !empty($jamMasukBaru) ? (strlen($jamMasukBaru) === 5 ? $jamMasukBaru . ':00' : $jamMasukBaru) : null;
                $jamPulangVal = !empty($jamPulangBaru) ? (strlen($jamPulangBaru) === 5 ? $jamPulangBaru . ':00' : $jamPulangBaru) : null;
                $lateMinutes = $jamMasukVal ? attendance_minutes_late($jamMasukVal) : 0;
                $checkInStatus = $lateMinutes > 0 ? 'TERLAMBAT' : 'TEPAT_WAKTU';

                $adminUser = current_user();

                $stmtKoreksi = $db->prepare("
                    UPDATE absensi 
                    SET jam_masuk = :jam_masuk,
                        jam_pulang = :jam_pulang,
                        status = :status,
                        status_masuk = :status_masuk,
                        menit_terlambat = :menit_terlambat,
                        status_pulang = :status_pulang,
                        dikoreksi_oleh = :admin_id,
                        alasan_koreksi = :alasan,
                        waktu_koreksi = NOW()
                    WHERE id = :id
                ");
                $stmtKoreksi->execute([
                    ':jam_masuk'  => $jamMasukVal,
                    ':jam_pulang' => $jamPulangVal,
                    ':status'     => $statusBaru,
                    ':status_masuk' => $checkInStatus,
                    ':menit_terlambat' => $lateMinutes,
                    ':status_pulang' => $jamPulangVal ? 'DIKOREKSI_ADMIN' : null,
                    ':admin_id'   => $adminUser['id'],
                    ':alasan'     => $alasanKoreksi,
                    ':id'         => $absenId
                ]);

                catat_aktivitas(
                    $adminUser['id'],
                    $adminUser['nama'],
                    'admin',
                    'KOREKSI_ABSENSI',
                    "Koreksi absensi ID #{$absenId}: {$statusBaru} (Alasan: {$alasanKoreksi})"
                );

                set_flash('success', "Catatan absensi #{$absenId} berhasil dikoreksi dan dicatat pada log audit.", "Koreksi Berhasil");
                header('Location: ' . BASE_URL . 'admin/absensi.php?tanggal=' . urlencode($filterTanggal));
                exit;

            } catch (Exception $e) {
                error_log("Error koreksi absensi: " . $e->getMessage());
                set_flash('danger', 'Terjadi kesalahan sistem saat menyimpan koreksi.', 'Gagal');
            }
        }
    }
}

// Query data absensi berdasarkan tanggal filter
$stmt = $db->prepare("
    SELECT a.id, a.tanggal, a.jam_masuk, a.jam_pulang, a.status, a.status_masuk, a.menit_terlambat, a.status_pulang, a.keterangan, a.alasan_koreksi, a.waktu_koreksi,
           k.id AS karyawan_id, k.nik, k.nama, k.jabatan, k.no_hp, k.email
    FROM absensi a
    JOIN karyawan k ON a.id_karyawan = k.id
    WHERE a.tanggal = :tanggal
    " . (is_demo_user() ? 'AND k.nik = :demo_nik' : '') . "
    ORDER BY a.jam_masuk DESC, a.created_at DESC
");
$stmt->execute(is_demo_user()
    ? [':tanggal' => $filterTanggal, ':demo_nik' => DEMO_ACCOUNTS['karyawan']['nik']]
    : [':tanggal' => $filterTanggal]);
$absensiList = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-calendar2-check-fill text-primary me-2"></i>Data & Koreksi Presensi</h4>
        <p class="text-muted small mb-0">Pemantauan harian seluruh karyawan dan fasilitas koreksi absensi resmi</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL; ?>admin/laporan.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-file-earmark-bar-graph me-1"></i>Rekapitulasi Periode Laporan
        </a>
    </div>
</div>

<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom">
        <form action="" method="GET" class="row g-2 align-items-center">
            <div class="col-sm-6 col-md-4 col-lg-3">
                <label for="tanggal" class="form-label small text-muted mb-1">Pilih Tanggal Presensi:</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-event"></i></span>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= e($filterTanggal); ?>">
                </div>
            </div>
            <div class="col-sm-6 col-md-4 col-lg-3 d-flex align-items-end gap-2 mt-sm-auto pt-sm-3">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel-fill me-1"></i>Filter
                </button>
                <a href="<?= BASE_URL; ?>admin/absensi.php" class="btn btn-outline-secondary btn-sm">
                    Hari Ini
                </a>
            </div>
            <div class="col-md-4 col-lg-6 text-md-end text-muted small mt-2 mt-md-auto">
                Tanggal: <strong><?= format_tanggal_indo($filterTanggal); ?></strong> &bull; Total: <strong><?= count($absensiList); ?></strong> catatan
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Karyawan</th>
                        <th>Jabatan</th>
                        <th>Kontak WA</th>
                        <th class="text-center">Jam Masuk</th>
                        <th class="text-center">Jam Pulang</th>
                        <th class="text-center">Status</th>
                        <th>Keterangan / Audit</th>
                        <th class="text-center" style="width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($absensiList)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ada data absensi tercatat pada <strong><?= format_tanggal_indo($filterTanggal); ?></strong>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($absensiList as $row): ?>
                            <tr>
                                <td class="text-center text-muted fw-semibold"><?= $no++; ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($row['nama']); ?></div>
                                    <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.72rem;"><?= e($row['nik']); ?></span>
                                </td>
                                <td><?= e($row['jabatan']); ?></td>
                                <td>
                                    <?php if ($row['no_hp'] !== ''): ?>
                                        <a href="https://wa.me/<?= e(normalize_phone($row['no_hp'])); ?>" target="_blank" class="text-decoration-none text-success small font-monospace">
                                            <i class="bi bi-whatsapp me-1"></i><?= e(format_phone_display($row['no_hp'])); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace">
                                    <?= !empty($row['jam_masuk']) ? date('H:i', strtotime($row['jam_masuk'])) . ' WIB' : '-'; ?>
                                </td>
                                <td class="text-center font-monospace">
                                    <?= !empty($row['jam_pulang']) ? date('H:i', strtotime($row['jam_pulang'])) . ' WIB' : '-'; ?>
                                </td>
                                <td class="text-center">
                                    <?= badge_status_absensi($row['status']); ?>
                                    <?php if ((int)$row['menit_terlambat'] > 0): ?><div class="small text-warning">Terlambat <?= (int)$row['menit_terlambat']; ?> menit</div><?php elseif (!empty($row['jam_masuk'])): ?><div class="small text-success">Tepat waktu</div><?php endif; ?>
                                    <?php if (($row['status_pulang'] ?? '') === 'PULANG_LEBIH_AWAL_IZIN'): ?><div class="small text-success">Pulang awal (izin disetujui)</div><?php elseif (($row['status_pulang'] ?? '') === 'DIKOREKSI_ADMIN'): ?><div class="small text-muted">Pulang dikoreksi admin</div><?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['alasan_koreksi'])): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Alasan: <?= e($row['alasan_koreksi']); ?>">
                                            <i class="bi bi-info-circle me-1"></i>Dikoreksi
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!is_demo_user()): ?>
                                        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalKoreksi<?= $row['id']; ?>" title="Koreksi Data Absensi">
                                            <i class="bi bi-pencil-square"></i> Koreksi
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">Hanya-baca</span>
                                    <?php endif; ?>

                                    <?php if (!is_demo_user()): ?>
                                    <!-- Modal Koreksi Presensi -->
                                    <div class="modal fade" id="modalKoreksi<?= $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-start border-0 shadow">
                                                <div class="modal-header bg-warning bg-opacity-25 border-bottom">
                                                    <h6 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-warning"></i>Koreksi Presensi Karyawan</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                </div>
                                                <form action="" method="POST">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="koreksi_presensi">
                                                    <input type="hidden" name="absen_id" value="<?= $row['id']; ?>">
                                                    <div class="modal-body p-4">
                                                        <div class="p-2 border rounded bg-light mb-3">
                                                            <div class="fw-bold text-dark"><?= e($row['nama']); ?></div>
                                                            <small class="text-muted font-monospace"><?= e($row['nik']); ?> &bull; Tanggal: <?= format_tanggal_indo($row['tanggal']); ?></small>
                                                        </div>

                                                        <div class="row g-3 mb-3">
                                                            <div class="col-6">
                                                                <label class="form-label small text-muted">Jam Masuk (HH:MM:SS):</label>
                                                                <input type="text" class="form-control form-control-sm font-monospace" name="jam_masuk" value="<?= e($row['jam_masuk'] ?? '08:00:00'); ?>" placeholder="08:00:00">
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label small text-muted">Jam Pulang (HH:MM:SS):</label>
                                                                <input type="text" class="form-control form-control-sm font-monospace" name="jam_pulang" value="<?= e($row['jam_pulang'] ?? '17:00:00'); ?>" placeholder="17:00:00">
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small text-muted">Status Kehadiran:</label>
                                                            <select class="form-select form-select-sm" name="status">
                                                                <option value="SUDAH ABSEN MASUK" <?= ($row['status'] === 'SUDAH ABSEN MASUK') ? 'selected' : ''; ?>>Sudah Masuk</option>
                                                                <option value="SELESAI" <?= ($row['status'] === 'SELESAI' || $row['status'] === 'PULANG') ? 'selected' : ''; ?>>Selesai (Sudah Pulang)</option>
                                                                <option value="BELUM ABSEN" <?= ($row['status'] === 'BELUM ABSEN') ? 'selected' : ''; ?>>Belum Absen</option>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label small text-muted">Alasan Koreksi: <span class="text-danger">*</span></label>
                                                            <textarea class="form-control form-control-sm" name="alasan_koreksi" rows="2" placeholder="Contoh: Karyawan lupa tap out karena pemadaman listrik..." required><?= e($row['alasan_koreksi'] ?? ''); ?></textarea>
                                                            <div class="form-text" style="font-size: 0.72rem;">Wajib diisi sebagai bukti audit internal.</div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light py-2">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                                                            <i class="bi bi-save me-1"></i> Simpan Koreksi
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
