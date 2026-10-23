<?php
/**
 * Konfigurasi Database PDO Terproteksi Menggunakan .env dan Dotenv
 * Portal Presensi Karyawan
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Muat variabel lingkungan dari file .env di root direktori proyek
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta');

/**
 * Mendapatkan koneksi PDO Database MySQL
 * Menggunakan Singleton Pattern untuk efisiensi koneksi
 *
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $host     = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port     = $_ENV['DB_PORT'] ?? '3306';
        $name     = $_ENV['DB_DATABASE'] ?? 'db_karyawan';
        $user     = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        if (empty($host) || empty($name) || empty($user)) {
            error_log('Konfigurasi database di file .env belum lengkap.');
            die('
                <div style="font-family: system-ui, sans-serif; text-align: center; padding: 60px 20px;">
                    <h2 style="color: #dc2626; margin-bottom: 8px;">Konfigurasi Database Belum Lengkap</h2>
                    <p style="color: #64748b;">Periksa pengaturan <code>DB_HOST</code>, <code>DB_DATABASE</code>, dan <code>DB_USERNAME</code> pada file <code>.env</code>.</p>
                </div>
            ');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            error_log('Koneksi database gagal: ' . $e->getMessage());
            die('
                <div style="font-family: system-ui, sans-serif; text-align: center; padding: 60px 20px;">
                    <h2 style="color: #dc2626; margin-bottom: 8px;">Database Tidak Dapat Dihubungi</h2>
                    <p style="color: #64748b;">Pastikan layanan MySQL/MariaDB pada XAMPP sudah berjalan dan pengaturan koneksi sudah sesuai.</p>
                </div>
            ');
        }
    }

    return $pdo;
}
