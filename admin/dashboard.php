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
//  STATISTIK 1: TOTAL PENDAPATAN (dari booking berstatus Lunas)
// ============================================================

// SUM(total_harga) menjumlahkan semua kolom total_harga
// COALESCE memastikan hasilnya 0 (bukan NULL) jika belum ada data Lunas
$query_pendapatan = "
    SELECT COALESCE(SUM(total_harga), 0) AS total_pendapatan
    FROM booking
    WHERE status = 'Lunas'
";

$result_pendapatan = $conn->query($query_pendapatan);
$row_pendapatan    = $result_pendapatan->fetch_assoc();

// Simpan angka total pendapatan ke variabel
$total_pendapatan  = $row_pendapatan['total_pendapatan'];


// ============================================================
//  STATISTIK 2: JUMLAH LAPANGAN TERSEDIA
// ============================================================

// COUNT(*) menghitung semua baris yang ada di tabel lapangan
$query_lapangan = "SELECT COUNT(*) AS jumlah_lapangan FROM lapangan";

$result_lapangan  = $conn->query($query_lapangan);
$row_lapangan     = $result_lapangan->fetch_assoc();

// Simpan jumlah lapangan ke variabel
$jumlah_lapangan  = $row_lapangan['jumlah_lapangan'];


// ============================================================
//  STATISTIK 3: JUMLAH BOOKING MENUNGGU KONFIRMASI
// ============================================================

// COUNT(*) menghitung booking yang statusnya masih pending / belum diproses
$query_pending = "
    SELECT COUNT(*) AS jumlah_pending
    FROM booking
    WHERE status = 'Menunggu Konfirmasi'
";

$result_pending        = $conn->query($query_pending);
$row_pending           = $result_pending->fetch_assoc();

// Simpan jumlah booking pending ke variabel
$jumlah_booking_pending = $row_pending['jumlah_pending'];


// ============================================================
//  STATISTIK 4: JUMLAH CUSTOMER TERDAFTAR
// ============================================================

// Hitung hanya user dengan role 'customer' agar tidak ikut terhitung akun admin
$query_customer = "
    SELECT COUNT(*) AS jumlah_customer
    FROM user
    WHERE role = 'customer'
";

$result_customer  = $conn->query($query_customer);
$row_customer     = $result_customer->fetch_assoc();

// Simpan jumlah customer ke variabel
$jumlah_customer  = $row_customer['jumlah_customer'];


// ============================================================
//  DATA TRANSAKSI TERBARU (5 booking terakhir)
// ============================================================

// Query SELECT dengan JOIN ke tabel user dan lapangan
// LIMIT 5 agar hanya mengambil 5 data terbaru
// ORDER BY b.id_booking DESC mengambil data dengan ID terbesar (terbaru) duluan
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

// Tampung semua baris ke dalam array, siap di-loop di HTML
$recent_bookings = [];
while ($row = $result_recent->fetch_assoc()) {
    $recent_bookings[] = $row;
}

// ============================================================
//  AMBIL & HAPUS FLASH MESSAGE DARI SESSION (jika ada)
// ============================================================

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