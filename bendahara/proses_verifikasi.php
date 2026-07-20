<?php

/** @var mysqli $conn */ // Pastikan koneksi database sudah tersedia melalui $conn
session_start();
include '../koneksi.php';

// ========== CEK AKSES BENDARAHA ==========
// Hindari fungsi isBendahara() yang mungkin tidak didefinisikan
if (!isset($_SESSION['login']) || $_SESSION['level'] != 'bendahara') {
    header("Location: ../index.php");
    exit();
}

$id_konfirmasi = isset($_GET['id']) ? intval($_GET['id']) : 0;
$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

if ($id_konfirmasi == 0 || empty($aksi)) {
    die("❌ Parameter tidak lengkap!");
}

// ========== AMBIL DATA KONFIRMASI ==========
// Gunakan id_konfirmasi, bukan id
$query = mysqli_query($conn, "SELECT * FROM konfirmasi_pembayaran WHERE id_konfirmasi = '$id_konfirmasi'");

// Jika query gagal atau data tidak ditemukan
if (!$query || mysqli_num_rows($query) == 0) {
    $_SESSION['error'] = "Data konfirmasi tidak ditemukan!";
    header("Location: konfirmasi_pembayaran.php");
    exit();
}

$data = mysqli_fetch_assoc($query);

if ($aksi == 'setuju') {
    // 1. Update status konfirmasi
    $update_konf = mysqli_query($conn, "UPDATE konfirmasi_pembayaran SET status = 'approved' WHERE id_konfirmasi = '$id_konfirmasi'");
    if (!$update_konf) {
        $_SESSION['error'] = "Gagal update konfirmasi: " . mysqli_error($conn);
        header("Location: konfirmasi_pembayaran.php");
        exit();
    }

    // 2. Update tagihan menjadi lunas
    $update_tagihan = mysqli_query($conn, "UPDATE tagihan SET status = 'Lunas', nominal_dibayar = nominal_tagihan WHERE id_tagihan = '{$data['id_tagihan']}'");
    if (!$update_tagihan) {
        $_SESSION['error'] = "Gagal update tagihan: " . mysqli_error($conn);
        header("Location: konfirmasi_pembayaran.php");
        exit();
    }

    // 3. Ambil data tagihan untuk insert pembayaran
    $q_tagihan = mysqli_query($conn, "SELECT * FROM tagihan WHERE id_tagihan = '{$data['id_tagihan']}'");
    if (!$q_tagihan || mysqli_num_rows($q_tagihan) == 0) {
        $_SESSION['error'] = "Data tagihan tidak ditemukan!";
        header("Location: konfirmasi_pembayaran.php");
        exit();
    }
    $tagihan = mysqli_fetch_assoc($q_tagihan);

    // 4. Insert ke tabel pembayaran
    $id_user = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 1; // fallback
    $insert_pembayaran = mysqli_query($conn, "INSERT INTO pembayaran (id_tagihan, id_user, tanggal_bayar, jumlah_bayar, keterangan, metode_pembayaran) 
                     VALUES ('{$tagihan['id_tagihan']}', '$id_user', NOW(), '{$tagihan['nominal_tagihan']}', 'Konfirmasi Manual via Upload Bukti', 'Manual/Tunai')");

    if (!$insert_pembayaran) {
        $_SESSION['error'] = "Gagal insert pembayaran: " . mysqli_error($conn);
        header("Location: konfirmasi_pembayaran.php");
        exit();
    }

    $_SESSION['success'] = "✅ Pembayaran berhasil diverifikasi! Tagihan LUNAS.";
} elseif ($aksi == 'tolak') {
    // Jika ditolak
    $update_tolak = mysqli_query($conn, "UPDATE konfirmasi_pembayaran SET status = 'rejected' WHERE id_konfirmasi = '$id_konfirmasi'");
    if (!$update_tolak) {
        $_SESSION['error'] = "Gagal update konfirmasi: " . mysqli_error($conn);
        header("Location: konfirmasi_pembayaran.php");
        exit();
    }
    $_SESSION['error'] = "❌ Pembayaran ditolak!";
} else {
    $_SESSION['error'] = "Aksi tidak valid!";
}

header("Location: konfirmasi_pembayaran.php");
exit();
