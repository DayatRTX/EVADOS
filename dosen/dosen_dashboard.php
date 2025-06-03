<?php
// evados/dosen_dashboard.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$js_force_sidebar_closed = 'false';
if (isset($_SESSION['initial_dashboard_load_sidebar_closed']) && $_SESSION['initial_dashboard_load_sidebar_closed'] === true) {
    $js_force_sidebar_closed = 'true';
    unset($_SESSION['initial_dashboard_load_sidebar_closed']);
}

require_once '../includes/auth_check_dosen.php';
require_once '../config/db.php';

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
    error_log("Gagal prepare statement untuk cek notifikasi belum dibaca (dosen_dashboard): " . $conn->error);
}

// --- Pengambilan data untuk filter periode ---
$available_periods = [];
$stmt_periods = $conn->prepare(
    "SELECT DISTINCT semester_evaluasi, tahun_ajaran_evaluasi
     FROM Evaluations
     WHERE lecturer_user_id = ?
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
    error_log("Gagal mengambil periode tersedia (dosen_dashboard): " . $conn->error);
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

// --- Pengambilan Data Evaluasi dengan Filter ---
$overall_avg = 0.00;
$total_evals = 0;

$sql_overall = "SELECT AVG(submission_average) as overall_avg, COUNT(evaluation_id) as total_evals
                FROM Evaluations
                WHERE lecturer_user_id = ? $sql_condition_periode";
$stmt_overall = $conn->prepare($sql_overall);

if ($stmt_overall) {
    $all_params_overall = array_merge([$loggedInDosenId], $params_periode);
    $all_types_overall = "i" . $types_periode;
    if (!empty($params_periode)) {
        $stmt_overall->bind_param($all_types_overall, ...$all_params_overall);
    } else {
        $stmt_overall->bind_param("i", $loggedInDosenId);
    }
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

$sql_aspek = "SELECT $question_fields
              FROM Evaluations
              WHERE lecturer_user_id = ? $sql_condition_periode";
$stmt_aspek = $conn->prepare($sql_aspek);
if ($stmt_aspek) {
    $all_params_aspek = array_merge([$loggedInDosenId], $params_periode);
    $all_types_aspek = "i" . $types_periode;
    if (!empty($params_periode)) {
        $stmt_aspek->bind_param($all_types_aspek, ...$all_params_aspek);
    } else {
        $stmt_aspek->bind_param("i", $loggedInDosenId);
    }
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

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen - Evados</title>
    <link rel="icon" href="../logo.png" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
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
            /* Atur padding agar tidak terlalu mepet dengan judul */
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
                <a href="../logout.php" class="logout-link"> <i class="fas fa-sign-out-alt"></i>
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
                <p><strong>Periode Data Ditampilkan:</strong> <?php echo $periode_evaluasi_display; ?></p>
                <p>Total Evaluasi Diterima: <strong class="score-highlight"><?php echo $total_evals; ?></strong></p>
                <p>Skor Rata-Rata Keseluruhan: <strong
                        class="score-highlight"><?php echo number_format($overall_avg, 2); ?> / 4.00</strong></p>
            </div>

            <section class="content-card">
                <h2><i class="fas fa-clipboard-check"></i> Rata-Rata Skor per Aspek Penilaian</h2>

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
                <p style="font-size:0.9em; margin-top:0; color: #555;"><i>Menampilkan data untuk periode:
                        <?php echo $periode_evaluasi_display; ?></i></p>

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
                    <p>Belum ada data evaluasi yang diterima untuk periode
                        <?php echo strtolower($periode_evaluasi_display); ?>.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>
