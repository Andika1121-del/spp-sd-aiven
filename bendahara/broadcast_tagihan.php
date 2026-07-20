<?php

/** @var mysqli $conn */
session_start();
require_once __DIR__ . '/../koneksi.php';

// Cek session manual agar tidak ke-kick otomatis / Fatal Error
if (!isset($_SESSION['username']) || $_SESSION['level'] !== 'bendahara') {
    header("Location: ../index.php");
    exit();
}



function formatWhatsApp($nomor)
{
    $nomor = preg_replace('/[^0-9]/', '', $nomor);
    if (substr($nomor, 0, 1) === '0') {
        $nomor = '62' . substr($nomor, 1);
    }
    return $nomor;
}

// Filter Bulan & Tahun
$map_bulan = ['January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'];
$bulan_default = $map_bulan[date('F')] ?? 'Januari';

$bulan_filter = isset($_GET['bulan']) ? mysqli_real_escape_string($conn, $_GET['bulan']) : $bulan_default;
$tahun_filter = isset($_GET['tahun']) ? mysqli_real_escape_string($conn, $_GET['tahun']) : date('Y');

// Filter Tingkat, Kelas (Abjad), dan PENCARIAN NAMA
$tingkat_filter = isset($_GET['tingkat_filter']) ? mysqli_real_escape_string($conn, $_GET['tingkat_filter']) : 'semua';
$kelas_huruf_filter = isset($_GET['kelas_huruf_filter']) ? mysqli_real_escape_string($conn, $_GET['kelas_huruf_filter']) : 'semua';
$search_siswa = isset($_GET['search_siswa']) ? mysqli_real_escape_string($conn, $_GET['search_siswa']) : '';

$filter_sql = "";
if ($tingkat_filter != 'semua') {
    $filter_sql .= " AND kelas.nama_kelas LIKE '$tingkat_filter%'";
}
if ($kelas_huruf_filter != 'semua') {
    $filter_sql .= " AND kelas.nama_kelas LIKE '%$kelas_huruf_filter'";
}
if (!empty($search_siswa)) {
    $filter_sql .= " AND siswa.nama_siswa LIKE '%$search_siswa%'";
}

// Ambil nominal default jika di tabel anak tersebut kosong
$default_nominal = 400000;
$query_default = mysqli_query($conn, "SELECT nominal FROM spp_kategori LIMIT 1");
if ($query_default && mysqli_num_rows($query_default) > 0) {
    $row_default = mysqli_fetch_assoc($query_default);
    $default_nominal = $row_default['nominal'];
}

// Query dioptimasi dengan Filter Kelas, Join SPP Kategori, dan Pencarian Nama
$query_tunggakan = "SELECT siswa.*, kelas.nama_kelas, sk.nominal as tarif_spp_siswa,
                    COALESCE(tagihan.status, 'Belum Bayar') as status_tagihan,
                    COALESCE(tagihan.nominal_dibayar, 0) as sudah_dibayar,
                    COALESCE(tagihan.nominal_tagihan, sk.nominal) as nominal_tagihan
                    FROM siswa 
                    LEFT JOIN kelas ON siswa.id_kelas = kelas.id_kelas
                    LEFT JOIN spp_kategori sk ON kelas.id_spp = sk.id_spp
                    LEFT JOIN tagihan ON siswa.id_siswa = tagihan.id_siswa 
                        AND tagihan.bulan = '$bulan_filter' 
                        AND tagihan.tahun = '$tahun_filter'
                    WHERE (tagihan.id_tagihan IS NULL OR tagihan.status != 'Lunas')
                    $filter_sql
                    ORDER BY kelas.nama_kelas ASC, siswa.nama_siswa ASC
                    LIMIT 50";

$data_tunggakan = mysqli_query($conn, $query_tunggakan);

if (!$data_tunggakan) {
    $error_message = mysqli_error($conn);
    $total_belum = 0;
    $total_nominal_sisa = 0;
} else {
    $total_belum = mysqli_num_rows($data_tunggakan);
    $total_nominal_sisa = 0;

    if ($total_belum > 0) {
        while ($s = mysqli_fetch_assoc($data_tunggakan)) {
            $nominal_tagihan = $s['nominal_tagihan'] > 0 ? $s['nominal_tagihan'] : ($s['tarif_spp_siswa'] ?? 400000);
            $sisa = $nominal_tagihan - $s['sudah_dibayar'];
            $total_nominal_sisa += $sisa;
        }
        mysqli_data_seek($data_tunggakan, 0);
    }
}

// Daftar statis untuk bulan dan abjad kelas
$bulan_list = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$huruf_list = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Broadcast Tagihan SPP - SD Mujahidin</title>

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
        <a href="cek_rapor.php"><i class="fas fa-file-alt"></i> Monitoring Rapor</a>
        <a href="broadcast_tagihan.php" class="active"><i class="fab fa-whatsapp"></i> Pengingat Tagihan</a>
        <a href="konfirmasi_pembayaran.php"><i class="fas fa-check-double"></i> Konfirmasi Bayar</a>
    </div>

    <div class="content">
        <div class="fade-in-up">
            <div class="stats-grid">
                <div class="stat-card">
                    <h4><i class="fas fa-users"></i> Siswa Ditampilkan</h4>
                    <div class="stat-number text-danger"><?= number_format($total_belum) ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-money-bill-wave"></i> Total Sisa (Data Tampil)</h4>
                    <div class="stat-number text-warning">Rp <?= number_format($total_nominal_sisa, 0, ',', '.') ?></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h2><i class="fab fa-whatsapp"></i> Kirim Pengingat Tagihan SPP</h2>
                    <p><i class="fas fa-info-circle"></i> Kirim pengingat otomatis via WhatsApp ke orang tua siswa per kelas</p>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-box">
                        <div class="filter-group">
                            <label><i class="fas fa-layer-group"></i> Tingkat Kelas</label>
                            <select name="tingkat_filter">
                                <option value="semua" <?= $tingkat_filter == 'semua' ? 'selected' : '' ?>>Semua Tingkat</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>" <?= $tingkat_filter == (string)$i ? 'selected' : '' ?>>Kelas <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-chalkboard"></i> Kelas (Abjad)</label>
                            <select name="kelas_huruf_filter">
                                <option value="semua" <?= $kelas_huruf_filter == 'semua' ? 'selected' : '' ?>>Semua Abjad</option>
                                <?php foreach ($huruf_list as $h): ?>
                                    <option value="<?= $h ?>" <?= $kelas_huruf_filter == $h ? 'selected' : '' ?>><?= $h ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Bulan Tagihan</label>
                            <select name="bulan">
                                <?php foreach ($bulan_list as $b): ?>
                                    <option value="<?= $b ?>" <?= $bulan_filter == $b ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                            <select name="tahun">
                                <option value="2024" <?= $tahun_filter == '2024' ? 'selected' : '' ?>>2024</option>
                                <option value="2025" <?= $tahun_filter == '2025' ? 'selected' : '' ?>>2025</option>
                                <option value="2026" <?= $tahun_filter == '2026' ? 'selected' : '' ?>>2026</option>
                            </select>
                        </div>

                        <div class="filter-group" style="flex: 1 1 200px;">
                            <label><i class="fas fa-search"></i> Cari Nama Siswa</label>
                            <input type="text" name="search_siswa" value="<?= htmlspecialchars($search_siswa) ?>" placeholder="Ketik nama siswa..." style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; width: 100%; outline: none;">
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>

                        <?php if ($tingkat_filter != 'semua' || $kelas_huruf_filter != 'semua' || $search_siswa != ''): ?>
                            <a href="broadcast_tagihan.php" class="btn btn-secondary"><i class="fas fa-undo-alt"></i> Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="empty-state text-danger"><i class="fas fa-exclamation-triangle"></i>
                    <p>Error: <?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php elseif ($total_belum > 0 && $data_tunggakan && mysqli_num_rows($data_tunggakan) > 0): ?>
                <div class="blast-box mb-4">
                    <div class="blast-info">
                        <i class="fas fa-broadcast-tower"></i>
                        <p><strong><?= number_format($total_belum) ?> Siswa</strong> belum lunas pada filter ini (Bulan <?= $bulan_filter ?> <?= $tahun_filter ?>)</p>
                    </div>
                    <button onclick="kirimMasal()" class="btn btn-warning"><i class="fas fa-paper-plane"></i> Blast WA Bertahap</button>
                </div>

                <div class="card">
                    <div class="card-header flex-between">
                        <h3><i class="fas fa-list"></i> Daftar Target Pesan WA</h3>
                        <span class="badge badge-primary">Maksimal 50 Data Ditampilkan</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Siswa</th>
                                        <th>Kelas</th>
                                        <th>Status SPP</th>
                                        <th>Sisa Tagihan</th>
                                        <th>No. WhatsApp</th>
                                        <th>Aksi Manual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    while ($s = mysqli_fetch_assoc($data_tunggakan)):
                                        $no_wa = formatWhatsApp($s['no_wa_ortu']);
                                        $nominal_tagihan = $s['nominal_tagihan'] > 0 ? $s['nominal_tagihan'] : ($s['tarif_spp_siswa'] ?? 400000);
                                        $sisa = $nominal_tagihan - $s['sudah_dibayar'];
                                        $status = $s['status_tagihan'] == 'Cicil' ? 'Cicil' : 'Belum Bayar';
                                        $status_class = $status == 'Cicil' ? 'badge-warning' : 'badge-danger';

                                        $pesan = "🏫 *SD MUJAHIDIN*\n"
                                            . "━━━━━━━━━━━━━━━━━━━━\n"
                                            . "📢 *PENGINGAT PEMBAYARAN SPP*\n"
                                            . "━━━━━━━━━━━━━━━━━━━━\n\n"
                                            . "📝 *Nama Siswa:* {$s['nama_siswa']}\n"
                                            . "📆 *Bulan:* {$bulan_filter} {$tahun_filter}\n"
                                            . "💰 *Sisa Tagihan:* Rp " . number_format($sisa, 0, ',', '.') . "\n"
                                            . "🏷️ *Status:* {$status}\n\n"
                                            . "🙏 Mohon segera melakukan pembayaran.\n"
                                            . "━━━━━━━━━━━━━━━━━━━━\n"
                                            . "Terima kasih - SD Mujahidin";

                                        $wa_link = "https://api.whatsapp.com/send?phone=$no_wa&text=" . urlencode($pesan);
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td>
                                                <div class="fw-600"><?= htmlspecialchars($s['nama_siswa']) ?></div>
                                                <div class="text-xs text-gray"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars(substr($s['alamat'] ?? '-', 0, 25)) ?></div>
                                            </td>
                                            <td><span class="badge-kelas"><?= htmlspecialchars($s['nama_kelas']) ?></span></td>
                                            <td><span class="badge <?= $status_class ?>"><?= $status ?></span></td>
                                            <td><span class="text-danger fw-700">Rp <?= number_format($sisa, 0, ',', '.') ?></span></td>
                                            <td>
                                                <?php if (!empty($s['no_wa_ortu'])): ?>
                                                    <span class="text-gray text-xs"><i class="fab fa-whatsapp"></i> <?= substr($s['no_wa_ortu'], 0, 4) ?>****<?= substr($s['no_wa_ortu'], -3) ?></span>
                                                <?php else: ?>
                                                    <span class="text-gray text-xs">Tidak tersedia</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($s['no_wa_ortu'])): ?>
                                                    <a href="<?= $wa_link ?>" target="wa_shared_tab" class="btn-wa" style="display: inline-flex; align-items: center; gap: 6px; background: #25D366; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600;">
                                                        <i class="fab fa-whatsapp"></i> Kirim
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray text-xs"><i class="fas fa-phone-slash"></i> No WA</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($total_belum == 0): ?>
                <p style="text-align: center; padding: 20px 0; color: #10b981; font-weight: 600;">
                    ✅ Semua siswa sudah lunas untuk filter yang dipilih
                </p>
            <?php else: ?>
                <div class="empty-state" style="text-align: center; padding: 40px;">
                    <i class="fas fa-inbox fa-3x text-gray mb-3"></i>
                    <p>Tidak ada data siswa yang ditemukan</p>
                </div>
            <?php endif; ?>

            <div class="footer">
                <p>&copy; 2026 SD Mujahidin - Sistem Informasi Pembayaran SPP</p>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        let index = 0;
        let listTombol = [];

        function kirimMasal() {
            listTombol = document.querySelectorAll('.btn-wa');
            if (listTombol.length === 0) {
                alert("Tidak ada data atau nomor WA untuk dikirim.");
                return;
            }
            if (confirm("Kirim pengingat ke " + listTombol.length + " orang secara bertahap?\n\n⚠️ Pastikan Anda sudah login WhatsApp Web di browser!")) {
                index = 0;
                prosesKirim();
            }
        }

        function prosesKirim() {
            if (index < listTombol.length) {
                window.open(listTombol[index].href, 'wa_shared_tab');
                let row = listTombol[index].closest('tr');
                if (row) row.classList.add('highlight');
                index++;
                setTimeout(() => {
                    if (confirm("✅ Terkirim ke " + index + " dari " + listTombol.length + "\n\nKlik OK untuk lanjut ke pesan berikutnya, atau Batal untuk berhenti.")) {
                        prosesKirim();
                    } else {
                        alert("⏸️ Pengiriman dihentikan pada urutan ke-" + index);
                    }
                }, 500);
            } else {
                alert("🎉 Selesai! Semua pengingat telah diproses.\n\nTotal: " + listTombol.length + " pesan dikirim.");
                document.querySelectorAll('tr').forEach(r => r.classList.remove('highlight'));
            }
        }
    </script>
</body>

</html>