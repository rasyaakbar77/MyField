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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Booking</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            background: #f4f4f4;
            color: #333;
        }

        .container {
            max-width: 960px;
            margin: 30px auto;
            padding: 0 16px;
        }

        h2 {
            font-size: 20px;
            margin-bottom: 16px;
            color: #222;
        }

        /* Alert */
        .alert {
            padding: 10px 14px;
            border-radius: 4px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger  { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Tabel */
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        thead th {
            background: #3a7bd5;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-size: 13px;
        }

        tbody td {
            padding: 9px 12px;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: middle;
        }

        tbody tr:hover { background: #f9f9f9; }

        /* Badge status */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-lunas    { background: #d4edda; color: #155724; }
        .badge-menunggu { background: #fff3cd; color: #856404; }
        .badge-ditolak  { background: #f8d7da; color: #721c24; }
        .badge-default  { background: #e2e3e5; color: #383d41; }

        /* Form upload inline */
        .form-upload {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-upload input[type="file"] {
            font-size: 12px;
            max-width: 160px;
        }

        .btn {
            padding: 5px 12px;
            font-size: 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-primary { background: #3a7bd5; color: #fff; }
        .btn-primary:hover { background: #2f66b8; }

        .link-bukti {
            color: #3a7bd5;
            text-decoration: none;
            font-size: 12px;
        }
        .link-bukti:hover { text-decoration: underline; }

        .text-muted { color: #999; font-style: italic; font-size: 12px; }

        .empty-state {
            background: #fff;
            padding: 40px;
            text-align: center;
            color: #888;
            border: 1px solid #e5e5e5;
        }
    </style>
</head>
<body>

<div class="container">

    <h2>Riwayat Booking Saya</h2>

    <?php if ($pesan_sukses): ?>
        <div class="alert alert-success"><?= htmlspecialchars($pesan_sukses) ?></div>
    <?php endif; ?>

    <?php if ($pesan_gagal): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($pesan_gagal) ?></div>
    <?php endif; ?>

    <?php if (empty($pembayaran_list)): ?>

        <div class="empty-state">Kamu belum memiliki riwayat booking.</div>

    <?php else: ?>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Lapangan</th>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>Total Harga</th>
                    <th>Status</th>
                    <th>Bukti Transfer</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($pembayaran_list as $booking): ?>

                <?php
                    // Tentukan class badge sesuai status
                    $status = $booking['status'];
                    if ($status === 'Lunas') {
                        $badge = 'badge-lunas';
                    } elseif ($status === 'Menunggu Konfirmasi') {
                        $badge = 'badge-menunggu';
                    } elseif ($status === 'Ditolak') {
                        $badge = 'badge-ditolak';
                    } else {
                        $badge = 'badge-default';
                    }
                ?>

                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($booking['nama_lapangan']) ?></td>
                    <td><?= htmlspecialchars($booking['tanggal_booking']) ?></td>
                    <td><?= htmlspecialchars($booking['jam_mulai']) ?> – <?= htmlspecialchars($booking['jam_selesai']) ?></td>
                    <td>Rp <?= number_format($booking['total_harga'], 0, ',', '.') ?></td>
                    <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($status) ?></span></td>
                    <td>
                        <?php if (!empty($booking['bukti_transfer'])): ?>

                            <!-- Bukti sudah pernah diupload: tampilkan link lihat -->
                            <a class="link-bukti"
                               href="../uploads/bukti_transfer/<?= htmlspecialchars($booking['bukti_transfer']) ?>"
                               target="_blank">Lihat Bukti</a>

                        <?php elseif ($booking['status'] === 'Belum Bayar'): ?>

                            <!-- Belum bayar: tampilkan form upload -->
                            <form class="form-upload"
                                  action="bookinghistory.php"
                                  method="POST"
                                  enctype="multipart/form-data">
                                <input type="hidden"
                                       name="id_booking"
                                       value="<?= (int) $booking['id_booking'] ?>">
                                <input type="file"
                                       name="bukti_transfer"
                                       accept=".jpg,.jpeg,.png"
                                       required>
                                <button class="btn btn-primary"
                                        type="submit"
                                        name="upload_bukti">Kirim</button>
                            </form>

                        <?php else: ?>

                            <span class="text-muted">—</span>

                        <?php endif; ?>
                    </td>
                </tr>

                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>

</div>

</body>
</html>