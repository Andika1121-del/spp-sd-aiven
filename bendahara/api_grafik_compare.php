<?php

/** @var mysqli $conn */
session_start();
require_once __DIR__ . '/../koneksi.php';

// Cek hak akses langsung via $_SESSION agar tidak memicu "Call to undefined function"
if (!isset($_SESSION['username']) || ($_SESSION['level'] !== 'bendahara' && $_SESSION['level'] !== 'kepsek' && $_SESSION['level'] !== 'admin')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$mode = isset($_GET['mode']) ? mysqli_real_escape_string($conn, $_GET['mode']) : 'bulan';
$tahun_pilihan = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

$allowed_modes = ['minggu', 'bulan', 'semester', 'tahun'];
if (!in_array($mode, $allowed_modes)) {
    echo json_encode(['error' => 'Invalid mode parameter']);
    exit();
}

$labels = [];
$dataset = [];
$judul_grafik = "";

switch ($mode) {
    case 'minggu':
        $judul_grafik = "Pendapatan 7 Hari Terakhir";
        for ($i = 6; $i >= 0; $i--) {
            $tgl = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d M', strtotime($tgl));

            // Kolom tanggal_bayar & jumlah_bayar aman di tabel pembayaran
            $q = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_bayar), 0) as total FROM pembayaran WHERE DATE(tanggal_bayar) = '$tgl'");
            $res = mysqli_fetch_assoc($q);
            $dataset[] = intval($res['total']);
        }
        break;

    case 'bulan':
        $judul_grafik = "Tren Pendapatan Bulanan Tahun $tahun_pilihan";
        $list_bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        foreach ($list_bulan as $bln) {
            $labels[] = $bln;

            // PERBAIKAN: Wajib JOIN ke tabel tagihan untuk memfilter bulan dan tahun pilihan
            $q = mysqli_query($conn, "SELECT COALESCE(SUM(p.jumlah_bayar), 0) as total 
                                       FROM pembayaran p 
                                       JOIN tagihan t ON p.id_tagihan = t.id_tagihan 
                                       WHERE t.bulan = '$bln' AND t.tahun = '$tahun_pilihan'");
            $res = mysqli_fetch_assoc($q);
            $dataset[] = intval($res['total']);
        }
        break;

    case 'semester':
        $judul_grafik = "Perbandingan Semester Tahun $tahun_pilihan";
        $labels = ['Semester 1 (Jan-Jun)', 'Semester 2 (Jul-Des)'];

        // PERBAIKAN: JOIN ke tagihan untuk Semester 1
        $q1 = mysqli_query($conn, "SELECT COALESCE(SUM(p.jumlah_bayar), 0) as total 
                                    FROM pembayaran p 
                                    JOIN tagihan t ON p.id_tagihan = t.id_tagihan 
                                    WHERE t.tahun = '$tahun_pilihan' 
                                    AND t.bulan IN ('Januari','Februari','Maret','April','Mei','Juni')");
        $res1 = mysqli_fetch_assoc($q1);
        $dataset[] = intval($res1['total']);

        // PERBAIKAN: JOIN ke tagihan untuk Semester 2
        $q2 = mysqli_query($conn, "SELECT COALESCE(SUM(p.jumlah_bayar), 0) as total 
                                    FROM pembayaran p 
                                    JOIN tagihan t ON p.id_tagihan = t.id_tagihan 
                                    WHERE t.tahun = '$tahun_pilihan' 
                                    AND t.bulan IN ('Juli','Agustus','September','Oktober','November','Desember')");
        $res2 = mysqli_fetch_assoc($q2);
        $dataset[] = intval($res2['total']);
        break;

    case 'tahun':
        $judul_grafik = "Perbandingan Total Pendapatan Antar Tahun";
        $tahun_sekarang = date('Y');
        $tahun_mulai = $tahun_sekarang - 2;

        for ($thn = $tahun_mulai; $thn <= $tahun_sekarang; $thn++) {
            $labels[] = "Tahun $thn";

            // PERBAIKAN: JOIN ke tagihan untuk memfilter tahun riwayat pendapatan
            $q = mysqli_query($conn, "SELECT COALESCE(SUM(p.jumlah_bayar), 0) as total 
                                       FROM pembayaran p 
                                       JOIN tagihan t ON p.id_tagihan = t.id_tagihan 
                                       WHERE t.tahun = '$thn'");
            $res = mysqli_fetch_assoc($q);
            $dataset[] = intval($res['total']);
        }
        break;
}

// Mengembalikan response JSON yang bersih tanpa error teks HTML / whitespace liar
echo json_encode([
    'status' => 'success',
    'judul' => $judul_grafik,
    'labels' => $labels,
    'dataset' => $dataset,
    'mode' => $mode,
    'tahun' => $tahun_pilihan
]);
exit();
