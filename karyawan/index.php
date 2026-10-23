<?php
/**
 * Modul Manajemen Data Karyawan - Premium UI
 * Fitur pencarian, filter jabatan, status akun, sorting, dan pagination
 */

$pageTitle = 'Manajemen Master Karyawan';
$activeMenu = 'karyawan';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = getDBConnection();
$isDemo = is_demo_user();

// Parameter Pencarian & Filter
$keyword      = trim($_GET['q'] ?? '');
$filterJabatan = trim($_GET['jabatan'] ?? '');
$filterStatus = trim($_GET['status_akun'] ?? '');
$sortBy       = trim($_GET['sort'] ?? 'terbaru');

// Ambil daftar unik jabatan untuk dropdown filter
$jabatanSql = "SELECT DISTINCT jabatan FROM karyawan WHERE jabatan IS NOT NULL AND jabatan != ''";
if ($isDemo) {
    $jabatanSql .= ' AND nik = ' . $db->quote(DEMO_ACCOUNTS['karyawan']['nik']);
}
$daftarJabatan = $db->query($jabatanSql . ' ORDER BY jabatan ASC')->fetchAll(PDO::FETCH_COLUMN);

// Bangun Klausa WHERE dengan Prepared Statement
$conditions = [];
$params = [];
if ($isDemo) {
    $conditions[] = 'nik = :demo_nik';
    $params[':demo_nik'] = DEMO_ACCOUNTS['karyawan']['nik'];
}

if (!empty($keyword)) {
    $conditions[] = '(nik LIKE :kw1 OR nama LIKE :kw2 OR email LIKE :kw3 OR no_hp LIKE :kw4)';
    $params[':kw1'] = "%{$keyword}%";
    $params[':kw2'] = "%{$keyword}%";
    $params[':kw3'] = "%{$keyword}%";
    $params[':kw4'] = "%{$keyword}%";
}
if (!empty($filterJabatan)) {
    $conditions[] = 'jabatan = :jabatan';
    $params[':jabatan'] = $filterJabatan;
}
if (!empty($filterStatus)) {
    $conditions[] = 'status_akun = :status_akun';
    $params[':status_akun'] = $filterStatus;
}

$whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Pengurutan (Sorting) yang Aman
$orderSql = match ($sortBy) {
    'nama_asc'   => 'ORDER BY nama ASC',
    'nama_desc'  => 'ORDER BY nama DESC',
    'terlama'    => 'ORDER BY id ASC',
    default      => 'ORDER BY id DESC'
};

// 1. Hitung Total Data untuk Pagination
$stmtCount = $db->prepare("SELECT COUNT(*) AS total FROM karyawan {$whereSql}");
$stmtCount->execute($params);
$totalRecords = (int)($stmtCount->fetch()['total'] ?? 0);

// 2. Pagination Logic
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, ceil($totalRecords / $perPage));
$offset = ($page - 1) * $perPage;

// 3. Query Data Karyawan
$stmt = $db->prepare("
    SELECT id, nik, nama, jabatan, no_hp, email, role, status_akun, created_at
    FROM karyawan
    {$whereSql}
    {$orderSql}
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$karyawanList = $stmt->fetchAll();

// Query String untuk Pagination
$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);
$paginationUrl = BASE_URL . 'karyawan/index.php?' . ($queryString ? $queryString . '&' : '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-people-fill text-primary me-2"></i>Data & Akun Karyawan</h4>
        <p class="text-muted small mb-0">Kelola master data profil, penugasan jabatan, dan status keaktifan akun karyawan</p>
    </div>
    <?php if (!$isDemo): ?>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL; ?>karyawan/tambah.php" class="btn btn-primary d-flex align-items-center gap-2 shadow-xs">
            <i class="bi bi-person-plus-fill"></i>
            <span>Tambah Karyawan Baru</span>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($isDemo): ?>
    <div class="alert alert-info border-0 border-start border-4 border-info bg-white shadow-sm">
        <i class="bi bi-info-circle-fill me-2"></i>Daftar ini hanya berisi akun contoh demo. Perubahan data tidak tersedia pada mode demo.
    </div>
<?php endif; ?>

<!-- Card Filter dan Pencarian Tingkat Lanjut -->
<div class="card card-custom mb-4">
    <div class="card-body p-4">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="q" class="form-label small text-muted">Pencarian Cepat:</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="q" name="q" placeholder="Cari nama, NIK, email, no HP..." value="<?= e($keyword); ?>">
                </div>
            </div>

            <div class="col-sm-6 col-md-3">
                <label for="jabatan" class="form-label small text-muted">Filter Jabatan:</label>
                <select class="form-select form-select-sm" id="jabatan" name="jabatan">
                    <option value="">Semua Jabatan</option>
                    <?php foreach ($daftarJabatan as $j): ?>
                        <option value="<?= e($j); ?>" <?= ($filterJabatan === $j) ? 'selected' : ''; ?>><?= e($j); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-sm-6 col-md-2">
                <label for="status_akun" class="form-label small text-muted">Status Akun:</label>
                <select class="form-select form-select-sm" id="status_akun" name="status_akun">
                    <option value="">Semua Status</option>
                    <option value="aktif" <?= ($filterStatus === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                    <option value="nonaktif" <?= ($filterStatus === 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                </select>
            </div>

            <div class="col-sm-6 col-md-2">
                <label for="sort" class="form-label small text-muted">Urutkan:</label>
                <select class="form-select form-select-sm" id="sort" name="sort">
                    <option value="terbaru" <?= ($sortBy === 'terbaru') ? 'selected' : ''; ?>>Terbaru</option>
                    <option value="nama_asc" <?= ($sortBy === 'nama_asc') ? 'selected' : ''; ?>>Nama (A - Z)</option>
                    <option value="nama_desc" <?= ($sortBy === 'nama_desc') ? 'selected' : ''; ?>>Nama (Z - A)</option>
                    <option value="terlama" <?= ($sortBy === 'terlama') ? 'selected' : ''; ?>>Terlama</option>
                </select>
            </div>

            <div class="col-sm-6 col-md-1 d-flex align-items-end gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100" title="Terapkan Filter">
                    <i class="bi bi-funnel-fill"></i>
                </button>
                <a href="<?= BASE_URL; ?>karyawan/index.php" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Card Tabel Karyawan -->
<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold text-dark">Daftar Karyawan Terdaftar</h6>
            <small class="text-muted">Total: <strong><?= $totalRecords; ?></strong> karyawan ditemukan</small>
        </div>
        <span class="badge bg-light text-dark border">Halaman <?= $page; ?> dari <?= $totalPages; ?></span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Karyawan</th>
                        <th>Jabatan</th>
                        <th>Nomor WhatsApp</th>
                        <th>Email Akun</th>
                        <th>Status</th>
                        <th>Role</th>
                        <th class="text-center" style="width: 150px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($karyawanList)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                                Tidak ditemukan data karyawan yang sesuai dengan kriteria pencarian.
                                <div class="mt-2">
                                    <a href="<?= BASE_URL; ?>karyawan/index.php" class="btn btn-sm btn-outline-secondary">Reset Pencarian</a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($karyawanList as $k): 
                        ?>
                            <tr>
                                <td class="text-center text-muted fw-semibold"><?= $no++; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar-bubble" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                            <?= strtoupper(substr($k['nama'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <a href="<?= BASE_URL; ?>karyawan/detail.php?id=<?= $k['id']; ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                                <?= e($k['nama']); ?>
                                            </a>
                                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.72rem;"><?= e($k['nik']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($k['jabatan']); ?></td>
                                <td>
                                    <a href="https://wa.me/<?= e(normalize_phone($k['no_hp'])); ?>" target="_blank" class="text-decoration-none text-success small d-flex align-items-center gap-1 font-monospace" title="Kirim Pesan WhatsApp">
                                        <i class="bi bi-whatsapp"></i>
                                        <span><?= e(format_phone_display($k['no_hp'])); ?></span>
                                    </a>
                                </td>
                                <td class="small text-muted"><?= e($k['email']); ?></td>
                                <td>
                                    <?php if (($k['status_akun'] ?? 'aktif') === 'aktif'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($k['role'] === 'admin'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">ADMIN</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">KARYAWAN</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_URL; ?>karyawan/detail.php?id=<?= $k['id']; ?>" class="btn btn-outline-info" title="Lihat Profil & Riwayat">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (!$isDemo): ?>
                                            <a href="<?= BASE_URL; ?>karyawan/edit.php?id=<?= $k['id']; ?>" class="btn btn-outline-primary" title="Ubah Data">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalKonfirmasiHapus" data-id="<?= $k['id']; ?>" data-nama="<?= e($k['nama']); ?>" data-nik="<?= e($k['nik']); ?>" title="Hapus Data">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
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

<?php if (!$isDemo): ?>
<!-- Modal Konfirmasi Hapus Data Karyawan -->
<div class="modal fade" id="modalKonfirmasiHapus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Karyawan</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form action="<?= BASE_URL; ?>karyawan/hapus.php" method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="id" id="hapusKaryawanId" value="">
                <div class="modal-body py-4">
                    <p class="mb-2">Apakah Anda yakin ingin menghapus data karyawan berikut?</p>
                    <div class="card bg-light border p-3 my-3">
                        <div class="fw-bold fs-6 text-dark" id="hapusKaryawanNama">-</div>
                        <div class="small text-muted font-monospace">NIK: <span id="hapusKaryawanNik">-</span></div>
                    </div>
                    <div class="alert alert-warning small d-flex align-items-center mb-0">
                        <i class="bi bi-info-circle-fill me-2 fs-5 flex-shrink-0"></i>
                        <div>
                            Setelah penghapusan berhasil, sistem akan mengirim <strong>notifikasi WhatsApp pemberitahuan penghapusan permanen</strong> jika nomor tujuan tersedia dan valid.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash-fill me-1"></i> Ya, Hapus Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
