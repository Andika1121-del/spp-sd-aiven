<?php
session_start();
/** @var mysqli $conn */
include '../koneksi.php';

// Cek login
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: ../login.php");
    exit();
}

// ========== FUNGSI PEMBERSIH NOMOR WA ==========
function bersihkanNomorWa($nomor)
{
    $nomor = preg_replace('/[^0-9]/', '', $nomor);
    if (substr($nomor, 0, 1) === '0') {
        $nomor = '62' . substr($nomor, 1);
    }
    return $nomor;
}

// ========== PROSES TAMBAH SISWA ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $nis = mysqli_real_escape_string($conn, $_POST['nis']);
    $nama_siswa = mysqli_real_escape_string($conn, $_POST['nama_siswa']);
    $id_kelas = mysqli_real_escape_string($conn, $_POST['id_kelas']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $jk = mysqli_real_escape_string($conn, $_POST['jk'] ?? 'L');

    $no_wa_ortu = isset($_POST['no_wa_ortu']) ? trim($_POST['no_wa_ortu']) : '';
    $no_wa_ortu = preg_replace('/[^0-9]/', '', $no_wa_ortu);
    $panjang = strlen($no_wa_ortu);
    if (!empty($no_wa_ortu) && ($panjang < 10 || $panjang > 15)) {
        $_SESSION['error'] = "Nomor WA harus terdiri dari 10-15 digit angka!";
        header("Location: siswa.php");
        exit();
    }
    if (!empty($no_wa_ortu) && substr($no_wa_ortu, 0, 1) === '0') {
        $no_wa_ortu = '62' . substr($no_wa_ortu, 1);
    }

    $cek = mysqli_query($conn, "SELECT id_siswa FROM siswa WHERE nis = '$nis'");
    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['error'] = "NIS '$nis' sudah terdaftar! Gunakan NIS lain.";
        header("Location: siswa.php");
        exit();
    }

    $query = "INSERT INTO siswa (nis, nama_siswa, id_kelas, alamat, no_wa_ortu, jk) 
              VALUES ('$nis', '$nama_siswa', '$id_kelas', '$alamat', '$no_wa_ortu', '$jk')";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Data siswa '$nama_siswa' berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal tambah data: " . mysqli_error($conn);
    }
    header("Location: siswa.php");
    exit();
}

// ========== PROSES EDIT SISWA ==========
if (isset($_GET['edit'])) {
    $id_edit = mysqli_real_escape_string($conn, $_GET['edit']);
    $data_edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM siswa WHERE id_siswa = '$id_edit'"));
    if (!$data_edit) {
        $_SESSION['error'] = "Data siswa tidak ditemukan!";
        header("Location: siswa.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id_siswa = mysqli_real_escape_string($conn, $_POST['id_siswa']);
    $nis = mysqli_real_escape_string($conn, $_POST['nis']);
    $nama_siswa = mysqli_real_escape_string($conn, $_POST['nama_siswa']);
    $id_kelas = mysqli_real_escape_string($conn, $_POST['id_kelas']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $jk = mysqli_real_escape_string($conn, $_POST['jk'] ?? 'L');

    $no_wa_ortu = isset($_POST['no_wa_ortu']) ? trim($_POST['no_wa_ortu']) : '';
    $no_wa_ortu = preg_replace('/[^0-9]/', '', $no_wa_ortu);
    $panjang = strlen($no_wa_ortu);
    if (!empty($no_wa_ortu) && ($panjang < 10 || $panjang > 15)) {
        $_SESSION['error'] = "Nomor WA harus terdiri dari 10-15 digit angka!";
        header("Location: siswa.php");
        exit();
    }
    if (!empty($no_wa_ortu) && substr($no_wa_ortu, 0, 1) === '0') {
        $no_wa_ortu = '62' . substr($no_wa_ortu, 1);
    }

    $cek = mysqli_query($conn, "SELECT id_siswa FROM siswa WHERE nis = '$nis' AND id_siswa != '$id_siswa'");
    if (mysqli_num_rows($cek) > 0) {
        $_SESSION['error'] = "NIS '$nis' sudah digunakan oleh siswa lain!";
        header("Location: siswa.php");
        exit();
    }

    $query = "UPDATE siswa SET 
                nis='$nis', 
                nama_siswa='$nama_siswa', 
                id_kelas='$id_kelas', 
                alamat='$alamat', 
                no_wa_ortu='$no_wa_ortu',
                jk='$jk'
              WHERE id_siswa='$id_siswa'";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Data siswa '$nama_siswa' berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal update data: " . mysqli_error($conn);
    }
    header("Location: siswa.php");
    exit();
}

// ========== AMBIL DATA KELAS ==========
$kelas_list = [];
$query_kelas = mysqli_query($conn, "SELECT * FROM kelas ORDER BY nama_kelas ASC");
while ($row = mysqli_fetch_assoc($query_kelas)) {
    $kelas_list[] = $row;
}

// ========== FILTER & PENCARIAN ==========
$where = [];
if (!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where[] = "(s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%')";
}
if (!empty($_GET['kelas'])) {
    $kelas_filter = (int)$_GET['kelas'];
    $where[] = "s.id_kelas = $kelas_filter";
}

$where_sql = (count($where) > 0) ? "WHERE " . implode(" AND ", $where) : "";

$query_siswa = mysqli_query($conn, "SELECT s.*, k.nama_kelas 
                                     FROM siswa s
                                     JOIN kelas k ON s.id_kelas = k.id_kelas
                                     $where_sql
                                     ORDER BY k.nama_kelas ASC, s.nama_siswa ASC");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Data Siswa - Admin SD Mujahidin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>
    <style>
        /* ===== Toast Notification ===== */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 380px;
            width: 100%;
        }

        .toast {
            background: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.4s ease;
            border-left: 5px solid;
            transition: all 0.3s;
        }

        .toast-success {
            border-color: #10b981;
        }

        .toast-error {
            border-color: #ef4444;
        }

        .toast-icon {
            font-size: 22px;
            width: 32px;
            text-align: center;
        }

        .toast-success .toast-icon {
            color: #10b981;
        }

        .toast-error .toast-icon {
            color: #ef4444;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            font-size: 15px;
            color: #1e293b;
        }

        .toast-message {
            font-size: 13px;
            color: #475569;
            margin-top: 2px;
        }

        .toast-close {
            cursor: pointer;
            color: #94a3b8;
            font-size: 18px;
            background: none;
            border: none;
            padding: 0 4px;
        }

        .toast-close:hover {
            color: #475569;
        }

        @keyframes slideInRight {
            0% {
                transform: translateX(120%);
                opacity: 0;
            }

            100% {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            0% {
                transform: translateX(0);
                opacity: 1;
            }

            100% {
                transform: translateX(120%);
                opacity: 0;
            }
        }

        .toast-hide {
            animation: slideOutRight 0.4s ease forwards;
        }

        .alert-notification {
            display: none;
        }

        .filter-form .form-group {
            margin-bottom: 0;
        }

        .filter-form .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
        }

        .filter-form .form-control {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
        }

        .filter-form .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
        }

        /* ===== STYLE MODAL POP-UP KUSTOM ===== */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .custom-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .custom-modal {
            background: white;
            width: 90%;
            max-width: 400px;
            padding: 30px 24px;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: center;
            transform: scale(0.85) translateY(20px);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .custom-modal-overlay.show .custom-modal {
            transform: scale(1) translateY(0);
        }

        .modal-danger-icon {
            width: 64px;
            height: 64px;
            background: #fee2e2;
            color: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 18px;
            animation: pulse-red 2s infinite;
        }

        .custom-modal h4 {
            font-size: 18px;
            color: #0f172a;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .custom-modal p {
            font-size: 14px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-btn {
            flex: 1;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .modal-btn-confirm {
            background: #ef4444;
            color: white;
        }

        .modal-btn-confirm:hover {
            background: #dc2626;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }

        .modal-btn-cancel {
            background: #f1f5f9;
            color: #475569;
        }

        .modal-btn-cancel:hover {
            background: #e2e8f0;
        }

        @keyframes pulse-red {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }

            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }

            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }
    </style>
</head>

<body>

    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-shield-alt"></i>
            <strong>SD Mujahidin</strong>
            <span style="font-weight: normal; font-size: 13px;">- Admin</span>
        </div>
        <a href="../logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="siswa.php" class="active"><i class="fas fa-users"></i> Data Siswa</a>
        <a href="kelas.php"><i class="fas fa-chalkboard"></i> Data Kelas</a>
        <a href="user.php"><i class="fas fa-user-shield"></i> Data User</a>
        <a href="spp.php"><i class="fas fa-money-bill-wave"></i> Jenis Pembayaran</a>
        <a href="laporan_admin.php"><i class="fas fa-print"></i> Laporan</a>
        <a href="import_siswa_excel.php"><i class="fas fa-file-import"></i> Import Siswa</a>
    </div>

    <div class="content">

        <!-- ===== FORM TAMBAH / EDIT SISWA ===== -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> <?= isset($data_edit) ? 'Edit Data Siswa' : 'Tambah Data Siswa' ?></h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> NIS / NISN</label>
                            <input type="text" name="nis" value="<?= $data_edit['nis'] ?? '' ?>" placeholder="Masukkan NIS/NISN" required>
                            <small class="text-gray">Nomor Induk Siswa Nasional</small>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Nama Siswa</label>
                            <input type="text" name="nama_siswa" value="<?= $data_edit['nama_siswa'] ?? '' ?>" placeholder="Nama lengkap siswa" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-venus-mars"></i> Jenis Kelamin</label>
                            <select name="jk" required>
                                <option value="L" <?= isset($data_edit) && $data_edit['jk'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= isset($data_edit) && $data_edit['jk'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-chalkboard"></i> Kelas</label>
                            <select name="id_kelas" required>
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($kelas_list as $k): ?>
                                    <option value="<?= $k['id_kelas'] ?>" <?= isset($data_edit) && $data_edit['id_kelas'] == $k['id_kelas'] ? 'selected' : '' ?>>
                                        <?= $k['nama_kelas'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Alamat</label>
                            <input type="text" name="alamat" value="<?= $data_edit['alamat'] ?? '' ?>" placeholder="Alamat siswa">
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-whatsapp"></i> No WhatsApp Orang Tua</label>
                            <input type="text"
                                name="no_wa_ortu"
                                id="no_wa_ortu"
                                pattern="[0-9]{10,15}"
                                maxlength="15"
                                title="Hanya angka, minimal 10 digit, maksimal 15 digit"
                                placeholder="Contoh: 81234567890 atau 6281234567890"
                                value="<?= isset($data_edit['no_wa_ortu']) ? htmlspecialchars($data_edit['no_wa_ortu']) : '' ?>">
                            <small class="text-gray">* Hanya angka, tanpa spasi/tanda hubung. Panjang 10-15 digit.</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <?php if (isset($data_edit)): ?>
                            <input type="hidden" name="id_siswa" value="<?= $data_edit['id_siswa'] ?>">
                            <button type="submit" name="update" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Data
                            </button>
                            <a href="siswa.php" class="btn btn-secondary">Batal</a>
                        <?php else: ?>
                            <button type="submit" name="simpan" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Data
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===== TABEL DATA SISWA ===== -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Daftar Siswa</h3>
            </div>

            <div class="card-body" style="padding-bottom:0;">
                <form method="GET" class="filter-form" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
                    <div class="form-group" style="flex:1; min-width:180px;">
                        <label for="search"><i class="fas fa-search"></i> Cari Nama / NIS</label>
                        <input type="text" name="search" id="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Ketik nama atau NIS..." class="form-control">
                    </div>
                    <div class="form-group" style="min-width:150px;">
                        <label for="kelas"><i class="fas fa-chalkboard"></i> Filter Kelas</label>
                        <select name="kelas" id="kelas" class="form-control">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($kelas_list as $k): ?>
                                <option value="<?= $k['id_kelas'] ?>" <?= (isset($_GET['kelas']) && $_GET['kelas'] == $k['id_kelas']) ? 'selected' : '' ?>>
                                    <?= $k['nama_kelas'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; gap:8px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Terapkan
                        </button>
                        <a href="siswa.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
            <hr style="margin:10px 20px;">

            <div class="table-wrapper">
                <?php if (mysqli_num_rows($query_siswa) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th width="40">No</th>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>JK</th>
                                    <th>No WhatsApp</th>
                                    <th width="120">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                while ($s = mysqli_fetch_assoc($query_siswa)): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td>export<strong><?= htmlspecialchars($s['nis'] ?? '-') ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($s['nama_siswa']) ?></strong><br>
                                            <small class="text-gray"><?= htmlspecialchars(substr($s['alamat'] ?? '-', 0, 30)) ?></small>
                                        </td>
                                        <td><span class="badge-kelas"><?= $s['nama_kelas'] ?></span></td>
                                        <td><?= $s['jk'] == 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                                        <td>
                                            <i class="fab fa-whatsapp text-success"></i> <?= htmlspecialchars($s['no_wa_ortu'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit=<?= $s['id_siswa'] ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <button type="button"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="konfirmasiHapus('hapus_siswa.php?id_siswa=<?= $s['id_siswa']; ?>', '<?= htmlspecialchars(addslashes($s['nama_siswa'])); ?>')">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Belum ada data siswa. Silakan tambah siswa baru.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== STRUKTUR MODAL POP-UP KUSTOM ===== -->
    <div class="custom-modal-overlay" id="deleteModalOverlay">
        <div class="custom-modal">
            <div class="modal-danger-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h4>Hapus Data Siswa?</h4>
            <p>Apakah Anda yakin ingin menghapus <strong id="namaSiswaModal" style="color:#0f172a;">-</strong>? Semua data tagihan dan pembayaran yang terkait akan ikut dibersihkan secara permanen!</p>
            <div class="modal-buttons">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="tutupModalHapus()">Batal</button>
                <a href="#" id="linkHapusModal" class="modal-btn modal-btn-confirm" style="text-align:center; text-decoration:none; line-height:20px;">Ya, Hapus</a>
            </div>
        </div>
    </div>

    <!-- ===== KONTAINER TOAST ===== -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- ===== SCRIPT UTAMA JAVASCRIPT ===== -->
    <script>
        // --- BUKA & TUTUP MODAL POP-UP ---
        function konfirmasiHapus(urlTarget, namaSiswa) {
            document.getElementById('namaSiswaModal').textContent = namaSiswa;
            document.getElementById('linkHapusModal').setAttribute('href', urlTarget);

            const overlay = document.getElementById('deleteModalOverlay');
            overlay.classList.add('show');
        }

        function tutupModalHapus() {
            const overlay = document.getElementById('deleteModalOverlay');
            overlay.classList.remove('show');
        }

        document.getElementById('deleteModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                tutupModalHapus();
            }
        });

        // --- SISTEM TOAST NOTIFICATION ---
        function showToast(type, message, title = '') {
            const container = document.getElementById('toastContainer');
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle'
            };
            const titles = {
                success: 'Sukses',
                error: 'Gagal'
            };
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-icon"><i class="${icons[type] || icons.success}"></i></div>
                <div class="toast-content">
                    <div class="toast-title">${title || titles[type]}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            container.appendChild(toast);

            // Auto hilang setelah 5 detik agar sempat dibaca admin
            setTimeout(() => {
                toast.classList.add('toast-hide');
                setTimeout(() => toast.remove(), 400);
            }, 5000);
        }
    </script>

    <!-- ===== PEMANGGILAN TOAST BERDASARKAN SESSION PHP ===== -->
    <script>
        <?php if (isset($_SESSION['success'])): ?>
            showToast('success', '<?= addslashes($_SESSION['success']) ?>');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            showToast('error', '<?= addslashes($_SESSION['error']) ?>');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>

    <script>
        // Logika Input WA & Sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const inputWa = document.getElementById('no_wa_ortu');
            if (inputWa) {
                inputWa.addEventListener('input', function(e) {
                    this.value = this.value.replace(/\D/g, '');
                });
            }
        });

        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) sidebar.classList.remove('active');
            });
        }
    </script>
</body>

</html>