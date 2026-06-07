<?php
session_start();
require_once '../config/connection.php';

// Proteksi halaman
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../auth/login.php");
    exit();
}

$id_pengguna = $_SESSION['id_pengguna'];
$id_booking  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_booking === 0) {
    header("Location: bookinghistory.php");
    exit();
}

// Ambil data booking — pastikan milik customer tersebut, statusnya selesai, belum diulas
$stmt = mysqli_prepare($conn,
    "SELECT b.id_booking, b.id_lapangan, b.tanggal_main,
            b.jam_mulai, b.jam_selesai, b.total_harga,
            l.nama_lapangan, l.jenis_olahraga,
            u.id_ulasan
     FROM booking b
     JOIN lapangan l ON b.id_lapangan = l.id_lapangan
     LEFT JOIN ulasan u ON b.id_booking = u.id_booking
     WHERE b.id_booking = ? AND b.id_pengguna = ? AND b.status_booking = 'selesai'"
);
mysqli_stmt_bind_param($stmt, "ii", $id_booking, $id_pengguna);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    header("Location: bookinghistory.php");
    exit();
}

if (!is_null($booking['id_ulasan'])) {
    header("Location: bookinghistory.php");
    exit();
}

$error = '';

// Proses submit ulasan
if (isset($_POST['kirim_ulasan'])) {
    $rating   = (float) $_POST['rating'];
    $komentar = trim($_POST['komentar']);

    if ($rating < 1 || $rating > 5) {
        $error = "Rating harus antara 1 sampai 5.";
    } else {
        $stmt2 = mysqli_prepare($conn,
            "INSERT INTO ulasan (id_booking, id_lapangan, id_pengguna, rating, komentar)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt2, "iiids",
            $id_booking, $booking['id_lapangan'], $id_pengguna, $rating, $komentar
        );

        if (mysqli_stmt_execute($stmt2)) {
            header("Location: bookinghistory.php?review=success");
            exit();
        } else {
            $error = "Gagal mengirim ulasan, silakan coba lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beri Ulasan — MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: #343a40; }
        .card-review { border: none; border-radius: 10px; }

        /* Star rating interaktif */
        .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 6px; }
        .star-rating input { display: none; }
        .star-rating label {
            font-size: 2rem;
            color: #dee2e6;
            cursor: pointer;
            transition: color .15s, transform .15s;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffc107;
        }
        .star-rating label:hover { transform: scale(1.15); }
        .star-rating input:checked + label { transform: scale(1.1); }
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
                    <a class="nav-link rounded p-3" href="homepage.php">
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
                <h1 class="h2">Beri Ulasan</h1>
                <span class="badge bg-secondary p-2">Halo, <?= htmlspecialchars($_SESSION['nama_pengguna']) ?></span>
            </div>

            <!-- Info Booking -->
            <div class="card card-review shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fa-solid fa-circle-info me-2 text-muted"></i> Detail Booking</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-muted small">Lapangan</div>
                            <div class="fw-bold"><?= htmlspecialchars($booking['nama_lapangan']) ?></div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-muted small">Jenis Olahraga</div>
                            <div class="fw-bold"><?= htmlspecialchars($booking['jenis_olahraga']) ?></div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-muted small">Tanggal Main</div>
                            <div class="fw-bold"><?= date('d M Y', strtotime($booking['tanggal_main'])) ?></div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-muted small">Jam</div>
                            <div class="fw-bold">
                                <?= substr($booking['jam_mulai'], 0, 5) ?> – <?= substr($booking['jam_selesai'], 0, 5) ?>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-muted small">Total Bayar</div>
                            <div class="fw-bold text-success">Rp <?= number_format($booking['total_harga'], 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Form Ulasan -->
            <div class="card card-review shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fa-solid fa-star me-2 text-muted"></i> Form Ulasan</h5>
                </div>
                <div class="card-body">
                    <form method="POST">

                        <!-- Star Rating Interaktif -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Rating <span class="text-danger">*</span></label>
                            <div class="star-rating mb-1">
                                <input type="radio" id="star5" name="rating" value="5" required>
                                <label for="star5" title="Sangat Bagus"><i class="fa-solid fa-star"></i></label>

                                <input type="radio" id="star4" name="rating" value="4">
                                <label for="star4" title="Bagus"><i class="fa-solid fa-star"></i></label>

                                <input type="radio" id="star3" name="rating" value="3">
                                <label for="star3" title="Biasa"><i class="fa-solid fa-star"></i></label>

                                <input type="radio" id="star2" name="rating" value="2">
                                <label for="star2" title="Kurang"><i class="fa-solid fa-star"></i></label>

                                <input type="radio" id="star1" name="rating" value="1">
                                <label for="star1" title="Buruk"><i class="fa-solid fa-star"></i></label>
                            </div>
                            <div id="rating-label" class="text-muted small">Pilih rating dengan klik bintang</div>
                        </div>

                        <!-- Komentar -->
                        <div class="mb-4">
                            <label for="komentar" class="form-label fw-semibold">
                                Komentar <span class="text-muted fw-normal">(opsional)</span>
                            </label>
                            <textarea id="komentar" name="komentar" rows="4" class="form-control"
                                      placeholder="Ceritakan pengalamanmu bermain di lapangan ini..."></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="kirim_ulasan" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane me-1"></i> Kirim Ulasan
                            </button>
                            <a href="bookinghistory.php" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-xmark me-1"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Update label saat bintang diklik
    const labels = {
        '5': '⭐⭐⭐⭐⭐ — Sangat Bagus',
        '4': '⭐⭐⭐⭐ — Bagus',
        '3': '⭐⭐⭐ — Biasa',
        '2': '⭐⭐ — Kurang',
        '1': '⭐ — Buruk'
    };
    document.querySelectorAll('.star-rating input').forEach(input => {
        input.addEventListener('change', function () {
            document.getElementById('rating-label').textContent = labels[this.value] || '';
        });
    });
</script>
</body>
</html>