<?php

/** @var mysqli $conn */
session_start();
include '../koneksi.php';

// Perbaikan: Cek hak akses langsung via $_SESSION agar tidak memicu "Call to undefined function"
if (!isset($_SESSION['username']) || ($_SESSION['level'] !== 'bendahara' && $_SESSION['level'] !== 'kepsek' && $_SESSION['level'] !== 'admin')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$mode = isset($_GET['mode']) ? mysqli_real_escape_string($conn, $_GET['mode']) : 'bulan';
$tgl = isset($_GET['tgl']) ? mysqli_real_escape_string($conn, $_GET['tgl']) : date('Y-m-d');
$bulan = isset($_GET['bulan']) ? mysqli_real_escape_string($conn, $_GET['bulan']) : '';
$tahun = isset($_GET['tahun']) ? mysqli_real_escape_string($conn, $_GET['tahun']) : date('Y');

// Mapping bulan
$map_bulan = [
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
if (empty($bulan)) {
    $bulan = isset($map_bulan[date('m')]) ? $map_bulan[date('m')] : 'Januari';
}

// Hitung total siswa
$total_siswa_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa");
$total_siswa = mysqli_fetch_assoc($total_siswa_query)['total'] ?? 0;

$sudah_bayar = 0;
$total_uang = 0;
$judul_teks = "";

switch ($mode) {
    case 'hari':
        $judul_teks = "Tanggal: " . date('d/m/Y', strtotime($tgl));

        // PERBAIKAN: JOIN ke tabel tagihan karena tabel pembayaran tidak punya kolom id_siswa
        $q_siswa = mysqli_query($conn, "SELECT COUNT(DISTINCT t.id_siswa) as total FROM pembayaran p JOIN tagihan t ON p.id_tagihan = t.id_tagihan WHERE DATE(p.tanggal_bayar) = '$tgl'");
        $sudah_bayar = $q_siswa ? (mysqli_fetch_assoc($q_siswa)['total'] ?? 0) : 0;

        $q_uang = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_bayar), 0) as total FROM pembayaran WHERE DATE(tanggal_bayar) = '$tgl'");
        $total_uang = $q_uang ? (mysqli_fetch_assoc($q_uang)['total'] ?? 0) : 0;
        break;

    case 'bulan':
        $judul_teks = "Bulan: $bulan $tahun";

        $q_siswa = mysqli_query($conn, "SELECT COUNT(DISTINCT id_siswa) as total FROM tagihan WHERE bulan = '$bulan' AND tahun = '$tahun' AND status IN ('Lunas', 'Cicil')");
        $sudah_bayar = $q_siswa ? (mysqli_fetch_assoc($q_siswa)['total'] ?? 0) : 0;

        // PERBAIKAN: JOIN ke tabel tagihan karena tabel pembayaran tidak punya kolom bulan & tahun
        $q_uang = mysqli_query($conn, "SELECT COALESCE(SUM(p.jumlah_bayar), 0) as total FROM pembayaran p JOIN tagihan t ON p.id_tagihan = t.id_tagihan WHERE t.bulan = '$bulan' AND t.tahun = '$tahun'");
        $total_uang = $q_uang ? (mysqli_fetch_assoc($q_uang)['total'] ?? 0) : 0;
        break;

    case 'semester':
        $bln_angka = date('m');
        $periode = ($bln_angka <= 6) ? "Genap" : "Ganjil";
        $judul_teks = "Semester $periode - $tahun";

        $list_bulan = ($periode == "Genap") ? "'Januari','Februari','Maret','April','Mei','Juni'" : "'Juli','Agustus','September','Oktober','November','Desember'";

        $q_siswa = mysqli_query($conn, "SELECT COUNT(DISTINCT id_siswa) as total FROM tagihan WHERE bulan IN ($list_bulan) AND tahun = '$tahun' AND status IN ('Lunas', 'Cicil')");
        $sudah_bayar = $q_siswa ? (mysqli_fetch_assoc($q_siswa)['total'] ?? 0) : 0;

        // PERBAIKAN: JOIN ke tabel tagihan karena tabel pembayaran tidak punya kolom bulan & tahun
        $q_uang = mysqli_query($conn, "SELECT COALESCE(SUM(p.jumlah_bayar), 0) as total FROM pembayaran p JOIN tagihan t ON p.id_tagihan = t.id_tagihan WHERE t.bulan IN ($list_bulan) AND t.tahun = '$tahun'");
        $total_uang = $q_uang ? (mysqli_fetch_assoc($q_uang)['total'] ?? 0) : 0;
        break;

    default:
        echo json_encode(['error' => 'Mode tidak dikenal']);
        exit();
}

$belum_bayar = max(0, $total_siswa - $sudah_bayar);

// Kirim data angka murni tanpa format Rp string agar JS .toLocaleString() di halaman utama tidak rusak
echo json_encode([
    'judul' => $judul_teks,
    'total_siswa' => intval($total_siswa),
    'sudah_bayar' => intval($sudah_bayar),
    'belum_bayar' => intval($belum_bayar),
    'total_uang' => intval($total_uang)
]);
exit();
