<?php
session_start();

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../auth/login.php");
    exit();
}

include "../config/connection.php";

$id_pengguna_login = $_SESSION['id_pengguna'];
$id_booking = isset($_GET['id_booking']) ? (int)$_GET['id_booking'] : 0;

// Validasi data booking & kepemilikan data pengguna
$query_booking = "
    SELECT b.*, l.nama_lapangan, p.id_pembayaran, p.status_bayar
    FROM booking b
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    LEFT JOIN pembayaran p ON b.id_booking = p.id_booking
    WHERE b.id_booking = ? AND b.id_pengguna = ?
";
$stmt = $conn->prepare($query_booking);
$stmt->bind_param("ii", $id_booking, $id_pengguna_login);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Proteksi halaman: Izinkan masuk jika status dikonfirmasi DAN belum lunas/pending
if (!$booking || $booking['status_booking'] !== 'dikonfirmasi' || (!empty($booking['status_bayar']) && $booking['status_bayar'] !== 'belum_bayar')) {
    header("Location: bookinghistory.php");
    exit();
}

// ============================================================
//  PROSES POST: UPLOAD BUKTI TRANSFER
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti'])) {

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
        
        // FIX LOGIKA: Jika baris data pembayaran belum ada sama sekali di database, lakukan INSERT
        if (empty($booking['id_pembayaran'])) {
            $query_pembayaran = "
                INSERT INTO pembayaran (id_booking, metode_bayar, jumlah_bayar, bukti_transfer, status_bayar)
                VALUES (?, 'transfer', ?, ?, 'pending')
            ";
            $stmt_action = $conn->prepare($query_pembayaran);
            $stmt_action->bind_param("ids", $id_booking, $booking['total_harga'], $nama_file_baru);
        } else {
            // Jika baris data pembayaran sudah ada (misal dari submit gagal sebelumnya), lakukan UPDATE
            $id_pembayaran = (int)$booking['id_pembayaran'];
            $query_pembayaran = "
                UPDATE pembayaran 
                SET bukti_transfer = ?, status_bayar = 'pending'
                WHERE id_pembayaran = ?
            ";
            $stmt_action = $conn->prepare($query_pembayaran);
            $stmt_action->bind_param("si", $nama_file_baru, $id_pembayaran);
        }

        if ($stmt_action->execute()) {
            $_SESSION['sukses'] = "Bukti transfer berhasil diunggah! Menunggu konfirmasi admin.";
        } else {
            $_SESSION['gagal'] = "Terjadi kesalahan database saat memproses pembayaran.";
        }
        $stmt_action->close();

    } else {
        $_SESSION['gagal'] = "Gagal menyimpan file ke server.";
    }

    header("Location: bookinghistory.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Booking — MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background-color: #212529; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: #343a40; }
        .card-custom { border: none; border-radius: 10px; }
        .bank-box { background: #f1f3f5; border-radius: 8px; padding: 15px; border-left: 5px solid #0d6efd; }
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

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2">Form Pembayaran</h1>
                <a href="bookinghistory.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali
                </a>
            </div>

            <div class="row g-4">
                <div class="col-12 col-lg-7">
                    <div class="card card-custom shadow-sm p-4 mb-4 bg-white">
                        <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Ringkasan Tagihan</h5>
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted ps-0">Nama Lapangan</td>
                                <td class="fw-bold text-end"><?= htmlspecialchars($booking['nama_lapangan']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Tanggal Main</td>
                                <td class="fw-bold text-end"><?= date('d F Y', strtotime($booking['tanggal_main'])) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Waktu / Jam</td>
                                <td class="fw-bold text-end"><?= substr($booking['jam_mulai'], 0, 5) ?> - <?= substr($booking['jam_selesai'], 0, 5) ?> WIB</td>
                            </tr>
                            <tr class="border-top">
                                <td class="text-dark fw-bold ps-0 pt-3">Total Transfer</td>
                                <td class="text-primary fw-bold fs-4 text-end pt-2">Rp <?= number_format($booking['total_harga'], 0, ',', '.') ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="card card-custom shadow-sm p-4 bg-white">
                        <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-building-columns me-2 text-success"></i>Rekening Tujuan Transfer</h5>
                        <p class="text-muted small">Silakan melakukan transfer ke rekening bank resmi MyField berikut sebesar total tagihan di atas:</p>
                        <div class="bank-box mb-3">
                            <div class="small text-muted fw-bold">BANK CENTRAL ASIA (BCA)</div>
                            <div class="fs-4 fw-bold text-dark my-1">123 - 4567 - 890</div>
                            <div class="small text-muted">Atas Nama: <strong class="text-dark">PT MyField Indonesia Jaya</strong></div>
                        </div>
                        <div class="small text-muted">
                            <i class="fa-solid fa-circle-info me-1 text-primary"></i> Harap pastikan nominal transfer pas hingga digit terakhir agar proses verifikasi admin berjalan cepat.
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="card card-custom shadow-sm p-4 bg-white">
                        <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-cloud-arrow-up me-2 text-warning"></i>Unggah Bukti Bayar</h5>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label for="bukti_transfer" class="form-label text-muted small fw-bold">Pilih File Foto Bukti Transfer</label>
                                <input class="form-control" type="file" id="bukti_transfer" name="bukti_transfer" accept=".jpg,.jpeg,.png" required>
                                <div class="form-text small opacity-75">Format file diizinkan: JPG, JPEG, PNG (Maksimal 2MB).</div>
                            </div>

                            <button type="submit" name="upload_bukti" class="btn btn-primary w-100 py-2 rounded-pill fw-bold">
                                <i class="fa-solid fa-paper-plane me-1"></i> Kirim Bukti Pembayaran
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>