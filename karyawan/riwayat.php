<?php
/**
 * Modul Riwayat Absensi Karyawan - Premium UI
 * Menampilkan seluruh catatan presensi karyawan yang sedang login
 * dengan filter tanggal, bulan, tahun, status, pagination, dan ekspor CSV
 */

$pageTitle = 'Riwayat Presensi Saya';
$activeMenu = 'riwayat_saya';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user = current_user();
$karyawanId = (int)$user['id'];
$db = getDBConnection();

// Parameter Filter
$tglMulai   = trim($_GET['tgl_mulai'] ?? '');
$tglSelesai = trim($_GET['tgl_selesai'] ?? '');
$filterBulan = trim($_GET['bulan'] ?? '');
$filterTahun = trim($_GET['tahun'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$exportMode = trim($_GET['export'] ?? '');

// Bangun Klausa WHERE dengan Prepared Statement (Selalu batasi dengan id_karyawan dari sesi!)
$conditions = ['a.id_karyawan = :karyawan_id'];
$params = [':karyawan_id' => $karyawanId];

if (!empty($tglMulai)) {
    $conditions[] = 'a.tanggal >= :tgl_mulai';
    $params[':tgl_mulai'] = $tglMulai;
}
if (!empty($tglSelesai)) {
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
if (!empty($filterStatus)) {
    $conditions[] = 'a.status = :status';
    $params[':status'] = $filterStatus;
}

$whereSql = implode(' AND ', $conditions);

// FITUR EKSPOR CSV DATA PRIBADI KARYAWAN
if ($exportMode === 'csv') {
    $stmtExp = $db->prepare("
        SELECT a.tanggal, a.jam_masuk, a.jam_pulang, a.status, a.status_masuk, a.menit_terlambat, a.status_pulang,
               a.keterangan, i.kategori_alasan
        FROM absensi a
        LEFT JOIN izin_pulang_awal i ON i.id = a.id_izin_pulang
        WHERE {$whereSql}
        ORDER BY tanggal DESC
    ");
    $stmtExp->execute($params);
    $dataExp = $stmtExp->fetchAll();

    $filename = "Riwayat_Presensi_" . preg_replace('/[^a-zA-Z0-9]/', '_', $user['nama']) . "_" . date('Ymd') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    fputcsv($out, ['No', 'Tanggal', 'Hari', 'Jam Masuk', 'Status Masuk', 'Menit Terlambat', 'Jam Pulang', 'Status Pulang', 'Kategori Izin', 'Status', 'Keterangan']);
    $no = 1;
    foreach ($dataExp as $row) {
        $hari = date('l', strtotime($row['tanggal']));
        fputcsv($out, [
            $no++,
            $row['tanggal'],
            $hari,
            !empty($row['jam_masuk']) ? date('H:i', strtotime($row['jam_masuk'])) : '-',
            $row['status_masuk'],
            (int)$row['menit_terlambat'],
            !empty($row['jam_pulang']) ? date('H:i', strtotime($row['jam_pulang'])) : '-',
            $row['status_pulang'] ?? '-',
            $row['kategori_alasan'] ?? '-',
            $row['status'],
            $row['keterangan'] ?? '-'
        ]);
    }
    fclose($out);
    exit;
}

// 1. Hitung Total Data untuk Pagination
$stmtCount = $db->prepare("SELECT COUNT(*) AS total FROM absensi a WHERE {$whereSql}");
$stmtCount->execute($params);
$totalRecords = (int)($stmtCount->fetch()['total'] ?? 0);

// 2. Pagination Logic
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, ceil($totalRecords / $perPage));
$offset = ($page - 1) * $perPage;

// 3. Ambil Data Sesuai Limit dan Offset
$stmtList = $db->prepare("
    SELECT a.id, a.tanggal, a.jam_masuk, a.jam_pulang, a.status, a.status_masuk, a.menit_terlambat,
           a.status_pulang, a.keterangan, a.alasan_koreksi, a.waktu_koreksi, i.kategori_alasan
    FROM absensi a
    LEFT JOIN izin_pulang_awal i ON i.id = a.id_izin_pulang
    WHERE {$whereSql}
    ORDER BY tanggal DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmtList->execute($params);
$absensiList = $stmtList->fetchAll();

// Bangun query string untuk pagination agar filter tidak hilang saat berpindah halaman
$queryParams = $_GET;
unset($queryParams['page'], $queryParams['export']);
$queryString = http_build_query($queryParams);
$paginationUrl = BASE_URL . 'karyawan/riwayat.php?' . ($queryString ? $queryString . '&' : '');

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (is_demo_user()): ?>
    <div class="alert alert-info border-0 border-start border-4 border-info bg-white shadow-sm">
        <i class="bi bi-info-circle-fill me-2"></i>Riwayat yang ditampilkan adalah data contoh Karyawan Demo.
    </div>
<?php endif; ?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Presensi Saya</h4>
        <p class="text-muted small mb-0">Daftar kehadiran pribadi Anda pada Portal Presensi Karyawan</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($totalRecords > 0): ?>
            <a href="<?= $paginationUrl; ?>export=csv" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1 shadow-xs">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                <span>Unduh Riwayat (CSV)</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Card Filter Riwayat -->
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
                <label for="bulan" class="form-label small text-muted">Bulan:</label>
                <select class="form-select form-select-sm" id="bulan" name="bulan">
                    <option value="">Semua Bulan</option>
                    <?php 
                    $daftarBulan = [
                        1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni',
                        7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
                    ];
                    foreach ($daftarBulan as $idx => $namaBln): 
                    ?>
                        <option value="<?= $idx; ?>" <?= ($filterBulan == $idx) ? 'selected' : ''; ?>><?= $namaBln; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label for="status" class="form-label small text-muted">Status Presensi:</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="SUDAH ABSEN MASUK" <?= ($filterStatus === 'SUDAH ABSEN MASUK') ? 'selected' : ''; ?>>Sudah Masuk</option>
                    <option value="SELESAI" <?= ($filterStatus === 'SELESAI') ? 'selected' : ''; ?>>Selesai</option>
                    <option value="BELUM ABSEN" <?= ($filterStatus === 'BELUM ABSEN') ? 'selected' : ''; ?>>Belum Absen</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-funnel-fill me-1"></i>Filter
                </button>
                <a href="<?= BASE_URL; ?>karyawan/riwayat.php" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Card Tabel Riwayat -->
<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold text-dark">Data Presensi</h6>
            <small class="text-muted">Menampilkan <?= count($absensiList); ?> dari total <?= $totalRecords; ?> catatan</small>
        </div>
        <span class="badge bg-light text-dark border">Halaman <?= $page; ?> dari <?= $totalPages; ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Tanggal Presensi</th>
                        <th class="text-center">Jam Masuk</th>
                        <th class="text-center">Status Masuk</th>
                        <th class="text-center">Terlambat</th>
                        <th class="text-center">Jam Pulang</th>
                        <th class="text-center">Status Pulang / Izin</th>
                        <th class="text-center">Status</th>
                        <th>Keterangan</th>
                        <th class="text-center" style="width: 80px;">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($absensiList)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 text-secondary d-block mb-2"></i>
                                Tidak ditemukan catatan presensi yang sesuai dengan filter.
                                <div class="mt-2">
                                    <a href="<?= BASE_URL; ?>karyawan/riwayat.php" class="btn btn-sm btn-outline-secondary">Reset Filter</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($absensiList as $item): 
                        ?>
                            <tr>
                                <td class="text-center text-muted fw-semibold"><?= $no++; ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= format_tanggal_indo($item['tanggal']); ?></div>
                                    <small class="text-muted font-monospace"><?= date('d/m/Y', strtotime($item['tanggal'])); ?></small>
                                </td>
                                <td class="text-center font-monospace">
                                    <?= !empty($item['jam_masuk']) ? date('H:i', strtotime($item['jam_masuk'])) . ' WIB' : '-'; ?>
                                </td>
                                <td class="text-center"><?= e($item['status_masuk']); ?></td>
                                <td class="text-center"><?= (int)$item['menit_terlambat'] > 0 ? (int)$item['menit_terlambat'] . ' menit' : '-'; ?></td>
                                <td class="text-center font-monospace">
                                    <?= !empty($item['jam_pulang']) ? date('H:i', strtotime($item['jam_pulang'])) . ' WIB' : '-'; ?>
                                </td>
                                <td class="text-center">
                                    <?= e(($item['status_pulang'] ?? '') === 'PULANG_LEBIH_AWAL_IZIN' ? 'Pulang awal (izin)' : (!empty($item['jam_pulang']) ? 'Normal' : '-')); ?>
                                    <?php if (!empty($item['kategori_alasan'])): ?><div class="small text-muted"><?= e($item['kategori_alasan']); ?></div><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?= badge_status_absensi($item['status']); ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['keterangan'])): ?>
                                        <span class="small text-dark"><?= e($item['keterangan']); ?></span>
                                    <?php elseif (!empty($item['alasan_koreksi'])): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Catatan dikoreksi oleh Administrator">
                                            <i class="bi bi-info-circle me-1"></i>Dikoreksi Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalDetail<?= $item['id']; ?>" title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <!-- Modal Detail Catatan -->
                                    <div class="modal fade" id="modalDetail<?= $item['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content text-start border-0 shadow">
                                                <div class="modal-header bg-light">
                                                    <h6 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-event text-primary me-2"></i>Rincian Presensi</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="text-muted small">Tanggal:</span>
                                                        <span class="fw-bold text-dark"><?= format_tanggal_indo($item['tanggal']); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="text-muted small">Status:</span>
                                                        <span><?= badge_status_absensi($item['status']); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="text-muted small">Status masuk:</span>
                                                        <span><?= e($item['status_masuk']); ?><?= (int)$item['menit_terlambat'] > 0 ? ' (' . (int)$item['menit_terlambat'] . ' menit)' : ''; ?></span>
                                                    </div>
                                                    <?php if (($item['status_pulang'] ?? '') === 'PULANG_LEBIH_AWAL_IZIN'): ?>
                                                        <div class="alert alert-success small">Pulang lebih awal dengan izin admin disetujui.</div>
                                                    <?php endif; ?>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-6">
                                                            <div class="p-2 border rounded bg-light text-center">
                                                                <small class="text-muted d-block">Jam Masuk</small>
                                                                <strong class="font-monospace text-dark"><?= !empty($item['jam_masuk']) ? date('H:i:s', strtotime($item['jam_masuk'])) : '-'; ?></strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="p-2 border rounded bg-light text-center">
                                                                <small class="text-muted d-block">Jam Pulang</small>
                                                                <strong class="font-monospace text-dark"><?= !empty($item['jam_pulang']) ? date('H:i:s', strtotime($item['jam_pulang'])) : '-'; ?></strong>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($item['alasan_koreksi'])): ?>
                                                        <div class="alert alert-warning small mb-0">
                                                            <strong><i class="bi bi-shield-exclamation me-1"></i>Catatan Koreksi Admin:</strong><br>
                                                            <?= e($item['alasan_koreksi']); ?><br>
                                                            <small class="text-muted">Waktu koreksi: <?= date('d/m/Y H:i', strtotime($item['waktu_koreksi'])); ?> WIB</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer bg-light py-2">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center">
            <small class="text-muted">Menampilkan halaman <?= $page; ?> dari <?= $totalPages; ?></small>
            <nav aria-label="Pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?= $paginationUrl . 'page=' . ($page - 1); ?>">Sebelumnya</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($p == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?= $paginationUrl . 'page=' . $p; ?>"><?= $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?= $paginationUrl . 'page=' . ($page + 1); ?>">Berikutnya</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
