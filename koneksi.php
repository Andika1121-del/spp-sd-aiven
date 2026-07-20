<?php
require_once __DIR__ . '/vendor/autoload.php';

// Menggunakan safe load agar tidak crash jika .env bermasalah
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Berikan nilai default (fallback) jika $_ENV kosong
$host   = $_ENV['DB_HOST'] ?? 'localhost';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';
$dbname = $_ENV['DB_NAME'] ?? 'db_spp_sd_new';

// Tetap gunakan $conn, tapi kita siapkan juga $koneksi sebagai cadangan
$conn = mysqli_connect($host, $user, $pass, $dbname);
$koneksi = $conn;

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Mulai session secara otomatis jika belum aktif (supaya fungsi cek role di bawah berjalan)
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
