<?php
session_start();
/** @var mysqli $conn */
include '../koneksi.php';

// Fungsi untuk mengubah level menjadi label yang lebih manusiawi
function labelLevel($level)
{
    $map = [
        'admin'     => 'Administrator',
        'bendahara' => 'Bendahara',
        'kepsek'    => 'Kepala Sekolah'
    ];
    return $map[$level] ?? $level;
}

// Cek apakah user adalah admin
if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

// Proses Tambah User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = md5($_POST['password']);
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);

    // Cek apakah username sudah ada
    $cek = mysqli_query($conn, "SELECT * FROM user WHERE username = '$username'");
    if (mysqli_num_rows($cek) > 0) {
        $error = "Username sudah terdaftar!";
    } else {
        $query = "INSERT INTO user (username, password, nama_lengkap, level) 
                  VALUES ('$username', '$password', '$nama_lengkap', '$level')";
        mysqli_query($conn, $query);
        header("Location: user.php?msg=added");
        exit();
    }
}

// Proses Hapus
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($conn, $_GET['hapus']);
    // Cegah penghapusan user sendiri
    if ($id == $_SESSION['id_user']) {
        header("Location: user.php?msg=self_delete");
        exit();
    }
    mysqli_query($conn, "DELETE FROM user WHERE id_user = '$id'");
    header("Location: user.php?msg=deleted");
    exit();
}

// Proses Edit
if (isset($_GET['edit'])) {
    $id_edit = mysqli_real_escape_string($conn, $_GET['edit']);
    $data_edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM user WHERE id_user = '$id_edit'"));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id_user = mysqli_real_escape_string($conn, $_POST['id_user']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);

    // Cek apakah username sudah digunakan oleh user lain
    $cek = mysqli_query($conn, "SELECT * FROM user WHERE username = '$username' AND id_user != '$id_user'");
    if (mysqli_num_rows($cek) > 0) {
        $error = "Username sudah digunakan oleh user lain!";
    } else {
        if (!empty($_POST['password'])) {
            $password = md5($_POST['password']);
            $query = "UPDATE user SET username='$username', password='$password', nama_lengkap='$nama_lengkap', level='$level' WHERE id_user='$id_user'";
        } else {
            $query = "UPDATE user SET username='$username', nama_lengkap='$nama_lengkap', level='$level' WHERE id_user='$id_user'";
        }
        mysqli_query($conn, $query);
        header("Location: user.php?msg=updated");
        exit();
    }
}

// Ambil semua data user
$query_user = mysqli_query($conn, "SELECT * FROM user ORDER BY level ASC, username ASC");

// Cek pesan dari URL
$message = '';
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> User berhasil ditambahkan!</div>';
            break;
        case 'updated':
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> User berhasil diperbarui!</div>';
            break;
        case 'deleted':
            $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> User berhasil dihapus!</div>';
            break;
        case 'self_delete':
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Anda tidak bisa menghapus akun sendiri!</div>';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Data User - Admin SD Mujahidin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js" defer></script>
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
        <a href="user.php" class="active"><i class="fas fa-user-shield"></i> Data User</a>
        <a href="spp.php"><i class="fas fa-money-bill-wave"></i> Jenis Pembayaran</a>
        <a href="laporan_admin.php"><i class="fas fa-print"></i> Laporan</a>
        <a href="import_siswa_excel.php"><i class="fas fa-file-import"></i> Import Siswa</a>
    </div>

    <div class="content">

        <!-- Pesan notifikasi -->
        <?= $message ?>

        <!-- Form Tambah/Edit -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> <?= isset($data_edit) ? 'Edit User' : 'Tambah User Baru' ?></h3>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Username</label>
                            <input type="text" name="username" value="<?= $data_edit['username'] ?? '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password <?= isset($data_edit) ? '(Kosongkan jika tidak diubah)' : '' ?></label>
                            <input type="password" name="password" <?= isset($data_edit) ? '' : 'required' ?>>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" value="<?= $data_edit['nama_lengkap'] ?? '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Level / Role</label>
                            <select name="level" required>
                                <option value="">Pilih Level</option>
                                <option value="admin" <?= isset($data_edit) && $data_edit['level'] == 'admin' ? 'selected' : '' ?>>Administrator</option>
                                <option value="bendahara" <?= isset($data_edit) && $data_edit['level'] == 'bendahara' ? 'selected' : '' ?>>Bendahara</option>
                                <option value="kepsek" <?= isset($data_edit) && $data_edit['level'] == 'kepsek' ? 'selected' : '' ?>>Kepala Sekolah</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <?php if (isset($data_edit)): ?>
                                <input type="hidden" name="id_user" value="<?= $data_edit['id_user'] ?>">
                                <button type="submit" name="update" class="btn btn-primary"><i class="fas fa-save"></i> Update User</button>
                                <a href="user.php" class="btn btn-outline" style="margin-left: 10px;">Batal</a>
                            <?php else: ?>
                                <button type="submit" name="simpan" class="btn btn-primary"><i class="fas fa-save"></i> Simpan User</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Data User -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Daftar Pengguna Sistem</h3>
            </div>
            <div class="table-wrapper">
                <?php if (mysqli_num_rows($query_user) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Level</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            while ($u = mysqli_fetch_assoc($query_user)):
                                // Mapping badge class
                                $badge_map = [
                                    'admin'     => 'badge-primary',
                                    'bendahara' => 'badge-info',
                                    'kepsek'    => 'badge-warning'
                                ];
                                $badge_class = $badge_map[$u['level']] ?? 'badge-secondary';
                            ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['nama_lengkap'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge <?= $badge_class ?>">
                                            <?= labelLevel($u['level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $u['id_user'] ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <?php if ($u['id_user'] != $_SESSION['id_user']): ?>
                                            <a href="?hapus=<?= $u['id_user'] ?>" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Hapus user <?= addslashes($u['username']) ?>?')">
                                                <i class="fas fa-trash"></i> Hapus
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Belum ada data user. Silakan tambah user baru.</p>
                    </div>
                <?php endif; ?>
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