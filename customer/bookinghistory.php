<?php
// ============================================================
//  FILE: customer/bookinghistory.php
//  FUNGSI: Backend - Riwayat booking & upload bukti transfer
// ============================================================

session_start();

// Proteksi halaman: redirect ke login jika belum login
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Include file koneksi database, menghasilkan variabel $conn (MySQLi)
include "../config/connection.php";

// Ambil ID pengguna yang sedang login dari session
$id_pengguna_login = $_SESSION['id_pengguna'];


// ============================================================
//  PROSES POST: UPLOAD BUKTI TRANSFER
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti'])) {

    // Ambil id_pembayaran dari form, cast ke integer untuk keamanan
    $id_pembayaran = (int) $_POST['id_pembayaran'];

    // Ambil data file dari superglobal $_FILES
    $file        = $_FILES['bukti_transfer'];
    $nama_file   = $file['name'];
    $ukuran_file = $file['size'];
    $tmp_file    = $file['tmp_name'];
    $error_file  = $file['error'];

    // Validasi 1: cek error upload dari PHP
    if ($error_file !== UPLOAD_ERR_OK) {
        $_SESSION['gagal'] = "Gagal mengunggah file. Silakan coba lagi.";
        header("Location: bookinghistory.php");
        exit();
    }

    // Validasi 2: cek ekstensi file (hanya jpg, jpeg, png)
    $ekstensi_file = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
    $ekstensi_izin = ['jpg', 'jpeg', 'png'];

    if (!in_array($ekstensi_file, $ekstensi_izin)) {
        $_SESSION['gagal'] = "Format file tidak valid. Hanya jpg, jpeg, dan png yang diizinkan.";
        header("Location: bookinghistory.php");
        exit();
    }

    // Validasi 3: cek ukuran file (maksimal 2MB)
    $maks_ukuran = 2 * 1024 * 1024;

    if ($ukuran_file > $maks_ukuran) {
        $_SESSION['gagal'] = "Ukuran file terlalu besar. Maksimal 2MB.";
        header("Location: bookinghistory.php");
        exit();
    }

    // Buat folder tujuan jika belum ada
    $folder_tujuan = __DIR__ . "/../uploads/bukti_transfer/";
    if (!is_dir($folder_tujuan)) {
        mkdir($folder_tujuan, 0755, true);
    }

    // Buat nama file unik agar tidak bentrok
    $nama_file_baru = time() . '_' . uniqid() . '.' . $ekstensi_file;
    $path_tujuan    = $folder_tujuan . $nama_file_baru;

    // Pindahkan file ke folder tujuan
    if (move_uploaded_file($tmp_file, $path_tujuan)) {

        // Update tabel pembayaran: isi bukti_transfer dan ubah status_bayar ke 'pending'
        // JOIN via subquery memastikan id_pembayaran benar-benar milik pengguna yang login
        $query_update = "
            UPDATE pembayaran p
            JOIN booking b ON p.id_booking = b.id_booking
            SET
                p.bukti_transfer = ?,
                p.status_bayar   = 'pending'
            WHERE p.id_pembayaran = ?
            AND   b.id_pengguna   = ?
        ";

        $stmt_update = $conn->prepare($query_update);

        // 's' = string (nama file), 'i' = integer (id_pembayaran), 'i' = integer (id_pengguna)
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

    // PRG Pattern: redirect agar tidak submit ulang saat refresh
    header("Location: bookinghistory.php");
    exit();
}


// ============================================================
//  QUERY READ: AMBIL RIWAYAT BOOKING MILIK PENGGUNA
// ============================================================

// JOIN tiga tabel: booking + lapangan + pembayaran
// Satu booking bisa tidak punya pembayaran, pakai LEFT JOIN ke pembayaran
$query_booking = "
    SELECT
        b.id_booking,
        b.tanggal_main,
        b.jam_mulai,
        b.jam_selesai,
        b.total_harga,
        b.status_booking,
        b.created_at,
        l.nama_lapangan,
        l.harga_per_jam,
        p.id_pembayaran,
        p.metode_bayar,
        p.status_bayar,
        p.bukti_transfer
    FROM booking b
    JOIN  lapangan   l ON b.id_lapangan  = l.id_lapangan
    LEFT JOIN pembayaran p ON b.id_booking   = p.id_booking
    WHERE b.id_pengguna = ?
    ORDER BY b.created_at DESC
";

$stmt_booking = $conn->prepare($query_booking);
$stmt_booking->bind_param("i", $id_pengguna_login);
$stmt_booking->execute();
$result_booking = $stmt_booking->get_result();

// Tampung semua baris ke dalam array, siap di-loop di HTML
$pembayaran_list = [];
while ($row = $result_booking->fetch_assoc()) {
    $pembayaran_list[] = $row;
}

$stmt_booking->close();


// ============================================================
//  AMBIL & HAPUS FLASH MESSAGE DARI SESSION
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
            max-width: 1000px;
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

        /* Badge status booking */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-dikonfirmasi { background: #d4edda; color: #155724; }
        .badge-selesai      { background: #cce5ff; color: #004085; }
        .badge-pending      { background: #fff3cd; color: #856404; }
        .badge-dibatalkan   { background: #f8d7da; color: #721c24; }
        .badge-default      { background: #e2e3e5; color: #383d41; }

        /* Badge status bayar */
        .badge-lunas        { background: #d4edda; color: #155724; }
        .badge-gagal        { background: #f8d7da; color: #721c24; }
        .badge-belum        { background: #e2e3e5; color: #383d41; }

        /* Form upload inline */
        .form-upload {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .form-upload input[type="file"] {
            font-size: 12px;
            max-width: 170px;
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
                    // Tentukan badge class untuk status_booking
                    switch ($booking['status_booking']) {
                        case 'dikonfirmasi': $badge_booking = 'badge-dikonfirmasi'; break;
                        case 'selesai':      $badge_booking = 'badge-selesai';      break;
                        case 'pending':      $badge_booking = 'badge-pending';      break;
                        case 'dibatalkan':   $badge_booking = 'badge-dibatalkan';   break;
                        default:             $badge_booking = 'badge-default';
                    }

                    // Tentukan badge class untuk status_bayar
                    switch ($booking['status_bayar']) {
                        case 'lunas':       $badge_bayar = 'badge-lunas';   break;
                        case 'pending':     $badge_bayar = 'badge-pending'; break;
                        case 'gagal':       $badge_bayar = 'badge-gagal';   break;
                        case 'belum_bayar': $badge_bayar = 'badge-belum';   break;
                        default:            $badge_bayar = 'badge-default';
                    }
                ?>

                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($booking['nama_lapangan']) ?></td>
                    <td><?= htmlspecialchars($booking['tanggal_main']) ?></td>
                    <td>
                        <?= htmlspecialchars(substr($booking['jam_mulai'], 0, 5)) ?> –
                        <?= htmlspecialchars(substr($booking['jam_selesai'], 0, 5)) ?>
                    </td>
                    <td>Rp <?= number_format($booking['total_harga'], 0, ',', '.') ?></td>
                    <td>
                        <span class="badge <?= $badge_booking ?>">
                            <?= htmlspecialchars($booking['status_booking']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($booking['status_bayar']): ?>
                            <span class="badge <?= $badge_bayar ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $booking['status_bayar'])) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($booking['bukti_transfer'])): ?>

                            <!-- Bukti sudah diupload: tampilkan link lihat -->
                            <a class="link-bukti"
                               href="../uploads/bukti_transfer/<?= htmlspecialchars($booking['bukti_transfer']) ?>"
                               target="_blank">Lihat Bukti</a>

                        <?php elseif (!empty($booking['id_pembayaran']) && $booking['status_bayar'] === 'belum_bayar'): ?>

                            <!-- Ada data pembayaran tapi belum upload bukti: tampilkan form upload -->
                            <form class="form-upload"
                                  action="bookinghistory.php"
                                  method="POST"
                                  enctype="multipart/form-data">
                                <input type="hidden"
                                       name="id_pembayaran"
                                       value="<?= (int) $booking['id_pembayaran'] ?>">
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