<?php
// Halaman public - tidak perlu session_start()
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Portal SPP - SD Mujahidin Pontianak</title>

    <!-- CSS Eksternal -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body class="landing-page">

    <main class="landing-container">
        <!-- Hero Section -->
        <div class="hero-section" style="text-align: center; display: flex; flex-direction: column; align-items: center; width: 100%; margin: 0 auto 40px auto;">
            <div class="logo-icon" style="margin: 0 auto 16px;">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1 style="text-align: center; width: 100%;">Sumbangan Pembinaan Pendidikan</h1>
            <h2 class="subtitle" style="text-align: center; width: 100%;">SD Mujahidin Pontianak</h2>
        </div>

        <!-- Dua Card dalam satu baris -->
        <div class="main-container">
            <!-- Card Cek Mandiri -->
            <div class="card card-stretch">
                <div class="card-content">
                    <div class="card-icon">
                        <i class="fas fa-search-dollar"></i>
                    </div>
                    <h3>Cek Mandiri</h3>
                    <p>Masukkan NIS atau Nama Siswa untuk melihat status pembayaran SPP putra/putri Anda.</p>
                </div>
                <div class="card-actions">
                    <form id="formCekMandiri" action="cek_spp.php" method="GET">
                        <div class="form-group">
                            <input type="text" name="nis" placeholder="Masukkan NIS / Nama Siswa" required autocomplete="off" class="form-control">
                        </div>
                        <button type="button" class="btn btn-primary w-100" onclick="confirmCekMandiri()">
                            <i class="fas fa-arrow-right"></i> Lihat Riwayat SPP
                        </button>
                    </form>
                </div>
            </div>

            <!-- Card Akses Petugas -->
            <div class="card card-stretch">
                <div class="card-content">
                    <div class="card-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3>Akses Petugas</h3>
                    <p>Khusus untuk Administrator, Bendahara, dan Kepala Sekolah untuk mengelola data transaksi dan laporan keuangan.</p>
                </div>
                <div class="card-actions">
                    <a href="login.php" class="btn btn-outline w-100" style="text-decoration: none; display: inline-block; text-align: center;">
                        <i class="fas fa-sign-in-alt"></i> Masuk ke Sistem
                    </a>
                    <span class="link-lupa-pw" onclick="openLupaPassword()" style="cursor: pointer; display: block; margin-top: 10px;">
                        <i class="fas fa-key"></i> Lupa Password?
                    </span>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>&copy; 2026 SD Mujahidin Pontianak | Sistem Informasi Pembayaran SPP</p>
        </div>
    </main>

    <!-- ===== MODAL KONFIRMASI CEK MANDIRI ===== -->
    <div id="modalKonfirmasiCek" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-search"></i> Konfirmasi Pencarian</h3>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin melihat riwayat SPP dengan data tersebut?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modalKonfirmasiCek')">Batal</button>
                <button class="btn btn-primary" onclick="submitCekMandiri()">
                    <i class="fas fa-arrow-right"></i> Lanjutkan
                </button>
            </div>
        </div>
    </div>

    <!-- ===== MODAL LUPA PASSWORD ===== -->
    <div id="modalLupaPassword" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Lupa Password</h3>
            </div>
            <div class="modal-body">
                <p>Masukkan email atau username Anda, kami akan kirimkan link reset password.</p>
                <form id="formLupaPassword" onsubmit="return false;">
                    <div class="form-group">
                        <label>Email / Username</label>
                        <input type="text" id="inputLupa" placeholder="Masukkan email atau username" required class="form-control">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('modalLupaPassword')">Batal</button>
                        <button type="button" class="btn btn-primary" onclick="prosesLupaPassword()">
                            <i class="fas fa-paper-plane"></i> Kirim
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Eksternal -->
    <script src="assets/js/main.js"></script>
    <script>
        function confirmCekMandiri() {
            const nisInput = document.querySelector('input[name="nis"]').value;
            if (nisInput.trim() === '') {
                alert('Silakan masukkan NIS atau Nama Siswa terlebih dahulu.');
                return;
            }
            document.getElementById('modalKonfirmasiCek').style.display = 'flex';
        }

        function submitCekMandiri() {
            document.getElementById('formCekMandiri').submit();
        }

        function openLupaPassword() {
            document.getElementById('modalLupaPassword').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function prosesLupaPassword() {
            const input = document.getElementById('inputLupa').value;
            if (input.trim() === '') {
                alert('Silakan masukkan email atau username!');
                return;
            }
            alert('Instruksi reset password telah dikirim jika akun terdaftar.');
            closeModal('modalLupaPassword');
        }
    </script>
</body>

</html>