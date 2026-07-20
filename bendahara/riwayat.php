<?php
session_start();
include '../koneksi.php';

// PERBAIKAN 1: Cek session manual agar tidak Fatal Error karena isBendahara()
if (!isset($_SESSION['username']) || $_SESSION['level'] !== 'bendahara') {
    header("Location: ../index.php");
    exit();
}

/** @var mysqli $conn */

// Ambil filter dari GET dengan pengaman
$bulan_filter = isset($_GET['bulan_filter']) ? mysqli_real_escape_string($conn, $_GET['bulan_filter']) : 'semua';
$tahun_filter = isset($_GET['tahun_filter']) ? mysqli_real_escape_string($conn, $_GET['tahun_filter']) : 'semua';

// PERBAIKAN 2: JOIN berlapis yang Benaaaarrr sesuai struktur Database-mu!
$query_filter = "SELECT p.id_pembayaran, p.tanggal_bayar, p.jumlah_bayar, 
                 t.bulan, t.tahun, 
                 s.nama_siswa, s.no_wa_ortu, 
                 k.nama_kelas, 
                 u.nama_lengkap as bendahara 
          FROM pembayaran p
          JOIN tagihan t ON p.id_tagihan = t.id_tagihan
          JOIN siswa s ON t.id_siswa = s.id_siswa
          LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
          LEFT JOIN user u ON p.id_user = u.id_user
          WHERE 1=1";

// Filternya diarahkan ke tabel tagihan (t), bukan pembayaran (p)
if ($bulan_filter != 'semua') {
    $query_filter .= " AND t.bulan = '$bulan_filter'";
}
if ($tahun_filter != 'semua') {
    $query_filter .= " AND t.tahun = '$tahun_filter'";
}
$query_filter .= " ORDER BY p.tanggal_bayar DESC";

$riwayat_filter = mysqli_query($conn, $query_filter);

// Cek kalau query-nya error biar gampang di-debug
if (!$riwayat_filter) {
    die("Query Error: " . mysqli_error($conn));
}

$total_nominal_filter = 0;

$bulan_list = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tahun_list = ['2024', '2025', '2026'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Riwayat Transaksi - SD Mujahidin</title>

    <!-- CSS GLOBAL -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-graduation-cap"></i>
            <strong>SPP SD Mujahidin</strong>
            <span>- Panel Bendahara</span>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="pembayaran.php"><i class="fas fa-money-bill-wave"></i> Catatan Pembayaran</a>
        <a href="riwayat.php" class="active"><i class="fas fa-history"></i> Riwayat Transaksi</a>
        <a href="laporan.php"><i class="fas fa-print"></i> Laporan & Cetak</a>
        <a href="cek_rapor.php"><i class="fas fa-file-alt"></i> Monitoring Rapor</a>
        <a href="broadcast_tagihan.php"><i class="fab fa-whatsapp"></i> Pengingat Tagihan</a>
        <a href="konfirmasi_pembayaran.php"><i class="fas fa-check-double"></i> Konfirmasi Bayar</a>
    </div>

    <div class="content">
        <div class="fade-in-up">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Riwayat Pembayaran SPP</h2>
                    <p><i class="fas fa-info-circle"></i> Semua transaksi pembayaran SPP yang telah tercatat dalam sistem</p>
                </div>

                <!-- Filter -->
                <form method="GET" class="filter-box">
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Bulan</label>
                        <select name="bulan_filter">
                            <option value="semua" <?= $bulan_filter == 'semua' ? 'selected' : '' ?>>Semua Bulan</option>
                            <?php foreach ($bulan_list as $b): ?>
                                <option value="<?= $b ?>" <?= $bulan_filter == $b ? 'selected' : '' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                        <select name="tahun_filter">
                            <option value="semua" <?= $tahun_filter == 'semua' ? 'selected' : '' ?>>Semua Tahun</option>
                            <?php foreach ($tahun_list as $t): ?>
                                <option value="<?= $t ?>" <?= $tahun_filter == $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Tampilkan</button>
                    <?php if ($bulan_filter != 'semua' || $tahun_filter != 'semua'): ?>
                        <a href="riwayat.php" class="btn btn-secondary"><i class="fas fa-undo-alt"></i> Reset</a>
                    <?php endif; ?>
                </form>

                <div class="table-wrapper">
                    <?php if (mysqli_num_rows($riwayat_filter) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 40px;">No</th>
                                    <th>Waktu</th>
                                    <th>Siswa</th>
                                    <th>Kelas</th>
                                    <th>Periode</th>
                                    <th>Nominal</th>
                                    <th>Petugas</th>
                                    <th style="width: 80px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                while ($row = mysqli_fetch_assoc($riwayat_filter)):
                                    // PERBAIKAN 3: Disesuaikan dengan nama kolom yang benar (jumlah_bayar)
                                    $total_nominal_filter += $row['jumlah_bayar'];
                                ?>
                                    <tr>
                                        <td class="text-gray"><?= $no++ ?></td>
                                        <td class="text-sm"><?= date('d/m/Y H:i', strtotime($row['tanggal_bayar'])) ?></td>
                                        <td>
                                            <div class="fw-600"><?= htmlspecialchars($row['nama_siswa']) ?></div>
                                            <div class="text-xs text-gray"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($row['no_wa_ortu'] ?? '-') ?></div>
                                        </td>
                                        <td><span class="badge-kelas"><?= htmlspecialchars($row['nama_kelas'] ?? '-') ?></span></td>
                                        <td class="text-sm"><?= htmlspecialchars($row['bulan']) ?> <?= htmlspecialchars($row['tahun']) ?></td>
                                        <td class="text-success fw-700">Rp <?= number_format($row['jumlah_bayar'], 0, ',', '.') ?></td>
                                        <td><span class="badge-info"><i class="fas fa-user-check"></i> <?= htmlspecialchars($row['bendahara'] ?? 'Sistem') ?></span></td>
                                        <td><a href="cetak_struk.php?id=<?= $row['id_pembayaran'] ?>" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-print"></i> Struk</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-inbox"></i>
                            <p>Belum ada transaksi pembayaran</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (mysqli_num_rows($riwayat_filter) > 0): ?>
                    <div class="total-wrapper">
                        <div class="total-card"><span><i class="fas fa-chart-line"></i> Total Pemasukan:</span><strong>Rp <?= number_format($total_nominal_filter, 0, ',', '.') ?></strong></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="footer">
                <p>&copy; <?= date('Y') ?> SD Mujahidin - Sistem Informasi Pembayaran SPP</p>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>

</html>