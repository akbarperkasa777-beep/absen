<?php

$enabled = filter_var($argv[1] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$_ENV['DEMO_LOGIN_ENABLED'] = $enabled ? 'true' : 'false';
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require __DIR__ . '/../auth/login.php';
$html = ob_get_clean();

$hasAdminDemoButton = str_contains($html, 'Masuk sebagai Admin Demo');
$hasEmployeeDemoButton = str_contains($html, 'Masuk sebagai Karyawan Demo');
$buttonsVisible = $hasAdminDemoButton && $hasEmployeeDemoButton;

if ($buttonsVisible !== $enabled) {
    fwrite(STDERR, "Demo login visibility did not match DEMO_LOGIN_ENABLED.\n");
    exit(1);
}

echo 'Demo login page check passed (enabled=' . ($enabled ? 'true' : 'false') . ').' . PHP_EOL;
