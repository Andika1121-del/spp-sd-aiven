<?php

/** @var mysqli $conn */
session_start();
include '../koneksi.php';

// Cek login admin
if (!isset($_SESSION['login']) || $_SESSION['level'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Administrator');

// Ambil filter jika ada
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

// Konversi bulan angka ke nama
$nama_bulan_arr = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember'
];
$bulan_nama = $nama_bulan_arr[sprintf('%02d', $bulan)];

// Query data pembayaran
$query = "SELECT p.*, s.nama_siswa, s.id_siswa, s.alamat, k.nama_kelas, t.bulan, t.tahun 
          FROM pembayaran p
          JOIN tagihan t ON p.id_tagihan = t.id_tagihan
          JOIN siswa s ON t.id_siswa = s.id_siswa
          JOIN kelas k ON s.id_kelas = k.id_kelas
          WHERE t.bulan = '$bulan_nama' AND t.tahun = '$tahun'";

// Tambahkan filter kelas jika ada
if (!empty($kelas)) {
    $query .= " AND k.nama_kelas = '$kelas'";
}

$query .= " ORDER BY p.tanggal_bayar DESC";
$result = mysqli_query($conn, $query);

// Hitung total nominal
$total_nominal = 0;
$total_transaksi = 0;
if ($result && mysqli_num_rows($result) > 0) {
    $total_transaksi = mysqli_num_rows($result);
    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)) {
        $total_nominal += $row['jumlah_bayar'];
    }
    mysqli_data_seek($result, 0);
}

// Ambil data kelas untuk filter
$query_kelas = mysqli_query($conn, "SELECT DISTINCT nama_kelas FROM kelas ORDER BY nama_kelas ASC");

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Laporan Admin - SD Mujahidin</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="../assets/js/main.js" defer></script>

    <style>
        a,
        .card a,
        .sidebar-link,
        .navbar a {
            text-decoration: none !important;
        }

        .filter-box {
            display: flex;
            align-items: flex-end;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            min-width: 150px;
        }
    </style>
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
        <a href="dashboard.php" class="sidebar-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="siswa.php" class="sidebar-link <?= $current_page == 'siswa.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Data Siswa</span>
        </a>
        <a href="kelas.php" class="sidebar-link <?= $current_page == 'kelas.php' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard"></i>
            <span>Data Kelas</span>
        </a>
        <a href="user.php" class="sidebar-link <?= $current_page == 'user.php' ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i>
            <span>Data User</span>
        </a>
        <a href="spp.php" class="sidebar-link <?= $current_page == 'spp.php' ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Jenis Pembayaran</span>
        </a>
        <a href="laporan_admin.php" class="sidebar-link active">
            <i class="fas fa-print"></i>
            <span>Laporan</span>
        </a>
        <a href="import_siswa_excel.php" class="sidebar-link <?= $current_page == 'import_siswa_excel.php' ? 'active' : '' ?>">
            <i class="fas fa-file-import"></i>
            <span>Import Siswa</span>
        </a>
    </div>

    <div class="content">
        <div class="fade-in-up">
            <div class="card">
                <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h2 style="color: var(--primary-bg); margin-bottom: 4px; display: flex; align-items: center; gap: 10px; text-decoration: none;">
                            <i class="fas fa-print"></i> Laporan Pembayaran SPP
                        </h2>
                        <p style="color: var(--gray); font-size: 13px; margin: 0;">
                            Selamat datang, <strong><?= htmlspecialchars($nama_user) ?></strong> |
                            Rekapitulasi pembayaran bulanan siswa
                        </p>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button onclick="window.print()" class="btn btn-warning">
                            <i class="fas fa-print"></i> Cetak Laporan
                        </button>
                        <button onclick="exportToExcel('laporanTable', 'laporan_spp_<?= $bulan_nama ?>_<?= $tahun ?>')" class="btn btn-primary">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h4><i class="fas fa-chart-line"></i> Total Transaksi</h4>
                    <div class="stat-number"><?= number_format($total_transaksi) ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-money-bill-wave"></i> Total Pemasukan</h4>
                    <div class="stat-number success">Rp <?= number_format($total_nominal, 0, ',', '.') ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-calendar"></i> Periode Laporan</h4>
                    <div class="stat-number info"><?= $bulan_nama ?> <?= $tahun ?></div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Filter Periode & Kelas</h3>
                </div>
                <div class="card-body">
                    <form action="" method="GET" class="filter-box">
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Bulan</label>
                            <select name="bulan">
                                <?php for ($i = 1; $i <= 12; $i++):
                                    $bln = sprintf('%02d', $i);
                                    $selected = ($bln == $bulan) ? 'selected' : '';
                                ?>
                                    <option value="<?= $bln ?>" <?= $selected ?>><?= $nama_bulan_arr[$bln] ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                            <select name="tahun">
                                <?php for ($y = 2023; $y <= 2026; $y++): ?>
                                    <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-chalkboard"></i> Kelas</label>
                            <select name="kelas">
                                <option value="">Semua Kelas</option>
                                <?php if ($query_kelas && mysqli_num_rows($query_kelas) > 0): ?>
                                    <?php mysqli_data_seek($query_kelas, 0); ?>
                                    <?php while ($k = mysqli_fetch_assoc($query_kelas)): ?>
                                        <option value="<?= $k['nama_kelas'] ?>" <?= $kelas == $k['nama_kelas'] ? 'selected' : '' ?>><?= $k['nama_kelas'] ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Tampilkan
                            </button>
                            <a href="laporan_admin.php" class="btn btn-outline" style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px;">
                                <i class="fas fa-undo-alt" style="margin-right: 5px;"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header flex-between">
                    <h3><i class="fas fa-list"></i> Rincian Pembayaran SPP</h3>
                    <span class="badge badge-primary">Total <?= number_format($total_transaksi) ?> Data</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-wrapper">
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <table id="laporanTable" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>ID Siswa</th>
                                        <th>Nama Siswa</th>
                                        <th>Kelas</th>
                                        <th>Tanggal Bayar</th>
                                        <th>Periode</th>
                                        <th>Jumlah</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    mysqli_data_seek($result, 0);
                                    while ($data = mysqli_fetch_assoc($result)):
                                    ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><span class="font-medium">#<?= $data['id_siswa'] ?></span></td>
                                            <td>
                                                <strong><?= htmlspecialchars($data['nama_siswa']) ?></strong>
                                                <br><small class="text-gray"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars(substr($data['alamat'], 0, 20)) ?></small>
                                            </td>
                                            <td><span class="badge-kelas"><?= htmlspecialchars($data['nama_kelas']) ?></span></td>
                                            <td><?= date('d/m/Y H:i', strtotime($data['tanggal_bayar'])) ?></td>
                                            <td><?= $data['bulan'] ?> <?= $data['tahun'] ?></td>
                                            <td class="font-bold text-success">Rp <?= number_format($data['jumlah_bayar'], 0, ',', '.') ?></td>
                                            <td><span class="badge badge-success"><i class="fas fa-check-circle"></i> Lunas</span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot style="background: #f8fafc;">
                                    <tr>
                                        <th colspan="6" style="text-align: right;">Total Keseluruhan:</th>
                                        <th class="font-bold text-primary">Rp <?= number_format($total_nominal, 0, ',', '.') ?></th>
                                        <th></th>
                                    </tr>
                                    </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Tidak ada data pembayaran untuk periode <?= $bulan_nama ?> <?= $tahun ?></p>
                                <p class="text-gray" style="font-size: 12px;">Coba ubah filter periode atau kelas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="footer">
                <p>&copy; 2024 SD Mujahidin - Sistem Informasi Pembayaran SPP | Admin Control Panel</p>
            </div>
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

            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                        sidebar.classList.remove('active');
                    }
                }
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                }
            });
        }
    </script>

</body>

</html>