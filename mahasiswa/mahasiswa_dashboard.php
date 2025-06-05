<?php
// evados/mahasiswa_dashboard.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$js_initial_sidebar_force_closed = 'false';
if (isset($_SESSION['initial_dashboard_load_sidebar_closed']) && $_SESSION['initial_dashboard_load_sidebar_closed'] === true) {
    $js_initial_sidebar_force_closed = 'true';
    unset($_SESSION['initial_dashboard_load_sidebar_closed']);
}

require_once '../includes/auth_check_mahasiswa.php';
require_once '../config/db.php';

// Ambil pengaturan sistem
$settings_db = [];
$semester_aktif = "Semester Belum Diatur";
$batas_akhir_penilaian_db_str = "2099-12-31"; // Default jauh di masa depan jika tidak ada setting
$tahun_ajaran_aktif = ""; // Variabel baru untuk tahun ajaran

$result_settings_db = $conn->query("SELECT setting_key, setting_value FROM system_settings");
if ($result_settings_db) {
    while ($row_db = $result_settings_db->fetch_assoc()) {
        $settings_db[$row_db['setting_key']] = $row_db['setting_value'];
    }
    $semester_aktif = $settings_db['semester_aktif'] ?? $semester_aktif;
    $batas_akhir_penilaian_db_str = $settings_db['batas_akhir_penilaian'] ?? $batas_akhir_penilaian_db_str;

    // Ekstrak tahun ajaran dari semester_aktif
    if ($semester_aktif !== "Semester Belum Diatur") {
        if (preg_match('/(\d{4}\/\d{4})/', $semester_aktif, $matches_tahun)) {
            $tahun_ajaran_aktif = $matches_tahun[1];
        } else {
            error_log("Format semester_aktif (" . $semester_aktif . ") di mahasiswa_dashboard.php tidak mengandung tahun ajaran (YYYY/YYYY).");
            // Handle error, mungkin $tahun_ajaran_aktif tetap kosong atau diberi nilai default error
            // atau redirect dengan pesan error jika ini krusial untuk fungsionalitas.
        }
    }
} else {
    error_log("Gagal mengambil system_settings di mahasiswa_dashboard.php: " . $conn->error);
}


// Proses batas waktu penilaian
$batas_akhir_display = "Tanggal Belum Diatur";
$periode_penilaian_berakhir = false;
$tanggal_sekarang = new DateTime();

if ($batas_akhir_penilaian_db_str !== "Tanggal Belum Diatur") {
    try {
        $batas_waktu_obj = DateTime::createFromFormat('Y-m-d H:i:s', $batas_akhir_penilaian_db_str . ' 23:59:59');
        if ($batas_waktu_obj) {
            if ($tanggal_sekarang > $batas_waktu_obj) {
                $periode_penilaian_berakhir = true;
            }
            if (class_exists('IntlDateFormatter')) {
                $formatter = new IntlDateFormatter('id_ID', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'Asia/Jakarta');
                $batas_akhir_display = $formatter->format($batas_waktu_obj);
            } else {
                $bulan = array(1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
                $batas_akhir_display = $batas_waktu_obj->format('d') . ' ' . $bulan[(int) $batas_waktu_obj->format('m')] . ' ' . $batas_waktu_obj->format('Y');
            }
        } else {
            $batas_akhir_display = "Format Tanggal Pengaturan Salah";
            // $periode_penilaian_berakhir = true; // Anggap berakhir jika format salah
        }
    } catch (Exception $e) {
        $batas_akhir_display = "Error Tanggal Pengaturan";
        // $periode_penilaian_berakhir = true;
        error_log("Error DateTime untuk batas_akhir_penilaian (mahasiswa_dashboard): " . $e->getMessage());
    }
}


$kelas_mahasiswa = '';
$stmt_kelas = $conn->prepare("SELECT kelas FROM Mahasiswa WHERE user_id = ?");
if ($stmt_kelas) {
    $stmt_kelas->bind_param("i", $loggedInUserId);
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    if ($row_kelas = $result_kelas->fetch_assoc()) {
        $kelas_mahasiswa = $row_kelas['kelas'];
    }
    $stmt_kelas->close();
} else {
    error_log("DB Error (prepare) - Fetching student class in mahasiswa_dashboard.php: " . $conn->error);
}

$dosen_list = [];
$total_evaluable_lecturers = 0;
$evaluated_count = 0;

if (!empty($kelas_mahasiswa) && $semester_aktif !== "Semester Belum Diatur" && !empty($tahun_ajaran_aktif)) {
    $sql_dosen = "SELECT DISTINCT au.user_id, au.full_name, d.nidn
                  FROM Auth_Users au
                  JOIN Dosen d ON au.user_id = d.user_id
                  JOIN Jadwal_Mengajar jm ON au.user_id = jm.dosen_user_id
                  WHERE jm.nama_kelas = ? AND jm.semester = ?
                  ORDER BY au.full_name ASC";
    $stmt_dosen = $conn->prepare($sql_dosen);
    if ($stmt_dosen) {
        $stmt_dosen->bind_param("ss", $kelas_mahasiswa, $semester_aktif);
        $stmt_dosen->execute();
        $result_dosen = $stmt_dosen->get_result();
        if ($result_dosen && $result_dosen->num_rows > 0) {
            while ($row = $result_dosen->fetch_assoc()) {
                $dosen_list[] = $row;
            }
            $total_evaluable_lecturers = count($dosen_list);

            // Hitung dosen yang sudah dievaluasi PADA PERIODE AKTIF
            $sql_evaluated_count = "SELECT COUNT(DISTINCT lecturer_user_id) as count
                                    FROM Evaluations
                                    WHERE student_user_id = ?
                                    AND semester_evaluasi = ? AND tahun_ajaran_evaluasi = ?";
            $stmt_evaluated_count = $conn->prepare($sql_evaluated_count);
            if ($stmt_evaluated_count) {
                $stmt_evaluated_count->bind_param("iss", $loggedInUserId, $semester_aktif, $tahun_ajaran_aktif);
                $stmt_evaluated_count->execute();
                $res_eval_count = $stmt_evaluated_count->get_result();
                if ($row_eval_count = $res_eval_count->fetch_assoc()) {
                    $evaluated_count = (int) $row_eval_count['count'];
                }
                $stmt_evaluated_count->close();
            } else {
                error_log("DB Error (prepare) - Counting evaluated lecturers in mahasiswa_dashboard.php: " . $conn->error);
            }
        }
        $stmt_dosen->close();
    } else {
        error_log("DB Error (prepare) - Fetching lecturers in mahasiswa_dashboard.php: " . $conn->error);
    }
}

$success_message_page = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
$error_message_page_redirect = $_SESSION['error_message_page'] ?? '';
unset($_SESSION['error_message_page']);

$current_page_php_mhs = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa - Evados</title>
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
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars"></i> </button>
                <div class="sidebar-header">
                    <h3 class="logo-text">EVADOS</h3>
                </div>
            </div>
            <nav class="sidebar-menu">
                <ul>
                    <li><a href="mahasiswa_dashboard.php"
                            class="<?php echo ($current_page_php_mhs == 'mahasiswa_dashboard.php') ? 'active' : ''; ?>"><i
                                class="fas fa-edit"></i> <span class="menu-text">Penilaian Dosen</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-logout-section">
                <a href="../logout.php" class="logout-link"> <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Logout</span>
                </a>
            </div>
        </aside>

        <main class="main-content" id="mainContent">
            <header class="header">
                <h1>Selamat Datang, <?php echo htmlspecialchars($loggedInUserFullName); ?>!</h1>
            </header>

            <?php if ($success_message_page): ?>
                <div class="success-msg"><?php echo htmlspecialchars($success_message_page); ?></div>
            <?php endif; ?>
            <?php if ($error_message_page_redirect): ?>
                <div class="error-msg-page"><?php echo htmlspecialchars($error_message_page_redirect); ?></div>
            <?php endif; ?>

            <div class="info-box">
                <h4>Informasi Evaluasi</h4>
                <p><strong>Periode Evaluasi:</strong> <?php echo htmlspecialchars($semester_aktif); ?></p>
                <p><strong>Batas Akhir Penilaian:</strong> <?php echo htmlspecialchars($batas_akhir_display); ?></p>
                <hr style="margin: 10px 0; border-color: var(--primary-color);">
                <p><strong>Petunjuk:</strong></p>
                <ul>
                    <li>Penilaian ini bertujuan untuk meningkatkan kualitas pembelajaran.</li>
                    <li>Identitas Anda sebagai penilai akan dijaga kerahasiaannya (ANONIM).</li>
                    <li>Mohon berikan penilaian yang objektif dan konstruktif.</li>
                    <li>Anda dapat memberikan satu set penilaian untuk setiap dosen yang mengajar Anda pada periode ini.
                    </li>
                </ul>
            </div>

            <?php if ($periode_penilaian_berakhir): ?>
                <div class="info-box" style="border-left-color: var(--error-color); background-color: #f8d7da;">
                    <h4><i class="fas fa-exclamation-triangle"></i> Periode Penilaian Telah Berakhir</h4>
                    <p>Anda tidak dapat lagi memberikan atau mengubah penilaian dosen untuk periode ini.</p>
                </div>
            <?php endif; ?>

            <?php if (empty($tahun_ajaran_aktif) && $semester_aktif !== "Semester Belum Diatur"): ?>
                <div class="info-box" style="border-left-color: var(--error-color); background-color: #f8d7da;">
                    <h4><i class="fas fa-exclamation-triangle"></i> Pengaturan Periode Tidak Lengkap</h4>
                    <p>Informasi tahun ajaran untuk periode evaluasi saat ini tidak dapat ditemukan. Silakan hubungi
                        administrator.</p>
                </div>
            <?php endif; ?>

            <div class="info-box" style="margin-bottom: 20px;">
                <h4>Progres Penilaian Anda (Kelas: <?php echo htmlspecialchars($kelas_mahasiswa); ?>)</h4>
                <?php if ($total_evaluable_lecturers > 0 && !empty($tahun_ajaran_aktif)): ?>
                    <p>Anda telah menilai <strong><?php echo $evaluated_count; ?></strong> dari
                        <strong><?php echo $total_evaluable_lecturers; ?></strong> dosen yang tersedia pada periode ini.
                    </p>
                    <div style="background-color: #e0e0e0; border-radius: 5px; padding: 2px; margin-top: 5px;">
                        <div
                            style="width: <?php echo ($total_evaluable_lecturers > 0 ? ($evaluated_count / $total_evaluable_lecturers) * 100 : 0); ?>%; background-color: var(--primary-color); color: white; text-align: center; line-height: 20px; border-radius: 3px; font-size:0.8em;">
                            <?php echo ($total_evaluable_lecturers > 0 ? round(($evaluated_count / $total_evaluable_lecturers) * 100) : 0); ?>%
                        </div>
                    </div>
                <?php elseif (empty($tahun_ajaran_aktif) && $semester_aktif !== "Semester Belum Diatur"): ?>
                    <p>Tidak dapat menghitung progres karena informasi tahun ajaran tidak lengkap.</p>
                <?php else: ?>
                    <p>Tidak ada dosen yang dijadwalkan untuk Anda evaluasi saat ini di kelas
                        <?php echo htmlspecialchars($kelas_mahasiswa); ?> untuk periode
                        <?php echo htmlspecialchars($semester_aktif); ?>.
                    </p>
                <?php endif; ?>
            </div>

            <section id="penilaian-content" class="content-card">
                <h2><i class="fas fa-chalkboard-teacher"></i> Daftar Dosen untuk Dinilai</h2>
                <p>Dosen yang ditampilkan adalah dosen yang mengajar di kelas Anda
                    (<?php echo htmlspecialchars($kelas_mahasiswa); ?>) pada periode
                    <?php echo htmlspecialchars($semester_aktif); ?>.
                </p>
                <table class="dosen-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No.</th>
                            <th>NIDN</th>
                            <th>Nama Dosen</th>
                            <th>Status Penilaian Periode Ini</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($dosen_list) && !empty($tahun_ajaran_aktif)): ?>
                            <?php $no = 1;
                            foreach ($dosen_list as $dosen): ?>
                                <?php
                                $has_evaluated_periode_ini = false;
                                if (!empty($tahun_ajaran_aktif)) { // Hanya cek jika tahun ajaran valid
                                    $sql_check_eval_loop = "SELECT evaluation_id FROM Evaluations 
                                                            WHERE student_user_id = ? AND lecturer_user_id = ?
                                                            AND semester_evaluasi = ? AND tahun_ajaran_evaluasi = ?";
                                    $stmt_check_eval_loop = $conn->prepare($sql_check_eval_loop);
                                    if ($stmt_check_eval_loop) {
                                        $stmt_check_eval_loop->bind_param("iiss", $loggedInUserId, $dosen['user_id'], $semester_aktif, $tahun_ajaran_aktif);
                                        $stmt_check_eval_loop->execute();
                                        $result_check_eval_loop = $stmt_check_eval_loop->get_result();
                                        if ($result_check_eval_loop->num_rows > 0) {
                                            $has_evaluated_periode_ini = true;
                                        }
                                        $stmt_check_eval_loop->close();
                                    } else {
                                        error_log("DB Error (prepare) mahasiswa_dashboard.php - check eval loop: " . $conn->error);
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($dosen['nidn']); ?></td>
                                    <td><?php echo htmlspecialchars($dosen['full_name']); ?></td>
                                    <td>
                                        <?php if ($has_evaluated_periode_ini): ?>
                                            <span class="status-done"><i class="fas fa-check-circle"></i> Selesai</span>
                                        <?php else: ?>
                                            <span class="status-pending"><i class="fas fa-exclamation-circle"></i> Belum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($has_evaluated_periode_ini): ?>
                                            <button class="btn-penilaian btn-disabled" disabled>Sudah Dinilai</button>
                                        <?php elseif ($periode_penilaian_berakhir): ?>
                                            <button class="btn-penilaian btn-disabled" disabled
                                                title="Periode penilaian telah berakhir">Periode Berakhir</button>
                                        <?php elseif (empty($tahun_ajaran_aktif)): ?>
                                            <button class="btn-penilaian btn-disabled" disabled
                                                title="Pengaturan periode tidak lengkap">Evaluasi Ditutup</button>
                                        <?php else: ?>
                                            <a href="penilaian_dosen.php?dosen_id=<?php echo $dosen['user_id']; ?>"
                                                class="btn-penilaian">Beri Penilaian <i class="fas fa-pen-to-square"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif (empty($tahun_ajaran_aktif) && $semester_aktif !== "Semester Belum Diatur"): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;">Tidak dapat menampilkan daftar dosen karena
                                    pengaturan periode tidak lengkap.</td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center;">Belum ada data dosen yang terdaftar mengajar di
                                    kelas Anda untuk periode ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
    $conn->close(); ?>