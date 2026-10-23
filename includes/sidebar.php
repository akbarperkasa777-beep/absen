<?php
/**
 * Sidebar Navigation - Premium UI
 * Menyesuaikan struktur menu untuk Administrator dan Karyawan
 */

$user = current_user();
$isAdmin = ($user && ($user['role'] ?? '') === 'admin');
$initials = strtoupper(substr($user['nama'] ?? 'U', 0, 1));
$pendingEarlyLeaveCount = 0;
if ($isAdmin) {
    $db = getDBConnection();
    $stmtPendingLeave = $db->prepare("
        SELECT COUNT(*)
        FROM izin_pulang_awal i
        JOIN karyawan k ON k.id = i.id_karyawan
        WHERE i.status = 'pending' " . (is_demo_user() ? 'AND k.nik = :demo_nik' : '')
    );
    $stmtPendingLeave->execute(is_demo_user() ? [':demo_nik' => DEMO_ACCOUNTS['karyawan']['nik']] : []);
    $pendingEarlyLeaveCount = (int)$stmtPendingLeave->fetchColumn();
}
?>
<aside class="app-sidebar">
    <a href="<?= $isAdmin ? BASE_URL . 'admin/index.php' : BASE_URL . 'karyawan/dashboard.php'; ?>" class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-fingerprint"></i>
        </div>
        <div class="d-flex flex-column">
            <span class="fw-bold fs-6 text-white leading-none">Presensi<span class="text-info">WA</span></span>
            <small class="text-white-50" style="font-size: 0.68rem; letter-spacing: 0.05em;">ENTERPRISE SAAS</small>
        </div>
    </a>

    <ul class="sidebar-menu">
        <?php if ($isAdmin): ?>
            <li class="menu-header">Menu Utama</li>
            <li>
                <a href="<?= BASE_URL; ?>admin/index.php" class="nav-link <?= ($activeMenu === 'dashboard') ? 'active' : ''; ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard Admin</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>karyawan/index.php" class="nav-link <?= ($activeMenu === 'karyawan') ? 'active' : ''; ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Data Karyawan</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>admin/absensi.php" class="nav-link <?= ($activeMenu === 'absensi_admin') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar2-check-fill"></i>
                    <span>Data & Koreksi Absensi</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>admin/izin_pulang.php" class="nav-link <?= ($activeMenu === 'izin_pulang') ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Persetujuan Izin Pulang</span>
                    <?php if ($pendingEarlyLeaveCount > 0): ?><span class="badge bg-warning text-dark ms-auto"><?= $pendingEarlyLeaveCount; ?></span><?php endif; ?>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>admin/laporan.php" class="nav-link <?= ($activeMenu === 'laporan') ? 'active' : ''; ?>">
                    <i class="bi bi-file-earmark-bar-graph-fill"></i>
                    <span>Laporan Presensi</span>
                </a>
            </li>

            <?php if (!is_demo_user()): ?>
            <li class="menu-header">Sistem & Gateway</li>
            <li>
                <a href="<?= BASE_URL; ?>admin/whatsapp.php" class="nav-link <?= ($activeMenu === 'whatsapp') ? 'active' : ''; ?>">
                    <i class="bi bi-whatsapp"></i>
                    <span>Gateway Fonnte WA</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>admin/log_aktivitas.php" class="nav-link <?= ($activeMenu === 'log_aktivitas') ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>Log Aktivitas</span>
                </a>
            </li>

            <li class="menu-header">Portal Karyawan</li>
            <li>
                <a href="<?= BASE_URL; ?>absensi/index.php" class="nav-link <?= ($activeMenu === 'absensi_mandiri') ? 'active' : ''; ?>">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Presensi Masuk / Pulang</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>karyawan/riwayat.php" class="nav-link <?= ($activeMenu === 'riwayat_saya') ? 'active' : ''; ?>">
                    <i class="bi bi-journal-check"></i>
                    <span>Riwayat Presensi Saya</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>karyawan/kalender.php" class="nav-link <?= ($activeMenu === 'kalender') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar3"></i>
                    <span>Kalender Kehadiran</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>karyawan/profil.php" class="nav-link <?= ($activeMenu === 'profil') ? 'active' : ''; ?>">
                    <i class="bi bi-person-gear"></i>
                    <span>Profil Saya</span>
                </a>
            </li>
            <?php endif; ?>

        <?php else: ?>
            <li class="menu-header">Karyawan</li>
            <li>
                <a href="<?= BASE_URL; ?>karyawan/dashboard.php" class="nav-link <?= ($activeMenu === 'dashboard_karyawan') ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard Saya</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>absensi/index.php" class="nav-link <?= ($activeMenu === 'absensi_mandiri') ? 'active' : ''; ?>">
                    <i class="bi bi-fingerprint"></i>
                    <span>Presensi Masuk / Pulang</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>karyawan/riwayat.php" class="nav-link <?= ($activeMenu === 'riwayat_saya') ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>Riwayat Presensi Saya</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL; ?>karyawan/kalender.php" class="nav-link <?= ($activeMenu === 'kalender') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-event"></i>
                    <span>Kalender Kehadiran</span>
                </a>
            </li>

            <li class="menu-header">Pengaturan Akun</li>
            <li>
                <a href="<?= BASE_URL; ?>karyawan/profil.php" class="nav-link <?= ($activeMenu === 'profil') ? 'active' : ''; ?>">
                    <i class="bi bi-person-circle"></i>
                    <span>Profil & Keamanan</span>
                </a>
            </li>
        <?php endif; ?>

        <li class="menu-header">Keluar</li>
        <li>
            <a href="<?= BASE_URL; ?>auth/logout.php" class="nav-link text-danger-emphasis" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
                <i class="bi bi-box-arrow-left text-danger"></i>
                <span class="text-white-50">Logout Sesi</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL; ?>karyawan/profil.php" class="d-flex align-items-center gap-2 text-decoration-none text-white">
            <div class="user-avatar-bubble">
                <?= $initials; ?>
            </div>
            <div class="text-truncate flex-grow-1">
                <div class="text-white small fw-semibold text-truncate"><?= e($user['nama'] ?? 'Pengguna'); ?></div>
                <div class="text-slate-400" style="font-size: 0.72rem;"><?= is_demo_user() ? 'Akun Demo' : ucfirst(e($user['role'] ?? 'Karyawan')); ?></div>
            </div>
            <i class="bi bi-gear text-slate-400 small"></i>
        </a>
    </div>
</aside>
