<?php
session_start();
require_once __DIR__ . '/../koneksi.php';

// Cek login
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: ../login.php");
    exit;
}

// Cek ID
if (!isset($_GET['id_siswa']) || empty($_GET['id_siswa'])) {
    $_SESSION['error'] = "ID Siswa tidak valid!";
    header("Location: siswa.php");
    exit;
}

$id_siswa = mysqli_real_escape_string($conn, $_GET['id_siswa']);

// Transaksi hapus berantai
mysqli_begin_transaction($conn);

try {
    mysqli_query($conn, "DELETE FROM pembayaran WHERE id_tagihan IN (SELECT id_tagihan FROM tagihan WHERE id_siswa = '$id_siswa')");
    mysqli_query($conn, "DELETE FROM transaksi_midtrans WHERE id_tagihan IN (SELECT id_tagihan FROM tagihan WHERE id_siswa = '$id_siswa')");
    mysqli_query($conn, "DELETE FROM konfirmasi_pembayaran WHERE id_tagihan IN (SELECT id_tagihan FROM tagihan WHERE id_siswa = '$id_siswa')");
    mysqli_query($conn, "DELETE FROM tagihan WHERE id_siswa = '$id_siswa'");
    mysqli_query($conn, "DELETE FROM siswa WHERE id_siswa = '$id_siswa'");

    mysqli_commit($conn);
    $_SESSION['success'] = "Data siswa dan seluruh riwayat berhasil dihapus!";
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Gagal menghapus: " . $e->getMessage();
}

header("Location: siswa.php");
exit;
