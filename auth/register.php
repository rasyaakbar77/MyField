<?php
include '../config/connection.php';

session_start();

if (isset($_SESSION['id_pengguna'])) {
    header("Location: ../customer/homepage.php");
    exit();
}

$error   = '';
$success = '';

if (isset($_POST['register'])) {
    $nama            = trim($_POST['nama']);
    $email           = trim($_POST['email']);
    $password        = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);
    $no_telepon      = trim($_POST['no_telepon']);

    if (empty($nama) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = "Semua data harus diisi";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter";
    } elseif ($password !== $confirmPassword) {
        $error = "Konfirmasi password tidak sesuai";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id_pengguna FROM pengguna WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Email sudah terdaftar";
        } else {
            mysqli_stmt_close($stmt);

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn,
                "INSERT INTO pengguna (nama_pengguna, email, password, no_telepon, role)
                 VALUES (?, ?, ?, ?, 'customer')"
            );
            $no_tel = !empty($no_telepon) ? $no_telepon : null;
            mysqli_stmt_bind_param($stmt, "ssss", $nama, $email, $hashedPassword, $no_tel);

            if (mysqli_stmt_execute($stmt)) {
                $success = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Registrasi gagal, silakan coba lagi";
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .register-container { max-width: 450px; margin-top: 50px; margin-bottom: 50px; }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="register-container w-100 p-4 bg-white rounded shadow-sm border">
        <h3 class="text-center mb-4 fw-bold text-primary">MyField Register</h3>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="nama" class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" id="nama" class="form-control" placeholder="Masukkan nama lengkap" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="nama@email.com" required>
            </div>

            <div class="mb-3">
                <label for="no_telepon" class="form-label">No. Telepon (Opsional)</label>
                <input type="text" name="no_telepon" id="no_telepon" class="form-control" placeholder="08xxxxxxxxxx">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Minimal 8 karakter" required>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Ulangi password" required>
            </div>

            <button type="submit" name="register" class="btn btn-primary w-100 py-2 mt-2">
                Daftar
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">Sudah punya akun? <a href="login.php" class="text-decoration-none">Login di sini</a></small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>