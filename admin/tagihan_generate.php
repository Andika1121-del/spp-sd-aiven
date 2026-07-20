<?php
session_start();
include '../koneksi.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

$pesan = "";
if (isset($_POST['generate'])) {
    $bulan = mysqli_real_escape_string($conn, $_POST['bulan']);
    $tahun = mysqli_real_escape_string($conn, $_POST['tahun']);
    $nominal = mysqli_real_escape_string($conn, $_POST['nominal']);

    $siswa_query = mysqli_query($conn, "SELECT id_siswa FROM siswa");
    $jumlah_berhasil = 0;
    $jumlah_skip = 0;

    while ($siswa = mysqli_fetch_assoc($siswa_query)) {
        $id_siswa = $siswa['id_siswa'];
        $cek = mysqli_query($conn, "SELECT id_tagihan FROM tagihan WHERE id_siswa = '$id_siswa' AND bulan = '$bulan' AND tahun = '$tahun'");
        
        if (mysqli_num_rows($cek) == 0) {
            $insert = mysqli_query($conn, "INSERT INTO tagihan (id_siswa, bulan, tahun, nominal_tagihan, status) 
                                           VALUES ('$id_siswa', '$bulan', '$tahun', '$nominal', 'Belum Bayar')");
            if ($insert) $jumlah_berhasil++;
        } else {
            $jumlah_skip++;
        }
    }
    $pesan = "✅ Berhasil: $jumlah_berhasil tagihan dibuat. (Skip: $jumlah_skip sudah ada)";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Generate Tagihan - Admin SD Mujahidin</title>
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
    <a href="user.php"><i class="fas fa-user-shield"></i> Data User</a>
    <a href="spp.php"><i class="fas fa-money-bill-wave"></i> Jenis Pembayaran</a>
    <a href="laporan_admin.php"><i class="fas fa-print"></i> Laporan</a>
</div>

<div class="content">
    <div class="card" style="max-width: 550px; margin: 0 auto;">
        <div class="card-header">
            <h3><i class="fas fa-cogs"></i> Generate Tagihan Bulanan</h3>
        </div>
        <div class="card-body">
            <?php if($pesan): ?>
                <div class="alert alert-success" style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?= $pesan ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Bulan SPP</label>
                    <select name="bulan" class="form-control" required>
                        <?php
                        $bulan_list = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                        foreach($bulan_list as $b) echo "<option value='$b'>$b</option>";
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Tahun</label>
                    <input type="number" name="tahun" class="form-control" value="<?= date('Y') ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Nominal SPP (Rp)</label>
                    <input type="number" name="nominal" class="form-control" placeholder="Contoh: 150000" required>
                </div>
                <button type="submit" name="generate" class="btn btn-primary btn-block" style="width: 100%;">
                    <i class="fas fa-play"></i> Generate Tagihan Sekarang
                </button>
                <a href="dashboard.php" class="btn btn-outline btn-block" style="width: 100%; margin-top: 12px; display: block; text-align: center;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </form>
        </div>
    </div>
</div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    menuToggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    window.addEventListener('resize', () => { if (window.innerWidth > 768) sidebar.classList.remove('active'); });
</script>
</body>
</html>