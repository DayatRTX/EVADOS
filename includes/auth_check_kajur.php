<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login dan rolenya adalah kajur
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'kajur') {
    $_SESSION['error_message_login'] = "Akses ditolak. Silahkan login sebagai Ketua Jurusan.";
    header("Location: ../login.php");
    exit();
}

$loggedInKajurId = $_SESSION['user_id'];
$loggedInKajurFullName = $_SESSION['full_name'] ?? 'Ketua Jurusan'; // Fallback
?>