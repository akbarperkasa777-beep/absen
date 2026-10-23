<?php
/**
 * Modul Log Aktivitas Sistem (Audit Trail) - Premium UI
 * Menampilkan catatan seluruh tindakan penting yang dilakukan oleh pengguna dan administrator
 */

$pageTitle = 'Log Aktivitas Pengguna';
$activeMenu = 'log_aktivitas';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_write();

$db = getDBConnection();

$filterAksi = trim($_GET['aksi'] ?? '');
$filterUser = trim($_GET['user'] ?? '');

$conditions = [];
$params = [];

if (!empty($filterAksi)) {
    $conditions[] = 'aksi = :aksi';
    $params[':aksi'] = $filterAksi;
}
if (!empty($filterUser)) {
    $conditions[] = 'nama_user LIKE :user';
    $params[':user'] = "%{$filterUser}%";
}

$whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// 1. Hitung Total Data untuk Pagination
$stmtCount = $db->prepare("SELECT COUNT(*) AS total FROM log_aktivitas {$whereSql}");
$stmtCount->execute($params);
$totalRecords = (int)($stmtCount->fetch()['total'] ?? 0);

// 2. Pagination Logic
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, ceil($totalRecords / $perPage));
$offset = ($page - 1) * $perPage;

// 3. Query Log
$stmt = $db->prepare("
    SELECT id, id_user, nama_user, role, aksi, keterangan, ip_address, created_at
    FROM log_aktivitas
    {$whereSql}
    ORDER BY id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Daftar unik aksi untuk filter
$daftarAksi = $db->query("SELECT DISTINCT aksi FROM log_aktivitas ORDER BY aksi ASC")->fetchAll(PDO::FETCH_COLUMN);

$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);
$paginationUrl = BASE_URL . 'admin/log_aktivitas.php?' . ($queryString ? $queryString . '&' : '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Log Aktivitas Sistem</h4>
        <p class="text-muted small mb-0">Audit trail kronologis tindakan pengguna, otentikasi, presensi, dan manipulasi data</p>
    </div>
</div>

<!-- Card Filter Log -->
<div class="card card-custom mb-4">
    <div class="card-body p-4">
        <form action="" method="GET" class="row g-3">
            <div class="col-sm-6 col-md-4">
                <label for="user" class="form-label small text-muted">Aktor / Nama Pengguna:</label>
                <input type="text" class="form-control form-control-sm" id="user" name="user" placeholder="Cari nama aktor..." value="<?= e($filterUser); ?>">
            </div>
            <div class="col-sm-6 col-md-4">
                <label for="aksi" class="form-label small text-muted">Jenis Tindakan (Aksi):</label>
                <select class="form-select form-select-sm" id="aksi" name="aksi">
                    <option value="">Semua Aksi</option>
                    <?php foreach ($daftarAksi as $ak): ?>
                        <option value="<?= e($ak); ?>" <?= ($filterAksi === $ak) ? 'selected' : ''; ?>><?= e($ak); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-funnel-fill me-1"></i>Filter Log
                </button>
                <a href="<?= BASE_URL; ?>admin/log_aktivitas.php" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Log Aktivitas -->
<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold text-dark">Daftar Audit Trail</h6>
            <small class="text-muted">Total: <?= $totalRecords; ?> catatan audit</small>
        </div>
        <span class="badge bg-light text-dark border">Halaman <?= $page; ?> dari <?= $totalPages; ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Waktu & Tanggal</th>
                        <th>Aktor (Pengguna)</th>
                        <th>Peran</th>
                        <th>Jenis Aksi</th>
                        <th>Keterangan Aktivitas</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada catatan aktivitas yang sesuai dengan filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($logs as $row): 
                        ?>
                            <tr>
                                <td class="text-center text-muted fw-semibold"><?= $no++; ?></td>
                                <td class="small text-muted font-monospace">
                                    <?= date('d/m/Y H:i:s', strtotime($row['created_at'])); ?> WIB
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($row['nama_user']); ?></div>
                                    <?php if (!empty($row['id_user'])): ?>
                                        <small class="text-muted font-monospace">ID #<?= $row['id_user']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['role'] === 'admin'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">KARYAWAN</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-dark-subtle text-dark border font-monospace"><?= e($row['aksi']); ?></span>
                                </td>
                                <td class="small text-dark">
                                    <?= e($row['keterangan'] ?? '-'); ?>
                                </td>
                                <td class="small font-monospace text-muted">
                                    <?= e($row['ip_address'] ?? '127.0.0.1'); ?>
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
