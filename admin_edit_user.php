<?php
// evados/admin_edit_user.php
require_once 'includes/auth_check_admin.php';
require_once 'config/db.php'; //

$page_title = "Tambah Pengguna Baru";
$success_message = '';
$error_message = '';
$user_data = [
    'user_id' => null,
    'username' => '',
    'email' => '',
    'full_name' => '',
    'role' => '',
    'is_active' => 1,
    'nidn' => '',
    'department_dosen' => '',
    'npm' => '',
    'angkatan' => '',
    'kelas' => '',
    'jabatan_kelas' => '',
    'department_managed_kajur' => '',
    'start_date_kajur' => null,
    'end_date_kajur' => null
];
$is_editing = false;
$user_id_to_edit = null;
$form_action_url = 'admin_edit_user.php?action=add';
$generated_password_display = null;

$current_page_php = "admin_manage_users.php";
$js_initial_sidebar_force_closed = 'false';

if (isset($_SESSION['success_message_user_manage'])) {
    $success_message = $_SESSION['success_message_user_manage'];
    unset($_SESSION['success_message_user_manage']);
}
if (isset($_SESSION['error_message_user_manage'])) {
    $error_message = $_SESSION['error_message_user_manage'];
    unset($_SESSION['error_message_user_manage']);
}

function generateRandomPassword($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
    $password = '';
    $characterListLength = strlen($characters);
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $characterListLength - 1)];
    }
    return $password;
}

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    // ... (Logika fetch data pengguna untuk mode EDIT, sama seperti sebelumnya) ...
    $user_id_to_edit = intval($_GET['user_id']);
    $page_title = "Ubah Data Pengguna";
    $is_editing = true;
    $form_action_url = 'admin_edit_user.php?user_id=' . $user_id_to_edit;

    $stmt_fetch_auth = $conn->prepare("SELECT user_id, username, email, full_name, role, is_active FROM Auth_Users WHERE user_id = ?");
    if ($stmt_fetch_auth) {
        $stmt_fetch_auth->bind_param("i", $user_id_to_edit);
        $stmt_fetch_auth->execute();
        $result_auth = $stmt_fetch_auth->get_result();
        if ($result_auth->num_rows == 1) {
            $auth_data_from_db = $result_auth->fetch_assoc();
            $user_data = array_merge($user_data, $auth_data_from_db);

            if ($user_data['role'] == 'dosen') {
                $stmt_role_detail = $conn->prepare("SELECT nidn, department as department_dosen FROM Dosen WHERE user_id = ?");
                if ($stmt_role_detail) {
                    $stmt_role_detail->bind_param("i", $user_id_to_edit);
                    $stmt_role_detail->execute();
                    $res_role = $stmt_role_detail->get_result();
                    if ($res_role->num_rows == 1)
                        $user_data = array_merge($user_data, $res_role->fetch_assoc());
                    $stmt_role_detail->close();
                }
            } elseif ($user_data['role'] == 'mahasiswa') {
                $stmt_role_detail = $conn->prepare("SELECT npm, angkatan, kelas, jabatan_kelas FROM Mahasiswa WHERE user_id = ?");
                if ($stmt_role_detail) {
                    $stmt_role_detail->bind_param("i", $user_id_to_edit);
                    $stmt_role_detail->execute();
                    $res_role = $stmt_role_detail->get_result();
                    if ($res_role->num_rows == 1)
                        $user_data = array_merge($user_data, $res_role->fetch_assoc());
                    $stmt_role_detail->close();
                }
            } elseif ($user_data['role'] == 'kajur') {
                $stmt_role_detail = $conn->prepare("SELECT department_managed as department_managed_kajur, start_date as start_date_kajur, end_date as end_date_kajur FROM Kajur WHERE user_id = ?");
                if ($stmt_role_detail) {
                    $stmt_role_detail->bind_param("i", $user_id_to_edit);
                    $stmt_role_detail->execute();
                    $res_role = $stmt_role_detail->get_result();
                    if ($res_role->num_rows == 1)
                        $user_data = array_merge($user_data, $res_role->fetch_assoc());
                    $stmt_role_detail->close();
                }
            }
        } else {
            $error_message = "Data pengguna tidak ditemukan untuk ID: $user_id_to_edit.";
            $user_data = null;
        }
        $stmt_fetch_auth->close();
    } else {
        $error_message = "Gagal mengambil data pengguna: " . $conn->error;
        $user_data = null;
    }

} elseif (isset($_GET['action']) && $_GET['action'] == 'add') {
    $page_title = "Tambah Pengguna Baru";
} else {
    $_SESSION['error_message_user_manage'] = "Aksi tidak valid atau ID Pengguna tidak disediakan untuk diedit.";
    header("Location: admin_manage_users.php");
    exit();
}

// Logika untuk Reset Password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password_user']) && $user_id_to_edit) {
    // ... (Logika reset password seperti sebelumnya) ...
}
// Logika untuk Simpan (Tambah atau Ubah)
elseif ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_user']) || isset($_POST['update_user']))) {
    // ... (Logika Simpan Pengguna seperti di respons sebelumnya, termasuk validasi dan penyimpanan detail peran) ...
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
        function confirmResetPassword() {
            return confirm("Apakah Anda yakin ingin mereset password pengguna ini? Password baru akan digenerate secara acak dan akan ditampilkan sekali di halaman ini setelah berhasil.");
        }
        function toggleRoleSpecificFields() {
            var role = document.getElementById('role').value;
            var dosenFields = document.getElementById('dosen_specific_fields');
            var mahasiswaFields = document.getElementById('mahasiswa_specific_fields');
            var kajurFields = document.getElementById('kajur_specific_fields');

            if (dosenFields) dosenFields.style.display = (role === 'dosen') ? 'block' : 'none';
            if (mahasiswaFields) mahasiswaFields.style.display = (role === 'mahasiswa') ? 'block' : 'none';
            if (kajurFields) kajurFields.style.display = (role === 'kajur') ? 'block' : 'none';
        }
        document.addEventListener('DOMContentLoaded', function () {
            var roleSelect = document.getElementById('role');
            if (roleSelect) {
                toggleRoleSpecificFields();
                roleSelect.addEventListener('change', toggleRoleSpecificFields);
            }

            // Logika untuk toggle password (adaptasi dari js/script.js)
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

            // Panggil untuk field password
            setupPasswordToggle("password", "toggle_password_main");
            // Panggil untuk field konfirmasi password (hanya jika ada, yaitu saat mode tambah)
            <?php if (!$is_editing): ?>
                setupPasswordToggle("confirm_password", "toggle_confirm_password");
            <?php endif; ?>
        });
    </script>
    <style>
        .input-group {
            margin-bottom: 18px;
        }

        .password-display-box {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            margin-top: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
        }

        .password-display-box strong {
            font-size: 1.2em;
            word-break: break-all;
        }

        .password-display-box p {
            margin-top: 5px;
            font-size: 0.9em;
        }

        #dosen_specific_fields,
        #mahasiswa_specific_fields,
        #kajur_specific_fields {
            border-left: 3px solid var(--secondary-color);
            padding-left: 15px;
            margin-left: 0px;
            margin-top: 15px;
            padding-top: 5px;
            margin-bottom: 15px;
        }

        #dosen_specific_fields h4,
        #mahasiswa_specific_fields h4,
        #kajur_specific_fields h4 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.1em;
            color: var(--primary-color);
        }

        .form-actions {
            margin-top: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .form-actions .btn-login,
        .form-actions .btn-penilaian {
            margin-bottom: 0;
        }

        /* CSS untuk toggle password (sama seperti di style.css, atau pastikan style.css dimuat) */
        .input-group .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group .password-wrapper input[type="password"],
        .input-group .password-wrapper input[type="text"] {
            padding-right: 40px;
            /* Ruang untuk ikon */
            width: 100%;
            /* Agar input mengisi wrapper */
            box-sizing: border-box;
            /* Perhitungkan padding dalam width */
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #757575;
            /* Warna ikon awal */
            z-index: 2;
            /* Pastikan di atas input */
        }

        .toggle-password:hover {
            color: var(--primary-color);
            /* Warna ikon saat hover */
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
                            class="<?php echo ($current_page_php == 'admin_manage_users.php') ? 'active' : ''; ?>"><i
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
                <div class="success-msg"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-msg"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if ($generated_password_display): ?>
                <div class="password-display-box">
                    <h4>Password Baru Telah Digenerate:</h4>
                    <p>Harap segera catat dan berikan password ini kepada pengguna. Password hanya ditampilkan sekali.</p>
                    <strong><?php echo htmlspecialchars($generated_password_display); ?></strong>
                </div>
            <?php endif; ?>

            <?php if ($user_data !== null || (isset($_GET['action']) && $_GET['action'] == 'add')): ?>
                <section class="content-card">
                    <h2><i class="fas <?php echo $is_editing ? 'fa-user-edit' : 'fa-user-plus'; ?>"></i>
                        <?php echo $is_editing ? "Ubah Detail Pengguna: " . htmlspecialchars($user_data['full_name'] ?? 'Pengguna') : "Tambah Pengguna Baru"; ?>
                    </h2>
                    <form action="<?php echo htmlspecialchars($form_action_url); ?>" method="POST" class="form-container"
                        style="max-width: 600px;">
                        <div class="input-group">
                            <label for="full_name">Nama Lengkap <span style="color:red;">*</span></label>
                            <input type="text" id="full_name" name="full_name" required
                                value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="email">Email <span style="color:red;">*</span></label>
                            <input type="email" id="email" name="email" required
                                value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="username">Username (Wajib jika peran Mahasiswa atau Admin)</label>
                            <input type="text" id="username" name="username"
                                value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label for="role">Peran (Role) <span style="color:red;">*</span></label>
                            <select id="role" name="role" required>
                                <option value="">-- Pilih Peran --</option>
                                <option value="mahasiswa" <?php echo (($user_data['role'] ?? '') == 'mahasiswa') ? 'selected' : ''; ?>>Mahasiswa</option>
                                <option value="dosen" <?php echo (($user_data['role'] ?? '') == 'dosen') ? 'selected' : ''; ?>>Dosen</option>
                                <option value="kajur" <?php echo (($user_data['role'] ?? '') == 'kajur') ? 'selected' : ''; ?>>Kajur</option>
                                <option value="admin" <?php echo (($user_data['role'] ?? '') == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="is_active">Status Akun <span style="color:red;">*</span></label>
                            <select id="is_active" name="is_active" required>
                                <option value="1" <?php echo (($user_data['is_active'] ?? 1) == 1) ? 'selected' : ''; ?>>Aktif
                                </option>
                                <option value="0" <?php echo (($user_data['is_active'] ?? 1) == 0) ? 'selected' : ''; ?>>Tidak
                                    Aktif</option>
                            </select>
                        </div>

                        <div id="dosen_specific_fields">
                            <h4>Detail Dosen:</h4>
                            <div class="input-group"><label for="nidn">NIDN <span style="color:red;">*</span></label><input
                                    type="text" id="nidn" name="nidn"
                                    value="<?php echo htmlspecialchars($user_data['nidn'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="department_dosen">Departemen/Jurusan <span
                                        style="color:red;">*</span></label><input type="text" id="department_dosen"
                                    name="department_dosen"
                                    value="<?php echo htmlspecialchars($user_data['department_dosen'] ?? ''); ?>"></div>
                        </div>
                        <div id="mahasiswa_specific_fields">
                            <h4>Detail Mahasiswa:</h4>
                            <div class="input-group"><label for="npm">NPM/NIM <span
                                        style="color:red;">*</span></label><input type="text" id="npm" name="npm"
                                    value="<?php echo htmlspecialchars($user_data['npm'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="angkatan">Angkatan <span
                                        style="color:red;">*</span></label><input type="text" id="angkatan" name="angkatan"
                                    pattern="\d{4}" value="<?php echo htmlspecialchars($user_data['angkatan'] ?? ''); ?>">
                            </div>
                            <div class="input-group"><label for="kelas">Kelas <span
                                        style="color:red;">*</span></label><input type="text" id="kelas" name="kelas"
                                    value="<?php echo htmlspecialchars($user_data['kelas'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="jabatan_kelas">Jabatan Kelas</label><input type="text"
                                    id="jabatan_kelas" name="jabatan_kelas"
                                    value="<?php echo htmlspecialchars($user_data['jabatan_kelas'] ?? ''); ?>"></div>
                        </div>
                        <div id="kajur_specific_fields">
                            <h4>Detail Kajur:</h4>
                            <div class="input-group"><label for="department_managed_kajur">Departemen yang Dikelola <span
                                        style="color:red;">*</span></label><input type="text" id="department_managed_kajur"
                                    name="department_managed_kajur"
                                    value="<?php echo htmlspecialchars($user_data['department_managed_kajur'] ?? ''); ?>">
                            </div>
                            <div class="input-group"><label for="start_date_kajur">Tanggal Mulai Jabatan</label><input
                                    type="date" id="start_date_kajur" name="start_date_kajur"
                                    value="<?php echo htmlspecialchars($user_data['start_date_kajur'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="end_date_kajur">Tanggal Akhir Jabatan</label><input
                                    type="date" id="end_date_kajur" name="end_date_kajur"
                                    value="<?php echo htmlspecialchars($user_data['end_date_kajur'] ?? ''); ?>"></div>
                        </div>

                        <hr style="margin: 20px 0;">
                        <p style="font-size:0.9em; color:#555;">
                            <?php if ($is_editing): ?>Kosongkan field password jika tidak ingin mengubah password.
                            <?php else: ?>Password wajib diisi untuk pengguna baru (min. 6 karakter).<?php endif; ?>
                        </p>
                        <div class="input-group">
                            <label for="password">Password <?php echo $is_editing ? 'Baru' : ''; ?></label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password" <?php echo !$is_editing ? 'required' : ''; ?>>
                                <span class="toggle-password" id="toggle_password_main">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        <?php if (!$is_editing): ?>
                            <div class="input-group">
                                <label for="confirm_password">Konfirmasi Password <span style="color:red;">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                    <span class="toggle-password" id="toggle_confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-actions">
                            <button type="submit" name="<?php echo $is_editing ? 'update_user' : 'add_user'; ?>"
                                class="btn-login" style="width:auto; padding: 10px 20px;">
                                <?php echo $is_editing ? 'Simpan Perubahan' : 'Tambah Pengguna'; ?>
                            </button>
                            <a href="admin_manage_users.php" class="btn-penilaian"
                                style="background-color: #6c757d; color:white; text-decoration:none; padding: 10px 20px;">Batal</a>

                            <?php if ($is_editing && $user_id_to_edit != $loggedInAdminId): ?>
                                <button type="submit" name="reset_password_user" class="btn-penilaian"
                                    style="background-color: #ffc107; color:black; padding: 10px 15px;"
                                    onclick="return confirmResetPassword();">
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
            <?php elseif ($is_editing && $user_data === null): ?>
                <section class="content-card">
                    <p>Gagal memuat data pengguna atau data tidak ditemukan.</p>
                    <a href="admin_manage_users.php" class="btn-penilaian">Kembali ke Daftar Pengguna</a>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <script src="js/script.js"></script>
</body>

</html>