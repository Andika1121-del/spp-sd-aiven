<?php

/** @var mysqli $conn */ // Pastikan koneksi database sudah tersedia melalui $conn
session_start();
include '../koneksi.php';

// ========== CEK AKSES BENDARAHA ==========
if (!isset($_SESSION['login']) || $_SESSION['level'] != 'bendahara') {
    exit("Akses ditolak");
}

$id_konfirmasi = mysqli_real_escape_string($conn, $_GET['id']);
$aksi = $_GET['aksi'];

if ($aksi == 'setuju') {
    // 1. Ambil data konfirmasi dengan JOIN ke tagihan dan siswa
    $q_konf = mysqli_query($conn, "SELECT k.*, t.id_tagihan, t.id_siswa, t.bulan, t.tahun, t.nominal_tagihan, s.nis 
                                   FROM konfirmasi_pembayaran k
                                   JOIN tagihan t ON k.id_tagihan = t.id_tagihan
                                   JOIN siswa s ON t.id_siswa = s.id_siswa
                                   WHERE k.id_konfirmasi = '$id_konfirmasi'");

    if (!$q_konf || mysqli_num_rows($q_konf) == 0) {
        $_SESSION['error'] = "Data konfirmasi tidak ditemukan!";
        header("Location: konfirmasi_pembayaran.php");
        exit();
    }

    $data = mysqli_fetch_assoc($q_konf);
    $id_tagihan = $data['id_tagihan'];
    $id_siswa = $data['id_siswa'];
    $nominal = $data['nominal_tagihan'];
    $bulan = $data['bulan'];
    $tahun = $data['tahun'];
    $tgl = date('Y-m-d H:i:s');

    // 2. UPDATE TAGIHAN: Jadi Lunas
    $update_tagihan = mysqli_query($conn, "UPDATE tagihan SET status = 'Lunas', nominal_dibayar = '$nominal' WHERE id_tagihan = '$id_tagihan'");

    if (!$update_tagihan) {
        $_SESSION['error'] = "Gagal update tagihan: " . mysqli_error($conn);
        header("Location: konfirmasi_pembayaran.php");
        exit();
    }

    // 3. INSERT PEMBAYARAN: Catat ke riwayat transaksi
    $id_user = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 1;
    $ket = "Pembayaran via Transfer (Diverifikasi Bendahara)";
    $insert_pembayaran = mysqli_query($conn, "INSERT INTO pembayaran (id_siswa, id_user, tgl_bayar, bulan, tahun, nominal_dibayar, keterangan) 
                         VALUES ('$id_siswa', '$id_user', '$tgl', '$bulan', '$tahun', '$nominal', '$ket')");

    if (!$insert_pembayaran) {
        $_SESSION['error'] = "Gagal insert pembayaran: " . mysqli_error($conn);
        header("Location: konfirmasi_pembayaran.php");
        exit();
    }

    // 4. UPDATE KONFIRMASI: Ubah status jadi approved
    mysqli_query($conn, "UPDATE konfirmasi_pembayaran SET status = 'approved' WHERE id_konfirmasi = '$id_konfirmasi'");

    $_SESSION['success'] = "✅ Bukti transfer berhasil disetujui! Tagihan otomatis LUNAS.";
} elseif ($aksi == 'tolak') {
    // Jika ditolak
    mysqli_query($conn, "UPDATE konfirmasi_pembayaran SET status = 'rejected' WHERE id_konfirmasi = '$id_konfirmasi'");
    $_SESSION['error'] = "❌ Bukti transfer telah ditolak.";
} else {
    $_SESSION['error'] = "Aksi tidak valid!";
}

header("Location: konfirmasi_pembayaran.php");
exit();
