<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$js_force_sidebar_closed = 'false';
if (isset($_SESSION['initial_dashboard_load_sidebar_closed']) && $_SESSION['initial_dashboard_load_sidebar_closed'] === true) {
    $js_force_sidebar_closed = 'true';
    unset($_SESSION['initial_dashboard_load_sidebar_closed']);
}

require_once 'includes/auth_check_dosen.php'; //
require_once 'config/db.php'; //

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
    error_log("Gagal prepare statement untuk cek notifikasi belum dibaca (dosen_dashboard): " . $conn->error);
}

$periode_evaluasi_display = "Semua Periode";
$overall_avg = 0.00;
$total_evals = 0;
$stmt_overall = $conn->prepare("SELECT AVG(submission_average) as overall_avg, COUNT(evaluation_id) as total_evals FROM Evaluations WHERE lecturer_user_id = ?");
if ($stmt_overall) {
    $stmt_overall->bind_param("i", $loggedInDosenId);
    $stmt_overall->execute();
    $result_overall = $stmt_overall->get_result();
    if ($row_overall = $result_overall->fetch_assoc()) {
        $overall_avg = $row_overall['overall_avg'] ? round((float) $row_overall['overall_avg'], 2) : 0.00;
        $total_evals = (int) $row_overall['total_evals'];
    }
    $stmt_overall->close();
} else {
    error_log("Dasbor Dosen: Gagal mempersiapkan statement untuk skor keseluruhan: " . $conn->error);
}

$aspek_scores = [];
$question_fields_array = [];
for ($i = 1; $i <= 12; $i++) {
    $question_fields_array[] = "AVG(q{$i}_score) as avg_q{$i}";
}
for ($i = 1; $i <= 5; $i++) {
    $question_fields_array[] = "AVG(qb{$i}_score) as avg_qb{$i}";
}
for ($i = 1; $i <= 3; $i++) {
    $question_fields_array[] = "AVG(qc{$i}_score) as avg_qc{$i}";
}
$question_fields = implode(", ", $question_fields_array);

$sql_aspek = "SELECT $question_fields FROM Evaluations WHERE lecturer_user_id = ?";
$stmt_aspek = $conn->prepare($sql_aspek);
if ($stmt_aspek) {
    $stmt_aspek->bind_param("i", $loggedInDosenId);
    $stmt_aspek->execute();
    $result_aspek = $stmt_aspek->get_result();
    if ($row_aspek = $result_aspek->fetch_assoc()) {
        for ($i = 1; $i <= 12; $i++) {
            $aspek_scores["Pertanyaan A" . $i] = $row_aspek['avg_q' . $i] ? round($row_aspek['avg_q' . $i], 2) : 0.00;
        }
        for ($i = 1; $i <= 5; $i++) {
            $aspek_scores["Pertanyaan B" . $i] = $row_aspek['avg_qb' . $i] ? round($row_aspek['avg_qb' . $i], 2) : 0.00;
        }
        for ($i = 1; $i <= 3; $i++) {
            $aspek_scores["Pertanyaan C" . $i] = $row_aspek['avg_qc' . $i] ? round($row_aspek['avg_qc' . $i], 2) : 0.00;
        }
    }
    $stmt_aspek->close();
} else {
    error_log("Dasbor Dosen: Gagal mempersiapkan statement untuk skor aspek: " . $conn->error);
}

// Nama kategori untuk tampilan
$category_display_names = [
    'A' => 'Kompetensi Profesional',
    'B' => 'Kompetensi Personal',
    'C' => 'Kompetensi Sosial'
];

// Teks pertanyaan (kunci harus cocok dengan yang digunakan di $aspek_scores)
$questions_text_map = [
    "Pertanyaan A1" => "1. Menjelaskan silabus, buku acuan (referensi) dan aturan penilaian pada awal perkuliahan.",
    "Pertanyaan A2" => "2. Penguasaan materi kuliah.",
    "Pertanyaan A3" => "3. Menjelaskan/menerangkan materi kuliah.",
    "Pertanyaan A4" => "4. Penggunaan media ajar (laptop, LCD, proyektor, internet, dsb).",
    "Pertanyaan A5" => "5. Kemampuan membangkitkan minat/motivasi pada mahasiswa.",
    "Pertanyaan A6" => "6. Memberikan tanggapan atas pertanyaan mahasiswa.",
    "Pertanyaan A7" => "7. Memberikan contoh yang relevan atas materi yang diberikan.",
    "Pertanyaan A8" => "8. Kesesuaian antara materi dan silabus.",
    "Pertanyaan A9" => "9. Menyediakan bahan ajar (diktat, modul, handout, dsb).",
    "Pertanyaan A10" => "10. Memberikan tugas yang relevan dan bermanfaat.",
    "Pertanyaan A11" => "11. Memberikan umpan balik setiap tugas atau ujian.",
    "Pertanyaan A12" => "12. Menyediakan waktu di luar jam kuliah.",
    "Pertanyaan B1" => "1. Penampilan/perilaku dalam perkuliahan.",
    "Pertanyaan B2" => "2. Meningkatkan minat (memberi motivasi) belajar",
    "Pertanyaan B3" => "3. Disiplin waktu (tepat waktu masuk dan keluar kelas).",
    "Pertanyaan B4" => "4. Gaya bicara dan tutur bahasa.",
    "Pertanyaan B5" => "5. Simpati dan menarik.",
    "Pertanyaan C1" => "1. Memberikan kesempatan bertanya.",
    "Pertanyaan C2" => "2. Perhatian terhadap mahasiswa, misalnya dengan mengabsen setiap awal kuliah.",
    "Pertanyaan C3" => "3. Ramah dan bersahabat.",
];

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen - Evados</title>
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
                            class="<?php echo ($current_page == 'dosen_notifikasi.php') ? 'active' : ''; ?>"> <i
                                class="fas fa-bell"></i>
                            <span class="menu-text">Notifikasi</span>
                            <?php if ($has_unread_notifications): ?>
                                <span class="notification-dot-indicator"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="sidebar-logout-section">
                <a href="logout.php" class="logout-link"> <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Logout</span>
                </a>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <header class="header">
                <h1>Dashboard Dosen: <?php echo htmlspecialchars($loggedInDosenFullName); ?></h1>
            </header>

            <div class="info-box">
                <h4>Ringkasan Umum Evaluasi Anda</h4>
                <p><strong>Periode Data:</strong> <?php echo htmlspecialchars($periode_evaluasi_display); ?></p>
                <p>Total Evaluasi Diterima: <strong class="score-highlight"><?php echo $total_evals; ?></strong></p>
                <p>Skor Rata-Rata Keseluruhan: <strong
                        class="score-highlight"><?php echo number_format($overall_avg, 2); ?> / 4.00</strong></p>
            </div>

            <section class="content-card">
                <h2><i class="fas fa-clipboard-check"></i> Rata-Rata Skor per Aspek Penilaian</h2>
                <?php if ($total_evals > 0 && !empty($aspek_scores)): ?>
                    <table class="aspek-table">
                        <thead>
                            <tr>
                                <th>Aspek Penilaian</th>
                                <th style="width:120px; text-align:center;">Skor Rata-Rata</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Menampilkan Kompetensi Profesional
                            echo '<tr><td colspan="2" style="background-color: var(--background-color); font-weight:bold;">' . htmlspecialchars($category_display_names['A']) . '</td></tr>';
                            for ($i = 1; $i <= 12; $i++) {
                                $aspek_key = "Pertanyaan A" . $i;
                                if (isset($aspek_scores[$aspek_key]) && isset($questions_text_map[$aspek_key])) {
                                    echo "<tr><td>" . htmlspecialchars($questions_text_map[$aspek_key]) . "</td><td style='text-align:center;'>" . number_format($aspek_scores[$aspek_key], 2) . "</td></tr>";
                                }
                            }
                            // Menampilkan Kompetensi Personal
                            echo '<tr><td colspan="2" style="background-color: var(--background-color); font-weight:bold;">' . htmlspecialchars($category_display_names['B']) . '</td></tr>';
                            for ($i = 1; $i <= 5; $i++) {
                                $aspek_key = "Pertanyaan B" . $i;
                                if (isset($aspek_scores[$aspek_key]) && isset($questions_text_map[$aspek_key])) {
                                    echo "<tr><td>" . htmlspecialchars($questions_text_map[$aspek_key]) . "</td><td style='text-align:center;'>" . number_format($aspek_scores[$aspek_key], 2) . "</td></tr>";
                                }
                            }
                            // Menampilkan Kompetensi Sosial
                            echo '<tr><td colspan="2" style="background-color: var(--background-color); font-weight:bold;">' . htmlspecialchars($category_display_names['C']) . '</td></tr>';
                            for ($i = 1; $i <= 3; $i++) {
                                $aspek_key = "Pertanyaan C" . $i;
                                if (isset($aspek_scores[$aspek_key]) && isset($questions_text_map[$aspek_key])) {
                                    echo "<tr><td>" . htmlspecialchars($questions_text_map[$aspek_key]) . "</td><td style='text-align:center;'>" . number_format($aspek_scores[$aspek_key], 2) . "</td></tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Belum ada data evaluasi yang diterima untuk ditampilkan.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>