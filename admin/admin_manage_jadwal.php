<?php
// evados/admin_manage_jadwal.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php'; //

$page_title = "Manajemen Jadwal Kuliah";
$success_message = '';
$error_message = '';

// Variabel untuk sidebar dan JS - PENTING diinisialisasi di awal
$current_page_php = basename($_SERVER['PHP_SELF']);
$js_initial_sidebar_force_closed = 'false';

// Ambil pesan dari sesi
if (isset($_SESSION['success_message_jadwal_manage'])) {
    $success_message = $_SESSION['success_message_jadwal_manage'];
    unset($_SESSION['success_message_jadwal_manage']);
}
if (isset($_SESSION['error_message_jadwal_manage'])) {
    $error_message = $_SESSION['error_message_jadwal_manage'];
    unset($_SESSION['error_message_jadwal_manage']);
}

// Logika untuk Hapus Jadwal
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['jadwal_id'])) {
    $jadwal_id_to_delete = intval($_GET['jadwal_id']);

    $stmt_delete = $conn->prepare("DELETE FROM jadwal_mengajar WHERE jadwal_id = ?"); //
    if ($stmt_delete) {
        $stmt_delete->bind_param("i", $jadwal_id_to_delete);
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                $success_message = "Jadwal berhasil dihapus.";
            } else {
                $error_message = "Jadwal tidak ditemukan atau sudah dihapus.";
            }
        } else {
            $error_message = "Gagal menghapus jadwal: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    } else {
        $error_message = "Gagal mempersiapkan penghapusan jadwal: " . $conn->error;
    }
}

// Logika Paginasi
$limit = 10;
$page = isset($_GET['page_jadwal']) && is_numeric($_GET['page_jadwal']) ? (int) $_GET['page_jadwal'] : 1; // Parameter unik
$offset = ($page - 1) * $limit;

// Filter dan Pencarian
$jadwal_list = [];
$search_jadwal_dosen = isset($_GET['search_dosen']) ? trim($_GET['search_dosen']) : '';
$search_jadwal_mk = isset($_GET['search_mk']) ? trim($_GET['search_mk']) : '';
$search_jadwal_kelas = isset($_GET['search_kelas']) ? trim($_GET['search_kelas']) : '';
$search_jadwal_semester = isset($_GET['search_semester']) ? trim($_GET['search_semester']) : '';
$search_jadwal_tahun = isset($_GET['search_tahun']) ? trim($_GET['search_tahun']) : '';


$sql_base_jadwal = "FROM jadwal_mengajar jm 
                    JOIN Auth_Users au_dosen ON jm.dosen_user_id = au_dosen.user_id 
                    JOIN Dosen d ON au_dosen.user_id = d.user_id
                    JOIN mata_kuliah mk ON jm.mk_id = mk.mk_id 
                    WHERE 1=1"; //
$sql_conditions_jadwal = "";
$params_list_jadwal = [];
$types_list_jadwal = "";

if (!empty($search_jadwal_dosen)) {
    $sql_conditions_jadwal .= " AND (au_dosen.full_name LIKE ? OR d.nidn LIKE ?)";
    $search_term_dosen = "%" . $search_jadwal_dosen . "%";
    $params_list_jadwal[] = $search_term_dosen;
    $params_list_jadwal[] = $search_term_dosen;
    $types_list_jadwal .= "ss";
}
if (!empty($search_jadwal_mk)) {
    $sql_conditions_jadwal .= " AND (mk.nama_mk LIKE ? OR mk.kode_mk LIKE ?)";
    $search_term_mk = "%" . $search_jadwal_mk . "%";
    $params_list_jadwal[] = $search_term_mk;
    $params_list_jadwal[] = $search_term_mk;
    $types_list_jadwal .= "ss";
}
if (!empty($search_jadwal_kelas)) {
    $sql_conditions_jadwal .= " AND jm.nama_kelas LIKE ?";
    $search_term_kelas = "%" . $search_jadwal_kelas . "%";
    $params_list_jadwal[] = $search_term_kelas;
    $types_list_jadwal .= "s";
}
if (!empty($search_jadwal_semester)) {
    $sql_conditions_jadwal .= " AND jm.semester = ?";
    $params_list_jadwal[] = $search_jadwal_semester;
    $types_list_jadwal .= "s";
}
if (!empty($search_jadwal_tahun)) {
    $sql_conditions_jadwal .= " AND jm.tahun_ajaran = ?";
    $params_list_jadwal[] = $search_jadwal_tahun;
    $types_list_jadwal .= "s";
}


$total_records_jadwal = 0;
$total_pages_jadwal = 1;
$sql_total_count_jadwal = "SELECT COUNT(jm.jadwal_id) as total " . $sql_base_jadwal . $sql_conditions_jadwal;
$stmt_total_count_jadwal = $conn->prepare($sql_total_count_jadwal);
if ($stmt_total_count_jadwal) {
    if (!empty($params_list_jadwal)) {
        $stmt_total_count_jadwal->bind_param($types_list_jadwal, ...$params_list_jadwal);
    }
    $stmt_total_count_jadwal->execute();
    $total_records_result_jadwal = $stmt_total_count_jadwal->get_result();
    if ($total_records_result_jadwal) {
        $total_records_row_jadwal = $total_records_result_jadwal->fetch_assoc();
        if ($total_records_row_jadwal)
            $total_records_jadwal = (int) $total_records_row_jadwal['total'];
    }
    if ($limit > 0 && $total_records_jadwal > 0) {
        $total_pages_jadwal = ceil($total_records_jadwal / $limit);
    }
    $stmt_total_count_jadwal->close();
}

$sql_jadwal_list_select = "SELECT jm.jadwal_id, au_dosen.full_name as nama_dosen, d.nidn, mk.nama_mk, mk.kode_mk, jm.nama_kelas, jm.semester, jm.tahun_ajaran ";
$sql_jadwal_list_query = $sql_jadwal_list_select . $sql_base_jadwal . $sql_conditions_jadwal . " ORDER BY jm.tahun_ajaran DESC, jm.semester DESC, mk.nama_mk ASC, jm.nama_kelas ASC LIMIT ? OFFSET ?";
$params_list_jadwal_paginated = $params_list_jadwal;
$params_list_jadwal_paginated[] = $limit;
$params_list_jadwal_paginated[] = $offset;
$types_list_jadwal_paginated = $types_list_jadwal . "ii";

$stmt_jadwal_list = $conn->prepare($sql_jadwal_list_query);
if ($stmt_jadwal_list) {
    if (!empty($types_list_jadwal_paginated)) {
        $stmt_jadwal_list->bind_param($types_list_jadwal_paginated, ...$params_list_jadwal_paginated);
    }
    $stmt_jadwal_list->execute();
    $result_jadwal_list = $stmt_jadwal_list->get_result();
    if ($result_jadwal_list) {
        while ($row = $result_jadwal_list->fetch_assoc()) {
            $jadwal_list[] = $row;
        }
    }
    $stmt_jadwal_list->close();
} else {
    $error_message = "Gagal mempersiapkan daftar jadwal: " . $conn->error;
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
        function confirmDeleteJadwal(jadwalId, jadwalDesc) {
            return confirm("Apakah Anda yakin ingin menghapus jadwal '" + jadwalDesc + "' (ID: " + jadwalId + ")?");
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
            gap: 10px;
            /* sedikit kurangi gap */
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
            flex: 1 1 130px;
            /* sedikit perkecil flex-basis */
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
            padding: 0 15px;
            /* sedikit kurangi padding horizontal tombol */
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

        /* Warna Ikon Hapus */
        .btn-penilaian.btn-delete .btn-delete-icon,
        .btn-penilaian[style*="var(--error-color)"] .btn-delete-icon,
        .btn-penilaian.error-bg .btn-delete-icon {
            color: grey;
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
                    <h2><i class="fas fa-calendar-alt"></i> Daftar Jadwal Mengajar</h2>
                    <a href="admin_edit_jadwal.php?action=add" class="btn-login"
                        style="width:auto; padding: 10px 18px;"><i class="fas fa-plus"></i> Tambah Jadwal</a>
                </div>

                <form action="admin_manage_jadwal.php" method="GET" class="filter-form">
                    <div class="input-group">
                        <label for="search_dosen">Dosen (Nama/NIDN)</label>
                        <input type="text" name="search_dosen" id="search_dosen"
                            value="<?php echo htmlspecialchars($search_jadwal_dosen); ?>">
                    </div>
                    <div class="input-group">
                        <label for="search_mk">Mata Kuliah (Nama/Kode)</label>
                        <input type="text" name="search_mk" id="search_mk"
                            value="<?php echo htmlspecialchars($search_jadwal_mk); ?>">
                    </div>
                    <div class="input-group">
                        <label for="search_kelas">Kelas</label>
                        <input type="text" name="search_kelas" id="search_kelas"
                            value="<?php echo htmlspecialchars($search_jadwal_kelas); ?>">
                    </div>
                    <div class="input-group">
                        <label for="search_semester">Semester</label>
                        <input type="text" name="search_semester" id="search_semester" placeholder="Ganjil/Genap"
                            value="<?php echo htmlspecialchars($search_jadwal_semester); ?>">
                    </div>
                    <div class="input-group">
                        <label for="search_tahun">Tahun Ajaran</label>
                        <input type="text" name="search_tahun" id="search_tahun" placeholder="YYYY/YYYY"
                            value="<?php echo htmlspecialchars($search_jadwal_tahun); ?>">
                    </div>
                    <button type="submit" class="btn-login btn-filter-action"><i class="fas fa-filter"></i>
                        Filter</button>
                    <a href="admin_manage_jadwal.php" class="btn-filter-action btn-reset-filter"><i
                            class="fas fa-times"></i> Reset</a>
                </form>

                <div style="overflow-x: auto;">
                    <table class="dosen-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No.</th>
                                <th>Dosen (NIDN)</th>
                                <th>Mata Kuliah (Kode)</th>
                                <th>Kelas</th>
                                <th>Semester</th>
                                <th>Thn. Ajaran</th>
                                <th style="min-width:100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($jadwal_list)): ?>
                                <?php $nomor = $offset + 1; ?>
                                <?php foreach ($jadwal_list as $jadwal): ?>
                                    <tr>
                                        <td><?php echo $nomor++; ?></td>
                                        <td><?php echo htmlspecialchars($jadwal['nama_dosen']) . "<br>(<small>" . htmlspecialchars($jadwal['nidn']) . "</small>)"; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($jadwal['nama_mk']) . "<br>(<small>" . htmlspecialchars($jadwal['kode_mk']) . "</small>)"; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($jadwal['nama_kelas']); ?></td>
                                        <td><?php echo htmlspecialchars($jadwal['semester']); ?></td>
                                        <td><?php echo htmlspecialchars($jadwal['tahun_ajaran']); ?></td>
                                        <td class="btn-action-group">
                                            <a href="admin_edit_jadwal.php?jadwal_id=<?php echo $jadwal['jadwal_id']; ?>"
                                                class="btn-penilaian" style="font-size:0.8em; padding: 4px 8px;" title="Ubah"><i
                                                    class="fas fa-edit"></i></a>
                                            <a href="admin_manage_jadwal.php?action=delete&jadwal_id=<?php echo $jadwal['jadwal_id']; ?>"
                                                class="btn-penilaian"
                                                style="font-size:0.8em; padding: 4px 8px; background-color: var(--error-color);"
                                                title="Hapus"
                                                onclick="return confirmDeleteJadwal(<?php echo $jadwal['jadwal_id']; ?>, '<?php echo htmlspecialchars(addslashes($jadwal['nama_mk'] . ' - ' . $jadwal['nama_dosen'] . ' - ' . $jadwal['nama_kelas'])); ?>');">
                                                <i class="fas fa-trash btn-delete-icon"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;">
                                        <?php if (!empty($search_jadwal_dosen) || !empty($search_jadwal_mk) || !empty($search_jadwal_kelas) || !empty($search_jadwal_semester) || !empty($search_jadwal_tahun)): ?>
                                            Tidak ada jadwal yang cocok dengan kriteria pencarian/filter Anda.
                                        <?php else: ?>
                                            Belum ada data jadwal mengajar.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages_jadwal > 1): ?>
                    <ul class="pagination">
                        <?php $query_params_jadwal = "&search_dosen=" . urlencode($search_jadwal_dosen) . "&search_mk=" . urlencode($search_jadwal_mk) . "&search_kelas=" . urlencode($search_jadwal_kelas) . "&search_semester=" . urlencode($search_jadwal_semester) . "&search_tahun=" . urlencode($search_jadwal_tahun); ?>
                        <?php if ($page > 1): ?>
                            <li><a
                                    href="?page_jadwal=<?php echo $page - 1; ?><?php echo $query_params_jadwal; ?>">Sebelumnya</a>
                            </li>
                        <?php else: ?>
                            <li class="disabled"><span>Sebelumnya</span></li> <?php endif; ?>
                        <?php
                        $start_loop_jadwal = max(1, $page - 2);
                        $end_loop_jadwal = min($total_pages_jadwal, $page + 2);
                        if ($start_loop_jadwal > 1) {
                            echo '<li><a href="?page_jadwal=1' . $query_params_jadwal . '">1</a></li>';
                            if ($start_loop_jadwal > 2)
                                echo '<li class="disabled"><span>...</span></li>';
                        }
                        for ($i = $start_loop_jadwal; $i <= $end_loop_jadwal; $i++): ?>
                            <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php if ($i == $page): ?><span><?php echo $i; ?></span>
                                <?php else: ?><a
                                        href="?page_jadwal=<?php echo $i; ?><?php echo $query_params_jadwal; ?>"><?php echo $i; ?></a><?php endif; ?>
                            </li>
                        <?php endfor;
                        if ($end_loop_jadwal < $total_pages_jadwal) {
                            if ($end_loop_jadwal < $total_pages_jadwal - 1)
                                echo '<li class="disabled"><span>...</span></li>';
                            echo '<li><a href="?page_jadwal=' . $total_pages_jadwal . $query_params_jadwal . '">' . $total_pages_jadwal . '</a></li>';
                        } ?>
                        <?php if ($page < $total_pages_jadwal): ?>
                            <li><a
                                    href="?page_jadwal=<?php echo $page + 1; ?><?php echo $query_params_jadwal; ?>">Berikutnya</a>
                            </li>
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