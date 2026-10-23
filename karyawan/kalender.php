<?php
/**
 * Modul Kalender Presensi Karyawan - Premium UI
 * Menampilkan kalender bulanan dengan penanda tanggal presensi aktual
 */

$pageTitle = 'Kalender Kehadiran';
$activeMenu = 'kalender';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user = current_user();
$karyawanId = (int)$user['id'];
$db = getDBConnection();

// Tangkap Bulan & Tahun (Default bulan & tahun saat ini)
$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

if ($bulan < 1) { $bulan = 12; $tahun--; }
if ($bulan > 12) { $bulan = 1; $tahun++; }

// Ambil seluruh data absensi karyawan pada bulan & tahun ini
$stmt = $db->prepare("
    SELECT tanggal, jam_masuk, jam_pulang, status
    FROM absensi
    WHERE id_karyawan = :id AND MONTH(tanggal) = :bulan AND YEAR(tanggal) = :tahun
");
$stmt->execute([
    ':id'    => $karyawanId,
    ':bulan' => $bulan,
    ':tahun' => $tahun
]);
$rawAbsen = $stmt->fetchAll();

// Mapping tanggal ke data presensi
$absenMap = [];
foreach ($rawAbsen as $a) {
    $absenMap[$a['tanggal']] = $a;
}

// Perhitungan Kalender
$firstDayTimestamp = mktime(0, 0, 0, $bulan, 1, $tahun);
$daysInMonth = (int)date('t', $firstDayTimestamp);
$firstDayOfWeek = (int)date('w', $firstDayTimestamp); // 0 = Minggu, 1 = Senin, dst.

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$prevBulan = $bulan - 1;
$prevTahun = $tahun;
if ($prevBulan < 1) { $prevBulan = 12; $prevTahun--; }

$nextBulan = $bulan + 1;
$nextTahun = $tahun;
if ($nextBulan > 12) { $nextBulan = 1; $nextTahun++; }

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (is_demo_user()): ?>
    <div class="alert alert-info border-0 border-start border-4 border-info bg-white shadow-sm">
        <i class="bi bi-info-circle-fill me-2"></i>Kalender ini menampilkan data contoh Karyawan Demo.
    </div>
<?php endif; ?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-calendar-event-fill text-primary me-2"></i>Kalender Kehadiran Saya</h4>
        <p class="text-muted small mb-0">Visualisasi hari kerja dan catatan kehadiran bulanan Anda</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL; ?>karyawan/kalender.php?bulan=<?= $prevBulan; ?>&tahun=<?= $prevTahun; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i>
        </a>
        <span class="fw-bold text-dark px-3 py-1 bg-white border rounded small">
            <?= $namaBulan[$bulan]; ?> <?= $tahun; ?>
        </span>
        <a href="<?= BASE_URL; ?>karyawan/kalender.php?bulan=<?= $nextBulan; ?>&tahun=<?= $nextTahun; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-right"></i>
        </a>
        <a href="<?= BASE_URL; ?>karyawan/kalender.php" class="btn btn-outline-primary btn-sm ms-2">Bulan Ini</a>
    </div>
</div>

<div class="card card-custom">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">Matriks Kehadiran: <?= $namaBulan[$bulan]; ?> <?= $tahun; ?></h6>
        <div class="d-flex gap-3 small">
            <span class="d-flex align-items-center gap-1"><span class="badge bg-success rounded-circle p-1"> </span> Selesai / Pulang</span>
            <span class="d-flex align-items-center gap-1"><span class="badge bg-primary rounded-circle p-1"> </span> Sudah Masuk</span>
            <span class="d-flex align-items-center gap-1"><span class="badge bg-light border rounded-circle p-1"> </span> Tidak Ada Catatan</span>
        </div>
    </div>
    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table table-bordered text-center align-middle mb-0" style="table-layout: fixed;">
                <thead>
                    <tr class="bg-light text-muted small">
                        <th class="py-2 text-danger">Minggu</th>
                        <th class="py-2">Senin</th>
                        <th class="py-2">Selasa</th>
                        <th class="py-2">Rabu</th>
                        <th class="py-2">Kamis</th>
                        <th class="py-2">Jumat</th>
                        <th class="py-2 text-danger">Sabtu</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        $dayCount = 0;
                        // Sel kosong sebelum hari pertama
                        for ($i = 0; $i < $firstDayOfWeek; $i++) {
                            echo '<td class="bg-light text-muted opacity-25 p-3"></td>';
                            $dayCount++;
                        }

                        // Isi tanggal
                        for ($d = 1; $d <= $daysInMonth; $d++) {
                            $dateStr = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
                            $isToday = ($dateStr === date('Y-m-d'));
                            $hasData = isset($absenMap[$dateStr]);
                            $dayOfWeek = ($dayCount % 7);
                            $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);

                            $cellBg = $isToday ? 'bg-primary-subtle border-primary' : ($hasData ? 'bg-white' : ($isWeekend ? 'bg-slate-50' : 'bg-white'));
                            
                            echo "<td class='p-2 align-top {$cellBg}' style='height: 95px;'>";
                            echo "<div class='d-flex justify-content-between align-items-center mb-1'>";
                            echo "<span class='fw-bold " . ($isToday ? 'text-primary' : ($isWeekend ? 'text-danger' : 'text-dark')) . "'>{$d}</span>";
                            if ($isToday) {
                                echo "<span class='badge bg-primary' style='font-size: 0.65rem;'>Hari Ini</span>";
                            }
                            echo "</div>";

                            if ($hasData) {
                                $item = $absenMap[$dateStr];
                                if (!empty($item['jam_pulang'])) {
                                    echo "<div class='badge bg-success-subtle text-success border border-success-subtle w-100 text-truncate my-1' style='font-size: 0.7rem;'>";
                                    echo "<i class='bi bi-check2 me-1'></i>" . date('H:i', strtotime($item['jam_masuk'])) . " - " . date('H:i', strtotime($item['jam_pulang']));
                                    echo "</div>";
                                } else {
                                    echo "<div class='badge bg-primary-subtle text-primary border border-primary-subtle w-100 text-truncate my-1' style='font-size: 0.7rem;'>";
                                    echo "<i class='bi bi-arrow-right me-1'></i>" . date('H:i', strtotime($item['jam_masuk']));
                                    echo "</div>";
                                }
                            }
                            echo "</td>";

                            $dayCount++;
                            if ($dayCount % 7 == 0 && $d != $daysInMonth) {
                                echo "</tr><tr>";
                            }
                        }

                        // Sel kosong setelah hari terakhir
                        while ($dayCount % 7 != 0) {
                            echo '<td class="bg-light text-muted opacity-25 p-3"></td>';
                            $dayCount++;
                        }
                        ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
