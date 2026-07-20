<?php
echo "<h2>🔍 Cek File System</h2>";
echo "<pre>";

// Cek folder saat ini
echo "Folder saat ini: " . __DIR__ . "\n\n";

// Daftar file yang harus ada
$files = [
    'cek_spp.php',
    'config_midtrans.php',
    'proses_payment.php',
    'payment_success.php',
    'payment_pending.php',
    'koneksi.php',
    'midtrans/Midtrans.php',
    'midtrans/Snap.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✅ " . $file . " - ADA\n";
    } else {
        echo "❌ " . $file . " - TIDAK ADA\n";
    }
}

echo "</pre>";
?>