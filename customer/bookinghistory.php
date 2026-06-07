<?php
// ============================================================
//  FILE: customer/bookinghistory.php
//  FUNGSI: Backend - Riwayat booking & upload bukti transfer
// ============================================================


// ============================================================
//  1. KEAMANAN & PROTEKSI HALAMAN
// ============================================================

// Aktifkan session agar bisa membaca & menulis data session
session_start();

// Cek apakah user sudah login dengan memeriksa keberadaan session 'id_user'
if (!isset($_SESSION['id_user'])) {
    // Redirect paksa ke halaman login jika belum login
    header("Location: ../auth/login.php");
    // Hentikan eksekusi script agar kode di bawah tidak ikut berjalan
    exit();
}


// ============================================================
//  2. KONEKSI DATABASE
// ============================================================

// Include file koneksi, diasumsikan menghasilkan variabel $conn (objek MySQLi)
include "../config/connection.php";


// ============================================================
//  3. MENGAMBIL DATA RIWAYAT BOOKING (Query READ)
// ============================================================

// Ambil ID user yang sedang login dari session
$id_user_login = $_SESSION['id_user'];

// Query SELECT dengan JOIN ke tabel lapangan
// untuk mengambil nama_lapangan dan harga_per_jam
// Diurutkan berdasarkan tanggal_booking terbaru (DESC)
$query_booking = "
    SELECT
        b.id_booking,
        b.tanggal_booking,
        b.jam_mulai,
        b.jam_selesai,
        b.total_harga,
        b.status,
        b.bukti_transfer,
        l.nama_lapangan,
        l.harga_per_jam
    FROM booking b
    JOIN lapangan l ON b.id_lapangan = l.id_lapangan
    WHERE b.id_user = ?
    ORDER BY b.tanggal_booking DESC
";

// Siapkan prepared statement untuk mencegah SQL Injection
$stmt_booking = $conn->prepare($query_booking);

// Bind parameter: 'i' = integer, sesuai tipe id_user di database
$stmt_booking->bind_param("i", $id_user_login);

// Eksekusi query
$stmt_booking->execute();

// Ambil semua hasil query
$result_booking = $stmt_booking->get_result();

// Tampung semua baris ke dalam array, siap di-loop di HTML
$pembayaran_list = [];
while ($row = $result_booking->fetch_assoc()) {
    $pembayaran_list[] = $row;
}

// Tutup statement setelah selesai digunakan
$stmt_booking->close();


// ============================================================
//  4. LOGIKA UPLOAD BUKTI PEMBAYARAN (Proses POST)
// ============================================================

// Deteksi request POST dari tombol upload bukti transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti'])) {

    // Ambil id_booking dari form, cast ke integer untuk keamanan
    $id_booking = (int) $_POST['id_booking'];

    // Ambil data file dari superglobal $_FILES
    $file        = $_FILES['bukti_transfer'];
    $nama_file   = $file['name'];       // Nama asli file dari komputer user
    $ukuran_file = $file['size'];       // Ukuran file dalam byte
    $tmp_file    = $file['tmp_name'];   // Path sementara file di server
    $error_file  = $file['error'];      // Kode error (0 = tidak ada error)

    // --- Validasi 1: Cek error saat proses upload ---
    if ($error_file !== UPLOAD_ERR_OK) {
        $_SESSION['gagal'] = "Gagal mengunggah file. Silakan coba lagi.";
        header("Location: bookinghistory.php");
        exit();
    }

    // --- Validasi 2: Cek ekstensi file ---
    // Ambil ekstensi dan ubah ke huruf kecil agar 'JPG' == 'jpg'
    $ekstensi_file = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
    $ekstensi_izin = ['jpg', 'jpeg', 'png'];

    if (!in_array($ekstensi_file, $ekstensi_izin)) {
        $_SESSION['gagal'] = "Format file tidak valid. Hanya jpg, jpeg, dan png yang diizinkan.";
        header("Location: bookinghistory.php");
        exit();
    }

    // --- Validasi 3: Cek ukuran file (maksimal 2MB) ---
    $maks_ukuran = 2 * 1024 * 1024; // 2MB dalam byte

    if ($ukuran_file > $maks_ukuran) {
        $_SESSION['gagal'] = "Ukuran file terlalu besar. Maksimal 2MB.";
        header("Location: bookinghistory.php");
        exit();
    }

    // --- Proses Penyimpanan File ---

    // Path folder tujuan (relatif dari lokasi file ini)
    $folder_tujuan = __DIR__ . "/../uploads/bukti_transfer/";

    // Buat folder jika belum ada (rekursif, permission 755)
    if (!is_dir($folder_tujuan)) {
        mkdir($folder_tujuan, 0755, true);
    }

    // Buat nama file unik dengan time() + uniqid() agar tidak bentrok
    $nama_file_baru = time() . '_' . uniqid() . '.' . $ekstensi_file;

    // Gabungkan path folder dengan nama file baru
    $path_tujuan = $folder_tujuan . $nama_file_baru;

    // Pindahkan file dari lokasi sementara ke folder tujuan
    if (move_uploaded_file($tmp_file, $path_tujuan)) {

        // --- Update database jika file berhasil dipindah ---
        // Ubah status jadi 'Menunggu Konfirmasi' dan simpan nama file bukti
        $query_update = "
            UPDATE booking
            SET
                status         = 'Menunggu Konfirmasi',
                bukti_transfer = ?
            WHERE id_booking   = ?
            AND   id_user      = ?
        ";
        // Kondisi 'AND id_user = ?' sebagai lapisan keamanan
        // agar user tidak bisa mengubah data booking milik orang lain

        $stmt_update = $conn->prepare($query_update);

        // Bind: 's' = string (nama file), 'i' = integer (id_booking), 'i' = integer (id_user)
        $stmt_update->bind_param("sii", $nama_file_baru, $id_booking, $id_user_login);

        if ($stmt_update->execute()) {
            // Query berhasil: simpan flash message sukses
            $_SESSION['sukses'] = "Bukti transfer berhasil diunggah! Menunggu konfirmasi admin.";
        } else {
            // Query gagal: simpan flash message gagal
            $_SESSION['gagal'] = "Terjadi kesalahan saat memperbarui data. Silakan coba lagi.";
        }

        $stmt_update->close();

    } else {
        // move_uploaded_file() gagal (kemungkinan masalah permission folder)
        $_SESSION['gagal'] = "Gagal menyimpan file ke server. Hubungi administrator.";
    }

    // Redirect kembali (Pola PRG: Post/Redirect/Get)
    // Mencegah form ter-submit ulang saat user menekan Refresh
    header("Location: bookinghistory.php");
    exit();
}


// ============================================================
//  5. AMBIL & HAPUS FLASH MESSAGE DARI SESSION
// ============================================================

// Salin pesan sukses ke variabel lokal lalu hapus dari session
// agar pesan hanya muncul sekali saja
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

// ============================================================
//  SAMPAI SINI BACKEND SELESAI.
//  Variabel yang tersedia untuk dipakai di HTML:
//    $pembayaran_list  => array semua data booking user (siap di-foreach)
//    $pesan_sukses     => string pesan sukses atau null
//    $pesan_gagal      => string pesan gagal atau null
// ============================================================
?>