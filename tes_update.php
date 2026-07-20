<?php
include 'koneksi.php';

// Ambil satu Order ID transaksi DANA yang sukses tadi dari HeidiSQL (tabel transaksi_midtrans)
// Contoh di bawah menggunakan salah satu ID yang sempat sukses dari screenshot dashboard-mu:
$order_id = 'SPP-12-1784220988';
$gross_amount = 100000;

echo "<h2>Simulasi Update Database Lokal</h2>";

$query_transaksi = mysqli_query($conn, "SELECT * FROM transaksi_midtrans WHERE order_id = '$order_id'");
$transaksi = mysqli_fetch_assoc($query_transaksi);

if ($transaksi) {
    $id_tagihan = $transaksi['id_tagihan'];
    echo "✅ Transaksi Midtrans ditemukan untuk Tagihan ID: " . $id_tagihan . "<br>";

    $query_tagihan = mysqli_query($conn, "SELECT * FROM tagihan WHERE id_tagihan = '$id_tagihan'");
    $tagihan = mysqli_fetch_assoc($query_tagihan);

    if ($tagihan) {
        $nominal_tagihan = $tagihan['nominal_tagihan'];
        $new_nominal_dibayar = $tagihan['nominal_dibayar'] + $gross_amount;
        $status_tagihan = ($new_nominal_dibayar >= $nominal_tagihan) ? 'Lunas' : 'Cicil';

        // Jalankan Update Tagihan
        $update = mysqli_query($conn, "UPDATE tagihan SET status = '$status_tagihan', nominal_dibayar = '$new_nominal_dibayar' WHERE id_tagihan = '$id_tagihan'");
        if ($update) {
            echo "✅ Status Tagihan sukses di-update menjadi: " . $status_tagihan . "<br>";
        } else {
            echo "❌ Gagal update tagihan: " . mysqli_error($conn) . "<br>";
        }

        // Jalankan Insert Pembayaran
        $insert = mysqli_query($conn, "INSERT INTO pembayaran (id_tagihan, id_user, jumlah_bayar, tanggal_bayar, keterangan, metode_pembayaran) 
                                       VALUES ('$id_tagihan', 1, '$gross_amount', NOW(), 'Simulasi Pembayaran', 'Midtrans')");
        if ($insert) {
            echo "✅ Riwayat Pembayaran sukses dicatat!<br>";
        } else {
            echo "❌ Gagal simpan pembayaran: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "❌ Tagihan tidak ditemukan di database.<br>";
    }
} else {
    echo "❌ Order ID '$order_id' tidak ada di tabel transaksi_midtrans. Silakan ganti variabel \$order_id di baris ke-6 dengan Order ID yang ada di HeidiSQL kamu!<br>";
}
