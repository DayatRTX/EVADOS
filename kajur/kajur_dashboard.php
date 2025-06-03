<?php
// evados/kajur_dashboard.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$js_force_sidebar_closed = 'false';
if (isset($_SESSION['initial_dashboard_load_sidebar_closed']) && $_SESSION['initial_dashboard_load_sidebar_closed'] === true) {
    $js_force_sidebar_closed = 'true';
    unset($_SESSION['initial_dashboard_load_sidebar_closed']);
}

require_once '../includes/auth_check_kajur.php';
require_once '../config/db.php';

$department_managed = '';
$stmt_dept = $conn->prepare("SELECT department_managed FROM Kajur WHERE user_id = ?");
if ($stmt_dept) {
    $stmt_dept->bind_param("i", $loggedInKajurId);
    $stmt_dept->execute();
    $result_dept = $stmt_dept->get_result();
    if ($row_dept = $result_dept->fetch_assoc()) {
        $department_managed = $row_dept['department_managed'];
    } else {
        error_log("Kajur Dashboard: Data Kajur atau department_managed tidak ditemukan untuk user_id: " . $loggedInKajurId);
        $department_managed = "Departemen Tidak Terdefinisi";
    }
    $stmt_dept->close();
} else {
    error_log("Kajur Dashboard: Gagal prepare statement untuk department_managed: " . $conn->error);
    $department_managed = "Error Mengambil Data Departemen";
}

// --- Pengambilan data untuk filter periode ---
$available_periods_kajur = [];
if (!empty($department_managed) && $department_managed !== "Departemen Tidak Terdefinisi" && $department_managed !== "Error Mengambil Data Departemen") {
    $stmt_periods_kajur = $conn->prepare(
        "SELECT DISTINCT e.semester_evaluasi, e.tahun_ajaran_evaluasi
         FROM Evaluations e
         JOIN Auth_Users au_lecturer ON e.lecturer_user_id = au_lecturer.user_id
         JOIN Dosen d ON au_lecturer.user_id = d.user_id
         WHERE d.department = ?
         ORDER BY e.tahun_ajaran_evaluasi DESC, e.semester_evaluasi DESC"
    );
    if ($stmt_periods_kajur) {
        $stmt_periods_kajur->bind_param("s", $department_managed);
        $stmt_periods_kajur->execute();
        $result_periods_kajur = $stmt_periods_kajur->get_result();
        while ($row_period_kajur = $result_periods_kajur->fetch_assoc()) {
            $available_periods_kajur[] = $row_period_kajur;
        }
        $stmt_periods_kajur->close();
    } else {
        error_log("Gagal mengambil periode tersedia (kajur_dashboard): " . $conn->error);
    }
}

// --- Logika Filter Periode ---
$selected_semester_filter_kajur = $_GET['semester_filter'] ?? 'semua';
$selected_tahun_ajaran_filter_kajur = $_GET['tahun_ajaran_filter'] ?? 'semua';

$periode_evaluasi_display_kajur = "Semua Periode";
$sql_condition_periode_kajur_direct = ""; // Untuk query statistik jurusan
$params_periode_kajur = []; // Untuk bind_param filter periode
$types_periode_kajur = "";

if ($selected_semester_filter_kajur !== 'semua' && $selected_tahun_ajaran_filter_kajur !== 'semua' && !empty($selected_semester_filter_kajur) && !empty($selected_tahun_ajaran_filter_kajur)) {
    $sql_condition_periode_kajur_direct = " AND e.semester_evaluasi = ? AND e.tahun_ajaran_evaluasi = ? ";
    $params_periode_kajur[] = $selected_semester_filter_kajur;
    $params_periode_kajur[] = $selected_tahun_ajaran_filter_kajur;
    $types_periode_kajur .= "ss";
    $periode_evaluasi_display_kajur = htmlspecialchars($selected_semester_filter_kajur) . " - " . htmlspecialchars($selected_tahun_ajaran_filter_kajur);
}

// --- Statistik Jurusan dengan Filter ---
// (Kode statistik jurusan tetap sama seperti sebelumnya, menggunakan $sql_condition_periode_kajur_direct dan $params_periode_kajur)
$dept_overall_avg = 0.00;
$total_dosen_in_dept_aktif = 0;
$total_evals_in_dept = 0;

if (!empty($department_managed) && $department_managed !== "Departemen Tidak Terdefinisi" && $department_managed !== "Error Mengambil Data Departemen") {
    $stmt_total_dosen_aktif = $conn->prepare(
        "SELECT COUNT(d.user_id) as total_dosen
         FROM Dosen d
         JOIN Auth_Users au ON d.user_id = au.user_id
         WHERE d.department = ? AND au.role='dosen' AND au.is_active = 1"
    );
    if ($stmt_total_dosen_aktif) {
        $stmt_total_dosen_aktif->bind_param("s", $department_managed);
        $stmt_total_dosen_aktif->execute();
        $res_total_dosen_aktif = $stmt_total_dosen_aktif->get_result();
        if ($row_total_dosen_aktif = $res_total_dosen_aktif->fetch_assoc()) {
            $total_dosen_in_dept_aktif = (int) $row_total_dosen_aktif['total_dosen'];
        }
        $stmt_total_dosen_aktif->close();
    }

    $sql_dept_avg = "SELECT AVG(e.submission_average) as dept_avg, COUNT(DISTINCT e.evaluation_id) as total_evals
                     FROM Evaluations e
                     JOIN Auth_Users au_lecturer ON e.lecturer_user_id = au_lecturer.user_id
                     JOIN Dosen d ON au_lecturer.user_id = d.user_id
                     WHERE d.department = ? AND au_lecturer.role = 'dosen' $sql_condition_periode_kajur_direct";
    $stmt_dept_avg = $conn->prepare($sql_dept_avg);
    if ($stmt_dept_avg) {
        $all_params_dept_avg = array_merge([$department_managed], $params_periode_kajur);
        $all_types_dept_avg = "s" . $types_periode_kajur;

        if (!empty($params_periode_kajur)) {
            $stmt_dept_avg->bind_param($all_types_dept_avg, ...$all_params_dept_avg);
        } else {
            $stmt_dept_avg->bind_param("s", $department_managed);
        }
        $stmt_dept_avg->execute();
        $result_dept_avg = $stmt_dept_avg->get_result();
        if ($row_dept_avg = $result_dept_avg->fetch_assoc()) {
            $dept_overall_avg = $row_dept_avg['dept_avg'] ? round((float) $row_dept_avg['dept_avg'], 2) : 0.00;
            $total_evals_in_dept = (int) $row_dept_avg['total_evals'];
        }
        $stmt_dept_avg->close();
    } else {
        error_log("Kajur Dashboard: Gagal prepare statement untuk dept_overall_avg: " . $conn->error);
    }
}


// --- Logika Paginasi dan Filter untuk Daftar Dosen Jurusan ---
$limit_dosen = 10;
$page_dosen = isset($_GET['page_dosen']) && is_numeric($_GET['page_dosen']) ? (int) $_GET['page_dosen'] : 1;
$offset_dosen = ($page_dosen - 1) * $limit_dosen;
$search_query_kajur_dosen = isset($_GET['search_dosen']) ? trim($_GET['search_dosen']) : '';

$dosen_jurusan_list = [];
$total_records_dosen_jurusan = 0;
$total_pages_dosen_jurusan = 1;

// Query utama untuk mengambil daftar dosen
$sql_dosen_list_base = "
    SELECT 
        au.user_id, au.full_name, d.nidn,
        COALESCE(eval_stats.avg_score, NULL) as avg_score,
        COALESCE(eval_stats.total_evaluations, 0) as total_evaluations
    FROM Auth_Users au
    JOIN Dosen d ON au.user_id = d.user_id
    LEFT JOIN (
        SELECT 
            e_sub.lecturer_user_id,
            AVG(e_sub.submission_average) as avg_score,
            COUNT(e_sub.evaluation_id) as total_evaluations
        FROM Evaluations e_sub
        WHERE 1=1 "; // Kondisi WHERE 1=1 untuk memudahkan penambahan AND

// Tambahkan kondisi filter periode ke subquery jika aktif
$subquery_params = [];
$subquery_types = "";
if (!empty($params_periode_kajur)) {
    // Kondisi untuk subquery Evaluations (e_sub)
    $sql_dosen_list_base .= " AND e_sub.semester_evaluasi = ? AND e_sub.tahun_ajaran_evaluasi = ? ";
    $subquery_params = array_merge($subquery_params, $params_periode_kajur);
    $subquery_types .= $types_periode_kajur;
}
$sql_dosen_list_base .= " GROUP BY e_sub.lecturer_user_id
    ) eval_stats ON au.user_id = eval_stats.lecturer_user_id
    WHERE d.department = ? AND au.role = 'dosen' AND au.is_active = 1 ";

// Parameter dan Tipe untuk kondisi WHERE utama (filter dosen dan departemen)
$main_where_params = [$department_managed];
$main_where_types = "s";
$sql_main_where_conditions = "";

if (!empty($search_query_kajur_dosen)) {
    $sql_main_where_conditions .= " AND (au.full_name LIKE ? OR d.nidn LIKE ?) ";
    $search_term_kajur = "%" . $search_query_kajur_dosen . "%";
    $main_where_params[] = $search_term_kajur;
    $main_where_params[] = $search_term_kajur;
    $main_where_types .= "ss";
}
$sql_dosen_list_base .= $sql_main_where_conditions;

// Hitung total record untuk paginasi (berdasarkan filter departemen dan nama/NIDN dosen)
$sql_total_dosen_jurusan = "SELECT COUNT(au.user_id) as total 
                            FROM Auth_Users au 
                            JOIN Dosen d ON au.user_id = d.user_id 
                            WHERE d.department = ? AND au.role = 'dosen' AND au.is_active = 1 $sql_main_where_conditions";
$stmt_total_dosen_jurusan = $conn->prepare($sql_total_dosen_jurusan);
if ($stmt_total_dosen_jurusan) {
    $stmt_total_dosen_jurusan->bind_param($main_where_types, ...$main_where_params);
    $stmt_total_dosen_jurusan->execute();
    $res_total = $stmt_total_dosen_jurusan->get_result()->fetch_assoc();
    if ($res_total)
        $total_records_dosen_jurusan = (int) $res_total['total'];
    if ($limit_dosen > 0 && $total_records_dosen_jurusan > 0) {
        $total_pages_dosen_jurusan = ceil($total_records_dosen_jurusan / $limit_dosen);
    }
    $stmt_total_dosen_jurusan->close();
}


// Query final untuk daftar dosen dengan paginasi
$sql_dosen_jurusan_query_final = $sql_dosen_list_base . " ORDER BY au.full_name ASC LIMIT ? OFFSET ?";

// Gabungkan semua parameter dalam urutan yang benar
$final_bind_params = [];
$final_bind_types = "";

// Parameter untuk subquery (filter periode)
$final_bind_params = array_merge($final_bind_params, $subquery_params);
$final_bind_types .= $subquery_types;

// Parameter untuk WHERE utama (departemen, search nama/NIDN)
$final_bind_params = array_merge($final_bind_params, $main_where_params);
$final_bind_types .= $main_where_types;

// Parameter untuk LIMIT dan OFFSET
$final_bind_params[] = $limit_dosen;
$final_bind_params[] = $offset_dosen;
$final_bind_types .= "ii";

$stmt_dosen_jurusan = $conn->prepare($sql_dosen_jurusan_query_final);
if ($stmt_dosen_jurusan) {
    if (!empty($final_bind_types)) {
        $stmt_dosen_jurusan->bind_param($final_bind_types, ...$final_bind_params);
    }
    $stmt_dosen_jurusan->execute();
    $result_dosen_jurusan = $stmt_dosen_jurusan->get_result();
    while ($row = $result_dosen_jurusan->fetch_assoc()) {
        $dosen_jurusan_list[] = $row;
    }
    $stmt_dosen_jurusan->close();
} else {
    error_log("Kajur Dashboard: Gagal prepare statement untuk dosen_jurusan_list: " . $conn->error);
    error_log("Query: " . $sql_dosen_jurusan_query_final);
    error_log("Types: " . $final_bind_types);
    error_log("Params: " . print_r($final_bind_params, true));
}


$current_page_php = basename($_SERVER['PHP_SELF']);
$success_message_kajur = $_SESSION['success_message_kajur_dashboard'] ?? '';
unset($_SESSION['success_message_kajur_dashboard']);
$error_message_kajur = $_SESSION['error_message_kajur_dashboard'] ?? '';
unset($_SESSION['error_message_kajur_dashboard']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Ketua Jurusan - Evados</title>
    <link rel="icon" href="../logo.png" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_force_sidebar_closed; ?>;
        function updatePeriodeFieldsKajur(selectedValue) {
            const semesterHidden = document.getElementById('semester_filter_hidden_kajur');
            const tahunAjaranHidden = document.getElementById('tahun_ajaran_filter_hidden_kajur');
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
            const selectElement = document.getElementById('periode_filter_select_kajur');
            if (selectElement) {
                updatePeriodeFieldsKajur(selectElement.value);
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
            flex: 1 1 180px;
        }

        .filter-form .input-group.periode {
            flex: 1 1 250px;
        }

        .filter-form .input-group label {
            font-size: 0.85em;
            margin-bottom: 4px;
            color: var(--text-color);
            opacity: 0.9;
            display: block;
        }

        .filter-form input[type="text"],
        .filter-form select {
            width: 100%;
            box-sizing: border-box;
            height: 40px;
            padding: 8px 12px;
            font-size: 0.9em;
            border-radius: 6px;
            border: 1px solid var(--tertiary-color);
        }

        .filter-form button,
        .filter-form a.btn-filter-action {
            height: 40px;
            padding: 0 18px;
            font-size: 0.9em;
            margin-bottom: 0;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            border: none;
            cursor: pointer;
        }

        .filter-form button {
            background-color: var(--primary-color);
            color: var(--white-color);
        }

        .filter-form button:hover {
            background-color: var(--secondary-color);
        }

        .filter-form a.btn-reset-filter {
            background-color: #6c757d;
            color: white;
        }

        .filter-form a.btn-reset-filter:hover {
            background-color: #5a6268;
        }

        .filter-form a.btn-filter-action i,
        .filter-form button i {
            margin-right: 6px;
        }

        .department-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .summary-card {
            background-color: var(--white-color);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px var(--shadow-color);
            border-left: 5px solid var(--primary-color);
        }

        .summary-card h4 {
            margin-top: 0;
            color: var(--primary-color);
        }

        .summary-card p {
            font-size: 1.5em;
            font-weight: bold;
            margin: 5px 0 0 0;
            color: var(--text-color);
        }

        .pagination {
            display: flex;
            justify-content: center;
            padding: 25px 0;
            list-style: none;
        }

        .pagination li {
            margin: 0 4px;
        }

        .pagination li a,
        .pagination li span {
            display: inline-block;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 6px;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
            font-size: 0.9em;
            font-weight: 500;
            border: 1px solid var(--tertiary-color);
            background-color: var(--white-color);
            color: var(--primary-color);
        }

        .pagination li a:hover,
        .pagination li.active span,
        .pagination li.active a {
            background-color: var(--primary-color);
            color: var(--white-color);
            border-color: var(--primary-color);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .pagination li.disabled span {
            background-color: #f0f0f0;
            color: #aaa;
            border-color: #ddd;
            cursor: default;
        }
    </style>
</head>

<body>
    <div class="mahasiswa-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-top-section">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar"><i
                        class="fas fa-bars"></i></button>
                <div class="sidebar-header">
                    <h3 class="logo-text">Evados</h3>
                </div>
            </div>
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="kajur_dashboard.php"
                            class="<?php echo ($current_page_php == 'kajur_dashboard.php') ? 'active' : ''; ?>"><i
                                class="fas fa-tachometer-alt"></i> <span class="menu-text">Dashboard Jurusan</span></a>
                    </li>
                    <li><a href="kajur_kirim_pesan.php"
                            class="<?php echo ($current_page_php == 'kajur_kirim_pesan.php') ? 'active' : ''; ?>"><i
                                class="fas fa-paper-plane"></i> <span class="menu-text">Kirim Pesan</span></a></li>
                    <li><a href="kajur_riwayat_pesan.php"
                            class="<?php echo ($current_page_php == 'kajur_riwayat_pesan.php') ? 'active' : ''; ?>"><i
                                class="fas fa-history"></i> <span class="menu-text">Riwayat Pesan</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-logout-section">
                <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span
                        class="menu-text">Logout</span></a>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <header class="header">
                <h1>Dashboard Ketua Jurusan: <?php echo htmlspecialchars($loggedInKajurFullName); ?></h1>
            </header>

            <?php if (!empty($success_message_kajur)): ?>
                <div class="success-msg"><?php echo htmlspecialchars($success_message_kajur); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message_kajur)): ?>
                <div class="error-msg-page"><?php echo htmlspecialchars($error_message_kajur); ?></div>
            <?php endif; ?>

            <div class="info-box">
                <p>Selamat datang di Dashboard Ketua Jurusan. Di sini Anda dapat memantau hasil evaluasi dosen di
                    jurusan Anda dan mengirimkan komunikasi kepada dosen.</p>
                <p><strong>Jurusan yang Dikelola:</strong>
                    <?php echo htmlspecialchars($department_managed ?: 'Belum ditentukan atau Error'); ?></p>
                <p><strong>Periode Data Ditampilkan:</strong> <?php echo $periode_evaluasi_display_kajur; ?></p>
            </div>

            <div class="department-summary-grid">
                <div class="summary-card">
                    <h4>Total Dosen Aktif di Jurusan</h4>
                    <p><?php echo $total_dosen_in_dept_aktif; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Total Evaluasi di Jurusan (<?php echo $periode_evaluasi_display_kajur; ?>)</h4>
                    <p><?php echo $total_evals_in_dept; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Rata-Rata Skor Jurusan (<?php echo $periode_evaluasi_display_kajur; ?>)</h4>
                    <p><?php echo number_format($dept_overall_avg, 2); ?> / 4.00</p>
                </div>
            </div>

            <section class="content-card">
                <h2><i class="fas fa-users"></i> Daftar Evaluasi Dosen (Aktif) di Jurusan
                    "<?php echo htmlspecialchars($department_managed); ?>"</h2>

                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" class="filter-form">
                    <div class="input-group periode">
                        <label for="periode_filter_select_kajur">Pilih Periode Data:</label>
                        <select name="periode_filter_select" id="periode_filter_select_kajur"
                            onchange="updatePeriodeFieldsKajur(this.value)">
                            <option value="semua_semua" <?php echo ($selected_semester_filter_kajur === 'semua') ? 'selected' : ''; ?>>Semua Periode</option>
                            <?php foreach ($available_periods_kajur as $period): ?>
                                <?php
                                $period_val = htmlspecialchars($period['semester_evaluasi']) . "___" . htmlspecialchars($period['tahun_ajaran_evaluasi']);
                                $period_disp = htmlspecialchars($period['semester_evaluasi']) . " - " . htmlspecialchars($period['tahun_ajaran_evaluasi']);
                                $is_sel = ($selected_semester_filter_kajur === $period['semester_evaluasi'] && $selected_tahun_ajaran_filter_kajur === $period['tahun_ajaran_evaluasi']);
                                ?>
                                <option value="<?php echo $period_val; ?>" <?php echo $is_sel ? 'selected' : ''; ?>>
                                    <?php echo $period_disp; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="semester_filter" id="semester_filter_hidden_kajur"
                            value="<?php echo htmlspecialchars($selected_semester_filter_kajur); ?>">
                        <input type="hidden" name="tahun_ajaran_filter" id="tahun_ajaran_filter_hidden_kajur"
                            value="<?php echo htmlspecialchars($selected_tahun_ajaran_filter_kajur); ?>">
                    </div>
                    <div class="input-group">
                        <label for="search_dosen">Cari Dosen (Nama/NIDN)</label>
                        <input type="text" name="search_dosen" id="search_dosen" placeholder="Nama atau NIDN..."
                            value="<?php echo htmlspecialchars($search_query_kajur_dosen); ?>">
                    </div>
                    <button type="submit"><i class="fas fa-filter"></i> Filter</button>
                    <a href="kajur_dashboard.php" class="btn-filter-action btn-reset-filter"><i
                            class="fas fa-times"></i> Reset Filter</a>
                </form>
                <p style="font-size:0.9em; margin-top:0; color: #555;"><i>Menampilkan data untuk periode:
                        <?php echo $periode_evaluasi_display_kajur; ?></i></p>

                <?php if (!empty($department_managed) && $department_managed !== "Departemen Tidak Terdefinisi" && $department_managed !== "Error Mengambil Data Departemen"): ?>
                    <?php if (!empty($dosen_jurusan_list)): ?>
                        <div style="overflow-x: auto;">
                            <table class="dosen-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th>NIDN</th>
                                        <th>Nama Dosen</th>
                                        <th style="text-align:center;">Skor Rata-Rata</th>
                                        <th style="text-align:center;">Jumlah Evaluasi</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $nomor_dosen = $offset_dosen + 1;
                                    foreach ($dosen_jurusan_list as $dosen): ?>
                                        <tr>
                                            <td><?php echo $nomor_dosen++; ?></td>
                                            <td><?php echo htmlspecialchars($dosen['nidn']); ?></td>
                                            <td><?php echo htmlspecialchars($dosen['full_name']); ?></td>
                                            <td style="text-align:center;">
                                                <?php echo $dosen['avg_score'] !== null ? number_format($dosen['avg_score'], 2) : 'N/A'; ?>
                                            </td>
                                            <td style="text-align:center;"><?php echo (int) $dosen['total_evaluations']; ?></td>
                                            <td>
                                                <a href="kajur_lihat_evaluasi_dosen.php?dosen_id=<?php echo $dosen['user_id']; ?>&semester_filter=<?php echo urlencode($selected_semester_filter_kajur); ?>&tahun_ajaran_filter=<?php echo urlencode($selected_tahun_ajaran_filter_kajur); ?>"
                                                    class="btn-penilaian" style="font-size:0.8em; padding: 5px 10px;">
                                                    <i class="fas fa-eye"></i> Lihat Detail
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($total_pages_dosen_jurusan > 1): ?>
                            <ul class="pagination">
                                <?php
                                $query_params_kajur_page = "&search_dosen=" . urlencode($search_query_kajur_dosen) . "&semester_filter=" . urlencode($selected_semester_filter_kajur) . "&tahun_ajaran_filter=" . urlencode($selected_tahun_ajaran_filter_kajur);
                                ?>
                                <?php if ($page_dosen > 1): ?>
                                    <li><a
                                            href="?page_dosen=<?php echo $page_dosen - 1; ?><?php echo $query_params_kajur_page; ?>">Sebelumnya</a>
                                    </li>
                                <?php else: ?>
                                    <li class="disabled"><span>Sebelumnya</span></li>
                                <?php endif; ?>
                                <?php
                                $start_loop_dosen_p = max(1, $page_dosen - 2);
                                $end_loop_dosen_p = min($total_pages_dosen_jurusan, $page_dosen + 2);
                                if ($start_loop_dosen_p > 1) {
                                    echo '<li><a href="?page_dosen=1' . $query_params_kajur_page . '">1</a></li>';
                                    if ($start_loop_dosen_p > 2)
                                        echo '<li class="disabled"><span>...</span></li>';
                                }
                                for ($i = $start_loop_dosen_p; $i <= $end_loop_dosen_p; $i++): ?>
                                    <li class="<?php echo ($i == $page_dosen) ? 'active' : ''; ?>">
                                        <?php if ($i == $page_dosen): ?>
                                            <span><?php echo $i; ?></span>
                                        <?php else: ?>
                                            <a
                                                href="?page_dosen=<?php echo $i; ?><?php echo $query_params_kajur_page; ?>"><?php echo $i; ?></a>
                                        <?php endif; ?>
                                    </li>
                                <?php endfor;
                                if ($end_loop_dosen_p < $total_pages_dosen_jurusan) {
                                    if ($end_loop_dosen_p < $total_pages_dosen_jurusan - 1)
                                        echo '<li class="disabled"><span>...</span></li>';
                                    echo '<li><a href="?page_dosen=' . $total_pages_dosen_jurusan . $query_params_kajur_page . '">' . $total_pages_dosen_jurusan . '</a></li>';
                                } ?>
                                <?php if ($page_dosen < $total_pages_dosen_jurusan): ?>
                                    <li><a
                                            href="?page_dosen=<?php echo $page_dosen + 1; ?><?php echo $query_params_kajur_page; ?>">Berikutnya</a>
                                    </li>
                                <?php else: ?>
                                    <li class="disabled"><span>Berikutnya</span></li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>
                            <?php if (!empty($search_query_kajur_dosen) || ($selected_semester_filter_kajur !== 'semua')): ?>
                                Tidak ada dosen aktif yang cocok dengan kriteria pencarian/filter Anda di jurusan
                                "<?php echo htmlspecialchars($department_managed); ?>" untuk periode
                                <?php echo strtolower($periode_evaluasi_display_kajur); ?>.
                            <?php else: ?>
                                Belum ada data dosen aktif yang terdaftar di jurusan
                                "<?php echo htmlspecialchars($department_managed); ?>" atau belum ada evaluasi yang masuk untuk
                                dosen di jurusan tersebut pada periode ini.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                <?php elseif (empty($department_managed) || $department_managed === "Departemen Tidak Terdefinisi" || $department_managed === "Error Mengambil Data Departemen"): ?>
                    <p>Informasi jurusan Anda belum diatur atau gagal diambil. Pastikan akun Kajur Anda memiliki data
                        departemen yang dikelola di tabel 'Kajur'.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>
