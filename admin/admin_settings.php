<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// evados/admin_settings.php
require_once '../includes/auth_check_admin.php';
require_once '../config/db.php';

$page_title = "Pengaturan Sistem";
$success_message = '';
$error_message = '';

// Ambil pengaturan saat ini
$settings = [];
$result_settings = $conn->query("SELECT setting_key, setting_value FROM system_settings");
if ($result_settings) {
    while ($row = $result_settings->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_settings'])) {
    $semester_aktif = trim($_POST['semester_aktif']);
    $batas_akhir_penilaian = trim($_POST['batas_akhir_penilaian']);

    if (empty($semester_aktif) || empty($batas_akhir_penilaian)) {
        $error_message = "Semua field pengaturan wajib diisi.";
    } else {
        // Validasi format tanggal (YYYY-MM-DD)
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $batas_akhir_penilaian)) {
            $error_message = "Format Batas Akhir Penilaian tidak valid. Gunakan YYYY-MM-DD.";
        } else {
            $conn->begin_transaction();
            try {
                $stmt_update_semester = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'semester_aktif'");
                $stmt_update_semester->bind_param("s", $semester_aktif);
                if (!$stmt_update_semester->execute())
                    throw new Exception("Gagal menyimpan Semester Aktif.");
                $stmt_update_semester->close();

                $stmt_update_batas = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'batas_akhir_penilaian'");
                $stmt_update_batas->bind_param("s", $batas_akhir_penilaian);
                if (!$stmt_update_batas->execute())
                    throw new Exception("Gagal menyimpan Batas Akhir Penilaian.");
                $stmt_update_batas->close();

                $conn->commit();
                $success_message = "Pengaturan sistem berhasil disimpan.";
                // Refresh settings
                $settings['semester_aktif'] = $semester_aktif;
                $settings['batas_akhir_penilaian'] = $batas_akhir_penilaian;

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    }
}

$current_page_php = basename($_SERVER['PHP_SELF']);
$js_initial_sidebar_force_closed = 'false';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Evados</title>
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
                <h1><?php echo $page_title; ?></h1>
            </header>

            <?php if (!empty($success_message)): ?>
                <div class="success-msg"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <section class="content-card">
                <h2><i class="fas fa-cogs"></i> Atur Parameter Sistem</h2>
                <form action="admin_settings.php" method="POST" class="form-container" style="max-width: 600px;">
                    <div class="input-group">
                        <label for="semester_aktif">Semester Aktif <span style="color:red;">*</span></label>
                        <input type="text" id="semester_aktif" name="semester_aktif" required
                            value="<?php echo htmlspecialchars($settings['semester_aktif'] ?? 'Genap 2024/2025'); ?>"
                            placeholder="Contoh: Genap 2024/2025">
                    </div>
                    <div class="input-group">
                        <label for="batas_akhir_penilaian">Batas Akhir Penilaian <span
                                style="color:red;">*</span></label>
                        <input type="date" id="batas_akhir_penilaian" name="batas_akhir_penilaian" required
                            value="<?php echo htmlspecialchars($settings['batas_akhir_penilaian'] ?? '2024-12-30'); ?>">
                        <small>Format: YYYY-MM-DD</small>
                    </div>
                    <button type="submit" name="save_settings" class="btn-login"
                        style="width:auto; padding: 10px 20px;">Simpan Pengaturan</button>
                </form>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>