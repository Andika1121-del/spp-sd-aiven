<?php
include '../koneksi.php';

// Membungkam notice session jika session sudah aktif di header/sidebar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// include 'header.php';
// include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kenaikan Kelas Massal - SPP SD Mujahidin</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>

<body style="background-color: var(--primary-light, #34d399); padding: 20px;">

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg border-0" style="border-radius: var(--radius-lg, 20px); overflow: hidden;">

                    <div class="card-header text-white text-center py-4" style="background: var(--primary-gradient, linear-gradient(135deg, #064e3b 0%, #0a5c45 100%));">
                        <h3 class="mb-0 font-weight-bold">
                            <i class="fas fa-graduation-cap mr-2"></i> Fitur Kenaikan Kelas Massal
                        </h3>
                        <small class="text-white-50">Sistem Informasi Pembayaran SPP SD Mujahidin</small>
                    </div>

                    <div class="card-body p-5 bg-white">
                        <h5 class="text-dark font-weight-bold mb-3">Alur & Logika Pembaruan Data:</h5>
                        <p class="text-muted">
                            Fitur ini digunakan sekali dalam setahun pada akhir tahun ajaran untuk memperbarui data kelas siswa secara otomatis dan serentak:
                        </p>

                        <ul class="list-group list-group-flush mb-4">
                            <li class="list-group-item px-0">
                                <i class="fas fa-check-circle text-success mr-2"></i> Siswa <strong>Kelas 6 (A-E)</strong> akan otomatis diluluskan dan status kelas berubah menjadi <strong>Alumni / Lulus</strong>.
                            </li>
                            <li class="list-group-item px-0">
                                <i class="fas fa-check-circle text-success mr-2"></i> Siswa <strong>Kelas 1 s/d 5 (Paralel A-H)</strong> akan otomatis bergeser naik ke tingkat kelas di atasnya secara presisi.
                            </li>
                        </ul>

                        <div class="alert p-3 mb-4" style="background-color: var(--danger-light, #fcc2c2); border-left: 5px solid var(--danger, #e14444); border-radius: var(--radius-md, 16px);">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-exclamation-triangle mt-1 mr-3" style="color: var(--danger, #e14444); font-size: 1.25rem;"></i>
                                <div>
                                    <strong style="color: var(--danger-dark, #dc2626);">PERINGATAN KERAS!</strong>
                                    <p class="mb-0 text-dark" style="font-size: 0.9rem;">
                                        Tindakan ini akan memanipulasi seluruh tabel data siswa sekaligus dan <strong>tidak dapat dibatalkan (Undo)</strong> secara otomatis. Harap lakukan <strong>BACKUP / EXPORT</strong> tabel <code>siswa</code> melalui phpMyAdmin terlebih dahulu!
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form id="formKenaikan" action="proses_kenaikan.php" method="POST">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="dashboard.php" class="btn btn-light px-4 font-weight-bold" style="border-radius: var(--radius, 12px);">
                                    <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
                                </a>
                                <button type="button" id="btnEksekusi" class="btn text-white font-weight-bold px-4 py-2" style="background-color: var(--secondary, #f59e0b); border-radius: var(--radius, 12px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.15);">
                                    <i class="fas fa-bolt mr-2"></i> Eksekusi Kenaikan Kelas
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.getElementById('btnEksekusi').addEventListener('click', function(e) {
            Swal.fire({
                title: 'APAKAH ANDA YAKIN?',
                text: "Tindakan ini akan menaikkan kelas seluruh siswa dan meluluskan kelas 6. Pastikan Anda sudah membackup database!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e14444',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Eksekusi Sekarang!',
                cancelButtonText: 'Batal',
                background: '#ffffff',
                customClass: {
                    popup: 'rounded-lg'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Loading pop-up saat proses berjalan agar admin tidak klik berkali-kali
                    Swal.fire({
                        title: 'Sedang Memproses...',
                        text: 'Harap tunggu, sistem sedang memindahkan data kelas siswa.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    // Jalankan submit data ke backend
                    document.getElementById('formKenaikan').submit();
                }
            });
        });
    </script>

</body>

</html>