<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// evados/admin_edit_jadwal.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php';

$page_title = "Tambah Jadwal Mengajar Baru";
$success_message = '';
$error_message = '';
$jadwal_data = [
    'jadwal_id' => null,
    'dosen_user_id' => '',
    'mk_id' => '',
    'nama_kelas' => '',
    'semester' => '',
    'tahun_ajaran' => ''
];
$is_editing = false;
$jadwal_id_to_edit = null;
$form_action_url = 'admin_edit_jadwal.php?action=add';

// Variabel untuk sidebar dan JS
$current_page_php = "admin_manage_jadwal.php";
$js_initial_sidebar_force_closed = 'false';

// Ambil pesan dari sesi (jika ada)
if (isset($_SESSION['success_message_jadwal_manage'])) {
    $success_message = $_SESSION['success_message_jadwal_manage'];
    unset($_SESSION['success_message_jadwal_manage']);
}
if (isset($_SESSION['error_message_jadwal_manage'])) {
    $error_message = $_SESSION['error_message_jadwal_manage'];
    unset($_SESSION['error_message_jadwal_manage']);
}

// Ambil daftar dosen dan mata kuliah untuk dropdown
$dosen_options = [];
$stmt_dosen = $conn->query("SELECT au.user_id, au.full_name, d.nidn FROM Auth_Users au JOIN Dosen d ON au.user_id = d.user_id WHERE au.role='dosen' AND au.is_active = 1 ORDER BY au.full_name ASC");
if ($stmt_dosen) {
    while ($row = $stmt_dosen->fetch_assoc()) {
        $dosen_options[] = $row;
    }
    $stmt_dosen->close();
}

$mk_options = [];
$stmt_mk = $conn->query("SELECT mk_id, kode_mk, nama_mk FROM mata_kuliah ORDER BY nama_mk ASC");
if ($stmt_mk) {
    while ($row = $stmt_mk->fetch_assoc()) {
        $mk_options[] = $row;
    }
    $stmt_mk->close();
}


if (isset($_GET['jadwal_id']) && is_numeric($_GET['jadwal_id'])) {
    $jadwal_id_to_edit = intval($_GET['jadwal_id']);
    $page_title = "Ubah Data Jadwal Mengajar";
    $is_editing = true;
    $form_action_url = 'admin_edit_jadwal.php?jadwal_id=' . $jadwal_id_to_edit;

    $stmt_fetch = $conn->prepare("SELECT jadwal_id, dosen_user_id, mk_id, nama_kelas, semester, tahun_ajaran FROM jadwal_mengajar WHERE jadwal_id = ?");
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $jadwal_id_to_edit);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows == 1) {
            $db_data = $result_fetch->fetch_assoc();
            $jadwal_data = array_merge($jadwal_data, $db_data);
        } else {
            $_SESSION['error_message_jadwal_manage'] = "Data jadwal tidak ditemukan.";
            header("Location: admin_manage_jadwal.php");
            exit();
        }
        $stmt_fetch->close();
    } else {
        $error_message = "Gagal mengambil data jadwal: " . $conn->error;
        $jadwal_data = null;
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'add') {
    // $page_title sudah "Tambah Jadwal Mengajar Baru"
} else {
    $_SESSION['error_message_jadwal_manage'] = "Aksi tidak valid atau ID Jadwal tidak disediakan.";
    header("Location: admin_manage_jadwal.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_jadwal']) || isset($_POST['update_jadwal']))) {
    $is_new_jadwal_submission = isset($_POST['add_jadwal']);

    $dosen_user_id = $_POST['dosen_user_id'];
    $mk_id = $_POST['mk_id'];
    $nama_kelas = trim($_POST['nama_kelas']);
    $semester = trim($_POST['semester']); // Ganjil, Genap
    $tahun_ajaran = trim($_POST['tahun_ajaran']); // YYYY/YYYY

    // Update $jadwal_data untuk repopulate form jika ada error
    $temp_jadwal_data = $jadwal_data;
    $temp_jadwal_data['dosen_user_id'] = $dosen_user_id;
    $temp_jadwal_data['mk_id'] = $mk_id;
    $temp_jadwal_data['nama_kelas'] = $nama_kelas;
    $temp_jadwal_data['semester'] = $semester;
    $temp_jadwal_data['tahun_ajaran'] = $tahun_ajaran;
    $jadwal_data = $temp_jadwal_data;


    $validation_errors = [];
    if (empty($dosen_user_id))
        $validation_errors[] = "Dosen wajib dipilih.";
    if (empty($mk_id))
        $validation_errors[] = "Mata Kuliah wajib dipilih.";
    if (empty($nama_kelas))
        $validation_errors[] = "Nama Kelas wajib diisi.";
    if (empty($semester) || !in_array($semester, ['Ganjil', 'Genap']))
        $validation_errors[] = "Semester wajib diisi (Ganjil/Genap).";
    if (empty($tahun_ajaran) || !preg_match("/^\d{4}\/\d{4}$/", $tahun_ajaran))
        $validation_errors[] = "Tahun Ajaran wajib diisi dengan format YYYY/YYYY (misal: 2023/2024).";

    if (!empty($validation_errors)) {
        $error_message = implode("<br>", $validation_errors);
    } else {
        // Cek duplikasi (Dosen, MK, Kelas, Semester, Tahun Ajaran yang sama)
        $check_duplicate_sql = "SELECT jadwal_id FROM jadwal_mengajar WHERE dosen_user_id = ? AND mk_id = ? AND nama_kelas = ? AND semester = ? AND tahun_ajaran = ?";
        $params_check_duplicate = [$dosen_user_id, $mk_id, $nama_kelas, $semester, $tahun_ajaran];
        $types_check_duplicate = "iisss";
        if ($is_editing) {
            $check_duplicate_sql .= " AND jadwal_id != ?";
            $params_check_duplicate[] = $jadwal_id_to_edit;
            $types_check_duplicate .= "i";
        }
        $stmt_check_duplicate = $conn->prepare($check_duplicate_sql);
        if ($stmt_check_duplicate) {
            $stmt_check_duplicate->bind_param($types_check_duplicate, ...$params_check_duplicate);
            $stmt_check_duplicate->execute();
            $result_check_duplicate = $stmt_check_duplicate->get_result();
            if ($result_check_duplicate->num_rows > 0) {
                $error_message = "Jadwal yang sama (Dosen, Mata Kuliah, Kelas, Semester, Tahun Ajaran) sudah ada.";
            } else {
                if ($is_editing) {
                    $sql_upsert = "UPDATE jadwal_mengajar SET dosen_user_id = ?, mk_id = ?, nama_kelas = ?, semester = ?, tahun_ajaran = ? WHERE jadwal_id = ?";
                    $params_upsert = [$dosen_user_id, $mk_id, $nama_kelas, $semester, $tahun_ajaran, $jadwal_id_to_edit];
                    $types_upsert = "iisssi";
                } else {
                    $sql_upsert = "INSERT INTO jadwal_mengajar (dosen_user_id, mk_id, nama_kelas, semester, tahun_ajaran) VALUES (?, ?, ?, ?, ?)";
                    $params_upsert = [$dosen_user_id, $mk_id, $nama_kelas, $semester, $tahun_ajaran];
                    $types_upsert = "iisss";
                }

                $stmt_upsert = $conn->prepare($sql_upsert);
                if (!$stmt_upsert) {
                    $error_message = "Gagal mempersiapkan data jadwal: " . $conn->error;
                } else {
                    $stmt_upsert->bind_param($types_upsert, ...$params_upsert);
                    if ($stmt_upsert->execute()) {
                        $new_jadwal_id = $is_editing ? $jadwal_id_to_edit : $stmt_upsert->insert_id;
                        $success_message = "Data jadwal berhasil " . ($is_editing ? "diperbarui." : "ditambahkan (ID Jadwal: $new_jadwal_id).");

                        if ($is_new_jadwal_submission) {
                            $jadwal_data = array_fill_keys(array_keys($jadwal_data), ''); // Reset form
                            $jadwal_data['jadwal_id'] = null;
                        } elseif ($is_editing) {
                            // Re-fetch data setelah update
                            $stmt_refresh_jadwal = $conn->prepare("SELECT jadwal_id, dosen_user_id, mk_id, nama_kelas, semester, tahun_ajaran FROM jadwal_mengajar WHERE jadwal_id = ?");
                            if ($stmt_refresh_jadwal) {
                                $stmt_refresh_jadwal->bind_param("i", $jadwal_id_to_edit);
                                $stmt_refresh_jadwal->execute();
                                $res_refresh = $stmt_refresh_jadwal->get_result();
                                if ($res_refresh->num_rows == 1) {
                                    $default_jadwal_data = ['jadwal_id' => null, 'dosen_user_id' => '', 'mk_id' => '', 'nama_kelas' => '', 'semester' => '', 'tahun_ajaran' => ''];
                                    $jadwal_data = array_merge($default_jadwal_data, $res_refresh->fetch_assoc());
                                }
                                $stmt_refresh_jadwal->close();
                            }
                        }
                    } else {
                        $error_message = "Gagal menyimpan data jadwal: " . $stmt_upsert->error;
                    }
                    $stmt_upsert->close();
                }
            }
            $stmt_check_duplicate->close();
        } else {
            $error_message = "Gagal mempersiapkan pengecekan duplikasi jadwal: " . $conn->error;
        }
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_initial_sidebar_force_closed; ?>;
    </script>
    <style>
        .input-group {
            margin-bottom: 18px;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .input-group select,
        .input-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .form-actions {
            margin-top: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
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
                <div class="error-msg">
                    <?php echo $error_message; // Tidak perlu htmlspecialchars karena bisa mengandung <br> ?>
                </div>
            <?php endif; ?>

            <?php if ($jadwal_data !== null || (isset($_GET['action']) && $_GET['action'] == 'add')): ?>
                <section class="content-card">
                    <h2><i class="fas <?php echo $is_editing ? 'fa-edit' : 'fa-plus-square'; ?>"></i>
                        <?php echo $is_editing ? "Ubah Detail Jadwal" : "Tambah Jadwal Baru"; ?></h2>
                    <form action="<?php echo htmlspecialchars($form_action_url); ?>" method="POST" class="form-container"
                        style="max-width: 600px;">
                        <div class="input-group">
                            <label for="dosen_user_id">Dosen Pengampu <span style="color:red;">*</span></label>
                            <select id="dosen_user_id" name="dosen_user_id" required>
                                <option value="">-- Pilih Dosen --</option>
                                <?php foreach ($dosen_options as $dosen): ?>
                                    <option value="<?php echo htmlspecialchars($dosen['user_id']); ?>" <?php echo (($jadwal_data['dosen_user_id'] ?? '') == $dosen['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dosen['full_name'] . ' (' . $dosen['nidn'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="mk_id">Mata Kuliah <span style="color:red;">*</span></label>
                            <select id="mk_id" name="mk_id" required>
                                <option value="">-- Pilih Mata Kuliah --</option>
                                <?php foreach ($mk_options as $mk): ?>
                                    <option value="<?php echo htmlspecialchars($mk['mk_id']); ?>" <?php echo (($jadwal_data['mk_id'] ?? '') == $mk['mk_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mk['nama_mk'] . ' (' . $mk['kode_mk'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="nama_kelas">Nama Kelas <span style="color:red;">*</span> (Contoh: 4MIA, 2TKA,
                                Reguler Pagi B)</label>
                            <input type="text" id="nama_kelas" name="nama_kelas" required
                                value="<?php echo htmlspecialchars($jadwal_data['nama_kelas'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="semester">Semester <span style="color:red;">*</span></label>
                            <select id="semester" name="semester" required>
                                <option value="">-- Pilih Semester --</option>
                                <option value="Ganjil" <?php echo (($jadwal_data['semester'] ?? '') == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                                <option value="Genap" <?php echo (($jadwal_data['semester'] ?? '') == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="tahun_ajaran">Tahun Ajaran <span style="color:red;">*</span> (Format: YYYY/YYYY,
                                Contoh: 2023/2024)</label>
                            <input type="text" id="tahun_ajaran" name="tahun_ajaran" pattern="\d{4}\/\d{4}" required
                                value="<?php echo htmlspecialchars($jadwal_data['tahun_ajaran'] ?? ''); ?>"
                                placeholder="YYYY/YYYY">
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="<?php echo $is_editing ? 'update_jadwal' : 'add_jadwal'; ?>"
                                class="btn-login" style="width:auto; padding: 10px 20px;">
                                <?php echo $is_editing ? 'Simpan Perubahan' : 'Tambah Jadwal'; ?>
                            </button>
                            <a href="admin_manage_jadwal.php" class="btn-penilaian"
                                style="background-color: #6c757d; color:white; text-decoration:none; padding: 10px 20px;">Batal</a>
                        </div>
                    </form>
                </section>
            <?php elseif ($is_editing && $jadwal_data === null): ?>
                <section class="content-card">
                    <p>Gagal memuat data jadwal atau data tidak ditemukan.</p>
                    <a href="admin_manage_jadwal.php" class="btn-penilaian">Kembali ke Daftar Jadwal</a>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>