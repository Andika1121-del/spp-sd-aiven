<?php
$order_id = $_GET['order_id'] ?? '';
$nis = isset($_GET['nis']) ? $_GET['nis'] : '';
$error_msg = isset($_GET['error']) ? $_GET['error'] : 'Terjadi kesalahan pada pembayaran';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Pembayaran Gagal - SD Mujahidin</title>

    <!-- CSS GLOBAL -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <meta http-equiv="refresh" content="5;url=cek_spp.php?nis=<?= urlencode($nis) ?>">
</head>

<body class="error-page">

    <div class="error-container">
        <div class="error-card">
            <div class="error-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h2>Pembayaran Gagal</h2>
            <p><?= htmlspecialchars($error_msg) ?></p>
            <p>Silakan coba lagi atau hubungi pihak sekolah.</p>
            <div class="error-buttons">
                <a href="cek_spp.php?nis=<?= urlencode($nis) ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <p class="error-redirect">Mengalihkan dalam 5 detik...</p>
        </div>
    </div>

</body>

</html>