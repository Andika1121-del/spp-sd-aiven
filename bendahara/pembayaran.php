<?php

/**
 * Halaman Catatan Pembayaran SPP - Bendahara
 * Menggunakan prepared statements untuk keamanan
 * 
 * @var mysqli $conn
 */
session_start();
include '../koneksi.php';

// Cek akses
if (!isset($_SESSION['username']) || $_SESSION['level'] !== 'bendahara') {
    header("Location: ../index.php");
    exit();
}

// Pastikan koneksi tersedia
if (!$conn) {
    die("Koneksi database gagal.");
}

// Fungsi format nomor WhatsApp
function formatWhatsApp($nomor)
{
    $nomor = preg_replace('/[^0-9]/', '', $nomor);
    if (substr($nomor, 0, 1) === '0') {
        $nomor = '62' . substr($nomor, 1);
    }
    return $nomor;
}

// ======================================================
// 1. PROSES TAMBAH PEMBAYARAN (GABUNGAN BIASA & VIA WA)
// ======================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi']; // 'simpan' atau 'simpan_wa'
    $id_siswa = trim($_POST['id_siswa'] ?? '');
    $bulan    = trim($_POST['bulan'] ?? '');
    $tahun    = trim($_POST['tahun'] ?? '');
    $nominal_input = $_POST['nominal_dibayar'] ?? '0';
    $nominal_dibayar = intval(preg_replace('/[^0-9]/', '', $nominal_input));

    // Validasi dasar
    if (empty($id_siswa) || empty($bulan) || empty($tahun) || $nominal_dibayar <= 0) {
        $_SESSION['error'] = "Data tidak lengkap atau nominal tidak valid.";
        header("Location: pembayaran.php");
        exit();
    }

    // Ambil nominal SPP dan sisa tagihan (jika ada)
    $stmt = $conn->prepare("
        SELECT sk.nominal, COALESCE(t.nominal_dibayar, 0) AS sudah_dibayar
        FROM siswa s
        JOIN kelas k ON s.id_kelas = k.id_kelas
        JOIN spp_kategori sk ON k.id_spp = sk.id_spp
        LEFT JOIN tagihan t ON s.id_siswa = t.id_siswa AND t.bulan = ? AND t.tahun = ?
        WHERE s.id_siswa = ?
    ");
    $stmt->bind_param("sss", $bulan, $tahun, $id_siswa);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if (!$data) {
        $_SESSION['error'] = "Data siswa tidak ditemukan.";
        header("Location: pembayaran.php");
        exit();
    }

    $nominal_tagihan = $data['nominal'] ?? 400000;
    $sudah_dibayar   = $data['sudah_dibayar'] ?? 0;
    $sisa_tagihan    = $nominal_tagihan - $sudah_dibayar;

    // Cegah overpayment
    if ($nominal_dibayar > $sisa_tagihan) {
        $_SESSION['error'] = "Nominal yang dibayar melebihi sisa tagihan (Rp " . number_format($sisa_tagihan, 0, ',', '.') . ")";
        header("Location: pembayaran.php");
        exit();
    }

    // Ambil ID user dari session atau dari database
    $id_user = $_SESSION['id_user'] ?? 0;
    if ($id_user == 0) {
        $uname = $_SESSION['username'];
        $q = $conn->prepare("SELECT id_user FROM user WHERE username = ?");
        $q->bind_param("s", $uname);
        $q->execute();
        $q->bind_result($id_user);
        $q->fetch();
        $q->close();
        if (!$id_user) {
            $_SESSION['error'] = "User tidak dikenali.";
            header("Location: pembayaran.php");
            exit();
        }
        $_SESSION['id_user'] = $id_user;
    }

    $tgl_bayar = date('Y-m-d H:i:s');

    // Cek apakah tagihan sudah ada
    $stmt = $conn->prepare("SELECT id_tagihan, nominal_dibayar FROM tagihan WHERE id_siswa = ? AND bulan = ? AND tahun = ?");
    $stmt->bind_param("sss", $id_siswa, $bulan, $tahun);
    $stmt->execute();
    $result = $stmt->get_result();
    $tagihan_existing = $result->fetch_assoc();
    $stmt->close();

    if ($tagihan_existing) {
        // UPDATE tagihan
        $id_tagihan = $tagihan_existing['id_tagihan'];
        $total_bayar_baru = $tagihan_existing['nominal_dibayar'] + $nominal_dibayar;
        if ($total_bayar_baru >= $nominal_tagihan) {
            $total_bayar_baru = $nominal_tagihan;
            $status = 'Lunas';
        } else {
            $status = 'Cicil';
        }
        $stmt = $conn->prepare("UPDATE tagihan SET nominal_dibayar = ?, status = ? WHERE id_tagihan = ?");
        $stmt->bind_param("isi", $total_bayar_baru, $status, $id_tagihan);
        $stmt->execute();
        $stmt->close();
        $sisa = $nominal_tagihan - $total_bayar_baru;
    } else {
        // INSERT tagihan baru
        if ($nominal_dibayar >= $nominal_tagihan) {
            $total_bayar_baru = $nominal_tagihan;
            $status = 'Lunas';
            $sisa = 0;
        } else {
            $total_bayar_baru = $nominal_dibayar;
            $status = 'Cicil';
            $sisa = $nominal_tagihan - $nominal_dibayar;
        }
        $stmt = $conn->prepare("INSERT INTO tagihan (id_siswa, bulan, tahun, nominal_tagihan, nominal_dibayar, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiss", $id_siswa, $bulan, $tahun, $nominal_tagihan, $total_bayar_baru, $status);
        $stmt->execute();
        $id_tagihan = $stmt->insert_id;
        $stmt->close();
    }

    // Insert pembayaran
    $keterangan = "Pembayaran SPP bulan $bulan $tahun sebesar Rp " . number_format($nominal_dibayar, 0, ',', '.');
    $stmt = $conn->prepare("INSERT INTO pembayaran (id_tagihan, id_user, tanggal_bayar, jumlah_bayar, keterangan) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisis", $id_tagihan, $id_user, $tgl_bayar, $nominal_dibayar, $keterangan);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Pembayaran Berhasil! Sisa: Rp " . number_format($sisa, 0, ',', '.');
        if ($aksi === 'simpan_wa') {
            // Ambil nama dan no WA orang tua
            $stmt2 = $conn->prepare("SELECT nama_siswa, no_wa_ortu FROM siswa WHERE id_siswa = ?");
            $stmt2->bind_param("s", $id_siswa);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $siswa = $result2->fetch_assoc();
            $stmt2->close();
            $nama_siswa = $siswa['nama_siswa'] ?? 'Siswa';
            $no_wa = $siswa['no_wa_ortu'] ?? '';
            if (!empty($no_wa)) {
                $no_wa_siap = formatWhatsApp($no_wa);
                $pesan = "🏫 *SD MUJAHIDIN*\n"
                    . "━━━━━━━━━━━━━━━━━━━━\n"
                    . "✅ *KONFIRMASI PEMBAYARAN SPP*\n"
                    . "━━━━━━━━━━━━━━━━━━━━\n\n"
                    . "📝 *Nama Siswa:* {$nama_siswa}\n"
                    . "📆 *Bulan:* {$bulan} {$tahun}\n"
                    . "💰 *Nominal Dibayar:* Rp " . number_format($nominal_dibayar, 0, ',', '.') . "\n"
                    . "📊 *Sisa Tagihan:* Rp " . number_format($sisa, 0, ',', '.') . "\n"
                    . "🏷️ *Status:* " . strtoupper($status) . "\n\n"
                    . "🙏 Terima kasih atas pembayarannya!\n"
                    . "━━━━━━━━━━━━━━━━━━━━\n"
                    . "SPP SD Mujahidin - Melayani dengan Hati";
                $_SESSION['wa_data'] = ['no' => $no_wa_siap, 'pesan' => $pesan];
            }
        }
    } else {
        $_SESSION['error'] = "Gagal menyimpan riwayat pembayaran: " . $stmt->error;
    }
    $stmt->close();
    header("Location: pembayaran.php");
    exit();
}

// ======================================================
// 2. SETUP FILTER & PAGINATION
// ======================================================
$bulan_raw = isset($_GET['bulan_filter']) ? $_GET['bulan_filter'] : date('F');
$tahun_filter = isset($_GET['tahun_filter']) ? intval($_GET['tahun_filter']) : intval(date('Y'));
$tingkat_filter = isset($_GET['tingkat_filter']) ? $_GET['tingkat_filter'] : '1';
$kelas_huruf_filter = isset($_GET['kelas_huruf_filter']) ? $_GET['kelas_huruf_filter'] : 'A';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'semua';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Map bulan Inggris ke Indonesia
$map_bulan = [
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
$bulan_filter_display = isset($map_bulan[$bulan_raw]) ? $map_bulan[$bulan_raw] : $bulan_raw;
$bulan_filter = $bulan_filter_display;

// Validasi filter
$tingkat_filter = ($tingkat_filter == 'semua') ? 'semua' : preg_replace('/[^0-9]/', '', $tingkat_filter);
$kelas_huruf_filter = ($kelas_huruf_filter == 'semua') ? 'semua' : preg_replace('/[^A-Z]/', '', $kelas_huruf_filter);
$status_filter = in_array($status_filter, ['semua', 'Lunas', 'Cicil', 'Belum Bayar']) ? $status_filter : 'semua';

// ======================================================
// 3. QUERY UTAMA dengan Prepared Statements dan Paginasi
// ======================================================
$sql = "
    SELECT s.id_siswa, s.nama_siswa, s.alamat, s.no_wa_ortu, 
           k.nama_kelas, sk.nominal AS tagihan_spp, 
           COALESCE(t.status, 'Belum Bayar') AS status_tagihan, 
           COALESCE(t.nominal_dibayar, 0) AS sudah_dibayar
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    LEFT JOIN spp_kategori sk ON k.id_spp = sk.id_spp
    LEFT JOIN tagihan t ON s.id_siswa = t.id_siswa AND t.bulan = ? AND t.tahun = ?
    WHERE 1=1
";

$params = [$bulan_filter, $tahun_filter];
$types = "ss";

if ($tingkat_filter != 'semua') {
    $sql .= " AND k.nama_kelas LIKE ?";
    $params[] = $tingkat_filter . '%';
    $types .= "s";
}
if ($kelas_huruf_filter != 'semua') {
    $sql .= " AND k.nama_kelas LIKE ?";
    $params[] = '%' . $kelas_huruf_filter;
    $types .= "s";
}

// Filter status menggunakan HAVING karena status adalah alias
if ($status_filter != 'semua') {
    $sql .= " HAVING status_tagihan = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY k.nama_kelas ASC, s.nama_siswa ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result_siswa = $stmt->get_result();
$list_siswa_data = [];
while ($row = $result_siswa->fetch_assoc()) {
    $list_siswa_data[] = $row;
}
$stmt->close();

// Hitung total untuk paginasi (tanpa LIMIT)
$sql_count = "
    SELECT COUNT(*) as total
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    LEFT JOIN spp_kategori sk ON k.id_spp = sk.id_spp
    LEFT JOIN tagihan t ON s.id_siswa = t.id_siswa AND t.bulan = ? AND t.tahun = ?
    WHERE 1=1
";
$params_count = [$bulan_filter, $tahun_filter];
$types_count = "ss";
if ($tingkat_filter != 'semua') {
    $sql_count .= " AND k.nama_kelas LIKE ?";
    $params_count[] = $tingkat_filter . '%';
    $types_count .= "s";
}
if ($kelas_huruf_filter != 'semua') {
    $sql_count .= " AND k.nama_kelas LIKE ?";
    $params_count[] = '%' . $kelas_huruf_filter;
    $types_count .= "s";
}
// Untuk total, kita tidak perlu filter status karena status dihitung dari HAVING, 
// tapi kita perlu menghitung semua data sebelum filter status. 
// Kita gunakan subquery untuk menghitung total dengan filter status.
// Cara sederhana: hitung semua data, lalu filter status di PHP? 
// Atau kita jalankan query terpisah dengan kondisi yang sama tanpa HAVING.
// Kita akan buat query count terpisah tanpa HAVING status.
$sql_count = "
    SELECT COUNT(*) as total
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE 1=1
";
$params_count = [];
$types_count = "";
if ($tingkat_filter != 'semua') {
    $sql_count .= " AND k.nama_kelas LIKE ?";
    $params_count[] = $tingkat_filter . '%';
    $types_count .= "s";
}
if ($kelas_huruf_filter != 'semua') {
    $sql_count .= " AND k.nama_kelas LIKE ?";
    $params_count[] = '%' . $kelas_huruf_filter;
    $types_count .= "s";
}
// Tambahkan kondisi bulan dan tahun? Tidak perlu karena kita hanya hitung siswa yang ada.
// Tapi kita juga harus filter berdasarkan bulan/tahun? Karena tagihan mungkin belum ada, 
// tetap semua siswa ditampilkan. Jadi count semua siswa sesuai filter kelas.
$stmt_count = $conn->prepare($sql_count);
if (!empty($params_count)) {
    $stmt_count->bind_param($types_count, ...$params_count);
}
$stmt_count->execute();
$res_count = $stmt_count->get_result();
$total_all = $res_count->fetch_assoc()['total'];
$stmt_count->close();

// Hitung statistik status dari data yang sudah difilter (dari query utama dengan HAVING)
// Kita ambil dari $list_siswa_data yang sudah difilter status, namun untuk statistik total, 
// kita perlu menghitung berdasarkan semua siswa tanpa status filter. 
// Lebih baik ambil statistik dari query terpisah tanpa filter status.
// Tapi untuk kemudahan, kita hitung dari $list_siswa_data (sudah terfilter status) jika status_filter != 'semua'.
// Untuk memudahkan, kita akan menghitung statistik dari data yang sudah ada di $list_siswa_data.
$total_lunas = 0;
$total_belum = 0;
$total_cicil = 0;
foreach ($list_siswa_data as $s) {
    if ($s['status_tagihan'] == 'Lunas') $total_lunas++;
    elseif ($s['status_tagihan'] == 'Cicil') $total_cicil++;
    else $total_belum++;
}
$total_siswa_filter = count($list_siswa_data);

// Untuk total siswa yang sebenarnya (tanpa paginasi) agar paginasi benar
// Kita gunakan $total_all yang sudah dihitung.

// Ambil jumlah konfirmasi pending
$query_konfirmasi = $conn->prepare("SELECT COUNT(*) as total FROM konfirmasi_pembayaran WHERE status = 'pending'");
$query_konfirmasi->execute();
$res_konf = $query_konfirmasi->get_result();
$total_konfirmasi = $res_konf->fetch_assoc()['total'] ?? 0;
$query_konfirmasi->close();

// Data untuk dropdown
$bulan_list = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$kelas_huruf_list = ['semua', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Catatan Pembayaran - SD Mujahidin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .modal-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .modal-footer .btn {
            flex: 1 1 auto;
            min-width: 120px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .modal-footer .btn i {
            font-size: 16px;
        }

        .modal-footer .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }

        .modal-footer .btn-secondary:hover {
            background: #cbd5e1;
        }

        .modal-footer .btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .modal-footer .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .modal-footer .btn-danger {
            background: #dc2626;
            color: #fff;
        }

        .modal-footer .btn-danger:hover {
            background: #b91c1c;
        }

        .modal-footer .btn-wa {
            background: #25D366;
            color: #fff;
        }

        .modal-footer .btn-wa:hover {
            background: #1da851;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
        }

        @media (max-width: 480px) {
            .modal-footer .btn {
                min-width: 100%;
                flex: 1 1 100%;
            }
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }

        .pagination .active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .pagination a:hover {
            background: #f1f5f9;
        }

        .alert-wa {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .alert-wa a {
            background: #25D366;
            color: white;
            padding: 8px 18px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .alert-wa a:hover {
            background: #1da851;
        }
    </style>
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

         

    <div class="content">
        <div class="fade-in-up">
            <!-- Tampilkan notifikasi -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                    <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                    <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Notifikasi WA -->
            <?php if (isset($_SESSION['wa_data'])): ?>
                <div class="alert-wa">
                    <span><i class="fas fa-check-circle" style="color:#10b981;"></i> Pembayaran berhasil! Kirim notifikasi ke orang tua via WhatsApp.</span>
                    <a href="https://wa.me/<?= $_SESSION['wa_data']['no'] ?>?text=<?= urlencode($_SESSION['wa_data']['pesan']) ?>" target="_blank">
                        <i class="fab fa-whatsapp"></i> Kirim Sekarang
                    </a>
                </div>
                <?php unset($_SESSION['wa_data']); ?>
            <?php endif; ?>

            <!-- Statistik -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4><i class="fas fa-users"></i> Total Ditampilkan</h4>
                    <div class="stat-number"><?= number_format($total_siswa_filter) ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-check-circle"></i> Lunas</h4>
                    <div class="stat-number text-success"><?= number_format($total_lunas) ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-clock"></i> Cicil</h4>
                    <div class="stat-number text-warning"><?= number_format($total_cicil) ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-times-circle"></i> Belum Bayar</h4>
                    <div class="stat-number text-danger"><?= number_format($total_belum) ?></div>
                </div>
            </div>

            <?php if ($total_konfirmasi > 0): ?>
                <div class="alert-notification mb-4" style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px 20px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <i class="fas fa-clock" style="color: #f59e0b;"></i>
                        <strong style="margin-left: 8px;">Ada <?= $total_konfirmasi ?> konfirmasi pembayaran manual yang menunggu verifikasi!</strong>
                    </div>
                    <a href="konfirmasi_pembayaran.php" class="btn btn-sm btn-warning" style="background: #f59e0b; color: white; padding: 6px 16px; border-radius: 8px; text-decoration: none;">
                        <i class="fas fa-check-double"></i> Verifikasi Sekarang
                    </a>
                </div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Filter Data Tagihan</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-box">
                        <div class="filter-group">
                            <label><i class="fas fa-layer-group"></i> Tingkat Kelas</label>
                            <select name="tingkat_filter">
                                <option value="semua" <?= $tingkat_filter == 'semua' ? 'selected' : '' ?>>-- Semua Tingkat --</option>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>" <?= $tingkat_filter == (string)$i ? 'selected' : '' ?>>Kelas <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-chalkboard"></i> Kelas (Huruf)</label>
                            <select name="kelas_huruf_filter">
                                <option value="semua" <?= $kelas_huruf_filter == 'semua' ? 'selected' : '' ?>>-- Semua --</option>
                                <?php foreach ($kelas_huruf_list as $huruf): if ($huruf == 'semua') continue; ?>
                                    <option value="<?= $huruf ?>" <?= $kelas_huruf_filter == $huruf ? 'selected' : '' ?>><?= $huruf ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-info-circle"></i> Status Bayar</label>
                            <select name="status_filter">
                                <option value="semua" <?= $status_filter == 'semua' ? 'selected' : '' ?>>-- Semua Status --</option>
                                <option value="Lunas" <?= $status_filter == 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                                <option value="Cicil" <?= $status_filter == 'Cicil' ? 'selected' : '' ?>>Cicil</option>
                                <option value="Belum Bayar" <?= $status_filter == 'Belum Bayar' ? 'selected' : '' ?>>Belum Bayar</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Bulan</label>
                            <select name="bulan_filter">
                                <?php foreach ($bulan_list as $b): ?>
                                    <option value="<?= $b ?>" <?= $bulan_filter == $b ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                            <select name="tahun_filter">
                                <?php for ($y = 2024; $y <= 2026; $y++): ?>
                                    <option value="<?= $y ?>" <?= $tahun_filter == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
                        <?php
                        $default_bulan = date('F');
                        $default_bulan_ind = $map_bulan[$default_bulan] ?? $default_bulan;
                        if ($tingkat_filter != '1' || $kelas_huruf_filter != 'A' || $status_filter != 'semua' || $bulan_filter != $default_bulan_ind || $tahun_filter != date('Y')):
                        ?>
                            <a href="pembayaran.php" class="btn btn-secondary"><i class="fas fa-undo-alt"></i> Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabel -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Daftar Siswa & Tagihan SPP</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th width="50">No</th>
                                    <th>Siswa</th>
                                    <th width="90">Kelas</th>
                                    <th width="110">Tagihan</th>
                                    <th width="110">Dibayar</th>
                                    <th width="150">Status</th>
                                    <th width="90">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = $offset + 1;
                                if ($total_siswa_filter > 0):
                                    foreach ($list_siswa_data as $s):
                                        $nominal_tagihan = $s['tagihan_spp'] ?? 400000;
                                        $sudah_dibayar = $s['sudah_dibayar'] ?? 0;
                                        $sisa = $nominal_tagihan - $sudah_dibayar;
                                        $st = strtolower($s['status_tagihan']);
                                        if ($st == 'lunas') {
                                            $status_class = 'badge-success';
                                            $status_text = 'LUNAS';
                                        } elseif ($st == 'cicil') {
                                            $status_class = 'badge-warning';
                                            $status_text = 'CICIL';
                                        } else {
                                            $status_class = 'badge-danger';
                                            $status_text = 'BELUM BAYAR';
                                        }
                                ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td>
                                                <div class="siswa-name"><?= htmlspecialchars($s['nama_siswa']) ?></div>
                                                <div class="siswa-wa">
                                                    <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($s['no_wa_ortu'] ?? '-') ?>
                                                </div>
                                            </td>
                                            <td><span class="kelas-badge"><?= $s['nama_kelas'] ?></span></td>
                                            <td class="text-nowrap">Rp <?= number_format($nominal_tagihan, 0, ',', '.') ?></td>
                                            <td class="text-nowrap">Rp <?= number_format($sudah_dibayar, 0, ',', '.') ?></td>
                                            <td>
                                                <div class="status-wrapper">
                                                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                                    <?php if ($sisa > 0 && $sisa < $nominal_tagihan): ?>
                                                        <div class="sisa-wrapper">
                                                            <span class="sisa-label">Sisa:</span>
                                                            <span class="sisa-nominal">Rp <?= number_format($sisa, 0, ',', '.') ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($s['status_tagihan'] != 'Lunas'): ?>
                                                    <button class="btn btn-success btn-sm btn-bayar" onclick="showForm(<?= htmlspecialchars(json_encode($s)) ?>, <?= $nominal_tagihan ?>)">
                                                        <i class="fas fa-credit-card"></i> Bayar
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-success"><i class="fas fa-check-circle"></i> Lunas</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php
                                    endforeach;
                                else:
                                    ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-inbox fa-2x text-gray mb-2"></i>
                                            <p>Tidak ada data siswa ditemukan dengan filter ini.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Paginasi -->
            <?php if ($total_all > $limit): ?>
                <div class="pagination">
                    <?php
                    $total_pages = ceil($total_all / $limit);
                    $query_params = $_GET;
                    unset($query_params['page']);
                    $base_url = '?' . http_build_query($query_params);
                    for ($i = 1; $i <= $total_pages; $i++):
                        $active = ($i == $page) ? 'active' : '';
                    ?>
                        <a href="<?= $base_url . '&page=' . $i ?>" class="<?= $active ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

            <div class="footer" style="margin-top: 20px;">
                <p>&copy; 2026 SD Mujahidin - Sistem Informasi Pembayaran SPP</p>
            </div>
        </div>
    </div>

    <!-- MODAL INPUT PEMBAYARAN -->
    <div id="formBayar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-money-bill-wave"></i> Input Pembayaran</h3>
                <p>Siswa: <span id="nama_siswa_display"></span></p>
            </div>
            <div class="modal-body">
                <form method="POST" id="formPembayaran">
                    <input type="hidden" name="id_siswa" id="id_siswa">
                    <input type="hidden" name="nama_siswa" id="nama_siswa_hidden">
                    <input type="hidden" name="no_wa_ortu" id="no_wa_hidden">
                    <input type="hidden" name="bulan" value="<?= htmlspecialchars($bulan_filter) ?>">
                    <input type="hidden" name="tahun" value="<?= htmlspecialchars($tahun_filter) ?>">
                    <input type="hidden" name="aksi" id="aksi_submit" value="">

                    <div class="form-group">
                        <label>Nominal Bayar (Rp)</label>
                        <input type="text" name="nominal_dibayar" id="input_nominal" class="input-nominal" placeholder="Masukkan nominal bayar..." required>
                        <small class="text-gray">*Isi sesuai kemampuan (bisa cicil)</small>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="confirmSubmit('simpan')">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                        <button type="button" class="btn btn-wa" onclick="confirmSubmit('simpan_wa')">
                            <i class="fab fa-whatsapp"></i> Simpan & Kirim WA
                        </button>
                        <button type="button" class="btn btn-danger" onclick="hideForm()">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function showForm(s, tagihan_spp) {
            document.getElementById('id_siswa').value = s.id_siswa;
            document.getElementById('nama_siswa_hidden').value = s.nama_siswa;
            document.getElementById('no_wa_hidden').value = s.no_wa_ortu || '';
            document.getElementById('nama_siswa_display').innerText = s.nama_siswa;
            var sisa = tagihan_spp - (s.sudah_dibayar || 0);
            document.getElementById('input_nominal').value = sisa > 0 ? sisa : 0;
            document.getElementById('formBayar').style.display = 'flex';
        }

        function hideForm() {
            document.getElementById('formBayar').style.display = 'none';
            document.getElementById('formPembayaran').reset();
        }

        function confirmSubmit(action) {
            var nominal = document.getElementById('input_nominal').value;
            if (!nominal || parseInt(nominal) <= 0) {
                if (typeof showToast === 'function') {
                    showToast('Masukkan nominal bayar yang valid!', 'error');
                } else {
                    alert('Masukkan nominal bayar yang valid!');
                }
                return;
            }

            var message = (action === 'simpan_wa') ?
                'Simpan pembayaran dan kirim notifikasi WhatsApp ke orang tua?' :
                'Simpan pembayaran ini?';

            if (typeof confirmDialog === 'function') {
                confirmDialog(message, 'Konfirmasi', function() {
                    document.getElementById('aksi_submit').value = action;
                    document.getElementById('formPembayaran').submit();
                });
            } else {
                if (confirm(message)) {
                    document.getElementById('aksi_submit').value = action;
                    document.getElementById('formPembayaran').submit();
                }
            }
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.querySelectorAll('.alert').forEach(function(el) {
                    el.style.transition = 'opacity 0.5s';
                    el.style.opacity = '0';
                    setTimeout(function() {
                        el.style.display = 'none';
                    }, 500);
                });
            }, 5000);
        });
    </script>
</body>

</html>