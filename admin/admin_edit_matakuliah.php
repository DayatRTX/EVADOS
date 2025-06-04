<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// evados/admin_edit_matakuliah.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php'; //

$page_title = "Tambah Mata Kuliah Baru";
$success_message = '';
$error_message = '';
$mk_data = [
    'mk_id' => null,
    'kode_mk' => '',
    'nama_mk' => ''
];
$is_editing = false;
$mk_id_to_edit = null;
$form_action_url = 'admin_edit_matakuliah.php?action=add';

// Variabel untuk sidebar dan JS
$current_page_php = "admin_manage_matakuliah.php";
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

if (isset($_GET['mk_id']) && is_numeric($_GET['mk_id'])) {
    $mk_id_to_edit = intval($_GET['mk_id']);
    $page_title = "Ubah Data Mata Kuliah";
    $is_editing = true;
    $form_action_url = 'admin_edit_matakuliah.php?mk_id=' . $mk_id_to_edit;

    $stmt_fetch = $conn->prepare("SELECT mk_id, kode_mk, nama_mk FROM mata_kuliah WHERE mk_id = ?"); //
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $mk_id_to_edit);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows == 1) {
            $db_data = $result_fetch->fetch_assoc();
            $mk_data = array_merge($mk_data, $db_data);
        } else {
            $_SESSION['error_message_mk_manage'] = "Data mata kuliah tidak ditemukan.";
            header("Location: admin_manage_matakuliah.php");
            exit();
        }
        $stmt_fetch->close();
    } else {
        $error_message = "Gagal mengambil data mata kuliah: " . $conn->error;
        $mk_data = null;
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'add') {
    // $page_title sudah "Tambah Mata Kuliah Baru"
} else {
    $_SESSION['error_message_mk_manage'] = "Aksi tidak valid atau ID Mata Kuliah tidak disediakan.";
    header("Location: admin_manage_matakuliah.php");
    exit();
}

// Logika untuk Simpan (Tambah atau Ubah) Mata Kuliah
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_matakuliah']) || isset($_POST['update_matakuliah']))) {
    $is_new_mk_submission = isset($_POST['add_matakuliah']);

    $kode_mk = trim($_POST['kode_mk']);
    $nama_mk = trim($_POST['nama_mk']);

    // Isi kembali $mk_data untuk form jika ada error validasi
    $temp_mk_data = $mk_data;
    $temp_mk_data['kode_mk'] = $kode_mk;
    $temp_mk_data['nama_mk'] = $nama_mk;
    $mk_data = $temp_mk_data;

    if (empty($kode_mk) || empty($nama_mk)) {
        $error_message = "Kode MK dan Nama Mata Kuliah wajib diisi.";
    } else {
        $check_sql = "";
        $params_check = [];
        $types_check = "";
        if ($is_editing) {
            $check_sql = "SELECT mk_id FROM mata_kuliah WHERE kode_mk = ? AND mk_id != ?"; //
            $params_check = [$kode_mk, $mk_id_to_edit];
            $types_check = "si";
        } else {
            $check_sql = "SELECT mk_id FROM mata_kuliah WHERE kode_mk = ?"; //
            $params_check = [$kode_mk];
            $types_check = "s";
        }

        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param($types_check, ...$params_check);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error_message = "Kode MK '" . htmlspecialchars($kode_mk) . "' sudah digunakan oleh mata kuliah lain.";
        } else {
            if ($is_editing) {
                $stmt_save = $conn->prepare("UPDATE mata_kuliah SET kode_mk = ?, nama_mk = ? WHERE mk_id = ?"); //
                $stmt_save->bind_param("ssi", $kode_mk, $nama_mk, $mk_id_to_edit);
            } else {
                $stmt_save = $conn->prepare("INSERT INTO mata_kuliah (kode_mk, nama_mk) VALUES (?, ?)"); //
                $stmt_save->bind_param("ss", $kode_mk, $nama_mk);
            }

            if ($stmt_save) {
                if ($stmt_save->execute()) {
                    $new_mk_id_info = $is_editing ? "" : " (ID MK: " . $stmt_save->insert_id . ")";
                    $success_message = "Data mata kuliah '" . htmlspecialchars($nama_mk) . "' berhasil " . ($is_editing ? "diperbarui." : "ditambahkan." . $new_mk_id_info);
                    if ($is_new_mk_submission) {
                        $mk_data = array_fill_keys(array_keys($mk_data), '');
                        $mk_data['mk_id'] = null;
                    } elseif ($is_editing) {
                        $stmt_refresh_mk = $conn->prepare("SELECT mk_id, kode_mk, nama_mk FROM mata_kuliah WHERE mk_id = ?"); //
                        if ($stmt_refresh_mk) {
                            $stmt_refresh_mk->bind_param("i", $mk_id_to_edit);
                            $stmt_refresh_mk->execute();
                            $res_refresh = $stmt_refresh_mk->get_result();
                            if ($res_refresh->num_rows == 1)
                                $mk_data = array_merge($mk_data, $res_refresh->fetch_assoc());
                            $stmt_refresh_mk->close();
                        }
                    }
                } else {
                    $error_message = "Gagal menyimpan data mata kuliah: " . $stmt_save->error;
                }
                $stmt_save->close();
            } else {
                $error_message = "Gagal mempersiapkan penyimpanan data mata kuliah: " . $conn->error;
            }
        }
        if (isset($stmt_check))
            $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Evados</title>
    <link rel="icon" href="../logo.png" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

            <?php if ($mk_data !== null || (isset($_GET['action']) && $_GET['action'] == 'add')): ?>
                <section class="content-card">
                    <h2><i class="fas <?php echo $is_editing ? 'fa-edit' : 'fa-folder-plus'; ?>"></i>
                        <?php echo $is_editing ? "Ubah Detail Mata Kuliah: " . htmlspecialchars($mk_data['nama_mk'] ?? 'Mata Kuliah') : "Tambah Mata Kuliah Baru"; ?>
                    </h2>
                    <form action="<?php echo htmlspecialchars($form_action_url); ?>" method="POST" class="form-container"
                        style="max-width: 600px;">
                        <div class="input-group">
                            <label for="kode_mk">Kode MK <span style="color:red;">*</span></label>
                            <input type="text" id="kode_mk" name="kode_mk" required
                                value="<?php echo htmlspecialchars($mk_data['kode_mk'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="nama_mk">Nama Mata Kuliah <span style="color:red;">*</span></label>
                            <input type="text" id="nama_mk" name="nama_mk" required
                                value="<?php echo htmlspecialchars($mk_data['nama_mk'] ?? ''); ?>">
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="<?php echo $is_editing ? 'update_matakuliah' : 'add_matakuliah'; ?>"
                                class="btn-login" style="width:auto; padding: 10px 20px;">
                                <?php echo $is_editing ? 'Simpan Perubahan' : 'Tambah Mata Kuliah'; ?>
                            </button>
                            <a href="admin_manage_matakuliah.php" class="btn-penilaian"
                                style="background-color: #6c757d; color:white; text-decoration:none; padding: 10px 20px;">Batal</a>
                        </div>
                    </form>
                </section>
            <?php elseif ($is_editing && $mk_data === null): ?>
                <section class="content-card">
                    <p>Gagal memuat data mata kuliah atau data tidak ditemukan.</p>
                    <a href="admin_manage_matakuliah.php" class="btn-penilaian">Kembali ke Daftar Mata Kuliah</a>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>