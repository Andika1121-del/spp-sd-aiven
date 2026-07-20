<?php

/** @var mysqli $conn */ // Pastikan koneksi database sudah tersedia melalui $conn
session_start();
include '../koneksi.php';

// ========== CEK AKSES BENDARAHA (LANGSUNG, TANPA FUNGSI DUPLIKAT) ==========
if (!isset($_SESSION['login']) || $_SESSION['level'] != 'bendahara') {
    header("Location: ../index.php");
    exit();
}

// ========== AMBIL DATA KONFIRMASI ==========
// Perbaiki query: pastikan kolom yang diambil sesuai struktur
$query = mysqli_query($conn, "SELECT k.*, t.bulan, t.tahun, s.nama_siswa, s.nis, s.no_wa_ortu 
                              FROM konfirmasi_pembayaran k
                              JOIN tagihan t ON k.id_tagihan = t.id_tagihan
                              JOIN siswa s ON k.id_siswa = s.id_siswa
                              ORDER BY k.tgl_konfirmasi DESC");

// Jika query error, tampilkan pesan
if (!$query) {
    die("Query Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Konfirmasi Pembayaran - SD Mujahidin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ===== MODAL KONFIRMASI CUSTOM ===== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 20px;
            max-width: 420px;
            width: 90%;
            padding: 30px 25px 25px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: slideUp 0.3s ease;
            position: relative;
        }

        .modal-box .modal-icon {
            font-size: 52px;
            margin-bottom: 8px;
            display: inline-block;
        }

        .modal-box h3 {
            margin: 8px 0 6px;
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }

        .modal-box p {
            color: #475569;
            font-size: 15px;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .modal-actions .btn {
            padding: 10px 28px;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .modal-actions .btn-cancel {
            background: #e2e8f0;
            color: #1e293b;
        }

        .modal-actions .btn-cancel:hover {
            background: #cbd5e1;
        }

        .modal-actions .btn-confirm {
            background: #2563eb;
            color: #fff;
        }

        .modal-actions .btn-confirm:hover {
            background: #1d4ed8;
        }

        .modal-actions .btn-danger {
            background: #dc2626;
            color: #fff;
        }

        .modal-actions .btn-danger:hover {
            background: #b91c1c;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Tambahan kecil agar tombol aksi tetap rapi */
        .btn-sm {
            padding: 5px 12px;
            font-size: 13px;
        }

        .flex-gap {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
    </style>
</head>

<body>

    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>

    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-graduation-cap"></i>
            <strong>SPP SD Mujahidin</strong>
            <span>- Panel Bendahara</span>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="pembayaran.php"><i class="fas fa-money-bill-wave"></i> Catatan Pembayaran</a>
        <a href="riwayat.php"><i class="fas fa-history"></i> Riwayat Transaksi</a>
        <a href="laporan.php"><i class="fas fa-print"></i> Laporan & Cetak</a>
        <a href="cek_rapor.php"><i class="fas fa-file-alt"></i> Monitoring Rapor</a>
        <a href="broadcast_tagihan.php"><i class="fab fa-whatsapp"></i> Pengingat Tagihan</a>
        <a href="konfirmasi_pembayaran.php" class="active"><i class="fas fa-check-double"></i> Konfirmasi Bayar</a>
    </div>

    <div class="content">
        <div class="fade-in-up">
            <div class="flex-between mb-4">
                <div>
                    <h2 class="page-title"><i class="fas fa-check-double"></i> Konfirmasi Pembayaran Manual</h2>
                    <p class="text-white-80">Verifikasi bukti transfer dari orang tua siswa</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Daftar Konfirmasi</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-wrapper">
                        <?php if (mysqli_num_rows($query) > 0): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tgl Upload</th>
                                        <th>Siswa</th>
                                        <th>NIS</th>
                                        <th>Periode</th>
                                        <th>Bukti</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    while ($row = mysqli_fetch_assoc($query)):
                                        $status_class = '';
                                        $status_text = '';
                                        if ($row['status'] == 'pending') {
                                            $status_class = 'badge-warning';
                                            $status_text = '⏳ Menunggu';
                                        } elseif ($row['status'] == 'approved') {
                                            $status_class = 'badge-success';
                                            $status_text = '✅ Disetujui';
                                        } else {
                                            $status_class = 'badge-danger';
                                            $status_text = '❌ Ditolak';
                                        }
                                    ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($row['tgl_konfirmasi'])) ?></td>
                                            <td><strong><?= htmlspecialchars($row['nama_siswa']) ?></strong><br><small class="text-gray"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($row['no_wa_ortu'] ?? '-') ?></small></td>
                                            <td><?= htmlspecialchars($row['nis']) ?></td>
                                            <td><?= $row['bulan'] ?> <?= $row['tahun'] ?></td>
                                            <td>
                                                <a href="../uploads/bukti_pembayaran/<?= htmlspecialchars($row['bukti_transfer']) ?>" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> Lihat
                                                </a>
                                            </td>
                                            <td><span class="badge <?= $status_class ?>"><?= $status_text ?></span></td>
                                            <td>
                                                <?php if ($row['status'] == 'pending'): ?>
                                                    <div class="flex-gap">
                                                        <!-- Tombol Setuju (panggil modal) -->
                                                        <a href="#" class="btn btn-sm btn-success"
                                                            onclick="openModal('✅', 'Setujui Pembayaran', 'Apakah Anda yakin ingin menyetujui pembayaran ini?', 'proses_verifikasi.php?id=<?= $row['id_konfirmasi'] ?>&aksi=setuju', 'btn-confirm')">
                                                            <i class="fas fa-check"></i> Setuju
                                                        </a>
                                                        <!-- Tombol Tolak (panggil modal) -->
                                                        <a href="#" class="btn btn-sm btn-danger"
                                                            onclick="openModal('❌', 'Tolak Pembayaran', 'Apakah Anda yakin ingin menolak pembayaran ini?', 'proses_verifikasi.php?id=<?= $row['id_konfirmasi'] ?>&aksi=tolak', 'btn-danger')">
                                                            <i class="fas fa-times"></i> Tolak
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Belum ada konfirmasi pembayaran</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL KONFIRMASI ===== -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-box">
            <div class="modal-icon" id="modalIcon">⚠️</div>
            <h3 id="modalTitle">Konfirmasi</h3>
            <p id="modalMessage">Apakah Anda yakin?</p>
            <div class="modal-actions">
                <button class="btn btn-cancel" id="modalCancel">Batal</button>
                <a href="#" class="btn btn-confirm" id="modalConfirm">Ya, Lanjutkan</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // ===== MODAL CONTROLLER =====
        const modalOverlay = document.getElementById('confirmModal');
        const modalIcon = document.getElementById('modalIcon');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirmBtn = document.getElementById('modalConfirm');
        const modalCancelBtn = document.getElementById('modalCancel');

        // Fungsi buka modal
        function openModal(icon, title, message, confirmUrl, confirmClass = 'btn-confirm') {
            modalIcon.textContent = icon;
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            modalConfirmBtn.href = confirmUrl;
            modalConfirmBtn.className = 'btn ' + confirmClass; // 'btn-confirm' atau 'btn-danger'
            modalOverlay.classList.add('active');
        }

        // Tutup modal
        function closeModal() {
            modalOverlay.classList.remove('active');
        }

        // Event listener untuk tombol batal
        modalCancelBtn.addEventListener('click', closeModal);

        // Klik di luar modal (overlay) juga menutup
        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) closeModal();
        });

        // Tutup dengan tombol Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>

</html>