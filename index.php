<?php

session_start();

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'mahasiswa') {
        header("Location: mahasiswa/mahasiswa_dashboard.php"); 
        exit();
    } elseif ($_SESSION['role'] == 'dosen') {
        header("Location: dosen/dosen_dashboard.php"); 
        exit();
    } elseif ($_SESSION['role'] == 'kajur') {
        header("Location: kajur/kajur_dashboard.php"); 
        exit();
    } elseif ($_SESSION['role'] == 'admin') { 
        header("Location: admin/admin_dashboard.php");
        exit();
    }
}
header("Location: login.php"); //
exit();
?>