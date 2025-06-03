<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$js_force_sidebar_closed = 'false'; // Sidebar mengikuti localStorage

require_once '../includes/auth_check_kajur.php';
require_once '../config/db.php';

$message_sent_status = ''; // Tidak digunakan lagi, diganti session flash
$error_message = '';
$target_dosen_id_from_get = isset($_GET['dosen_id']) && is_numeric($_GET['dosen_id']) ? (int) $_GET['dosen_id'] : null;

// Ambil daftar dosen untuk pilihan
$dosen_options = [];
// PERBAIKAN: Spesifikasikan kolom user_id dengan alias tabel (au.user_id)
$sql_dosen_opt = "SELECT au.user_id, au.full_name, d.nidn 
                  FROM Auth_Users au 
                  JOIN Dosen d ON au.user_id = d.user_id 
                  WHERE au.role = 'dosen' 
                  ORDER BY au.full_name ASC";
$res_dosen_opt = $conn->query($sql_dosen_opt);
if ($res_dosen_opt && $res_dosen_opt->num_rows > 0) {
    while ($row = $res_dosen_opt->fetch_assoc()) {
        $dosen_options[] = $row;
    }
} else if ($conn->error) {
    error_log("Kajur Kirim Pesan: Gagal query dosen options: " . $conn->error);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $receiver_user_id = $_POST['receiver_user_id'] ?? null;
    $message_type = $_POST['message_type'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($receiver_user_id) || empty($message_type) || empty($content)) {
        $error_message = "Penerima, Jenis Pesan, dan Isi Pesan tidak boleh kosong.";
    } else {
        $stmt_send = $conn->prepare("INSERT INTO Messages (sender_user_id, receiver_user_id, message_type, subject, content, sent_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt_send) {
            $stmt_send->bind_param("iisss", $loggedInKajurId, $receiver_user_id, $message_type, $subject, $content);
            if ($stmt_send->execute()) {
                $_SESSION['success_message_kajur_dashboard'] = "Pesan berhasil dikirim kepada dosen terkait."; // Pesan untuk dashboard
                header("Location: kajur_riwayat_pesan.php"); // Arahkan ke riwayat pesan
                exit();
            } else {
                $error_message = "Gagal mengirim pesan: " . $stmt_send->error;
            }
            $stmt_send->close();
        } else {
            $error_message = "Gagal mempersiapkan statement pengiriman pesan: " . $conn->error;
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirim Pesan ke Dosen - Evados</title>
    <link rel="icon" href="../logo.png" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_force_sidebar_closed; ?>;
    </script>
</head>

<body>
    <div class="mahasiswa-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-top-section">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="sidebar-header">
                    <h3 class="logo-text">Evados</h3>
                </div>
            </div>
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="kajur_dashboard.php"
                            class="<?php echo ($current_page == 'kajur_dashboard.php') ? 'active' : ''; ?>"><i
                                class="fas fa-tachometer-alt"></i> <span class="menu-text">Dashboard Jurusan</span></a>
                    </li>
                    <li><a href="kajur_kirim_pesan.php"
                            class="<?php echo ($current_page == 'kajur_kirim_pesan.php') ? 'active' : ''; ?>"><i
                                class="fas fa-paper-plane"></i> <span class="menu-text">Kirim Pesan</span></a></li>
                    <li><a href="kajur_riwayat_pesan.php"
                            class="<?php echo ($current_page == 'kajur_riwayat_pesan.php') ? 'active' : ''; ?>"><i
                                class="fas fa-history"></i> <span class="menu-text">Riwayat Pesan</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-logout-section">
                <a href="../logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Logout</span>
                </a>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <header class="header">
                <h1>Kirim Pesan/Notifikasi ke Dosen</h1>
            </header>

            <section class="content-card form-container">
                <h2><i class="fas fa-envelope"></i> Buat Pesan Baru</h2>
                <?php if (!empty($error_message)): ?>
                    <p class="error-msg"><?php echo htmlspecialchars($error_message); ?></p>
                <?php endif; ?>

                <form action="kajur_kirim_pesan.php" method="POST">
                    <div class="input-group">
                        <label for="receiver_user_id">Kirim Ke Dosen:</label>
                        <select name="receiver_user_id" id="receiver_user_id" required>
                            <option value="">-- Pilih Dosen --</option>
                            <?php if (!empty($dosen_options)): ?>
                                <?php foreach ($dosen_options as $dosen_opt): ?>
                                    <option value="<?php echo $dosen_opt['user_id']; ?>" <?php echo ($target_dosen_id_from_get == $dosen_opt['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dosen_opt['full_name']) . " (" . htmlspecialchars($dosen_opt['nidn']) . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Tidak ada data dosen</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="message_type">Jenis Pesan:</label>
                        <select name="message_type" id="message_type" required>
                            <option value="pesan">Pesan Biasa</option>
                            <option value="peringatan">Peringatan</option>
                            <option value="panggilan">Panggilan</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="subject">Subjek (Opsional):</label>
                        <input type="text" name="subject" id="subject" placeholder="Subjek pesan...">
                    </div>
                    <div class="input-group">
                        <label for="content">Isi Pesan:</label>
                        <textarea name="content" id="content" rows="6" placeholder="Tulis isi pesan Anda di sini..."
                            required></textarea>
                    </div>
                    <button type="submit" class="btn-kirim-penilaian"> <i class="fas fa-paper-plane"></i> Kirim Pesan
                    </button>
                </form>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>