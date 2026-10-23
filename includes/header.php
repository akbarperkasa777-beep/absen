 <?php
/**
 * Header Template - Premium UI
 * Portal Presensi Karyawan
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle  = $pageTitle ?? APP_NAME;
$activeMenu = $activeMenu ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?> - <?= APP_NAME; ?></title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons 1.11.3 CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Chart.js CDN untuk Visualisasi Statistik -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

    <!-- Custom Premium CSS -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/style.css">
</head>
<body>
<?php if (empty($isAuthPage)): ?>
<div class="sidebar-overlay"></div>
<div class="app-wrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="app-main">
        <?php require_once __DIR__ . '/topbar.php'; ?>
        <main class="app-content">
            <?= display_flash(); ?>
<?php endif; ?>
