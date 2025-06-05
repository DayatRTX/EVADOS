<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$js_force_sidebar_closed = 'false';

require_once '../includes/auth_check_kajur.php';
require_once '../config/db.php';

$sent_messages = [];
// Ambil pesan yang dikirim oleh Kajur yang login
$stmt_sent = $conn->prepare("SELECT m.subject, m.content, m.sent_at, m.message_type, m.is_read, m.read_at, au_receiver.full_name as receiver_name 
                             FROM Messages m
                             JOIN Auth_Users au_receiver ON m.receiver_user_id = au_receiver.user_id
                             WHERE m.sender_user_id = ? 
                             ORDER BY m.sent_at DESC");
if ($stmt_sent) {
    $stmt_sent->bind_param("i", $loggedInKajurId);
    $stmt_sent->execute();
    $result_sent = $stmt_sent->get_result();
    while ($row_sent = $result_sent->fetch_assoc()) {
        $sent_messages[] = $row_sent;
    }
    $stmt_sent->close();
} else {
    error_log("Kajur Riwayat Pesan: Gagal prepare statement: " . $conn->error);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesan Terkirim - EVADOS</title>
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
                    <h3 class="logo-text">EVADOS</h3>
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
                <h1>Riwayat Pesan Terkirim</h1>
            </header>

            <section class="content-card">
                <h2><i class="fas fa-list-alt"></i> Pesan yang Telah Anda Kirim</h2>
                <?php if (!empty($sent_messages)): ?>
                    <ul class="message-list">
                        <?php foreach ($sent_messages as $msg): ?>
                            <li class="message-item">
                                <span class="date">Terkirim:
                                    <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($msg['sent_at']))); ?></span>
                                <div class="recipient">Kepada: <?php echo htmlspecialchars($msg['receiver_name']); ?></div>
                                <div>
                                    <span class="type type-<?php echo htmlspecialchars($msg['message_type']); ?>">
                                        <i class="fas <?php
                                        if ($msg['message_type'] == 'peringatan')
                                            echo 'fa-exclamation-triangle';
                                        elseif ($msg['message_type'] == 'panggilan')
                                            echo 'fa-bullhorn';
                                        else
                                            echo 'fa-info-circle';
                                        ?>"></i>
                                        <?php echo htmlspecialchars(ucfirst($msg['message_type'])); ?>
                                    </span>
                                    <?php if ($msg['is_read']): ?>
                                        <span class="read-status">(Dibaca pada:
                                            <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($msg['read_at']))); ?>)</span>
                                    <?php else: ?>
                                        <span class="unread-status">(Belum dibaca)</span>
                                    <?php endif; ?>
                                </div>
                                <h4><?php echo htmlspecialchars($msg['subject'] ?: '(Tanpa Subjek)'); ?></h4>
                                <p><?php echo nl2br(htmlspecialchars($msg['content'])); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Anda belum mengirim pesan apapun.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>