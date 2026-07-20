<?php
session_start();
include '../koneksi.php';

if (!isBendahara()) {
    header("Location: ../index.php");
    exit();
}

// ========== TANGKAP FILTER ==========
$bulan       = isset($_GET['bulan']) ? mysqli_real_escape_string($conn, $_GET['bulan']) : '';
$tahun       = isset($_GET['tahun']) ? mysqli_real_escape_string($conn, $_GET['tahun']) : '';
$tingkat     = isset($_GET['tingkat']) ? mysqli_real_escape_string($conn, $_GET['tingkat']) : '';
$kelas_huruf = isset($_GET['kelas_huruf']) ? mysqli_real_escape_string($conn, $_GET['kelas_huruf']) : '';
$status      = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$keyword     = isset($_GET['keyword']) ? mysqli_real_escape_string($conn, $_GET['keyword']) : '';

// ========== DEFAULT ==========
$bulan_ini = $bulan ? $bulan : date('F');
$tahun_ini = $tahun ? $tahun : date('Y');

$map_bln = [
    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];
if (isset($map_bln[$bulan_ini])) {
    $bulan_ini = $map_bln[$bulan_ini];
}

// ========== FILTER KELAS ==========
$filter_kelas = "";
if ($tingkat) {
    $filter_kelas .= " AND k.nama_kelas LIKE '$tingkat%'";
}
if ($kelas_huruf) {
    $filter_kelas .= " AND k.nama_kelas LIKE '%$kelas_huruf'";
}
if ($keyword) {
    $filter_kelas .= " AND (s.nama_siswa LIKE '%$keyword%' OR s.nis LIKE '%$keyword%')";
}

// ========== QUERY LAPORAN PER SISWA ==========
$query = "
    SELECT 
        s.nis,
        s.nama_siswa,
        k.nama_kelas,
        t.id_tagihan,
        t.nominal_tagihan,
        COALESCE(t.nominal_dibayar, 0) AS nominal_dibayar,
        (t.nominal_tagihan - COALESCE(t.nominal_dibayar, 0)) AS sisa,
        t.status
    FROM siswa s
    JOIN kelas k ON s.id_kelas = k.id_kelas
    LEFT JOIN tagihan t ON s.id_siswa = t.id_siswa
    WHERE 1=1
      AND t.bulan = '$bulan_ini'
      AND t.tahun = '$tahun_ini'
      $filter_kelas
";

if ($status) {
    $query .= " AND t.status = '$status'";
}

$query .= " ORDER BY k.nama_kelas, s.nama_siswa";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// ========== KUMPULKAN DATA ==========
$data_siswa = [];
$total_tagihan = 0;
$total_dibayar = 0;
$total_sisa = 0;
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data_siswa[] = $row;
        $total_tagihan += $row['nominal_tagihan'];
        $total_dibayar += $row['nominal_dibayar'];
        $total_sisa += $row['sisa'];
    }
    mysqli_data_seek($result, 0);
}

// ========== STATISTIK ==========
$stat_lunas  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tagihan WHERE bulan = '$bulan_ini' AND tahun = '$tahun_ini' AND status = 'Lunas'"))['total'] ?? 0;
$stat_cicil  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tagihan WHERE bulan = '$bulan_ini' AND tahun = '$tahun_ini' AND status = 'Cicil'"))['total'] ?? 0;
$stat_belum  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tagihan WHERE bulan = '$bulan_ini' AND tahun = '$tahun_ini' AND status = 'Belum Bayar'"))['total'] ?? 0;
$total_siswa_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa"))['total'] ?? 0;

// ========== LAPORAN PER KELAS ==========
$query_per_kelas = "
    SELECT k.nama_kelas, 
        COUNT(DISTINCT s.id_siswa) as total_siswa,
        SUM(CASE WHEN t.status = 'Lunas' THEN 1 ELSE 0 END) as lunas,
        SUM(CASE WHEN t.status = 'Cicil' THEN 1 ELSE 0 END) as cicil,
        SUM(CASE WHEN t.status = 'Belum Bayar' THEN 1 ELSE 0 END) as belum
    FROM kelas k
    LEFT JOIN siswa s ON k.id_kelas = s.id_kelas
    LEFT JOIN tagihan t ON s.id_siswa = t.id_siswa AND t.bulan = '$bulan_ini' AND t.tahun = '$tahun_ini'
    WHERE 1=1 $filter_kelas
    GROUP BY k.id_kelas
    ORDER BY k.nama_kelas
";
$per_kelas = mysqli_query($conn, $query_per_kelas);

// ========== DATA FILTER DROPDOWN ==========
$bulan_list = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tahun_list = ['2023', '2024', '2025', '2026', '2027', '2028'];
$tingkat_list = ['1', '2', '3', '4', '5', '6'];
$huruf_list = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
$status_list = [
    '' => 'Semua Status',
    'Lunas' => '✅ Lunas',
    'Cicil' => '🔄 Cicil',
    'Belum Bayar' => '❌ Belum Bayar'
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan SPP - SD Mujahidin</title>

    <!-- ===== PAKAI ASET ===== -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ============================================================
           STYLE KHUSUS UNTUK HALAMAN LAPORAN (tidak ada di style.css)
        ============================================================ */

        /* Tambahan untuk badge status yang spesifik */
        .badge-status.lunas {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-status.cicil {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-status.belum {
            background: #f1f5f9;
            color: #64748b;
        }

        /* Progress bar (jika belum ada di style.css) */
        .progress-bar {
            width: 100%;
            max-width: 140px;
            height: 16px;
            background: #f1f5f9;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 8px;
            transition: width 0.5s;
        }

        .progress-text {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
        }

        /* KOP SURAT & TTD untuk print */
        .print-only-signature {
            display: none;
        }

        .kop-surat-print,
        .judul-laporan-print,
        .ttd-print {
            display: none;
        }

        /* Badge count di header card */
        .badge-count {
            background: #ecfdf5;
            color: #059669;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        /* ============================================================
           PRINT STYLES (hanya untuk cetak)
        ============================================================ */
        @media print {
            .print-only-signature {
                display: block !important;
                margin-top: 30px;
            }

            .kop-surat-print,
            .judul-laporan-print,
            .ttd-print {
                display: block !important;
            }

            .kop-surat-print {
                text-align: center;
                border-bottom: 2px solid #000;
                padding-bottom: 6px;
                margin-bottom: 12px;
            }

            .kop-surat-print h1 {
                font-size: 16px;
                margin: 0;
            }

            .kop-surat-print p {
                font-size: 10px;
                margin: 1px 0;
            }

            .judul-laporan-print {
                text-align: center;
                margin: 10px 0;
            }

            .judul-laporan-print h2 {
                font-size: 14px;
                text-decoration: underline;
                margin: 0;
            }

            .ttd-print {
                display: flex !important;
                justify-content: space-between;
                margin-top: 30px;
            }

            .ttd-print div {
                text-align: center;
            }

            .ttd-print .garis-ttd {
                width: 150px;
                border-top: 1px solid #000;
                margin: 12px auto 2px;
            }

            .ttd-print .nama-ttd {
                font-weight: 600;
            }

            /* Sembunyikan elemen yang tidak perlu */
            .no-print {
                display: none !important;
            }

            .sidebar,
            .navbar,
            .menu-toggle {
                display: none !important;
            }

            body {
                background: #fff !important;
                padding-top: 0 !important;
            }

            .content {
                margin-left: 0 !important;
                padding: 10px !important;
                max-width: 100% !important;
            }

            .card {
                border: 1px solid #ccc !important;
                box-shadow: none !important;
                margin-bottom: 8px !important;
            }

            .card-header {
                display: none !important;
            }

            .data-table {
                font-size: 8px !important;
            }

            .data-table th,
            .data-table td {
                padding: 2px 4px !important;
                border: 0.5px solid #000 !important;
            }

            .data-table th {
                background: #f1f5f9 !important;
            }

            .data-table tfoot td {
                background: #f0f0f0 !important;
            }

            .badge-status {
                background: none !important;
                border: none !important;
                padding: 0 !important;
                color: #000 !important;
                font-weight: normal !important;
                font-size: 8px !important;
                display: block !important;
            }

            .badge-status i {
                display: none !important;
            }

            .badge-kelas {
                border: 1px solid #000 !important;
                background: #f1f5f9 !important;
            }

            .progress-bar {
                border: 1px solid #000 !important;
            }

            .progress-fill {
                background: #000 !important;
            }

            .stats-grid {
                display: none !important;
            }

            .footer {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

    <!-- ===== NAVBAR ===== -->
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-graduation-cap"></i>
            <strong>SPP SD Mujahidin</strong>
            <span style="font-weight:400;font-size:13px;opacity:0.7;">- Bendahara</span>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar" id="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="pembayaran.php"><i class="fas fa-money-bill-wave"></i> Catatan Pembayaran</a>
        <a href="riwayat.php"><i class="fas fa-history"></i> Riwayat Transaksi</a>
        <a href="laporan.php" class="active"><i class="fas fa-print"></i> Laporan &amp; Cetak</a>
        <a href="cek_rapor.php"><i class="fas fa-file-alt"></i> Monitoring Rapor</a>
        <a href="broadcast_tagihan.php"><i class="fab fa-whatsapp"></i> Pengingat Tagihan</a>
        <a href="konfirmasi_pembayaran.php"><i class="fas fa-check-double"></i> Konfirmasi Bayar</a>
    </div>

    <!-- ===== CONTENT ===== -->
    <div class="content">

        <!-- HEADER -->
        <div class="flex-between mb-3">
            <div>
                <h1 class="page-title"><i class="fas fa-chart-line"></i> Laporan Pembayaran SPP</h1>
                <p class="sub-title">Rekapitulasi pembayaran per siswa SD Mujahidin Pontianak</p>
            </div>
            <div class="no-print flex gap-2">
                <button onclick="window.print()" class="btn btn-warning"><i class="fas fa-print"></i> Cetak</button>
                <button onclick="exportToExcel()" class="btn btn-primary"><i class="fas fa-file-excel"></i> Excel</button>
            </div>
        </div>

        <!-- STATISTIK -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4><i class="fas fa-check-circle" style="color:#10b981;"></i> Lunas</h4>
                <div class="stat-number green"><?= number_format($stat_lunas) ?></div>
                <small>Siswa</small>
            </div>
            <div class="stat-card">
                <h4><i class="fas fa-clock" style="color:#f59e0b;"></i> Cicil</h4>
                <div class="stat-number yellow"><?= number_format($stat_cicil) ?></div>
                <small>Siswa</small>
            </div>
            <div class="stat-card">
                <h4><i class="fas fa-times-circle" style="color:#ef4444;"></i> Belum Bayar</h4>
                <div class="stat-number red"><?= number_format($stat_belum) ?></div>
                <small>Siswa</small>
            </div>
            <div class="stat-card">
                <h4><i class="fas fa-users" style="color:#064e3b;"></i> Total Siswa</h4>
                <div class="stat-number primary"><?= number_format($total_siswa_all) ?></div>
                <small>Siswa</small>
            </div>
        </div>

        <!-- FILTER -->
        <div class="card no-print mb-4">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter Data</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-box">
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Bulan</label>
                        <select name="bulan">
                            <option value="">Semua</option>
                            <?php foreach ($bulan_list as $b): ?>
                                <option value="<?= $b ?>" <?= $bulan == $b ? 'selected' : '' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                        <select name="tahun">
                            <option value="">Semua</option>
                            <?php foreach ($tahun_list as $t): ?>
                                <option value="<?= $t ?>" <?= $tahun == $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-layer-group"></i> Tingkat</label>
                        <select name="tingkat">
                            <option value="">Semua</option>
                            <?php foreach ($tingkat_list as $t): ?>
                                <option value="<?= $t ?>" <?= $tingkat == $t ? 'selected' : '' ?>>Kelas <?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-chalkboard"></i> Huruf</label>
                        <select name="kelas_huruf">
                            <option value="">Semua</option>
                            <?php foreach ($huruf_list as $h): ?>
                                <option value="<?= $h ?>" <?= $kelas_huruf == $h ? 'selected' : '' ?>>Kelas <?= $h ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-flag"></i> Status</label>
                        <select name="status">
                            <?php foreach ($status_list as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $status == $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Cari</label>
                        <input type="text" name="keyword" placeholder="Nama / NIS" value="<?= htmlspecialchars($keyword) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-eye"></i> Tampilkan</button>
                    <a href="laporan.php" class="btn btn-secondary"><i class="fas fa-undo-alt"></i> Reset</a>
                </form>
            </div>
        </div>

        <!-- REKAP PER KELAS -->
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-chalkboard"></i> Rekapitulasi per Kelas</h3>
                <span class="badge-count"><?= htmlspecialchars($bulan_ini . ' ' . $tahun_ini) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kelas</th>
                                <th>Total Siswa</th>
                                <th>Lunas</th>
                                <th>Cicil</th>
                                <th>Belum</th>
                                <th>Persentase</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_siswa_kelas = 0;
                            $total_lunas_kelas = 0;
                            if ($per_kelas && mysqli_num_rows($per_kelas) > 0):
                                while ($row = mysqli_fetch_assoc($per_kelas)):
                                    $persen = $row['total_siswa'] > 0 ? round(($row['lunas'] / $row['total_siswa']) * 100) : 0;
                                    $total_siswa_kelas += $row['total_siswa'];
                                    $total_lunas_kelas += $row['lunas'];
                            ?>
                                    <tr>
                                        <td><span class="badge-kelas"><?= htmlspecialchars($row['nama_kelas']) ?></span></td>
                                        <td><?= $row['total_siswa'] ?></td>
                                        <td><span class="badge-status lunas"><?= $row['lunas'] ?></span></td>
                                        <td><span class="badge-status cicil"><?= $row['cicil'] ?></span></td>
                                        <td><span class="badge-status belum"><?= $row['belum'] ?></span></td>
                                        <td>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?= $persen ?>%;"></div>
                                                <span class="progress-text"><?= $persen ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr style="background:#f8fafc; font-weight:700;">
                                    <td>TOTAL</td>
                                    <td><?= $total_siswa_kelas ?></td>
                                    <td><?= $total_lunas_kelas ?></td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td><?= $total_siswa_kelas > 0 ? round(($total_lunas_kelas / $total_siswa_kelas) * 100) : 0 ?>%</td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-gray" style="padding:30px;">Belum ada data kelas untuk periode ini</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- DETAIL PER SISWA -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Detail Tagihan per Siswa</h3>
                <span class="badge-count"><?= count($data_siswa) ?> siswa</span>
            </div>
            <div class="card-body p-0">
                <div class="table-wrapper">
                    <?php if (count($data_siswa) > 0): ?>
                        <table class="data-table" id="laporanTable">
                            <thead>
                                <tr>
                                    <th style="width:40px;">No</th>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th style="text-align:right;">Tagihan</th>
                                    <th style="text-align:right;">Dibayar</th>
                                    <th style="text-align:right;">Sisa</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $grand_tagihan = 0;
                                $grand_dibayar = 0;
                                $grand_sisa = 0;
                                foreach ($data_siswa as $row):
                                    $grand_tagihan += $row['nominal_tagihan'];
                                    $grand_dibayar += $row['nominal_dibayar'];
                                    $grand_sisa += $row['sisa'];
                                    $status_class = strtolower($row['status'] ?? 'belum bayar');
                                    $status_label = $row['status'] ?: 'Belum Bayar';
                                ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($row['nis']) ?></td>
                                        <td><strong><?= htmlspecialchars($row['nama_siswa']) ?></strong></td>
                                        <td><span class="badge-kelas"><?= htmlspecialchars($row['nama_kelas']) ?></span></td>
                                        <td style="text-align:right;">Rp <?= number_format($row['nominal_tagihan'], 0, ',', '.') ?></td>
                                        <td style="text-align:right;">Rp <?= number_format($row['nominal_dibayar'], 0, ',', '.') ?></td>
                                        <td style="text-align:right; font-weight:600; <?= $row['sisa'] > 0 ? 'color:#1e293b;' : 'color:#10b981;' ?>">
                                            Rp <?= number_format($row['sisa'], 0, ',', '.') ?>
                                        </td>
                                        <td>
                                            <span class="badge-status <?= $status_class ?>">
                                                <?php if ($status_label == 'Lunas'): ?>✅<?php elseif ($status_label == 'Cicil'): ?>🔄<?php else: ?>❌<?php endif; ?>
                                                <?= $status_label ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" style="text-align:right;font-weight:700;">GRAND TOTAL</td>
                                    <td style="text-align:right;font-weight:700;">Rp <?= number_format($grand_tagihan, 0, ',', '.') ?></td>
                                    <td style="text-align:right;font-weight:700;">Rp <?= number_format($grand_dibayar, 0, ',', '.') ?></td>
                                    <td style="text-align:right;font-weight:700; <?= $grand_sisa > 0 ? 'color:#1e293b;' : 'color:#10b981;' ?>">
                                        Rp <?= number_format($grand_sisa, 0, ',', '.') ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Tidak ada data tagihan untuk periode yang dipilih</p>
                            <small class="text-gray">Coba ubah filter atau buat tagihan baru</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== KOP SURAT & TTD UNTUK PRINT ===== -->
        <div class="print-only-signature">
            <div class="kop-surat-print">
                <h1>SD MUJAHIDIN PONTIANAK</h1>
                <p>Alamat: Jl. Contoh No. 123, Pontianak</p>
                <p>Telp. (0561) 123456, Email: sd_mujahidin@yahoo.com</p>
            </div>
            <div class="judul-laporan-print">
                <h2>LAPORAN PEMBAYARAN SPP</h2>
                <p>Periode: <?= htmlspecialchars($bulan_ini . ' ' . $tahun_ini) ?></p>
            </div>
            <div class="ttd-print">
                <div>
                    <p>Mengetahui,<br>Kepala Sekolah</p>
                    <div class="garis-ttd"></div>
                    <p class="nama-ttd">________________________</p>
                    <p style="font-size:11px;">Nama Kepala Sekolah</p>
                </div>
                <div>
                    <p>Pontianak, <?= date('d F Y') ?></p>
                    <p>Bendahara</p>
                    <div class="garis-ttd"></div>
                    <p class="nama-ttd">________________________</p>
                    <p style="font-size:11px;">Nama Bendahara</p>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>&copy; <?= date('Y') ?> SD Mujahidin - Sistem Informasi Pembayaran SPP</p>
        </div>

    </div>

    <!-- ===== PAKAI JS ASET ===== -->
    <script src="../assets/js/main.js"></script>
    <script>
        // ========== SIDEBAR TOGGLE (jika main.js belum handle) ==========
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar')?.classList.toggle('active');
        });

        // ========== EXPORT EXCEL ==========
        function exportToExcel() {
            var table = document.getElementById('laporanTable');
            if (!table) {
                alert('Tidak ada data untuk diexport!');
                return;
            }
            var clone = table.cloneNode(true);
            // Ubah badge menjadi teks biasa
            clone.querySelectorAll('.badge-status').forEach(function(el) {
                el.textContent = el.textContent.trim();
            });
            var html = clone.outerHTML;
            var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            var link = document.createElement('a');
            link.download = 'Laporan_SPP_<?= $bulan_ini ?>_<?= $tahun_ini ?>.xls';
            link.href = url;
            link.click();
        }

        console.log('✅ Laporan siap.');
    </script>

</body>

</html>