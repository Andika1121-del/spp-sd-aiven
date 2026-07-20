<?php
session_start();
/** @var mysqli $conn */
include '../koneksi.php';
require_once '../vendor/autoload.php'; // PhpSpreadsheet via Composer

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

// Proses upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_excel'])) {
    $file = $_FILES['file_excel']['tmp_name'];
    $extension = pathinfo($_FILES['file_excel']['name'], PATHINFO_EXTENSION);
    $allowed = ['xlsx', 'xls'];
    if (!in_array(strtolower($extension), $allowed)) {
        $_SESSION['error'] = "Format file tidak didukung. Gunakan .xlsx atau .xls";
        header("Location: import_siswa_excel.php");
        exit();
    }

    try {
        $spreadsheet = IOFactory::load($file);
        $total_insert = 0;
        $total_skip = 0;
        $errors = [];

        // Ambil semua kelas dari database untuk mapping
        $kelas_map = [];
        $query_kelas = mysqli_query($conn, "SELECT id_kelas, nama_kelas FROM kelas");
        while ($row = mysqli_fetch_assoc($query_kelas)) {
            $kelas_map[$row['nama_kelas']] = $row['id_kelas'];
        }

        // Loop setiap sheet
        // Loop setiap sheet
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray();
            $row_count = count($rows);
            $i = 0;
            while ($i < $row_count) {
                $row = $rows[$i];

                // Gabungkan baris jadi satu string untuk mempermudah deteksi regex
                $row_string = is_array($row) ? implode(' ', array_filter($row)) : '';

                // Regex fleksibel: mencari kata "KELAS", diikuti angka tingkat, opsional spasi/titik, lalu huruf kelas (A-H)
                if (!empty($row_string) && preg_match('/KELAS\s*(\d+)\s*[\.\s]*\s*([A-H])/i', $row_string, $matches)) {
                    $tingkat = $matches[1];
                    $huruf = strtoupper($matches[2]);

                    // DISESUAIKAN: Format database kamu adalah "Angka [Spasi] Huruf" (Contoh: "1 A")
                    $nama_kelas = "$tingkat $huruf";

                    $id_kelas = isset($kelas_map[$nama_kelas]) ? $kelas_map[$nama_kelas] : null;

                    if (!$id_kelas) {
                        $errors[] = "Kelas '$nama_kelas' tidak ditemukan di database. Lewati blok ini.";
                        $i++;
                        continue;
                    }

                    // Cari baris header data (mengandung "NO INDUK" dan "NAMA SISWA")
                    // Cari baris header data (mengandung "NO INDUK" dan "NAMA SISWA")
                    $data_start = null;
                    for ($j = $i + 1; $j < $row_count; $j++) {
                        if (!isset($rows[$j])) continue;

                        // Gabungkan satu baris jadi string tunggal untuk dicek
                        $check_row_str = strtoupper(implode(' ', array_filter($rows[$j])));

                        if (strpos($check_row_str, 'NO INDUK') !== false && strpos($check_row_str, 'NAMA SISWA') !== false) {
                            $data_start = $j + 1; // data siswa dimulai dari baris setelah ini
                            break;
                        }
                    }

                    if (!$data_start) {
                        $errors[] = "Tidak ditemukan header data (NO INDUK / NAMA SISWA) untuk kelas $nama_kelas";
                        $i++;
                        continue;
                    }

                    // Ambil data siswa di bawah header
                    $k = $data_start;
                    while ($k < $row_count) {
                        $data_row = $rows[$k];

                        // Jika baris kosong, atau menemukan judul kelas baru, atau teks header baru, berhenti dari blok ini
                        if (empty(array_filter($data_row))) break;
                        $data_row_str = strtoupper(implode(' ', array_filter($data_row)));
                        if (strpos($data_row_str, 'KELAS') !== false || strpos($data_row_str, 'TAHUN AJARAN') !== false) {
                            break;
                        }

                        // Cari posisi kolom NO INDUK (1) dan NAMA SISWA (2) secara dinamis atau fallback ke index default
                        $nis = isset($data_row[1]) ? trim($data_row[1]) : '';
                        $nama = isset($data_row[2]) ? trim($data_row[2]) : '';
                        $jk = isset($data_row[3]) ? trim($data_row[3]) : '';

                        if (empty($nis) || empty($nama) || is_numeric($nama)) {
                            // Jika kolom nama berisi angka nomor urut (karena geser kolom), atau kosong, skip ke baris berikutnya
                            $k++;
                            continue;
                        }

                        // Cek duplikat NIS
                        $check = mysqli_query($conn, "SELECT id_siswa FROM siswa WHERE nis = '$nis'");
                        if (mysqli_num_rows($check) > 0) {
                            $total_skip++;
                            $k++;
                            continue;
                        }

                        // Normalisasi JK
                        $jk = (strtoupper($jk) == 'L') ? 'L' : ((strtoupper($jk) == 'P') ? 'P' : 'L');

                        $query = "INSERT INTO siswa (nis, nama_siswa, id_kelas, jk, alamat, no_wa_ortu) 
                                  VALUES ('$nis', '$nama', '$id_kelas', '$jk', '', '')";
                        if (mysqli_query($conn, $query)) {
                            $total_insert++;
                        } else {
                            $errors[] = "Gagal insert siswa $nama (NIS $nis): " . mysqli_error($conn);
                        }

                        $k++;
                    }

                    // Lanjutkan perulangan utama dari baris terakhir yang diproses di blok kelas ini
                    $i = $k;
                } else {
                    $i++;
                }
            }
        }

        $_SESSION['success'] = "Import selesai. Berhasil: $total_insert siswa, Duplikat/skip: $total_skip";
        if (!empty($errors)) {
            $_SESSION['error_details'] = $errors;
        }
        header("Location: import_siswa_excel.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: import_siswa_excel.php");
        exit();
    }
}

// Ambil notifikasi
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
$error_details = $_SESSION['error_details'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['error_details']);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Import Data Siswa - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .alert-notification {
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-errors ul {
            margin: 6px 0 0 20px;
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .form-group input[type="file"] {
            display: block;
            width: 100%;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary {
            background: #10b981;
            color: white;
        }

        .btn-primary:hover {
            background: #059669;
        }
    </style>
</head>

<body>
    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
    <div class="navbar">
        <div class="navbar-brand"><i class="fas fa-shield-alt"></i><strong>SD Mujahidin</strong><span style="font-weight:normal;">- Admin</span></div>
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="sidebar" id="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="siswa.php"><i class="fas fa-users"></i> Data Siswa</a>
        <a href="kelas.php"><i class="fas fa-chalkboard"></i> Data Kelas</a>
        <a href="user.php"><i class="fas fa-user-shield"></i> Data User</a>
        <a href="spp.php"><i class="fas fa-money-bill-wave"></i> Jenis Pembayaran</a>
        <a href="laporan_admin.php"><i class="fas fa-print"></i> Laporan</a>
        <a href="import_siswa_excel.php" class="active"><i class="fas fa-file-import"></i> Import Siswa</a>
    </div>

    <div class="content">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-import"></i> Import Data Siswa dari Excel</h3>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert-notification alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert-notification alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($error_details): ?>
                    <div class="alert-notification alert-error alert-errors">
                        <strong>Detail Error:</strong>
                        <ul>
                            <?php foreach ($error_details as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <p style="color: #475569; margin-bottom: 16px;">
                    Upload file Excel dengan format seperti template dari sekolah. File harus memiliki sheet dengan nama "KELAS 1", "KELAS 2", dst.
                    Setiap sheet berisi blok data per kelas (misal KELAS 1.A, 1.B, dst). Pastikan kelas sudah terdaftar di database.
                </p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="file_excel">Pilih File Excel (.xlsx / .xls)</label>
                        <input type="file" name="file_excel" id="file_excel" accept=".xlsx,.xls" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import</button>
                </form>
                <p style="margin-top: 16px; color: #94a3b8; font-size: 12px;">
                    Catatan: NIS (NO INDUK) akan digunakan sebagai identitas unik. Jika NIS sudah ada, data akan dilewati.
                </p>
            </div>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        menuToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) sidebar.classList.remove('active');
        });
    </script>
</body>

</html>