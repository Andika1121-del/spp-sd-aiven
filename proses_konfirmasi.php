<?php
// Aktifkan laporan error untuk debugging (hapus di production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'koneksi.php'; // pastikan file ini mendefinisikan $conn

// Fungsi bantuan redirect dengan alert
function redirect($pesan, $url)
{
    echo "<script>
            alert(" . json_encode($pesan) . "); 
            window.location.href=" . json_encode($url) . ";
          </script>";
    exit();
}

// Validasi input
$id_tagihan = isset($_POST['id_tagihan']) ? mysqli_real_escape_string($conn, $_POST['id_tagihan']) : '';
$nis = isset($_POST['nis']) ? mysqli_real_escape_string($conn, $_POST['nis']) : '';

if (empty($id_tagihan) || empty($nis)) {
    redirect("❌ Data tidak lengkap! Silakan coba lagi.", "cek_spp.php");
}

// 1. Ambil id_siswa berdasarkan nis
$query_siswa = "SELECT id_siswa FROM siswa WHERE nis = '$nis'";
$result_siswa = mysqli_query($conn, $query_siswa);

if (!$result_siswa || mysqli_num_rows($result_siswa) == 0) {
    redirect("❌ Siswa dengan NIS '$nis' tidak ditemukan.", "cek_spp.php");
}

$data_siswa = mysqli_fetch_assoc($result_siswa);
$id_siswa = $data_siswa['id_siswa'];

// (Opsional) Cek apakah id_tagihan milik siswa tersebut
$query_cek_tagihan = "SELECT id_tagihan FROM tagihan WHERE id_tagihan = '$id_tagihan' AND id_siswa = '$id_siswa'";
$result_cek = mysqli_query($conn, $query_cek_tagihan);
if (!$result_cek || mysqli_num_rows($result_cek) == 0) {
    redirect("❌ Tagihan tidak valid atau bukan milik siswa ini.", "cek_spp.php?nis=" . urlencode($nis));
}

// Setup folder upload
$upload_dir = 'uploads/bukti_pembayaran/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Cek apakah ada file yang di-upload
if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === 0) {
    $ext = strtolower(pathinfo($_FILES['foto_bukti']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($ext, $allowed)) {
        redirect('❌ Format tidak didukung! Gunakan JPG, PNG, atau PDF.', 'cek_spp.php?nis=' . urlencode($nis));
    }

    // Nama file unik
    $new_filename = 'bukti_' . $id_tagihan . '_' . time() . '.' . $ext;
    $upload_path = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $upload_path)) {
        // Query INSERT ke tabel konfirmasi_pembayaran
        // Sesuaikan nama kolom dengan struktur tabel Anda:
        // id_konfirmasi (auto_increment), id_tagihan, id_siswa, bukti_transfer, status, tgl_konfirmasi, nominal_bayar (opsional), catatan (opsional)
        $query = "INSERT INTO konfirmasi_pembayaran 
                  (id_tagihan, id_siswa, bukti_transfer, status, tgl_konfirmasi) 
                  VALUES 
                  ('$id_tagihan', '$id_siswa', '$new_filename', 'pending', NOW())";

        if (mysqli_query($conn, $query)) {
            redirect('✅ Bukti pembayaran berhasil diupload! Menunggu verifikasi bendahara.', 'cek_spp.php?nis=' . urlencode($nis));
        } else {
            // Jika query gagal, tampilkan error database
            redirect('❌ Database Error: ' . mysqli_error($conn), 'cek_spp.php?nis=' . urlencode($nis));
        }
    } else {
        redirect('❌ Gagal memindahkan file ke server. Periksa izin folder uploads.', 'cek_spp.php?nis=' . urlencode($nis));
    }
} else {
    // Jika tidak ada file atau error upload
    $error_upload = isset($_FILES['foto_bukti']['error']) ? $_FILES['foto_bukti']['error'] : 'Tidak ada file';
    redirect('❌ Gagal upload file (Kode error: ' . $error_upload . '). Maksimal 2MB.', 'cek_spp.php?nis=' . urlencode($nis));
}
