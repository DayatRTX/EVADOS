<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// evados/admin_manage_users.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php';

$page_title = "Manajemen Pengguna";
$success_message = '';
$error_message = '';

// Variabel untuk sidebar dan JS - PENTING diinisialisasi di awal
$current_page_php = basename($_SERVER['PHP_SELF']);
$js_initial_sidebar_force_closed = 'false';

// Ambil pesan dari sesi
if (isset($_SESSION['success_message_user_manage'])) {
    $success_message = $_SESSION['success_message_user_manage'];
    unset($_SESSION['success_message_user_manage']);
}
if (isset($_SESSION['error_message_user_manage'])) {
    $error_message = $_SESSION['error_message_user_manage'];
    unset($_SESSION['error_message_user_manage']);
}

// Logika untuk Hapus Pengguna
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['user_id'])) {
    $user_id_to_delete = intval($_GET['user_id']);
    if ($user_id_to_delete == $loggedInAdminId) {
        $error_message = "Anda tidak dapat menghapus akun Anda sendiri.";
    } else {
        $conn->begin_transaction();
        try {
            $role_stmt = $conn->prepare("SELECT role FROM Auth_Users WHERE user_id = ?"); //
            $role_stmt->bind_param("i", $user_id_to_delete);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            $user_to_delete_data = $role_result->fetch_assoc();
            $role_stmt->close();

            if ($user_to_delete_data) {
                $role = $user_to_delete_data['role'];
                if ($role == 'dosen') {
                    $stmt_del_jadwal = $conn->prepare("DELETE FROM jadwal_mengajar WHERE dosen_user_id = ?"); //
                    if ($stmt_del_jadwal) {
                        $stmt_del_jadwal->bind_param("i", $user_id_to_delete);
                        $stmt_del_jadwal->execute();
                        $stmt_del_jadwal->close();
                    }
                    $stmt_del_role_specific = $conn->prepare("DELETE FROM Dosen WHERE user_id = ?"); //
                } elseif ($role == 'mahasiswa') {
                    $stmt_del_evals = $conn->prepare("DELETE FROM evaluations WHERE student_user_id = ?"); //
                    if ($stmt_del_evals) {
                        $stmt_del_evals->bind_param("i", $user_id_to_delete);
                        $stmt_del_evals->execute();
                        $stmt_del_evals->close();
                    }
                    $stmt_del_role_specific = $conn->prepare("DELETE FROM Mahasiswa WHERE user_id = ?"); //
                } elseif ($role == 'kajur') {
                    $stmt_del_role_specific = $conn->prepare("DELETE FROM Kajur WHERE user_id = ?"); //
                }

                if (isset($stmt_del_role_specific)) {
                    $stmt_del_role_specific->bind_param("i", $user_id_to_delete);
                    $stmt_del_role_specific->execute();
                    $stmt_del_role_specific->close();
                }
            }

            $stmt_delete_auth = $conn->prepare("DELETE FROM Auth_Users WHERE user_id = ?"); //
            if ($stmt_delete_auth) {
                $stmt_delete_auth->bind_param("i", $user_id_to_delete);
                if ($stmt_delete_auth->execute()) {
                    if ($stmt_delete_auth->affected_rows > 0) {
                        $success_message = "Pengguna berhasil dihapus beserta data terkait.";
                    } else {
                        $error_message = "Pengguna tidak ditemukan di tabel utama atau sudah dihapus.";
                    }
                } else {
                    throw new Exception("Gagal menghapus pengguna dari Auth_Users: " . $stmt_delete_auth->error);
                }
                $stmt_delete_auth->close();
            } else {
                throw new Exception("Gagal mempersiapkan penghapusan dari Auth_Users: " . $conn->error);
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Terjadi kesalahan saat menghapus pengguna: " . $e->getMessage();
        }
    }
}

// Logika Paginasi
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$users = [];
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$sql_base = "FROM Auth_Users WHERE 1=1"; //
$sql_conditions = "";
$params_list = [];
$types_list = "";

if (!empty($role_filter)) {
    $sql_conditions .= " AND role = ?";
    $params_list[] = $role_filter;
    $types_list .= "s";
}
if (!empty($search_query)) {
    $sql_conditions .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_term = "%" . $search_query . "%";
    $params_list[] = $search_term;
    $params_list[] = $search_term;
    $params_list[] = $search_term;
    $types_list .= "sss";
}
if ($status_filter !== '') {
    $sql_conditions .= " AND is_active = ?";
    $params_list[] = (int) $status_filter;
    $types_list .= "i";
}

$total_records = 0;
$total_pages = 1;
$sql_total_count = "SELECT COUNT(user_id) as total " . $sql_base . $sql_conditions;
$stmt_total_count = $conn->prepare($sql_total_count);
if ($stmt_total_count) {
    if (!empty($params_list)) {
        $stmt_total_count->bind_param($types_list, ...$params_list);
    }
    $stmt_total_count->execute();
    $total_records_result = $stmt_total_count->get_result();
    if ($total_records_result) {
        $total_records_row = $total_records_result->fetch_assoc();
        if ($total_records_row) {
            $total_records = (int) $total_records_row['total'];
        }
    }
    if ($limit > 0 && $total_records > 0) {
        $total_pages = ceil($total_records / $limit);
    }
    $stmt_total_count->close();
}

$sql_users_list_select = "SELECT user_id, username, email, full_name, role, is_active ";
$sql_users_list_query = $sql_users_list_select . $sql_base . $sql_conditions . " ORDER BY role, full_name ASC LIMIT ? OFFSET ?";
$params_list_paginated = $params_list;
$params_list_paginated[] = $limit;
$params_list_paginated[] = $offset;
$types_list_paginated = $types_list . "ii";

$stmt_users_list = $conn->prepare($sql_users_list_query);
if ($stmt_users_list) {
    if (!empty($types_list_paginated)) {
        $stmt_users_list->bind_param($types_list_paginated, ...$params_list_paginated);
    }
    $stmt_users_list->execute();
    $result_users = $stmt_users_list->get_result();
    if ($result_users) {
        while ($row = $result_users->fetch_assoc()) {
            $users[] = $row;
        }
    }
    $stmt_users_list->close();
} else {
    $error_message = "Gagal mempersiapkan daftar pengguna: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Evados</title>
    <link rel="icon" href="../logo.png" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_initial_sidebar_force_closed; ?>;
        function confirmDeleteUser(userId, userName) {
            return confirm("Apakah Anda yakin ingin menghapus pengguna '" + userName + "' (ID Akun: " + userId + ")? Tindakan ini akan menghapus akun login dan semua data terkait pengguna tersebut. Tindakan ini tidak dapat diurungkan.");
        }
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
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
            </header>

            <?php if (!empty($success_message)): ?>
                <div class="success-msg"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-msg"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <section class="content-card">
                <div class="page-header-actions">
                    <h2><i class="fas fa-users"></i> Daftar Pengguna Sistem</h2>
                    <a href="admin_edit_user.php?action=add" class="btn-login"
                        style="width:auto; padding: 10px 18px;"><i class="fas fa-user-plus"></i> Tambah Pengguna</a>
                </div>

                <form action="admin_manage_users.php" method="GET" class="filter-form">
                    <div class="input-group">
                        <label for="search">Cari Pengguna</label>
                        <input type="text" name="search" id="search" placeholder="Nama, email, username..."
                            value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    <div class="input-group">
                        <label for="role_filter">Filter Peran</label>
                        <select name="role_filter" id="role_filter">
                            <option value="">Semua Peran</option>
                            <option value="admin" <?php echo ($role_filter == 'admin') ? 'selected' : ''; ?>>Admin
                            </option>
                            <option value="dosen" <?php echo ($role_filter == 'dosen') ? 'selected' : ''; ?>>Dosen
                            </option>
                            <option value="mahasiswa" <?php echo ($role_filter == 'mahasiswa') ? 'selected' : ''; ?>>
                                Mahasiswa</option>
                            <option value="kajur" <?php echo ($role_filter == 'kajur') ? 'selected' : ''; ?>>Kajur
                            </option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="status_filter">Filter Status Akun</label>
                        <select name="status_filter" id="status_filter">
                            <option value="">Semua Status</option>
                            <option value="1" <?php echo ($status_filter === '1') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="0" <?php echo ($status_filter === '0') ? 'selected' : ''; ?>>Tidak Aktif
                            </option>
                        </select>
                    </div>
                    <button type="submit" class="btn-login btn-filter-action"><i class="fas fa-filter"></i>
                        Filter</button>
                    <a href="admin_manage_users.php" class="btn-filter-action btn-reset-filter"><i
                            class="fas fa-times"></i> Reset</a>
                </form>

                <div style="overflow-x: auto;">
                    <table class="dosen-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No.</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Peran</th>
                                <th>Status</th>
                                <th style="min-width:100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php $nomor = $offset + 1; ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $nomor++; ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username'] ?? '-'); ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($user['role'])); ?></td>
                                        <td>
                                            <?php if ($user['is_active'] == 1): ?>
                                                <span class="status-aktif"><i class="fas fa-check-circle"></i> Aktif</span>
                                            <?php else: ?>
                                                <span class="status-tidak-aktif"><i class="fas fa-times-circle"></i> Tidak
                                                    Aktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="btn-action-group">
                                            <a href="admin_edit_user.php?user_id=<?php echo $user['user_id']; ?>"
                                                class="btn-penilaian" style="font-size:0.8em; padding: 4px 8px;" title="Ubah"><i
                                                    class="fas fa-edit"></i></a>
                                            <?php if ($user['user_id'] != $loggedInAdminId): ?>
                                                <a href="admin_manage_users.php?action=delete&user_id=<?php echo $user['user_id']; ?>"
                                                    class="btn-penilaian"
                                                    style="font-size:0.8em; padding: 4px 8px; background-color: var(--error-color); color: gray;"
                                                    title="Hapus"
                                                    onclick="return confirmDeleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;">
                                        <?php if (!empty($search_query) || !empty($role_filter) || $status_filter !== ''): ?>
                                            Tidak ada pengguna yang cocok dengan kriteria pencarian/filter Anda.
                                        <?php else: ?>
                                            Belum ada pengguna terdaftar.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <ul class="pagination">
                        <?php $query_params = "&role_filter=" . urlencode($role_filter) . "&search=" . urlencode($search_query) . "&status_filter=" . urlencode($status_filter); ?>
                        <?php if ($page > 1): ?>
                            <li><a href="?page=<?php echo $page - 1; ?><?php echo $query_params; ?>">Sebelumnya</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>Sebelumnya</span></li>
                        <?php endif; ?>

                        <?php
                        $start_loop = max(1, $page - 2);
                        $end_loop = min($total_pages, $page + 2);

                        if ($start_loop > 1) {
                            echo '<li><a href="?page=1' . $query_params . '">1</a></li>';
                            if ($start_loop > 2) {
                                echo '<li class="disabled"><span>...</span></li>';
                            }
                        }

                        for ($i = $start_loop; $i <= $end_loop; $i++): ?>
                            <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php if ($i == $page): ?>
                                    <span><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $query_params; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_loop < $total_pages): ?>
                            <?php if ($end_loop < $total_pages - 1): ?>
                                <li class="disabled"><span>...</span></li>
                            <?php endif; ?>
                            <li><a
                                    href="?page=<?php echo $total_pages; ?><?php echo $query_params; ?>"><?php echo $total_pages; ?></a>
                            </li>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <li><a href="?page=<?php echo $page + 1; ?><?php echo $query_params; ?>">Berikutnya</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>Berikutnya</span></li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>