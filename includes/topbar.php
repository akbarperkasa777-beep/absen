<?php
/**
 * Topbar Component - Premium UI
 * Menampilkan kontrol responsif sidebar, jam digital realtime WIB, dan status profil
 */

$user = current_user();
$initials = strtoupper(substr($user['nama'] ?? 'U', 0, 1));
?>
<header class="app-topbar">
    <div class="topbar-left d-flex align-items-center gap-3">
        <button type="button" class="btn btn-light d-lg-none border shadow-xs" id="sidebarToggle" aria-label="Toggle Navigation">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div>
            <h5 class="mb-0 fw-bold text-dark" style="letter-spacing: -0.02em;"><?= e($pageTitle); ?></h5>
            <small class="text-muted d-none d-sm-inline" style="font-size: 0.78rem;">
                <i class="bi bi-calendar3 me-1 text-primary"></i><?= format_tanggal_indo(date('Y-m-d')); ?>
            </small>
        </div>
    </div>

    <div class="topbar-right d-flex align-items-center gap-3">
        <!-- Live Realtime Clock WIB -->
        <div class="d-none d-md-flex align-items-center gap-2 bg-slate-50 border border-slate-200 px-3 py-1 rounded-pill">
            <span class="spinner-grow spinner-grow-sm text-success" style="width: 8px; height: 8px;" role="status"></span>
            <span id="liveClock" class="realtime-clock text-dark fw-bold small">--:--:-- WIB</span>
        </div>

        <!-- User Profile Dropdown Pill -->
        <div class="dropdown">
            <button class="btn btn-white border dropdown-toggle d-flex align-items-center gap-2 px-2 py-1 rounded-pill shadow-xs" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar-bubble" style="width: 30px; height: 30px; font-size: 0.8rem;">
                    <?= $initials; ?>
                </div>
                <div class="text-start d-none d-md-block pe-1">
                    <span class="d-block fw-semibold text-dark small leading-none"><?= e($user['nama'] ?? 'User'); ?></span>
                </div>
                <span class="badge <?= ($user['role'] ?? '') === 'admin' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle'; ?> rounded-pill small ms-1" style="font-size: 0.68rem;">
                    <?= strtoupper(e($user['role'] ?? 'KARYAWAN')); ?>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2 py-2" style="min-width: 210px;">
                <li class="px-3 py-1">
                    <div class="fw-bold text-dark small"><?= e($user['nama'] ?? ''); ?></div>
                    <div class="text-muted" style="font-size: 0.72rem;"><?= e($user['email'] ?? ''); ?></div>
                    <div class="text-secondary font-monospace" style="font-size: 0.7rem;">NIK: <?= e($user['nik'] ?? ''); ?></div>
                </li>
                <li><hr class="dropdown-divider my-2"></li>
                <li>
                    <a class="dropdown-item small d-flex align-items-center gap-2" href="<?= BASE_URL; ?>karyawan/profil.php">
                        <i class="bi bi-person-gear text-primary"></i> Profil & Keamanan
                    </a>
                </li>
                <li>
                    <a class="dropdown-item small d-flex align-items-center gap-2" href="<?= BASE_URL; ?>karyawan/riwayat.php">
                        <i class="bi bi-journal-text text-info"></i> Riwayat Absensi
                    </a>
                </li>
                <li><hr class="dropdown-divider my-2"></li>
                <li>
                    <a class="dropdown-item small text-danger d-flex align-items-center gap-2" href="<?= BASE_URL; ?>auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');">
                        <i class="bi bi-box-arrow-right"></i> Keluar
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
