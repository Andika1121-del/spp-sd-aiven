<?php
session_start();
include_once __DIR__ . '/../koneksi.php';

if (!isKepsek()) {
    header("Location: ../index.php");
    exit();
}

// Statistik
$total_siswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa"))['total'] ?? 0;
$tahun_ini = date('Y');

$map_bln = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
$bulan_ini_nama = $map_bln[date('m')];

// Total pemasukan bulan ini
$pemasukan_bln = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(p.jumlah_bayar), 0) as total FROM pembayaran p JOIN tagihan t ON p.id_tagihan = t.id_tagihan WHERE t.bulan = '$bulan_ini_nama' AND t.tahun = '$tahun_ini'"))['total'];
$total_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_bayar), 0) as total FROM pembayaran"))['total'];
$nama_user = $_SESSION['nama'] ?? 'Kepala Sekolah';

// Target bulanan (contoh Rp 50.000.000)
$target_bulanan = 50000000;
$persen_target = $target_bulanan > 0 ? round(($pemasukan_bln / $target_bulanan) * 100) : 0;

// Query kelas terdisiplin
$query_rank = mysqli_query($conn, "SELECT kelas.nama_kelas, 
    (SUM(CASE WHEN tagihan.status = 'Lunas' THEN 1 ELSE 0 END) / COUNT(siswa.id_siswa) * 100) as persentase
    FROM kelas
    JOIN siswa ON kelas.id_kelas = siswa.id_kelas
    JOIN tagihan ON siswa.id_siswa = tagihan.id_siswa AND tagihan.bulan = '$bulan_ini_nama' AND tagihan.tahun = '$tahun_ini'
    GROUP BY kelas.id_kelas ORDER BY persentase DESC LIMIT 3");

// Query aktivitas terakhir
$query_log = mysqli_query($conn, "SELECT p.tanggal_bayar as tgl_bayar, s.nama_siswa, p.jumlah_bayar as nominal_dibayar 
                                  FROM pembayaran p
                                  JOIN tagihan t ON p.id_tagihan = t.id_tagihan
                                  JOIN siswa s ON t.id_siswa = s.id_siswa 
                                  ORDER BY p.id_pembayaran DESC LIMIT 5");

// Query siswa tunggakan > 3 bulan
$query_tunggakan = mysqli_query($conn, "
    SELECT s.nama_siswa, k.nama_kelas, COUNT(t.id_tagihan) as bulan_tunggak
    FROM siswa s
    JOIN kelas k ON s.id_kelas = k.id_kelas
    JOIN tagihan t ON s.id_siswa = t.id_siswa
    WHERE t.status != 'Lunas'
    GROUP BY s.id_siswa
    HAVING bulan_tunggak > 3
    ORDER BY bulan_tunggak DESC LIMIT 5
");

// Query data kelas untuk grafik batang
$query_kelas_chart = mysqli_query($conn, "SELECT kelas.nama_kelas, 
    (SUM(CASE WHEN tagihan.status = 'Lunas' THEN 1 ELSE 0 END) / COUNT(siswa.id_siswa) * 100) as persentase
    FROM kelas
    LEFT JOIN siswa ON kelas.id_kelas = siswa.id_kelas
    LEFT JOIN tagihan ON siswa.id_siswa = tagihan.id_siswa AND tagihan.bulan = '$bulan_ini_nama' AND tagihan.tahun = '$tahun_ini'
    GROUP BY kelas.id_kelas ORDER BY kelas.nama_kelas ASC");

$kelas_labels = [];
$kelas_data = [];
while ($row = mysqli_fetch_assoc($query_kelas_chart)) {
    $kelas_labels[] = $row['nama_kelas'];
    $kelas_data[] = round($row['persentase'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Dashboard Kepsek - SD Mujahidin</title>

    <!-- CSS GLOBAL -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="laporan.php"><i class="fas fa-chart-line"></i> Laporan Pembayaran</a>
    </div>

    <div class="content">
        <div class="fade-in-up">
            <!-- Header -->
            <div class="card welcome-card mb-4">
                <div class="card-body flex-between">
                    <div>
                        <h2 class="page-title">Dashboard Monitoring</h2>
                        <p class="text-white-80">Selamat datang, <strong><?= htmlspecialchars($nama_user) ?></strong></p>
                    </div>
                    <div><span class="badge badge-primary"><i class="fas fa-chalkboard-user"></i> Kepala Sekolah</span></div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4><i class="fas fa-users"></i> Total Siswa</h4>
                    <div class="stat-number"><?= number_format($total_siswa) ?> <span class="text-sm">Orang</span></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-calendar"></i> Pemasukan <?= $bulan_ini_nama ?></h4>
                    <div class="stat-number text-success">Rp <?= number_format($pemasukan_bln, 0, ',', '.') ?></div>
                </div>
                <div class="stat-card stat-total">
                    <h4><i class="fas fa-database"></i> Total Saldo Masuk</h4>
                    <div class="stat-number text-warning">Rp <?= number_format($total_all, 0, ',', '.') ?></div>
                </div>
            </div>

            <!-- Target vs Realisasi -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-bullseye"></i> Target Bulanan vs Realisasi</h3>
                </div>
                <div class="card-body">
                    <div class="target-wrapper">
                        <div class="target-info flex-between">
                            <span class="text-gray">Target: <strong>Rp <?= number_format($target_bulanan, 0, ',', '.') ?></strong></span>
                            <span class="text-gray">Realisasi: <strong class="text-primary"><?= $persen_target ?>%</strong></span>
                        </div>
                        <div class="target-progress">
                            <div class="target-fill" style="width: <?= min($persen_target, 100) ?>%"></div>
                        </div>
                        <p class="target-status mt-2">
                            <?php if ($persen_target >= 100): ?>
                                <i class="fas fa-trophy text-warning"></i> 🎉 Target tercapai! Lebih <?= $persen_target - 100 ?>% dari target
                            <?php else: ?>
                                <i class="fas fa-chart-line text-primary"></i> 📈 Masih perlu <strong><?= 100 - $persen_target ?>%</strong> lagi untuk mencapai target
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Grafik Utama -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Grafik Pendapatan</h3>
                </div>
                <div class="card-body">
                    <div class="filter-box">
                        <div class="filter-group">
                            <label><i class="fas fa-chart-simple"></i> Rentang Grafik</label>
                            <select id="filterMode" onchange="updateGrafik()">
                                <option value="minggu">7 Hari Terakhir</option>
                                <option value="bulan" selected>Bulanan (Tahun Ini)</option>
                                <option value="semester">Per Semester</option>
                                <option value="tahun">Perbandingan Tahunan</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                            <input type="number" id="inputTahun" class="input-box" value="<?= $tahun_ini ?>" onchange="updateGrafik()" style="width: 100px;">
                        </div>
                    </div>
                    <div class="chart-container" style="height: 350px; margin-top: 20px;">
                        <canvas id="canvasKepsek"></canvas>
                    </div>
                </div>
            </div>

            <!-- Grafik Perbandingan Kelas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-simple"></i> Perbandingan Kelas</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="kelasChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Bottom Grid (3 Kolom) -->
            <div class="row-3-cols">
                <!-- Kelas Terdisiplin -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> Kelas Terdisiplin</h3>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($query_rank) > 0): ?>
                            <ul class="rank-list">
                                <?php $no = 1;
                                while ($rank = mysqli_fetch_assoc($query_rank)):
                                    $icon = ($no == 1) ? '🥇' : (($no == 2) ? '🥈' : '🥉');
                                ?>
                                    <li class="rank-item <?= $no == 1 ? 'gold' : '' ?>">
                                        <span class="rank-icon"><?= $icon ?></span>
                                        <span class="rank-name"><?= $rank['nama_kelas'] ?></span>
                                        <span class="rank-value"><?= round($rank['persentase']) ?>%</span>
                                    </li>
                                <?php $no++;
                                endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-inbox"></i>
                                <p>Belum ada data kelas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Aktivitas Terakhir -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Aktivitas Terakhir</h3>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($query_log) > 0): ?>
                            <ul class="activity-list">
                                <?php while ($log = mysqli_fetch_assoc($query_log)): ?>
                                    <li class="activity-item">
                                        <div class="activity-name"><strong><?= htmlspecialchars($log['nama_siswa']) ?></strong></div>
                                        <div class="activity-amount">Rp <?= number_format($log['nominal_dibayar'], 0, ',', '.') ?></div>
                                        <div class="activity-time"><?= date('d/m H:i', strtotime($log['tgl_bayar'])) ?></div>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-inbox"></i>
                                <p>Belum ada aktivitas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Siswa Tunggakan > 3 Bulan -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Siswa Tunggakan > 3 Bulan</h3>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($query_tunggakan) > 0): ?>
                            <ul class="warning-list">
                                <?php while ($row = mysqli_fetch_assoc($query_tunggakan)): ?>
                                    <li class="warning-item">
                                        <div class="warning-name"><strong><?= htmlspecialchars($row['nama_siswa']) ?></strong> <span class="badge-kelas"><?= $row['nama_kelas'] ?></span></div>
                                        <div class="warning-value badge badge-danger"><?= $row['bulan_tunggak'] ?> bulan</div>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                            <p class="text-sm text-gray mt-3"><i class="fas fa-info-circle"></i> Perlu tindak lanjut segera</p>
                        <?php else: ?>
                            <div class="empty-state"><i class="fas fa-check-circle text-success"></i>
                                <p>✅ Semua siswa dalam kondisi baik</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="footer">
                <p>&copy; 2024 SD Mujahidin - Sistem Informasi Pembayaran SPP</p>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        let myChart;
        let kelasChart;

        function updateGrafik() {
            const mode = document.getElementById('filterMode').value;
            const tahun = document.getElementById('inputTahun').value;

            fetch(`../bendahara/api_grafik_compare.php?mode=${mode}&tahun=${tahun}`)
                .then(res => res.json())
                .then(data => {
                    const ctx = document.getElementById('canvasKepsek').getContext('2d');
                    if (myChart) myChart.destroy();

                    myChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Pendapatan (Rp)',
                                data: data.dataset,
                                borderColor: '#e67e22',
                                backgroundColor: 'rgba(230, 126, 34, 0.1)',
                                fill: true,
                                tension: 0.4,
                                borderWidth: 3,
                                pointRadius: 5,
                                pointBackgroundColor: '#e67e22'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: (v) => 'Rp ' + v.toLocaleString('id-ID')
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: (c) => 'Total: Rp ' + c.raw.toLocaleString('id-ID')
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(err => console.error("Error:", err));
        }

        // Grafik Perbandingan Kelas
        function initKelasChart() {
            const kelasLabels = <?= json_encode($kelas_labels) ?>;
            const kelasData = <?= json_encode($kelas_data) ?>;

            const ctx = document.getElementById('kelasChart').getContext('2d');
            if (kelasChart) kelasChart.destroy();

            kelasChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: kelasLabels,
                    datasets: [{
                        label: 'Persentase Kelulusan (%)',
                        data: kelasData,
                        backgroundColor: '#e67e22',
                        borderRadius: 8,
                        barPercentage: 0.7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: (v) => v + '%'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: (c) => 'Kelulusan: ' + c.raw + '%'
                            }
                        }
                    }
                }
            });
        }

        window.addEventListener('DOMContentLoaded', () => {
            updateGrafik();
            initKelasChart();
        });
    </script>
</body>

</html>