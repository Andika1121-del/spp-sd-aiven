<?php

/**
 * notification_handler.php
 * Endpoint webhook untuk menerima kiriman data otomatis dari Midtrans (Lewat Ngrok).
 * 
 * Perbaikan:
 * - Prepared statements untuk keamanan SQL Injection.
 * - Pengecekan duplikat notifikasi (idempotensi).
 * - Validasi gross_amount dari notifikasi dengan yang ada di database.
 * - Update status tagihan (Cicil/Lunas) sesuai nominal dibayar.
 * - Insert ke tabel pembayaran bendahara dengan id_user = NULL (karena otomatis).
 * - Logging aktivitas.
 */

// Sertakan file koneksi dan konfigurasi Midtrans
include 'koneksi.php';
require_once 'config_midtrans.php';
require_once 'Midtrans/Notification.php';

// Fungsi logging sederhana
function writeLog($message)
{
    $logFile = 'webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Cek koneksi database
if (!$conn) {
    writeLog("Koneksi database gagal: " . mysqli_connect_error());
    http_response_code(500);
    die('Database connection error');
}

// Verifikasi signature key menggunakan SDK Midtrans
try {
    $notif = new \Midtrans\Notification();
} catch (Exception $e) {
    writeLog("Signature validation gagal: " . $e->getMessage());
    http_response_code(403);
    die('Signature validation failed: ' . $e->getMessage());
}

// Ambil properti notifikasi
$order_id           = $notif->order_id ?? '';
$transaction_status = $notif->transaction_status ?? '';
$payment_type       = $notif->payment_type ?? '';
$fraud_status       = $notif->fraud_status ?? '';
$gross_amount_notif = $notif->gross_amount ?? 0;

if (empty($order_id)) {
    writeLog("Missing order_id");
    http_response_code(400);
    die('Missing order_id');
}

writeLog("Notifikasi diterima: order_id=$order_id, status=$transaction_status, amount=$gross_amount_notif");

// Cari transaksi lokal
$stmt = $conn->prepare("SELECT * FROM transaksi_midtrans WHERE order_id = ? LIMIT 1");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    writeLog("Transaksi dengan order_id $order_id tidak ditemukan di database");
    http_response_code(404);
    die('Transaksi tidak ditemukan');
}

$transaksi = $result->fetch_assoc();
$id_tagihan      = $transaksi['id_tagihan'];
$nis             = $transaksi['nis'];
$gross_amount_db = $transaksi['gross_amount'];
$current_status  = $transaksi['status'];

// Validasi gross_amount
if (abs($gross_amount_notif - $gross_amount_db) > 0.01) {
    writeLog("Gross amount mismatch: notif=$gross_amount_notif, db=$gross_amount_db");
    http_response_code(400);
    die('Gross amount mismatch');
}

// Cegah duplikat
if ($current_status == 'success') {
    writeLog("Notifikasi duplikat untuk order_id $order_id yang sudah sukses. Abaikan.");
    http_response_code(200);
    echo 'OK (Duplicate, already success)';
    exit;
}

// Tentukan status update
$status_update = 'pending';
switch ($transaction_status) {
    case 'capture':
        if ($payment_type == 'credit_card') {
            $status_update = ($fraud_status == 'challenge') ? 'pending' : 'success';
        }
        break;
    case 'settlement':
        $status_update = 'success';
        break;
    case 'pending':
        $status_update = 'pending';
        break;
    case 'deny':
    case 'cancel':
    case 'expire':
    case 'failure':
        $status_update = 'cancel';
        break;
    default:
        $status_update = 'pending';
}

writeLog("Status update untuk transaksi: $status_update");

// 1. Update status di tabel transaksi_midtrans
$updateTransStmt = $conn->prepare("UPDATE transaksi_midtrans SET status = ? WHERE order_id = ?");
$updateTransStmt->bind_param("ss", $status_update, $order_id);
$updateTransStmt->execute();

// 2. Jika sukses, proses pembayaran
if ($status_update == 'success') {
    // Ambil data tagihan
    $tagihanStmt = $conn->prepare("SELECT nominal_tagihan, nominal_dibayar FROM tagihan WHERE id_tagihan = ?");
    $tagihanStmt->bind_param("i", $id_tagihan);
    $tagihanStmt->execute();
    $tagihanResult = $tagihanStmt->get_result();

    if ($tagihanResult && $tagihanResult->num_rows > 0) {
        $tagihan = $tagihanResult->fetch_assoc();
        $nominal_tagihan = $tagihan['nominal_tagihan'];
        $nominal_dibayar = $tagihan['nominal_dibayar'] + $gross_amount_db;

        // Batasi tidak melebihi tagihan
        if ($nominal_dibayar > $nominal_tagihan) {
            $nominal_dibayar = $nominal_tagihan;
        }

        // Tentukan status tagihan
        $status_tagihan = 'Cicil';
        if ($nominal_dibayar >= $nominal_tagihan) {
            $status_tagihan = 'Lunas';
        }

        // Update tagihan
        $updateTagihanStmt = $conn->prepare("UPDATE tagihan SET nominal_dibayar = ?, status = ? WHERE id_tagihan = ?");
        $updateTagihanStmt->bind_param("dsi", $nominal_dibayar, $status_tagihan, $id_tagihan);
        $updateTagihanStmt->execute();

        writeLog("Tagihan $id_tagihan diupdate: nominal_dibayar=$nominal_dibayar, status=$status_tagihan");

        // ==================== INSERT KE TABEL PEMBAYARAN (Bendahara) ====================
        // Kolom: id_tagihan, id_user (NULL karena otomatis), jumlah_bayar, tanggal_bayar, keterangan, metode_pembayaran
        $keterangan = "Pembayaran Online Midtrans (Order ID: $order_id)";
        // Metode pembayaran: jika enum belum punya 'Midtrans', gunakan default 'Manual/Tunai'
        // Jika ingin menampilkan 'Midtrans', ubah enum di database terlebih dahulu.
        $metode = 'Manual/Tunai'; // atau 'Midtrans' jika sudah ditambahkan ke enum

        $insertPembayaranStmt = $conn->prepare("
            INSERT INTO pembayaran 
            (id_tagihan, id_user, jumlah_bayar, tanggal_bayar, keterangan, metode_pembayaran)
            VALUES (?, NULL, ?, NOW(), ?, ?)
        ");
        $insertPembayaranStmt->bind_param("idss", $id_tagihan, $gross_amount_db, $keterangan, $metode);
        $insertPembayaranStmt->execute();

        if ($insertPembayaranStmt->affected_rows > 0) {
            writeLog("Pembayaran berhasil ditambahkan ke tabel pembayaran: id_tagihan=$id_tagihan, jumlah=$gross_amount_db");
        } else {
            writeLog("GAGAL insert ke tabel pembayaran: " . $conn->error);
        }
        // =================================================================================

    } else {
        writeLog("Tagihan dengan id $id_tagihan tidak ditemukan, update gagal");
    }
}

// Kirim respon 200 OK
http_response_code(200);
echo 'OK';
writeLog("Notifikasi diproses sukses untuk order_id $order_id");
