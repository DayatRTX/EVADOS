<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login dan rolenya adalah dosen
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dosen') {
    $_SESSION['error_message_login'] = "Akses ditolak. Silahkan login sebagai Dosen untuk mengakses halaman ini.";
    header("Location: ../login.php");
    exit();
}

$loggedInDosenId = $_SESSION['user_id'];
$loggedInDosenFullName = $_SESSION['full_name'] ?? 'Dosen Yth.'; // Fallback jika full_name tidak ada di sesi
?>