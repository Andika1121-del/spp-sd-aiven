<?php
require_once __DIR__ . '/../koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi akses halaman langsung
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Memulai database transaction
    mysqli_begin_transaction($koneksi);

    try {
        // Pemetaan ID kelas berdasarkan database db_spp_sd_new milikmu
        $map_kelas = [
            // --- KELAS 6 Lulus ke Alumni (id_kelas = 40) ---
            35 => 40,
            36 => 40,
            37 => 40,
            38 => 40,
            39 => 40,

            // --- KELAS 5 Naik ke KELAS 6 ---
            30 => 35,
            31 => 36,
            32 => 37,
            33 => 38,
            34 => 39,

            // --- KELAS 4 Naik ke KELAS 5 ---
            24 => 30,
            25 => 31,
            26 => 32,
            27 => 33,
            28 => 34,
            29 => 34,

            // --- KELAS 3 Naik ke KELAS 4 ---
            17 => 24,
            18 => 25,
            19 => 26,
            20 => 27,
            21 => 28,
            22 => 29,
            23 => 29,

            // --- KELAS 2 Naik ke KELAS 3 ---
            9  => 17,
            10 => 18,
            11 => 19,
            12 => 20,
            13 => 21,
            14 => 22,
            15 => 23,
            16 => 23,

            // --- KELAS 1 Naik ke KELAS 2 ---
            1  => 9,
            2  => 10,
            3  => 11,
            4  => 12,
            5  => 13,
            6  => 14,
            7  => 15,
            8  => 16
        ];

        // Eksekusi pembaruan data secara looping
        foreach ($map_kelas as $kelas_lama => $kelas_baru) {
            $query = "UPDATE siswa SET id_kelas = '$kelas_baru' WHERE id_kelas = '$kelas_lama'";
            if (!mysqli_query($koneksi, $query)) {
                throw new Exception("Gagal memperbarui ID Kelas dari $kelas_lama ke $kelas_baru");
            }
        }

        // Simpan data jika seluruh query aman tanpa kendala
        mysqli_commit($koneksi);

        // Response sukses dengan SweetAlert2
        echo "<!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Proses kelulusan alumni dan kenaikan kelas siswa sukses diperbarui.',
                    icon: 'success',
                    confirmButtonColor: '#0a5c45'
                }).then(() => {
                    window.location.href = 'dashboard.php';
                });
            </script>
        </body>
        </html>";
    } catch (Exception $e) {
        // Batalkan perubahan jika ada error dalam transaksi query
        mysqli_rollback($koneksi);

        // Response gagal dengan SweetAlert2
        echo "<!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    title: 'Proses Gagal!',
                    text: '" . $e->getMessage() . "',
                    icon: 'error',
                    confirmButtonColor: '#e14444'
                }).then(() => {
                    window.location.href = 'kenaikan_kelas.php';
                });
            </script>
        </body>
        </html>";
    }
} else {
    header("Location: dashboard.php");
    exit();
}
