<?php
require_once __DIR__ . '/vendor/autoload.php';

// Menggunakan safe load agar tidak crash jika .env bermasalah
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Berikan nilai default (fallback)
$host   = $_ENV['DB_HOST'] ?? 'localhost';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';
$dbname = $_ENV['DB_NAME'] ?? 'db_spp_sd_new';
$port   = $_ENV['DB_PORT'] ?? 3306;

// Inisialisasi koneksi mysqli
$conn = mysqli_init();

if ($host !== 'localhost' && $host !== '127.0.0.1') {
    // Mengaktifkan SSL untuk Aiven MySQL
    mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
    mysqli_real_connect($conn, $host, $user, $pass, $dbname, (int)$port, NULL, MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);
} else {
    // Koneksi standar untuk localhost/Laragon
    mysqli_real_connect($conn, $host, $user, $pass, $dbname, (int)$port);
}

$koneksi = $conn;

if (mysqli_connect_errno()) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Mulai session secara otomatis jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('isBendahara')) {
    function isBendahara()
    {
        return isset($_SESSION['level']) && $_SESSION['level'] == 'bendahara';
    }
}
if (!function_exists('isAdmin')) {
    function isAdmin()
    {
        return isset($_SESSION['level']) && $_SESSION['level'] == 'admin';
    }
}
if (!function_exists('isKepsek')) {
    function isKepsek()
    {
        return isset($_SESSION['level']) && $_SESSION['level'] == 'kepsek';
    }
}
