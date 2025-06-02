<?php
// evados/kajur_lihat_evaluasi_dosen.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$js_force_sidebar_closed = 'false';

require_once '../includes/auth_check_kajur.php';
require_once '../config/db.php';

$target_dosen_id = null;
$target_dosen_name = "N/A";
$target_dosen_nidn = "N/A";

if (!isset($_GET['dosen_id']) || !is_numeric($_GET['dosen_id'])) {
    $_SESSION['error_message_kajur_dashboard'] = "ID Dosen tidak valid atau tidak disediakan untuk dilihat detailnya.";
    header("Location: kajur_dashboard.php");
    exit();
}
$target_dosen_id = intval($_GET['dosen_id']);

$stmt_target_dosen = $conn->prepare("SELECT au.full_name, d.nidn FROM Auth_Users au JOIN Dosen d ON au.user_id = d.user_id WHERE au.user_id = ? AND au.role='dosen'"); //
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

// --- Pengambilan data untuk filter periode ---
$available_periods_detail = [];
$stmt_periods_detail = $conn->prepare(
    "SELECT DISTINCT semester_evaluasi, tahun_ajaran_evaluasi
     FROM Evaluations
     WHERE lecturer_user_id = ?
     ORDER BY tahun_ajaran_evaluasi DESC, semester_evaluasi DESC"
);
if ($stmt_periods_detail) {
    $stmt_periods_detail->bind_param("i", $target_dosen_id);
    $stmt_periods_detail->execute();
    $result_periods_detail = $stmt_periods_detail->get_result();
    while ($row_period_detail = $result_periods_detail->fetch_assoc()) {
        $available_periods_detail[] = $row_period_detail;
    }
    $stmt_periods_detail->close();
} else {
    error_log("Gagal mengambil periode tersedia (kajur_lihat_evaluasi_dosen): " . $conn->error);
}

// --- Logika Filter Periode ---
$selected_semester_filter_detail = $_GET['semester_filter'] ?? 'semua';
$selected_tahun_ajaran_filter_detail = $_GET['tahun_ajaran_filter'] ?? 'semua';

$periode_evaluasi_display_detail = "Semua Periode";
$sql_condition_periode_detail = "";
$params_periode_detail = [];
$types_periode_detail = "";

if ($selected_semester_filter_detail !== 'semua' && $selected_tahun_ajaran_filter_detail !== 'semua' && !empty($selected_semester_filter_detail) && !empty($selected_tahun_ajaran_filter_detail)) {
    $sql_condition_periode_detail = " AND semester_evaluasi = ? AND tahun_ajaran_evaluasi = ? ";
    $params_periode_detail[] = $selected_semester_filter_detail;
    $params_periode_detail[] = $selected_tahun_ajaran_filter_detail;
    $types_periode_detail .= "ss";
    $periode_evaluasi_display_detail = htmlspecialchars($selected_semester_filter_detail) . " - " . htmlspecialchars($selected_tahun_ajaran_filter_detail);
}

// --- Pengambilan Data Evaluasi dengan Filter ---
$overall_avg = 0.00;
$total_evals = 0;
$sql_overall_detail = "SELECT AVG(submission_average) as overall_avg, COUNT(evaluation_id) as total_evals
                       FROM Evaluations
                       WHERE lecturer_user_id = ? $sql_condition_periode_detail"; //
$stmt_overall_detail = $conn->prepare($sql_overall_detail);
// ... (Logika $stmt_overall_detail, $aspek_scores, $comments dengan filter periode sama seperti sebelumnya) ...
if ($stmt_overall_detail) {
    $all_params_overall_detail = array_merge([$target_dosen_id], $params_periode_detail);
    $all_types_overall_detail = "i" . $types_periode_detail;

    if (!empty($params_periode_detail)) {
        $stmt_overall_detail->bind_param($all_types_overall_detail, ...$all_params_overall_detail);
    } else {
        $stmt_overall_detail->bind_param("i", $target_dosen_id);
    }
    $stmt_overall_detail->execute();
    $result_overall_detail = $stmt_overall_detail->get_result();
    if ($row_overall_detail = $result_overall_detail->fetch_assoc()) {
        $overall_avg = $row_overall_detail['overall_avg'] ? round($row_overall_detail['overall_avg'], 2) : 0.00;
        $total_evals = (int) $row_overall_detail['total_evals'];
    }
    $stmt_overall_detail->close();
} else {
    error_log("Kajur Lihat Evaluasi: Gagal prepare statement untuk overall_avg: " . $conn->error);
}

$aspek_scores = [];
$question_fields_array = [];
for ($i = 1; $i <= 12; $i++) {
    $question_fields_array[] = "AVG(q{$i}_score) as avg_q{$i}";
} //
for ($i = 1; $i <= 5; $i++) {
    $question_fields_array[] = "AVG(qb{$i}_score) as avg_qb{$i}";
} //
for ($i = 1; $i <= 3; $i++) {
    $question_fields_array[] = "AVG(qc{$i}_score) as avg_qc{$i}";
} //
$question_fields = implode(", ", $question_fields_array); //

$sql_aspek_detail = "SELECT $question_fields
                     FROM Evaluations
                     WHERE lecturer_user_id = ? $sql_condition_periode_detail"; //
$stmt_aspek_detail = $conn->prepare($sql_aspek_detail);
if ($stmt_aspek_detail) {
    $all_params_aspek_detail = array_merge([$target_dosen_id], $params_periode_detail);
    $all_types_aspek_detail = "i" . $types_periode_detail;

    if (!empty($params_periode_detail)) {
        $stmt_aspek_detail->bind_param($all_types_aspek_detail, ...$all_params_aspek_detail);
    } else {
        $stmt_aspek_detail->bind_param("i", $target_dosen_id);
    }
    $stmt_aspek_detail->execute();
    $result_aspek_detail = $stmt_aspek_detail->get_result();
    if ($row_aspek_detail = $result_aspek_detail->fetch_assoc()) {
        for ($i = 1; $i <= 12; $i++) {
            $aspek_scores["Pertanyaan A" . $i] = $row_aspek_detail['avg_q' . $i] ? round($row_aspek_detail['avg_q' . $i], 2) : 0.00;
        } //
        for ($i = 1; $i <= 5; $i++) {
            $aspek_scores["Pertanyaan B" . $i] = $row_aspek_detail['avg_qb' . $i] ? round($row_aspek_detail['avg_qb' . $i], 2) : 0.00;
        } //
        for ($i = 1; $i <= 3; $i++) {
            $aspek_scores["Pertanyaan C" . $i] = $row_aspek_detail['avg_qc' . $i] ? round($row_aspek_detail['avg_qc' . $i], 2) : 0.00;
        } //
    }
    $stmt_aspek_detail->close();
} else {
    error_log("Kajur Lihat Evaluasi: Gagal prepare statement untuk aspek_scores: " . $conn->error);
}

$comments = [];
$sql_comments_detail = "SELECT comment, evaluation_date
                        FROM Evaluations
                        WHERE lecturer_user_id = ? AND comment IS NOT NULL AND TRIM(comment) != '' $sql_condition_periode_detail
                        ORDER BY evaluation_date DESC"; //
$stmt_comments_detail = $conn->prepare($sql_comments_detail);
if ($stmt_comments_detail) {
    $all_params_comments_detail = array_merge([$target_dosen_id], $params_periode_detail);
    $all_types_comments_detail = "i" . $types_periode_detail;

    if (!empty($params_periode_detail)) {
        $stmt_comments_detail->bind_param($all_types_comments_detail, ...$all_params_comments_detail);
    } else {
        $stmt_comments_detail->bind_param("i", $target_dosen_id);
    }
    $stmt_comments_detail->execute();
    $result_comments_detail = $stmt_comments_detail->get_result();
    while ($row_comment_detail = $result_comments_detail->fetch_assoc()) {
        $comments[] = $row_comment_detail;
    }
    $stmt_comments_detail->close();
} else {
    error_log("Kajur Lihat Evaluasi: Gagal prepare statement untuk comments: " . $conn->error);
}

$category_display_names = [ /* ... */]; //
$questions_text_map = [ /* ... */]; //
// Salin dari implementasi dosen_dashboard.php untuk konsistensi
$category_display_names = [
    'A' => 'Kompetensi Profesional',
    'B' => 'Kompetensi Personal',
    'C' => 'Kompetensi Sosial'
];
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
    "Pertanyaan B1" => "1. Keadilan dalam memperlakukan mahasiswa.",
    "Pertanyaan B2" => "2. Menghargai pendapat mahasiswa.",
    "Pertanyaan B3" => "3. Kerapihan dalam berpakaian.",
    "Pertanyaan B4" => "4. Keteladanan dalam bersikap dan berperilaku.",
    "Pertanyaan B5" => "5. Kemampuan mengendalikan diri dalam berbagai situasi dan kondisi.",
    "Pertanyaan C1" => "1. Kemampuan berkomunikasi dengan mahasiswa.",
    "Pertanyaan C2" => "2. Kemampuan bekerja sama dengan mahasiswa.",
    "Pertanyaan C3" => "3. Kepedulian terhadap kesulitan mahasiswa."
];


$current_page = basename($_SERVER['PHP_SELF']); //
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Evaluasi Dosen: <?php echo htmlspecialchars($target_dosen_name); ?> - Evados</title>
    <link rel="icon" href="../logo.png" type="image/png" />
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_force_sidebar_closed; ?>;
        function updatePeriodeFieldsDetail(selectedValue) {
            const semesterHidden = document.getElementById('semester_filter_hidden_detail');
            const tahunAjaranHidden = document.getElementById('tahun_ajaran_filter_hidden_detail');
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
            const selectElement = document.getElementById('periode_filter_select_detail');
            if (selectElement) {
                updatePeriodeFieldsDetail(selectElement.value);
            }
        });
    </script>
    <style>
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            padding: 15px;
            background-color: var(--background-color);
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
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
                    <li><a href="kajur_dashboard.php"><i class="fas fa-arrow-left"></i> <span
                                class="menu-text">Kembali</span></a></li>
                    <li><a href="kajur_kirim_pesan.php?dosen_id=<?php echo $target_dosen_id; ?>"><i
                                class="fas fa-paper-plane"></i> <span class="menu-text">Kirim Pesan ke Dosen
                                Ini</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-logout-section">
                <a href="../logout.php" class="logout-link"> <i class="fas fa-sign-out-alt"></i>
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

            <section class="content-card" style="margin-bottom: 20px;">
                <h2><i class="fas fa-filter"></i> Filter Periode Evaluasi</h2>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" class="filter-form">
                    <input type="hidden" name="dosen_id" value="<?php echo htmlspecialchars($target_dosen_id); ?>">
                    <div class="input-group">
                        <label for="periode_filter_select_detail">Pilih Periode:</label>
                        <select name="periode_filter_select" id="periode_filter_select_detail"
                            onchange="updatePeriodeFieldsDetail(this.value)">
                            <option value="semua_semua" <?php echo ($selected_semester_filter_detail === 'semua') ? 'selected' : ''; ?>>Semua Periode</option>
                            <?php foreach ($available_periods_detail as $period): ?>
                                <?php
                                $period_val_detail = htmlspecialchars($period['semester_evaluasi']) . "___" . htmlspecialchars($period['tahun_ajaran_evaluasi']);
                                $period_disp_detail = htmlspecialchars($period['semester_evaluasi']) . " - " . htmlspecialchars($period['tahun_ajaran_evaluasi']);
                                $is_sel_detail = ($selected_semester_filter_detail === $period['semester_evaluasi'] && $selected_tahun_ajaran_filter_detail === $period['tahun_ajaran_evaluasi']);
                                ?>
                                <option value="<?php echo $period_val_detail; ?>" <?php echo $is_sel_detail ? 'selected' : ''; ?>>
                                    <?php echo $period_disp_detail; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="semester_filter" id="semester_filter_hidden_detail"
                            value="<?php echo htmlspecialchars($selected_semester_filter_detail); ?>">
                        <input type="hidden" name="tahun_ajaran_filter" id="tahun_ajaran_filter_hidden_detail"
                            value="<?php echo htmlspecialchars($selected_tahun_ajaran_filter_detail); ?>">
                    </div>
                    <button type="submit"><i class="fas fa-search"></i> Tampilkan</button>
                </form>
            </section>

            <div class="info-box">
                <h4>Ringkasan Umum Evaluasi Dosen</h4>
                <p><strong>Periode Data Ditampilkan:</strong> <?php echo $periode_evaluasi_display_detail; ?></p>
                <p>Total Evaluasi Diterima: <strong class="score-highlight"><?php echo $total_evals; ?></strong></p>
                <p>Skor Rata-Rata Keseluruhan: <strong
                        class="score-highlight"><?php echo number_format($overall_avg, 2); ?> / 4.00</strong></p>
            </div>

            <section class="content-card">
                <h2><i class="fas fa-clipboard-check"></i> Rata-Rata Skor per Aspek Penilaian (Periode:
                    <?php echo $periode_evaluasi_display_detail; ?>)</h2>
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
                    <p>Dosen ini belum memiliki data evaluasi untuk periode
                        <?php echo strtolower($periode_evaluasi_display_detail); ?>.</p>
                <?php endif; ?>
            </section>

            <section class="content-card" style="margin-top: 20px;">
                <h2><i class="fas fa-comment-dots"></i> Komentar Mahasiswa (Anonim) (Periode:
                    <?php echo $periode_evaluasi_display_detail; ?>)</h2>
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
                    <p>Tidak ada komentar yang diterima untuk dosen ini pada periode
                        <?php echo strtolower($periode_evaluasi_display_detail); ?>.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>
