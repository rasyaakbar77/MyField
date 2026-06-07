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
$result = mysqli_stmt_get_result($stmt);
$lapangan = mysqli_fetch_assoc($result);

// Kalau lapangan tidak ditemukan / tidak tersedia
if (!$lapangan) {
    header("Location: homepage.php");
    exit();
}

$error   = '';
$success = '';

// Ambil slot yang sudah terisi untuk tanggal yang dipilih
$slots_terisi = [];
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
        'mulai'   => $mulai,
        'selesai' => $selesai,
        'label'   => sprintf('%02d:00 - %02d:00', $jam, $jam + 1),
        'booked'  => in_array($mulai . '-' . $selesai, $slots_terisi)
    ];
}

// Proses booking
if (isset($_POST['booking'])) {
    $tanggal    = trim($_POST['tanggal']);
    $jam_mulai  = trim($_POST['jam_mulai']);
    $jam_selesai = trim($_POST['jam_selesai']);
    $id_pengguna = $_SESSION['id_pengguna'];

    // Validasi input
    if (empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) {
        $error = "Semua data harus diisi";

    } elseif ($tanggal < date('Y-m-d')) {
        $error = "Tidak bisa booking untuk tanggal yang sudah lewat";

    } elseif ($jam_mulai >= $jam_selesai) {
        $error = "Jam selesai harus lebih besar dari jam mulai";

    } else {
        // Cek apakah slot bentrok dengan booking yang sudah ada
        $stmt3 = mysqli_prepare($conn,
            "SELECT id_jadwal_lapangan FROM jadwal_lapangan 
             WHERE id_lapangan = ? AND tanggal = ? AND status_slot = 'booked'
             AND NOT (jam_selesai <= ? OR jam_mulai >= ?)"
        );
        mysqli_stmt_bind_param($stmt3, "isss", $id_lapangan, $tanggal, $jam_mulai, $jam_selesai);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_store_result($stmt3);

        if (mysqli_stmt_num_rows($stmt3) > 0) {
            $error = "Slot waktu tersebut sudah dibooking orang lain";
        } else {
            // Hitung total harga
            $jam_main    = (strtotime($jam_selesai) - strtotime($jam_mulai)) / 3600;
            $total_harga = $jam_main * $lapangan['harga_per_jam'];

            // Insert ke tabel booking
            $stmt4 = mysqli_prepare($conn,
                "INSERT INTO booking (id_pengguna, id_lapangan, tanggal_main, jam_mulai, jam_selesai, total_harga, status_booking)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending')"
            );
            mysqli_stmt_bind_param($stmt4, "iisssd", $id_pengguna, $id_lapangan, $tanggal, $jam_mulai, $jam_selesai, $total_harga);

            if (mysqli_stmt_execute($stmt4)) {
                $id_booking_baru = mysqli_insert_id($conn);

                // Insert ke jadwal_lapangan
                $stmt5 = mysqli_prepare($conn,
                    "INSERT INTO jadwal_lapangan (id_lapangan, tanggal, jam_mulai, jam_selesai, status_slot)
                     VALUES (?, ?, ?, ?, 'booked')"
                );
                mysqli_stmt_bind_param($stmt5, "isss", $id_lapangan, $tanggal, $jam_mulai, $jam_selesai);
                mysqli_stmt_execute($stmt5);

                // Redirect ke riwayat booking
                header("Location: bookinghistory.php?booking=success");
                exit();
            } else {
                $error = "Booking gagal, silakan coba lagi";
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
    <title>Booking — <?= htmlspecialchars($lapangan['nama_lapangan']) ?></title>
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

    <h2>Booking Lapangan</h2>

// Tampilkan info lapangan
    <table border="1" cellpadding="8" cellspacing="0">
        <tr><th>Nama Lapangan</th><td><?= htmlspecialchars($lapangan['nama_lapangan']) ?></td></tr>
        <tr><th>Jenis Olahraga</th><td><?= htmlspecialchars($lapangan['jenis_olahraga']) ?></td></tr>
        <tr><th>Harga</th><td>Rp <?= number_format($lapangan['harga_per_jam'], 0, ',', '.') ?> / jam</td></tr>
        <tr><th>Deskripsi</th><td><?= htmlspecialchars($lapangan['deskripsi']) ?></td></tr>
    </table>

    <br>

    <?php if ($error !== ''): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

// Form booking
    <h3>1. Pilih Tanggal</h3>
    <form method="GET">
        <input type="hidden" name="id" value="<?= $id_lapangan ?>">
        <input type="date" name="tanggal" 
               value="<?= htmlspecialchars($tanggal_dipilih) ?>" 
               min="<?= date('Y-m-d') ?>" required>
        <button type="submit">Lihat Slot Tersedia</button>
    </form>

// Tampilkan slot jika tanggal sudah dipilih
    <?php if (!empty($tanggal_dipilih)): ?>
        <br>
        <h3>2. Pilih Slot Waktu — <?= htmlspecialchars($tanggal_dipilih) ?></h3>

        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Slot</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($semua_slot as $slot): ?>
                <tr>
                    <td><?= $slot['label'] ?></td>
                    <td>
                        <?php if ($slot['booked']): ?>
                            <span style="color:red;">Sudah Dibooking</span>
                        <?php else: ?>
                            <span style="color:green;">Tersedia</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <br>
        <h3>3. Isi Form Booking</h3>

        <form method="POST">
            <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggal_dipilih) ?>">

            <label>Jam Mulai:</label><br>
            <select name="jam_mulai" required>
                <option value="">-- Pilih Jam Mulai --</option>
                <?php foreach ($semua_slot as $slot): ?>
                    <?php if (!$slot['booked']): ?>
                        <option value="<?= $slot['mulai'] ?>">
                            <?= substr($slot['mulai'], 0, 5) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <br><br>

            <label>Jam Selesai:</label><br>
            <select name="jam_selesai" required>
                <option value="">-- Pilih Jam Selesai --</option>
                <?php foreach ($semua_slot as $slot): ?>
                    <?php if (!$slot['booked']): ?>
                        <option value="<?= $slot['selesai'] ?>">
                            <?= substr($slot['selesai'], 0, 5) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <br><br>

            <p><em>* Total harga akan dihitung otomatis berdasarkan durasi × Rp <?= number_format($lapangan['harga_per_jam'], 0, ',', '.') ?>/jam</em></p>

            <button type="submit" name="booking">Konfirmasi Booking</button>
        </form>
    <?php endif; ?>

    <br>
    <a href="homepage.php">← Kembali ke Daftar Lapangan</a>

</body>
</html>