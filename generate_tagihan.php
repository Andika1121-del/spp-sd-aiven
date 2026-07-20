<?php
include 'koneksi.php';

// Tentukan bulan dan tahun untuk tagihan baru
$bulan_baru = "Juni";
$tahun_baru = "2026";
$nominal_default = 400000;

// Ambil semua siswa
$siswa = mysqli_query($conn, "SELECT id_siswa FROM siswa");

while ($s = mysqli_fetch_assoc($siswa)) {
    $id = $s['id_siswa'];

    // Cek dulu apakah tagihan bulan ini sudah ada agar tidak duplikat
    $cek = mysqli_query($conn, "SELECT * FROM tagihan WHERE id_siswa = '$id' AND bulan = '$bulan_baru' AND tahun = '$tahun_baru'");

    if (mysqli_num_rows($cek) == 0) {
        // Jika belum ada, masukkan tagihannya
        mysqli_query($conn, "INSERT INTO tagihan (id_siswa, bulan, tahun, nominal_tagihan, nominal_dibayar, status) 
                             VALUES ('$id', '$bulan_baru', '$tahun_baru', '$nominal_default', 0, 'Belum Bayar')");
    }
}
echo "Tagihan massal berhasil digenerate!";
