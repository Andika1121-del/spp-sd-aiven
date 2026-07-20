<?php
$order_id = $_GET['order_id'] ?? '';
$nis = $_GET['nis'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Pembayaran Pending - SD Mujahidin</title>

    <!-- CSS GLOBAL -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <meta http-equiv="refresh" content="10;url=cek_spp.php?nis=<?= urlencode($nis) ?>">
</head>

<body class="pending-page">

    <div class="pending-container">
        <div class="pending-card">
            <div class="pending-icon">
                <i class="fas fa-clock"></i>
            </div>
            <h2>Menunggu Pembayaran</h2>
            <p>Silakan selesaikan pembayaran Anda melalui metode yang dipilih.</p>
            <p>Status akan otomatis berubah setelah pembayaran dikonfirmasi.</p>
            <div class="pending-buttons">
                <a href="cek_spp.php?nis=<?= urlencode($nis) ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <p class="pending-redirect">Mengalihkan dalam 10 detik...</p>
        </div>
    </div>

</body>

</html>