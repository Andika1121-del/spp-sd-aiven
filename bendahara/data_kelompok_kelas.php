<?php
include '../koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========== PAGINATION & SEARCH ==========
$limit = 50;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query dasar (hanya alumni id_kelas = 40)
$where = "WHERE id_kelas = 40";
if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($koneksi, $search);
    $where .= " AND (nama_siswa LIKE '%$search_esc%' OR nis LIKE '%$search_esc%')";
}

// Hitung total data (untuk pagination)
$count_query = "SELECT COUNT(*) AS total FROM siswa $where";
$count_result = mysqli_query($koneksi, $count_query);
$total_row = mysqli_fetch_assoc($count_result);
$total_data = $total_row['total'];
$total_pages = ceil($total_data / $limit);

// Ambil data dengan limit
$query_alumni = "SELECT * FROM siswa $where ORDER BY nama_siswa ASC LIMIT $limit OFFSET $offset";
$result_alumni = mysqli_query($koneksi, $query_alumni);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Alumni - SD Mujahidin</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body style="background-color: var(--light, #f8fafc); padding: 20px;">

    <div class="container-fluid mt-3">
        <div class="card shadow border-0 mb-4" style="border-radius: var(--radius-md, 16px); overflow: hidden;">

            <div class="card-header text-white py-3 d-flex justify-content-between align-items-center flex-wrap" style="background: var(--primary-gradient, linear-gradient(135deg, #064e3b 0%, #0a5c45 100%)); border: none;">
                <h4 class="mb-0 font-weight-bold">
                    <i class="fas fa-graduation-cap mr-2"></i> Daftar Data Alumni
                </h4>
                <a href="dashboard.php" class="btn btn-sm btn-light font-weight-bold" style="border-radius: var(--radius, 12px); color: var(--primary-dark, #056643);">
                    <i class="fas fa-arrow-left mr-1"></i> Dashboard
                </a>
            </div>

            <div class="card-body" style="background-color: var(--card, #ffffff);">

                <!-- ===== FORM PENCARIAN ===== -->
                <div class="row mb-3">
                    <div class="col-md-8">
                        <form method="GET" class="form-inline">
                            <div class="input-group w-100">
                                <input type="text" name="search" class="form-control" placeholder="Cari Nama atau NIS..." value="<?= htmlspecialchars($search) ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Cari</button>
                                </div>
                                <?php if (!empty($search)): ?>
                                    <a href="data_alumni.php" class="btn btn-outline-secondary ml-2"><i class="fas fa-times"></i> Reset</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4 text-md-right">
                        <span class="text-muted">Total Alumni: <strong><?= $total_data ?></strong></span>
                    </div>
                </div>

                <!-- ===== TABEL DATA ===== -->
                <div class="table-responsive mt-2">
                    <table class="table table-hover" style="border-color: var(--border, #e2e8f0);">
                        <thead style="background-color: var(--light, #f8fafc); color: var(--dark, #1e293b);">
                            <tr class="text-center">
                                <th width="5%">No</th>
                                <th width="15%">NIS</th>
                                <th>Nama Lengkap</th>
                                <th width="10%">JK</th>
                                <th>No. WA Orang Tua</th>
                                <th width="12%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody style="color: var(--dark, #1e293b);">
                            <?php
                            if (mysqli_num_rows($result_alumni) > 0) {
                                $no = $offset + 1;
                                while ($row = mysqli_fetch_assoc($result_alumni)) {
                            ?>
                                    <tr class="text-center" style="border-bottom: 1px solid var(--border, #e2e8f0);">
                                        <td><?= $no; ?></td>
                                        <td><?= htmlspecialchars($row['nis']); ?></td>
                                        <td class="text-left font-weight-bold"><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                        <td><?= htmlspecialchars($row['jk']); ?></td>
                                        <td><?= ($row['no_wa_ortu']) ? htmlspecialchars($row['no_wa_ortu']) : '-'; ?></td>
                                        <td>
                                            <a href="detail_siswa.php?id=<?= $row['id_siswa']; ?>" class="btn btn-sm text-white" style="background-color: var(--info, #3b82f6); border-radius: var(--radius, 12px);" title="Lihat Detail">
                                                <i class="fas fa-eye mr-1"></i> Lihat
                                            </a>
                                        </td>
                                    </tr>
                            <?php
                                    $no++;
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center py-5' style='color: var(--gray, #64748b);'>Data tidak ditemukan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- ===== PAGINATION ===== -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

                <!-- ===== INFO JUMLAH DATA ===== -->
                <div class="text-muted small mt-2">
                    Menampilkan <?= min($total_data, ($offset + 1)) ?> - <?= min($offset + $limit, $total_data) ?> dari total <?= $total_data ?> data.
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>