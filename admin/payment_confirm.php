<?php
session_start();

// Proteksi halaman: hanya admin yang boleh mengakses
// Jika role bukan 'admin', redirect paksa ke halaman login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Include file koneksi database, menghasilkan variabel $conn (MySQLi)
include "../config/connection.php";

// ============================================================
//  PROSES POST: TERIMA ATAU TOLAK PEMBAYARAN
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil id_booking dari form, cast ke integer untuk keamanan
    $id_booking = (int) $_POST['id_booking'];

    // Deteksi tombol mana yang ditekan admin
    if (isset($_POST['aksi_terima'])) {

        // --- AKSI TERIMA (Approve) ---
        // Update status booking menjadi 'Lunas'
        $query_terima = "UPDATE booking SET status = 'Lunas' WHERE id_booking = ?";

        $stmt_terima = $conn->prepare($query_terima);

        // Bind parameter: 'i' = integer
        $stmt_terima->bind_param("i", $id_booking);

        if ($stmt_terima->execute()) {
            $_SESSION['sukses'] = "Pembayaran berhasil dikonfirmasi!";
        } else {
            $_SESSION['gagal'] = "Gagal mengkonfirmasi pembayaran. Silakan coba lagi.";
        }

        $stmt_terima->close();

    } elseif (isset($_POST['aksi_tolak'])) {

        // --- AKSI TOLAK (Reject) ---
        // Update status booking menjadi 'Ditolak'
        $query_tolak = "UPDATE booking SET status = 'Ditolak' WHERE id_booking = ?";

        $stmt_tolak = $conn->prepare($query_tolak);

        $stmt_tolak->bind_param("i", $id_booking);

        if ($stmt_tolak->execute()) {
            $_SESSION['gagal'] = "Pembayaran telah ditolak.";
        } else {
            $_SESSION['gagal'] = "Gagal menolak pembayaran. Silakan coba lagi.";
        }

        $stmt_tolak->close();
    }

    // Redirect kembali ke halaman ini (Pola PRG) agar data tabel langsung refresh
    // dan mencegah form ter-submit ulang saat user menekan Refresh
    header("Location: payment_confirm.php");
    exit();
}

// ============================================================
//  QUERY READ: AMBIL DATA YANG MENUNGGU KONFIRMASI
// ============================================================

// Query SELECT dengan JOIN ke tabel user dan lapangan
// Filter hanya data dengan status 'Menunggu Konfirmasi'
$query_konfirmasi = "
    SELECT
        b.id_booking,
        b.tanggal_booking,
        b.jam_mulai,
        b.jam_selesai,
        b.total_harga,
        b.bukti_transfer,
        u.nama        AS nama_customer,
        l.nama_lapangan,
        l.harga_per_jam
    FROM booking b
    JOIN user     u ON b.id_user     = u.id_user
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    WHERE b.status = 'Menunggu Konfirmasi'
    ORDER BY b.tanggal_booking ASC
";

// Eksekusi query (tidak ada input user, tidak perlu prepared statement)
$result_konfirmasi = $conn->query($query_konfirmasi);

// Tampung semua baris ke dalam array, siap di-loop di HTML
$konfirmasi_list = [];
while ($row = $result_konfirmasi->fetch_assoc()) {
    $konfirmasi_list[] = $row;
}

// ============================================================
//  AMBIL & HAPUS FLASH MESSAGE DARI SESSION
// ============================================================

// Salin pesan sukses ke variabel lokal lalu hapus dari session
$pesan_sukses = null;
if (isset($_SESSION['sukses'])) {
    $pesan_sukses = $_SESSION['sukses'];
    unset($_SESSION['sukses']);
}

// Salin pesan gagal ke variabel lokal lalu hapus dari session
$pesan_gagal = null;
if (isset($_SESSION['gagal'])) {
    $pesan_gagal = $_SESSION['gagal'];
    unset($_SESSION['gagal']);
}
?>