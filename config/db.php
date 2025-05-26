<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Ganti jika username database Anda berbeda
define('DB_PASSWORD', '');      // Ganti jika password database Anda berbeda
define('DB_NAME', 'evados');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Koneksi Gagal: " . $conn->connect_error);
}
// Atur charset ke utf8mb4 untuk dukungan karakter yang lebih baik
$conn->set_charset("utf8mb4");
?>