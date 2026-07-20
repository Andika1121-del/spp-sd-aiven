<?php
session_start();
/** @var mysqli $conn */
require_once __DIR__ . '/../koneksi.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

// Proses Tambah
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $nama_kategori = mysqli_real_escape_string($conn, trim($_POST['nama_kategori']));
    $nominal = (int) mysqli_real_escape_string($conn, $_POST['nominal']);
    $keterangan = mysqli_real_escape_string($conn, trim($_POST['keterangan'] ?? ''));

    // Validasi
    if (empty($nama_kategori)) {
        $_SESSION['error'] = "Nama Kategori wajib diisi!";
    } else {
        $query = "INSERT INTO spp_kategori (nama_kategori, nominal, keterangan) 
                  VALUES ('$nama_kategori', '$nominal', '$keterangan')";
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Data SPP berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan: " . mysqli_error($conn);
        }
    }
    header("Location: spp.php");
    exit();
}

// Proses Hapus
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($conn, $_GET['hapus']);
    // Cek apakah kategori sedang digunakan oleh siswa
    $cek_siswa = mysqli_query($conn, "SELECT id_siswa FROM siswa WHERE id_spp = '$id' LIMIT 1");
    if (mysqli_num_rows($cek_siswa) > 0) {
        $_SESSION['error'] = "Kategori ini sedang digunakan oleh siswa, tidak bisa dihapus!";
    } else {
        if (mysqli_query($conn, "DELETE FROM spp_kategori WHERE id_spp = '$id'")) {
            $_SESSION['success'] = "Data SPP berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus: " . mysqli_error($conn);
        }
    }
    header("Location: spp.php");
    exit();
}

// Proses Edit - Ambil data
if (isset($_GET['edit'])) {
    $id_edit = mysqli_real_escape_string($conn, $_GET['edit']);
    $result_edit = mysqli_query($conn, "SELECT * FROM spp_kategori WHERE id_spp = '$id_edit'");
    if ($result_edit && mysqli_num_rows($result_edit) > 0) {
        $data_edit = mysqli_fetch_assoc($result_edit);
    } else {
        $_SESSION['error'] = "Data tidak ditemukan!";
        header("Location: spp.php");
        exit();
    }
}

// Proses Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id_spp = mysqli_real_escape_string($conn, $_POST['id_spp']);
    $nama_kategori = mysqli_real_escape_string($conn, trim($_POST['nama_kategori']));
    $nominal = (int) mysqli_real_escape_string($conn, $_POST['nominal']);
    $keterangan = mysqli_real_escape_string($conn, trim($_POST['keterangan'] ?? ''));

    if (empty($nama_kategori)) {
        $_SESSION['error'] = "Nama Kategori wajib diisi!";
    } else {
        $query = "UPDATE spp_kategori SET nama_kategori='$nama_kategori', nominal='$nominal', keterangan='$keterangan' 
                  WHERE id_spp='$id_spp'";
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Data SPP berhasil diupdate!";
        } else {
            $_SESSION['error'] = "Gagal update: " . mysqli_error($conn);
        }
    }
    header("Location: spp.php");
    exit();
}

// Ambil data SPP
$query_spp = mysqli_query($conn, "SELECT * FROM spp_kategori ORDER BY nominal ASC");
if (!$query_spp) {
    die("Query Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Jenis SPP - Admin SD Mujahidin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>

    <style>
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .table-spp {
            width: 100%;
            border-collapse: collapse;
        }

        .table-spp th {
            text-align: left;
            padding: 12px 15px;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            font-size: 13px;
            color: #475569;
        }

        .table-spp td {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .table-spp tr:hover {
            background: #f8fafc;
        }

        .nominal-spp {
            font-weight: 700;
            color: #10b981;
        }

        .aksi-cell {
            white-space: nowrap;
        }

        .aksi-cell a {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            margin: 0 3px;
            border-radius: 6px;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .btn-edit:hover {
            background: #d97706;
            transform: scale(1.02);
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
            transform: scale(1.02);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .aksi-cell {
                white-space: normal;
            }

            .aksi-cell a {
                margin: 3px;
            }
        }
    </style>
</head>

<body>

    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

    <div class="navbar">
        <div class="navbar-brand"><i class="fas fa-shield-alt"></i><strong>SD Mujahidin</strong><span style="font-weight: normal;">- Admin</span></div>
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="siswa.php"><i class="fas fa-users"></i> Data Siswa</a>
        <a href="kelas.php"><i class="fas fa-chalkboard"></i> Data Kelas</a>
        <a href="user.php"><i class="fas fa-user-shield"></i> Data User</a>
        <a href="spp.php" class="active"><i class="fas fa-money-bill-wave"></i> Jenis Pembayaran</a>
        <a href="laporan_admin.php"><i class="fas fa-print"></i> Laporan</a>
        <a href="import_siswa_excel.php"><i class="fas fa-file-import"></i> Import Siswa</a>
    </div>

    <div class="content">
        <!-- Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">✅ <?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">❌ <?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Form Tambah/Edit -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> <?= isset($data_edit) ? 'Edit Jenis SPP' : 'Tambah Jenis SPP Baru' ?></h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Nama Kategori <span style="color: red;">*</span></label>
                            <input type="text" name="nama_kategori" value="<?= $data_edit['nama_kategori'] ?? '' ?>" placeholder="Contoh: Kelas 1, Kelas 2, Tahfidz" required>
                            <small class="text-gray">Nama kategori akan terlihat di daftar dan untuk pemilihan siswa</small>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-money-bill-wave"></i> Nominal SPP (Rp) <span style="color: red;">*</span></label>
                            <input type="number" name="nominal" value="<?= $data_edit['nominal'] ?? '' ?>" placeholder="Contoh: 400000" required>
                            <small class="text-gray">Masukkan nominal dalam angka (tanpa titik/koma)</small>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-info-circle"></i> Keterangan (Opsional)</label>
                            <input type="text" name="keterangan" value="<?= $data_edit['keterangan'] ?? '' ?>" placeholder="Catatan tambahan (misal: Reguler, Tahfidz)">
                            <small class="text-gray">Bisa diisi untuk deskripsi tambahan</small>
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0; display: flex; gap: 12px;">
                        <?php if (isset($data_edit)): ?>
                            <input type="hidden" name="id_spp" value="<?= $data_edit['id_spp'] ?>">
                            <button type="submit" name="update" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Data
                            </button>
                            <a href="spp.php" class="btn btn-secondary">Batal</a>
                        <?php else: ?>
                            <button type="submit" name="simpan" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Data
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Data SPP -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Daftar Jenis Pembayaran SPP</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-wrapper">
                    <?php if (mysqli_num_rows($query_spp) > 0): ?>
                        <table class="table-spp">
                            <thead>
                                <tr>
                                    <th width="50">No</th>
                                    <th>Nama Kategori</th>
                                    <th>Nominal SPP</th>
                                    <th>Keterangan</th>
                                    <th width="180">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                mysqli_data_seek($query_spp, 0);
                                while ($sp = mysqli_fetch_assoc($query_spp)): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><strong><?= htmlspecialchars($sp['nama_kategori']) ?></strong></td>
                                        <td class="nominal-spp">Rp <?= number_format($sp['nominal'], 0, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($sp['keterangan'] ?? '-') ?></td>
                                        <td class="aksi-cell">
                                            <a href="?edit=<?= $sp['id_spp'] ?>" class="btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="?hapus=<?= $sp['id_spp'] ?>" class="btn-delete" onclick="return confirm('Yakin hapus data ini?')">
                                                <i class="fas fa-trash"></i> Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>Belum ada data jenis SPP. Silakan tambah data baru.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) sidebar.classList.remove('active');
            });
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });
        }
    </script>
</body>

</html>