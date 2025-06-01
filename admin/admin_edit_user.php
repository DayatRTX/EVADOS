<?php
// evados/admin_edit_user.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php'; //

$page_title = "Tambah Pengguna Baru";
$success_message = '';
$error_message = '';
$user_data = [
    'user_id' => null,
    'username' => '',
    'email' => '',
    'full_name' => '',
    'role' => '', // Akan di-set dari DB saat edit
    'is_active' => 1,
    // Field spesifik peran (akan diisi jika relevan)
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

// Variabel untuk sidebar
$current_page_php = "admin_manage_users.php"; // Untuk menandai menu sidebar yang aktif
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

// Fungsi untuk generate password acak
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
            $user_data = array_merge($user_data, $auth_data_from_db); // Menggabungkan dengan data default

            // Ambil detail berdasarkan peran
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
            $_SESSION['error_message_user_manage'] = "Data pengguna tidak ditemukan untuk ID: $user_id_to_edit.";
            header("Location: admin_manage_users.php");
            exit();
        }
        $stmt_fetch_auth->close();
    } else {
        $error_message = "Gagal mengambil data pengguna: " . $conn->error;
        $user_data = null; // Set agar form tidak ditampilkan jika gagal fetch
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'add') {
    // $page_title sudah "Tambah Pengguna Baru"
    // $user_data sudah diinisialisasi dengan nilai default
} else {
    // Jika tidak ada user_id untuk edit atau action=add, redirect
    $_SESSION['error_message_user_manage'] = "Aksi tidak valid atau ID Pengguna tidak disediakan.";
    header("Location: admin_manage_users.php");
    exit();
}


// Logika untuk Reset Password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password_user']) && $user_id_to_edit) {
    if ($user_id_to_edit == $loggedInAdminId) {
        $error_message = "Anda tidak dapat mereset password akun Anda sendiri melalui form ini.";
    } else {
        $new_password_plain = generateRandomPassword(12);
        $new_password_hash = password_hash($new_password_plain, PASSWORD_DEFAULT);

        $stmt_reset = $conn->prepare("UPDATE Auth_Users SET password_hash = ? WHERE user_id = ?");
        if ($stmt_reset) {
            $stmt_reset->bind_param("si", $new_password_hash, $user_id_to_edit);
            if ($stmt_reset->execute()) {
                $success_message = "Password untuk pengguna '" . htmlspecialchars($user_data['full_name']) . "' berhasil direset.";
                $generated_password_display = $new_password_plain; // Untuk ditampilkan sekali
            } else {
                $error_message = "Gagal mereset password: " . $stmt_reset->error;
            }
            $stmt_reset->close();
        } else {
            $error_message = "Gagal mempersiapkan reset password: " . $conn->error;
        }
    }
}
// Logika untuk Simpan (Tambah atau Ubah)
elseif ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_user']) || isset($_POST['update_user']))) {
    $is_new_user_submission = isset($_POST['add_user']);

    // Ambil data dari form
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = isset($_POST['username']) ? trim($_POST['username']) : null;
    $password_input = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $is_active = (int) ($_POST['is_active'] ?? 1);

    // Jika mode edit, peran diambil dari data yang sudah ada (tidak bisa diubah)
    // Jika mode tambah, peran diambil dari form
    $role = $is_editing ? $user_data['role'] : ($_POST['role'] ?? '');


    // Isi kembali $user_data untuk form jika ada error validasi
    // atau untuk menampilkan data yang baru saja disubmit/diubah
    $temp_user_data = $user_data; // Mulai dengan data yang ada atau default
    $temp_user_data['full_name'] = $full_name;
    $temp_user_data['email'] = $email;
    $temp_user_data['username'] = $username;
    $temp_user_data['role'] = $role; // Peran sudah ditentukan
    $temp_user_data['is_active'] = $is_active;
    // Ambil data spesifik peran dari $_POST
    if ($role == 'dosen') {
        $temp_user_data['nidn'] = trim($_POST['nidn'] ?? '');
        $temp_user_data['department_dosen'] = trim($_POST['department_dosen'] ?? '');
    } elseif ($role == 'mahasiswa') {
        $temp_user_data['npm'] = trim($_POST['npm'] ?? '');
        $temp_user_data['angkatan'] = trim($_POST['angkatan'] ?? '');
        $temp_user_data['kelas'] = trim($_POST['kelas'] ?? '');
        $temp_user_data['jabatan_kelas'] = trim($_POST['jabatan_kelas'] ?? '');
    } elseif ($role == 'kajur') {
        $temp_user_data['department_managed_kajur'] = trim($_POST['department_managed_kajur'] ?? '');
        $temp_user_data['start_date_kajur'] = !empty($_POST['start_date_kajur']) ? trim($_POST['start_date_kajur']) : null;
        $temp_user_data['end_date_kajur'] = !empty($_POST['end_date_kajur']) ? trim($_POST['end_date_kajur']) : null;
    }
    $user_data = $temp_user_data;


    // Validasi dasar
    $validation_errors = [];
    if (empty($full_name))
        $validation_errors[] = "Nama Lengkap wajib diisi.";
    if (empty($email))
        $validation_errors[] = "Email wajib diisi.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $validation_errors[] = "Format email tidak valid.";
    if (empty($role))
        $validation_errors[] = "Peran wajib dipilih.";

    // Validasi username (wajib untuk mahasiswa dan admin)
    if (in_array($role, ['mahasiswa', 'admin']) && empty($username)) {
        $validation_errors[] = "Username wajib diisi untuk peran Mahasiswa atau Admin.";
    }
    // Username tidak boleh null untuk peran lain jika diisi (opsional untuk dosen & kajur)
    if (!in_array($role, ['mahasiswa', 'admin']) && $username === '') { // Jika diisi string kosong, set ke null
        $username = null;
    }


    // Validasi password
    if ($is_new_user_submission) { // Wajib untuk user baru
        if (empty($password_input))
            $validation_errors[] = "Password wajib diisi untuk pengguna baru.";
        elseif (strlen($password_input) < 6)
            $validation_errors[] = "Password minimal harus 6 karakter.";
        if (empty($confirm_password))
            $validation_errors[] = "Konfirmasi Password wajib diisi.";
        elseif ($password_input !== $confirm_password)
            $validation_errors[] = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (!empty($password_input) && strlen($password_input) < 6) { // Opsional saat edit, tapi jika diisi, harus valid
        $validation_errors[] = "Password baru minimal harus 6 karakter.";
    }

    // Validasi field spesifik peran
    if ($role == 'dosen') {
        if (empty($user_data['nidn']))
            $validation_errors[] = "NIDN wajib diisi untuk Dosen.";
        if (empty($user_data['department_dosen']))
            $validation_errors[] = "Departemen wajib diisi untuk Dosen.";
    } elseif ($role == 'mahasiswa') {
        if (empty($user_data['npm']))
            $validation_errors[] = "NPM wajib diisi untuk Mahasiswa.";
        if (empty($user_data['angkatan']) || !preg_match("/^\d{4}$/", $user_data['angkatan']))
            $validation_errors[] = "Angkatan wajib diisi dengan 4 digit tahun untuk Mahasiswa.";
        if (empty($user_data['kelas']))
            $validation_errors[] = "Kelas wajib diisi untuk Mahasiswa.";
    } elseif ($role == 'kajur') {
        if (empty($user_data['department_managed_kajur']))
            $validation_errors[] = "Departemen yang Dikelola wajib diisi untuk Kajur.";
    }


    if (!empty($validation_errors)) {
        $error_message = implode("<br>", $validation_errors);
    } else {
        // Cek duplikasi email atau username (kecuali untuk diri sendiri saat edit)
        $check_sql = "SELECT user_id FROM Auth_Users WHERE (email = ? OR (username IS NOT NULL AND username = ?))";
        $params_check = [$email, $username];
        $types_check = "ss";
        if ($is_editing) {
            $check_sql .= " AND user_id != ?";
            $params_check[] = $user_id_to_edit;
            $types_check .= "i";
        }
        $stmt_check_unique = $conn->prepare($check_sql);
        $stmt_check_unique->bind_param($types_check, ...$params_check);
        $stmt_check_unique->execute();
        $result_check_unique = $stmt_check_unique->get_result();

        $is_duplicate = false;
        if ($result_check_unique->num_rows > 0) {
            $existing_user = $result_check_unique->fetch_assoc();
            if ($existing_user['email'] == $email) {
                $error_message = "Email '" . htmlspecialchars($email) . "' sudah digunakan oleh pengguna lain.";
                $is_duplicate = true;
            } elseif ($username !== null && $existing_user['username'] == $username) {
                $error_message = "Username '" . htmlspecialchars($username) . "' sudah digunakan oleh pengguna lain.";
                $is_duplicate = true;
            }
        }
        $stmt_check_unique->close();

        // Cek duplikasi NIDN untuk Dosen
        if (!$is_duplicate && $role == 'dosen') {
            $check_nidn_sql = "SELECT user_id FROM Dosen WHERE nidn = ?";
            $params_nidn_check = [$user_data['nidn']];
            $types_nidn_check = "s";
            if ($is_editing) {
                $check_nidn_sql .= " AND user_id != ?";
                $params_nidn_check[] = $user_id_to_edit;
                $types_nidn_check .= "i";
            }
            $stmt_check_nidn = $conn->prepare($check_nidn_sql);
            $stmt_check_nidn->bind_param($types_nidn_check, ...$params_nidn_check);
            $stmt_check_nidn->execute();
            if ($stmt_check_nidn->get_result()->num_rows > 0) {
                $error_message = "NIDN '" . htmlspecialchars($user_data['nidn']) . "' sudah digunakan oleh dosen lain.";
                $is_duplicate = true;
            }
            $stmt_check_nidn->close();
        }

        // Cek duplikasi NPM untuk Mahasiswa
        if (!$is_duplicate && $role == 'mahasiswa') {
            $check_npm_sql = "SELECT user_id FROM Mahasiswa WHERE npm = ?";
            $params_npm_check = [$user_data['npm']];
            $types_npm_check = "s";
            if ($is_editing) {
                $check_npm_sql .= " AND user_id != ?";
                $params_npm_check[] = $user_id_to_edit;
                $types_npm_check .= "i";
            }
            $stmt_check_npm = $conn->prepare($check_npm_sql);
            $stmt_check_npm->bind_param($types_npm_check, ...$params_npm_check);
            $stmt_check_npm->execute();
            if ($stmt_check_npm->get_result()->num_rows > 0) {
                $error_message = "NPM '" . htmlspecialchars($user_data['npm']) . "' sudah digunakan oleh mahasiswa lain.";
                $is_duplicate = true;
            }
            $stmt_check_npm->close();
        }


        if (!$is_duplicate) {
            $conn->begin_transaction();
            try {
                $current_user_id_for_op = $is_editing ? $user_id_to_edit : null;

                if ($is_editing) { // UPDATE Auth_Users
                    $sql_auth = "UPDATE Auth_Users SET full_name = ?, email = ?, username = ?, is_active = ?";
                    $params_auth = [$full_name, $email, $username, $is_active];
                    $types_auth = "sssi"; // username bisa null
                    if (!empty($password_input)) {
                        $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
                        $sql_auth .= ", password_hash = ?";
                        $params_auth[] = $password_hash;
                        $types_auth .= "s";
                    }
                    $sql_auth .= " WHERE user_id = ?";
                    // Peran TIDAK diupdate karena sudah diset sebelumnya dan tidak bisa diubah saat edit
                    $params_auth[] = $current_user_id_for_op;
                    $types_auth .= "i";
                } else { // INSERT Auth_Users
                    $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
                    $sql_auth = "INSERT INTO Auth_Users (full_name, email, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?, ?)";
                    $params_auth = [$full_name, $email, $username, $password_hash, $role, $is_active];
                    $types_auth = "sssssi"; // username bisa null
                }

                $stmt_auth = $conn->prepare($sql_auth);
                if (!$stmt_auth)
                    throw new Exception("Gagal mempersiapkan data Auth_Users: " . $conn->error);
                $stmt_auth->bind_param($types_auth, ...$params_auth);
                if (!$stmt_auth->execute())
                    throw new Exception("Gagal menyimpan data Auth_Users: " . $stmt_auth->error . " | SQL: " . $sql_auth);


                if (!$is_editing) {
                    $current_user_id_for_op = $stmt_auth->insert_id;
                    // Set user_id pada $user_data untuk kasus tambah baru, agar form bisa repopulate dengan benar jika ada error di detail peran
                    $user_data['user_id'] = $current_user_id_for_op;
                }
                $stmt_auth->close();

                // Insert atau Update tabel peran spesifik
                if ($role == 'dosen') {
                    $stmt_role_upsert = $conn->prepare("INSERT INTO Dosen (user_id, nidn, department) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nidn = VALUES(nidn), department = VALUES(department)");
                    if (!$stmt_role_upsert)
                        throw new Exception("Gagal mempersiapkan data Dosen: " . $conn->error);
                    $stmt_role_upsert->bind_param("iss", $current_user_id_for_op, $user_data['nidn'], $user_data['department_dosen']);
                    if (!$stmt_role_upsert->execute())
                        throw new Exception("Gagal menyimpan detail Dosen: " . $stmt_role_upsert->error);
                    $stmt_role_upsert->close();
                } elseif ($role == 'mahasiswa') {
                    $stmt_role_upsert = $conn->prepare("INSERT INTO Mahasiswa (user_id, npm, angkatan, kelas, jabatan_kelas) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE npm = VALUES(npm), angkatan = VALUES(angkatan), kelas = VALUES(kelas), jabatan_kelas = VALUES(jabatan_kelas)");
                    if (!$stmt_role_upsert)
                        throw new Exception("Gagal mempersiapkan data Mahasiswa: " . $conn->error);
                    $stmt_role_upsert->bind_param("issss", $current_user_id_for_op, $user_data['npm'], $user_data['angkatan'], $user_data['kelas'], $user_data['jabatan_kelas']);
                    if (!$stmt_role_upsert->execute())
                        throw new Exception("Gagal menyimpan detail Mahasiswa: " . $stmt_role_upsert->error);
                    $stmt_role_upsert->close();
                } elseif ($role == 'kajur') {
                    $stmt_role_upsert = $conn->prepare("INSERT INTO Kajur (user_id, department_managed, start_date, end_date) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE department_managed = VALUES(department_managed), start_date = VALUES(start_date), end_date = VALUES(end_date)");
                    if (!$stmt_role_upsert)
                        throw new Exception("Gagal mempersiapkan data Kajur: " . $conn->error);
                    $stmt_role_upsert->bind_param("isss", $current_user_id_for_op, $user_data['department_managed_kajur'], $user_data['start_date_kajur'], $user_data['end_date_kajur']);
                    if (!$stmt_role_upsert->execute())
                        throw new Exception("Gagal menyimpan detail Kajur: " . $stmt_role_upsert->error);
                    $stmt_role_upsert->close();
                }

                $conn->commit();
                $success_message = "Data pengguna '" . htmlspecialchars($full_name) . "' berhasil " . ($is_editing ? "diperbarui." : "ditambahkan (ID Akun: $current_user_id_for_op).");

                // Jika tambah baru dan sukses, reset $user_data agar form kosong lagi
                if ($is_new_user_submission) {
                    $user_data = [ // Reset ke default untuk tambah baru
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
                } elseif ($is_editing) {
                    // Re-fetch data yang baru diupdate untuk ditampilkan di form
                    // (sudah dilakukan di awal blok if(isset($_GET['user_id'])), tapi bisa di-refresh lagi)
                    // Atau kita bisa mengandalkan $user_data yang sudah diupdate dari $_POST
                }

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Terjadi kesalahan: " . $e->getMessage();
            }
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
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        var js_initial_sidebar_force_closed = <?php echo $js_initial_sidebar_force_closed; ?>;
        function confirmResetPassword() {
            return confirm("Apakah Anda yakin ingin mereset password pengguna ini? Password baru akan digenerate secara acak dan akan ditampilkan sekali di halaman ini setelah berhasil.");
        }
        function toggleRoleSpecificFields() {
            var roleSelect = document.getElementById('role');
            if (!roleSelect) return; // elemen tidak ada
            var role = roleSelect.value;

            var dosenFields = document.getElementById('dosen_specific_fields');
            var mahasiswaFields = document.getElementById('mahasiswa_specific_fields');
            var kajurFields = document.getElementById('kajur_specific_fields');
            var usernameInput = document.getElementById('username');
            var usernameLabel = document.querySelector('label[for="username"]');


            if (dosenFields) dosenFields.style.display = (role === 'dosen') ? 'block' : 'none';
            if (mahasiswaFields) mahasiswaFields.style.display = (role === 'mahasiswa') ? 'block' : 'none';
            if (kajurFields) kajurFields.style.display = (role === 'kajur') ? 'block' : 'none';

            if (usernameInput && usernameLabel) {
                if (role === 'mahasiswa' || role === 'admin') {
                    usernameLabel.innerHTML = 'Username <span style="color:red;">*</span>';
                    // usernameInput.required = true; // Dinamis set required (jika diperlukan)
                } else {
                    usernameLabel.innerHTML = 'Username (Opsional untuk Dosen/Kajur)';
                    // usernameInput.required = false;
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            var roleSelect = document.getElementById('role');
            if (roleSelect) {
                toggleRoleSpecificFields(); // Panggil saat load
                roleSelect.addEventListener('change', toggleRoleSpecificFields);
            }

            // Fungsi untuk toggle password
            function setupPasswordToggle(inputId, toggleButtonId) {
                const passwordInput = document.getElementById(inputId);
                const togglePasswordButton = document.getElementById(toggleButtonId);

                if (passwordInput && togglePasswordButton) {
                    const eyeIcon = togglePasswordButton.querySelector("i");
                    if (!eyeIcon) return; // Pastikan ikon ada

                    const showPass = function () {
                        passwordInput.type = "text";
                        eyeIcon.classList.remove("fa-eye");
                        eyeIcon.classList.add("fa-eye-slash");
                    };
                    const hidePass = function () {
                        passwordInput.type = "password";
                        eyeIcon.classList.remove("fa-eye-slash");
                        eyeIcon.classList.add("fa-eye");
                    };

                    togglePasswordButton.addEventListener("mousedown", showPass);
                    togglePasswordButton.addEventListener("touchstart", function (e) { e.preventDefault(); showPass(); });
                    togglePasswordButton.addEventListener("mouseup", hidePass);
                    togglePasswordButton.addEventListener("mouseleave", function () { if (passwordInput.type === "text") hidePass(); });
                    togglePasswordButton.addEventListener("touchend", hidePass);
                    togglePasswordButton.addEventListener("touchcancel", hidePass);
                }
            }
            setupPasswordToggle("password", "toggle_password_main");
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
            margin: 15px 0;
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
            margin-top: 15px;
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
                            class="<?php echo ($current_page_php == 'admin_manage_users.php') ? 'active' : ''; ?>"><i
                                class="fas fa-users-cog"></i> <span class="menu-text">User</span></a></li>
                    <li><a href="admin_manage_dosen.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_dosen.php') !== false) ? 'active' : ''; ?>"><i
                                class="fas fa-chalkboard-teacher"></i> <span class="menu-text">Dosen</span></a></li>
                    <li><a href="admin_manage_mahasiswa.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_mahasiswa.php') !== false) ? 'active' : ''; ?>"><i
                                class="fas fa-user-graduate"></i> <span class="menu-text">Mahasiswa</span></a></li>
                    <li><a href="admin_manage_jadwal.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_jadwal.php') !== false) ? 'active' : ''; ?>"><i
                                class="fas fa-calendar-alt"></i> <span class="menu-text">Jadwal</span></a></li>
                    <li><a href="admin_manage_matakuliah.php"
                            class="<?php echo (strpos($current_page_php, 'admin_manage_matakuliah.php') !== false) ? 'active' : ''; ?>"><i
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
                <div class="success-msg"><?php echo $success_message; ?></div><?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-msg"><?php echo $error_message; ?></div><?php endif; ?>

            <?php if ($generated_password_display): ?>
                <div class="password-display-box">
                    <h4>Password Baru Telah Digenerate:</h4>
                    <p>Harap segera catat dan berikan password ini kepada pengguna. Password hanya ditampilkan sekali.</p>
                    <strong><?php echo htmlspecialchars($generated_password_display); ?></strong>
                </div>
            <?php endif; ?>

            <?php if ($user_data !== null || !$is_editing): // Tampilkan form jika user_data ada (untuk edit) ATAU jika mode tambah ?>
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
                            <select id="role" name="role" required <?php echo $is_editing ? 'disabled' : ''; ?>>
                                <option value="">-- Pilih Peran --</option>
                                <option value="mahasiswa" <?php echo (($user_data['role'] ?? '') == 'mahasiswa') ? 'selected' : ''; ?>>Mahasiswa</option>
                                <option value="dosen" <?php echo (($user_data['role'] ?? '') == 'dosen') ? 'selected' : ''; ?>>Dosen</option>
                                <option value="kajur" <?php echo (($user_data['role'] ?? '') == 'kajur') ? 'selected' : ''; ?>>Kajur</option>
                                <option value="admin" <?php echo (($user_data['role'] ?? '') == 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <?php if ($is_editing): // Jika mode edit, tambahkan hidden input untuk role ?>
                                <input type="hidden" name="role" value="<?php echo htmlspecialchars($user_data['role']); ?>">
                                <small>Peran tidak dapat diubah setelah pengguna dibuat. Hapus dan buat ulang pengguna jika
                                    peran perlu diganti.</small>
                            <?php endif; ?>
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

                        <div id="dosen_specific_fields" style="display:none;">
                            <h4>Detail Dosen:</h4>
                            <div class="input-group"><label for="nidn">NIDN <span style="color:red;">*</span></label><input
                                    type="text" id="nidn" name="nidn"
                                    value="<?php echo htmlspecialchars($user_data['nidn'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="department_dosen">Departemen/Jurusan <span
                                        style="color:red;">*</span></label><input type="text" id="department_dosen"
                                    name="department_dosen"
                                    value="<?php echo htmlspecialchars($user_data['department_dosen'] ?? ''); ?>"></div>
                        </div>
                        <div id="mahasiswa_specific_fields" style="display:none;">
                            <h4>Detail Mahasiswa:</h4>
                            <div class="input-group"><label for="npm">NPM/NIM <span
                                        style="color:red;">*</span></label><input type="text" id="npm" name="npm"
                                    value="<?php echo htmlspecialchars($user_data['npm'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="angkatan">Angkatan <span
                                        style="color:red;">*</span></label><input type="text" id="angkatan" name="angkatan"
                                    pattern="\d{4}" value="<?php echo htmlspecialchars($user_data['angkatan'] ?? ''); ?>"
                                    placeholder="YYYY"></div>
                            <div class="input-group"><label for="kelas">Kelas <span
                                        style="color:red;">*</span></label><input type="text" id="kelas" name="kelas"
                                    value="<?php echo htmlspecialchars($user_data['kelas'] ?? ''); ?>"></div>
                            <div class="input-group"><label for="jabatan_kelas">Jabatan Kelas</label><input type="text"
                                    id="jabatan_kelas" name="jabatan_kelas"
                                    value="<?php echo htmlspecialchars($user_data['jabatan_kelas'] ?? ''); ?>"></div>
                        </div>
                        <div id="kajur_specific_fields" style="display:none;">
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
                            <label for="password">Password <?php echo $is_editing ? 'Baru' : ''; ?>
                                <?php if (!$is_editing)
                                    echo '<span style="color:red;">*</span>'; ?></label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password" <?php echo !$is_editing ? 'required minlength="6"' : 'minlength="6"'; ?>>
                                <span class="toggle-password" id="toggle_password_main"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>
                        <?php if (!$is_editing): // Hanya tampilkan konfirmasi password untuk pengguna baru ?>
                            <div class="input-group">
                                <label for="confirm_password">Konfirmasi Password <span style="color:red;">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                                    <span class="toggle-password" id="toggle_confirm_password"><i class="fas fa-eye"></i></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-actions">
                            <button type="submit" name="<?php echo $is_editing ? 'update_user' : 'add_user'; ?>"
                                class="btn-login" style="width:auto; padding: 10px 20px;">
                                <i class="fas <?php echo $is_editing ? 'fa-save' : 'fa-plus'; ?>"></i>
                                <?php echo $is_editing ? 'Simpan Perubahan' : 'Tambah Pengguna'; ?>
                            </button>
                            <a href="admin_manage_users.php" class="btn-penilaian"
                                style="background-color: #6c757d; color:white; text-decoration:none; padding: 10px 20px;">
                                <i class="fas fa-times"></i> Batal
                            </a>
                            <?php if ($is_editing && $user_id_to_edit != $loggedInAdminId): ?>
                                <button type="submit" name="reset_password_user" class="btn-penilaian"
                                    formaction="<?php echo htmlspecialchars($form_action_url); ?>"
                                    style="background-color: #ffc107; color:black; padding: 10px 15px;"
                                    onclick="return confirmResetPassword();">
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
            <?php elseif ($is_editing && $user_data === null): // Jika mode edit tapi data tidak ditemukan ?>
                <section class="content-card">
                    <p>Gagal memuat data pengguna atau data tidak ditemukan.</p>
                    <a href="admin_manage_users.php" class="btn-penilaian">Kembali ke Daftar Pengguna</a>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <script src="../js/script.js"></script>
    <?php // Pastikan script.js sudah memuat fungsi toggleRoleSpecificFields dan setupPasswordToggle jika dipisah ?>
</body>

</html>
