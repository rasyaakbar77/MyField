<?php
session_start();
require_once '../config/connection.php';

// Proteksi halaman
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Filter jenis olahraga
$filter = isset($_GET['jenis']) ? trim($_GET['jenis']) : '';

// Query lapangan
if (!empty($filter)) {
    $stmt = mysqli_prepare($conn,
        "SELECT * FROM lapangan WHERE jenis_olahraga = ? ORDER BY nama_lapangan"
    );
    mysqli_stmt_bind_param($stmt, "s", $filter);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn,
        "SELECT * FROM lapangan ORDER BY jenis_olahraga, nama_lapangan"
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Lapangan — MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: #343a40; }
        .card-lapangan { border: none; border-radius: 10px; transition: transform 0.2s, box-shadow 0.2s; }
        .card-lapangan:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.1) !important; }
        .badge-olahraga { font-size: .75rem; font-weight: 600; letter-spacing: .5px; }
        .filter-btn { border-radius: 20px; font-size: .85rem; padding: .35rem .9rem; }
        .filter-btn.active { background-color: #0d6efd; color: #fff; border-color: #0d6efd; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse p-3">
            <div class="text-white text-center mb-4">
                <h4><i class="fa-solid fa-dumbbell me-2"></i>MyField</h4>
                <small class="text-muted">Customer Panel</small>
            </div>
            <hr class="text-secondary">
            <ul class="nav flex-column gap-2">
                <li class="nav-item">
                    <a class="nav-link active rounded p-3" href="homepage.php">
                        <i class="fa-solid fa-table-cells-large me-2"></i> Daftar Lapangan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded p-3" href="bookinghistory.php">
                        <i class="fa-solid fa-clock-rotate-left me-2"></i> Riwayat Booking
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link rounded p-3 text-danger" href="../auth/logout.php">
                        <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">Daftar Lapangan</h1>
                <span class="badge bg-secondary p-2">Halo, <?= htmlspecialchars($_SESSION['nama_pengguna']) ?></span>
            </div>

            <!-- Filter Pills -->
            <div class="mb-4 d-flex gap-2 flex-wrap">
                <a href="homepage.php"
                   class="btn btn-outline-primary filter-btn <?= $filter === '' ? 'active' : '' ?>">
                    <i class="fa-solid fa-border-all me-1"></i> Semua
                </a>
                <a href="homepage.php?jenis=Futsal"
                   class="btn btn-outline-primary filter-btn <?= $filter === 'Futsal' ? 'active' : '' ?>">
                    <i class="fa-solid fa-futbol me-1"></i> Futsal
                </a>
                <a href="homepage.php?jenis=Badminton"
                   class="btn btn-outline-primary filter-btn <?= $filter === 'Badminton' ? 'active' : '' ?>">
                    <i class="fa-solid fa-shuttlecock me-1"></i> Badminton
                </a>
                <a href="homepage.php?jenis=Basket"
                   class="btn btn-outline-primary filter-btn <?= $filter === 'Basket' ? 'active' : '' ?>">
                    <i class="fa-solid fa-basketball me-1"></i> Basket
                </a>
            </div>

            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="row g-4">
                    <?php while ($lapangan = mysqli_fetch_assoc($result)): ?>
                    <div class="col-12 col-sm-6 col-xl-4">
                        <div class="card card-lapangan shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0 fw-bold"><?= htmlspecialchars($lapangan['nama_lapangan']) ?></h5>
                                    <?php if ($lapangan['status'] === 'tersedia'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle badge-olahraga rounded-pill px-2">Tersedia</span>
                                    <?php elseif ($lapangan['status'] === 'pemeliharaan'): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle badge-olahraga rounded-pill px-2">Pemeliharaan</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle badge-olahraga rounded-pill px-2">Tidak Tersedia</span>
                                    <?php endif; ?>
                                </div>

                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle badge-olahraga mb-3">
                                    <i class="fa-solid fa-tag me-1"></i><?= htmlspecialchars($lapangan['jenis_olahraga']) ?>
                                </span>

                                <p class="text-muted small mb-3"><?= htmlspecialchars($lapangan['deskripsi']) ?></p>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="text-muted small">Harga / Jam</span>
                                        <div class="fw-bold text-dark">Rp <?= number_format($lapangan['harga_per_jam'], 0, ',', '.') ?></div>
                                    </div>
                                    <?php if ($lapangan['status'] === 'tersedia'): ?>
                                        <a href="bookingform.php?id=<?= $lapangan['id_lapangan'] ?>"
                                           class="btn btn-primary btn-sm rounded-pill px-3">
                                            <i class="fa-solid fa-calendar-plus me-1"></i> Booking
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm rounded-pill px-3" disabled>
                                            <i class="fa-solid fa-ban me-1"></i> Tidak Tersedia
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="fa-solid fa-circle-exclamation fa-2x mb-3 opacity-50"></i>
                        <p class="mb-0">Tidak ada lapangan untuk jenis olahraga ini.</p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>