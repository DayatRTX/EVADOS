<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// evados/admin_dashboard.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php'; //

$js_initial_sidebar_force_closed = 'false';
if (isset($_SESSION['initial_dashboard_load_sidebar_closed']) && $_SESSION['initial_dashboard_load_sidebar_closed'] === true) {
    $js_initial_sidebar_force_closed = 'true';
    unset($_SESSION['initial_dashboard_load_sidebar_closed']);
}
$current_page_php = basename($_SERVER['PHP_SELF']);

// Ambil data statistik
$total_users = 0;
$total_dosen = 0;
$total_mahasiswa = 0;
$total_kajur = 0;
$total_admin = 0;
$total_jadwal = 0;
$total_matakuliah = 0;
$total_evaluasi_masuk = 0;

// Total Pengguna berdasarkan peran
$stmt_users_roles = $conn->query("SELECT role, COUNT(user_id) as count FROM Auth_Users GROUP BY role"); //
if ($stmt_users_roles) {
    while ($row_role = $stmt_users_roles->fetch_assoc()) {
        $total_users += $row_role['count'];
        if ($row_role['role'] == 'dosen')
            $total_dosen = $row_role['count'];
        if ($row_role['role'] == 'mahasiswa')
            $total_mahasiswa = $row_role['count'];
        if ($row_role['role'] == 'kajur')
            $total_kajur = $row_role['count'];
        if ($row_role['role'] == 'admin')
            $total_admin = $row_role['count'];
    }
    $stmt_users_roles->close();
}

// Total Jadwal Mengajar
$stmt_total_jadwal = $conn->query("SELECT COUNT(jadwal_id) as count FROM jadwal_mengajar"); //
if ($stmt_total_jadwal) {
    $row_total_jadwal = $stmt_total_jadwal->fetch_assoc();
    if ($row_total_jadwal)
        $total_jadwal = (int) $row_total_jadwal['count'];
    $stmt_total_jadwal->close();
}

// Total Mata Kuliah
$stmt_total_mk = $conn->query("SELECT COUNT(mk_id) as count FROM mata_kuliah"); //
if ($stmt_total_mk) {
    $row_total_mk = $stmt_total_mk->fetch_assoc();
    if ($row_total_mk)
        $total_matakuliah = (int) $row_total_mk['count'];
    $stmt_total_mk->close();
}

// Total Evaluasi yang Sudah Masuk
$stmt_total_eval = $conn->query("SELECT COUNT(evaluation_id) as count FROM evaluations"); //
if ($stmt_total_eval) {
    $row_total_eval = $stmt_total_eval->fetch_assoc();
    if ($row_total_eval)
        $total_evaluasi_masuk = (int) $row_total_eval['count'];
    $stmt_total_eval->close();
}

// Ambil pengaturan sistem untuk ditampilkan (opsional)
$semester_aktif_info = "Belum diatur";
$batas_akhir_info = "Belum diatur";
$result_settings_info = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('semester_aktif', 'batas_akhir_penilaian')");
if ($result_settings_info) {
    while ($row_setting = $result_settings_info->fetch_assoc()) {
        if ($row_setting['setting_key'] == 'semester_aktif')
            $semester_aktif_info = $row_setting['setting_value'];
        if ($row_setting['setting_key'] == 'batas_akhir_penilaian') {
            if (class_exists('IntlDateFormatter')) {
                try {
                    $date_obj_info = DateTime::createFromFormat('Y-m-d', $row_setting['setting_value']);
                    if ($date_obj_info) {
                        $formatter_info = new IntlDateFormatter('id_ID', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'Asia/Jakarta');
                        $batas_akhir_info = $formatter_info->format($date_obj_info);
                    } else {
                        $batas_akhir_info = $row_setting['setting_value'];
                    }
                } catch (Exception $e) {
                    $batas_akhir_info = $row_setting['setting_value'];
                }
            } else {
                $batas_akhir_info = $row_setting['setting_value'];
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Evados</title>
    <link rel="icon" href="../logo.png" type="image/png" />
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .summary-card-admin {
            background-color: var(--white-color);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.07);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: inherit;
            border-left: 5px solid transparent;
        }

        .summary-card-admin:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .summary-card-admin .icon {
            font-size: 2.6em;
            margin-right: 20px;
            padding: 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .summary-card-admin.users-card {
            border-left-color: #3498DB;
        }

        .summary-card-admin.users-card .icon {
            color: #3498DB;
            background-color: rgba(52, 152, 219, 0.1);
        }

        .summary-card-admin.dosen-card {
            border-left-color: #2ECC71;
        }

        .summary-card-admin.dosen-card .icon {
            color: #2ECC71;
            background-color: rgba(46, 204, 113, 0.1);
        }

        .summary-card-admin.mahasiswa-card {
            border-left-color: #F39C12;
        }

        .summary-card-admin.mahasiswa-card .icon {
            color: #F39C12;
            background-color: rgba(243, 156, 18, 0.1);
        }

        .summary-card-admin.jadwal-card {
            border-left-color: #9B59B6;
        }

        .summary-card-admin.jadwal-card .icon {
            color: #9B59B6;
            background-color: rgba(155, 89, 182, 0.1);
        }

        .summary-card-admin.admin-role-card {
            border-left-color: #E74C3C;
        }

        .summary-card-admin.admin-role-card .icon {
            color: #E74C3C;
            background-color: rgba(231, 76, 60, 0.1);
        }

        .summary-card-admin.kajur-role-card {
            border-left-color: #1ABC9C;
        }

        .summary-card-admin.kajur-role-card .icon {
            color: #1ABC9C;
            background-color: rgba(26, 188, 156, 0.1);
        }

        .summary-card-admin.matakuliah-card {
            border-left-color: #34495E;
        }

        .summary-card-admin.matakuliah-card .icon {
            color: #34495E;
            background-color: rgba(52, 73, 94, 0.1);
        }

        .summary-card-admin.evaluasi-card {
            border-left-color: #E67E22;
        }

        .summary-card-admin.evaluasi-card .icon {
            color: #E67E22;
            background-color: rgba(230, 126, 34, 0.1);
        }

        .summary-card-admin .info h4 {
            margin: 0 0 5px 0;
            font-size: 1em;
            color: var(--text-color);
            font-weight: 500;
        }

        .summary-card-admin .info p {
            margin: 0;
            font-size: 2em;
            font-weight: 700;
            color: var(--primary-color);
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .action-card {
            background-color: var(--white-color);
            color: var(--text-color);
            padding: 25px 20px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 130px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--tertiary-color);
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }

        .action-card i {
            font-size: 2.2em;
            margin-bottom: 12px;
            color: var(--primary-color);
        }

        .action-card span {
            font-weight: 500;
            font-size: 0.95em;
        }

        .content-card h2 .fas,
        .content-card h2 .fa {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .info-section {
            margin-bottom: 30px;
        }

        .info-section .content-card {
            background-color: var(--white-color);
        }

        .info-section h2 {
            color: var(--text-color);
        }

        .info-section p {
            font-size: 1.05em;
        }

        .info-section strong {
            color: var(--primary-color);
        }
    </style>
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_initial_sidebar_force_closed; ?>;
    </script>
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
                    <li><a href="admin_dashboard.php"
                            class="<?php echo ($current_page_php == 'admin_dashboard.php') ? 'active' : ''; ?>"><i
                                class="fas fa-tachometer-alt"></i> <span class="menu-text">Dashboard</span></a></li>
                    <li><a href="admin_manage_users.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_users.php') !== false || strpos($current_page_php, 'admin_edit_user.php') !== false) ? 'active' : ''; ?>"><i
                                class="fas fa-users-cog"></i> <span class="menu-text">User</span></a></li>
                    <li><a href="admin_manage_dosen.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_dosen.php') !== false || strpos($current_page_php, 'admin_edit_dosen.php') !== false) ? 'active' : ''; ?>"><i
                                class="fas fa-chalkboard-teacher"></i> <span class="menu-text">Dosen</span></a></li>
                    <li><a href="admin_manage_mahasiswa.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_mahasiswa.php') !== false || strpos($current_page_php, 'admin_edit_mahasiswa.php') !== false) ? 'active' : ''; ?>"><i
                                class="fas fa-user-graduate"></i> <span class="menu-text">Mahasiswa</span></a>
                    </li>
                    <li><a href="admin_manage_jadwal.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_jadwal.php') !== false || strpos($current_page_php, 'admin_edit_jadwal.php') !== false) ? 'active' : ''; ?>"><i
                                class="fas fa-calendar-alt"></i> <span class="menu-text">Jadwal</span></a>
                    </li>
                    <li><a href="admin_manage_matakuliah.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_matakuliah.php') !== false || strpos($current_page_php, 'admin_edit_matakuliah.php') !== false) ? 'active' : ''; ?>"><i
                                class="fas fa-book"></i> <span class="menu-text">Matakuliah</span></a></li>
                    <li><a href="admin_settings.php"
                            class="<?php echo ($current_page_php == 'admin_settings.php') ? 'active' : ''; ?>"><i
                                class="fas fa-cog"></i> <span class="menu-text">Pengaturan Sistem</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-logout-section">
                <a href="../logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span
                        class="menu-text">Logout</span></a>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <header class="header">
                <h1>Selamat Datang, <?php echo htmlspecialchars($loggedInAdminFullName); ?>!</h1>
            </header>

            <div class="info-section">
                <div class="content-card">
                    <h2><i class="fas fa-info-circle"></i> Informasi Sistem Saat Ini</h2>
                    <p><strong>Semester Aktif:</strong> <?php echo htmlspecialchars($semester_aktif_info); ?></p>
                    <p><strong>Batas Akhir Penilaian Dosen:</strong> <?php echo htmlspecialchars($batas_akhir_info); ?>
                    </p>
                    <p style="margin-top:10px;"><a href="admin_settings.php" class="btn-penilaian"
                            style="background-color: var(--primary-color); color:white;">Ubah Pengaturan</a></p>
                </div>
            </div>

            <div class="summary-grid">
                <a href="admin_manage_users.php" class="summary-card-admin users-card">
                    <div class="icon users"><i class="fas fa-users"></i></div>
                    <div class="info">
                        <h4>Total Pengguna</h4>
                        <p><?php echo $total_users; ?></p>
                    </div>
                </a>
                <a href="admin_manage_dosen.php" class="summary-card-admin dosen-card">
                    <div class="icon dosen"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="info">
                        <h4>Total Dosen Aktif</h4>
                        <p><?php echo $total_dosen; ?></p>
                    </div>
                </a>
                <a href="admin_manage_mahasiswa.php" class="summary-card-admin mahasiswa-card">
                    <div class="icon mahasiswa"><i class="fas fa-user-graduate"></i></div>
                    <div class="info">
                        <h4>Total Mhs Aktif</h4>
                        <p><?php echo $total_mahasiswa; ?></p>
                    </div>
                </a>
                <a href="admin_manage_users.php?role_filter=kajur" class="summary-card-admin kajur-role-card">
                    <div class="icon kajur-role"><i class="fas fa-sitemap"></i></div>
                    <div class="info">
                        <h4>Total Kajur</h4>
                        <p><?php echo $total_kajur; ?></p>
                    </div>
                </a>
                <a href="admin_manage_users.php?role_filter=admin" class="summary-card-admin admin-role-card">
                    <div class="icon admin-role"><i class="fas fa-user-shield"></i></div>
                    <div class="info">
                        <h4>Total Admin</h4>
                        <p><?php echo $total_admin; ?></p>
                    </div>
                </a>
                <a href="admin_manage_jadwal.php" class="summary-card-admin jadwal-card">
                    <div class="icon jadwal"><i class="fas fa-calendar-alt"></i></div>
                    <div class="info">
                        <h4>Total Jadwal</h4>
                        <p><?php echo $total_jadwal; ?></p>
                    </div>
                </a>
                <a href="admin_manage_matakuliah.php" class="summary-card-admin matakuliah-card">
                    <div class="icon matakuliah"><i class="fas fa-book-open"></i></div>
                    <div class="info">
                        <h4>Total Matakuliah</h4>
                        <p><?php echo $total_matakuliah; ?></p>
                    </div>
                </a>
                <div class="summary-card-admin evaluasi-card">
                    <div class="icon evaluasi"><i class="fas fa-chart-bar"></i></div>
                    <div class="info">
                        <h4>Evaluasi Masuk</h4>
                        <p><?php echo $total_evaluasi_masuk; ?></p>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <h2><i class="fas fa-bolt"></i> Pintasan Cepat</h2>
                <div class="quick-actions-grid">
                    <a href="admin_edit_user.php?action=add" class="action-card">
                        <i class="fas fa-user-plus"></i><span>Tambah Pengguna</span>
                    </a>
                    <a href="admin_edit_dosen.php?action=add" class="action-card">
                        <i class="fas fa-user-tie"></i><span>Tambah Dosen</span>
                    </a>
                    <a href="admin_edit_mahasiswa.php?action=add" class="action-card">
                        <i class="fas fa-id-card-alt"></i><span>Tambah Mahasiswa</span>
                    </a>
                    <a href="admin_edit_jadwal.php?action=add" class="action-card">
                        <i class="fas fa-calendar-plus"></i><span>Tambah Jadwal</span>
                    </a>
                    <a href="admin_edit_matakuliah.php?action=add" class="action-card">
                        <i class="fas fa-folder-plus"></i><span>Tambah Matakuliah</span>
                    </a>
                    <a href="admin_settings.php" class="action-card">
                        <i class="fas fa-cogs"></i><span>Pengaturan Sistem</span>
                    </a>
                </div>
            </div>

        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>