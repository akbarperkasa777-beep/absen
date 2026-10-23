<?php
/**
 * Modul Laporan Rekapitulasi Presensi Karyawan - Premium UI
 * Filter rentang tanggal, bulan, tahun, karyawan, jabatan, status, ekspor CSV & cetak
 */

$pageTitle = 'Laporan Rekapitulasi Presensi';
$activeMenu = 'laporan';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = getDBConnection();
$isDemo = is_demo_user();

// Parameter Filter
$tglMulai     = trim($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tglSelesai   = trim($_GET['tgl_selesai'] ?? date('Y-m-d'));
$filterBulan  = trim($_GET['bulan'] ?? '');
$filterTahun  = trim($_GET['tahun'] ?? '');
$filterNama   = trim($_GET['karyawan'] ?? '');
$filterJabatan = trim($_GET['jabatan'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$exportMode   = trim($_GET['export'] ?? '');

// Ambil daftar jabatan & karyawan untuk opsi filter
$scopeDemo = $isDemo ? ' AND nik = ' . $db->quote(DEMO_ACCOUNTS['karyawan']['nik']) : '';
$daftarJabatan = $db->query("SELECT DISTINCT jabatan FROM karyawan WHERE jabatan IS NOT NULL AND jabatan != ''{$scopeDemo} ORDER BY jabatan ASC")->fetchAll(PDO::FETCH_COLUMN);
$daftarKaryawan = $db->query("SELECT id, nik, nama FROM karyawan WHERE role = 'karyawan'{$scopeDemo} ORDER BY nama ASC")->fetchAll();

// Bangun Klausa WHERE dengan Prepared Statement
$conditions = [];
$params = [];
if ($isDemo) {
    $conditions[] = 'k.nik = :demo_nik';
    $params[':demo_nik'] = DEMO_ACCOUNTS['karyawan']['nik'];
}

if (!empty($tglMulai) && empty($filterBulan)) {
    $conditions[] = 'a.tanggal >= :tgl_mulai';
    $params[':tgl_mulai'] = $tglMulai;
}
if (!empty($tglSelesai) && empty($filterBulan)) {
    $conditions[] = 'a.tanggal <= :tgl_selesai';
    $params[':tgl_selesai'] = $tglSelesai;
}
if (!empty($filterBulan)) {
    $conditions[] = 'MONTH(a.tanggal) = :bulan';
    $params[':bulan'] = (int)$filterBulan;
}
if (!empty($filterTahun)) {
    $conditions[] = 'YEAR(a.tanggal) = :tahun';
    $params[':tahun'] = (int)$filterTahun;
}
if (!empty($filterNama)) {
    $conditions[] = '(k.nama LIKE :kw1 OR k.nik LIKE :kw2)';
    $params[':kw1'] = "%{$filterNama}%";
    $params[':kw2'] = "%{$filterNama}%";
}
if (!empty($filterJabatan)) {
    $conditions[] = 'k.jabatan = :jabatan';
    $params[':jabatan'] = $filterJabatan;
}
if (!empty($filterStatus)) {
    $conditions[] = 'a.status = :status';
    $params[':status'] = $filterStatus;
}

$whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Query Data Laporan Menggunakan JOIN Karyawan dan Absensi
$sql = "
    SELECT a.id, a.tanggal, a.jam_masuk, a.jam_pulang, a.status, a.status_masuk, a.menit_terlambat,
           a.status_pulang, a.keterangan, a.alasan_koreksi, i.kategori_alasan,
           k.id AS karyawan_id, k.nik, k.nama, k.jabatan, k.no_hp, k.email
    FROM absensi a
    JOIN karyawan k ON a.id_karyawan = k.id
    LEFT JOIN izin_pulang_awal i ON i.id = a.id_izin_pulang
    {$whereSql}
    ORDER BY a.tanggal DESC, a.jam_masuk DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$laporanList = $stmt->fetchAll();

// Ringkasan Agregat Laporan Faktual
$totalCatatan = count($laporanList);
$karyawanUnik = [];
$catatanMasuk = 0;
$catatanPulang = 0;
$karyawanTerlambat = 0;
$totalMenitTerlambat = 0;

foreach ($laporanList as $item) {
    $karyawanUnik[$item['karyawan_id']] = true;
    if (!empty($item['jam_masuk'])) $catatanMasuk++;
    if (!empty($item['jam_pulang'])) $catatanPulang++;
    if ((int)$item['menit_terlambat'] > 0) {
        $karyawanTerlambat++;
        $totalMenitTerlambat += (int)$item['menit_terlambat'];
    }
}
$totalKaryawanUnik = count($karyawanUnik);

// FITUR EKSPOR EXCEL / CSV
if ($exportMode === 'excel') {
    $filename = "Laporan_Presensi_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    fputcsv($out, ['No', 'NIK', 'Nama Karyawan', 'Jabatan', 'Tanggal', 'Jam Masuk', 'Status Masuk', 'Menit Terlambat', 'Jam Pulang', 'Status Pulang', 'Kategori Izin', 'Status', 'Catatan Koreksi']);

    $no = 1;
    foreach ($laporanList as $r) {
        fputcsv($out, [
            $no++,
            "'" . $r['nik'],
            $r['nama'],
            $r['jabatan'],
            $r['tanggal'],
            !empty($r['jam_masuk']) ? date('H:i', strtotime($r['jam_masuk'])) : '-',
            $r['status_masuk'],
            (int)$r['menit_terlambat'],
            !empty($r['jam_pulang']) ? date('H:i', strtotime($r['jam_pulang'])) : '-',
            $r['status_pulang'] ?? '-',
            $r['kategori_alasan'] ?? '-',
            $r['status'],
            $r['alasan_koreksi'] ?? ($r['keterangan'] ?? '-')
        ]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-file-earmark-bar-graph-fill text-primary me-2"></i>Laporan Rekapitulasi Presensi</h4>
        <p class="text-muted small mb-0">Analisis kehadiran karyawan dengan filter multi-parameter dan ekspor dokumen</p>
    </div>

    <?php if ($isDemo): ?>
        <div class="alert alert-info border-0 border-start border-4 border-info bg-white shadow-sm">
            <i class="bi bi-info-circle-fill me-2"></i>Laporan ini hanya memuat catatan Karyawan Demo dari database.
        </div>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <?php
        $exportQuery = $_GET;
        $exportQuery['export'] = 'excel';
        $exportUrl = '?' . http_build_query($exportQuery);
        ?>
        <a href="<?= $exportUrl; ?>" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1 shadow-xs">
            <i class="bi bi-file-earmark-spreadsheet-fill"></i>
            <span>Ekspor Excel (CSV)</span>
        </a>
        <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" onclick="window.print();">
            <i class="bi bi-printer-fill"></i>
            <span>Cetak Dokumen</span>
        </button>
    </div>
</div>

<!-- 4 KARTU RINGKASAN HASIL FILTER -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon primary">
                <i class="bi bi-journal-text"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Catatan</div>
                <div class="stat-number"><?= $totalCatatan; ?></div>
                <small class="text-muted" style="font-size: 0.72rem;">Sesuai filter aktif</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon cyan">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Karyawan Terlibat</div>
                <div class="stat-number text-cyan-600"><?= $totalKaryawanUnik; ?></div>
                <small class="text-muted" style="font-size: 0.72rem;">Karyawan unik berpresensi</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon warning">
                <i class="bi bi-box-arrow-in-right"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Tercatat Masuk</div>
                <div class="stat-number text-warning"><?= $catatanMasuk; ?></div>
                <small class="text-muted" style="font-size: 0.72rem;">Jam masuk terisi</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon success">
                <i class="bi bi-check2-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Tercatat Pulang</div>
                <div class="stat-number text-success"><?= $catatanPulang; ?></div>
                <small class="text-muted" style="font-size: 0.72rem;">Presensi selesai tuntas</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card-stat"><div class="stat-content"><div class="stat-label">Catatan Terlambat</div><div class="stat-number text-warning"><?= $karyawanTerlambat; ?></div><small class="text-muted">Sesuai filter laporan</small></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card-stat"><div class="stat-content"><div class="stat-label">Akumulasi Keterlambatan</div><div class="stat-number text-warning"><?= $totalMenitTerlambat; ?></div><small class="text-muted">Menit dari catatan masuk</small></div></div></div>
</div>

<!-- Form Filter Multi-Parameter -->
<div class="card card-custom mb-4">
    <div class="card-body p-4">
        <form action="" method="GET" class="row g-3">
            <div class="col-sm-6 col-md-3">
                <label for="tgl_mulai" class="form-label small text-muted">Dari Tanggal:</label>
                <input type="date" class="form-control form-control-sm" id="tgl_mulai" name="tgl_mulai" value="<?= e($tglMulai); ?>">
            </div>
            <div class="col-sm-6 col-md-3">
                <label for="tgl_selesai" class="form-label small text-muted">Sampai Tanggal:</label>
                <input type="date" class="form-control form-control-sm" id="tgl_selesai" name="tgl_selesai" value="<?= e($tglSelesai); ?>">
            </div>
            <div class="col-sm-6 col-md-2">
                <label for="karyawan" class="form-label small text-muted">Nama / NIK:</label>
                <input type="text" class="form-control form-control-sm" id="karyawan" name="karyawan" placeholder="Cari..." value="<?= e($filterNama); ?>">
            </div>
            <div class="col-sm-6 col-md-2">
                <label for="jabatan" class="form-label small text-muted">Jabatan:</label>
                <select class="form-select form-select-sm" id="jabatan" name="jabatan">
                    <option value="">Semua Jabatan</option>
                    <?php foreach ($daftarJabatan as $j): ?>
                        <option value="<?= e($j); ?>" <?= ($filterJabatan === $j) ? 'selected' : ''; ?>><?= e($j); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label for="status" class="form-label small text-muted">Status:</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="SUDAH ABSEN MASUK" <?= ($filterStatus === 'SUDAH ABSEN MASUK') ? 'selected' : ''; ?>>Sudah Masuk</option>
                    <option value="SELESAI" <?= ($filterStatus === 'SELESAI') ? 'selected' : ''; ?>>Selesai</option>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top">
                <a href="<?= BASE_URL; ?>admin/laporan.php" class="btn btn-outline-secondary btn-sm">Reset Filter</a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel-fill me-1"></i> Terapkan Filter Laporan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Laporan -->
<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">Data Presensi Karyawan Terpilih</h6>
        <span class="badge bg-light text-dark border">Total <?= $totalCatatan; ?> Baris Data</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Tanggal</th>
                        <th>Karyawan</th>
                        <th>Jabatan</th>
                        <th class="text-center">Jam Masuk</th>
                        <th class="text-center">Status Masuk</th>
                        <th class="text-center">Terlambat</th>
                        <th class="text-center">Jam Pulang</th>
                        <th class="text-center">Status</th>
                        <th>Keterangan / Audit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporanList)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ditemukan data presensi pada periode dan kriteria filter yang dipilih.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($laporanList as $lap): ?>
                            <tr>
                                <td class="text-center text-muted fw-semibold"><?= $no++; ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($lap['tanggal'])); ?></div>
                                    <small class="text-muted"><?= format_tanggal_indo($lap['tanggal']); ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($lap['nama']); ?></div>
                                    <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.72rem;"><?= e($lap['nik']); ?></span>
                                </td>
                                <td><?= e($lap['jabatan']); ?></td>
                                <td class="text-center font-monospace">
                                    <?= !empty($lap['jam_masuk']) ? date('H:i', strtotime($lap['jam_masuk'])) . ' WIB' : '-'; ?>
                                </td>
                                <td class="text-center"><?= e($lap['status_masuk']); ?></td>
                                <td class="text-center"><?= (int)$lap['menit_terlambat'] > 0 ? (int)$lap['menit_terlambat'] . ' menit' : '-'; ?></td>
                                <td class="text-center font-monospace">
                                    <?= !empty($lap['jam_pulang']) ? date('H:i', strtotime($lap['jam_pulang'])) . ' WIB' : '-'; ?>
                                </td>
                                <td class="text-center">
                                    <?= badge_status_absensi($lap['status']); ?>
                                    <?php if (($lap['status_pulang'] ?? '') === 'PULANG_LEBIH_AWAL_IZIN'): ?>
                                        <div class="small text-success">Pulang awal (izin)</div>
                                        <div class="small text-muted"><?= e($lap['kategori_alasan'] ?? ''); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($lap['alasan_koreksi'])): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Alasan: <?= e($lap['alasan_koreksi']); ?>">
                                            <i class="bi bi-info-circle me-1"></i>Dikoreksi Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="small text-muted"><?= e($lap['keterangan'] ?? '-'); ?></span>
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

<style>
@media print {
    .app-sidebar, .app-topbar, .btn, form, footer, .card-stat, .card-header span {
        display: none !important;
    }
    .app-main { margin: 0 !important; padding: 0 !important; }
    .card-custom { border: none !important; box-shadow: none !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
