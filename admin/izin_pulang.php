<?php
/**
 * Daftar dan keputusan pengajuan izin pulang lebih awal.
 */

$pageTitle = 'Persetujuan Izin Pulang Awal';
$activeMenu = 'izin_pulang';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/fonnte.php';

require_admin();
$db = getDBConnection();
$admin = current_user();
$statusFilter = trim($_GET['status'] ?? '');
$dateFilter = trim($_GET['tanggal'] ?? '');
$search = trim($_GET['q'] ?? '');
$allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'keputusan_izin') {
    require_admin_write();
    $requestId = filter_input(INPUT_POST, 'izin_id', FILTER_VALIDATE_INT);
    $decision = trim($_POST['keputusan'] ?? '');
    $rejectionReason = trim($_POST['alasan_penolakan'] ?? '');

    if (!validate_csrf()) {
        set_flash('danger', 'Token CSRF tidak sah.', 'Operasi Ditolak');
    } elseif (!$requestId || !in_array($decision, ['approved', 'rejected'], true)) {
        set_flash('warning', 'Pengajuan atau keputusan tidak valid.', 'Validasi Gagal');
    } elseif ($decision === 'rejected' && $rejectionReason === '') {
        set_flash('warning', 'Alasan penolakan wajib diisi.', 'Validasi Gagal');
    } elseif (strlen($rejectionReason) > 1000) {
        set_flash('warning', 'Alasan penolakan maksimal 1000 karakter.', 'Validasi Gagal');
    } else {
        try {
            $db->beginTransaction();
            $stmtRequest = $db->prepare("
                SELECT i.id, i.id_karyawan, i.tanggal_izin, i.jam_pulang_diajukan, i.kategori_alasan,
                       i.keterangan, i.status, k.nama, k.nik, k.jabatan, k.no_hp
                FROM izin_pulang_awal i
                JOIN karyawan k ON k.id = i.id_karyawan
                WHERE i.id = :id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtRequest->execute([':id' => $requestId]);
            $request = $stmtRequest->fetch();
            if (!$request || $request['status'] !== 'pending') {
                throw new DomainException('Pengajuan tidak ditemukan atau sudah diproses oleh admin lain.');
            }

            $stmtDecision = $db->prepare("
                UPDATE izin_pulang_awal
                SET status = :status, alasan_penolakan = :alasan, diputuskan_oleh = :admin_id, diputuskan_pada = NOW()
                WHERE id = :id AND status = 'pending'
            ");
            $stmtDecision->execute([
                ':status' => $decision,
                ':alasan' => $decision === 'rejected' ? $rejectionReason : null,
                ':admin_id' => (int)$admin['id'],
                ':id' => $requestId
            ]);
            if ($stmtDecision->rowCount() !== 1) {
                throw new DomainException('Pengajuan telah diproses. Muat ulang daftar untuk melihat status terbaru.');
            }
            $db->commit();

            catat_aktivitas(
                (int)$admin['id'],
                $admin['nama'],
                'admin',
                $decision === 'approved' ? 'SETUJUI_IZIN_PULANG_AWAL' : 'TOLAK_IZIN_PULANG_AWAL',
                "Pengajuan izin pulang awal #{$requestId} {$decision}" . ($decision === 'rejected' ? ": {$rejectionReason}" : '')
            );
            $request['tanggal'] = format_tanggal_indo($request['tanggal_izin']);
            $request['jam_pulang'] = date('H:i', strtotime($request['jam_pulang_diajukan'])) . ' WIB';
            $request['alasan_penolakan'] = $decision === 'rejected' ? $rejectionReason : null;
            $waResult = notifikasi_keputusan_izin_pulang($request, $request['no_hp'], $decision === 'approved');
            $message = $decision === 'approved' ? 'Pengajuan berhasil disetujui.' : 'Pengajuan berhasil ditolak.';
            if (!$waResult['success']) {
                $message .= ' Notifikasi WhatsApp gagal dikirim; status tercatat pada log.';
            } else {
                $message .= ' Notifikasi WhatsApp telah dikirim kepada karyawan.';
            }
            set_flash('success', $message, 'Keputusan Tersimpan');
        } catch (DomainException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            set_flash('warning', $e->getMessage(), 'Keputusan Tidak Tersimpan');
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Gagal memproses keputusan izin pulang awal: ' . $e->getMessage());
            set_flash('danger', 'Terjadi kesalahan sistem saat memproses pengajuan.', 'Gagal');
        }
    }
    header('Location: ' . BASE_URL . 'admin/izin_pulang.php');
    exit;
}

$scopeDemo = is_demo_user() ? ' AND k.nik = :demo_nik' : '';
$scopeParams = is_demo_user() ? [':demo_nik' => DEMO_ACCOUNTS['karyawan']['nik']] : [];
$stmtStats = $db->prepare("
    SELECT COUNT(*) AS total,
           SUM(i.status = 'pending') AS pending,
           SUM(i.status = 'approved') AS approved,
           SUM(i.status = 'rejected') AS rejected
    FROM izin_pulang_awal i
    JOIN karyawan k ON k.id = i.id_karyawan
    WHERE 1=1 {$scopeDemo}
");
$stmtStats->execute($scopeParams);
$stats = $stmtStats->fetch();

$conditions = ['1=1'];
$params = $scopeParams;
if ($scopeDemo !== '') {
    $conditions[] = 'k.nik = :demo_nik';
}
if (in_array($statusFilter, $allowedStatuses, true)) {
    $conditions[] = 'i.status = :status';
    $params[':status'] = $statusFilter;
}
if ($dateFilter !== '') {
    $conditions[] = 'i.tanggal_izin = :tanggal';
    $params[':tanggal'] = $dateFilter;
}
if ($search !== '') {
    $conditions[] = '(k.nama LIKE :search_nama OR k.nik LIKE :search_nik)';
    $params[':search_nama'] = '%' . $search . '%';
    $params[':search_nik'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $conditions);
$stmtCount = $db->prepare("
    SELECT COUNT(*)
    FROM izin_pulang_awal i
    JOIN karyawan k ON k.id = i.id_karyawan
    WHERE {$whereSql}
");
$stmtCount->execute($params);
$totalRequests = (int)$stmtCount->fetchColumn();
$perPage = 20;
$page = max(1, min((int)($_GET['page'] ?? 1), max(1, (int)ceil($totalRequests / $perPage))));
$totalPages = max(1, (int)ceil($totalRequests / $perPage));
$offset = ($page - 1) * $perPage;
$stmtList = $db->prepare("
    SELECT i.*, k.nama, k.nik, k.jabatan
    FROM izin_pulang_awal i
    JOIN karyawan k ON k.id = i.id_karyawan
    WHERE {$whereSql}
    ORDER BY CASE i.status WHEN 'pending' THEN 0 ELSE 1 END, i.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmtList->execute($params);
$requests = $stmtList->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-clipboard-check text-primary me-2"></i>Persetujuan Izin Pulang Awal</h4>
        <p class="text-muted small mb-0">Tinjau dan putuskan pengajuan izin karyawan. Persetujuan dibutuhkan sebelum absen pulang lebih awal.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Total Pengajuan', (int)($stats['total'] ?? 0), 'primary'],
        ['Menunggu Persetujuan', (int)($stats['pending'] ?? 0), 'warning'],
        ['Disetujui', (int)($stats['approved'] ?? 0), 'success'],
        ['Ditolak', (int)($stats['rejected'] ?? 0), 'danger']
    ] as [$label, $count, $color]): ?>
        <div class="col-6 col-xl-3"><div class="card-stat"><div class="stat-content"><div class="stat-label"><?= e($label); ?></div><div class="stat-number text-<?= e($color); ?>"><?= $count; ?></div></div></div></div>
    <?php endforeach; ?>
</div>

<?php if (is_demo_user()): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Mode demo hanya-baca; data yang ditampilkan dibatasi pada akun demo.</div>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><label for="q" class="form-label small">Cari nama / NIK</label><input class="form-control form-control-sm" id="q" name="q" value="<?= e($search); ?>"></div>
            <div class="col-md-3"><label for="tanggal" class="form-label small">Tanggal izin</label><input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= e($dateFilter); ?>"></div>
            <div class="col-md-3"><label for="status" class="form-label small">Status</label><select class="form-select form-select-sm" id="status" name="status"><option value="">Semua status</option><?php foreach ($allowedStatuses as $option): ?><option value="<?= e($option); ?>" <?= $statusFilter === $option ? 'selected' : ''; ?>><?= e(ucfirst($option)); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary btn-sm">Filter</button><a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL; ?>admin/izin_pulang.php">Reset</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead><tr><th>Karyawan</th><th>Tanggal / Jam</th><th>Alasan</th><th>Keterangan</th><th>Status</th><th>Keputusan</th></tr></thead>
            <tbody>
            <?php if (!$requests): ?>
                <tr><td colspan="6" class="text-center py-5 text-muted">Tidak ada pengajuan pada filter yang dipilih.</td></tr>
            <?php else: foreach ($requests as $request): ?>
                <tr>
                    <td><strong><?= e($request['nama']); ?></strong><br><small class="text-muted"><?= e($request['nik']); ?> · <?= e($request['jabatan']); ?></small></td>
                    <td><?= format_tanggal_indo($request['tanggal_izin']); ?><br><span class="font-monospace"><?= date('H:i', strtotime($request['jam_pulang_diajukan'])); ?> WIB</span><br><small class="text-muted">Diajukan <?= date('d/m/Y H:i', strtotime($request['created_at'])); ?></small></td>
                    <td><?= e($request['kategori_alasan']); ?></td>
                    <td class="small" style="max-width:220px; overflow-wrap:anywhere;"><?= e($request['keterangan'] ?: '-'); ?></td>
                    <td>
                        <?php $statusClasses = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary']; ?>
                        <span class="badge bg-<?= $statusClasses[$request['status']] ?? 'secondary'; ?>-subtle text-<?= $statusClasses[$request['status']] ?? 'secondary'; ?>"><?= e(ucfirst($request['status'])); ?></span>
                        <?php if ($request['status'] === 'rejected' && $request['alasan_penolakan']): ?><div class="small text-muted mt-1"><?= e($request['alasan_penolakan']); ?></div><?php endif; ?>
                    </td>
                    <td style="min-width:190px;">
                        <?php if ($request['status'] === 'pending' && !is_demo_user()): ?>
                            <form method="POST" class="d-flex flex-column gap-2">
                                <?= csrf_field(); ?><input type="hidden" name="action" value="keputusan_izin"><input type="hidden" name="izin_id" value="<?= (int)$request['id']; ?>">
                                <button class="btn btn-success btn-sm" name="keputusan" value="approved"><i class="bi bi-check-circle me-1"></i>Setujui</button>
                                <input type="text" class="form-control form-control-sm" name="alasan_penolakan" maxlength="1000" placeholder="Alasan penolakan">
                                <button class="btn btn-outline-danger btn-sm" name="keputusan" value="rejected"><i class="bi bi-x-circle me-1"></i>Tolak</button>
                            </form>
                        <?php elseif ($request['diputuskan_pada']): ?>
                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($request['diputuskan_pada'])); ?> WIB</small>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-3" aria-label="Pagination pengajuan izin">
        <ul class="pagination pagination-sm justify-content-center">
            <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                <?php $pageQuery = $_GET; $pageQuery['page'] = $pageNumber; ?>
                <li class="page-item <?= $pageNumber === $page ? 'active' : ''; ?>"><a class="page-link" href="?<?= e(http_build_query($pageQuery)); ?>"><?= $pageNumber; ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
