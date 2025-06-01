<?php
// evados/includes/auth_check_admin.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login dan rolenya adalah admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message_login'] = "Akses ditolak. Silakan login sebagai Administrator.";
    header("Location: ../login.php");
    exit();
}

$loggedInAdminId = $_SESSION['user_id'];
$loggedInAdminFullName = $_SESSION['full_name'] ?? 'Administrator'; // Fallback
?>