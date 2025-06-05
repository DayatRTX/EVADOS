<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// evados/admin_edit_dosen.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php'; //

$page_title = "Tambah Dosen Baru";
$success_message = '';
$error_message = '';
$dosen_data = [
    'user_id' => null,
    'full_name' => '',
    'email' => '',
    'nidn' => '',
    'department' => ''
    // Tidak ada 'is_active' di sini karena dikelola di admin_edit_user.php
];
$is_editing = false;
$user_id_to_edit = null;
$form_action_url = 'admin_edit_dosen.php?action=add';

// Variabel untuk sidebar dan JS
$current_page_php = "admin_manage_dosen.php";
$js_initial_sidebar_force_closed = 'false';

if (isset($_SESSION['success_message_dosen_manage'])) {
    $success_message = $_SESSION['success_message_dosen_manage'];
    unset($_SESSION['success_message_dosen_manage']);
}
if (isset($_SESSION['error_message_dosen_manage'])) {
    $error_message = $_SESSION['error_message_dosen_manage'];
    unset($_SESSION['error_message_dosen_manage']);
}

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id_to_edit = intval($_GET['user_id']);
    $page_title = "Ubah Data Dosen";
    $is_editing = true;
    $form_action_url = 'admin_edit_dosen.php?user_id=' . $user_id_to_edit;

    $stmt_fetch = $conn->prepare("SELECT au.user_id, au.full_name, au.email, d.nidn, d.department 
                                  FROM Auth_Users au
                                  JOIN Dosen d ON au.user_id = d.user_id
                                  WHERE au.user_id = ? AND au.role = 'dosen'"); //
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $user_id_to_edit);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows == 1) {
            $db_data = $result_fetch->fetch_assoc();
            $dosen_data = array_merge($dosen_data, $db_data);
        } else {
            $_SESSION['error_message_dosen_manage'] = "Data dosen tidak ditemukan atau bukan merupakan akun dosen.";
            header("Location: admin_manage_dosen.php");
            exit();
        }
        $stmt_fetch->close();
    } else {
        $error_message = "Gagal mengambil data dosen: " . $conn->error;
        $dosen_data = null;
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'add') {
    // $page_title sudah "Tambah Dosen Baru"
} else {
    $_SESSION['error_message_dosen_manage'] = "Aksi tidak valid atau ID Dosen tidak disediakan.";
    header("Location: admin_manage_dosen.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_dosen']) || isset($_POST['update_dosen']))) {
    $is_new_dosen_submission = isset($_POST['add_dosen']);

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $nidn = trim($_POST['nidn']);
    $department = trim($_POST['department']);
    $password_input = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? ''; // Hanya relevan untuk add_dosen

    // Isi kembali $dosen_data untuk form jika ada error validasi
    $temp_dosen_data = $dosen_data; // Mulai dengan data yang ada atau default
    $temp_dosen_data['full_name'] = $full_name;
    $temp_dosen_data['email'] = $email;
    $temp_dosen_data['nidn'] = $nidn;
    $temp_dosen_data['department'] = $department;
    $dosen_data = $temp_dosen_data;


    $validation_errors = [];
    if (empty($full_name))
        $validation_errors[] = "Nama Lengkap wajib diisi.";
    if (empty($email))
        $validation_errors[] = "Email wajib diisi.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $validation_errors[] = "Format email tidak valid.";
    if (empty($nidn))
        $validation_errors[] = "NIDN wajib diisi.";
    if (empty($department))
        $validation_errors[] = "Departemen wajib diisi.";

    if ($is_new_dosen_submission) {
        if (empty($password_input))
            $validation_errors[] = "Password wajib diisi untuk dosen baru.";
        elseif (strlen($password_input) < 6)
            $validation_errors[] = "Password minimal harus 6 karakter.";
        if (empty($confirm_password))
            $validation_errors[] = "Konfirmasi Password wajib diisi.";
        elseif ($password_input !== $confirm_password)
            $validation_errors[] = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (!empty($password_input) && strlen($password_input) < 6) { // Saat edit, jika password diisi
        $validation_errors[] = "Password baru minimal harus 6 karakter.";
    }

    if (!empty($validation_errors)) {
        $error_message = implode("<br>", $validation_errors);
    } else {
        // Cek duplikasi email atau NIDN
        $check_sql = "";
        $params_check = [];
        $types_check = "";
        if ($is_editing) {
            // Saat edit, NIDN dan email harus unik KECUALI untuk user_id saat ini
            $check_sql = "SELECT au.user_id FROM Auth_Users au LEFT JOIN Dosen d ON au.user_id = d.user_id WHERE (au.email = ? OR d.nidn = ?) AND au.user_id != ?";
            $params_check = [$email, $nidn, $user_id_to_edit];
            $types_check = "ssi";
        } else { // Mode Tambah
            $check_sql = "SELECT au.user_id FROM Auth_Users au LEFT JOIN Dosen d ON au.user_id = d.user_id WHERE au.email = ? OR d.nidn = ?";
            $params_check = [$email, $nidn];
            $types_check = "ss";
        }

        $stmt_check = $conn->prepare($check_sql);
        if ($stmt_check) {
            $stmt_check->bind_param($types_check, ...$params_check);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $error_message = "Email atau NIDN sudah digunakan oleh dosen lain.";
            } else {
                $conn->begin_transaction();
                try {
                    $current_user_id_for_op = $is_editing ? $user_id_to_edit : null;
                    $role_dosen = 'dosen';

                    if ($is_editing) { // UPDATE Auth_Users
                        $sql_auth = "UPDATE Auth_Users SET full_name = ?, email = ?"; //
                        // Username tidak diubah di sini untuk dosen, status keaktifan juga tidak
                        $params_auth = [$full_name, $email];
                        $types_auth = "ss";
                        if (!empty($password_input)) {
                            $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
                            $sql_auth .= ", password_hash = ?";
                            $params_auth[] = $password_hash;
                            $types_auth .= "s";
                        }
                        $sql_auth .= " WHERE user_id = ? AND role = ?";
                        $params_auth[] = $current_user_id_for_op;
                        $params_auth[] = $role_dosen;
                        $types_auth .= "is";
                    } else { // INSERT Auth_Users
                        $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
                        $username_dosen = null;
                        $is_active_default = 1;
                        $sql_auth = "INSERT INTO Auth_Users (full_name, email, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, ?)"; //
                        $params_auth = [$full_name, $email, $username_dosen, $password_hash, $role_dosen, $is_active_default];
                        $types_auth = "sssssi";
                    }

                    $stmt_auth = $conn->prepare($sql_auth);
                    if (!$stmt_auth)
                        throw new Exception("Gagal mempersiapkan data Auth_Users: " . $conn->error);
                    $stmt_auth->bind_param($types_auth, ...$params_auth);
                    if (!$stmt_auth->execute())
                        throw new Exception("Gagal menyimpan data Auth_Users: " . $stmt_auth->error);

                    if (!$is_editing) {
                        $current_user_id_for_op = $stmt_auth->insert_id;
                    }
                    $stmt_auth->close();

                    // Insert atau Update tabel Dosen
                    $stmt_dosen_upsert = $conn->prepare("INSERT INTO Dosen (user_id, nidn, department) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nidn = VALUES(nidn), department = VALUES(department)"); //
                    if (!$stmt_dosen_upsert)
                        throw new Exception("Gagal mempersiapkan data Dosen: " . $conn->error);
                    $stmt_dosen_upsert->bind_param("iss", $current_user_id_for_op, $nidn, $department);
                    if (!$stmt_dosen_upsert->execute())
                        throw new Exception("Gagal menyimpan detail Dosen: " . $stmt_dosen_upsert->error);
                    $stmt_dosen_upsert->close();

                    $conn->commit();
                    $success_message = "Data dosen '" . htmlspecialchars($full_name) . "' berhasil " . ($is_editing ? "diperbarui." : "ditambahkan (ID Akun: $current_user_id_for_op).");

                    if ($is_new_dosen_submission) {
                        $dosen_data = array_fill_keys(array_keys($dosen_data), '');
                        $dosen_data['user_id'] = null;
                    } elseif ($is_editing) {
                        // Re-fetch data dosen yang baru diupdate
                        $stmt_refresh_dosen = $conn->prepare("SELECT au.user_id, au.full_name, au.email, d.nidn, d.department FROM Auth_Users au JOIN Dosen d ON au.user_id = d.user_id WHERE au.user_id = ? AND au.role = 'dosen'");
                        if ($stmt_refresh_dosen) {
                            $stmt_refresh_dosen->bind_param("i", $user_id_to_edit);
                            $stmt_refresh_dosen->execute();
                            $res_refresh = $stmt_refresh_dosen->get_result();
                            if ($res_refresh->num_rows == 1) {
                                $default_dosen_data = ['user_id' => null, 'full_name' => '', 'email' => '', 'nidn' => '', 'department' => ''];
                                $dosen_data = array_merge($default_dosen_data, $res_refresh->fetch_assoc());
                            }
                            $stmt_refresh_dosen->close();
                        }
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Terjadi kesalahan: " . $e->getMessage();
                }
            }
            if (isset($stmt_check))
                $stmt_check->close();
        } else {
            $error_message = "Gagal mempersiapkan pengecekan duplikasi: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - EVADOS</title>
    <link rel="icon" href="../logo.png" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_initial_sidebar_force_closed; ?>;

        // Fungsi ini bisa dipindahkan ke js/script.js jika ingin digunakan global
        // dan dipanggil di halaman yang membutuhkan
        function setupPasswordToggle(inputId, toggleButtonId) {
            const passwordInput = document.getElementById(inputId);
            const togglePasswordButton = document.getElementById(toggleButtonId);

            if (passwordInput && togglePasswordButton) {
                const eyeIcon = togglePasswordButton.querySelector("i");

                const showPass = function () {
                    passwordInput.type = "text";
                    if (eyeIcon) eyeIcon.classList.replace("fa-eye", "fa-eye-slash");
                };
                const hidePass = function () {
                    passwordInput.type = "password";
                    if (eyeIcon) eyeIcon.classList.replace("fa-eye-slash", "fa-eye");
                };

                togglePasswordButton.addEventListener("mousedown", showPass);
                togglePasswordButton.addEventListener("touchstart", function (e) { e.preventDefault(); showPass(); });
                togglePasswordButton.addEventListener("mouseup", hidePass);
                togglePasswordButton.addEventListener("mouseleave", function () { if (passwordInput.type === "text") hidePass(); });
                togglePasswordButton.addEventListener("touchend", hidePass);
                togglePasswordButton.addEventListener("touchcancel", hidePass);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Panggil untuk field password utama
            setupPasswordToggle("password_dosen", "toggle_password_dosen");
            // Panggil untuk field konfirmasi password (hanya jika ada, yaitu saat mode tambah)
            <?php if (!$is_editing): ?>
                setupPasswordToggle("confirm_password_dosen", "toggle_confirm_password_dosen");
            <?php endif; ?>
        });
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
                <div class="error-msg"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if ($dosen_data !== null || (isset($_GET['action']) && $_GET['action'] == 'add')): ?>
                <section class="content-card">
                    <h2><i class="fas <?php echo $is_editing ? 'fa-edit' : 'fa-user-plus'; ?>"></i>
                        <?php echo $is_editing ? "Ubah Detail Dosen: " . htmlspecialchars($dosen_data['full_name'] ?? 'Dosen') : "Tambah Dosen Baru"; ?>
                    </h2>
                    <form action="<?php echo htmlspecialchars($form_action_url); ?>" method="POST" class="form-container"
                        style="max-width: 600px;">
                        <div class="input-group">
                            <label for="full_name">Nama Lengkap <span style="color:red;">*</span></label>
                            <input type="text" id="full_name" name="full_name" required
                                value="<?php echo htmlspecialchars($dosen_data['full_name'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="email">Email <span style="color:red;">*</span></label>
                            <input type="email" id="email" name="email" required
                                value="<?php echo htmlspecialchars($dosen_data['email'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="nidn">NIDN <span style="color:red;">*</span></label>
                            <input type="text" id="nidn" name="nidn" required
                                value="<?php echo htmlspecialchars($dosen_data['nidn'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="department">Departemen/Jurusan <span style="color:red;">*</span></label>
                            <input type="text" id="department" name="department" required
                                value="<?php echo htmlspecialchars($dosen_data['department'] ?? ''); ?>">
                        </div>
                        <hr style="margin: 20px 0;">
                        <p style="font-size:0.9em; color:#555;">
                            <?php if ($is_editing): ?>Kosongkan field password jika tidak ingin mengubah password.
                            <?php else: ?>Password wajib diisi untuk dosen baru (min. 6 karakter).<?php endif; ?>
                        </p>
                        <div class="input-group">
                            <label for="password_dosen">Password <?php echo $is_editing ? 'Baru' : ''; ?></label>
                            <div class="password-wrapper">
                                <input type="password" id="password_dosen" name="password" <?php echo !$is_editing ? 'required' : ''; ?>>
                                <span class="toggle-password" id="toggle_password_dosen">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        <?php if (!$is_editing): ?>
                            <div class="input-group">
                                <label for="confirm_password_dosen">Konfirmasi Password <span
                                        style="color:red;">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" id="confirm_password_dosen" name="confirm_password" required>
                                    <span class="toggle-password" id="toggle_confirm_password_dosen">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-actions">
                            <button type="submit" name="<?php echo $is_editing ? 'update_dosen' : 'add_dosen'; ?>"
                                class="btn-login" style="width:auto; padding: 10px 20px;">
                                <?php echo $is_editing ? 'Simpan Perubahan' : 'Tambah Dosen'; ?>
                            </button>
                            <a href="admin_manage_dosen.php" class="btn-penilaian"
                                style="background-color: #6c757d; color:white; text-decoration:none; padding: 10px 20px;">Batal</a>
                        </div>
                    </form>
                </section>
            <?php elseif ($is_editing && $dosen_data === null): ?>
                <section class="content-card">
                    <p>Gagal memuat data dosen atau data tidak ditemukan.</p>
                    <a href="admin_manage_dosen.php" class="btn-penilaian">Kembali ke Daftar Dosen</a>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>