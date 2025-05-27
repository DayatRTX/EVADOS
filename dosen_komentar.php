<?php
// evados/dosen_komentar.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$js_force_sidebar_closed = 'false';

require_once 'includes/auth_check_dosen.php';
require_once 'config/db.php';

// --- Cek Notifikasi Belum Dibaca ---
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

// --- Pengambilan data untuk filter periode ---
$available_periods = [];
$stmt_periods = $conn->prepare(
    "SELECT DISTINCT semester_evaluasi, tahun_ajaran_evaluasi
     FROM Evaluations
     WHERE lecturer_user_id = ? AND comment IS NOT NULL AND TRIM(comment) != ''
     ORDER BY tahun_ajaran_evaluasi DESC, semester_evaluasi DESC"
);
if ($stmt_periods) {
    $stmt_periods->bind_param("i", $loggedInDosenId);
    $stmt_periods->execute();
    $result_periods = $stmt_periods->get_result();
    while ($row_period = $result_periods->fetch_assoc()) {
        $available_periods[] = $row_period;
    }
    $stmt_periods->close();
} else {
    error_log("Gagal mengambil periode tersedia (dosen_komentar): " . $conn->error);
}

// --- Logika Filter Periode ---
$selected_semester_filter = $_GET['semester_filter'] ?? 'semua';
$selected_tahun_ajaran_filter = $_GET['tahun_ajaran_filter'] ?? 'semua';

$periode_evaluasi_display = "Semua Periode";
$sql_condition_periode = "";
$params_periode = [];
$types_periode = "";

if ($selected_semester_filter !== 'semua' && $selected_tahun_ajaran_filter !== 'semua' && !empty($selected_semester_filter) && !empty($selected_tahun_ajaran_filter)) {
    $sql_condition_periode = " AND semester_evaluasi = ? AND tahun_ajaran_evaluasi = ? ";
    $params_periode[] = $selected_semester_filter;
    $params_periode[] = $selected_tahun_ajaran_filter;
    $types_periode .= "ss";
    $periode_evaluasi_display = htmlspecialchars($selected_semester_filter) . " - " . htmlspecialchars($selected_tahun_ajaran_filter);
}

// --- Pengambilan Komentar dengan Filter ---
$comments = [];
$sql_comments = "SELECT comment, evaluation_date
                 FROM Evaluations
                 WHERE lecturer_user_id = ? AND comment IS NOT NULL AND TRIM(comment) != '' $sql_condition_periode
                 ORDER BY evaluation_date DESC";
$stmt_comments = $conn->prepare($sql_comments);
if ($stmt_comments) {
    $all_params_comments = array_merge([$loggedInDosenId], $params_periode);
    $all_types_comments = "i" . $types_periode;

    if (!empty($params_periode)) {
        $stmt_comments->bind_param($all_types_comments, ...$all_params_comments);
    } else {
        $stmt_comments->bind_param("i", $loggedInDosenId);
    }
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
        function updatePeriodeFieldsDosen(selectedValue) {
            const semesterHidden = document.getElementById('semester_filter_hidden_dosen');
            const tahunAjaranHidden = document.getElementById('tahun_ajaran_filter_hidden_dosen');
            if (selectedValue === 'semua_semua') {
                semesterHidden.value = 'semua';
                tahunAjaranHidden.value = 'semua';
            } else {
                const parts = selectedValue.split('___');
                semesterHidden.value = parts[0];
                tahunAjaranHidden.value = parts[1];
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            const selectElement = document.getElementById('periode_filter_select_dosen');
            if (selectElement) {
                updatePeriodeFieldsDosen(selectElement.value);
            }
        });
    </script>
    <style>
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            padding: 15px 0px 20px 0px;
            border-bottom: 1px solid var(--tertiary-color);
            margin-bottom: 20px;
        }

        .filter-form .input-group {
            margin-bottom: 0;
            flex: 1 1 250px;
        }

        .filter-form .input-group label {
            font-size: 0.85em;
            margin-bottom: 4px;
            color: var(--text-color);
            opacity: 0.9;
            display: block;
        }

        .filter-form select,
        .filter-form button {
            height: 40px;
            padding: 8px 12px;
            font-size: 0.9em;
            border-radius: 6px;
        }

        .filter-form select {
            border: 1px solid var(--tertiary-color);
            width: 100%;
            box-sizing: border-box;
        }

        .filter-form button {
            border: none;
            cursor: pointer;
            background-color: var(--primary-color);
            color: var(--white-color);
            transition: background-color 0.3s ease;
        }

        .filter-form button:hover {
            background-color: var(--secondary-color);
        }

        .filter-form button i {
            margin-right: 6px;
        }
    </style>
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

                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" class="filter-form">
                    <div class="input-group">
                        <label for="periode_filter_select_dosen">Pilih Periode:</label>
                        <select name="periode_filter_select" id="periode_filter_select_dosen"
                            onchange="updatePeriodeFieldsDosen(this.value)">
                            <option value="semua_semua" <?php echo ($selected_semester_filter === 'semua') ? 'selected' : ''; ?>>Semua Periode</option>
                            <?php foreach ($available_periods as $period): ?>
                                <?php
                                $period_value = htmlspecialchars($period['semester_evaluasi']) . "___" . htmlspecialchars($period['tahun_ajaran_evaluasi']);
                                $period_display = htmlspecialchars($period['semester_evaluasi']) . " - " . htmlspecialchars($period['tahun_ajaran_evaluasi']);
                                $is_selected = ($selected_semester_filter === $period['semester_evaluasi'] && $selected_tahun_ajaran_filter === $period['tahun_ajaran_evaluasi']);
                                ?>
                                <option value="<?php echo $period_value; ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                    <?php echo $period_display; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="semester_filter" id="semester_filter_hidden_dosen"
                            value="<?php echo htmlspecialchars($selected_semester_filter); ?>">
                        <input type="hidden" name="tahun_ajaran_filter" id="tahun_ajaran_filter_hidden_dosen"
                            value="<?php echo htmlspecialchars($selected_tahun_ajaran_filter); ?>">
                    </div>
                    <button type="submit"><i class="fas fa-search"></i> Tampilkan</button>
                </form>
                <p style="font-size:0.9em; margin-top:0; color: #555;"><i>Menampilkan komentar untuk periode:
                        <?php echo $periode_evaluasi_display; ?></i></p>

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
                    <p>Belum ada komentar yang diterima untuk periode <?php echo strtolower($periode_evaluasi_display); ?>.
                    </p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>
