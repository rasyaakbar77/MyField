<?php
include '../config/connection.php';
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'pemilik'])) {
    header("Location: ../auth/login.php");
    exit();
}

$success = '';
$error = '';

// ── UPDATE STATUS BOOKING ──────────────────────────────────────────────────
if (isset($_POST['update_status'])) {
    $id_booking     = (int) $_POST['id_booking'];
    $status_baru    = $_POST['status_booking'];
    $allowed_status = ['pending', 'dikonfirmasi', 'selesai', 'dibatalkan'];

    if (in_array($status_baru, $allowed_status)) {
        $query = "UPDATE booking SET status_booking = '$status_baru' WHERE id_booking = $id_booking";
        if (mysqli_query($conn, $query)) {
            if ($status_baru === 'dibatalkan') {
                $get_booking = mysqli_query($conn, "SELECT * FROM booking WHERE id_booking = $id_booking");
                $booking_data = mysqli_fetch_assoc($get_booking);
                $update_jadwal = "UPDATE jadwal_lapangan
                                  SET status_slot = 'kosong'
                                  WHERE id_lapangan = {$booking_data['id_lapangan']}
                                  AND tanggal = '{$booking_data['tanggal_main']}'
                                  AND jam_mulai = '{$booking_data['jam_mulai']}'
                                  AND jam_selesai = '{$booking_data['jam_selesai']}'";
                mysqli_query($conn, $update_jadwal);
            }
            $success = "Status booking #$id_booking berhasil diperbarui.";
        } else {
            $error = "Gagal memperbarui status: " . mysqli_error($conn);
        }
    }
}

// ── HAPUS BOOKING ──────────────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $id_booking = (int) $_GET['hapus'];
    $query = "DELETE FROM booking WHERE id_booking = $id_booking";
    if (mysqli_query($conn, $query)) {
        $success = "Booking #$id_booking berhasil dihapus.";
    } else {
        $error = "Gagal menghapus: " . mysqli_error($conn);
    }
}

// ── FILTER & PENCARIAN ─────────────────────────────────────────────────────
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search        = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = "WHERE 1=1";
if ($filter_status !== '') {
    $where .= " AND b.status_booking = '$filter_status'";
}
if ($search !== '') {
    $where .= " AND (p.nama_pengguna LIKE '%$search%' OR l.nama_lapangan LIKE '%$search%')";
}

// ── AMBIL DATA BOOKING ─────────────────────────────────────────────────────
$query = "SELECT
            b.id_booking,
            p.nama_pengguna,
            l.nama_lapangan,
            l.jenis_olahraga,
            b.tanggal_main,
            b.jam_mulai,
            b.jam_selesai,
            b.total_harga,
            b.status_booking,
            b.created_at,
            pay.status_bayar,
            pay.metode_bayar
          FROM booking b
          JOIN pengguna p ON b.id_pengguna = p.id_pengguna
          JOIN lapangan l ON b.id_lapangan = l.id_lapangan
          LEFT JOIN pembayaran pay ON b.id_booking = pay.id_booking
          $where
          ORDER BY b.created_at DESC";

$result = mysqli_query($conn, $query);

// ── HITUNG STATISTIK ───────────────────────────────────────────────────────
$stat_query = mysqli_query($conn, "SELECT status_booking, COUNT(*) as total FROM booking GROUP BY status_booking");
$stats = ['pending' => 0, 'dikonfirmasi' => 0, 'selesai' => 0, 'dibatalkan' => 0];
while ($s = mysqli_fetch_assoc($stat_query)) {
    $stats[$s['status_booking']] = $s['total'];
}
$total_all = array_sum($stats);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking — MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { color: #fff; background-color: #343a40; }
        .card-stat { border: none; border-radius: 10px; transition: transform 0.2s; }
        .card-stat:hover { transform: translateY(-4px); }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3">
            <div class="text-white text-center mb-4">
                <h4><i class="fa-solid fa-dumbbell me-2"></i>MyField</h4>
                <small class="text-muted">Admin Panel</small>
            </div>
            <hr class="text-secondary">
            <ul class="nav flex-column gap-2">
                <li class="nav-item">
                    <a class="nav-link rounded p-3" href="dashboard.php">
                        <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded p-3" href="field_manage.php">
                        <i class="fa-solid fa-layer-group me-2"></i> Kelola Lapangan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active rounded p-3" href="booking_manage.php">
                        <i class="fa-solid fa-calendar-check me-2"></i> Kelola Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded p-3" href="payment_confirm.php">
                        <i class="fa-solid fa-credit-card me-2"></i> Konfirmasi Pembayaran
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link rounded p-3 text-danger" href="../auth/logout.php">
                        <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- MAIN CONTENT -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">Kelola Booking</h1>
                <span class="badge bg-secondary p-2">
                    Halo, <?= htmlspecialchars($_SESSION['nama_pengguna']) ?>
                </span>
            </div>

            <!-- ALERT -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fa-solid fa-circle-check me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- STATISTIK -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="card card-stat p-3 bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1" style="font-size:11px;">Total Booking</h6>
                                <h3 class="mb-0"><?= $total_all ?></h3>
                            </div>
                            <i class="fa-solid fa-list fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card card-stat p-3 bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1" style="font-size:11px;">Pending</h6>
                                <h3 class="mb-0"><?= $stats['pending'] ?></h3>
                            </div>
                            <i class="fa-solid fa-clock fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card card-stat p-3 bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1" style="font-size:11px;">Dikonfirmasi</h6>
                                <h3 class="mb-0"><?= $stats['dikonfirmasi'] ?></h3>
                            </div>
                            <i class="fa-solid fa-circle-check fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card card-stat p-3 bg-danger text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1" style="font-size:11px;">Dibatalkan</h6>
                                <h3 class="mb-0"><?= $stats['dibatalkan'] ?></h3>
                            </div>
                            <i class="fa-solid fa-ban fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILTER & SEARCH -->
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-12 col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fa-solid fa-magnifying-glass text-muted"></i>
                                </span>
                                <input type="text" name="search" class="form-control"
                                       placeholder="Cari nama pelanggan atau lapangan..."
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="pending"      <?= $filter_status==='pending'      ?'selected':'' ?>>Pending</option>
                                <option value="dikonfirmasi" <?= $filter_status==='dikonfirmasi' ?'selected':'' ?>>Dikonfirmasi</option>
                                <option value="selesai"      <?= $filter_status==='selesai'      ?'selected':'' ?>>Selesai</option>
                                <option value="dibatalkan"   <?= $filter_status==='dibatalkan'   ?'selected':'' ?>>Dibatalkan</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-auto d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-search me-1"></i>Cari
                            </button>
                            <?php if ($search || $filter_status): ?>
                                <a href="booking_manage.php" class="btn btn-outline-secondary">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TABEL BOOKING -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-table me-2 text-muted"></i>
                        Data Booking
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#ID</th>
                                    <th>Pelanggan</th>
                                    <th>Lapangan</th>
                                    <th>Jadwal</th>
                                    <th>Total</th>
                                    <th>Pembayaran</th>
                                    <th>Status Booking</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <?php
                                    // Badge status booking
                                    $bs_map = [
                                        'pending'      => 'warning',
                                        'dikonfirmasi' => 'success',
                                        'selesai'      => 'primary',
                                        'dibatalkan'   => 'danger',
                                    ];
                                    $bs_color = $bs_map[$row['status_booking']] ?? 'secondary';

                                    // Badge status bayar
                                    $sb = $row['status_bayar'] ?? 'belum_bayar';
                                    $sb_map   = ['belum_bayar' => 'secondary', 'pending' => 'warning', 'lunas' => 'success', 'gagal' => 'danger'];
                                    $sb_label = ['belum_bayar' => 'Belum Bayar', 'pending' => 'Menunggu', 'lunas' => 'Lunas', 'gagal' => 'Gagal'];
                                    $sb_color = $sb_map[$sb] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><strong class="text-primary">#<?= $row['id_booking'] ?></strong></td>
                                    <td><?= htmlspecialchars($row['nama_pengguna']) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($row['nama_lapangan']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($row['jenis_olahraga']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= date('d M Y', strtotime($row['tanggal_main'])) ?></div>
                                        <small class="text-muted">
                                            <?= substr($row['jam_mulai'],0,5) ?> – <?= substr($row['jam_selesai'],0,5) ?>
                                        </small>
                                    </td>
                                    <td class="fw-bold text-success">
                                        Rp <?= number_format($row['total_harga'], 0, ',', '.') ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $sb_color ?>-subtle text-<?= $sb_color ?> border border-<?= $sb_color ?>-subtle rounded-pill px-2 py-1">
                                            <?= $sb_label[$sb] ?? $sb ?>
                                        </span>
                                        <?php if ($row['metode_bayar']): ?>
                                            <div class="text-muted" style="font-size:11px;margin-top:2px;">
                                                <?= str_replace('_', ' ', ucfirst($row['metode_bayar'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $bs_color ?>-subtle text-<?= $bs_color ?> border border-<?= $bs_color ?>-subtle rounded-pill px-2 py-1">
                                            <?= ucfirst($row['status_booking']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 align-items-center flex-wrap">
                                            <!-- Form ubah status -->
                                            <form method="POST" class="d-flex gap-1 align-items-center">
                                                <input type="hidden" name="id_booking" value="<?= $row['id_booking'] ?>">
                                                <select name="status_booking" class="form-select form-select-sm" style="width:130px;">
                                                    <option value="pending"      <?= $row['status_booking']==='pending'      ?'selected':'' ?>>Pending</option>
                                                    <option value="dikonfirmasi" <?= $row['status_booking']==='dikonfirmasi' ?'selected':'' ?>>Konfirmasi</option>
                                                    <option value="selesai"      <?= $row['status_booking']==='selesai'      ?'selected':'' ?>>Selesai</option>
                                                    <option value="dibatalkan"   <?= $row['status_booking']==='dibatalkan'   ?'selected':'' ?>>Batalkan</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                                    <i class="fa-solid fa-floppy-disk"></i>
                                                </button>
                                            </form>
                                            <!-- Tombol hapus -->
                                            <button class="btn btn-danger btn-sm"
                                                    onclick="confirmHapus(<?= $row['id_booking'] ?>, '<?= htmlspecialchars($row['nama_pengguna']) ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-calendar-xmark fa-2x mb-2 d-block"></i>
                                        Tidak ada data booking<?= $search || $filter_status ? ' yang sesuai filter' : '' ?>.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-trash me-2 text-danger"></i>Hapus Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                Apakah kamu yakin ingin menghapus booking ini?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="btnHapusConfirm" class="btn btn-danger">Ya, Hapus</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmHapus(id, nama) {
    document.getElementById('modalBody').innerHTML =
        `Yakin ingin menghapus booking <strong>#${id}</strong> milik <strong>${nama}</strong>? Tindakan ini tidak dapat dibatalkan.`;
    document.getElementById('btnHapusConfirm').href = `booking_manage.php?hapus=${id}`;
    new bootstrap.Modal(document.getElementById('modalHapus')).show();
}
</script>

</body>
</html>