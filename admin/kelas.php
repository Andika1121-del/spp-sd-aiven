<?php

/** @var mysqli $conn */
session_start();
include '../koneksi.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

// Proses Tambah
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
    mysqli_query($conn, "INSERT INTO kelas (nama_kelas) VALUES ('$nama_kelas')");
    header("Location: kelas.php");
    exit();
}

// Proses Hapus
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($conn, $_GET['hapus']);
    mysqli_query($conn, "DELETE FROM kelas WHERE id_kelas = '$id'");
    header("Location: kelas.php");
    exit();
}

// Proses Edit
if (isset($_GET['edit'])) {
    $id_edit = mysqli_real_escape_string($conn, $_GET['edit']);
    $data_edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM kelas WHERE id_kelas = '$id_edit'"));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id_kelas = mysqli_real_escape_string($conn, $_POST['id_kelas']);
    $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
    mysqli_query($conn, "UPDATE kelas SET nama_kelas='$nama_kelas' WHERE id_kelas='$id_kelas'");
    header("Location: kelas.php");
    exit();
}

$query_kelas = mysqli_query($conn, "SELECT k.*, COUNT(s.id_siswa) as jumlah_siswa 
                                     FROM kelas k
                                     LEFT JOIN siswa s ON k.id_kelas = s.id_kelas
                                     GROUP BY k.id_kelas
                                     ORDER BY k.nama_kelas ASC");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Data Kelas - Admin SD Mujahidin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>
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
        <a href="siswa.php"><i class="fas fa-users"></i> Data Siswa</a>
        <a href="kelas.php" class="active"><i class="fas fa-chalkboard"></i> Data Kelas</a>
        <a href="user.php"><i class="fas fa-user-shield"></i> Data User</a>
        <a href="spp.php"><i class="fas fa-money-bill-wave"></i> Jenis Pembayaran</a>
        <a href="laporan_admin.php"><i class="fas fa-print"></i> Laporan</a>
        <a href="import_siswa_excel.php"><i class="fas fa-file-import"></i> Import Siswa</a>
    </div>

    <div class="content">
        <!-- Form Tambah/Edit -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> <?= isset($data_edit) ? 'Edit Kelas' : 'Tambah Kelas Baru' ?></h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-chalkboard"></i> Nama Kelas</label>
                            <input type="text" name="nama_kelas" value="<?= $data_edit['nama_kelas'] ?? '' ?>" placeholder="Contoh: Kelas 1A, Kelas 1B, dll" required>
                        </div>
                        <div class="form-group">
                            <?php if (isset($data_edit)): ?>
                                <input type="hidden" name="id_kelas" value="<?= $data_edit['id_kelas'] ?>">
                                <button type="submit" name="update" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Kelas
                                </button>
                                <a href="kelas.php" class="btn btn-outline" style="margin-left: 10px;">Batal</a>
                            <?php else: ?>
                                <button type="submit" name="simpan" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Kelas
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Data Kelas -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Daftar Kelas</h3>
            </div>
            <div class="table-wrapper">
                <?php if (mysqli_num_rows($query_kelas) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Kelas</th>
                                <th>Jumlah Siswa</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            while ($k = mysqli_fetch_assoc($query_kelas)): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= htmlspecialchars($k['nama_kelas']) ?></strong></td>
                                    <td>
                                        <span class="badge badge-gray">
                                            <i class="fas fa-user"></i> <?= $k['jumlah_siswa'] ?> siswa
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $k['id_kelas'] ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="?hapus=<?= $k['id_kelas'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus kelas <?= addslashes($k['nama_kelas']) ?>?')">
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
                        <p>Belum ada data kelas. Silakan tambah kelas baru.</p>
                    </div>
                <?php endif; ?>
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
        }
    </script>
</body>

</html>