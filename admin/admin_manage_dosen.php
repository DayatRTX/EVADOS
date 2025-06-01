<?php
// evados/admin_manage_dosen.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php'; //

$page_title = "Manajemen Dosen";
$success_message = '';
$error_message = '';

// Variabel untuk sidebar dan JS
$current_page_php = basename($_SERVER['PHP_SELF']);
$js_initial_sidebar_force_closed = 'false';

// Ambil pesan dari sesi
if (isset($_SESSION['success_message_dosen_manage'])) {
    $success_message = $_SESSION['success_message_dosen_manage'];
    unset($_SESSION['success_message_dosen_manage']);
}
if (isset($_SESSION['error_message_dosen_manage'])) {
    $error_message = $_SESSION['error_message_dosen_manage'];
    unset($_SESSION['error_message_dosen_manage']);
}

// Logika untuk Hapus Dosen (Sama seperti sebelumnya)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['user_id'])) {
    $user_id_to_delete = intval($_GET['user_id']);

    $conn->begin_transaction();
    try {
        $stmt_delete_jadwal = $conn->prepare("DELETE FROM jadwal_mengajar WHERE dosen_user_id = ?"); //
        if ($stmt_delete_jadwal) {
            $stmt_delete_jadwal->bind_param("i", $user_id_to_delete);
            $stmt_delete_jadwal->execute();
            $stmt_delete_jadwal->close();
        } else {
            throw new Exception("Gagal mempersiapkan penghapusan jadwal: " . $conn->error);
        }

        $stmt_delete_dosen = $conn->prepare("DELETE FROM Dosen WHERE user_id = ?"); //
        if ($stmt_delete_dosen) {
            $stmt_delete_dosen->bind_param("i", $user_id_to_delete);
            $stmt_delete_dosen->execute();
            $stmt_delete_dosen->close();
        } else {
            throw new Exception("Gagal mempersiapkan penghapusan dari tabel Dosen: " . $conn->error);
        }

        $stmt_delete_auth = $conn->prepare("DELETE FROM Auth_Users WHERE user_id = ? AND role = 'dosen'"); //
        if ($stmt_delete_auth) {
            $stmt_delete_auth->bind_param("i", $user_id_to_delete);
            if ($stmt_delete_auth->execute()) {
                if ($stmt_delete_auth->affected_rows > 0) {
                    $success_message = "Dosen berhasil dihapus beserta data terkait.";
                } else {
                    $error_message = "Dosen tidak ditemukan atau bukan dosen.";
                }
            } else {
                throw new Exception("Gagal menghapus dosen dari Auth_Users: " . $stmt_delete_auth->error);
            }
            $stmt_delete_auth->close();
        } else {
            throw new Exception("Gagal mempersiapkan penghapusan dari Auth_Users: " . $conn->error);
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Terjadi kesalahan saat menghapus dosen: " . $e->getMessage();
    }
}

// Logika Paginasi
$limit = 10;
$page = isset($_GET['page_dosen']) && is_numeric($_GET['page_dosen']) ? (int) $_GET['page_dosen'] : 1; // Gunakan page_dosen
$offset = ($page - 1) * $limit;

// Filter dan Pencarian
$dosen_list = [];
$search_query_dosen = isset($_GET['search_dosen']) ? trim($_GET['search_dosen']) : '';
$department_filter = isset($_GET['department_filter']) ? trim($_GET['department_filter']) : '';

// HANYA AMBIL DOSEN YANG AKTIF DI AUTH_USERS
$sql_base_dosen = "FROM Auth_Users au JOIN Dosen d ON au.user_id = d.user_id WHERE au.role = 'dosen' AND au.is_active = 1"; //
$sql_conditions_dosen = "";
$params_list_dosen = [];
$types_list_dosen = "";

if (!empty($search_query_dosen)) {
    $sql_conditions_dosen .= " AND (au.full_name LIKE ? OR au.email LIKE ? OR d.nidn LIKE ?)";
    $search_term_dosen = "%" . $search_query_dosen . "%";
    $params_list_dosen[] = $search_term_dosen;
    $params_list_dosen[] = $search_term_dosen;
    $params_list_dosen[] = $search_term_dosen;
    $types_list_dosen .= "sss";
}
if (!empty($department_filter)) {
    $sql_conditions_dosen .= " AND d.department LIKE ?";
    $department_term = "%" . $department_filter . "%";
    $params_list_dosen[] = $department_term;
    $types_list_dosen .= "s";
}

$total_records_dosen = 0;
$total_pages_dosen = 1;
$sql_total_count_dosen = "SELECT COUNT(au.user_id) as total " . $sql_base_dosen . $sql_conditions_dosen;
$stmt_total_count_dosen = $conn->prepare($sql_total_count_dosen);
if ($stmt_total_count_dosen) {
    if (!empty($params_list_dosen)) {
        $stmt_total_count_dosen->bind_param($types_list_dosen, ...$params_list_dosen);
    }
    $stmt_total_count_dosen->execute();
    $total_records_result_dosen = $stmt_total_count_dosen->get_result();
    if ($total_records_result_dosen) {
        $total_records_row_dosen = $total_records_result_dosen->fetch_assoc();
        if ($total_records_row_dosen)
            $total_records_dosen = (int) $total_records_row_dosen['total'];
    }
    if ($limit > 0 && $total_records_dosen > 0) {
        $total_pages_dosen = ceil($total_records_dosen / $limit);
    }
    $stmt_total_count_dosen->close();
}

$sql_dosen_list_select = "SELECT au.user_id, au.full_name, au.email, d.nidn, d.department ";
$sql_dosen_list_query = $sql_dosen_list_select . $sql_base_dosen . $sql_conditions_dosen . " ORDER BY au.full_name ASC LIMIT ? OFFSET ?";
$params_list_dosen_paginated = $params_list_dosen;
$params_list_dosen_paginated[] = $limit;
$params_list_dosen_paginated[] = $offset;
$types_list_dosen_paginated = $types_list_dosen . "ii";

$stmt_dosen_list = $conn->prepare($sql_dosen_list_query);
if ($stmt_dosen_list) {
    if (!empty($types_list_dosen_paginated)) {
        $stmt_dosen_list->bind_param($types_list_dosen_paginated, ...$params_list_dosen_paginated);
    }
    $stmt_dosen_list->execute();
    $result_dosen_list = $stmt_dosen_list->get_result();
    if ($result_dosen_list) {
        while ($row = $result_dosen_list->fetch_assoc()) {
            $dosen_list[] = $row;
        }
    }
    $stmt_dosen_list->close();
} else {
    $error_message = "Gagal mempersiapkan daftar dosen: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Evados</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_initial_sidebar_force_closed; ?>;
        function confirmDeleteDosen(userId, dosenName) {
            return confirm("Apakah Anda yakin ingin menghapus dosen '" + dosenName + "' (ID Akun: " + userId + ")? Tindakan ini akan menghapus akun login dan semua data terkait dosen tersebut.");
        }
    </script>
    <style>
        .page-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .page-header-actions h2 {
            margin-bottom: 10px;
            margin-right: auto;
        }

        .page-header-actions .btn-login {
            margin-bottom: 10px;
            margin-left: 10px;
        }

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
            flex: 1 1 200px;
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
            border: 1px solid var(--input-border-color);
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

        .btn-action-group a {
            margin-right: 5px;
            display: inline-block;
        }

        .btn-action-group a:last-child {
            margin-right: 0;
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
            /* Gaya untuk SEMUA item paginasi */
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
        /* Gaya untuk halaman aktif (jika span) */
        .pagination li.active a

        /* Gaya untuk halaman aktif (jika link) */
            {
            background-color: var(--primary-color);
            /* Warna latar ungu */
            color: var(--white-color);
            /* Teks putih */
            border-color: var(--primary-color);
            /* Border sama dengan latar aktif */
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
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
            </header>

            <?php if (!empty($success_message)): ?>
                <div class="success-msg"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <section class="content-card">
                <div class="page-header-actions">
                    <h2><i class="fas fa-chalkboard-teacher"></i> Daftar Dosen Aktif</h2>
                    <a href="admin_edit_dosen.php?action=add" class="btn-login"
                        style="width:auto; padding: 10px 18px;"><i class="fas fa-user-plus"></i> Tambah Dosen</a>
                </div>

                <form action="admin_manage_dosen.php" method="GET" class="filter-form">
                    <div class="input-group">
                        <label for="search_dosen">Cari Dosen</label>
                        <input type="text" name="search_dosen" id="search_dosen" placeholder="Nama, NIDN, email..."
                            value="<?php echo htmlspecialchars($search_query_dosen); ?>">
                    </div>
                    <div class="input-group">
                        <label for="department_filter">Filter Departemen</label>
                        <input type="text" name="department_filter" id="department_filter"
                            placeholder="Nama departemen..."
                            value="<?php echo htmlspecialchars($department_filter); ?>">
                    </div>
                    <button type="submit" class="btn-login btn-filter-action"><i class="fas fa-filter"></i>
                        Filter</button>
                    <a href="admin_manage_dosen.php" class="btn-filter-action btn-reset-filter"><i
                            class="fas fa-times"></i> Reset</a>
                </form>

                <div style="overflow-x: auto;">
                    <table class="dosen-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No.</th>
                                <th>NIDN</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Departemen</th>
                                <th style="min-width:100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($dosen_list)): ?>
                                <?php $nomor = $offset + 1; ?>
                                <?php foreach ($dosen_list as $dosen): ?>
                                    <tr>
                                        <td><?php echo $nomor++; ?></td>
                                        <td><?php echo htmlspecialchars($dosen['nidn']); ?></td>
                                        <td><?php echo htmlspecialchars($dosen['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($dosen['email']); ?></td>
                                        <td><?php echo htmlspecialchars($dosen['department']); ?></td>
                                        <td class="btn-action-group">
                                            <a href="admin_edit_dosen.php?user_id=<?php echo $dosen['user_id']; ?>"
                                                class="btn-penilaian" style="font-size:0.8em; padding: 4px 8px;" title="Ubah"><i
                                                    class="fas fa-edit"></i></a>
                                            <a href="admin_manage_dosen.php?action=delete&user_id=<?php echo $dosen['user_id']; ?>"
                                                class="btn-penilaian"
                                                style="font-size:0.8em; padding: 4px 8px; background-color: var(--error-color); color: grey;"
                                                title="Hapus"
                                                onclick="return confirmDeleteDosen(<?php echo $dosen['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($dosen['full_name'])); ?>');">
                                                <i class="fas fa-trash btn-delete-icon"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;">
                                        <?php if (!empty($search_query_dosen) || !empty($department_filter)): ?>
                                            Tidak ada dosen aktif yang cocok dengan kriteria pencarian/filter Anda.
                                        <?php else: ?>
                                            Belum ada data dosen aktif.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages_dosen > 1): ?>
                    <ul class="pagination">
                        <?php $query_params_dosen = "&search_dosen=" . urlencode($search_query_dosen) . "&department_filter=" . urlencode($department_filter); ?>
                        <?php if ($page > 1): ?>
                            <li><a href="?page_dosen=<?php echo $page - 1; ?><?php echo $query_params_dosen; ?>">Sebelumnya</a>
                            </li> <?php else: ?>
                            <li class="disabled"><span>Sebelumnya</span></li> <?php endif; ?>
                        <?php
                        $start_loop_dosen = max(1, $page - 2);
                        $end_loop_dosen = min($total_pages_dosen, $page + 2);
                        if ($start_loop_dosen > 1) {
                            echo '<li><a href="?page_dosen=1' . $query_params_dosen . '">1</a></li>';
                            if ($start_loop_dosen > 2)
                                echo '<li class="disabled"><span>...</span></li>';
                        } // Ganti page menjadi page_dosen
                        for ($i = $start_loop_dosen; $i <= $end_loop_dosen; $i++): ?>
                            <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php if ($i == $page): ?>
                                    <span><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page_dosen=<?php echo $i; ?><?php echo $query_params_dosen; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor;
                        if ($end_loop_dosen < $total_pages_dosen) {
                            if ($end_loop_dosen < $total_pages_dosen - 1)
                                echo '<li class="disabled"><span>...</span></li>';
                            echo '<li><a href="?page_dosen=' . $total_pages_dosen . $query_params_dosen . '">' . $total_pages_dosen . '</a></li>';
                        } // Ganti page menjadi page_dosen ?>
                        <?php if ($page < $total_pages_dosen): ?>
                            <li><a href="?page_dosen=<?php echo $page + 1; ?><?php echo $query_params_dosen; ?>">Berikutnya</a>
                            </li> <?php else: ?>
                            <li class="disabled"><span>Berikutnya</span></li> <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>