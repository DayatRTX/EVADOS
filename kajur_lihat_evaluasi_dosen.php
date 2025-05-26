<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$js_force_sidebar_closed = 'false';

require_once 'includes/auth_check_kajur.php'; //
require_once 'config/db.php'; //

$target_dosen_id = null;
$target_dosen_name = "N/A";
$target_dosen_nidn = "N/A";

if (!isset($_GET['dosen_id']) || !is_numeric($_GET['dosen_id'])) {
    $_SESSION['error_message_kajur_dashboard'] = "ID Dosen tidak valid atau tidak disediakan untuk dilihat detailnya.";
    header("Location: kajur_dashboard.php");
    exit();
}
$target_dosen_id = intval($_GET['dosen_id']);

$stmt_target_dosen = $conn->prepare("SELECT au.full_name, d.nidn FROM Auth_Users au JOIN Dosen d ON au.user_id = d.user_id WHERE au.user_id = ? AND au.role='dosen'");
if ($stmt_target_dosen) {
    $stmt_target_dosen->bind_param("i", $target_dosen_id);
    $stmt_target_dosen->execute();
    $res_target_dosen = $stmt_target_dosen->get_result();
    if ($row_target_dosen = $res_target_dosen->fetch_assoc()) {
        $target_dosen_name = $row_target_dosen['full_name'];
        $target_dosen_nidn = $row_target_dosen['nidn'];
    } else {
        $_SESSION['error_message_kajur_dashboard'] = "Data dosen dengan ID tersebut tidak ditemukan.";
        header("Location: kajur_dashboard.php");
        exit();
    }
    $stmt_target_dosen->close();
} else {
    error_log("Kajur Lihat Evaluasi: Gagal prepare statement untuk target_dosen: " . $conn->error);
    $_SESSION['error_message_kajur_dashboard'] = "Kesalahan database saat mengambil data dosen.";
    header("Location: kajur_dashboard.php");
    exit();
}

$periode_evaluasi_display = "Semua Periode";
$overall_avg = 0.00;
$total_evals = 0;
$stmt_overall = $conn->prepare("SELECT AVG(submission_average) as overall_avg, COUNT(evaluation_id) as total_evals FROM Evaluations WHERE lecturer_user_id = ?");
if ($stmt_overall) {
    $stmt_overall->bind_param("i", $target_dosen_id);
    $stmt_overall->execute();
    $result_overall = $stmt_overall->get_result();
    if ($row_overall = $result_overall->fetch_assoc()) {
        $overall_avg = $row_overall['overall_avg'] ? round($row_overall['overall_avg'], 2) : 0.00;
        $total_evals = (int) $row_overall['total_evals'];
    }
    $stmt_overall->close();
} else {
    error_log("Kajur Lihat Evaluasi: Gagal prepare statement untuk overall_avg: " . $conn->error);
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
    $stmt_aspek->bind_param("i", $target_dosen_id);
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
    error_log("Kajur Lihat Evaluasi: Gagal prepare statement untuk aspek_scores: " . $conn->error);
}

// Nama kategori untuk tampilan
$category_display_names = [
    'A' => 'Kompetensi Profesional',
    'B' => 'Kompetensi Personal',
    'C' => 'Kompetensi Sosial'
];

// Teks pertanyaan
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

$comments = [];
$stmt_comments = $conn->prepare("SELECT comment, evaluation_date FROM Evaluations WHERE lecturer_user_id = ? AND comment IS NOT NULL AND TRIM(comment) != '' ORDER BY evaluation_date DESC");
if ($stmt_comments) {
    $stmt_comments->bind_param("i", $target_dosen_id);
    $stmt_comments->execute();
    $result_comments = $stmt_comments->get_result();
    while ($row_comment = $result_comments->fetch_assoc()) {
        $comments[] = $row_comment;
    }
    $stmt_comments->close();
} else {
    error_log("Kajur Lihat Evaluasi: Gagal prepare statement untuk comments: " . $conn->error);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Evaluasi Dosen: <?php echo htmlspecialchars($target_dosen_name); ?> - Evados</title>
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
                    <li><a href="kajur_dashboard.php"><i class="fas fa-arrow-left"></i> <span
                                class="menu-text">Kembali</span></a></li>
                    <li><a href="kajur_kirim_pesan.php?dosen_id=<?php echo $target_dosen_id; ?>"><i
                                class="fas fa-paper-plane"></i> <span class="menu-text">Kirim Pesan ke Dosen
                                Ini</span></a></li>
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
                <h1>Detail Evaluasi: <?php echo htmlspecialchars($target_dosen_name); ?> (NIDN:
                    <?php echo htmlspecialchars($target_dosen_nidn); ?>)
                </h1>
            </header>

            <div class="info-box">
                <h4>Ringkasan Umum Evaluasi Dosen</h4>
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
                            echo '<tr><td colspan="2" style="background-color: var(--background-color); font-weight:bold;">' . htmlspecialchars($category_display_names['A']) . '</td></tr>';
                            for ($i = 1; $i <= 12; $i++) {
                                $aspek_key = "Pertanyaan A" . $i;
                                if (isset($aspek_scores[$aspek_key]) && isset($questions_text_map[$aspek_key])) {
                                    echo "<tr><td>" . htmlspecialchars($questions_text_map[$aspek_key]) . "</td><td style='text-align:center;'>" . number_format($aspek_scores[$aspek_key], 2) . "</td></tr>";
                                }
                            }
                            echo '<tr><td colspan="2" style="background-color: var(--background-color); font-weight:bold;">' . htmlspecialchars($category_display_names['B']) . '</td></tr>';
                            for ($i = 1; $i <= 5; $i++) {
                                $aspek_key = "Pertanyaan B" . $i;
                                if (isset($aspek_scores[$aspek_key]) && isset($questions_text_map[$aspek_key])) {
                                    echo "<tr><td>" . htmlspecialchars($questions_text_map[$aspek_key]) . "</td><td style='text-align:center;'>" . number_format($aspek_scores[$aspek_key], 2) . "</td></tr>";
                                }
                            }
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
                    <p>Dosen ini belum memiliki data evaluasi untuk ditampilkan.</p>
                <?php endif; ?>
            </section>

            <section class="content-card" style="margin-top: 20px;">
                <h2><i class="fas fa-comment-dots"></i> Komentar Mahasiswa (Anonim)</h2>
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
                    <p>Tidak ada komentar yang diterima untuk dosen ini.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>