<?php
include '../koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_siswa = isset($_GET['id']) ? mysqli_real_escape_string($koneksi, $_GET['id']) : '';

// 1. Ambil informasi biodata lengkap siswa alumni
$query_siswa = mysqli_query($koneksi, "SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas WHERE s.id_siswa = '$id_siswa'");
$data_siswa = mysqli_fetch_assoc($query_siswa);

if (!$data_siswa) {
    echo "<script>alert('Data siswa tidak ditemukan!'); window.location.href='data_alumni.php';</script>";
    exit();
}

// 2. Ambil data tagihan asli yang tersimpan di database
$tagihan_ada = [];
$total_tagihan_riil = 0;
$total_terbayar = 0;
$jumlah_bulan_aktif = 0;

$query_tagihan = mysqli_query($koneksi, "SELECT * FROM tagihan WHERE id_siswa = '$id_siswa'");
while ($row = mysqli_fetch_assoc($query_tagihan)) {
    $key = strtolower($row['bulan']) . '-' . $row['tahun'];
    $tagihan_ada[$key] = $row;

    $total_tagihan_riil += $row['nominal_tagihan'];
    $total_terbayar += $row['nominal_dibayar'];
    $jumlah_bulan_aktif++;
}

// 3. Logika Fleksibel: Mengunci hitungan hanya untuk 12 bulan (1 tahun kelas 6) jika data lama tidak ada
if ($total_tagihan_riil == 0 || $jumlah_bulan_aktif <= 12) {
    $total_tagihan_tampil = 12 * 275000; // Rp 3.300.000
    $label_bulan = "12 BULAN / 1 TAHUN KELAS 6";
} else {
    $total_tagihan_tampil = $total_tagihan_riil;
    $label_bulan = $jumlah_bulan_aktif . " BULAN";
}

$sisa_tunggakan = $total_tagihan_tampil - $total_terbayar;

// 4. DAFTAR URUTAN BULAN AJARAN KELAS 6 (Juli 2025 s/d Juni 2026)
$list_bulan = ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'];
$tahun_mulai = 2025;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Riwayat Pembayaran Akhir - <?= $data_siswa['nama_siswa']; ?></title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body style="background-color: var(--light, #f8fafc); padding: 20px;">

    <div class="container mt-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="data_kelompok_kelas.php" class="btn btn-light font-weight-bold shadow-sm" style="border-radius: var(--radius, 12px); border: 1px solid var(--border, #e2e8f0); color: var(--dark, #1e293b);">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar Alumni
            </a>
            <button onclick="window.print();" class="btn text-white font-weight-bold shadow-sm" style="background-color: var(--primary, #10b981); border-radius: var(--radius, 12px);">
                <i class="fas fa-print mr-2"></i> Cetak Semua Bulan
            </button>
        </div>

        <div class="card shadow border-0 mb-4" style="border-radius: var(--radius-md, 16px);">
            <div class="card-header text-white font-weight-bold py-3" style="background: var(--primary-gradient, linear-gradient(135deg, #064e3b 0%, #0a5c45 100%)); border: none;">
                <h5 class="mb-0"><i class="fas fa-id-card mr-2"></i> Rekap Informasi Pembayaran SPP Bulanan</h5>
            </div>
            <div class="card-body p-4 text-dark" style="background-color: var(--card, #ffffff);">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td width="35%"><strong>Nama Lengkap</strong></td>
                                <td>: <?= $data_siswa['nama_siswa']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>NIS / Nomor Induk</strong></td>
                                <td>: <?= $data_siswa['nis']; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td width="35%"><strong>Jenis Kelamin</strong></td>
                                <td>: <?= ($data_siswa['jk'] == 'L') ? 'Laki-laki' : 'Perempuan'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status Akhir</strong></td>
                                <td>: <span class="badge text-white px-2 py-1" style="background-color: var(--success, #10b981); border-radius: 6px;"><?= $data_siswa['nama_kelas']; ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center p-3" style="border-radius: var(--radius, 12px); background-color: var(--card, #ffffff);">
                    <small class="text-muted font-weight-bold">TOTAL KEWAJIBAN (<?= $label_bulan; ?>)</small>
                    <h4 class="font-weight-bold mt-1 text-dark">Rp <?= number_format($total_tagihan_tampil, 0, ',', '.'); ?></h4>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center p-3" style="border-radius: var(--radius, 12px); background-color: var(--card, #ffffff); border-left: 4px solid var(--success, #10b981);">
                    <small class="text-muted font-weight-bold text-success">TOTAL TELAH DIBAYAR</small>
                    <h4 class="font-weight-bold mt-1 text-success">Rp <?= number_format($total_terbayar, 0, ',', '.'); ?></h4>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center p-3" style="border-radius: var(--radius, 12px); background-color: var(--card, #ffffff); border-left: 4px solid <?= ($sisa_tunggakan > 0) ? 'var(--danger, #e14444)' : 'var(--success, #10b981)'; ?>;">
                    <small class="text-muted font-weight-bold">SISA TUNGGAKAN AKHIR</small>
                    <h4 class="font-weight-bold mt-1 <?= ($sisa_tunggakan > 0) ? 'text-danger' : 'text-success'; ?>">
                        Rp <?= number_format($sisa_tunggakan, 0, ',', '.'); ?>
                    </h4>
                </div>
            </div>
        </div>

        <div class="card shadow border-0" style="border-radius: var(--radius-md, 16px); overflow: hidden;">
            <div class="card-header bg-light py-3 border-bottom" style="border-color: var(--border, #e2e8f0);">
                <h6 class="mb-0 font-weight-bold text-dark"><i class="fas fa-calendar-alt mr-2"></i> Rincian Histori Semua Daftar Bulan Pembayaran</h6>
            </div>
            <div class="card-body p-0" style="background-color: var(--card, #ffffff);">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="border-color: var(--border, #e2e8f0);">
                        <thead style="background-color: var(--light, #f8fafc); color: var(--dark, #1e293b);">
                            <tr class="text-center">
                                <th width="8%">No</th>
                                <th class="text-left">Bulan & Tahun Tagihan</th>
                                <th>Besar Tagihan SPP</th>
                                <th>Total Dana Masuk</th>
                                <th>Status Bayar</th>
                            </tr>
                        </thead>
                        <tbody style="color: var(--dark, #1e293b);">
                            <?php
                            $no = 1;

                            foreach ($list_bulan as $bulan_nama) {
                                // Penyesuaian tahun jika melompat ke bulan Januari - Juni 2026
                                $tahun_kalender = in_array($bulan_nama, ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni']) ? ($tahun_mulai + 1) : $tahun_mulai;

                                $search_key = strtolower($bulan_nama) . '-' . $tahun_kalender;

                                if (array_key_exists($search_key, $tagihan_ada)) {
                                    $nominal_tagihan = $tagihan_ada[$search_key]['nominal_tagihan'];
                                    $nominal_dibayar = $tagihan_ada[$search_key]['nominal_dibayar'];
                                    $status_bayar = $tagihan_ada[$search_key]['status'];
                                } else {
                                    $nominal_tagihan = 275000;
                                    $nominal_dibayar = 0;
                                    $status_bayar = 'Belum Bayar';
                                }

                                if ($status_bayar == 'Lunas') {
                                    $badge_color = 'var(--success, #10b981)';
                                } elseif ($status_bayar == 'Cicil') {
                                    $badge_color = 'var(--warning, #f59e0b)';
                                    $status_bayar = 'Dicicil';
                                } else {
                                    $badge_color = 'var(--danger, #e14444)';
                                    $status_bayar = 'Belum Bayar';
                                }
                            ?>
                                <tr class="text-center" style="border-bottom: 1px solid var(--border, #e2e8f0);">
                                    <td><?= $no; ?></td>
                                    <td class="text-left font-weight-bold"><?= $bulan_nama . " " . $tahun_kalender; ?></td>
                                    <td>Rp <?= number_format($nominal_tagihan, 0, ',', '.'); ?></td>
                                    <td class="text-success font-weight-bold">Rp <?= number_format($nominal_dibayar, 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="badge text-white px-3 py-1 font-weight-bold" style="background-color: <?= $badge_color; ?>; border-radius: 8px; min-width: 95px;">
                                            <?= $status_bayar; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php
                                $no++;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            body {
                background-color: #ffffff !important;
                padding: 0;
            }

            .btn,
            .alert {
                display: none !important;
            }

            .card {
                shadow: none !important;
                border: 1px solid #ccc !important;
            }
        }

        .table-hover tbody tr:hover {
            background-color: var(--gray-light, #f1f5f9);
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>