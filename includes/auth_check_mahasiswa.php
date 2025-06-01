<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'mahasiswa') {
    $_SESSION['error_message_login'] = "Anda harus login sebagai mahasiswa untuk mengakses halaman ini.";
    header("Location: ../login.php");
    exit();
}

$loggedInUserId = $_SESSION['user_id'];
$loggedInUserFullName = $_SESSION['full_name'] ?? 'Mahasiswa'; // Fallback jika full_name tidak ada di sesi
?>