<?php
/**
 * Modul Status & Pengujian WhatsApp Gateway Fonnte - Premium UI
 * Hak akses khusus Administrator
 */

$pageTitle = 'Status Integrasi WhatsApp Gateway Fonnte';
$activeMenu = 'whatsapp';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/fonnte.php';

require_admin_write();

$db = getDBConnection();
$adminUser = current_user();

$testResult = null;
if (empty($_SESSION['wa_test_nonce'])) {
    $_SESSION['wa_test_nonce'] = bin2hex(random_bytes(32));
}
$waTestNonce = $_SESSION['wa_test_nonce'];

// HANDLER UJI KIRIM PESAN OLEH ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'uji_kirim_wa') {
    $submittedNonce = $_POST['wa_test_nonce'] ?? '';
    $nonceIsValid = is_string($submittedNonce) && hash_equals($waTestNonce, $submittedNonce);
    $_SESSION['wa_test_nonce'] = bin2hex(random_bytes(32));
    $waTestNonce = $_SESSION['wa_test_nonce'];

    if (!$nonceIsValid) {
        set_flash('warning', 'Permintaan pengujian ini sudah diproses atau token sekali-pakai tidak valid. Muat ulang halaman sebelum mencoba lagi.', 'Pengiriman Ganda Dicegah');
    } elseif (!validate_csrf()) {
        set_flash('danger', 'Token CSRF tidak sah.', 'Gagal');
    } else {
        $noTujuan = trim($_POST['no_tujuan'] ?? '');
        $pesanUji = trim($_POST['pesan_uji'] ?? '');

        if (empty($noTujuan) || empty($pesanUji)) {
            set_flash('warning', 'Nomor tujuan dan isi pesan uji wajib diisi.', 'Form Belum Lengkap');
        } else {
            $testResult = kirim_wa_fonnte($noTujuan, $pesanUji, 'TEST_MANUAL');

            catat_aktivitas(
                $adminUser['id'],
                $adminUser['nama'],
                'admin',
                'UJI_WA',
                "Menguji pengiriman pesan WhatsApp ke nomor {$noTujuan} (Status: {$testResult['status']})"
            );

            if ($testResult['success']) {
                set_flash('success', "Pesan uji coba berhasil dikirim ke gateway Fonnte untuk nomor {$noTujuan}.", "Pengiriman Berhasil");
            } elseif (!empty($testResult['simulated'])) {
                set_flash('info', "Pengiriman disimulasikan: {$testResult['message']}", "Mode Simulasi .env");
            } else {
                set_flash('danger', "Pengiriman gagal: {$testResult['message']}", "Gateway Merespon Gagal");
            }
        }
    }
}

// Statistik Log Notifikasi
$stmtStats = $db->query("
    SELECT 
        COUNT(*) AS total,
        COUNT(CASE WHEN status = 'SUCCESS' THEN 1 END) AS sukses,
        COUNT(CASE WHEN status IN ('FAILED', 'INVALID_TARGET') THEN 1 END) AS gagal,
        COUNT(CASE WHEN status = 'SIMULATED' THEN 1 END) AS simulasi
    FROM log_notifikasi
");
$stats = $stmtStats->fetch();

// Daftar 20 Log Notifikasi Terbaru
$stmtLogs = $db->query("
    SELECT id, penerima, tipe_notifikasi, pesan, status, response_api, created_at
    FROM log_notifikasi
    ORDER BY id DESC
    LIMIT 20
");
$logs = $stmtLogs->fetchAll();

// Cek status konfigurasi token
$isTokenConfigured = (!empty(FONNTE_TOKEN) && !in_array(FONNTE_TOKEN, ['ISI_TOKEN_FONNTE_ANDA', 'FONNTE_TOKEN_ANDA', 'YOUR_FONNTE_TOKEN_HERE']));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-whatsapp text-success me-2"></i>Integrasi WhatsApp Gateway Fonnte</h4>
        <p class="text-muted small mb-0">Status konfigurasi variabel lingkungan (.env), audit log pesan, dan alat uji pengiriman</p>
    </div>
</div>

<!-- 4 KARTU STATISTIK GATEWAY -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon primary">
                <i class="bi bi-chat-dots-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Notifikasi</div>
                <div class="stat-number"><?= (int)($stats['total'] ?? 0); ?></div>
                <small class="text-muted" style="font-size: 0.72rem;">Seluruh riwayat dispatches</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon success">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Pengiriman Sukses</div>
                <div class="stat-number text-success"><?= (int)($stats['sukses'] ?? 0); ?></div>
                <small class="text-muted" style="font-size: 0.72rem;">Diterima server Fonnte</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon cyan">
                <i class="bi bi-laptop"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Mode Simulasi</div>
                <div class="stat-number text-cyan-600"><?= (int)($stats['simulasi'] ?? 0); ?></div>
                <small class="text-muted" style="font-size: 0.72rem;">Uji lokal tanpa kuota</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card-stat">
            <div class="stat-icon danger">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Pengiriman Gagal</div>
                <div class="stat-number text-danger"><?= (int)($stats['gagal'] ?? 0); ?></div>
                <small class="text-muted" style="font-size: 0.72rem;">Error jaringan / API</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Status Konfigurasi Server -->
    <div class="col-lg-5">
        <div class="card card-custom h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-shield-check text-primary me-2"></i>Status Konfigurasi (.env)</h6>
            </div>
            <div class="card-body p-4">
                <div class="p-3 border rounded-3 bg-slate-50 mb-3 small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Status Token Fonnte:</span>
                        <?php if ($isTokenConfigured): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Terkonfigurasi Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-info-subtle text-info border border-info-subtle">Placeholder (Mode Simulasi)</span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Target Nomor Admin:</span>
                        <strong class="font-monospace text-dark"><?= e(ADMIN_WHATSAPP ?: 'Belum diatur'); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Endpoint Fonnte:</span>
                        <code class="text-muted" style="font-size: 0.72rem;"><?= e(FONNTE_API_URL); ?></code>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Metode Pengiriman:</span>
                        <span class="badge bg-light text-dark border">cURL HTTP POST (Timeout 15s)</span>
                    </div>
                </div>

                <div class="alert alert-light border small text-muted mb-0">
                    <i class="bi bi-lock-fill text-secondary me-1"></i>
                    Token API disimpan secara aman di file <code>.env</code> pada root proyek dan tidak pernah ditampilkan ke browser untuk mencegah kebocoran kredensial.
                </div>
            </div>
        </div>
    </div>

    <!-- Form Uji Coba Kirim Pesan -->
    <div class="col-lg-7">
        <div class="card card-custom h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-send text-primary me-2"></i>Alat Pengujian Kirim Pesan</h6>
            </div>
            <div class="card-body p-4">
                <form action="" method="POST" class="protect-submit" novalidate>
                    <?= csrf_field(); ?>
                    <input type="hidden" name="wa_test_nonce" value="<?= e($waTestNonce); ?>">
                    <input type="hidden" name="action" value="uji_kirim_wa">

                    <div class="mb-3">
                        <label for="no_tujuan" class="form-label small text-muted fw-semibold">Nomor WhatsApp Tujuan: <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-success"><i class="bi bi-whatsapp"></i></span>
                            <input type="tel" class="form-control" id="no_tujuan" name="no_tujuan" placeholder="08xxxxxxxxxx atau 628xxxxxxxxxx" value="<?= e(ADMIN_WHATSAPP); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="pesan_uji" class="form-label small text-muted fw-semibold">Isi Pesan Pengujian: <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="pesan_uji" name="pesan_uji" rows="3" required>Halo, ini adalah pesan uji koneksi dari Portal Presensi Karyawan.</textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send-fill me-1"></i> Eksekusi Pengujian Kirim WA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Log Notifikasi -->
<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Audit Notifikasi WhatsApp (20 Terakhir)</h6>
        <span class="badge bg-light text-dark border">Tabel log_notifikasi</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Waktu</th>
                        <th>Penerima</th>
                        <th>Tipe Notifikasi</th>
                        <th>Status</th>
                        <th>Pratinjau Pesan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-chat-left-dots fs-1 d-block mb-2 text-secondary"></i>
                                Belum ada riwayat pengiriman notifikasi tercatat.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($logs as $l): ?>
                            <tr>
                                <td class="text-center text-muted fw-semibold"><?= $no++; ?></td>
                                <td class="small text-muted font-monospace">
                                    <?= date('d/m/Y H:i:s', strtotime($l['created_at'])); ?>
                                </td>
                                <td>
                                    <span class="font-monospace fw-semibold text-dark"><?= e($l['penerima']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace small"><?= e($l['tipe_notifikasi']); ?></span>
                                </td>
                                <td>
                                    <?php if ($l['status'] === 'SUCCESS'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Terkirim</span>
                                    <?php elseif ($l['status'] === 'SIMULATED'): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">Simulasi</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Gagal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small" style="max-width: 400px; overflow-wrap: anywhere;">
                                    <?= nl2br(e($l['pesan'])); ?>
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
