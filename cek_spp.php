<?php
include 'koneksi.php';

$db = $conn;
$nis = isset($_GET['nis']) ? trim(mysqli_real_escape_string($db, $_GET['nis'])) : '';

// ===== AMBIL FILTER BULAN & TAHUN =====
$bulan_filter = isset($_GET['bulan_filter']) ? trim(mysqli_real_escape_string($db, $_GET['bulan_filter'])) : '';
$tahun_filter = isset($_GET['tahun_filter']) ? (int)$_GET['tahun_filter'] : 0;

// ===== FUNGSI PEMBERSIH NOMOR WA =====
function bersihkanNomorWa($nomor)
{
    $nomor = preg_replace('/[^0-9]/', '', $nomor);
    if (substr($nomor, 0, 1) === '0') {
        $nomor = '62' . substr($nomor, 1);
    }
    return $nomor;
}

// Form pencarian jika NIS kosong
if (empty($nis)) {
?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
        <title>Cek SPP - SD Mujahidin</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    </head>

    <body class="landing-page">
        <div class="search-card">
            <div class="search-icon"><i class="fas fa-search"></i></div>
            <h2>Cek Status SPP</h2>
            <p>Masukkan ID atau Nama Siswa untuk melihat riwayat pembayaran</p>
            <form method="GET">
                <div class="form-group">
                    <input type="text" name="nis" class="form-control" placeholder="Masukkan ID / Nama Siswa" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-arrow-right"></i> Lihat Riwayat</button>
            </form>
            <a href="index.php" class="back-link">← Kembali ke Beranda</a>
        </div>
    </body>

    </html>
<?php
    exit();
}

// ========== AMBIL DATA SISWA + NOMINAL SPP + id_spp DARI KELAS ==========
$query_siswa = mysqli_query($db, "
    SELECT siswa.*, kelas.nama_kelas, kelas.id_spp as id_spp_kelas, sk.nominal as nominal_spp
    FROM siswa 
    JOIN kelas ON siswa.id_kelas = kelas.id_kelas
    LEFT JOIN spp_kategori sk ON kelas.id_spp = sk.id_spp
    WHERE siswa.nis = '$nis'
");

if (!$query_siswa) {
    die("Query Error: " . mysqli_error($db));
}

$siswa = mysqli_fetch_assoc($query_siswa);

if ($siswa) {
    $is_alumni = (strpos($siswa['nama_kelas'], 'Alumni') !== false);
    $nominal_spp = isset($siswa['nominal_spp']) ? (int)$siswa['nominal_spp'] : 0;
    $id_spp_kelas = isset($siswa['id_spp_kelas']) ? (int)$siswa['id_spp_kelas'] : 'NULL';
} else {
    $nominal_spp = 0;
    $is_alumni = false;
    $id_spp_kelas = 'NULL';
}

$id_siswa = $siswa['id_siswa'] ?? 0;

// ========== AMBIL SEMUA TAGIHAN SISWA ==========
$query_tagihan = mysqli_query($db, "
    SELECT * FROM tagihan 
    WHERE id_siswa = '$id_siswa' 
    ORDER BY tahun DESC, 
    CASE 
        WHEN bulan = 'Januari' THEN 1 WHEN bulan = 'Februari' THEN 2 WHEN bulan = 'Maret' THEN 3
        WHEN bulan = 'April' THEN 4 WHEN bulan = 'Mei' THEN 5 WHEN bulan = 'Juni' THEN 6
        WHEN bulan = 'Juli' THEN 7 WHEN bulan = 'Agustus' THEN 8 WHEN bulan = 'September' THEN 9
        WHEN bulan = 'Oktober' THEN 10 WHEN bulan = 'November' THEN 11 WHEN bulan = 'Desember' THEN 12
    END DESC
");

$tagihan_by_key = [];
while ($t = mysqli_fetch_assoc($query_tagihan)) {
    $key = $t['bulan'] . ' ' . $t['tahun'];
    $tagihan_by_key[$key] = $t;
}

// ========== DAFTAR BULAN INDONESIA ==========
$bulan_indonesia = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember'
];

// ========== TENTUKAN DAFTAR BULAN YANG AKAN DITAMPILKAN ==========
if (!empty($bulan_filter) && $tahun_filter > 0) {
    // Jika filter dipilih, tampilkan hanya bulan yang dipilih
    $daftar_bulan = [
        ['bulan' => $bulan_filter, 'tahun' => $tahun_filter]
    ];
    $mode_filter = true;
} else {
    // Default: tampilkan dari Juli sampai bulan sekarang
    $bulan_sekarang = (int)date('m');
    $tahun_sekarang = (int)date('Y');
    $tahun_ajaran = ($bulan_sekarang >= 7) ? $tahun_sekarang : $tahun_sekarang - 1;
    $bulan_mulai = 7;

    $daftar_bulan = [];
    $tahun = $tahun_ajaran;
    $bulan = $bulan_mulai;
    while (true) {
        if ($tahun > $tahun_sekarang || ($tahun == $tahun_sekarang && $bulan > $bulan_sekarang)) {
            break;
        }
        $daftar_bulan[] = [
            'bulan' => $bulan_indonesia[$bulan - 1],
            'tahun' => $tahun
        ];
        $bulan++;
        if ($bulan > 12) {
            $bulan = 1;
            $tahun++;
        }
    }
    $daftar_bulan = array_reverse($daftar_bulan);
    $mode_filter = false;
}

// ========== FUNGSI UNTUK AUTO-GENERATE TAGIHAN (jika belum ada) ==========
function generateTagihan($db, $id_siswa, $bulan, $tahun, $nominal_spp, $id_spp_kelas)
{
    $cek = mysqli_query($db, "
        SELECT id_tagihan FROM tagihan
        WHERE id_siswa='$id_siswa' AND bulan='$bulan' AND tahun='$tahun'
        LIMIT 1
    ");
    if (mysqli_num_rows($cek) > 0) {
        $row = mysqli_fetch_assoc($cek);
        return $row['id_tagihan'];
    } else {
        $id_spp_value = ($id_spp_kelas !== 'NULL') ? $id_spp_kelas : 'NULL';
        mysqli_query($db, "
            INSERT INTO tagihan
(id_siswa, bulan, tahun, nominal_tagihan, nominal_dibayar, status)
VALUES
('$id_siswa', '$bulan', '$tahun', '$nominal_spp', 0, 'Belum Bayar')
        ");
        return mysqli_insert_id($db);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Riwayat SPP - <?= htmlspecialchars($siswa['nama_siswa'] ?? 'SD Mujahidin') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 12px;
        }

        .riwayat-container {
            padding-bottom: 40px;
        }

        .total-row {
            font-weight: 700;
            background: #f8fafc;
            border-top: 2px solid #2563eb;
        }

        .total-row td {
            padding: 12px 8px;
        }

        .alumni-notice {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }

        .alumni-notice i {
            color: #f59e0b;
            font-size: 24px;
            display: block;
            margin-bottom: 8px;
        }

        .text-muted {
            color: #6b7280;
            font-size: 13px;
        }

        .filter-box-spp {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 12px;
            margin: 20px 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
            border: 1px solid #e2e8f0;
        }

        .filter-box-spp label {
            font-weight: 600;
            font-size: 14px;
            color: #1e293b;
            margin-right: 5px;
        }

        .filter-box-spp select,
        .filter-box-spp input {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: white;
            font-size: 14px;
        }

        .filter-box-spp .btn-filter {
            background: #2563eb;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .filter-box-spp .btn-filter:hover {
            background: #1d4ed8;
        }

        .filter-box-spp .btn-reset {
            background: #e2e8f0;
            color: #1e293b;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
        }

        .filter-box-spp .btn-reset:hover {
            background: #cbd5e1;
        }

        .filter-info {
            font-size: 14px;
            color: #475569;
            margin-left: auto;
        }

        .filter-info strong {
            color: #1e293b;
        }

        @media (max-width: 640px) {
            .filter-box-spp {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-box-spp .filter-info {
                margin-left: 0;
                text-align: center;
            }
        }
    </style>
</head>

<body class="riwayat-page">
    <div class="riwayat-container">
        <?php if ($siswa): ?>
            <!-- Header -->
            <div class="riwayat-header">
                <div class="header-icon"><i class="fas fa-graduation-cap"></i></div>
                <h1>Riwayat Pembayaran SPP</h1>
                <p>SD Mujahidin Pontianak</p>
            </div>

            <!-- Info Siswa -->
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label"><i class="fas fa-user"></i> Nama Siswa</div>
                    <div class="info-value"><?= htmlspecialchars($siswa['nama_siswa']) ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label"><i class="fas fa-chalkboard"></i> Kelas</div>
                    <div class="info-value"><?= htmlspecialchars($siswa['nama_kelas']) ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label"><i class="fas fa-money-bill"></i> SPP / Bulan</div>
                    <div class="info-value">
                        <?php if ($is_alumni): ?>
                            <span class="text-muted">-</span>
                        <?php else: ?>
                            Rp <?= number_format($nominal_spp, 0, ',', '.') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-box">
                    <div class="info-label"><i class="fas fa-id-card"></i> ID Siswa</div>
                    <div class="info-value">#<?= $siswa['id_siswa'] ?></div>
                </div>
            </div>

            <?php if ($is_alumni): ?>
                <div class="alumni-notice">
                    <i class="fas fa-user-graduate"></i>
                    <strong>Selamat! <?= htmlspecialchars($siswa['nama_siswa']) ?> telah lulus.</strong>
                    <p style="margin-top:5px; color:#475569;">Sudah tidak ada kewajiban SPP lagi. Terima kasih telah menjadi bagian dari SD Mujahidin.</p>
                </div>
            <?php else: ?>

                <!-- ===== FILTER BULAN & TAHUN ===== -->
                <div class="filter-box-spp">
                    <form method="GET" style="display: contents;">
                        <input type="hidden" name="nis" value="<?= htmlspecialchars($nis) ?>">
                        <label><i class="fas fa-calendar-alt"></i> Bulan:</label>
                        <select name="bulan_filter">
                            <option value="">-- Pilih Bulan --</option>
                            <?php foreach ($bulan_indonesia as $b): ?>
                                <option value="<?= $b ?>" <?= ($bulan_filter == $b) ? 'selected' : '' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Tahun:</label>
                        <select name="tahun_filter">
                            <option value="">-- Pilih Tahun --</option>
                            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= ($tahun_filter == $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Tampilkan</button>
                        <?php if ($mode_filter): ?>
                            <a href="?nis=<?= urlencode($nis) ?>" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
                        <?php endif; ?>
                    </form>
                    <span class="filter-info">
                        <?php if ($mode_filter): ?>
                            Menampilkan <strong><?= $bulan_filter ?> <?= $tahun_filter ?></strong>
                        <?php else: ?>
                            Menampilkan <strong>semua bulan</strong> dari <?= $daftar_bulan[count($daftar_bulan) - 1]['bulan'] ?? '' ?> <?= $daftar_bulan[count($daftar_bulan) - 1]['tahun'] ?? '' ?> - sekarang
                        <?php endif; ?>
                    </span>
                </div>

                <!-- Tabel Tagihan -->
                <div class="table-responsive">
                    <?php if (count($daftar_bulan) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Periode</th>
                                    <th>Tagihan</th>
                                    <th>Dibayar</th>
                                    <th>Sisa</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_tagihan = 0;
                                $total_dibayar = 0;
                                foreach ($daftar_bulan as $item):
                                    $bulan = $item['bulan'];
                                    $tahun = $item['tahun'];
                                    $key = $bulan . ' ' . $tahun;
                                    $tagihan = isset($tagihan_by_key[$key]) ? $tagihan_by_key[$key] : null;

                                    if ($tagihan) {
                                        $nominal_tagihan = (int)$nominal_spp;
                                        if ($tagihan['nominal_dibayar'] > $nominal_tagihan) {
                                            $nominal_tagihan = $tagihan['nominal_dibayar'];
                                        }
                                        $nominal_dibayar = (int)$tagihan['nominal_dibayar'];
                                        $sisa = max(0, $nominal_tagihan - $nominal_dibayar);

                                        if ($nominal_dibayar == 0) $status = 'Belum Bayar';
                                        elseif ($nominal_dibayar >= $nominal_tagihan) $status = 'Lunas';
                                        else $status = 'Cicil';
                                        $id_tagihan = $tagihan['id_tagihan'];

                                        if ($tagihan['nominal_tagihan'] != $nominal_tagihan) {
                                            mysqli_query($db, "UPDATE tagihan SET nominal_tagihan='$nominal_tagihan' WHERE id_tagihan='$id_tagihan'");
                                        }
                                    } else {
                                        // Auto-generate tagihan
                                        $nominal_tagihan = (int)$nominal_spp;
                                        $nominal_dibayar = 0;
                                        $status = 'Belum Bayar';
                                        $sisa = $nominal_tagihan;
                                        $id_tagihan = generateTagihan($db, $id_siswa, $bulan, $tahun, $nominal_spp, $id_spp_kelas);
                                    }

                                    $total_tagihan += $nominal_tagihan;
                                    $total_dibayar += $nominal_dibayar;

                                    $st = strtolower($status);
                                    if ($st == 'lunas') {
                                        $class = 'badge-success';
                                        $icon = '✅';
                                    } elseif ($st == 'cicil') {
                                        $class = 'badge-warning';
                                        $icon = '🔄';
                                    } else {
                                        $class = 'badge-danger';
                                        $icon = '❌';
                                    }
                                ?>
                                    <tr>
                                        <td><strong><?= $bulan ?> <?= $tahun ?></strong></td>
                                        <td>Rp <?= number_format($nominal_tagihan, 0, ',', '.') ?></td>
                                        <td>Rp <?= number_format($nominal_dibayar, 0, ',', '.') ?></td>
                                        <td class="fw-bold <?= $sisa > 0 ? 'text-danger' : 'text-success' ?>">
                                            Rp <?= number_format($sisa, 0, ',', '.') ?>
                                        </td>
                                        <td><span class="badge <?= $class ?>"><?= $icon ?> <?= strtoupper($status) ?></span></td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($status != 'Lunas' && $sisa > 0 && $id_tagihan > 0): ?>
                                                    <button class="btn btn-primary btn-sm" onclick="bayarOnline(<?= $id_tagihan ?>, '<?= $nis ?>', '<?= $bulan ?>', '<?= $tahun ?>')">
                                                        <i class="fas fa-credit-card"></i> Bayar via Midtrans
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($status != 'Lunas' && $id_tagihan > 0): ?>
                                                    <button class="btn btn-warning btn-sm" onclick="bukaKonfirmasi(<?= $id_tagihan ?>, '<?= $bulan ?> <?= $tahun ?>', '<?= $nis ?>')">
                                                        <i class="fas fa-upload"></i> Konfirmasi Pembayaran transfer
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($status == 'Lunas'): ?>
                                                    <span class="text-success"><i class="fas fa-check-circle"></i> Lunas</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="2" style="text-align:right;">Total Keseluruhan</td>
                                    <td>Rp <?= number_format($total_dibayar, 0, ',', '.') ?></td>
                                    <td colspan="3">Dari Rp <?= number_format($total_tagihan, 0, ',', '.') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada data tagihan untuk siswa ini</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <a href="cek_spp.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Cek Data Lain
            </a>

        <?php else: ?>
            <div class="error-header">
                <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h1>Data Tidak Ditemukan</h1>
                <p>NIS / Nama "<strong><?= htmlspecialchars($nis) ?></strong>" tidak terdaftar</p>
            </div>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <p>Maaf, data siswa tidak ditemukan.</p>
                <p class="text-muted">Silakan periksa kembali ID/NIS Anda atau hubungi pihak sekolah.</p>
            </div>
            <a href="cek_spp.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Coba Lagi
            </a>
        <?php endif; ?>
    </div>

    <!-- Modal Konfirmasi -->
    <div id="modalKonfirmasi" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="judulKonfirmasi">Konfirmasi Pembayaran</h3>
                <p>Upload bukti transfer untuk verifikasi</p>
            </div>
            <div class="modal-body">
                <form action="proses_konfirmasi.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_tagihan" id="id_tagihan_modal">
                    <input type="hidden" name="nis" id="nis_modal">

                    <div class="form-group">
                        <label class="file-label-custom" for="foto_bukti">
                            <i class="fas fa-cloud-upload-alt"></i> Pilih Bukti Transfer (Gambar)
                        </label>
                        <input type="file" name="foto_bukti" id="foto_bukti" accept="image/*" class="form-control-file" required style="display: none;">
                        <div class="file-info" id="fileInfo">
                            <span id="fileName" class="file-name">Belum ada file dipilih</span>
                            <span id="fileSize" class="file-size"></span>
                            <span id="fileStatus" class="file-status"></span>
                        </div>
                        <small class="text-muted">Format: JPG, PNG, JPEG. Maks 2 MB.</small>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Kirim Bukti
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="tutupKonfirmasi()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js?v=2"></script>
    <script>
        function bayarOnline(id_tagihan, nis, bulan, tahun) {
            var url = 'proses_payment.php?id_tagihan=' + id_tagihan + '&nis=' + nis + '&bulan=' + bulan + '&tahun=' + tahun;
            window.open(url, '_blank', 'width=500,height=700');
        }

        function bukaKonfirmasi(id_tagihan, periode, nis) {
            document.getElementById('id_tagihan_modal').value = id_tagihan;
            document.getElementById('nis_modal').value = nis;
            document.getElementById('judulKonfirmasi').innerHTML = "Konfirmasi " + periode;
            document.getElementById('modalKonfirmasi').style.display = 'flex';
            resetFileInfo();
        }

        function tutupKonfirmasi() {
            document.getElementById('modalKonfirmasi').style.display = 'none';
            var fileInput = document.getElementById('foto_bukti');
            if (fileInput) fileInput.value = '';
            resetFileInfo();
        }

        function resetFileInfo() {
            var fileName = document.getElementById('fileName');
            var fileSize = document.getElementById('fileSize');
            var fileStatus = document.getElementById('fileStatus');
            if (fileName) fileName.textContent = 'Belum ada file dipilih';
            if (fileSize) fileSize.textContent = '';
            if (fileStatus) {
                fileStatus.textContent = '';
                fileStatus.className = 'file-status';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var fileInput = document.getElementById('foto_bukti');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    var file = this.files[0];
                    var fileNameSpan = document.getElementById('fileName');
                    var fileSizeSpan = document.getElementById('fileSize');
                    var fileStatusSpan = document.getElementById('fileStatus');

                    if (file) {
                        fileNameSpan.textContent = file.name;
                        var size = file.size;
                        var sizeText = '';
                        if (size < 1024 * 1024) {
                            sizeText = (size / 1024).toFixed(1) + ' KB';
                        } else {
                            sizeText = (size / (1024 * 1024)).toFixed(1) + ' MB';
                        }
                        fileSizeSpan.textContent = sizeText;

                        if (size > 2 * 1024 * 1024) {
                            if (fileStatusSpan) {
                                fileStatusSpan.textContent = '⚠️ Ukuran terlalu besar (maks 2 MB)';
                                fileStatusSpan.className = 'file-status invalid';
                            }
                            this.value = '';
                            resetFileInfo();
                            alert('Ukuran file terlalu besar! Maksimal 2 MB.');
                            return;
                        }

                        var allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                        if (!allowedTypes.includes(file.type)) {
                            if (fileStatusSpan) {
                                fileStatusSpan.textContent = '⚠️ Format tidak didukung (harus JPG/PNG/JPEG)';
                                fileStatusSpan.className = 'file-status invalid';
                            }
                            this.value = '';
                            resetFileInfo();
                            alert('Format file tidak didukung. Harap pilih gambar (JPG, PNG, JPEG).');
                            return;
                        }

                        if (fileStatusSpan) {
                            fileStatusSpan.textContent = '✅ File siap diunggah';
                            fileStatusSpan.className = 'file-status valid';
                        }
                    } else {
                        resetFileInfo();
                    }
                });
            }
        });
    </script>
</body>

</html>