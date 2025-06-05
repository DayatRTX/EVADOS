<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// evados/admin_manage_matakuliah.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php'; //

$page_title = "Manajemen Mata Kuliah";
$success_message = '';
$error_message = '';

// Variabel untuk sidebar dan JS - PENTING diinisialisasi di awal
$current_page_php = basename($_SERVER['PHP_SELF']);
$js_initial_sidebar_force_closed = 'false';

// Ambil pesan dari sesi
if (isset($_SESSION['success_message_mk_manage'])) {
    $success_message = $_SESSION['success_message_mk_manage'];
    unset($_SESSION['success_message_mk_manage']);
}
if (isset($_SESSION['error_message_mk_manage'])) {
    $error_message = $_SESSION['error_message_mk_manage'];
    unset($_SESSION['error_message_mk_manage']);
}

// Logika Hapus Mata Kuliah
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['mk_id'])) {
    $mk_id_to_delete = intval($_GET['mk_id']);

    $stmt_check_jadwal = $conn->prepare("SELECT COUNT(*) as count FROM jadwal_mengajar WHERE mk_id = ?"); //
    if ($stmt_check_jadwal) {
        $stmt_check_jadwal->bind_param("i", $mk_id_to_delete);
        $stmt_check_jadwal->execute();
        $result_check_jadwal = $stmt_check_jadwal->get_result()->fetch_assoc();
        $stmt_check_jadwal->close();

        if ($result_check_jadwal['count'] > 0) {
            $error_message = "Mata kuliah tidak dapat dihapus karena masih digunakan dalam " . $result_check_jadwal['count'] . " jadwal mengajar. Hapus dari jadwal terlebih dahulu.";
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM mata_kuliah WHERE mk_id = ?"); //
            if ($stmt_delete) {
                $stmt_delete->bind_param("i", $mk_id_to_delete);
                if ($stmt_delete->execute()) {
                    if ($stmt_delete->affected_rows > 0) {
                        $success_message = "Mata kuliah berhasil dihapus.";
                    } else {
                        $error_message = "Mata kuliah tidak ditemukan atau sudah dihapus.";
                    }
                } else {
                    $error_message = "Gagal menghapus mata kuliah: " . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $error_message = "Gagal mempersiapkan penghapusan: " . $conn->error;
            }
        }
    } else {
        $error_message = "Gagal memeriksa penggunaan mata kuliah di jadwal: " . $conn->error;
    }
}

// Logika Paginasi
$limit = 10;
$page = isset($_GET['page_mk']) && is_numeric($_GET['page_mk']) ? (int) $_GET['page_mk'] : 1; // Parameter unik untuk paginasi matakuliah
$offset = ($page - 1) * $limit;

// Filter dan Pencarian
$matakuliah_list = [];
$search_query_mk = isset($_GET['search_mk']) ? trim($_GET['search_mk']) : '';

$sql_base_mk = "FROM mata_kuliah WHERE 1=1"; //
$sql_conditions_mk = "";
$params_list_mk = [];
$types_list_mk = "";

if (!empty($search_query_mk)) {
    $sql_conditions_mk .= " AND (kode_mk LIKE ? OR nama_mk LIKE ?)";
    $search_term_mk = "%" . $search_query_mk . "%";
    $params_list_mk[] = $search_term_mk;
    $params_list_mk[] = $search_term_mk;
    $types_list_mk .= "ss";
}

$total_records_mk = 0;
$total_pages_mk = 1;
$sql_total_count_mk = "SELECT COUNT(mk_id) as total " . $sql_base_mk . $sql_conditions_mk;
$stmt_total_count_mk = $conn->prepare($sql_total_count_mk);
if ($stmt_total_count_mk) {
    if (!empty($params_list_mk)) {
        $stmt_total_count_mk->bind_param($types_list_mk, ...$params_list_mk);
    }
    $stmt_total_count_mk->execute();
    $total_records_result_mk = $stmt_total_count_mk->get_result();
    if ($total_records_result_mk) {
        $total_records_row_mk = $total_records_result_mk->fetch_assoc();
        if ($total_records_row_mk)
            $total_records_mk = (int) $total_records_row_mk['total'];
    }
    if ($limit > 0 && $total_records_mk > 0) {
        $total_pages_mk = ceil($total_records_mk / $limit);
    }
    $stmt_total_count_mk->close();
}

$sql_mk_list_query = "SELECT mk_id, kode_mk, nama_mk " . $sql_base_mk . $sql_conditions_mk . " ORDER BY kode_mk ASC LIMIT ? OFFSET ?";
$params_list_mk_paginated = $params_list_mk;
$params_list_mk_paginated[] = $limit;
$params_list_mk_paginated[] = $offset;
$types_list_mk_paginated = $types_list_mk . "ii";

$stmt_mk_list = $conn->prepare($sql_mk_list_query);
if ($stmt_mk_list) {
    if (!empty($types_list_mk_paginated)) {
        $stmt_mk_list->bind_param($types_list_mk_paginated, ...$params_list_mk_paginated);
    }
    $stmt_mk_list->execute();
    $result_mk_list_data = $stmt_mk_list->get_result();
    if ($result_mk_list_data) {
        while ($row = $result_mk_list_data->fetch_assoc()) {
            $matakuliah_list[] = $row;
        }
    }
    $stmt_mk_list->close();
} else {
    $error_message = "Gagal mempersiapkan daftar mata kuliah: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - EVADOS</title>
    <link rel="icon" href="../logo.png" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_initial_sidebar_force_closed; ?>;
        function confirmDeleteMatakuliah(mkId, mkNama) {
            return confirm("Apakah Anda yakin ingin menghapus mata kuliah '" + mkNama + "' (ID: " + mkId + ")? Pastikan mata kuliah ini tidak sedang digunakan dalam jadwal mengajar.");
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
                    <h3 class="logo-text">EVADOS</h3>
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
                    <h2><i class="fas fa-book-open"></i> Daftar Mata Kuliah</h2>
                    <a href="admin_edit_matakuliah.php?action=add" class="btn-login"
                        style="width:auto; padding: 10px 18px;"><i class="fas fa-folder-plus"></i> Tambah Mata Kuliah</a>
                </div>

                <form action="admin_manage_matakuliah.php" method="GET" class="filter-form">
                    <div class="input-group">
                        <label for="search_mk">Cari Mata Kuliah</label>
                        <input type="text" name="search_mk" id="search_mk" placeholder="Kode atau Nama MK..."
                            value="<?php echo htmlspecialchars($search_query_mk); ?>">
                    </div>
                    <button type="submit" class="btn-login btn-filter-action"><i class="fas fa-filter"></i>
                        Filter</button>
                    <a href="admin_manage_matakuliah.php" class="btn-filter-action btn-reset-filter"><i
                            class="fas fa-times"></i> Reset</a>
                </form>

                <div style="overflow-x: auto;">
                    <table class="dosen-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No.</th>
                                <th>Kode MK</th>
                                <th>Nama Mata Kuliah</th>
                                <th style="min-width:100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($matakuliah_list)): ?>
                                <?php $nomor = $offset + 1; ?>
                                <?php foreach ($matakuliah_list as $mk): ?>
                                    <tr>
                                        <td><?php echo $nomor++; ?></td>
                                        <td><?php echo htmlspecialchars($mk['kode_mk']); ?></td>
                                        <td><?php echo htmlspecialchars($mk['nama_mk']); ?></td>
                                        <td class="btn-action-group">
                                            <a href="admin_edit_matakuliah.php?mk_id=<?php echo $mk['mk_id']; ?>"
                                                class="btn-penilaian" style="font-size:0.8em; padding: 4px 8px;" title="Ubah"><i
                                                    class="fas fa-edit"></i></a>
                                            <a href="admin_manage_matakuliah.php?action=delete&mk_id=<?php echo $mk['mk_id']; ?>"
                                                class="btn-penilaian"
                                                style="font-size:0.8em; padding: 4px 8px; background-color: var(--error-color);"
                                                title="Hapus"
                                                onclick="return confirmDeleteMatakuliah(<?php echo $mk['mk_id']; ?>, '<?php echo htmlspecialchars(addslashes($mk['nama_mk'])); ?>');">
                                                <i class="fas fa-trash btn-delete-icon"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;">
                                        <?php if (!empty($search_query_mk)): ?>
                                            Tidak ada mata kuliah yang cocok dengan kriteria pencarian Anda.
                                        <?php else: ?>
                                            Belum ada data mata kuliah.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages_mk > 1): ?>
                    <ul class="pagination">
                        <?php $query_params_mk = "&search_mk=" . urlencode($search_query_mk); ?>
                        <?php if ($page > 1): ?>
                            <li><a href="?page_mk=<?php echo $page - 1; ?><?php echo $query_params_mk; ?>">Sebelumnya</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>Sebelumnya</span></li> <?php endif; ?>
                        <?php
                        $start_loop_mk = max(1, $page - 2);
                        $end_loop_mk = min($total_pages_mk, $page + 2);
                        if ($start_loop_mk > 1) {
                            echo '<li><a href="?page_mk=1' . $query_params_mk . '">1</a></li>';
                            if ($start_loop_mk > 2)
                                echo '<li class="disabled"><span>...</span></li>';
                        }
                        for ($i = $start_loop_mk; $i <= $end_loop_mk; $i++): ?>
                            <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php if ($i == $page): ?><span><?php echo $i; ?></span>
                                <?php else: ?><a
                                        href="?page_mk=<?php echo $i; ?><?php echo $query_params_mk; ?>"><?php echo $i; ?></a><?php endif; ?>
                            </li>
                        <?php endfor;
                        if ($end_loop_mk < $total_pages_mk) {
                            if ($end_loop_mk < $total_pages_mk - 1)
                                echo '<li class="disabled"><span>...</span></li>';
                            echo '<li><a href="?page_mk=' . $total_pages_mk . $query_params_mk . '">' . $total_pages_mk . '</a></li>';
                        } ?>
                        <?php if ($page < $total_pages_mk): ?>
                            <li><a href="?page_mk=<?php echo $page + 1; ?><?php echo $query_params_mk; ?>">Berikutnya</a></li>
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