<?php
// evados/admin_edit_mahasiswa.php
require_once 'includes/auth_check_admin.php';
require_once 'config/db.php'; //

$page_title = "Tambah Mahasiswa Baru";
$success_message = '';
$error_message = '';
$mahasiswa_data = [
    'user_id' => null,
    'full_name' => '',
    'email' => '',
    'username' => '',
    'npm' => '',
    'angkatan' => '',
    'kelas' => '',
    'jabatan_kelas' => ''
    // Tidak ada 'is_active' di sini, dikelola di admin_edit_user.php
];
$is_editing = false;
$user_id_to_edit = null;
$form_action_url = 'admin_edit_mahasiswa.php?action=add';

// Variabel untuk sidebar dan JS
$current_page_php = "admin_manage_mahasiswa.php";
$js_initial_sidebar_force_closed = 'false';

if (isset($_SESSION['success_message_mhs_manage'])) {
    $success_message = $_SESSION['success_message_mhs_manage'];
    unset($_SESSION['success_message_mhs_manage']);
}
if (isset($_SESSION['error_message_mhs_manage'])) {
    $error_message = $_SESSION['error_message_mhs_manage'];
    unset($_SESSION['error_message_mhs_manage']);
}

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id_to_edit = intval($_GET['user_id']);
    $page_title = "Ubah Data Mahasiswa";
    $is_editing = true;
    $form_action_url = 'admin_edit_mahasiswa.php?user_id=' . $user_id_to_edit;

    $stmt_fetch = $conn->prepare("SELECT au.user_id, au.full_name, au.email, au.username, m.npm, m.angkatan, m.kelas, m.jabatan_kelas 
                                  FROM Auth_Users au
                                  JOIN Mahasiswa m ON au.user_id = m.user_id
                                  WHERE au.user_id = ? AND au.role = 'mahasiswa'"); //
    if ($stmt_fetch) {
        $stmt_fetch->bind_param("i", $user_id_to_edit);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        if ($result_fetch->num_rows == 1) {
            $db_data = $result_fetch->fetch_assoc();
            $mahasiswa_data = array_merge($mahasiswa_data, $db_data);
        } else {
            $_SESSION['error_message_mhs_manage'] = "Data mahasiswa tidak ditemukan atau bukan mahasiswa.";
            header("Location: admin_manage_mahasiswa.php");
            exit();
        }
        $stmt_fetch->close();
    } else {
        $error_message = "Gagal mengambil data mahasiswa: " . $conn->error;
        $mahasiswa_data = null;
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'add') {
    // $page_title sudah "Tambah Mahasiswa Baru"
} else {
    $_SESSION['error_message_mhs_manage'] = "Aksi tidak valid atau ID Mahasiswa tidak disediakan.";
    header("Location: admin_manage_mahasiswa.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_mahasiswa']) || isset($_POST['update_mahasiswa']))) {
    $is_new_mhs_submission = isset($_POST['add_mahasiswa']);

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $npm = trim($_POST['npm']);
    $angkatan = trim($_POST['angkatan']);
    $kelas = trim($_POST['kelas']);
    $jabatan_kelas = isset($_POST['jabatan_kelas']) ? trim($_POST['jabatan_kelas']) : null;
    $password_input = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $temp_mhs_data = $mahasiswa_data;
    $temp_mhs_data['full_name'] = $full_name;
    $temp_mhs_data['email'] = $email;
    $temp_mhs_data['username'] = $username;
    $temp_mhs_data['npm'] = $npm;
    $temp_mhs_data['angkatan'] = $angkatan;
    $temp_mhs_data['kelas'] = $kelas;
    $temp_mhs_data['jabatan_kelas'] = $jabatan_kelas;
    $mahasiswa_data = $temp_mhs_data;

    $validation_errors = [];
    if (empty($full_name))
        $validation_errors[] = "Nama Lengkap wajib diisi.";
    if (empty($email))
        $validation_errors[] = "Email wajib diisi.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $validation_errors[] = "Format email tidak valid.";
    if (empty($username))
        $validation_errors[] = "Username wajib diisi.";
    if (empty($npm))
        $validation_errors[] = "NPM wajib diisi.";
    if (empty($angkatan) || !preg_match("/^\d{4}$/", $angkatan))
        $validation_errors[] = "Angkatan wajib diisi dengan 4 digit tahun.";
    if (empty($kelas))
        $validation_errors[] = "Kelas wajib diisi.";

    if ($is_new_mhs_submission) {
        if (empty($password_input))
            $validation_errors[] = "Password wajib diisi untuk mahasiswa baru.";
        elseif (strlen($password_input) < 6)
            $validation_errors[] = "Password minimal harus 6 karakter.";
        if (empty($confirm_password))
            $validation_errors[] = "Konfirmasi Password wajib diisi.";
        elseif ($password_input !== $confirm_password)
            $validation_errors[] = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (!empty($password_input) && strlen($password_input) < 6) {
        $validation_errors[] = "Password baru minimal harus 6 karakter.";
    }

    if (!empty($validation_errors)) {
        $error_message = implode("<br>", $validation_errors);
    } else {
        $check_sql = "";
        $params_check = [];
        $types_check = "";
        if ($is_editing) {
            $check_sql = "SELECT au.user_id FROM Auth_Users au LEFT JOIN Mahasiswa m ON au.user_id = m.user_id WHERE (au.email = ? OR au.username = ? OR m.npm = ?) AND au.user_id != ?";
            $params_check = [$email, $username, $npm, $user_id_to_edit];
            $types_check = "sssi";
        } else {
            $check_sql = "SELECT au.user_id FROM Auth_Users au LEFT JOIN Mahasiswa m ON au.user_id = m.user_id WHERE au.email = ? OR au.username = ? OR m.npm = ?";
            $params_check = [$email, $username, $npm];
            $types_check = "sss";
        }

        $stmt_check = $conn->prepare($check_sql);
        if ($stmt_check) {
            $stmt_check->bind_param($types_check, ...$params_check);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $error_message = "Email, Username, atau NPM sudah digunakan oleh mahasiswa lain.";
            } else {
                $conn->begin_transaction();
                try {
                    $current_user_id_for_op = $is_editing ? $user_id_to_edit : null;
                    $role_mahasiswa = 'mahasiswa';

                    if ($is_editing) {
                        $sql_auth = "UPDATE Auth_Users SET full_name = ?, email = ?, username = ?";
                        $params_auth = [$full_name, $email, $username];
                        $types_auth = "sss";
                        if (!empty($password_input)) {
                            $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
                            $sql_auth .= ", password_hash = ?";
                            $params_auth[] = $password_hash;
                            $types_auth .= "s";
                        }
                        $sql_auth .= " WHERE user_id = ? AND role = ?";
                        $params_auth[] = $current_user_id_for_op;
                        $params_auth[] = $role_mahasiswa;
                        $types_auth .= "is";
                    } else {
                        $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
                        $is_active_default = 1;
                        $sql_auth = "INSERT INTO Auth_Users (full_name, email, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, ?)";
                        $params_auth = [$full_name, $email, $username, $password_hash, $role_mahasiswa, $is_active_default];
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

                    $stmt_mhs_upsert = $conn->prepare("INSERT INTO Mahasiswa (user_id, npm, angkatan, kelas, jabatan_kelas) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE npm = VALUES(npm), angkatan = VALUES(angkatan), kelas = VALUES(kelas), jabatan_kelas = VALUES(jabatan_kelas)");
                    if (!$stmt_mhs_upsert)
                        throw new Exception("Gagal mempersiapkan data Mahasiswa: " . $conn->error);
                    $stmt_mhs_upsert->bind_param("issss", $current_user_id_for_op, $npm, $angkatan, $kelas, $jabatan_kelas);
                    if (!$stmt_mhs_upsert->execute())
                        throw new Exception("Gagal menyimpan detail Mahasiswa: " . $stmt_mhs_upsert->error);
                    $stmt_mhs_upsert->close();

                    $conn->commit();
                    $success_message = "Data mahasiswa '" . htmlspecialchars($full_name) . "' berhasil " . ($is_editing ? "diperbarui." : "ditambahkan (ID Akun: $current_user_id_for_op).");

                    if ($is_new_mhs_submission) {
                        $mahasiswa_data = array_fill_keys(array_keys($mahasiswa_data), '');
                        $mahasiswa_data['user_id'] = null;
                    } elseif ($is_editing) {
                        $stmt_refresh_mhs = $conn->prepare("SELECT au.user_id, au.full_name, au.email, au.username, m.npm, m.angkatan, m.kelas, m.jabatan_kelas FROM Auth_Users au JOIN Mahasiswa m ON au.user_id = m.user_id WHERE au.user_id = ? AND au.role = 'mahasiswa'");
                        if ($stmt_refresh_mhs) {
                            $stmt_refresh_mhs->bind_param("i", $user_id_to_edit);
                            $stmt_refresh_mhs->execute();
                            $res_refresh = $stmt_refresh_mhs->get_result();
                            if ($res_refresh->num_rows == 1) {
                                $default_mhs_data = ['user_id' => null, 'full_name' => '', 'email' => '', 'username' => '', 'npm' => '', 'angkatan' => '', 'kelas' => '', 'jabatan_kelas' => ''];
                                $mahasiswa_data = array_merge($default_mhs_data, $res_refresh->fetch_assoc());
                            }
                            $stmt_refresh_mhs->close();
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
    <title><?php echo htmlspecialchars($page_title); ?> - Evados</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_initial_sidebar_force_closed; ?>;

        // Fungsi ini bisa dipindahkan ke js/script.js jika ingin digunakan global
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
            setupPasswordToggle("password_mhs", "toggle_password_mhs");
            // Panggil untuk field konfirmasi password (hanya jika ada, yaitu saat mode tambah)
            <?php if (!$is_editing): ?>
                setupPasswordToggle("confirm_password_mhs", "toggle_confirm_password_mhs");
            <?php endif; ?>
        });
    </script>
    <style>
        .input-group {
            margin-bottom: 18px;
        }

        .form-actions {
            margin-top: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* CSS untuk toggle password (sama seperti di style.css atau admin_edit_user.php) */
        .input-group .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group .password-wrapper input[type="password"],
        .input-group .password-wrapper input[type="text"] {
            padding-right: 40px;
            width: 100%;
            box-sizing: border-box;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #757575;
            z-index: 2;
        }

        .toggle-password:hover {
            color: var(--primary-color);
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
                <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span
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

            <?php if ($mahasiswa_data !== null || (isset($_GET['action']) && $_GET['action'] == 'add')): ?>
                <section class="content-card">
                    <h2><i class="fas <?php echo $is_editing ? 'fa-edit' : 'fa-user-plus'; ?>"></i>
                        <?php echo $is_editing ? "Ubah Detail Mahasiswa: " . htmlspecialchars($mahasiswa_data['full_name'] ?? 'Mahasiswa') : "Tambah Mahasiswa Baru"; ?>
                    </h2>
                    <form action="<?php echo htmlspecialchars($form_action_url); ?>" method="POST" class="form-container"
                        style="max-width: 600px;">
                        <div class="input-group">
                            <label for="full_name">Nama Lengkap <span style="color:red;">*</span></label>
                            <input type="text" id="full_name" name="full_name" required
                                value="<?php echo htmlspecialchars($mahasiswa_data['full_name'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="email">Email <span style="color:red;">*</span></label>
                            <input type="email" id="email" name="email" required
                                value="<?php echo htmlspecialchars($mahasiswa_data['email'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="username">Username <span style="color:red;">*</span></label>
                            <input type="text" id="username" name="username" required
                                value="<?php echo htmlspecialchars($mahasiswa_data['username'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="npm">NPM/NIM <span style="color:red;">*</span></label>
                            <input type="text" id="npm" name="npm" required
                                value="<?php echo htmlspecialchars($mahasiswa_data['npm'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="angkatan">Angkatan <span style="color:red;">*</span> (cth: 2023)</label>
                            <input type="text" id="angkatan" name="angkatan" pattern="\d{4}" required
                                value="<?php echo htmlspecialchars($mahasiswa_data['angkatan'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="kelas">Kelas <span style="color:red;">*</span> (cth: 4MIC)</label>
                            <input type="text" id="kelas" name="kelas" required
                                value="<?php echo htmlspecialchars($mahasiswa_data['kelas'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="jabatan_kelas">Jabatan Kelas (Opsional)</label>
                            <input type="text" id="jabatan_kelas" name="jabatan_kelas"
                                value="<?php echo htmlspecialchars($mahasiswa_data['jabatan_kelas'] ?? ''); ?>">
                        </div>
                        <hr style="margin: 20px 0;">
                        <p style="font-size:0.9em; color:#555;">
                            <?php if ($is_editing): ?>Kosongkan field password jika tidak ingin mengubah password.
                            <?php else: ?>Password wajib diisi untuk mahasiswa baru (min. 6 karakter).<?php endif; ?>
                        </p>
                        <div class="input-group">
                            <label for="password_mhs">Password <?php echo $is_editing ? 'Baru' : ''; ?></label>
                            <div class="password-wrapper">
                                <input type="password" id="password_mhs" name="password" <?php echo !$is_editing ? 'required' : ''; ?>>
                                <span class="toggle-password" id="toggle_password_mhs">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        <?php if (!$is_editing): ?>
                            <div class="input-group">
                                <label for="confirm_password_mhs">Konfirmasi Password <span style="color:red;">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" id="confirm_password_mhs" name="confirm_password" required>
                                    <span class="toggle-password" id="toggle_confirm_password_mhs">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-actions">
                            <button type="submit" name="<?php echo $is_editing ? 'update_mahasiswa' : 'add_mahasiswa'; ?>"
                                class="btn-login" style="width:auto; padding: 10px 20px;">
                                <?php echo $is_editing ? 'Simpan Perubahan' : 'Tambah Mahasiswa'; ?>
                            </button>
                            <a href="admin_manage_mahasiswa.php" class="btn-penilaian"
                                style="background-color: #6c757d; color:white; text-decoration:none; padding: 10px 20px;">Batal</a>
                        </div>
                    </form>
                </section>
            <?php elseif ($is_editing && $mahasiswa_data === null): ?>
                <section class="content-card">
                    <p>Gagal memuat data mahasiswa atau data tidak ditemukan.</p>
                    <a href="admin_manage_mahasiswa.php" class="btn-penilaian">Kembali ke Daftar Mahasiswa</a>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <script src="js/script.js"></script>
</body>

</html>