<?php

/** @var mysqli $conn */
session_start();
include '../koneksi.php';

if (!isKepsek()) {
    header("Location: ../index.php");
    exit();
}

// ========== AMBIL FILTER ==========
$bulan_filter = isset($_GET['bulan']) ? mysqli_real_escape_string($conn, $_GET['bulan']) : '';
$tahun_filter = isset($_GET['tahun']) ? mysqli_real_escape_string($conn, $_GET['tahun']) : date('Y');
$kelas_filter = isset($_GET['kelas']) ? mysqli_real_escape_string($conn, $_GET['kelas']) : '';

// Mapping bulan (untuk tampilan)
$map_bln = [
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
if (empty($bulan_filter)) {
    $bulan_filter = $map_bln[date('m')];
}

// ========== FUNGSI PEMBANTU (AMAN) ==========
function get_total($conn, $sql)
{
    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return (int) $row['total'];
    }
    return 0;
}

// ========== FILTER KELAS (SATU DEFINISI) ==========
$sql_kelas = "";
if (!empty($kelas_filter)) {
    $sql_kelas = " AND kelas.nama_kelas = '" . mysqli_real_escape_string($conn, $kelas_filter) . "'";
}

// ========== STATISTIK RINGKASAN ==========
$stat_lunas = get_total($conn, "
    SELECT COUNT(*) as total
    FROM tagihan
    JOIN siswa ON tagihan.id_siswa = siswa.id_siswa
    JOIN kelas ON siswa.id_kelas = kelas.id_kelas
    WHERE tagihan.bulan = '$bulan_filter'
      AND tagihan.tahun = '$tahun_filter'
      AND tagihan.status = 'Lunas'
      $sql_kelas
");

$stat_cicil = get_total($conn, "
    SELECT COUNT(*) as total
    FROM tagihan
    JOIN siswa ON tagihan.id_siswa = siswa.id_siswa
    JOIN kelas ON siswa.id_kelas = kelas.id_kelas
    WHERE tagihan.bulan = '$bulan_filter'
      AND tagihan.tahun = '$tahun_filter'
      AND tagihan.status = 'Cicil'
      $sql_kelas
");

$stat_total_siswa = get_total($conn, "
    SELECT COUNT(*) as total
    FROM siswa
    JOIN kelas ON siswa.id_kelas = kelas.id_kelas
    WHERE 1=1 $sql_kelas
");

$stat_belum = $stat_total_siswa - ($stat_lunas + $stat_cicil);

$total_uang = get_total($conn, "
    SELECT COALESCE(SUM(p.jumlah_bayar), 0) as total
    FROM pembayaran p
    JOIN tagihan t ON p.id_tagihan = t.id_tagihan
    JOIN siswa s ON t.id_siswa = s.id_siswa
    JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE t.bulan = '$bulan_filter'
      AND t.tahun = '$tahun_filter'
      $sql_kelas
");

// ========== DATA PER KELAS ==========
$query_per_kelas = mysqli_query($conn, "
    SELECT
        kelas.nama_kelas,
        COUNT(siswa.id_siswa) as total_siswa,
        SUM(CASE WHEN tagihan.status = 'Lunas' THEN 1 ELSE 0 END) as lunas,
        SUM(CASE WHEN tagihan.status = 'Cicil' THEN 1 ELSE 0 END) as cicil,
        SUM(CASE WHEN tagihan.status IS NULL OR tagihan.status = 'Belum Bayar' THEN 1 ELSE 0 END) as belum
    FROM kelas
    LEFT JOIN siswa ON kelas.id_kelas = siswa.id_kelas
    LEFT JOIN tagihan ON siswa.id_siswa = tagihan.id_siswa
        AND tagihan.bulan = '$bulan_filter'
        AND tagihan.tahun = '$tahun_filter'
    WHERE 1=1 $sql_kelas
    GROUP BY kelas.id_kelas
    ORDER BY kelas.nama_kelas
");

$nama_user = $_SESSION['nama'] ?? 'Kepala Sekolah';
$bulan_list = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember'
];
$tahun_list = ['2024', '2025', '2026', '2027'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pembayaran - Kepsek SD Mujahidin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body class="kepsek-page">

    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-chalkboard-user"></i>
            <strong>SD Mujahidin</strong>
            <span>- Panel Kepala Sekolah</span>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="laporan.php" class="active"><i class="fas fa-chart-line"></i> Laporan Pembayaran</a>
    </div>

    <div class="content">
        <!-- Kop Surat untuk Print -->
        <div class="kop-surat-print">
            <h2>SD MUJAHIDIN PONTIANAK</h2>
            <p>Jl. Jenderal Ahmad Yani, Pontianak</p>
            <p>Telp. (0561) 123456 | Email: info@sdmujahidin.sch.id</p>
            <hr>
        </div>

        <div class="fade-in-up">
            <!-- Header -->
            <div class="card welcome-card mb-4">
                <div class="card-body flex-between">
                    <div>
                        <h2 class="page-title">Rekapitulasi Pembayaran SPP</h2>
                        <p class="text-white-80">Selamat datang, <strong><?= htmlspecialchars($nama_user) ?></strong></p>
                    </div>
                    <div class="no-print"><span class="badge badge-primary"><i class="fas fa-chalkboard-user"></i> Kepala Sekolah</span></div>
                </div>
            </div>

            <!-- Filter Box -->
            <div class="card mb-4 no-print">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Filter Laporan</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="filter-group">
                            <label><i class="fas fa-chalkboard"></i> Kelas</label>
                            <select name="kelas">
                                <option value="">Semua Kelas</option>
                                <?php
                                $list_kelas = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas");
                                while ($k = mysqli_fetch_assoc($list_kelas)):
                                ?>
                                    <option value="<?= $k['nama_kelas'] ?>" <?= $kelas_filter == $k['nama_kelas'] ? 'selected' : '' ?>>
                                        <?= $k['nama_kelas'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                            <select name="tahun">
                                <?php foreach ($tahun_list as $t): ?>
                                    <option value="<?= $t ?>" <?= $tahun_filter == $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-eye"></i> Tampilkan</button>
                        <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Cetak Laporan</button>
                    </form>
                </div>
            </div>

            <!-- Judul Laporan Print -->
            <div class="judul-laporan-print">
                <h3>LAPORAN PEMBAYARAN SPP</h3>
                <p>Periode: <?= $bulan_filter ?> <?= $tahun_filter ?></p>
                <p>Tanggal Cetak: <?= date('d F Y') ?></p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4><i class="fas fa-money-bill-wave"></i> Pendapatan Periode Ini</h4>
                    <div class="stat-number text-success">Rp <?= number_format($total_uang, 0, ',', '.') ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-check-circle"></i> Siswa Lunas</h4>
                    <div class="stat-number text-primary"><?= number_format($stat_lunas) ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-times-circle"></i> Belum Bayar</h4>
                    <div class="stat-number text-danger"><?= number_format($stat_belum) ?></div>
                </div>
            </div>

            <!-- Tabel Laporan Per Kelas -->
            <div class="card">
                <div class="card-header flex-between">
                    <h3><i class="fas fa-chalkboard"></i> Laporan Per Kelas - <?= $bulan_filter ?> <?= $tahun_filter ?></h3>
                    <span class="badge badge-primary no-print"><?= number_format($stat_total_siswa) ?> Total Siswa</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Kelas</th>
                                    <th>Total Siswa</th>
                                    <th>Lunas</th>
                                    <th>Cicil</th>
                                    <th>Belum Bayar</th>
                                    <th>Persentase Lunas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_all_siswa = 0;
                                $total_all_lunas = 0;
                                while ($row = mysqli_fetch_assoc($query_per_kelas)):
                                    $persen = ($row['total_siswa'] > 0) ? round(($row['lunas'] / $row['total_siswa']) * 100) : 0;
                                    $total_all_siswa += $row['total_siswa'];
                                    $total_all_lunas += $row['lunas'];
                                ?>
                                    <tr>
                                        <td class="fw-600"><?= htmlspecialchars($row['nama_kelas']) ?></td>
                                        <td><?= $row['total_siswa'] ?> Orang</td>
                                        <td><span class="badge badge-success"><?= $row['lunas'] ?></span></td>
                                        <td><span class="badge badge-warning"><?= $row['cicil'] ?></span></td>
                                        <td><span class="badge badge-danger"><?= $row['belum'] ?></span></td>
                                        <td>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $persen ?>%"></div>
                                                <span class="progress-text"><?= $persen ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="bg-light fw-600">
                                    <td><strong>TOTAL</strong></td>
                                    <td><strong><?= $total_all_siswa ?> Orang</strong></td>
                                    <td><strong><?= $total_all_lunas ?></strong></td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td><strong><?= $total_all_siswa > 0 ? round(($total_all_lunas / $total_all_siswa) * 100) : 0 ?>%</strong> Keseluruhan</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tanda Tangan -->
            <div class="print-only-signature">
                <p>Pontianak, <?= date('d F Y') ?></p>
                <p>Mengetahui,</p>
                <p style="margin-top: 40px;">Kepala Sekolah SD Mujahidin</p>
                <p><u>_________________________</u></p>
                <p><?= htmlspecialchars($nama_user) ?></p>
                <p>NIP. __________________</p>
            </div>

            <div class="footer no-print">
                <p>&copy; 2024 SD Mujahidin - Sistem Informasi Pembayaran SPP</p>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>