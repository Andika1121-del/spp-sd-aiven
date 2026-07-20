<?php

/** @var mysqli $conn */ // Pastikan koneksi database sudah tersedia melalui $conn
ob_start(); // Mengunci output buffer agar redirect header() anti-error
session_start();
include_once __DIR__ . '/../koneksi.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

if (!isBendahara()) {
    header("Location: ../index.php");
    exit();
}

$nama_user = isset($_SESSION['nama']) ? $_SESSION['nama'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Bendahara');

// Statistik
$tgl_sekarang = date('Y-m-d');
$query_hari_ini = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_bayar), 0) as total FROM pembayaran WHERE DATE(tanggal_bayar) = '$tgl_sekarang'");
$total_hari_ini = mysqli_fetch_assoc($query_hari_ini)['total'];

$bulan_sekarang = date('Y-m');
$query_bulan_ini = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_bayar), 0) as total FROM pembayaran WHERE DATE_FORMAT(tanggal_bayar, '%Y-%m') = '$bulan_sekarang'");
$total_bulan_ini = mysqli_fetch_assoc($query_bulan_ini)['total'];

$query_jml = mysqli_query($conn, "SELECT COUNT(*) as jml FROM pembayaran WHERE DATE(tanggal_bayar) = '$tgl_sekarang'");
$jml_transaksi = mysqli_fetch_assoc($query_jml)['jml'] ?? 0;

$query_total_siswa = mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa");
$total_siswa_keseluruhan = mysqli_fetch_assoc($query_total_siswa)['total'] ?? 0;

$query_total_tunggakan = mysqli_query($conn, "SELECT COUNT(DISTINCT id_siswa) as total FROM tagihan WHERE status != 'Lunas'");
$total_siswa_tunggakan = mysqli_fetch_assoc($query_total_tunggakan)['total'] ?? 0;

$bulan_list = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$map_bln_active = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
$bulan_aktif = isset($map_bln_active[date('m')]) ? $map_bln_active[date('m')] : 'Januari';
$tahun_aktif = date('Y');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Dashboard Bendahara - SD Mujahidin</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>

<body>

    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-graduation-cap"></i>
            <strong>SPP SD Mujahidin</strong>
            <span style="font-weight: normal; font-size: 13px;">- Panel Bendahara</span>
        </div>
        <a href="../logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="pembayaran.php"><i class="fas fa-money-bill-wave"></i><span>Catatan Pembayaran</span></a>
        <a href="riwayat.php"><i class="fas fa-history"></i><span>Riwayat Transaksi</span></a>
        <a href="laporan.php"><i class="fas fa-print"></i><span>Laporan & Cetak</span></a>
        <a href="cek_rapor.php"><i class="fas fa-file-alt"></i><span>Monitoring Rapor</span></a>
        <a href="broadcast_tagihan.php"><i class="fab fa-whatsapp"></i><span>Pengingat Tagihan</span></a>
        <a href="konfirmasi_pembayaran.php"><i class="fas fa-check-double"></i> Konfirmasi Bayar</a>
        <a href="data_kelompok_kelas.php"><i class="fas fa-users"></i> Data Alumni</a>
    </div>

    <div class="content">
        <div class="fade-in-up">
            <div class="card">
                <div class="card-body flex-between">
                    <div>
                        <h2 class="page-title" style="color: #064e3b;"><i class="fas fa-chart-line"></i> Dashboard Bendahara</h2>
                        <p style="color: #064e3b; margin: 0;">Selamat datang, <strong><?= htmlspecialchars($nama_user) ?></strong></p>
                    </div>
                    <div><span class="badge badge-success"><i class="fas fa-check-circle"></i> Siap Mencatat</span></div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h4><i class="fas fa-coins"></i> Pemasukan Hari Ini</h4>
                    <div class="stat-number">Rp <?= number_format($total_hari_ini, 0, ',', '.') ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-calendar-alt"></i> Pemasukan Bulan Ini</h4>
                    <div class="stat-number text-success">Rp <?= number_format($total_bulan_ini, 0, ',', '.') ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-exchange-alt"></i> Transaksi Hari Ini</h4>
                    <div class="stat-number text-info"><?= number_format($jml_transaksi) ?> <span style="font-size: 14px;">kali</span></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-users"></i> Total Siswa</h4>
                    <div class="stat-number"><?= number_format($total_siswa_keseluruhan) ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-exclamation-triangle"></i> Siswa Menunggak</h4>
                    <div class="stat-number text-danger"><?= number_format($total_siswa_tunggakan) ?></div>
                </div>
            </div>

            <div class="quick-actions">
                <a href="pembayaran.php" class="action-btn"><i class="fas fa-plus-circle"></i><span>Catat Pembayaran Baru</span></a>
                <a href="broadcast_tagihan.php" class="action-btn"><i class="fab fa-whatsapp"></i><span>Kirim Pengingat Tagihan</span></a>
                <a href="riwayat.php" class="action-btn"><i class="fas fa-history"></i><span>Riwayat Transaksi</span></a>
                <a href="laporan.php" class="action-btn"><i class="fas fa-file-pdf"></i><span>Cetak Laporan Bulanan</span></a>
            </div>

            <div class="row-2-cols">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Riwayat Pembayaran Hari Ini</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Waktu</th>
                                        <th>Siswa</th>
                                        <th>Nominal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query_riwayat = mysqli_query($conn, "SELECT p.*, s.nama_siswa FROM pembayaran p JOIN tagihan t ON p.id_tagihan = t.id_tagihan JOIN siswa s ON t.id_siswa = s.id_siswa WHERE DATE(p.tanggal_bayar) = '$tgl_sekarang' ORDER BY p.id_pembayaran DESC LIMIT 5");
                                    $no = 1;
                                    if (mysqli_num_rows($query_riwayat) > 0) {
                                        while ($row = mysqli_fetch_assoc($query_riwayat)) {
                                            // PERBAIKAN: Mengubah tgl_bayar -> tanggal_bayar, dan nominal_dibayar -> jumlah_bayar
                                            echo "<tr><td>{$no}</td><td>" . date('d/m/Y H:i', strtotime($row['tanggal_bayar'])) . "</td><td><strong>" . htmlspecialchars(substr($row['nama_siswa'], 0, 25)) . "</strong></td><td class='text-success font-bold'>Rp " . number_format($row['jumlah_bayar'], 0, ',', '.') . "</td></tr>";
                                            $no++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center' style='padding: 30px;'><i class='fas fa-inbox'></i> Belum ada transaksi hari ini</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Status Pembayaran SPP</h3>
                    </div>
                    <div class="card-body">
                        <div class="controls-wrapper">
                            <div class="btn-group">
                                <button id="btn-hari" class="btn-filter" onclick="setModeDonat('hari')">Hari Ini</button>
                                <button id="btn-bulan" class="btn-filter active" onclick="setModeDonat('bulan')">Bulan Ini</button>
                                <button id="btn-semester" class="btn-filter" onclick="setModeDonat('semester')">Semester Ini</button>
                            </div>
                            <div class="custom-inputs">
                                <input type="date" id="input-tgl" class="input-box" value="<?= $tgl_sekarang ?>" onchange="pilihTanggalKustom()">
                                <select id="input-bulan" class="input-box" onchange="pilihBulanKustom()">
                                    <?php foreach ($bulan_list as $b): ?>
                                        <option value="<?= $b ?>" <?= ($b == $bulan_aktif) ? 'selected' : '' ?>><?= $b ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div id="teks-periode" class="text-center text-gray" style="font-size: 12px; margin-bottom: 10px;">Memuat data...</div>
                        <div class="income-badge"><i class="fas fa-money-bill-wave"></i> Pendapatan: <strong id="lbl-uang">Rp 0</strong></div>
                        <div class="canvas-container"><canvas id="sppChart"></canvas></div>
                        <div class="chart-legend">
                            <div class="legend-item"><span class="dot dot-paid"></span><span>Sudah Bayar: <strong id="lbl-sudah">0</strong></span></div>
                            <div class="legend-item"><span class="dot dot-unpaid"></span><span>Belum Bayar: <strong id="lbl-belum">0</strong></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header flex-between">
                    <h3><i class="fas fa-chart-line"></i> Perbandingan Tren Pendapatan</h3>
                    <div class="flex gap-2">
                        <div class="flex" style="align-items: center; gap: 6px;"><span style="font-size: 12px;">Tahun:</span><input type="number" id="input-tahun-comp" class="input-box" style="width: 80px;" value="<?= $tahun_aktif ?>" onchange="pilihTahunCompare()"></div>
                        <div class="btn-group">
                            <button id="comp-minggu" class="btn-filter btn-comp" onclick="setModeCompare('minggu')">Mingguan</button>
                            <button id="comp-bulan" class="btn-filter btn-comp active" onclick="setModeCompare('bulan')">Bulanan</button>
                            <button id="comp-semester" class="btn-filter btn-comp" onclick="setModeCompare('semester')">Semester</button>
                            <button id="comp-tahun" class="btn-filter btn-comp" onclick="setModeCompare('tahun')">Tahunan</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div style="height: 300px;"><canvas id="compareChart"></canvas></div>
                </div>
            </div>

            <div class="footer">
                <p>&copy; 2026 SD Mujahidin - Sistem Informasi Pembayaran SPP</p>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>

    <script>
        let sppChart, compareChart;
        let totalPopulasi = 0;
        let compModeSaatIni = 'bulan';

        function initDonat() {
            const ctx = document.getElementById('sppChart').getContext('2d');
            sppChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Sudah Bayar', 'Belum Bayar'],
                    datasets: [{
                        data: [0, 0],
                        backgroundColor: ['#10b981', '#ef4444'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (c) => `${c.label}: ${c.raw} Siswa (${totalPopulasi > 0 ? Math.round((c.raw/totalPopulasi)*100) : 0}%)`
                            }
                        }
                    }
                }
            });
            muatDonatAPI('bulan', document.getElementById('input-tgl').value, document.getElementById('input-bulan').value, document.getElementById('input-tahun-comp').value);
        }

        function setModeDonat(mode) {
            document.querySelectorAll('.controls-wrapper .btn-filter').forEach(b => b.classList.remove('active'));
            document.getElementById('btn-' + mode).classList.add('active');
            muatDonatAPI(mode, document.getElementById('input-tgl').value, document.getElementById('input-bulan').value, document.getElementById('input-tahun-comp').value);
        }

        function pilihTanggalKustom() {
            document.querySelectorAll('.controls-wrapper .btn-filter').forEach(b => b.classList.remove('active'));
            muatDonatAPI('hari', document.getElementById('input-tgl').value, '', '');
        }

        function pilihBulanKustom() {
            document.querySelectorAll('.controls-wrapper .btn-filter').forEach(b => b.classList.remove('active'));
            muatDonatAPI('bulan', '', document.getElementById('input-bulan').value, document.getElementById('input-tahun-comp').value);
        }

        function muatDonatAPI(mode, tgl, bulan, tahun) {
            const teksPeriode = document.getElementById('teks-periode');
            if (teksPeriode) teksPeriode.innerText = "Menghitung...";
            fetch(`api_grafik.php?mode=${mode}&tgl=${tgl}&bulan=${bulan}&tahun=${tahun}`)
                .then(r => r.json()).then(data => {
                    if (data.error) return;
                    totalPopulasi = data.total_siswa || 0;
                    if (teksPeriode) teksPeriode.innerText = data.judul || 'Data tidak tersedia';
                    document.getElementById('lbl-sudah').innerText = data.sudah_bayar || 0;
                    document.getElementById('lbl-belum').innerText = data.belum_bayar || 0;
                    document.getElementById('lbl-uang').innerHTML = 'Rp ' + (data.total_uang || 0).toLocaleString('id-ID');
                    sppChart.data.datasets[0].data = [data.sudah_bayar || 0, data.belum_bayar || 0];
                    sppChart.update();
                }).catch(error => console.error('Error:', error));
        }

        function initCompare() {
            const ctxComp = document.getElementById('compareChart').getContext('2d');
            compareChart = new Chart(ctxComp, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Total Pendapatan',
                        data: [],
                        backgroundColor: '#10b981',
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
                            ticks: {
                                callback: (v) => 'Rp ' + v.toLocaleString('id-ID')
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: (c) => 'Pendapatan: Rp ' + c.raw.toLocaleString('id-ID')
                            }
                        }
                    }
                }
            });
            muatCompareAPI('bulan');
        }

        function setModeCompare(mode) {
            compModeSaatIni = mode;
            document.querySelectorAll('.btn-comp').forEach(b => b.classList.remove('active'));
            const activeBtn = document.getElementById('comp-' + mode);
            if (activeBtn) activeBtn.classList.add('active');
            muatCompareAPI(mode);
        }

        function pilihTahunCompare() {
            muatCompareAPI(compModeSaatIni);
            pilihBulanKustom();
        }

        function muatCompareAPI(mode) {
            let tahun = document.getElementById('input-tahun-comp').value;
            fetch(`api_grafik_compare.php?mode=${mode}&tahun=${tahun}`)
                .then(r => r.json()).then(data => {
                    if (data.error) return;
                    compareChart.data.labels = data.labels || [];
                    compareChart.data.datasets[0].data = data.dataset || [];
                    compareChart.update();
                }).catch(error => console.error('Error:', error));
        }

        window.addEventListener('DOMContentLoaded', () => {
            initDonat();
            initCompare();
        });
    </script>
</body>

</html>
<?php
ob_end_flush();
?>