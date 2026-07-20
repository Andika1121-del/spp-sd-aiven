<?php
session_start();
include '../koneksi.php';

// Cek akses
if (!isset($_SESSION['username']) || $_SESSION['level'] !== 'bendahara') {
    header("Location: ../index.php");
    exit();
}

/** @var mysqli $conn */

// ===== FUNGSI PEMBERSIH NOMOR WA =====
function bersihkanNomorWa($nomor)
{
    // Hapus semua karakter selain angka
    $nomor = preg_replace('/[^0-9]/', '', $nomor);
    // Jika diawali '0', ganti dengan '62'
    if (substr($nomor, 0, 1) === '0') {
        $nomor = '62' . substr($nomor, 1);
    }
    return $nomor;
}

// ===== SETUP FILTER =====
$tingkat_filter = isset($_GET['tingkat_filter']) ? mysqli_real_escape_string($conn, $_GET['tingkat_filter']) : '1';
$kelas_filter = isset($_GET['kelas_filter']) ? mysqli_real_escape_string($conn, $_GET['kelas_filter']) : '1 A';
$status_filter = isset($_GET['status_filter']) ? mysqli_real_escape_string($conn, $_GET['status_filter']) : 'semua';

$tahun_filter = isset($_GET['tahun']) ? mysqli_real_escape_string($conn, $_GET['tahun']) : date('Y');
$semester_filter = isset($_GET['semester']) ? mysqli_real_escape_string($conn, $_GET['semester']) : 'Genap';

// Penentuan Bulan Wajib berdasarkan Semester
if ($semester_filter == 'Ganjil') {
    $bulan_wajib = ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
} else {
    $bulan_wajib = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'];
}
$jumlah_bulan_wajib = count($bulan_wajib);

$abjad_kelas_list = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

// ===== BUILD QUERY =====
$filter_sql = "";
if ($kelas_filter != 'semua') {
    $filter_sql .= " AND k.nama_kelas = '$kelas_filter'";
} elseif ($tingkat_filter != 'semua') {
    $filter_sql .= " AND k.nama_kelas LIKE '$tingkat_filter %'";
}

$having_sql = "";
if ($status_filter != 'semua') {
    if ($status_filter == 'tunggakan') {
        $having_sql = " HAVING lunas_count < $jumlah_bulan_wajib ";
    } elseif ($status_filter == 'lunas') {
        $having_sql = " HAVING lunas_count = $jumlah_bulan_wajib ";
    }
}

$bulan_in_query = "'" . implode("','", $bulan_wajib) . "'";
$sql = "SELECT s.id_siswa, s.nama_siswa, k.nama_kelas, s.no_wa_ortu, s.alamat, sk.nominal as tarif_spp,
        COUNT(CASE WHEN t.status = 'Lunas' THEN 1 END) as lunas_count,
        COALESCE(SUM(t.nominal_dibayar), 0) as total_dibayar,
        GROUP_CONCAT(CONCAT(t.bulan, ':', t.status) SEPARATOR ',') as history_bayar
        FROM siswa s
        JOIN kelas k ON s.id_kelas = k.id_kelas
        LEFT JOIN spp_kategori sk ON k.id_spp = sk.id_spp
        LEFT JOIN tagihan t ON s.id_siswa = t.id_siswa AND t.tahun = '$tahun_filter' AND t.bulan IN ($bulan_in_query)
        WHERE 1=1 $filter_sql
        GROUP BY s.id_siswa, s.nama_siswa, k.nama_kelas, s.no_wa_ortu, s.alamat, sk.nominal
        $having_sql
        ORDER BY s.nama_siswa ASC";

$query = mysqli_query($conn, $sql);

if (!$query) {
    die("Gagal mengambil data! Kesalahan: " . mysqli_error($conn));
}

// ===== OLAH DATA =====
$data_siswa_final = [];
$total_siswa = mysqli_num_rows($query);
$siswa_tunggakan = 0;
$siswa_siap = 0;

if ($total_siswa > 0) {
    while ($row = mysqli_fetch_assoc($query)) {
        $tarif_spp = $row['tarif_spp'] ?? 400000;

        $map_bayar = [];
        if (!empty($row['history_bayar'])) {
            $items = explode(',', $row['history_bayar']);
            foreach ($items as $item) {
                $parts = explode(':', $item);
                if (count($parts) == 2) {
                    $map_bayar[$parts[0]] = $parts[1];
                }
            }
        }

        $bulan_nunggak = [];
        foreach ($bulan_wajib as $bw) {
            if (!isset($map_bayar[$bw]) || $map_bayar[$bw] !== 'Lunas') {
                $bulan_nunggak[] = $bw;
            }
        }

        $total_tagihan_wajib = $jumlah_bulan_wajib * $tarif_spp;
        $sisa_tunggakan = $total_tagihan_wajib - $row['total_dibayar'];

        $row['bulan_nunggak'] = $bulan_nunggak;
        $row['sisa_tunggakan'] = $sisa_tunggakan;

        if (count($bulan_nunggak) > 0) {
            $siswa_tunggakan++;
        } else {
            $siswa_siap++;
        }

        $data_siswa_final[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Monitoring Rapor - SD Mujahidin</title>
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
        <a href="riwayat.php"><i class="fas fa-history"></i> Riwayat Transaksi</a>
        <a href="laporan.php"><i class="fas fa-print"></i> Laporan & Cetak</a>
        <a href="cek_rapor.php" class="active"><i class="fas fa-file-alt"></i> Monitoring Rapor</a>
        <a href="broadcast_tagihan.php"><i class="fab fa-whatsapp"></i> Pengingat Tagihan</a>
        <a href="konfirmasi_pembayaran.php"><i class="fas fa-check-double"></i> Konfirmasi Bayar</a>
    </div>

    <div class="content">
        <div class="fade-in-up">
            <div class="flex-between mb-4">
                <div>
                    <h2 class="page-title"><i class="fas fa-file-alt"></i> Monitoring Kelayakan Rapor</h2>
                    <p class="text-white-80">Syarat Pengambilan: Lunas 6 Bulan (Semester <?= htmlspecialchars($semester_filter) ?> Tahun <?= htmlspecialchars($tahun_filter) ?>)</p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h4><i class="fas fa-users"></i> Total di Kelas ini</h4>
                    <div class="stat-number"><?= number_format($total_siswa) ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-exclamation-triangle"></i> Rapor Ditahan</h4>
                    <div class="stat-number text-danger"><?= number_format($siswa_tunggakan) ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-check-circle"></i> Siap Ambil Rapor</h4>
                    <div class="stat-number text-success"><?= number_format($siswa_siap) ?></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Konfigurasi Kelas & Waktu</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-box">
                        <div class="filter-group">
                            <label><i class="fas fa-layer-group"></i> Tingkat Kelas</label>
                            <select name="tingkat_filter" id="tingkat_filter" onchange="filterKelasByTingkat()">
                                <option value="semua" <?= $tingkat_filter == 'semua' ? 'selected' : '' ?>>Semua Tingkat</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>" <?= $tingkat_filter == (string)$i ? 'selected' : '' ?>>Kelas <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-chalkboard"></i> Nama Kelas</label>
                            <select name="kelas_filter" id="kelas_filter">
                                <option value="semua" <?= $kelas_filter == 'semua' ? 'selected' : '' ?>>Semua Kelas</option>
                                <?php
                                for ($t = 1; $t <= 6; $t++) {
                                    foreach ($abjad_kelas_list as $abjad) {
                                        $nama_kelas_kombinasi = $t . " " . $abjad;
                                        $selected = ($kelas_filter == $nama_kelas_kombinasi) ? 'selected' : '';
                                        echo "<option value='{$nama_kelas_kombinasi}' data-tingkat='{$t}' {$selected}>{$abjad}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label><i class="fas fa-book-open"></i> Semester</label>
                            <select name="semester">
                                <option value="Ganjil" <?= $semester_filter == 'Ganjil' ? 'selected' : '' ?>>Ganjil (Juli - Des)</option>
                                <option value="Genap" <?= $semester_filter == 'Genap' ? 'selected' : '' ?>>Genap (Jan - Juni)</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Tahun Ajaran</label>
                            <select name="tahun">
                                <?php for ($y = 2024; $y <= 2026; $y++): ?>
                                    <option value="<?= $y ?>" <?= $tahun_filter == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label><i class="fas fa-flag-checkered"></i> Kelayakan Status</label>
                            <select name="status_filter">
                                <option value="semua" <?= $status_filter == 'semua' ? 'selected' : '' ?>>Semua Kelayakan</option>
                                <option value="lunas" <?= $status_filter == 'lunas' ? 'selected' : '' ?>>Lunas / Siap Ambil</option>
                                <option value="tunggakan" <?= $status_filter == 'tunggakan' ? 'selected' : '' ?>>Rapor Ditahan</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Terapkan</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header flex-between">
                    <h3><i class="fas fa-list"></i> Daftar Kelayakan Rapor - Kelas <?= htmlspecialchars($kelas_filter) ?></h3>
                    <span class="badge badge-primary">Load Data Terfilter: <?= number_format($total_siswa) ?> Siswa</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-wrapper">
                        <?php if ($total_siswa > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Siswa</th>
                                        <th>Kelas</th>
                                        <th>Detail Tunggakan (6 Bulan)</th>
                                        <th>Status Kelayakan</th>
                                        <th>Kirim Info WA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data_siswa_final as $row) :
                                        $tunggakan = $row['sisa_tunggakan'];
                                        $list_nunggak = $row['bulan_nunggak'];
                                        $jumlah_nunggak = count($list_nunggak);
                                        $teks_bulan = "";
                                        if ($jumlah_nunggak == 0) {
                                            $status = "SIAP DIAMBIL";
                                            $class = "badge-success";
                                            $status_icon = "✅";
                                        } else {
                                            $status = "DITAHAN";
                                            $class = "badge-danger";
                                            $status_icon = "🔒";
                                            $teks_bulan = implode(', ', $list_nunggak);
                                        }
                                    ?>
                                        <tr>
                                            <td><strong>#<?= htmlspecialchars($row['id_siswa']) ?></strong></td>
                                            <td>
                                                <div class="fw-600"><?= htmlspecialchars($row['nama_siswa']) ?></div>
                                                <div class="text-xs text-gray"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars(substr($row['alamat'] ?? '-', 0, 25)) ?></div>
                                                <div class="text-xs text-gray"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($row['no_wa_ortu'] ?? '-') ?></div>
                                            </td>
                                            <td><span class="badge-kelas"><?= htmlspecialchars($row['nama_kelas']) ?></span></td>
                                            <td>
                                                <?php if ($jumlah_nunggak == 0): ?>
                                                    <div class="fw-700 text-success">Lunas 6 Bulan</div>
                                                <?php else: ?>
                                                    <div class="fw-700 text-danger">Rp <?= number_format(max(0, $tunggakan), 0, ',', '.') ?></div>
                                                    <div class="text-xs text-danger" style="margin-top: 4px;">
                                                        <strong>Belum Lunas:</strong> <?= htmlspecialchars($teks_bulan) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge <?= $class ?>"><?= $status_icon ?> <?= $status ?></span></td>
                                            <td>
                                                <?php
                                                if (!empty($row['no_wa_ortu']) && $jumlah_nunggak > 0):
                                                    // Bersihkan nomor WA
                                                    $no_wa_siap = bersihkanNomorWa($row['no_wa_ortu']);

                                                    // Buat teks pesan
                                                    $pesan_wa = "Assalamualaikum Wr.Wb.\n\n"
                                                        . "Yth. Bapak/Ibu Wali dari *" . $row['nama_siswa'] . "*\n\n"
                                                        . "Kami infokan bahwa syarat pengambilan rapor adalah melunasi SPP selama 6 Bulan (Semester " . $semester_filter . " Tahun " . $tahun_filter . ").\n\n"
                                                        . "Saat ini ananda tercatat belum melunasi SPP pada bulan:\n"
                                                        . "👉 *" . $teks_bulan . "*\n\n"
                                                        . "💰 *Total Sisa Tagihan: Rp " . number_format(max(0, $tunggakan), 0, ',', '.') . "*\n\n"
                                                        . "Mohon tagihan tersebut segera dilunasi agar rapor dapat diambil.\n\n"
                                                        . "Terima kasih.\n\n- SD Mujahidin";
                                                ?>
                                                    <a href="https://api.whatsapp.com/send?phone=<?= $no_wa_siap ?>&text=<?= urlencode($pesan_wa) ?>"
                                                        target="_blank"
                                                        class="btn btn-success btn-sm"
                                                        style="background: #25D366; color: white; padding: 6px 12px; border-radius: 50px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 12px;">
                                                        <i class="fab fa-whatsapp"></i> Chat WA
                                                    </a>
                                                <?php elseif ($jumlah_nunggak <= 0): ?>
                                                    <span style="color: #10b981; font-size: 12px; font-weight: bold;"><i class="fas fa-check-circle"></i> Selesai</span>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8; font-size: 12px;"><i class="fas fa-phone-slash"></i> No WA</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state" style="text-align: center; padding: 30px;">
                                <i class="fas fa-inbox fa-3x" style="color: #ccc; margin-bottom: 10px;"></i>
                                <p>Tidak ada data siswa untuk kriteria filter ini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="footer">
                <p>&copy; 2026 SD Mujahidin - Sistem Informasi Pembayaran SPP</p>
            </div>
        </div>
    </div>

    <script>
        function filterKelasByTingkat() {
            var tingkat = document.getElementById('tingkat_filter').value;
            var kelasSelect = document.getElementById('kelas_filter');
            var options = kelasSelect.options;

            var firstMatch = false;
            for (var i = 0; i < options.length; i++) {
                var opt = options[i];
                if (tingkat === 'semua') {
                    opt.style.display = 'block';
                } else {
                    if (opt.getAttribute('data-tingkat') === tingkat || opt.value === 'semua') {
                        opt.style.display = 'block';
                        if (!firstMatch && opt.value !== 'semua') {
                            kelasSelect.value = opt.value;
                            firstMatch = true;
                        }
                    } else {
                        opt.style.display = 'none';
                    }
                }
            }
        }

        window.onload = function() {
            var tingkat = document.getElementById('tingkat_filter').value;
            if (tingkat !== 'semua') {
                var options = document.getElementById('kelas_filter').options;
                for (var i = 0; i < options.length; i++) {
                    var opt = options[i];
                    if (opt.getAttribute('data-tingkat') !== tingkat && opt.value !== 'semua') {
                        opt.style.display = 'none';
                    }
                }
            }
        };
    </script>
    <script src="../assets/js/main.js"></script>
</body>

</html>