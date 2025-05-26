<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$js_force_sidebar_closed = 'false'; // Sidebar di halaman ini mengikuti localStorage

require_once 'includes/auth_check_dosen.php';
require_once 'config/db.php';

// --- LOGIKA UNTUK CEK NOTIFIKASI BELUM DIBACA ---
$has_unread_notifications = false;
$stmt_unread_check = $conn->prepare("SELECT COUNT(message_id) as unread_count FROM Messages WHERE receiver_user_id = ? AND is_read = FALSE");
if ($stmt_unread_check) {
    $stmt_unread_check->bind_param("i", $loggedInDosenId);
    $stmt_unread_check->execute();
    $result_unread_check = $stmt_unread_check->get_result();
    if ($row_unread = $result_unread_check->fetch_assoc()) {
        if ($row_unread['unread_count'] > 0) {
            $has_unread_notifications = true;
        }
    }
    $stmt_unread_check->close();
} else {
    error_log("Gagal prepare statement untuk cek notifikasi belum dibaca (dosen_komentar): " . $conn->error);
}
// --- AKHIR LOGIKA CEK NOTIFIKASI ---

$periode_evaluasi_display = "Semua Periode"; // Placeholder

$comments = [];
$stmt_comments = $conn->prepare("SELECT comment, evaluation_date FROM Evaluations WHERE lecturer_user_id = ? AND comment IS NOT NULL AND TRIM(comment) != '' ORDER BY evaluation_date DESC");
if ($stmt_comments) {
    $stmt_comments->bind_param("i", $loggedInDosenId);
    $stmt_comments->execute();
    $result_comments = $stmt_comments->get_result();
    while ($row_comment = $result_comments->fetch_assoc()) {
        $comments[] = $row_comment;
    }
    $stmt_comments->close();
} else {
    error_log("Dosen Komentar: Gagal mempersiapkan statement: " . $conn->error);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Komentar Mahasiswa - Evados</title>
    <link rel="stylesheet" href="css/style.css">
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
                <a href="logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Logout</span>
                </a>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <header class="header">
                <h1>Komentar Mahasiswa (Anonim)</h1>
            </header>

            <section class="content-card">
                <h2><i class="fas fa-comment-dots"></i> Daftar Komentar untuk Anda</h2>
                <p><strong>Periode Data:</strong> <?php echo htmlspecialchars($periode_evaluasi_display); ?></p>
                <?php if (!empty($comments)): ?>
                    <ul class="comment-list">
                        <?php foreach ($comments as $comment_data): ?>
                            <li class="comment-item">
                                <span class="comment-date">Diterima pada:
                                    <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($comment_data['evaluation_date']))); ?></span>
                                <p><?php echo nl2br(htmlspecialchars($comment_data['comment'])); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Belum ada komentar yang diterima.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>