<?php
session_start();

if (isset($_SESSION['id_pengguna'])) {
    $role = $_SESSION['role'];

    if ($role = 'admin' || $role = 'pemilik') {
        header("Location: admin/dashboard.php");
        exit();
    } else {
        header("Location: customer/homepage.php");
        exit();
    }
} else {
    header("Location: auth/login.php");
    exit();
}
?>
