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

require_once 'includes/auth_check_kajur.php'; //
require_once 'config/db.php'; //

$department_managed = '';
$stmt_dept = $conn->prepare("SELECT department_managed FROM Kajur WHERE user_id = ?"); //
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

$periode_evaluasi_display = "Semua Periode";
$dept_overall_avg = 0.00;
$total_dosen_in_dept_aktif = 0;
$total_evals_in_dept = 0;

if (!empty($department_managed) && $department_managed !== "Departemen Tidak Terdefinisi" && $department_managed !== "Error Mengambil Data Departemen") {
    $stmt_total_dosen_aktif = $conn->prepare(
        "SELECT COUNT(d.user_id) as total_dosen 
         FROM Dosen d 
         JOIN Auth_Users au ON d.user_id = au.user_id 
         WHERE d.department = ? AND au.role='dosen' AND au.is_active = 1"
    ); //
    if ($stmt_total_dosen_aktif) {
        $stmt_total_dosen_aktif->bind_param("s", $department_managed);
        $stmt_total_dosen_aktif->execute();
        $res_total_dosen_aktif = $stmt_total_dosen_aktif->get_result();
        if ($row_total_dosen_aktif = $res_total_dosen_aktif->fetch_assoc()) {
            $total_dosen_in_dept_aktif = (int) $row_total_dosen_aktif['total_dosen'];
        }
        $stmt_total_dosen_aktif->close();
    }

    $stmt_dept_avg = $conn->prepare(
        "SELECT AVG(e.submission_average) as dept_avg, COUNT(DISTINCT e.evaluation_id) as total_evals 
         FROM Evaluations e 
         JOIN Auth_Users au_lecturer ON e.lecturer_user_id = au_lecturer.user_id
         JOIN Dosen d ON au_lecturer.user_id = d.user_id 
         WHERE d.department = ? AND au_lecturer.role = 'dosen'"
    ); //
    if ($stmt_dept_avg) {
        $stmt_dept_avg->bind_param("s", $department_managed);
        $stmt_dept_avg->execute();
        $result_dept_avg = $stmt_dept_avg->get_result();
        if ($row_dept_avg = $result_dept_avg->fetch_assoc()) {
            $dept_overall_avg = $row_dept_avg['dept_avg'] ? round((float) $row_dept_avg['dept_avg'], 2) : 0.00;
            $total_evals_in_dept = (int) $row_dept_avg['total_evals'];
        }
        $stmt_dept_avg->close();
    }
}

// Logika Paginasi dan Filter untuk Daftar Dosen Jurusan
$limit_dosen = 10;
$page_dosen = isset($_GET['page_dosen']) && is_numeric($_GET['page_dosen']) ? (int) $_GET['page_dosen'] : 1;
$offset_dosen = ($page_dosen - 1) * $limit_dosen;
$search_query_kajur_dosen = isset($_GET['search_dosen']) ? trim($_GET['search_dosen']) : '';

$dosen_jurusan_list = [];
$total_records_dosen_jurusan = 0;
$total_pages_dosen_jurusan = 1;

$sql_base_kajur_dosen = "FROM Auth_Users au JOIN Dosen d ON au.user_id = d.user_id WHERE d.department = ? AND au.role = 'dosen' AND au.is_active = 1"; //
$sql_conditions_kajur_dosen = "";
$params_list_kajur_dosen = [$department_managed];
$types_list_kajur_dosen = "s";

if (!empty($search_query_kajur_dosen)) {
    $sql_conditions_kajur_dosen .= " AND (au.full_name LIKE ? OR d.nidn LIKE ?)";
    $search_term_kajur = "%" . $search_query_kajur_dosen . "%";
    $params_list_kajur_dosen[] = $search_term_kajur;
    $params_list_kajur_dosen[] = $search_term_kajur;
    $types_list_kajur_dosen .= "ss";
}

if (!empty($department_managed) && $department_managed !== "Departemen Tidak Terdefinisi" && $department_managed !== "Error Mengambil Data Departemen") {
    $sql_total_dosen_jurusan = "SELECT COUNT(au.user_id) as total " . $sql_base_kajur_dosen . $sql_conditions_kajur_dosen;
    $stmt_total_dosen_jurusan = $conn->prepare($sql_total_dosen_jurusan);
    if ($stmt_total_dosen_jurusan) {
        $stmt_total_dosen_jurusan->bind_param($types_list_kajur_dosen, ...$params_list_kajur_dosen);
        $stmt_total_dosen_jurusan->execute();
        $res_total = $stmt_total_dosen_jurusan->get_result()->fetch_assoc();
        if ($res_total)
            $total_records_dosen_jurusan = (int) $res_total['total'];
        if ($limit_dosen > 0 && $total_records_dosen_jurusan > 0) {
            $total_pages_dosen_jurusan = ceil($total_records_dosen_jurusan / $limit_dosen);
        }
        $stmt_total_dosen_jurusan->close();
    }

    $sql_dosen_jurusan_select = "SELECT au.user_id, au.full_name, d.nidn, 
                                (SELECT AVG(e_sub.submission_average) FROM Evaluations e_sub WHERE e_sub.lecturer_user_id = au.user_id) as avg_score,
                                (SELECT COUNT(e_sub.evaluation_id) FROM Evaluations e_sub WHERE e_sub.lecturer_user_id = au.user_id) as total_evaluations ";
    $sql_dosen_jurusan_query = $sql_dosen_jurusan_select . $sql_base_kajur_dosen . $sql_conditions_kajur_dosen . " ORDER BY au.full_name ASC LIMIT ? OFFSET ?";

    $params_list_kajur_dosen_paginated = $params_list_kajur_dosen;
    $params_list_kajur_dosen_paginated[] = $limit_dosen;
    $params_list_kajur_dosen_paginated[] = $offset_dosen;
    $types_list_kajur_dosen_paginated = $types_list_kajur_dosen . "ii";

    $stmt_dosen_jurusan = $conn->prepare($sql_dosen_jurusan_query);
    if ($stmt_dosen_jurusan) {
        $stmt_dosen_jurusan->bind_param($types_list_kajur_dosen_paginated, ...$params_list_kajur_dosen_paginated);
        $stmt_dosen_jurusan->execute();
        $result_dosen_jurusan = $stmt_dosen_jurusan->get_result();
        while ($row = $result_dosen_jurusan->fetch_assoc()) {
            $row['avg_score'] = $row['avg_score'] ? round((float) $row['avg_score'], 2) : null;
            $dosen_jurusan_list[] = $row;
        }
        $stmt_dosen_jurusan->close();
    } else {
        error_log("Kajur Dashboard: Gagal prepare statement untuk dosen_jurusan_list: " . $conn->error);
    }
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
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_force_sidebar_closed; ?>;
    </script>
    <style>
        /* Gaya untuk Paginasi dan Filter Form (Sama seperti admin_manage_users.php) */
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

        .filter-form input[type="text"] {
            height: 40px;
            padding: 8px 12px;
            font-size: 0.9em;
            border-radius: 6px;
            border: 1px solid var(--input-border-color);
            width: 100%;
            box-sizing: border-box;
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
            /* Latar putih untuk default */
            color: var(--primary-color);
            /* Teks ungu untuk default */
        }

        .pagination li a:hover,
        .pagination li.active span,
        .pagination li.active a {
            background-color: var(--primary-color);
            /* Latar ungu untuk hover dan aktif */
            color: var(--white-color);
            /* Teks putih untuk hover dan aktif */
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
                <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span
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
                <p><strong>Periode Data:</strong> <?php echo htmlspecialchars($periode_evaluasi_display); ?></p>
            </div>

            <div class="department-summary-grid">
                <div class="summary-card">
                    <h4>Total Dosen Aktif di Jurusan</h4>
                    <p><?php echo $total_dosen_in_dept_aktif; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Total Evaluasi di Jurusan</h4>
                    <p><?php echo $total_evals_in_dept; ?></p>
                </div>
                <div class="summary-card">
                    <h4>Rata-Rata Skor Jurusan</h4>
                    <p><?php echo number_format($dept_overall_avg, 2); ?> / 4.00</p>
                </div>
            </div>

            <section class="content-card">
                <h2><i class="fas fa-users"></i> Daftar Evaluasi Dosen (Aktif) di Jurusan
                    "<?php echo htmlspecialchars($department_managed); ?>"</h2>

                <form action="kajur_dashboard.php" method="GET" class="filter-form">
                    <div class="input-group">
                        <label for="search_dosen">Cari Dosen</label>
                        <input type="text" name="search_dosen" id="search_dosen" placeholder="Nama atau NIDN..."
                            value="<?php echo htmlspecialchars($search_query_kajur_dosen); ?>">
                    </div>
                    <button type="submit" class="btn-login btn-filter-action"><i class="fas fa-filter"></i>
                        Filter</button>
                    <a href="kajur_dashboard.php" class="btn-filter-action btn-reset-filter"><i
                            class="fas fa-times"></i> Reset</a>
                </form>

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
                                                <a href="kajur_lihat_evaluasi_dosen.php?dosen_id=<?php echo $dosen['user_id']; ?>"
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
                                <?php $query_params_kajur = "&search_dosen=" . urlencode($search_query_kajur_dosen); ?>
                                <?php if ($page_dosen > 1): ?>
                                    <li><a
                                            href="?page_dosen=<?php echo $page_dosen - 1; ?><?php echo $query_params_kajur; ?>">Sebelumnya</a>
                                    </li>
                                <?php else: ?>
                                    <li class="disabled"><span>Sebelumnya</span></li>
                                <?php endif; ?>

                                <?php
                                $start_loop_dosen_p = max(1, $page_dosen - 2);
                                $end_loop_dosen_p = min($total_pages_dosen_jurusan, $page_dosen + 2);
                                if ($start_loop_dosen_p > 1) {
                                    echo '<li><a href="?page_dosen=1' . $query_params_kajur . '">1</a></li>';
                                    if ($start_loop_dosen_p > 2)
                                        echo '<li class="disabled"><span>...</span></li>';
                                }
                                for ($i = $start_loop_dosen_p; $i <= $end_loop_dosen_p; $i++): ?>
                                    <li class="<?php echo ($i == $page_dosen) ? 'active' : ''; ?>">
                                        <?php if ($i == $page_dosen): ?>
                                            <span><?php echo $i; ?></span>
                                        <?php else: ?>
                                            <a href="?page_dosen=<?php echo $i; ?><?php echo $query_params_kajur; ?>"><?php echo $i; ?></a>
                                        <?php endif; ?>
                                    </li>
                                <?php endfor;
                                if ($end_loop_dosen_p < $total_pages_dosen_jurusan) {
                                    if ($end_loop_dosen_p < $total_pages_dosen_jurusan - 1)
                                        echo '<li class="disabled"><span>...</span></li>';
                                    echo '<li><a href="?page_dosen=' . $total_pages_dosen_jurusan . $query_params_kajur . '">' . $total_pages_dosen_jurusan . '</a></li>';
                                } ?>
                                <?php if ($page_dosen < $total_pages_dosen_jurusan): ?>
                                    <li><a
                                            href="?page_dosen=<?php echo $page_dosen + 1; ?><?php echo $query_params_kajur; ?>">Berikutnya</a>
                                    </li>
                                <?php else: ?>
                                    <li class="disabled"><span>Berikutnya</span></li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>

                    <?php else: ?>
                        <p>
                            <?php if (!empty($search_query_kajur_dosen)): ?>
                                Tidak ada dosen aktif yang cocok dengan kriteria pencarian Anda di jurusan
                                "<?php echo htmlspecialchars($department_managed); ?>".
                            <?php else: ?>
                                Belum ada data dosen aktif yang terdaftar di jurusan
                                "<?php echo htmlspecialchars($department_managed); ?>"
                                atau belum ada evaluasi yang masuk untuk dosen di jurusan tersebut.
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
    <script src="js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>