<?php
session_start();
include '../koneksi.php';

echo "<h1>Test Koneksi Database</h1>";
echo "<hr>";

// Test koneksi
if ($conn) {
    echo "✅ Koneksi ke database BERHASIL<br>";
} else {
    echo "❌ Koneksi GAGAL: " . mysqli_connect_error() . "<br>";
}

// Test query ke tabel pembayaran
$test = mysqli_query($conn, "SELECT COUNT(*) as total FROM pembayaran");
if ($test) {
    $data = mysqli_fetch_assoc($test);
    echo "✅ Tabel pembayaran ada, total data: " . $data['total'] . "<br>";
} else {
    echo "❌ Error query: " . mysqli_error($conn) . "<br>";
}

// Test session
echo "<br>📋 Session data:<br>";
echo "login: " . (isset($_SESSION['login']) ? $_SESSION['login'] : 'TIDAK ADA') . "<br>";
echo "level: " . (isset($_SESSION['level']) ? $_SESSION['level'] : 'TIDAK ADA') . "<br>";
echo "nama: " . (isset($_SESSION['nama']) ? $_SESSION['nama'] : 'TIDAK ADA') . "<br>";

// Cek apakah user adalah bendahara
if (isset($_SESSION['level']) && $_SESSION['level'] == 'bendahara') {
    echo "<br>✅ Anda login sebagai BENDAHARA<br>";
} else {
    echo "<br>❌ Anda TIDAK login sebagai bendahara. Silakan login ulang.<br>";
    echo "<a href='../index.php'>Klik untuk login</a>";
}
?>