<?php

/** @var mysqli $conn */
session_start();
include_once __DIR__ . '/../koneksi.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Administrator');

// Statistik
$total_siswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa"))['total'] ?? 0;
$total_kelas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM kelas"))['total'] ?? 0;
$total_user  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM user"))['total'] ?? 0;
$total_tagihan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tagihan"))['total'] ?? 0;
$total_tunggakan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tagihan WHERE status != 'Lunas'"))['total'] ?? 0;
$total_lunas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tagihan WHERE status = 'Lunas'"))['total'] ?? 0;

// Ambil data kelas untuk distribusi
$query_kelas = mysqli_query($conn, "SELECT k.nama_kelas, COUNT(s.id_siswa) as jumlah 
                                     FROM kelas k 
                                     LEFT JOIN siswa s ON k.id_kelas = s.id_kelas 
                                     GROUP BY k.id_kelas 
                                     ORDER BY k.nama_kelas ASC");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Admin Dashboard - SD Mujahidin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>
</head>

<body>

    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-shield-alt"></i>
            <strong>SD Mujahidin</strong>
            <span style="font-weight: normal; font-size: 13px;">- Admin Panel</span>
        </div>
        <a href="../logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php" class="active">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="siswa.php">
            <i class="fas fa-users"></i>
            <span>Data Siswa</span>
        </a>
        <a href="kelas.php">
            <i class="fas fa-chalkboard"></i>
            <span>Data Kelas</span>
        </a>
        <a href="user.php">
            <i class="fas fa-user-shield"></i>
            <span>Data User</span>
        </a>
        <a href="spp.php">
            <i class="fas fa-money-bill-wave"></i>
            <span>Jenis Pembayaran</span>
        </a>
        <a href="laporan_admin.php">
            <i class="fas fa-print"></i>
            <span>Laporan</span>
        </a>
        <a href="import_siswa_excel.php"><i class="fas fa-file-import"></i> Import Siswa</a>
    </div>

    <div class="content">
        <!-- Welcome Card -->
        <div class="card">
            <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h2 style="color: var(--primary-bg); margin-bottom: 4px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-chart-line"></i> System Overview
                    </h2>
                    <p style="color: var(--gray); font-size: 13px; margin: 0;">
                        Selamat datang, <strong><?= htmlspecialchars($nama_user) ?></strong>
                    </p>
                </div>
                <div>
                    <span class="badge badge-primary">
                        <i class="fas fa-shield-alt"></i> Administrator
                    </span>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4><i class="fas fa-users"></i> Total Siswa</h4>
                <div class="stat-number"><?= number_format($total_siswa) ?></div>
            </div>
            <div class="stat-card">
                <h4><i class="fas fa-chalkboard"></i> Data Kelas</h4>
                <div class="stat-number"><?= number_format($total_kelas) ?></div>
            </div>
            <div class="stat-card">
                <h4><i class="fas fa-user-shield"></i> Akun Pengguna</h4>
                <div class="stat-number"><?= number_format($total_user) ?></div>
            </div>
            <div class="stat-card">
                <h4><i class="fas fa-file-invoice"></i> Total Tagihan</h4>
                <div class="stat-number"><?= number_format($total_tagihan) ?></div>
            </div>
            <div class="stat-card">
                <h4><i class="fas fa-check-circle"></i> Tagihan Lunas</h4>
                <div class="stat-number success"><?= number_format($total_lunas) ?></div>
            </div>
            <div class="stat-card">
                <h4><i class="fas fa-exclamation-triangle"></i> Masih Tunggakan</h4>
                <div class="stat-number danger"><?= number_format($total_tunggakan) ?></div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="row" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 24px;">
            <!-- Quick Actions Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Pintasan Cepat</h3>
                </div>
                <div class="card-body">
                    <div class="action-buttons" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 16px;">
                        <a href="siswa.php" class="btn btn-primary" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-user-plus"></i> Tambah Siswa
                        </a>
                        <a href="user.php" class="btn btn-primary" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-key"></i> Kelola User
                        </a>
                        <a href="kelas.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-chalkboard"></i> Kelola Kelas
                        </a>
                        <a href="spp.php" class="btn btn-outline" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fas fa-money-bill"></i> Jenis SPP
                        </a>
                    </div>
                    <a href="tagihan_generate.php" class="btn btn-warning" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--warning); color: white;">
                        <i class="fas fa-cogs"></i> Generate Tagihan Bulanan
                    </a>
                    <div class="info-note" style="margin-top: 16px; padding: 12px; background: #f0fdf4; border-radius: 10px; border-left: 3px solid var(--primary);">
                        <p style="font-size: 12px; color: var(--gray); margin: 0;">
                            <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                            Gunakan tombol <strong>Generate Tagihan</strong> setiap awal bulan untuk membuat tagihan otomatis.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Recent Users Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Akun Pengguna Terbaru</h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-wrapper" style="padding: 0;">
                        <table style="margin-bottom: 0; width: 100%;">
                            <thead>
                                <tr>
                                    <th>Pengguna</th>
                                    <th style="text-align: center;">Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $q_user = mysqli_query($conn, "SELECT * FROM user ORDER BY id_user DESC LIMIT 5");
                                if (mysqli_num_rows($q_user) > 0):
                                    while ($u = mysqli_fetch_assoc($q_user)):
                                ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600;"><?= htmlspecialchars($u['nama_lengkap'] ?? $u['username']) ?></div>
                                                <div style="font-size: 11px; color: var(--gray);">@<?= htmlspecialchars($u['username']) ?></div>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="badge badge-primary"><?= htmlspecialchars($u['level']) ?></span>
                                            </td>
                                        </tr>
                                    <?php
                                    endwhile;
                                else:
                                    ?>
                                    <tr>
                                        <td colspan="2" style="text-align: center; padding: 30px; color: var(--gray);">
                                            <i class="fas fa-inbox"></i> Belum ada data user
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Distribution Chart Section -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Distribusi Siswa per Kelas</h3>
            </div>
            <div class="card-body">
                <?php
                $max_siswa = 0;
                $kelas_data = [];
                while ($row = mysqli_fetch_assoc($query_kelas)) {
                    $kelas_data[] = $row;
                    if ($row['jumlah'] > $max_siswa) $max_siswa = $row['jumlah'];
                }

                if (count($kelas_data) > 0):
                    foreach ($kelas_data as $row):
                        $persen = $max_siswa > 0 ? ($row['jumlah'] / $max_siswa) * 100 : 0;
                ?>
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px; flex-wrap: wrap;">
                            <div style="width: 100px; font-weight: 600; color: var(--dark);">
                                <?= htmlspecialchars($row['nama_kelas']) ?>
                            </div>
                            <div style="flex: 1; min-width: 150px;">
                                <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                    <div style="width: <?= $persen ?>%; height: 100%; background: var(--primary); border-radius: 4px;"></div>
                                </div>
                            </div>
                            <div style="width: 100px; text-align: right; font-size: 13px; color: var(--gray);">
                                <?= $row['jumlah'] ?> siswa
                            </div>
                            <div style="width: 45px; text-align: right; font-size: 13px; font-weight: 600; color: var(--primary);">
                                <?= round($persen) ?>%
                            </div>
                        </div>
                    <?php
                    endforeach;
                else:
                    ?>
                    <div class="empty-state" style="padding: 40px;">
                        <i class="fas fa-inbox"></i>
                        <p>Belum ada data kelas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; 2024 SD Mujahidin - Sistem Informasi Pembayaran SPP | Admin Control Panel</p>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                        sidebar.classList.remove('active');
                    }
                }
            });

            // Close sidebar on resize to desktop
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                }
            });
        }
    </script>

</body>

</html>