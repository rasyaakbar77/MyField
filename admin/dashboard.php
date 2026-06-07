<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/connection.php";

$query_pendapatan = "
    SELECT COALESCE(SUM(total_harga), 0) AS total_pendapatan
    FROM booking
    WHERE status = 'Lunas'
";
$result_pendapatan = $conn->query($query_pendapatan);
$row_pendapatan    = $result_pendapatan->fetch_assoc();
$total_pendapatan  = $row_pendapatan['total_pendapatan'];

$query_lapangan = "SELECT COUNT(*) AS jumlah_lapangan FROM lapangan";
$result_lapangan = $conn->query($query_lapangan);
$row_lapangan = $result_lapangan->fetch_assoc();
$jumlah_lapangan = $row_lapangan['jumlah_lapangan'];

$query_pending = "SELECT COUNT(*) AS jumlah_pending FROM booking WHERE status = 'Menunggu Konfirmasi'";
$result_pending = $conn->query($query_pending);
$row_pending = $result_pending->fetch_assoc();
$jumlah_booking_pending = $row_pending['jumlah_pending'];

$query_customer = "SELECT COUNT(*) AS jumlah_customer FROM user WHERE role = 'customer'";
$result_customer = $conn->query($query_customer);
$row_customer = $result_customer->fetch_assoc();
$jumlah_customer = $row_customer['jumlah_customer'];

$query_recent = "
    SELECT
        b.id_booking,
        b.tanggal_booking,
        b.jam_mulai,
        b.jam_selesai,
        b.total_harga,
        b.status,
        u.nama        AS nama_customer,
        l.nama_lapangan
    FROM booking b
    JOIN user     u ON b.id_user     = u.id_user
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    ORDER BY b.id_booking DESC
    LIMIT 5
";
$result_recent  = $conn->query($query_recent);
$recent_bookings = [];
while ($row = $result_recent->fetch_assoc()) {
    $recent_bookings[] = $row;
}

$pesan_sukses = null;
if (isset($_SESSION['sukses'])) {
    $pesan_sukses = $_SESSION['sukses'];
    unset($_SESSION['sukses']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: #rgba(255,255,255,.75); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: #343a40; }
        .card-stat { border: none; border-radius: 10px; transition: transform 0.2s; }
        .card-stat:hover { transform: translateY(-5px); }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3">
            <div class="text-white text-center mb-4">
                <h4><i class="fa-solid fa-dumbbell me-2"></i>MyField</h4>
                <small class="text-muted">Admin Panel</small>
            </div>
            <hr class="text-secondary">
            <ul class="nav flex-column gap-2">
                <li class="nav-item">
                    <a class="nav-link active rounded p-3" href="dashboard.php"><i class="fa-solid fa-chart-pie me-2"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded p-3" href="field_manage.php"><i class="fa-solid fa-layer-group me-2"></i> Kelola Lapangan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded p-3" href="booking_manage.php"><i class="fa-solid fa-calendar-check me-2"></i> Kelola Booking</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded p-3" href="payment_confirm.php"><i class="fa-solid fa-credit-card me-2"></i> Konfirmasi Pembayaran</a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link rounded p-3 text-danger" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
                </li>
            </ul>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <span class="badge bg-secondary p-2">Halo, Admin</span>
            </div>

            <?php if ($pesan_sukses): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($pesan_sukses); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-5">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-stat bg-primary text-white p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1">Pendapatan</h6>
                                <h4 class="mb-0">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h4>
                            </div>
                            <i class="fa-solid fa-wallet fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-stat bg-success text-white p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1">Total Lapangan</h6>
                                <h4 class="mb-0"><?= $jumlah_lapangan; ?></h4>
                            </div>
                            <i class="fa-solid fa-table-cells fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-stat bg-warning text-dark p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1">Pending Booking</h6>
                                <h4 class="mb-0"><?= $jumlah_booking_pending; ?></h4>
                            </div>
                            <i class="fa-solid fa-clock fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card card-stat bg-info text-white p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1">Total Customer</h6>
                                <h4 class="mb-0"><?= $jumlah_customer; ?></h4>
                            </div>
                            <i class="fa-solid fa-users fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fa-solid fa-history me-2 text-muted"></i> 5 Transaksi Terbaru</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Lapangan</th>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Total Harga</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_bookings)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Belum ada data transaksi transaksi masuk.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td><strong>#<?= $booking['id_booking']; ?></strong></td>
                                            <td><?= htmlspecialchars($booking['nama_customer']); ?></td>
                                            <td><?= htmlspecialchars($booking['nama_lapangan']); ?></td>
                                            <td><?= date('d M Y', strtotime($booking['tanggal_booking'])); ?></td>
                                            <td><?= substr($booking['jam_mulai'], 0, 5); ?> - <?= substr($booking['jam_selesai'], 0, 5); ?></td>
                                            <td>Rp <?= number_format($booking['total_harga'], 0, ',', '.'); ?></td>
                                            <td>
                                                <?php if ($booking['status'] === 'Lunas' || $booking['status'] === 'Dikonfirmasi'): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">Lunas</span>
                                                <?php elseif ($booking['status'] === 'Menunggu Konfirmasi' || $booking['status'] === 'Pending'): ?>
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2">Pending</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2"><?= htmlspecialchars($booking['status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>