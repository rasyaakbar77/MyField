<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/connection.php";

// ============================================================
//  PROSES POST: TERIMA ATAU TOLAK PEMBAYARAN
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_pembayaran = (int) $_POST['id_pembayaran'];

    if (isset($_POST['aksi_terima'])) {

        // Terima: update status_bayar jadi 'lunas' dan status_booking jadi 'dikonfirmasi'
        $query_terima = "
            UPDATE pembayaran p
            JOIN booking b ON p.id_booking = b.id_booking
            SET p.status_bayar   = 'lunas',
                b.status_booking = 'dikonfirmasi'
            WHERE p.id_pembayaran = ?
        ";
        $stmt = $conn->prepare($query_terima);
        $stmt->bind_param("i", $id_pembayaran);
        if ($stmt->execute()) {
            $_SESSION['sukses'] = "Pembayaran berhasil dikonfirmasi!";
        } else {
            $_SESSION['gagal'] = "Gagal mengkonfirmasi pembayaran. Silakan coba lagi.";
        }
        $stmt->close();

    } elseif (isset($_POST['aksi_tolak'])) {

        // Tolak: update status_bayar jadi 'gagal' dan status_booking jadi 'dibatalkan'
        $query_tolak = "
            UPDATE pembayaran p
            JOIN booking b ON p.id_booking = b.id_booking
            SET p.status_bayar   = 'gagal',
                b.status_booking = 'dibatalkan'
            WHERE p.id_pembayaran = ?
        ";
        $stmt = $conn->prepare($query_tolak);
        $stmt->bind_param("i", $id_pembayaran);
        if ($stmt->execute()) {
            $_SESSION['gagal'] = "Pembayaran telah ditolak.";
        } else {
            $_SESSION['gagal'] = "Gagal menolak pembayaran. Silakan coba lagi.";
        }
        $stmt->close();
    }

    header("Location: payment_confirm.php");
    exit();
}

// ============================================================
//  QUERY READ: AMBIL DATA PEMBAYARAN PENDING
// ============================================================

$query_konfirmasi = "
    SELECT
        p.id_pembayaran,
        p.jumlah_bayar,
        p.metode_bayar,
        p.status_bayar,
        p.bukti_transfer,
        p.created_at     AS tgl_bayar,
        b.id_booking,
        b.tanggal_main,
        b.jam_mulai,
        b.jam_selesai,
        b.total_harga,
        u.nama_pengguna  AS nama_customer,
        u.no_telepon,
        l.nama_lapangan
    FROM pembayaran p
    JOIN booking  b ON p.id_booking  = b.id_booking
    JOIN pengguna u ON b.id_pengguna = u.id_pengguna
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    WHERE p.status_bayar = 'pending'
    ORDER BY p.created_at ASC
";

$result_konfirmasi = $conn->query($query_konfirmasi);
$konfirmasi_list   = [];
while ($row = $result_konfirmasi->fetch_assoc()) {
    $konfirmasi_list[] = $row;
}

// Hitung total pending untuk badge info
$total_pending = count($konfirmasi_list);

// Flash message
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
    <title>Konfirmasi Pembayaran - MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { color: #fff; background-color: #343a40; }
        .bukti-thumb {
            width: 56px; height: 56px; object-fit: cover;
            border-radius: 6px; cursor: pointer;
            border: 2px solid #dee2e6;
            transition: transform .15s;
        }
        .bukti-thumb:hover { transform: scale(1.08); border-color: #0d6efd; }
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
                    <a class="nav-link rounded p-3" href="booking_manage.php">
                        <i class="fa-solid fa-calendar-check me-2"></i> Kelola Booking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active rounded p-3" href="payment_confirm.php">
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
                <h1 class="h2">Konfirmasi Pembayaran</h1>
                <span class="badge bg-secondary p-2">
                    Halo, <?= htmlspecialchars($_SESSION['nama_pengguna']) ?>
                </span>
            </div>

            <!-- FLASH MESSAGE -->
            <?php if ($pesan_sukses): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i>
                    <?= htmlspecialchars($pesan_sukses) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($pesan_gagal): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <?= htmlspecialchars($pesan_gagal) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- INFO CARD -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-4">
                    <div class="card border-0 shadow-sm text-white bg-warning rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1 text-dark">Menunggu Konfirmasi</h6>
                                <h3 class="mb-0 text-dark"><?= $total_pending ?></h3>
                            </div>
                            <i class="fa-solid fa-clock fa-2x opacity-50 text-dark"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABEL -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-file-invoice-dollar me-2 text-muted"></i>
                        Daftar Pembayaran Pending
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Lapangan</th>
                                    <th>Jadwal Main</th>
                                    <th>Total</th>
                                    <th>Metode</th>
                                    <th>Bukti</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($konfirmasi_list)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-circle-check fa-2x mb-2 d-block text-success"></i>
                                            Tidak ada pembayaran yang menunggu konfirmasi.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($konfirmasi_list as $item): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary">#<?= $item['id_pembayaran'] ?></strong>
                                            <div class="text-muted" style="font-size:11px;">
                                                Booking #<?= $item['id_booking'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($item['nama_customer']) ?></div>
                                            <div class="text-muted" style="font-size:12px;">
                                                <?= htmlspecialchars($item['no_telepon'] ?? '-') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($item['nama_lapangan']) ?></td>
                                        <td>
                                            <div class="fw-semibold">
                                                <?= date('d M Y', strtotime($item['tanggal_main'])) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:12px;">
                                                <?= substr($item['jam_mulai'], 0, 5) ?> –
                                                <?= substr($item['jam_selesai'], 0, 5) ?>
                                            </div>
                                        </td>
                                        <td class="fw-bold text-success">
                                            Rp <?= number_format($item['jumlah_bayar'], 0, ',', '.') ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-2">
                                                <?= str_replace('_', ' ', ucfirst($item['metode_bayar'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['bukti_transfer'])): ?>
                                                <img src="../uploads/bukti_transfer/<?= htmlspecialchars($item['bukti_transfer']) ?>"
                                                     class="bukti-thumb"
                                                     alt="Bukti Transfer"
                                                     data-bs-toggle="modal"
                                                     data-bs-target="#modalBukti"
                                                     data-src="../uploads/bukti_transfer/<?= htmlspecialchars($item['bukti_transfer']) ?>"
                                                     data-nama="<?= htmlspecialchars($item['nama_customer']) ?>">
                                            <?php else: ?>
                                                <span class="text-muted fst-italic" style="font-size:12px;">Tidak ada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('Konfirmasi terima pembayaran dari <?= htmlspecialchars($item['nama_customer']) ?>?')">
                                                <input type="hidden" name="id_pembayaran" value="<?= (int) $item['id_pembayaran'] ?>">
                                                <button type="submit" name="aksi_terima"
                                                        class="btn btn-success btn-sm px-3 me-1">
                                                    <i class="fa-solid fa-check me-1"></i>Terima
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline"
                                                  onsubmit="return confirm('Yakin ingin menolak pembayaran dari <?= htmlspecialchars($item['nama_customer']) ?>?')">
                                                <input type="hidden" name="id_pembayaran" value="<?= (int) $item['id_pembayaran'] ?>">
                                                <button type="submit" name="aksi_tolak"
                                                        class="btn btn-danger btn-sm px-3">
                                                    <i class="fa-solid fa-xmark me-1"></i>Tolak
                                                </button>
                                            </form>
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

<!-- MODAL LIHAT BUKTI TRANSFER -->
<div class="modal fade" id="modalBukti" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-image me-2"></i>
                    Bukti Transfer — <span id="modalNama"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="modalGambar" src="" alt="Bukti Transfer"
                     class="img-fluid rounded" style="max-height:480px;">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Isi modal bukti transfer saat thumbnail diklik
document.querySelectorAll('.bukti-thumb').forEach(img => {
    img.addEventListener('click', function () {
        document.getElementById('modalGambar').src = this.dataset.src;
        document.getElementById('modalNama').textContent  = this.dataset.nama;
    });
});
</script>

</body>
</html>