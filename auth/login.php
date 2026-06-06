<?php
include '../config/connection.php'; 

session_start();
$error = '';

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    //validasi input kosong
    if (empty($email) || empty($password)) {
        $error = "Semua data harus diisi";
    } else {
        //cek email di database
        $query = "SELECT * FROM pengguna WHERE email = '$email'";
        $result = mysqli_query($conn, $query);

        //validasi email
        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);

            //validasi password
            if (password_verify($password, $user['password'])) {
                //set session
                $_SESSION['id_pengguna'] = $user['id_pengguna'];
                $_SESSION['nama_pengguna'] = $user['nama_pengguna'];
                $_SESSION['role'] = $user['role'];

                //redirect berdasarkan role
                if ($user['role'] === 'admin') {
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
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h2>Login</h2>
    <?php if ($error != "") : ?>
        <p style="color:red;">
            <?= $error; ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <input type="email" name="email" placeholder="Email">
        <br><br>

        <input type="password" name="password" placeholder="Password">
        <br><br>

        <button type="submit" name="login">
            Login
        </button>

    </form>

</body>
</html>
