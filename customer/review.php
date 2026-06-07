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

// Ambil data booking — pastikan milik customer tersebut, statusnya selesai, dan belum pernah diulas
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

// Booking tidak ditemukan / bukan miliknya / belum selesai
if (!$booking) {
    header("Location: bookinghistory.php");
    exit();
}

// Sudah pernah diulas
if (!is_null($booking['id_ulasan'])) {
    header("Location: bookinghistory.php");
    exit();
}

$error = '';

// Proses submit ulasan
if (isset($_POST['kirim_ulasan'])) {
    $rating   = (float) $_POST['rating'];
    $komentar = trim($_POST['komentar']);

    // Validasi rating
    if ($rating < 1 || $rating > 5) {
        $error = "Rating harus antara 1 sampai 5";
    } else {
        $stmt2 = mysqli_prepare($conn,
            "INSERT INTO ulasan (id_booking, id_lapangan, id_pengguna, rating, komentar)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt2, "iiids",
            $id_booking,
            $booking['id_lapangan'],
            $id_pengguna,
            $rating,
            $komentar
        );

        if (mysqli_stmt_execute($stmt2)) {
            header("Location: bookinghistory.php?review=success");
            exit();
        } else {
            $error = "Gagal mengirim ulasan, silakan coba lagi";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Beri Ulasan — MyField</title>
</head>
<body>

// Navbar
    <nav>
        <strong>MyField</strong>
        &nbsp;|&nbsp;
        Halo, <?= htmlspecialchars($_SESSION['nama_pengguna']) ?>
        &nbsp;|&nbsp;
        <a href="homepage.php">Daftar Lapangan</a>
        &nbsp;|&nbsp;
        <a href="bookinghistory.php">Riwayat Booking</a>
        &nbsp;|&nbsp;
        <a href="../auth/logout.php">Logout</a>
    </nav>

    <hr>

    <h2>Beri Ulasan</h2>

// Tampilkan info booking
    <table border="1" cellpadding="8" cellspacing="0">
        <tr><th>Lapangan</th><td><?= htmlspecialchars($booking['nama_lapangan']) ?></td></tr>
        <tr><th>Jenis</th><td><?= htmlspecialchars($booking['jenis_olahraga']) ?></td></tr>
        <tr><th>Tanggal Main</th><td><?= date('d M Y', strtotime($booking['tanggal_main'])) ?></td></tr>
        <tr><th>Jam</th><td><?= substr($booking['jam_mulai'], 0, 5) ?> – <?= substr($booking['jam_selesai'], 0, 5) ?></td></tr>
        <tr><th>Total Bayar</th><td>Rp <?= number_format($booking['total_harga'], 0, ',', '.') ?></td></tr>
    </table>

    <br>

    <?php if ($error !== ''): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">

        <label><strong>Rating:</strong></label><br>
        <select name="rating" required>
            <option value="">-- Pilih Rating --</option>
            <option value="5">⭐⭐⭐⭐⭐ — Sangat Bagus (5)</option>
            <option value="4">⭐⭐⭐⭐ - Bagus (4)</option>
            <option value="3">⭐⭐⭐ - Biasa (3)</option>
            <option value="2">⭐⭐ - Kurang (2)</option>
            <option value="1">⭐ - Buruk (1)</option>
        </select>

        <br><br>

        <label><strong>Komentar:</strong> <small>(opsional)</small></label><br>
        <textarea name="komentar" rows="4" cols="50" 
                  placeholder="Ceritakan pengalamanmu..."></textarea>

        <br><br>

        <button type="submit" name="kirim_ulasan">Kirim Ulasan</button>
        <a href="bookinghistory.php">&nbsp; Batal</a>

    </form>

</body>
</html>
>