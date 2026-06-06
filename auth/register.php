<?php
include '../config/connection.php';

session_start();

// Redirect jika sudah login
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

    // Validasi input kosong
    if (empty($nama) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = "Semua data harus diisi";

    // Validasi format email
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid";

    // Validasi panjang password
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter";

    // Validasi konfirmasi password
    } elseif ($password !== $confirmPassword) {
        $error = "Konfirmasi password tidak sesuai";

    } else {
        // Cek email sudah terdaftar (prepared statement)
        $stmt = mysqli_prepare($conn, "SELECT id_pengguna FROM pengguna WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Email sudah terdaftar";
        } else {
            mysqli_stmt_close($stmt);

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert data (prepared statement)
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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Register — MyField</title>
</head>
<body>
    <h2>Register</h2>
    <?php if ($error !== ''): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <p style="color:green;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text"     name="nama"             placeholder="Nama Lengkap"         required>
        <br><br>
        <input type="email"    name="email"            placeholder="Email"                required>
        <br><br>
        <input type="text"     name="no_telepon"       placeholder="No. Telepon (opsional)">
        <br><br>
        <input type="password" name="password"         placeholder="Password (min. 8 karakter)" required>
        <br><br>
        <input type="password" name="confirm_password" placeholder="Konfirmasi Password"  required>
        <br><br>
        <button type="submit" name="register">Daftar</button>
    </form>
    <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
</body>
</html>