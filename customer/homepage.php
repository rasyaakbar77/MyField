<?php
session_start();
require_once '../config/connection.php';

// Proteksi halaman
if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Filter jenis olahraga
$filter = isset($_GET['jenis']) ? trim($_GET['jenis']) : '';

// Query lapangan
if (!empty($filter)) {
    $stmt = mysqli_prepare($conn, 
        "SELECT * FROM lapangan WHERE jenis_olahraga = ? ORDER BY nama_lapangan"
    );
    mysqli_stmt_bind_param($stmt, "s", $filter);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, 
        "SELECT * FROM lapangan ORDER BY jenis_olahraga, nama_lapangan"
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Homepage — MyField</title>
</head>
<body>

// Bar navigasi
    <nav>
        <strong>MyField</strong>
        &nbsp;|&nbsp;
        Halo, <?= htmlspecialchars($_SESSION['nama_pengguna']) ?>
        &nbsp;|&nbsp;
        <a href="bookinghistory.php">Riwayat Booking</a>
        &nbsp;|&nbsp;
        <a href="../auth/logout.php">Logout</a>
    </nav>

    <hr>

    <h2>Daftar Lapangan</h2>

// Filter jenis olahraga
    <div>
        <a href="homepage.php">Semua</a> &nbsp;
        <a href="homepage.php?jenis=Futsal">Futsal</a> &nbsp;
        <a href="homepage.php?jenis=Badminton">Badminton</a> &nbsp;
        <a href="homepage.php?jenis=Basket">Basket</a>
    </div>

    <br>

    <?php if (mysqli_num_rows($result) > 0): ?>

        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Nama Lapangan</th>
                    <th>Jenis Olahraga</th>
                    <th>Deskripsi</th>
                    <th>Harga / Jam</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($lapangan = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= htmlspecialchars($lapangan['nama_lapangan']) ?></td>
                    <td><?= htmlspecialchars($lapangan['jenis_olahraga']) ?></td>
                    <td><?= htmlspecialchars($lapangan['deskripsi']) ?></td>
                    <td>Rp <?= number_format($lapangan['harga_per_jam'], 0, ',', '.') ?></td>
                    <td>
                        <?php if ($lapangan['status'] === 'tersedia'): ?>
                            <span style="color:green;">Tersedia</span>
                        <?php elseif ($lapangan['status'] === 'pemeliharaan'): ?>
                            <span style="color:orange;">Pemeliharaan</span>
                        <?php else: ?>
                            <span style="color:red;">Tidak Tersedia</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($lapangan['status'] === 'tersedia'): ?>
                            <a href="bookingform.php?id=<?= $lapangan['id_lapangan'] ?>">
                                Booking Sekarang
                            </a>
                        <?php else: ?>
                            <span style="color:grey;">Tidak Bisa Dibooking</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php else: ?>
        <p>Tidak ada lapangan untuk jenis olahraga ini.</p>
    <?php endif; ?>

</body>
</html>