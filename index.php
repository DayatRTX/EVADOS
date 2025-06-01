<?php
// evados/index.php
session_start();

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'mahasiswa') {
        header("Location: mahasiswa/mahasiswa_dashboard.php"); //
        exit();
    } elseif ($_SESSION['role'] == 'dosen') {
        header("Location: dosen/dosen_dashboard.php"); //
        exit();
    } elseif ($_SESSION['role'] == 'kajur') {
        header("Location: kajur/kajur_dashboard.php"); //
        exit();
    } elseif ($_SESSION['role'] == 'admin') { // Tambahan untuk Admin
        header("Location: admin/admin_dashboard.php");
        exit();
    }
}
// Jika belum login atau role tidak cocok (atau role admin belum dihandle di atas), arahkan ke login
header("Location: login.php"); //
exit();
?>