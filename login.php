<?php
// evados/login.php
session_start();
require_once 'config/db.php'; //

// Simpan input sebelumnya jika ada percobaan login gagal
$previous_identifier = '';
$previous_password = ''; // Password tidak akan diisi ulang untuk keamanan, tapi variabelnya ada
$previous_kategori = '';


if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
  if ($_SESSION['role'] == 'mahasiswa') {
    header("Location: mahasiswa/mahasiswa_dashboard.php");
    exit();
  } elseif ($_SESSION['role'] == 'dosen') {
    header("Location: dosen/dosen_dashboard.php");
    exit();
  } elseif ($_SESSION['role'] == 'kajur') {
    header("Location: kajur/kajur_dashboard.php");
    exit();
  } elseif ($_SESSION['role'] == 'admin') {
    header("Location: admin/admin_dashboard.php");
    exit();
  }
}

$error_message = '';
if (isset($_SESSION['error_message_login'])) {
  $error_message = $_SESSION['error_message_login'];
  unset($_SESSION['error_message_login']);
}
// Ambil data input sebelumnya jika ada dari sesi (setelah gagal login)
if (isset($_SESSION['login_attempt_identifier'])) {
  $previous_identifier = $_SESSION['login_attempt_identifier'];
  unset($_SESSION['login_attempt_identifier']);
}
if (isset($_SESSION['login_attempt_kategori'])) {
  $previous_kategori = $_SESSION['login_attempt_kategori'];
  unset($_SESSION['login_attempt_kategori']);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $kategori_user = $_POST['kategori_user'] ?? '';
  $identifier = trim($_POST['identifier'] ?? '');
  $password = trim($_POST['password'] ?? '');

  // Simpan input ke sesi untuk diisi ulang jika gagal
  $_SESSION['login_attempt_identifier'] = $identifier;
  $_SESSION['login_attempt_kategori'] = $kategori_user;


  if (empty($kategori_user)) { // Cek jika kategori belum dipilih
    $error_message = "Silakan pilih kategori pengguna.";
  } elseif (empty($identifier) || empty($password)) {
    $error_message = "Semua field harus diisi.";
  } else {
    $sql = "";
    $stmt = null;
    $identifier_field_name = "";

    // Hanya proses jika kategori valid dipilih
    if (in_array($kategori_user, ['mahasiswa', 'dosen', 'kajur', 'admin'])) {
      if ($kategori_user == 'mahasiswa') {
        $sql = "SELECT user_id, username, password_hash, role, full_name, is_active FROM Auth_Users WHERE username = ? AND role = 'mahasiswa'"; //
        $stmt = $conn->prepare($sql);
        if ($stmt)
          $stmt->bind_param("s", $identifier);
        $identifier_field_name = "Username";
      } elseif (in_array($kategori_user, ['dosen', 'kajur', 'admin'])) {
        $sql = "SELECT user_id, email, password_hash, role, full_name, is_active FROM Auth_Users WHERE email = ? AND role = ?"; //
        $stmt = $conn->prepare($sql);
        if ($stmt)
          $stmt->bind_param("ss", $identifier, $kategori_user);
        $identifier_field_name = "Email";
      }

      if (isset($stmt) && $stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
          $user = $result->fetch_assoc();
          if (password_verify($password, $user['password_hash'])) {
            if ($user['is_active'] == 1) { // Cek status keaktifan
              // Hapus data percobaan login dari sesi jika berhasil
              unset($_SESSION['login_attempt_identifier']);
              unset($_SESSION['login_attempt_kategori']);

              $_SESSION['user_id'] = $user['user_id'];
              $_SESSION['full_name'] = $user['full_name'];
              $_SESSION['role'] = $user['role'];
              $_SESSION['initial_dashboard_load_sidebar_closed'] = true;

              if ($user['role'] == 'mahasiswa') {
                $_SESSION['username'] = $user['username'];
                header("Location: mahasiswa/mahasiswa_dashboard.php"); //
              } elseif ($user['role'] == 'dosen') {
                $_SESSION['email'] = $user['email'];
                header("Location: dosen/dosen_dashboard.php"); //
              } elseif ($user['role'] == 'kajur') {
                $_SESSION['email'] = $user['email'];
                header("Location: kajur/kajur_dashboard.php"); //
              } elseif ($user['role'] == 'admin') {
                $_SESSION['email'] = $user['email'];
                header("Location: admin/admin_dashboard.php");
              }
              exit();
            } else {
              $error_message = "Akun Anda tidak aktif. Silakan hubungi administrator.";
            }
          } else {
            $error_message = "Password salah.";
          }
        } else {
          $error_message = $identifier_field_name . " tidak ditemukan atau bukan akun " . ucfirst($kategori_user) . ".";
        }
        $stmt->close();
      } else {
        // Ini seharusnya tidak terjadi jika $kategori_user valid
        $error_message = "Gagal mempersiapkan statement login.";
      }
    } elseif ($kategori_user === "") { // Jika "--Pilih Kategori--" yang dipilih
      $error_message = "Silakan pilih kategori pengguna yang valid.";
    } else { // Kategori tidak valid (seharusnya tidak mungkin jika dropdown benar)
      $error_message = "Kategori pengguna tidak valid.";
    }
  }
  // Jika ada error, kita tidak ingin menghapus session percobaan login agar form terisi kembali
  // Kecuali jika errornya adalah kategori tidak dipilih, maka biarkan saja kosong
  if ($error_message !== "Silakan pilih kategori pengguna." && $error_message !== "Silakan pilih kategori pengguna yang valid.") {
    // Sudah di set di awal blok POST
  } else {
    // Kosongkan identifier jika errornya adalah kategori belum dipilih, agar tidak membingungkan
    $_SESSION['login_attempt_identifier'] = '';
    // $_SESSION['login_attempt_kategori'] sudah di-set.
  }

  if (isset($conn))
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Evaluasi Dosen</title>
  <link rel="icon" href="logo.png" type="image/png" />
  <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
  <div class="login-container">
    <div class="login-box">
      <h2><i class="fas fa-sign-in-alt"></i> Login Sistem Evados</h2>
      <?php if (!empty($error_message)): ?>
        <p class="error-msg"><?php echo htmlspecialchars($error_message); ?></p>
      <?php endif; ?>
      <form id="loginForm" action="login.php" method="POST">
        <div class="input-group">
          <label for="kategori_user">Login Sebagai</label>
          <select id="kategori_user" name="kategori_user" required>
            <option value="">-- Pilih Kategori --</option>
            <option value="mahasiswa" <?php echo ($previous_kategori == 'mahasiswa') ? 'selected' : ''; ?>>Mahasiswa
              (Perangkat Kelas)</option>
            <option value="dosen" <?php echo ($previous_kategori == 'dosen') ? 'selected' : ''; ?>>Dosen</option>
            <option value="kajur" <?php echo ($previous_kategori == 'kajur') ? 'selected' : ''; ?>>Ketua Jurusan</option>
            <option value="admin" <?php echo ($previous_kategori == 'admin') ? 'selected' : ''; ?>>Administrator</option>
          </select>
        </div>
        <div class="input-group">
          <label for="identifier" id="identifier_label">Username atau Email</label>
          <input type="text" id="identifier" name="identifier" placeholder="Masukkan Username atau Email" required
            value="<?php echo htmlspecialchars($previous_identifier); ?>">
        </div>
        <div class="input-group">
          <label for="password">Password</label>
          <div class="password-wrapper">
            <input type="password" id="password" name="password" placeholder="Masukkan Password" required>
            <span class="toggle-password"><i class="fas fa-eye"></i></span>
          </div>
        </div>
        <button type="submit" class="btn-login">Login <i class="fas fa-arrow-right"></i></button>
      </form>
    </div>
  </div>
  <script src="js/script.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const kategoriUserSelect = document.getElementById("kategori_user");
      const identifierInput = document.getElementById("identifier");
      const identifierLabel = document.getElementById("identifier_label");

      function updateIdentifierField() {
        if (kategoriUserSelect.value === "mahasiswa") {
          identifierLabel.textContent = "Username";
          identifierInput.placeholder = "Masukkan Username Mahasiswa";
          identifierInput.type = "text";
        } else if (kategoriUserSelect.value === "") { // Jika "--Pilih Kategori--"
          identifierLabel.textContent = "Username atau Email";
          identifierInput.placeholder = "Masukkan Username atau Email";
          // Biarkan tipe input sebagai text atau sesuai kebutuhan default
        }
        else { // Dosen, Kajur, atau Admin
          identifierLabel.textContent = "Email";
          identifierInput.placeholder = "Masukkan Email";
          identifierInput.type = "email";
        }
      }

      if (kategoriUserSelect && identifierInput && identifierLabel) {
        kategoriUserSelect.addEventListener("change", updateIdentifierField);
        // Panggil saat load untuk set kondisi awal berdasarkan $previous_kategori
        updateIdentifierField();
      }
    });
  </script>
</body>

</html>