<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// evados/admin_manage_mahasiswa.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php'; //

$page_title = "Manajemen Mahasiswa";
$success_message = '';
$error_message = '';

// Variabel untuk sidebar dan JS - PENTING diinisialisasi di awal
$current_page_php = basename($_SERVER['PHP_SELF']);
$js_initial_sidebar_force_closed = 'false';

// Ambil pesan dari sesi
if (isset($_SESSION['success_message_mhs_manage'])) {
    $success_message = $_SESSION['success_message_mhs_manage'];
    unset($_SESSION['success_message_mhs_manage']);
}
if (isset($_SESSION['error_message_mhs_manage'])) {
    $error_message = $_SESSION['error_message_mhs_manage'];
    unset($_SESSION['error_message_mhs_manage']);
}

// Logika untuk Hapus Mahasiswa
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['user_id'])) {
    $user_id_to_delete = intval($_GET['user_id']);

    // Admin tidak bisa menghapus akunnya sendiri, meskipun tidak mungkin admin adalah mahasiswa
    if ($user_id_to_delete == $loggedInAdminId) {
        $error_message = "Aksi tidak diizinkan.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt_delete_evals = $conn->prepare("DELETE FROM evaluations WHERE student_user_id = ?"); //
            if ($stmt_delete_evals) {
                $stmt_delete_evals->bind_param("i", $user_id_to_delete);
                $stmt_delete_evals->execute();
                $stmt_delete_evals->close();
            } else {
                throw new Exception("Gagal mempersiapkan penghapusan evaluasi terkait: " . $conn->error);
            }

            $stmt_delete_mhs = $conn->prepare("DELETE FROM Mahasiswa WHERE user_id = ?"); //
            if ($stmt_delete_mhs) {
                $stmt_delete_mhs->bind_param("i", $user_id_to_delete);
                $stmt_delete_mhs->execute();
                $stmt_delete_mhs->close();
            } else {
                throw new Exception("Gagal mempersiapkan penghapusan dari tabel Mahasiswa: " . $conn->error);
            }

            $stmt_delete_auth = $conn->prepare("DELETE FROM Auth_Users WHERE user_id = ? AND role = 'mahasiswa'"); //
            if ($stmt_delete_auth) {
                $stmt_delete_auth->bind_param("i", $user_id_to_delete);
                if ($stmt_delete_auth->execute()) {
                    if ($stmt_delete_auth->affected_rows > 0) {
                        $success_message = "Mahasiswa berhasil dihapus beserta data terkait.";
                    } else {
                        $error_message = "Mahasiswa tidak ditemukan atau bukan mahasiswa.";
                    }
                } else {
                    throw new Exception("Gagal menghapus mahasiswa dari Auth_Users: " . $stmt_delete_auth->error);
                }
                $stmt_delete_auth->close();
            } else {
                throw new Exception("Gagal mempersiapkan penghapusan dari Auth_Users: " . $conn->error);
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Terjadi kesalahan saat menghapus mahasiswa: " . $e->getMessage();
        }
    }
}

// Logika Paginasi
$limit = 10;
$page = isset($_GET['page_mhs']) && is_numeric($_GET['page_mhs']) ? (int) $_GET['page_mhs'] : 1; // Parameter unik untuk paginasi mahasiswa
$offset = ($page - 1) * $limit;

// Filter dan Pencarian
$mahasiswa_list = [];
$search_query_mhs = isset($_GET['search_mhs']) ? trim($_GET['search_mhs']) : '';
$kelas_filter = isset($_GET['kelas_filter']) ? trim($_GET['kelas_filter']) : '';
$angkatan_filter = isset($_GET['angkatan_filter']) ? trim($_GET['angkatan_filter']) : '';

// HANYA AMBIL MAHASISWA YANG AKTIF DI AUTH_USERS
$sql_base_mhs = "FROM Auth_Users au JOIN Mahasiswa m ON au.user_id = m.user_id WHERE au.role = 'mahasiswa' AND au.is_active = 1"; //
$sql_conditions_mhs = "";
$params_list_mhs = [];
$types_list_mhs = "";

if (!empty($search_query_mhs)) {
    $sql_conditions_mhs .= " AND (au.full_name LIKE ? OR au.email LIKE ? OR au.username LIKE ? OR m.npm LIKE ?)";
    $search_term_mhs = "%" . $search_query_mhs . "%";
    $params_list_mhs[] = $search_term_mhs;
    $params_list_mhs[] = $search_term_mhs;
    $params_list_mhs[] = $search_term_mhs;
    $params_list_mhs[] = $search_term_mhs;
    $types_list_mhs .= "ssss";
}
if (!empty($kelas_filter)) {
    $sql_conditions_mhs .= " AND m.kelas LIKE ?"; 
    $kelas_search_term = "%" . $kelas_filter . "%"; 
    $params_list_mhs[] = $kelas_search_term;
    $types_list_mhs .= "s";
}
if (!empty($angkatan_filter)) {
    $sql_conditions_mhs .= " AND m.angkatan = ?";
    $params_list_mhs[] = $angkatan_filter;
    $types_list_mhs .= "s";
}

$total_records_mhs = 0;
$total_pages_mhs = 1;
$sql_total_count_mhs = "SELECT COUNT(au.user_id) as total " . $sql_base_mhs . $sql_conditions_mhs;
$stmt_total_count_mhs = $conn->prepare($sql_total_count_mhs);
if ($stmt_total_count_mhs) {
    if (!empty($params_list_mhs)) {
        $stmt_total_count_mhs->bind_param($types_list_mhs, ...$params_list_mhs);
    }
    $stmt_total_count_mhs->execute();
    $total_records_result_mhs = $stmt_total_count_mhs->get_result();
    if ($total_records_result_mhs) {
        $total_records_row_mhs = $total_records_result_mhs->fetch_assoc();
        if ($total_records_row_mhs)
            $total_records_mhs = (int) $total_records_row_mhs['total'];
    }
    if ($limit > 0 && $total_records_mhs > 0) {
        $total_pages_mhs = ceil($total_records_mhs / $limit);
    }
    $stmt_total_count_mhs->close();
}

$sql_mhs_list_select = "SELECT au.user_id, au.full_name, au.email, au.username, m.npm, m.angkatan, m.kelas, m.jabatan_kelas ";
$sql_mhs_list_query = $sql_mhs_list_select . $sql_base_mhs . $sql_conditions_mhs . " ORDER BY m.kelas ASC, m.angkatan DESC, au.full_name ASC LIMIT ? OFFSET ?";
$params_list_mhs_paginated = $params_list_mhs;
$params_list_mhs_paginated[] = $limit;
$params_list_mhs_paginated[] = $offset;
$types_list_mhs_paginated = $types_list_mhs . "ii";

$stmt_mhs_list = $conn->prepare($sql_mhs_list_query);
if ($stmt_mhs_list) {
    if (!empty($types_list_mhs_paginated)) {
        $stmt_mhs_list->bind_param($types_list_mhs_paginated, ...$params_list_mhs_paginated);
    }
    $stmt_mhs_list->execute();
    $result_mhs_list = $stmt_mhs_list->get_result();
    if ($result_mhs_list) {
        while ($row = $result_mhs_list->fetch_assoc()) {
            $mahasiswa_list[] = $row;
        }
    }
    $stmt_mhs_list->close();
} else {
    $error_message = "Gagal mempersiapkan daftar mahasiswa: " . $conn->error;
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
        function confirmDeleteMahasiswa(userId, mhsName) {
            return confirm("Apakah Anda yakin ingin menghapus mahasiswa '" + mhsName + "' (ID Akun: " + userId + ")? Tindakan ini akan menghapus akun login dan semua data terkait mahasiswa tersebut, termasuk data evaluasi yang mungkin pernah diisi.");
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
                    <li><a href="admin_manage_matakuliah.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_matakuliah.php') !== false || strpos($current_page_php, 'admin_edit_matakuliah.php') !== false) ? 'active' : ''; ?>"><i
                                class="fas fa-book"></i> <span class="menu-text">Mata Kuliah</span></a></li>
                    <li><a href="admin_manage_jadwal.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_jadwal.php') !== false || strpos($current_page_php, 'admin_edit_jadwal.php') !== false) ? 'active' : ''; ?>"><i
                                class="fas fa-calendar-alt"></i> <span class="menu-text">Jadwal</span></a>
                    </li>
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
                    <h2><i class="fas fa-user-graduate"></i> Daftar Mahasiswa Aktif</h2>
                    <a href="admin_edit_mahasiswa.php?action=add" class="btn-login"
                        style="width:auto; padding: 10px 18px;"><i class="fas fa-user-plus"></i> Tambah Mahasiswa</a>
                </div>

                <form action="admin_manage_mahasiswa.php" method="GET" class="filter-form">
                    <div class="input-group">
                        <label for="search_mhs">Cari Mahasiswa</label>
                        <input type="text" name="search_mhs" id="search_mhs" placeholder="Nama, NPM, username..."
                            value="<?php echo htmlspecialchars($search_query_mhs); ?>">
                    </div>
                    <div class="input-group">
                        <label for="kelas_filter">Filter Kelas</label>
                        <input type="text" name="kelas_filter" id="kelas_filter" placeholder="cth: 4MIC"
                            value="<?php echo htmlspecialchars($kelas_filter); ?>">
                    </div>
                    <div class="input-group">
                        <label for="angkatan_filter">Filter Angkatan</label>
                        <input type="text" name="angkatan_filter" id="angkatan_filter" pattern="\d{4}"
                            placeholder="cth: 2023" value="<?php echo htmlspecialchars($angkatan_filter); ?>">
                    </div>
                    <button type="submit" class="btn-login btn-filter-action"><i class="fas fa-filter"></i>
                        Filter</button>
                    <a href="admin_manage_mahasiswa.php" class="btn-filter-action btn-reset-filter"><i
                            class="fas fa-times"></i> Reset</a>
                </form>

                <div style="overflow-x: auto;">
                    <table class="dosen-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No.</th>
                                <th>NPM</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Angkatan</th>
                                <th>Kelas</th>
                                <th>Jabatan</th>
                                <th style="min-width:100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($mahasiswa_list)): ?>
                                <?php $nomor = $offset + 1; ?>
                                <?php foreach ($mahasiswa_list as $mhs): ?>
                                    <tr>
                                        <td><?php echo $nomor++; ?></td>
                                        <td><?php echo htmlspecialchars($mhs['npm']); ?></td>
                                        <td><?php echo htmlspecialchars($mhs['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($mhs['username']); ?></td>
                                        <td><?php echo htmlspecialchars($mhs['email']); ?></td>
                                        <td><?php echo htmlspecialchars($mhs['angkatan']); ?></td>
                                        <td><?php echo htmlspecialchars($mhs['kelas']); ?></td>
                                        <td><?php echo htmlspecialchars($mhs['jabatan_kelas'] ?? '-'); ?></td>
                                        <td class="btn-action-group">
                                            <a href="admin_edit_mahasiswa.php?user_id=<?php echo $mhs['user_id']; ?>"
                                                class="btn-penilaian" style="font-size:0.8em; padding: 4px 8px;" title="Ubah"><i
                                                    class="fas fa-edit"></i></a>
                                            <a href="admin_manage_mahasiswa.php?action=delete&user_id=<?php echo $mhs['user_id']; ?>"
                                                class="btn-penilaian"
                                                style="font-size:0.8em; padding: 4px 8px; background-color: var(--error-color); color: grey;"
                                                title="Hapus"
                                                onclick="return confirmDeleteMahasiswa(<?php echo $mhs['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($mhs['full_name'])); ?>');">
                                                <i class="fas fa-trash btn-delete-icon"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align:center;">
                                        <?php if (!empty($search_query_mhs) || !empty($kelas_filter) || !empty($angkatan_filter)): ?>
                                            Tidak ada mahasiswa aktif yang cocok dengan kriteria pencarian/filter Anda.
                                        <?php else: ?>
                                            Belum ada data mahasiswa aktif.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages_mhs > 1): ?>
                    <ul class="pagination">
                        <?php $query_params_mhs = "&search_mhs=" . urlencode($search_query_mhs) . "&kelas_filter=" . urlencode($kelas_filter) . "&angkatan_filter=" . urlencode($angkatan_filter); ?>
                        <?php if ($page > 1): ?>
                            <li><a href="?page_mhs=<?php echo $page - 1; ?><?php echo $query_params_mhs; ?>">Sebelumnya</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>Sebelumnya</span></li> <?php endif; ?>
                        <?php
                        $start_loop_mhs = max(1, $page - 2);
                        $end_loop_mhs = min($total_pages_mhs, $page + 2);
                        if ($start_loop_mhs > 1) {
                            echo '<li><a href="?page_mhs=1' . $query_params_mhs . '">1</a></li>';
                            if ($start_loop_mhs > 2)
                                echo '<li class="disabled"><span>...</span></li>';
                        } // Gunakan page_mhs
                        for ($i = $start_loop_mhs; $i <= $end_loop_mhs; $i++): ?>
                            <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php if ($i == $page): ?><span><?php echo $i; ?></span>
                                <?php else: ?><a
                                        href="?page_mhs=<?php echo $i; ?><?php echo $query_params_mhs; ?>"><?php echo $i; ?></a><?php endif; // Gunakan page_mhs ?>
                            </li>
                        <?php endfor;
                        if ($end_loop_mhs < $total_pages_mhs) {
                            if ($end_loop_mhs < $total_pages_mhs - 1)
                                echo '<li class="disabled"><span>...</span></li>';
                            echo '<li><a href="?page_mhs=' . $total_pages_mhs . $query_params_mhs . '">' . $total_pages_mhs . '</a></li>';
                        } // Gunakan page_mhs ?>
                        <?php if ($page < $total_pages_mhs): ?>
                            <li><a href="?page_mhs=<?php echo $page + 1; ?><?php echo $query_params_mhs; ?>">Berikutnya</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>Berikutnya</span></li> <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>