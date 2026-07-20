<?php
session_start();
include 'koneksi.php';
require_once 'config_midtrans.php';
require_once 'Midtrans/Snap.php';

// PERBAIKAN: Mengambil data secara fleksibel dari POST (saat submit form) atau GET (saat pertama buka halaman)
$id_tagihan = isset($_POST['id_tagihan']) ? mysqli_real_escape_string($conn, $_POST['id_tagihan']) : (isset($_GET['id_tagihan']) ? mysqli_real_escape_string($conn, $_GET['id_tagihan']) : '');
$nis = isset($_POST['nis']) ? mysqli_real_escape_string($conn, $_POST['nis']) : (isset($_GET['nis']) ? mysqli_real_escape_string($conn, $_GET['nis']) : '');

if (empty($id_tagihan) || empty($nis)) {
    die("Parameter tidak lengkap!");
}

// Ambil data tagihan
$query = "SELECT t.*, s.nama_siswa, s.no_wa_ortu, k.nama_kelas 
          FROM tagihan t
          JOIN siswa s ON t.id_siswa = s.id_siswa
          JOIN kelas k ON s.id_kelas = k.id_kelas
          WHERE t.id_tagihan = '$id_tagihan'";

$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    die("Data tagihan tidak ditemukan!");
}
$data = mysqli_fetch_assoc($result);

$sisa = $data['nominal_tagihan'] - $data['nominal_dibayar'];
if ($sisa <= 0) {
    die("Tagihan sudah lunas!");
}

// Inisialisasi variabel
$error = '';
$jumlah_bayar = 0;
$show_payment = false;

// Proses form jika ada POST dari input nominal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jumlah'])) {
    // Hapus titik dan karakter non-digit
    $jumlah_input = intval(preg_replace('/[^0-9]/', '', $_POST['jumlah']));

    if ($jumlah_input <= 0) {
        $error = "Jumlah harus lebih dari 0.";
    } elseif ($jumlah_input > $sisa) {
        $error = "Jumlah tidak boleh melebihi sisa tagihan (Rp " . number_format($sisa, 0, ',', '.') . ").";
    } else {
        $jumlah_bayar = $jumlah_input;
        $show_payment = true;
    }
}

// Jika valid, proses pembayaran ke Midtrans
if ($show_payment && $jumlah_bayar > 0) {
    // Buat tabel transaksi jika belum ada
    $create_table = "CREATE TABLE IF NOT EXISTS `transaksi_midtrans` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` varchar(100) NOT NULL,
        `id_tagihan` int(11) NOT NULL,
        `nis` varchar(50) NOT NULL,
        `gross_amount` int(11) NOT NULL,
        `status` enum('pending','success','expired','cancel') DEFAULT 'pending',
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $create_table);

    $order_id = "SPP-" . $data['id_tagihan'] . "-" . time();
    $gross_amount = $jumlah_bayar;

    $params = [
        'transaction_details' => [
            'order_id' => $order_id,
            'gross_amount' => $gross_amount,
        ],
        'customer_details' => [
            'first_name' => $data['nama_siswa'],
            'email' => 'siswa@sd-mujahidin.sch.id',
            'phone' => $data['no_wa_ortu'] ?? '08123456789',
        ],
        'item_details' => [
            [
                'id' => $data['id_tagihan'],
                'price' => $gross_amount,
                'quantity' => 1,
                'name' => "SPP " . $data['bulan'] . " " . $data['tahun']
            ]
        ],
    ];

    try {
        $snapToken = \Midtrans\Snap::getSnapToken($params);

        // Simpan log transaksi awal ke database lokal dengan ID dinamis aman
        $query_save = "INSERT INTO transaksi_midtrans (order_id, id_tagihan, nis, gross_amount, status, created_at) 
                       VALUES ('$order_id', '$id_tagihan', '$nis', '$gross_amount', 'pending', NOW())";

        if (!mysqli_query($conn, $query_save)) {
            die("Gagal menyimpan transaksi awal ke database: " . mysqli_error($conn));
        }

        // Tampilkan halaman pembayaran dengan widget Snap
?>
        <!DOCTYPE html>
        <html lang="id">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Bayar SPP - SD Mujahidin</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: 'Segoe UI', sans-serif;
                    background: linear-gradient(135deg, #064e3b 0%, #0a5c45 100%);
                    min-height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    padding: 20px;
                }

                .payment-card {
                    background: white;
                    border-radius: 24px;
                    padding: 35px 30px;
                    max-width: 420px;
                    width: 100%;
                    text-align: center;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
                }

                .payment-card .logo {
                    width: 60px;
                    height: 60px;
                    background: linear-gradient(135deg, #10b981, #059669);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 15px;
                }

                .payment-card .logo i {
                    font-size: 28px;
                    color: white;
                }

                .payment-card h2 {
                    color: #064e3b;
                    margin-bottom: 5px;
                    font-size: 22px;
                }

                .payment-card p {
                    color: #64748b;
                    font-size: 13px;
                    margin-bottom: 10px;
                }

                .amount {
                    font-size: 32px;
                    font-weight: 800;
                    color: #10b981;
                    margin: 20px 0;
                }

                .info-payment {
                    background: #f8fafc;
                    padding: 12px;
                    border-radius: 12px;
                    margin: 15px 0;
                }

                .info-payment p {
                    margin: 5px 0;
                    font-size: 13px;
                    color: #1e293b;
                }

                #payment-button {
                    background: linear-gradient(135deg, #10b981, #059669);
                    color: white;
                    border: none;
                    padding: 14px 20px;
                    border-radius: 12px;
                    font-size: 16px;
                    font-weight: 700;
                    cursor: pointer;
                    width: 100%;
                    margin-top: 10px;
                    transition: all 0.3s;
                }

                #payment-button:hover {
                    transform: scale(1.02);
                    box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
                }

                .btn-back {
                    display: inline-block;
                    margin-top: 15px;
                    color: #10b981;
                    text-decoration: none;
                    font-size: 13px;
                }
            </style>
            <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="<?php echo \Midtrans\Config::$clientKey; ?>"></script>
        </head>

        <body>
            <div class="payment-card">
                <div class="logo"><i class="fas fa-graduation-cap"></i></div>
                <h2>SD Mujahidin Pontianak</h2>
                <p>Pembayaran SPP Online</p>
                <div class="amount">Rp <?= number_format($gross_amount, 0, ',', '.') ?></div>
                <div class="info-payment">
                    <p><strong><?= htmlspecialchars($data['nama_siswa']) ?></strong></p>
                    <p><?= $data['bulan'] ?> <?= $data['tahun'] ?> | Kelas <?= $data['nama_kelas'] ?></p>
                </div>
                <button id="payment-button"><i class="fas fa-credit-card"></i> Bayar Sekarang</button>
                <br>
                <a href="#" onclick="window.close(); window.opener.location.reload(); return false;" class="btn-back">← Kembali ke Riwayat</a>
            </div>
            <script>
                document.getElementById('payment-button').onclick = function() {
                    window.snap.pay('<?= $snapToken ?>', {
                        onSuccess: function(result) {
                            window.location.href = 'payment_success.php?order_id=<?= $order_id ?>&nis=<?= urlencode($nis) ?>';
                        },
                        onPending: function(result) {
                            window.location.href = 'payment_pending.php?order_id=<?= $order_id ?>&nis=<?= urlencode($nis) ?>';
                        },
                        onError: function(result) {
                            alert('Pembayaran gagal! Silakan coba lagi.');
                            console.log(result);
                        }
                    });
                };
            </script>
        </body>

        </html>
<?php
        exit;
    } catch (Exception $e) {
        die("Error Midtrans: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masukkan Jumlah Bayar - SD Mujahidin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #064e3b 0%, #0a5c45 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .payment-card {
            background: white;
            border-radius: 24px;
            padding: 35px 30px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
        }

        .payment-card .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .payment-card .logo i {
            font-size: 28px;
            color: white;
        }

        .payment-card h2 {
            color: #064e3b;
            margin-bottom: 5px;
            font-size: 22px;
        }

        .payment-card p {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .info-payment {
            background: #f8fafc;
            padding: 12px;
            border-radius: 12px;
            margin: 15px 0;
        }

        .info-payment p {
            margin: 5px 0;
            font-size: 13px;
            color: #1e293b;
        }

        .form-group {
            margin: 15px 0;
            text-align: left;
        }

        .form-group label {
            font-weight: 600;
            color: #064e3b;
            display: block;
            margin-bottom: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: border 0.3s;
            text-align: right;
        }

        .form-group input:focus {
            border-color: #10b981;
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-back {
            display: inline-block;
            margin-top: 15px;
            color: #10b981;
            text-decoration: none;
            font-size: 13px;
        }

        .error-box {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .sisa-info {
            font-weight: bold;
            color: #064e3b;
        }

        .small-note {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="payment-card">
        <div class="logo"><i class="fas fa-graduation-cap"></i></div>
        <h2>SD Mujahidin Pontianak</h2>
        <p>Masukkan jumlah yang akan dibayar</p>

        <div class="info-payment">
            <p><strong><?= htmlspecialchars($data['nama_siswa']) ?></strong></p>
            <p><?= $data['bulan'] ?> <?= $data['tahun'] ?> | Kelas <?= $data['nama_kelas'] ?></p>
            <p>Sisa tagihan: <span class="sisa-info">Rp <?= number_format($sisa, 0, ',', '.') ?></span></p>
        </div>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="proses_payment.php" id="formBayar">
            <input type="hidden" name="id_tagihan" value="<?= htmlspecialchars($id_tagihan) ?>">
            <input type="hidden" name="nis" value="<?= htmlspecialchars($nis) ?>">

            <div class="form-group">
                <label for="jumlah">Jumlah (Rp)</label>
                <input type="text" id="jumlah" name="jumlah" placeholder="Masukkan nominal" required autofocus>
                <div class="small-note">Maksimal Rp <?= number_format($sisa, 0, ',', '.') ?></div>
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-arrow-right"></i> Lanjutkan ke Pembayaran</button>
        </form>
        <br>
        <a href="cek_spp.php?nis=<?= urlencode($nis) ?>" class="btn-back">← Kembali ke Riwayat</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('jumlah');
            const form = document.getElementById('formBayar');

            function formatRupiah(angka) {
                let number_string = angka.replace(/[^,\d]/g, '').toString(),
                    split = number_string.split(','),
                    sisa = split[0].length % 3,
                    rupiah = split[0].substr(0, sisa),
                    ribuan = split[0].substr(sisa).match(/\d{3}/gi);

                if (ribuan) {
                    let separator = sisa ? '.' : '';
                    rupiah += separator + ribuan.join('.');
                }
                return split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            }

            input.addEventListener('keyup', function(e) {
                input.value = formatRupiah(this.value);
            });

            form.addEventListener('submit', function(e) {
                let cleanValue = input.value.replace(/\./g, '');
                if (cleanValue === '' || parseInt(cleanValue) <= 0) {
                    e.preventDefault();
                    alert('Masukkan nominal yang valid!');
                    return;
                }
                input.value = cleanValue;
            });
        });
    </script>
</body>

</html>