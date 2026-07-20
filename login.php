<?php
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'koneksi.php';

// Jika sudah login
if (isset($_SESSION['login']) && $_SESSION['login'] == true) {
    $level = $_SESSION['level'];

    if ($level == 'admin') {
        header("Location: admin/dashboard.php");
    } elseif ($level == 'bendahara') {
        header("Location: bendahara/dashboard.php");
    } elseif ($level == 'kepsek') {
        header("Location: kepsek/dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = md5($_POST['password']);

    $query = "SELECT * FROM `user` WHERE username='$username' AND password='$password'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        die("Query Error : " . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        $_SESSION['login'] = true;
        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['level'] = $user['level'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['nama'] = $user['nama_lengkap'];

        if ($user['level'] == 'admin') {
            header("Location: admin/dashboard.php");
        } elseif ($user['level'] == 'bendahara') {
            header("Location: bendahara/dashboard.php");
        } elseif ($user['level'] == 'kepsek') {
            header("Location: kepsek/dashboard.php");
        } else {
            $error = "Level user tidak dikenali!";
        }
        exit();
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Petugas - SD Mujahidin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="login-page">

    <div class="login-card">
        <div class="login-icon">
            <i class="fas fa-graduation-cap"></i>
        </div>

        <h2>Selamat Datang</h2>
        <p>Portal Manajemen SPP SD Mujahidin</p>

        <?php if ($error): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required autofocus>
            </div>

            <div class="input-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                    <button type="button" class="toggle-password" data-target="password">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Masuk ke Dashboard
            </button>
        </form>

        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Kembali ke Halaman Utama
        </a>

        <div class="login-footer">
            <p>© 2024 SD Mujahidin - Sistem Informasi Pembayaran SPP</p>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>

</html>