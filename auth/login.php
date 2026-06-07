<?php
include '../config/connection.php'; 

session_start();

if (isset($_SESSION['id_pengguna']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'pemilik') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../customer/homepage.php");
    }
    exit();
}

$error = '';

if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Semua data harus diisi";
    } else {
        $query = "SELECT * FROM pengguna WHERE email = '$email' LIMIT 1";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);

            // ── PERBAIKAN DI SINI: Menggunakan password_verify untuk mengecek hash ──
            if (password_verify($password, $user['password'])) {
                $_SESSION['id_pengguna'] = $user['id_pengguna'];
                $_SESSION['nama_pengguna'] = $user['nama_pengguna'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin' || $user['role'] === 'pemilik') {
                    header("Location: ../admin/dashboard.php");
                } else {
                    header("Location: ../customer/homepage.php");
                }
                exit();

            } else {
                $error = "Password salah";
            }
        } else {
            $error = "Email tidak ditemukan";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MyField</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin-top: 100px; }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="login-container w-100 p-4 bg-white rounded shadow-sm border">
        <h3 class="text-center mb-4 fw-bold text-primary">MyField Login</h3>

        <?php if ($error != "") : ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="nama@email.com" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required>
            </div>

            <button type="submit" name="login" class="btn btn-primary w-100 py-2 mt-2">
                Login
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">Belum punya akun? <a href="register.php" class="text-decoration-none">Daftar Sekarang</a></small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>