<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/connection.php";

$id_pengguna_login = $_SESSION['id_pengguna'];

// ============================================================
//  PROSES POST: UPLOAD BUKTI TRANSFER
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti'])) {

    $id_pembayaran = (int) $_POST['id_pembayaran'];
    $file          = $_FILES['bukti_transfer'];
    $nama_file     = $file['name'];
    $ukuran_file   = $file['size'];
    $tmp_file      = $file['tmp_name'];
    $error_file    = $file['error'];

    if ($error_file !== UPLOAD_ERR_OK) {
        $_SESSION['gagal'] = "Gagal mengunggah file. Silakan coba lagi.";
        header("Location: bookinghistory.php");
        exit();
    }

    $ekstensi_file = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
    $ekstensi_izin = ['jpg', 'jpeg', 'png'];

    if (!in_array($ekstensi_file, $ekstensi_izin)) {
        $_SESSION['gagal'] = "Format file tidak valid. Hanya jpg, jpeg, dan png yang diizinkan.";
        header("Location: bookinghistory.php");
        exit();
    }

    if ($ukuran_file > 2 * 1024 * 1024) {
        $_SESSION['gagal'] = "Ukuran file terlalu besar. Maksimal 2MB.";
        header("Location: bookinghistory.php");
        exit();
    }

    $folder_tujuan = __DIR__ . "/../uploads/bukti_transfer/";
    if (!is_dir($folder_tujuan)) {
        mkdir($folder_tujuan, 0755, true);
    }

    $nama_file_baru = time() . '_' . uniqid() . '.' . $ekstensi_file;
    $path_tujuan    = $folder_tujuan . $nama_file_baru;

    if (move_uploaded_file($tmp_file, $path_tujuan)) {
        $query_update = "
            UPDATE pembayaran p
            JOIN booking b ON p.id_booking = b.id_booking
            SET p.bukti_transfer = ?, p.status_bayar = 'pending'
            WHERE p.id_pembayaran = ? AND b.id_pengguna = ?
        ";
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->bind_param("sii", $nama_file_baru, $id_pembayaran, $id_pengguna_login);

        if ($stmt_update->execute()) {
            $_SESSION['sukses'] = "Bukti transfer berhasil diunggah! Menunggu konfirmasi admin.";
        } else {
            $_SESSION['gagal'] = "Terjadi kesalahan saat memperbarui data. Silakan coba lagi.";
        }
        $stmt_update->close();
    } else {
        $_SESSION['gagal'] = "Gagal menyimpan file ke server. Hubungi administrator.";
    }

    header("Location: bookinghistory.php");
    exit();
}

// ============================================================
//  QUERY READ: RIWAYAT BOOKING
// ============================================================

$query_booking = "
    SELECT
        b.id_booking, b.tanggal_main, b.jam_mulai, b.jam_selesai,
        b.total_harga, b.status_booking, b.created_at,
        l.nama_lapangan, l.harga_per_jam,
        p.id_pembayaran, p.metode_bayar, p.status_bayar, p.bukti_transfer
    FROM booking b
    JOIN  lapangan   l ON b.id_lapangan  = l.id_lapangan
    LEFT JOIN pembayaran p ON b.id_booking   = p.id_booking
    WHERE b.id_pengguna = ?
    ORDER BY b.created_at DESC
";

$stmt_booking = $conn->prepare($query_booking);
$stmt_booking->bind_param("i", $id_pengguna_login);
$stmt_booking->execute();
$result_booking  = $stmt_booking->get_result();
$pembayaran_list = [];
while ($row = $result_booking->fetch_assoc()) {
    $pembayaran_list[] = $row;
}
$stmt_booking->close();

$pesan_sukses = null;
if (isset($_SESSION['sukses'])) {
    $pesan_sukses = $_SESSION['sukses'];
    unset($_SESSION['sukses']);
}

$pesan_gagal = null;
if (isset($_SESSION['gagal'])) {
    $pesan_gagal = $_SESSION['gagal'];
    unset($_SESSION['gagal']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Booking — MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: #343a40; }
        .card-table { border: none; border-radius: 10px; }
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
                    <a class="nav-link active rounded p-3" href="bookinghistory.php">
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
                <h1 class="h2">Riwayat Booking</h1>
                <span class="badge bg-secondary p-2">Halo, <?= htmlspecialchars($_SESSION['nama_pengguna']) ?></span>
            </div>

            <?php if ($pesan_sukses): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($pesan_sukses) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($pesan_gagal): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($pesan_gagal) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['booking']) && $_GET['booking'] === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i> Booking berhasil dibuat! Silakan lakukan pembayaran.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card card-table border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fa-solid fa-list me-2 text-muted"></i> Daftar Transaksi Saya</h5>
                </div>
                <div class="card-body p-0">

                    <?php if (empty($pembayaran_list)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-calendar-xmark fa-2x mb-3 opacity-50"></i>
                            <p class="mb-0">Kamu belum memiliki riwayat booking.</p>
                        </div>
                    <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Lapangan</th>
                                    <th>Tgl Main</th>
                                    <th>Jam</th>
                                    <th>Total</th>
                                    <th>Status Booking</th>
                                    <th>Status Bayar</th>
                                    <th>Bukti Transfer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($pembayaran_list as $booking): ?>

                                <?php
                                    // Badge status_booking
                                    switch ($booking['status_booking']) {
                                        case 'dikonfirmasi':
                                            $cls_b = 'bg-success-subtle text-success border border-success-subtle';
                                            break;
                                        case 'selesai':
                                            $cls_b = 'bg-info-subtle text-info border border-info-subtle';
                                            break;
                                        case 'pending':
                                            $cls_b = 'bg-warning-subtle text-warning border border-warning-subtle';
                                            break;
                                        case 'dibatalkan':
                                            $cls_b = 'bg-danger-subtle text-danger border border-danger-subtle';
                                            break;
                                        default:
                                            $cls_b = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                                    }

                                    // Badge status_bayar
                                    switch ($booking['status_bayar']) {
                                        case 'lunas':
                                            $cls_p = 'bg-success-subtle text-success border border-success-subtle';
                                            break;
                                        case 'pending':
                                            $cls_p = 'bg-warning-subtle text-warning border border-warning-subtle';
                                            break;
                                        case 'gagal':
                                            $cls_p = 'bg-danger-subtle text-danger border border-danger-subtle';
                                            break;
                                        case 'belum_bayar':
                                            $cls_p = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                                            break;
                                        default:
                                            $cls_p = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                                    }
                                ?>

                                <tr>
                                    <td><strong><?= $no++ ?></strong></td>
                                    <td><?= htmlspecialchars($booking['nama_lapangan']) ?></td>
                                    <td><?= date('d M Y', strtotime($booking['tanggal_main'])) ?></td>
                                    <td>
                                        <?= substr($booking['jam_mulai'], 0, 5) ?> –
                                        <?= substr($booking['jam_selesai'], 0, 5) ?>
                                    </td>
                                    <td>Rp <?= number_format($booking['total_harga'], 0, ',', '.') ?></td>
                                    <td>
                                        <span class="badge rounded-pill px-3 py-2 text-uppercase <?= $cls_b ?>">
                                            <?= htmlspecialchars($booking['status_booking']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($booking['status_bayar']): ?>
                                            <span class="badge rounded-pill px-3 py-2 text-uppercase <?= $cls_p ?>">
                                                <?= htmlspecialchars(str_replace('_', ' ', $booking['status_bayar'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($booking['bukti_transfer'])): ?>
                                            <a href="../uploads/bukti_transfer/<?= htmlspecialchars($booking['bukti_transfer']) ?>"
                                               target="_blank" class="btn btn-outline-primary btn-sm rounded-pill">
                                                <i class="fa-solid fa-eye me-1"></i> Lihat
                                            </a>

                                        <?php elseif (!empty($booking['id_pembayaran']) && $booking['status_bayar'] === 'belum_bayar'): ?>
                                            <form action="bookinghistory.php" method="POST"
                                                  enctype="multipart/form-data"
                                                  class="d-flex align-items-center gap-2 flex-wrap">
                                                <input type="hidden" name="id_pembayaran"
                                                       value="<?= (int) $booking['id_pembayaran'] ?>">
                                                <input type="file" name="bukti_transfer"
                                                       accept=".jpg,.jpeg,.png" required
                                                       class="form-control form-control-sm" style="max-width:180px;">
                                                <button type="submit" name="upload_bukti"
                                                        class="btn btn-primary btn-sm rounded-pill">
                                                    <i class="fa-solid fa-upload me-1"></i> Kirim
                                                </button>
                                            </form>

                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>