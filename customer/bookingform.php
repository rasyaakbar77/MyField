<?php
session_start();
require_once '../config/connection.php';

// Proteksi halaman
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil id_lapangan dari URL
$id_lapangan = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_lapangan === 0) {
    header("Location: homepage.php");
    exit();
}

// Ambil data lapangan
$stmt = mysqli_prepare($conn, "SELECT * FROM lapangan WHERE id_lapangan = ? AND status = 'tersedia'");
mysqli_stmt_bind_param($stmt, "i", $id_lapangan);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$lapangan = mysqli_fetch_assoc($result);

if (!$lapangan) {
    header("Location: homepage.php");
    exit();
}

$error   = '';
$success = '';

// Ambil slot yang sudah terisi untuk tanggal yang dipilih
$slots_terisi    = [];
$tanggal_dipilih = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';

if (!empty($tanggal_dipilih)) {
    $stmt2 = mysqli_prepare($conn,
        "SELECT jam_mulai, jam_selesai FROM jadwal_lapangan
         WHERE id_lapangan = ? AND tanggal = ? AND status_slot = 'booked'"
    );
    mysqli_stmt_bind_param($stmt2, "is", $id_lapangan, $tanggal_dipilih);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    while ($row = mysqli_fetch_assoc($result2)) {
        $slots_terisi[] = $row['jam_mulai'] . '-' . $row['jam_selesai'];
    }
}

// Generate slot jam: 08:00 - 22:00, per 1 jam
$semua_slot = [];
for ($jam = 8; $jam < 22; $jam++) {
    $mulai   = sprintf('%02d:00:00', $jam);
    $selesai = sprintf('%02d:00:00', $jam + 1);
    $semua_slot[] = [
        'mulai'  => $mulai,
        'selesai' => $selesai,
        'label'  => sprintf('%02d:00 - %02d:00', $jam, $jam + 1),
        'booked' => in_array($mulai . '-' . $selesai, $slots_terisi)
    ];
}

// Proses booking
if (isset($_POST['booking'])) {
    $tanggal     = trim($_POST['tanggal']);
    $jam_mulai   = trim($_POST['jam_mulai']);
    $jam_selesai = trim($_POST['jam_selesai']);
    $id_pengguna = $_SESSION['id_pengguna'];

    if (empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) {
        $error = "Semua data harus diisi.";
    } elseif ($tanggal < date('Y-m-d')) {
        $error = "Tidak bisa booking untuk tanggal yang sudah lewat.";
    } elseif ($jam_mulai >= $jam_selesai) {
        $error = "Jam selesai harus lebih besar dari jam mulai.";
    } else {
        $stmt3 = mysqli_prepare($conn,
            "SELECT id_jadwal_lapangan FROM jadwal_lapangan
             WHERE id_lapangan = ? AND tanggal = ? AND status_slot = 'booked'
             AND NOT (jam_selesai <= ? OR jam_mulai >= ?)"
        );
        mysqli_stmt_bind_param($stmt3, "isss", $id_lapangan, $tanggal, $jam_mulai, $jam_selesai);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_store_result($stmt3);

        if (mysqli_stmt_num_rows($stmt3) > 0) {
            $error = "Slot waktu tersebut sudah dibooking orang lain.";
        } else {
            $jam_main    = (strtotime($jam_selesai) - strtotime($jam_mulai)) / 3600;
            $total_harga = $jam_main * $lapangan['harga_per_jam'];

            $stmt4 = mysqli_prepare($conn,
                "INSERT INTO booking (id_pengguna, id_lapangan, tanggal_main, jam_mulai, jam_selesai, total_harga, status_booking)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending')"
            );
            mysqli_stmt_bind_param($stmt4, "iisssd", $id_pengguna, $id_lapangan, $tanggal, $jam_mulai, $jam_selesai, $total_harga);

            if (mysqli_stmt_execute($stmt4)) {
                $id_booking_baru = mysqli_insert_id($conn);
                $stmt5 = mysqli_prepare($conn,
                    "INSERT INTO jadwal_lapangan (id_lapangan, tanggal, jam_mulai, jam_selesai, status_slot)
                     VALUES (?, ?, ?, ?, 'booked')"
                );
                mysqli_stmt_bind_param($stmt5, "isss", $id_lapangan, $tanggal, $jam_mulai, $jam_selesai);
                mysqli_stmt_execute($stmt5);
                header("Location: bookinghistory.php?booking=success");
                exit();
            } else {
                $error = "Booking gagal, silakan coba lagi.";
            }
        }
        mysqli_stmt_close($stmt3);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking — <?= htmlspecialchars($lapangan['nama_lapangan']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: #343a40; }
        .slot-badge { font-size: .8rem; padding: .35rem .7rem; border-radius: 20px; display: inline-block; }
        .slot-available { background: #d1e7dd; color: #0a3622; border: 1px solid #a3cfbb; }
        .slot-booked    { background: #f8d7da; color: #58151c; border: 1px solid #f1aeb5; }
        .card-info { border: none; border-radius: 10px; }
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
                <h1 class="h2">Form Booking</h1>
                <span class="badge bg-secondary p-2">Halo, <?= htmlspecialchars($_SESSION['nama_pengguna']) ?></span>
            </div>

            <!-- Info Lapangan -->
            <div class="card card-info shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fa-solid fa-circle-info me-2 text-muted"></i> Info Lapangan</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-muted small">Nama Lapangan</div>
                            <div class="fw-bold"><?= htmlspecialchars($lapangan['nama_lapangan']) ?></div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-muted small">Jenis Olahraga</div>
                            <div class="fw-bold"><?= htmlspecialchars($lapangan['jenis_olahraga']) ?></div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-muted small">Harga / Jam</div>
                            <div class="fw-bold text-success">Rp <?= number_format($lapangan['harga_per_jam'], 0, ',', '.') ?></div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="text-muted small">Deskripsi</div>
                            <div><?= htmlspecialchars($lapangan['deskripsi']) ?></div>
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

            <!-- Step 1: Pilih Tanggal -->
            <div class="card card-info shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <span class="badge bg-primary rounded-circle me-2">1</span>
                        Pilih Tanggal
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="d-flex align-items-end gap-3 flex-wrap">
                        <input type="hidden" name="id" value="<?= $id_lapangan ?>">
                        <div>
                            <label class="form-label text-muted small">Tanggal Main</label>
                            <input type="date" name="tanggal" class="form-control"
                                   value="<?= htmlspecialchars($tanggal_dipilih) ?>"
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Lihat Slot Tersedia
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($tanggal_dipilih)): ?>

            <!-- Step 2: Ketersediaan Slot -->
            <div class="card card-info shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <span class="badge bg-primary rounded-circle me-2">2</span>
                        Ketersediaan Slot — <?= htmlspecialchars($tanggal_dipilih) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($semua_slot as $slot): ?>
                            <span class="slot-badge <?= $slot['booked'] ? 'slot-booked' : 'slot-available' ?>">
                                <i class="fa-solid <?= $slot['booked'] ? 'fa-lock' : 'fa-circle-check' ?> me-1"></i>
                                <?= $slot['label'] ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 d-flex gap-3 small text-muted">
                        <span><i class="fa-solid fa-circle-check text-success me-1"></i> Tersedia</span>
                        <span><i class="fa-solid fa-lock text-danger me-1"></i> Sudah Dibooking</span>
                    </div>
                </div>
            </div>

            <!-- Step 3: Form Booking -->
            <div class="card card-info shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <span class="badge bg-primary rounded-circle me-2">3</span>
                        Isi Detail Booking
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal_dipilih) ?>">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">Jam Mulai</label>
                                <select name="jam_mulai" class="form-select" required>
                                    <option value="">-- Pilih Jam Mulai --</option>
                                    <?php foreach ($semua_slot as $slot): ?>
                                        <?php if (!$slot['booked']): ?>
                                            <option value="<?= $slot['mulai'] ?>">
                                                <?= substr($slot['mulai'], 0, 5) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Jam Selesai</label>
                                <select name="jam_selesai" class="form-select" required>
                                    <option value="">-- Pilih Jam Selesai --</option>
                                    <?php foreach ($semua_slot as $slot): ?>
                                        <?php if (!$slot['booked']): ?>
                                            <option value="<?= $slot['selesai'] ?>">
                                                <?= substr($slot['selesai'], 0, 5) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <p class="text-muted small mt-3">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Total harga dihitung otomatis: durasi × Rp <?= number_format($lapangan['harga_per_jam'], 0, ',', '.') ?>/jam
                        </p>
                        <div class="d-flex gap-2 mt-2">
                            <button type="submit" name="booking" class="btn btn-primary">
                                <i class="fa-solid fa-calendar-check me-1"></i> Konfirmasi Booking
                            </button>
                            <a href="homepage.php" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-arrow-left me-1"></i> Kembali
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <?php endif; ?>

            <?php if (empty($tanggal_dipilih)): ?>
            <div class="mt-2">
                <a href="homepage.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar Lapangan
                </a>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>