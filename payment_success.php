<?php
session_start();
include 'koneksi.php';

$order_id = $_GET['order_id'] ?? '';
$nis = $_GET['nis'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Berhasil - SD Mujahidin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body class="success-page">
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon" style="color: #10b981; font-size: 60px; margin-bottom: 15px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Pembayaran Berhasil! 🎉</h2>
            <p>Terima kasih, pembayaran SPP Anda sedang diproses oleh sistem.</p>
            <p class="text-muted">Order ID: <strong><?= htmlspecialchars($order_id) ?></strong></p>
            <div class="success-buttons" style="margin-top: 20px;">
                <a href="cek_spp.php?nis=<?= urlencode($nis) ?>" class="btn btn-primary">
                    <i class="fas fa-eye"></i> Lihat Riwayat
                </a>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-home"></i> Kembali ke Beranda
                </a>
            </div>
            <!-- Hitung mundur penutupan halaman -->
            <p class="success-redirect" style="margin-top: 25px; font-size: 12px; color: #64748b;">
                Halaman ini akan menutup otomatis dalam <strong id="countdown">5</strong> detik...
            </p>
        </div>
    </div>

    <script>
        // Set waktu hitung mundur (5 detik)
        let timeLeft = 5;
        const countdownElement = document.getElementById('countdown');

        const timer = setInterval(function() {
            timeLeft--;
            countdownElement.textContent = timeLeft;

            if (timeLeft <= 0) {
                clearInterval(timer);
                // Tutup tab/halaman browser otomatis
                window.close();
            }
        }, 1000);
    </script>
</body>

</html>