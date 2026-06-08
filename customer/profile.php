<?php
session_start();
require_once '../config/connection.php';

if (!isset($_SESSION['id_pengguna'])) {
    header("Location: ../auth/login.php");
    exit();
}

$id_pengguna = $_SESSION['id_pengguna'];

// Ambil data user terbaru dari DB
$stmt = mysqli_prepare($conn, "SELECT * FROM pengguna WHERE id_pengguna = ?");
mysqli_stmt_bind_param($stmt, "i", $id_pengguna);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);

$error_profil    = '';
$success_profil  = '';
$error_password  = '';
$success_password = '';

// ── FORM 1: Update profil ──
if (isset($_POST['update_profil'])) {
    $nama       = trim($_POST['nama']);
    $no_telepon = trim($_POST['no_telepon']);

    if (empty($nama)) {
        $error_profil = "Nama tidak boleh kosong.";
    } else {
        $stmt2 = mysqli_prepare($conn,
            "UPDATE pengguna SET nama_pengguna = ?, no_telepon = ? WHERE id_pengguna = ?"
        );
        $no_tel = !empty($no_telepon) ? $no_telepon : null;
        mysqli_stmt_bind_param($stmt2, "ssi", $nama, $no_tel, $id_pengguna);

        if (mysqli_stmt_execute($stmt2)) {
            // Update session name juga
            $_SESSION['nama_pengguna'] = $nama;
            $success_profil = "Profil berhasil diperbarui.";
            // Refresh data user
            $user['nama_pengguna'] = $nama;
            $user['no_telepon']    = $no_tel;
        } else {
            $error_profil = "Gagal memperbarui profil. Coba lagi.";
        }
    }
}

// ── FORM 2: Ganti password ──
if (isset($_POST['ganti_password'])) {
    $password_lama     = $_POST['password_lama'];
    $password_baru     = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        $error_password = "Semua field password harus diisi.";

    } elseif (!password_verify($password_lama, $user['password'])) {
        $error_password = "Password lama tidak sesuai.";

    } elseif (strlen($password_baru) < 8) {
        $error_password = "Password baru minimal 8 karakter.";

    } elseif ($password_baru !== $konfirmasi_password) {
        $error_password = "Konfirmasi password tidak sesuai.";

    } else {
        $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
        $stmt3 = mysqli_prepare($conn,
            "UPDATE pengguna SET password = ? WHERE id_pengguna = ?"
        );
        mysqli_stmt_bind_param($stmt3, "si", $hash_baru, $id_pengguna);

        if (mysqli_stmt_execute($stmt3)) {
            $success_password = "Password berhasil diubah.";
        } else {
            $error_password = "Gagal mengubah password. Coba lagi.";
        }
    }
}

// Hitung statistik booking customer
$stmt4 = mysqli_prepare($conn,
    "SELECT 
        COUNT(*) as total_booking,
        SUM(CASE WHEN status_booking = 'selesai' THEN 1 ELSE 0 END) as total_selesai,
        SUM(CASE WHEN status_booking = 'pending' OR status_booking = 'dikonfirmasi' THEN 1 ELSE 0 END) as total_aktif
     FROM booking WHERE id_pengguna = ?"
);
mysqli_stmt_bind_param($stmt4, "i", $id_pengguna);
mysqli_stmt_execute($stmt4);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt4));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya — MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar-brand { font-weight: 700; color: #0d6efd !important; font-size: 1.4rem; }

        .page-title { font-size: 1.5rem; font-weight: 700; color: #212529; margin-bottom: 4px; }
        .page-sub   { color: #6c757d; font-size: 0.9rem; }

        /* Avatar */
        .avatar-circle {
            width: 80px; height: 80px; border-radius: 50%;
            background: #0d6efd; color: #fff;
            font-size: 2rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        /* Stat cards */
        .stat-card {
            background: #fff; border: 1px solid #dee2e6; border-radius: 10px;
            padding: 16px 20px; text-align: center;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .stat-num   { font-size: 1.8rem; font-weight: 700; color: #0d6efd; line-height: 1; }
        .stat-label { font-size: 0.8rem; color: #6c757d; margin-top: 4px; }

        /* Section card */
        .section-card {
            background: #fff; border: 1px solid #dee2e6; border-radius: 10px;
            padding: 24px; margin-bottom: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .section-title {
            font-weight: 600; font-size: 1rem; color: #212529;
            margin-bottom: 20px; padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; gap: 8px;
        }

        /* Info row (read-only) */
        .info-row {
            display: flex; padding: 10px 0;
            border-bottom: 1px solid #f5f5f5; font-size: 0.9rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-key   { width: 160px; color: #6c757d; flex-shrink: 0; }
        .info-val   { font-weight: 500; color: #212529; }

        /* Role badge */
        .role-badge {
            display: inline-block; padding: 2px 10px; border-radius: 4px;
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
            background: #e7f1ff; color: #0d6efd;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-light bg-white border-bottom shadow-sm px-4 py-2 mb-4">
    <a class="navbar-brand" href="homepage.php">MyField</a>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small">Halo, <strong class="text-dark"><?= htmlspecialchars($_SESSION['nama_pengguna']) ?></strong></span>
        <a href="homepage.php"      class="btn btn-sm btn-outline-secondary">Lapangan</a>
        <a href="bookinghistory.php" class="btn btn-sm btn-outline-secondary">Riwayat</a>
        <a href="../auth/logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
    </div>
</nav>

<div class="container" style="max-width: 720px;">

    <!-- Header -->
    <div class="mb-4">
        <div class="page-title">Profil Saya</div>
        <div class="page-sub">Kelola informasi akun kamu</div>
    </div>

    <!-- Avatar + nama + stats -->
    <div class="section-card">
        <div class="d-flex align-items-center gap-4 mb-4">
            <div class="avatar-circle">
                <?= strtoupper(substr($user['nama_pengguna'], 0, 1)) ?>
            </div>
            <div>
                <div style="font-size:1.2rem;font-weight:700;color:#212529">
                    <?= htmlspecialchars($user['nama_pengguna']) ?>
                </div>
                <div class="text-muted small mb-1"><?= htmlspecialchars($user['email']) ?></div>
                <span class="role-badge"><?= htmlspecialchars($user['role']) ?></span>
            </div>
        </div>

        <!-- Statistik -->
        <div class="row g-3">
            <div class="col-4">
                <div class="stat-card">
                    <div class="stat-num"><?= $stats['total_booking'] ?></div>
                    <div class="stat-label">Total Booking</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card">
                    <div class="stat-num"><?= $stats['total_selesai'] ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-card">
                    <div class="stat-num"><?= $stats['total_aktif'] ?></div>
                    <div class="stat-label">Aktif</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info akun (read-only) -->
    <div class="section-card">
        <div class="section-title">📋 Informasi Akun</div>
        <div class="info-row">
            <div class="info-key">Email</div>
            <div class="info-val"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-key">No. Telepon</div>
            <div class="info-val">
                <?= !empty($user['no_telepon']) ? htmlspecialchars($user['no_telepon']) : '<span class="text-muted fst-italic">Belum diisi</span>' ?>
            </div>
        </div>
        <div class="info-row">
            <div class="info-key">Bergabung sejak</div>
            <div class="info-val"><?= date('d M Y', strtotime($user['created_at'])) ?></div>
        </div>
        <div class="info-row">
            <div class="info-key">Role</div>
            <div class="info-val"><span class="role-badge"><?= htmlspecialchars($user['role']) ?></span></div>
        </div>
    </div>

    <!-- Form edit profil -->
    <div class="section-card">
        <div class="section-title">✏️ Edit Profil</div>

        <?php if ($error_profil !== ''): ?>
        <div class="alert alert-danger py-2 small">⚠ <?= htmlspecialchars($error_profil) ?></div>
        <?php endif; ?>
        <?php if ($success_profil !== ''): ?>
        <div class="alert alert-success py-2 small">✓ <?= htmlspecialchars($success_profil) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold">Nama Lengkap</label>
                <input type="text" name="nama" class="form-control"
                       value="<?= htmlspecialchars($user['nama_pengguna']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">No. Telepon <span class="text-muted fw-normal">(Opsional)</span></label>
                <input type="text" name="no_telepon" class="form-control" placeholder="08xxxxxxxxxx"
                       value="<?= htmlspecialchars($user['no_telepon'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                <div class="form-text">Email tidak dapat diubah.</div>
            </div>
            <button type="submit" name="update_profil" class="btn btn-primary">
                Simpan Perubahan
            </button>
        </form>
    </div>

    <!-- Form ganti password -->
    <div class="section-card">
        <div class="section-title">🔒 Ganti Password</div>

        <?php if ($error_password !== ''): ?>
        <div class="alert alert-danger py-2 small">⚠ <?= htmlspecialchars($error_password) ?></div>
        <?php endif; ?>
        <?php if ($success_password !== ''): ?>
        <div class="alert alert-success py-2 small">✓ <?= htmlspecialchars($success_password) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold">Password Lama</label>
                <input type="password" name="password_lama" class="form-control"
                       placeholder="Masukkan password lama" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Password Baru</label>
                <input type="password" name="password_baru" id="password_baru" class="form-control"
                       placeholder="Minimal 8 karakter" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                <input type="password" name="konfirmasi_password" id="konfirmasi" class="form-control"
                       placeholder="Ulangi password baru" required
                       oninput="cekKonfirmasi()">
                <div class="form-text" id="konfirmasi-hint"></div>
            </div>
            <button type="submit" name="ganti_password" class="btn btn-warning fw-semibold">
                Ubah Password
            </button>
        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function cekKonfirmasi() {
    const baru       = document.getElementById('password_baru').value;
    const konfirmasi = document.getElementById('konfirmasi').value;
    const hint       = document.getElementById('konfirmasi-hint');
    if (konfirmasi === '') { hint.textContent = ''; return; }
    if (baru === konfirmasi) {
        hint.textContent = '✓ Password cocok';
        hint.className   = 'form-text text-success';
    } else {
        hint.textContent = '✗ Password tidak cocok';
        hint.className   = 'form-text text-danger';
    }
}
</script>
</body>
</html>