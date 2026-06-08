<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/connection.php";

$id_lapangan = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_lapangan === 0) {
    header("Location: homepage.php");
    exit();
}

// 1. Ambil Data Detail Lapangan
$query_lapangan = "SELECT * FROM lapangan WHERE id_lapangan = ?";
$stmt = $conn->prepare($query_lapangan);
$stmt->bind_param("i", $id_lapangan);
$stmt->execute();
$lapangan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lapangan) {
    header("Location: homepage.php");
    exit();
}

// 2. Ambil Data Semua Ulasan untuk Lapangan ini
$query_ulasan = "
    SELECT u.rating, u.komentar, p.nama_pengguna 
    FROM ulasan u 
    JOIN pengguna p ON u.id_pengguna = p.id_pengguna 
    WHERE u.id_lapangan = ? 
    ORDER BY u.id_ulasan DESC
";
$stmt_ulasan = $conn->prepare($query_ulasan);
$stmt_ulasan->bind_param("i", $id_lapangan);
$stmt_ulasan->execute();
$result_ulasan = $stmt_ulasan->get_result();

$ulasan_list = [];
$total_rating = 0;
while ($row = $result_ulasan->fetch_assoc()) {
    $ulasan_list[] = $row;
    $total_rating += $row['rating'];
}
$stmt_ulasan->close();

$jumlah_ulasan = count($ulasan_list);
$rata_rata_rating = $jumlah_ulasan > 0 ? round($total_rating / $jumlah_ulasan, 1) : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lapangan['nama_lapangan']) ?> — MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: #343a40; }
        .star-rating { color: #ffc107; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

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

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">Detail Lapangan</h1>
                <a href="homepage.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Katalog
                </a>
            </div>

            <div class="row g-4">
                <div class="col-12 col-lg-7">
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="bg-light text-center py-5 rounded-top" style="border-bottom: 1px solid #dee2e6;">
                            <i class="fa-solid fa-image fa-4x text-secondary opacity-50 mb-3"></i>
                            <h5 class="text-secondary">Foto Lapangan</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h2 class="fw-bold mb-1"><?= htmlspecialchars($lapangan['nama_lapangan']) ?></h2>
                                    <span class="badge bg-primary-subtle text-primary fs-6"><?= htmlspecialchars($lapangan['jenis_olahraga']) ?></span>
                                </div>
                                <div class="text-end">
                                    <h3 class="text-success fw-bold mb-0">Rp <?= number_format($lapangan['harga_per_jam'], 0, ',', '.') ?></h3>
                                    <small class="text-muted">per jam</small>
                                </div>
                            </div>

                            <hr>

                            <h5 class="fw-bold mt-4"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Deskripsi</h5>
                            <p class="text-muted lh-lg"><?= nl2br(htmlspecialchars($lapangan['deskripsi'])) ?></p>

                            <div class="mt-4 pt-3 border-top">
                                <?php if ($lapangan['status'] === 'tersedia'): ?>
                                    <a href="bookingform.php?id=<?= $lapangan['id_lapangan'] ?>" class="btn btn-primary btn-lg w-100 rounded-pill shadow-sm fw-bold">
                                        <i class="fa-solid fa-calendar-check me-2"></i> Pesan Jadwal Sekarang
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-lg w-100 rounded-pill fw-bold" disabled>
                                        <i class="fa-solid fa-ban me-2"></i> Saat ini <?= str_replace('_', ' ', $lapangan['status']) ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-comments me-2 text-warning"></i> Ulasan Pengguna</h5>
                            <span class="badge bg-light text-dark border"><?= $jumlah_ulasan ?> Ulasan</span>
                        </div>
                        <div class="card-body p-0">
                            
                            <div class="bg-light p-4 text-center border-bottom">
                                <h1 class="display-3 fw-bold mb-0 text-dark"><?= $rata_rata_rating ?></h1>
                                <div class="star-rating fs-4 mb-2">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-solid fa-star <?= $i <= round($rata_rata_rating) ? 'text-warning' : 'text-secondary opacity-25' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-muted small mb-0">Berdasarkan dari <?= $jumlah_ulasan ?> pengguna</p>
                            </div>

                            <div class="p-4" style="max-height: 500px; overflow-y: auto;">
                                <?php if ($jumlah_ulasan === 0): ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fa-regular fa-comment-dots fa-2x mb-2 opacity-50"></i>
                                        <p class="mb-0 small">Belum ada ulasan untuk lapangan ini.<br>Jadilah yang pertama mencoba!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($ulasan_list as $ulasan): ?>
                                        <div class="mb-4 pb-3 border-bottom">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong class="text-dark"><i class="fa-regular fa-user-circle me-1"></i> <?= htmlspecialchars($ulasan['nama_pengguna']) ?></strong>
                                                <div class="star-rating small">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fa-solid fa-star <?= $i <= $ulasan['rating'] ? 'text-warning' : 'text-secondary opacity-25' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <p class="text-muted small mb-0 fst-italic">"<?= htmlspecialchars($ulasan['komentar']) ?>"</p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>