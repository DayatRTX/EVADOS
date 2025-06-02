<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$js_force_sidebar_closed = 'false'; // Sidebar mengikuti localStorage

require_once '../includes/auth_check_dosen.php';
require_once '../config/db.php';

// Tandai semua notifikasi yang belum dibaca sebagai sudah dibaca SAAT halaman ini dibuka
$update_stmt = $conn->prepare("UPDATE Messages SET is_read = TRUE, read_at = NOW() WHERE receiver_user_id = ? AND is_read = FALSE");
if ($update_stmt) {
    $update_stmt->bind_param("i", $loggedInDosenId);
    $update_stmt->execute();
    $update_stmt->close();
} else {
    error_log("Dosen Notifikasi: Gagal mempersiapkan statement update is_read: " . $conn->error);
}

// Ambil semua notifikasi untuk dosen yang login, urutkan dari yang terbaru
// (setelah diupdate, $has_unread_notifications di sini akan jadi false jika tidak ada notif baru lagi)
$notifications = [];
$stmt_notif = $conn->prepare("SELECT subject, content, sent_at, message_type, is_read, read_at FROM Messages WHERE receiver_user_id = ? ORDER BY sent_at DESC");
if ($stmt_notif) {
    $stmt_notif->bind_param("i", $loggedInDosenId);
    $stmt_notif->execute();
    $result_notif = $stmt_notif->get_result();
    while ($row_notif = $result_notif->fetch_assoc()) {
        $notifications[] = $row_notif;
    }
    $stmt_notif->close();
} else {
    error_log("Dosen Notifikasi: Gagal prepare statement ambil notifikasi: " . $conn->error);
}

// Cek ulang notifikasi belum dibaca untuk tampilan sidebar di halaman ini (setelah update)
$has_unread_notifications = false; // Reset, karena mungkin sudah dibaca semua oleh update di atas
$stmt_unread_check_sidebar = $conn->prepare("SELECT COUNT(message_id) as unread_count FROM Messages WHERE receiver_user_id = ? AND is_read = FALSE");
if ($stmt_unread_check_sidebar) {
    $stmt_unread_check_sidebar->bind_param("i", $loggedInDosenId);
    $stmt_unread_check_sidebar->execute();
    $result_unread_check_sidebar = $stmt_unread_check_sidebar->get_result();
    if ($row_unread_sidebar = $result_unread_check_sidebar->fetch_assoc()) {
        if ($row_unread_sidebar['unread_count'] > 0) {
            $has_unread_notifications = true;
        }
    }
    $stmt_unread_check_sidebar->close();
}


$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Evados</title>
    <link rel="icon" href="../logo.png" type="image/png" />
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
                    <li><a href="dosen_dashboard.php"
                            class="<?php echo ($current_page == 'dosen_dashboard.php') ? 'active' : ''; ?>"><i
                                class="fas fa-chart-line"></i> <span class="menu-text">Ringkasan Evaluasi</span></a>
                    </li>
                    <li><a href="dosen_komentar.php"
                            class="<?php echo ($current_page == 'dosen_komentar.php') ? 'active' : ''; ?>"><i
                                class="fas fa-comments"></i> <span class="menu-text">Komentar</span></a></li>
                    <li>
                        <a href="dosen_notifikasi.php"
                            class="<?php echo ($current_page == 'dosen_notifikasi.php') ? 'active' : ''; ?>">
                            <i class="fas fa-bell"></i>
                            <span class="menu-text">Notifikasi</span>
                            <?php if ($has_unread_notifications): ?>
                                <span class="notification-dot-indicator"></span>
                            <?php endif; ?>
                        </a>
                    </li>
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
                <h1>Notifikasi dari Ketua Jurusan</h1>
            </header>

            <section class="content-card">
                <h2><i class="fas fa-envelope-open-text"></i> Daftar Notifikasi</h2>
                <?php if (!empty($notifications)): ?>
                    <ul class="notification-list">
                        <?php foreach ($notifications as $notif): ?>
                            <li class="notification-item <?php echo !$notif['is_read'] ? 'unread-notif' : ''; ?>">
                                <span class="date">
                                    Diterima: <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($notif['sent_at']))); ?>
                                    <?php if ($notif['is_read'] && $notif['read_at']): ?>
                                        | Dibaca: <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($notif['read_at']))); ?>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <span class="type type-<?php echo htmlspecialchars($notif['message_type']); ?>">
                                        <?php
                                        $icon_type = 'fa-info-circle';
                                        if ($notif['message_type'] == 'peringatan')
                                            $icon_type = 'fa-exclamation-triangle';
                                        if ($notif['message_type'] == 'panggilan')
                                            $icon_type = 'fa-bullhorn';
                                        ?>
                                        <i class="fas <?php echo $icon_type; ?>"></i>
                                        <?php echo htmlspecialchars(ucfirst($notif['message_type'])); ?>
                                    </span>
                                </div>
                                <h4><?php echo htmlspecialchars($notif['subject'] ?: '(Tanpa Subjek)'); ?></h4>
                                <p><?php echo nl2br(htmlspecialchars($notif['content'])); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Tidak ada notifikasi untuk Anda saat ini.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>