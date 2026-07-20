<?php
session_start();
include '../koneksi.php';

// Fungsi pengecekan login bendahara (Pastikan fungsi ini ada di koneksi.php)
if (!isset($_SESSION['level']) || $_SESSION['level'] != 'bendahara') {
    header("Location: ../index.php");
    exit();
}

// 1. Tangkap ID Pembayaran dengan aman
$id_pembayaran = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_pembayaran <= 0) {
    die("❌ ID pembayaran tidak valid!");
}

// 2. Query yang sudah disesuaikan dengan nama kolom tabel 'pembayaran'
// Catatan: Pastikan kolom 'tanggal_bayar' di database sesuai, atau gunakan 'created_at'
$sql = "SELECT p.*, 
               s.nama_siswa, 
               s.no_wa_ortu, 
               k.nama_kelas, 
               u.nama_lengkap AS bendahara, 
               t.bulan, 
               t.tahun, 
               t.status AS status_tagihan 
        FROM pembayaran p
        JOIN tagihan t ON p.id_tagihan = t.id_tagihan
        JOIN siswa s ON t.id_siswa = s.id_siswa
        LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
        JOIN user u ON p.id_user = u.id_user
        WHERE p.id_pembayaran = '$id_pembayaran'";

$query = mysqli_query($conn, $sql);

// 3. Error Handling yang lebih detail
if (!$query) {
    die("❌ Error pada Query: " . mysqli_error($conn));
}

if (mysqli_num_rows($query) == 0) {
    die("❌ Data pembayaran dengan ID <strong>$id_pembayaran</strong> tidak ditemukan di database!");
}

$data = mysqli_fetch_assoc($query);

// 4. Fallback data agar tidak error jika ada kolom kosong
$nominal    = $data['jumlah_bayar'] ?? 0;
$status     = 'LUNAS'; // Karena ini tabel pembayaran, diasumsikan sudah lunas
$bulan      = $data['bulan'] ?? '-';
$tahun      = $data['tahun'] ?? '';
$tgl_bayar  = $data['tanggal_bayar'] ?? $data['created_at']; // Pakai created_at jika tanggal_bayar null

$no_wa_display = $data['no_wa_ortu'] ? substr($data['no_wa_ortu'], 0, 4) . '****' . substr($data['no_wa_ortu'], -3) : '-';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Struk SPP - <?= htmlspecialchars($data['nama_siswa']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #064e3b 0%, #0a5c45 100%);
            min-height: 100vh;
            padding: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .struk-container {
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
        }

        .struk-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .struk-header {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            text-align: center;
            padding: 25px 20px;
        }

        .struk-header h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .struk-header h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .struk-header p {
            font-size: 11px;
            opacity: 0.9;
            margin-top: 5px;
        }

        .status-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }

        .struk-body {
            padding: 25px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        .info-value {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            text-align: right;
        }

        .nominal-box {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border-radius: 16px;
            padding: 15px;
            text-align: center;
            margin: 20px 0;
            border: 1px solid #bbf7d0;
        }

        .nominal-box .label {
            font-size: 11px;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .nominal-box .amount {
            font-size: 28px;
            font-weight: 800;
            color: #059669;
        }

        .struk-footer {
            background: #f8fafc;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .struk-footer p {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 5px;
        }

        .signature {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #cbd5e1;
        }

        .signature p {
            font-size: 10px;
        }

        .btn-print {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            max-width: 420px;
            margin: 15px auto 0;
            padding: 12px 20px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            text-decoration: none;
            font-weight: 600;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-print:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .btn-print {
                display: none;
            }

            .struk-card {
                box-shadow: none;
                border-radius: 0;
            }

            .struk-header {
                background: #1e3a8a;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .nominal-box {
                background: #f0fdf4;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 15px;
            }

            .struk-body {
                padding: 20px;
            }

            .nominal-box .amount {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

    <div class="struk-container">
        <div class="struk-card">
            <div class="struk-header">
                <i class="fas fa-school" style="font-size: 32px; margin-bottom: 8px;"></i>
                <h2>SD MUJAHIDIN</h2>
                <h3>BUKTI PEMBAYARAN SPP</h3>
                <p>Jl. Jenderal Ahmad Yani, Pontianak</p>
                <div class="status-badge">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($status) ?>
                </div>
            </div>

            <div class="struk-body">
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-hashtag"></i> No. Transaksi</span>
                    <span class="info-value">#TRX-<?= str_pad($data['id_pembayaran'], 6, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-calendar"></i> Tanggal</span>
                    <span class="info-value"><?= date('d/m/Y H:i', strtotime($tgl_bayar)) ?></span>
                </div>

                <div style="margin: 15px 0; border-top: 1px dashed #e2e8f0;"></div>

                <div class="info-row">
                    <span class="info-label"><i class="fas fa-user-graduate"></i> Nama Siswa</span>
                    <span class="info-value"><?= htmlspecialchars($data['nama_siswa']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-chalkboard"></i> Kelas</span>
                    <span class="info-value"><?= htmlspecialchars($data['nama_kelas'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fab fa-whatsapp"></i> No. WhatsApp</span>
                    <span class="info-value"><?= $no_wa_display ?></span>
                </div>

                <div style="margin: 15px 0; border-top: 1px dashed #e2e8f0;"></div>

                <div class="info-row">
                    <span class="info-label"><i class="fas fa-calendar-alt"></i> Pembayaran</span>
                    <span class="info-value">SPP <?= htmlspecialchars($bulan) . ' ' . htmlspecialchars($tahun) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-money-bill"></i> Metode</span>
                    <span class="info-value"><?= htmlspecialchars($data['metode_pembayaran'] ?? 'Cash') ?></span>
                </div>

                <div class="nominal-box">
                    <div class="label">TOTAL PEMBAYARAN</div>
                    <div class="amount">Rp <?= number_format($nominal, 0, ',', '.') ?></div>
                </div>

                <div class="info-row">
                    <span class="info-label"><i class="fas fa-user-check"></i> Petugas</span>
                    <span class="info-value"><?= htmlspecialchars($data['bendahara'] ?? '-') ?></span>
                </div>
                <?php if (!empty($data['keterangan'])): ?>
                    <div class="info-row">
                        <span class="info-label"><i class="fas fa-sticky-note"></i> Keterangan</span>
                        <span class="info-value" style="font-weight:400;font-size:12px;"><?= nl2br(htmlspecialchars($data['keterangan'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="struk-footer">
                <p><i class="fas fa-check-circle" style="color: #10b981;"></i> Terima kasih atas pembayaran Anda</p>
                <p style="font-size: 10px;">Simpan struk ini sebagai bukti pembayaran yang sah</p>
                <div class="signature">
                    <p>Hormat Kami,</p>
                    <p style="margin-top: 20px; font-weight: 600;">SD MUJAHIDIN</p>
                    <p style="font-size: 9px;">*Struk ini dicetak oleh sistem</p>
                </div>
            </div>
        </div>

        <button class="btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Cetak / Simpan PDF
        </button>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

</body>

</html>