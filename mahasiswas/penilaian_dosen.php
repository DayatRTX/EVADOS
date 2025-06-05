<?php
// evados/penilaian_dosen.php
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

require_once '../includes/auth_check_mahasiswa.php';
require_once '../config/db.php';

// Ambil pengaturan sistem untuk batas waktu dan periode aktif
$batas_akhir_penilaian_str_form = "2099-12-31"; // Default jika tidak ada setting
$semester_evaluasi_aktif_form = "Semester Belum Diatur";
$tahun_ajaran_evaluasi_aktif_form = "";

$stmt_settings_form = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('batas_akhir_penilaian', 'semester_aktif')");
if ($stmt_settings_form) {
  $stmt_settings_form->execute();
  $result_settings_form = $stmt_settings_form->get_result();
  while ($row_setting_form = $result_settings_form->fetch_assoc()) {
    if ($row_setting_form['setting_key'] == 'batas_akhir_penilaian') {
      $batas_akhir_penilaian_str_form = $row_setting_form['setting_value'];
    } elseif ($row_setting_form['setting_key'] == 'semester_aktif') {
      $semester_evaluasi_aktif_form = $row_setting_form['setting_value'];
      // Ekstrak tahun ajaran dari semester_evaluasi_aktif_form
      if (preg_match('/(\d{4}\/\d{4})/', $semester_evaluasi_aktif_form, $matches_tahun_form)) {
        $tahun_ajaran_evaluasi_aktif_form = $matches_tahun_form[1];
      } else {
        error_log("Format semester_aktif (form penilaian) tidak valid: " . $semester_evaluasi_aktif_form);
        // Tahun ajaran akan tetap kosong, akan divalidasi di bawah
      }
    }
  }
  $stmt_settings_form->close();
} else {
  error_log("Gagal mengambil batas_akhir_penilaian/semester_aktif dari system_settings (form penilaian): " . $conn->error);
}

// Validasi apakah periode dan tahun ajaran berhasil didapatkan dan valid
if ($semester_evaluasi_aktif_form === "Semester Belum Diatur" || empty($tahun_ajaran_evaluasi_aktif_form)) {
  $_SESSION['error_message_page'] = "Pengaturan periode evaluasi sistem tidak lengkap atau tidak valid. Tidak dapat melanjutkan penilaian. Hubungi administrator.";
  header("Location: mahasiswa_dashboard.php");
  exit();
}

$tanggal_sekarang_form = new DateTime();
$batas_waktu_obj_form = null;
$periode_penilaian_berakhir_form = false;

if ($batas_akhir_penilaian_str_form !== "2099-12-31" && $batas_akhir_penilaian_str_form !== "Tanggal Belum Diatur") {
  try {
    $batas_waktu_obj_form = DateTime::createFromFormat('Y-m-d H:i:s', $batas_akhir_penilaian_str_form . ' 23:59:59');
    if ($batas_waktu_obj_form && $tanggal_sekarang_form > $batas_waktu_obj_form) {
      $periode_penilaian_berakhir_form = true;
    } elseif (!$batas_waktu_obj_form) {
      // error_log("Format tanggal batas_akhir_penilaian tidak valid dari DB (form penilaian): " . $batas_akhir_penilaian_str_form);
      // $periode_penilaian_berakhir_form = true; // Anggap berakhir jika format salah
    }
  } catch (Exception $e) {
    error_log("Error DateTime untuk batas_akhir_penilaian (form penilaian): " . $e->getMessage());
    // $periode_penilaian_berakhir_form = true; // Anggap berakhir jika error
  }
}

// PENGECEKAN AWAL HALAMAN: Jika periode berakhir, redirect
if ($periode_penilaian_berakhir_form) {
  $_SESSION['error_message_page'] = "Periode penilaian telah berakhir. Anda tidak dapat mengisi formulir ini.";
  header("Location: mahasiswa_dashboard.php");
  exit();
}

$lecturer_id = null;
$lecturer_name = "Dosen Tidak Valid";
$error_message = '';

// Ambil pesan error dari redirect jika ada
if (isset($_SESSION['error_message_penilaian'])) {
  $error_message = $_SESSION['error_message_penilaian'];
  unset($_SESSION['error_message_penilaian']);
}


if (!isset($_GET['dosen_id']) || !is_numeric($_GET['dosen_id'])) {
  $_SESSION['error_message_page'] = "ID Dosen tidak valid atau tidak disediakan.";
  header("Location: mahasiswa_dashboard.php");
  exit();
}
$lecturer_id = intval($_GET['dosen_id']);

$sql_lecturer = "SELECT full_name FROM Auth_Users WHERE user_id = ? AND role = 'dosen'";
$stmt_lecturer = $conn->prepare($sql_lecturer);
if ($stmt_lecturer) {
  $stmt_lecturer->bind_param("i", $lecturer_id);
  $stmt_lecturer->execute();
  $result_lecturer = $stmt_lecturer->get_result();
  if ($result_lecturer->num_rows == 1) {
    $lecturer_data = $result_lecturer->fetch_assoc();
    $lecturer_name = $lecturer_data['full_name'];
  } else {
    $_SESSION['error_message_page'] = "Data dosen tidak ditemukan.";
    header("Location: mahasiswa_dashboard.php");
    exit();
  }
  $stmt_lecturer->close();
} else {
  error_log("DB Error (prepare) - Fetching lecturer name in penilaian_dosen.php: " . $conn->error);
  $_SESSION['error_message_page'] = "Kesalahan database saat mengambil data dosen.";
  header("Location: mahasiswa_dashboard.php");
  exit();
}

// Pengecekan apakah mahasiswa sudah menilai dosen ini PADA PERIODE AKTIF
$sql_check_eval = "SELECT evaluation_id FROM Evaluations 
                   WHERE student_user_id = ? AND lecturer_user_id = ?
                   AND semester_evaluasi = ? AND tahun_ajaran_evaluasi = ?";
$stmt_check_eval = $conn->prepare($sql_check_eval);
if ($stmt_check_eval) {
  $stmt_check_eval->bind_param("iiss", $loggedInUserId, $lecturer_id, $semester_evaluasi_aktif_form, $tahun_ajaran_evaluasi_aktif_form);
  $stmt_check_eval->execute();
  $result_check_eval = $stmt_check_eval->get_result();
  if ($result_check_eval->num_rows > 0) {
    $_SESSION['error_message_page'] = "Anda sudah memberikan penilaian untuk dosen " . htmlspecialchars($lecturer_name) . " pada periode " . htmlspecialchars($semester_evaluasi_aktif_form) . ".";
    header("Location: mahasiswa_dashboard.php");
    exit();
  }
  $stmt_check_eval->close();
} else {
  error_log("DB Error (prepare) - Checking existing evaluation in penilaian_dosen.php: " . $conn->error);
  $_SESSION['error_message_page'] = "Kesalahan database saat memeriksa evaluasi.";
  header("Location: mahasiswa_dashboard.php");
  exit();
}

// Definisikan nama kategori dan pertanyaan
$category_names = [
  'A' => 'Kompetensi Profesional',
  'B' => 'Kompetensi Personal',
  'C' => 'Kompetensi Sosial'
];

$questions_by_category = [
  'A' => [ // Kompetensi Profesional (12 Pertanyaan)
    "1. Menjelaskan silabus, buku acuan (referensi) dan aturan penilaian pada awal perkuliahan.",
    "2. Penguasaan materi kuliah.",
    "3. Menjelaskan/menerangkan materi kuliah.",
    "4. Penggunaan media ajar (laptop, LCD, proyektor, internet, dsb).",
    "5. Kemampuan membangkitkan minat/motivasi pada mahasiswa.",
    "6. Memberikan tanggapan atas pertanyaan mahasiswa.",
    "7. Memberikan contoh yang relevan atas materi yang diberikan.",
    "8. Kesesuaian antara materi dan silabus.",
    "9. Menyediakan bahan ajar (diktat, modul, handout, dsb).",
    "10. Memberikan tugas yang relevan dan bermanfaat.",
    "11. Memberikan umpan balik setiap tugas atau ujian.",
    "12. Menyediakan waktu di luar jam kuliah."
  ],
  'B' => [ // Kompetensi Personal (5 Pertanyaan)
    "1. Keadilan dalam memperlakukan mahasiswa.",
    "2. Menghargai pendapat mahasiswa.",
    "3. Kerapihan dalam berpakaian.",
    "4. Keteladanan dalam bersikap dan berperilaku.",
    "5. Kemampuan mengendalikan diri dalam berbagai situasi dan kondisi."
  ],
  'C' => [ // Kompetensi Sosial (3 Pertanyaan)
    "1. Kemampuan berkomunikasi dengan mahasiswa.",
    "2. Kemampuan bekerja sama dengan mahasiswa.",
    "3. Kepedulian terhadap kesulitan mahasiswa."
  ]
];
$total_questions_a = count($questions_by_category['A']);
$total_questions_b = count($questions_by_category['B']);
$total_questions_c = count($questions_by_category['C']);
$total_all_questions = $total_questions_a + $total_questions_b + $total_questions_c;


if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['lecturer_id_hidden']) && $_POST['lecturer_id_hidden'] == $lecturer_id) {

    // PENGECEKAN BATAS WAKTU SAAT SUBMIT (Re-check, sama seperti di atas)
    $tanggal_sekarang_post = new DateTime();
    $batas_waktu_obj_post = null;
    $periode_berakhir_saat_post = false;
    // Ambil ulang batas_akhir_penilaian_str_form karena state bisa berubah
    $stmt_settings_post = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'batas_akhir_penilaian'");
    $current_batas_akhir_post = "2099-12-31";
    if ($stmt_settings_post) {
      $stmt_settings_post->execute();
      $result_settings_post = $stmt_settings_post->get_result();
      if ($row_setting_post = $result_settings_post->fetch_assoc()) {
        $current_batas_akhir_post = $row_setting_post['setting_value'];
      }
      $stmt_settings_post->close();
    }

    if ($current_batas_akhir_post !== "2099-12-31" && $current_batas_akhir_post !== "Tanggal Belum Diatur") {
      try {
        $batas_waktu_obj_post = DateTime::createFromFormat('Y-m-d H:i:s', $current_batas_akhir_post . ' 23:59:59');
        if ($batas_waktu_obj_post && $tanggal_sekarang_post > $batas_waktu_obj_post) {
          $periode_berakhir_saat_post = true;
        }
      } catch (Exception $e) {
        error_log("Error DateTime saat POST batas_akhir_penilaian (form penilaian): " . $e->getMessage());
        $periode_berakhir_saat_post = true; /* Anggap berakhir jika ada error */
      }
    }

    if ($periode_berakhir_saat_post) {
      $error_message = "Periode penilaian telah berakhir saat Anda mencoba mengirimkan. Penilaian Anda tidak dapat diproses.";
    } else {
      $scores = [];
      $total_score_sum = 0;
      $all_questions_answered = true;
      $score_map = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1];

      // Initialize scores array
      for ($i = 1; $i <= $total_questions_a; $i++)
        $scores['q' . $i . '_score'] = null;
      for ($i = 1; $i <= $total_questions_b; $i++)
        $scores['qb' . $i . '_score'] = null;
      for ($i = 1; $i <= $total_questions_c; $i++)
        $scores['qc' . $i . '_score'] = null;

      for ($i = 1; $i <= $total_questions_a; $i++) {
        $post_key = 'pertanyaan_a_' . $i;
        if (isset($_POST[$post_key]) && array_key_exists($_POST[$post_key], $score_map)) {
          $scores['q' . $i . '_score'] = $score_map[$_POST[$post_key]];
          $total_score_sum += $scores['q' . $i . '_score'];
        } else {
          $all_questions_answered = false;
          break;
        }
      }
      if ($all_questions_answered) {
        for ($i = 1; $i <= $total_questions_b; $i++) {
          $post_key = 'pertanyaan_b_' . $i;
          if (isset($_POST[$post_key]) && array_key_exists($_POST[$post_key], $score_map)) {
            $scores['qb' . $i . '_score'] = $score_map[$_POST[$post_key]];
            $total_score_sum += $scores['qb' . $i . '_score'];
          } else {
            $all_questions_answered = false;
            break;
          }
        }
      }
      if ($all_questions_answered) {
        for ($i = 1; $i <= $total_questions_c; $i++) {
          $post_key = 'pertanyaan_c_' . $i;
          if (isset($_POST[$post_key]) && array_key_exists($_POST[$post_key], $score_map)) {
            $scores['qc' . $i . '_score'] = $score_map[$_POST[$post_key]];
            $total_score_sum += $scores['qc' . $i . '_score'];
          } else {
            $all_questions_answered = false;
            break;
          }
        }
      }
      $comment = isset($_POST['komentar']) ? trim($_POST['komentar']) : '';

      if (!$all_questions_answered) {
        $error_message = "Harap jawab semua (" . $total_all_questions . ") pertanyaan penilaian dengan memilih A, B, C, atau D.";
      } else {
        $submission_average = ($total_all_questions > 0) ? round($total_score_sum / $total_all_questions, 2) : 0.00;

        // $semester_evaluasi_aktif_form dan $tahun_ajaran_evaluasi_aktif_form sudah diambil di atas
        $sql_insert = "INSERT INTO Evaluations (
                            student_user_id, lecturer_user_id, 
                            semester_evaluasi, tahun_ajaran_evaluasi, 
                            q1_score, q2_score, q3_score, q4_score, q5_score, q6_score, q7_score, q8_score, q9_score, q10_score, q11_score, q12_score, 
                            qb1_score, qb2_score, qb3_score, qb4_score, qb5_score, 
                            qc1_score, qc2_score, qc3_score, 
                            submission_average, comment
                       ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // 26 placeholders

        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert) {
          // Tipe: i i s s i(x20) d s
          $type_string = "iiss" . str_repeat("i", 20) . "ds";

          $stmt_insert->bind_param(
            $type_string,
            $loggedInUserId,
            $lecturer_id,
            $semester_evaluasi_aktif_form,
            $tahun_ajaran_evaluasi_aktif_form,
            $scores['q1_score'],
            $scores['q2_score'],
            $scores['q3_score'],
            $scores['q4_score'],
            $scores['q5_score'],
            $scores['q6_score'],
            $scores['q7_score'],
            $scores['q8_score'],
            $scores['q9_score'],
            $scores['q10_score'],
            $scores['q11_score'],
            $scores['q12_score'],
            $scores['qb1_score'],
            $scores['qb2_score'],
            $scores['qb3_score'],
            $scores['qb4_score'],
            $scores['qb5_score'],
            $scores['qc1_score'],
            $scores['qc2_score'],
            $scores['qc3_score'],
            $submission_average,
            $comment
          );

          if ($stmt_insert->execute()) {
            $_SESSION['success_message'] = "Penilaian untuk " . htmlspecialchars($lecturer_name) . " berhasil dikirim.";
            header("Location: mahasiswa_dashboard.php");
            exit();
          } else {
            if ($conn->errno == 1062) { // Error duplicate entry untuk UNIQUE KEY
              $_SESSION['error_message_page'] = "Anda sudah pernah mengirim penilaian untuk dosen ini pada periode " . htmlspecialchars($semester_evaluasi_aktif_form) . ".";
              header("Location: mahasiswa_dashboard.php");
              exit();
            } else {
              error_log("DB Execute Error - Inserting evaluation in penilaian_dosen.php: " . $stmt_insert->error . " | Query: " . $sql_insert);
              $error_message = "Gagal menyimpan penilaian. Error: " . $stmt_insert->error;
            }
          }
          $stmt_insert->close();
        } else {
          error_log("DB Prepare Error - Inserting evaluation in penilaian_dosen.php: " . $conn->error);
          $error_message = "Kesalahan database (prepare): " . $conn->error;
        }
      }
    }
  } else {
    $error_message = "Terjadi kesalahan pada pengiriman form atau ID dosen tidak cocok.";
  }
}

$options = ['A' => 'Sangat Baik/Sangat Setuju', 'B' => 'Baik/Setuju', 'C' => 'Cukup/Ragu-ragu', 'D' => 'Kurang/Tidak Setuju'];
$current_page_php_penilaian = basename($_SERVER['PHP_SELF']);
$js_initial_sidebar_force_closed_penilaian = 'false';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Formulir Penilaian: <?php echo htmlspecialchars($lecturer_name); ?> - EVADOS</title>
  <link rel="icon" href="../logo.png" type="image/png" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script>
    var js_initial_sidebar_force_closed = <?php echo $js_initial_sidebar_force_closed_penilaian; ?>;
  </script>
</head>

<body>
  <div class="mahasiswa-layout">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-top-section">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
          <i class="fas fa-bars"></i>
        </button>
        <div class="sidebar-header">
          <h3 class="logo-text">EVADOS</h3>
        </div>
      </div>
      <nav class="sidebar-menu">
        <ul>
          <li><a href="mahasiswa_dashboard.php"><i class="fas fa-arrow-left"></i> <span class="menu-text">Kembali</span></a></li>
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
        <h1>Formulir Penilaian Dosen: <?php echo htmlspecialchars($lecturer_name); ?></h1>
      </header>

      <section class="content-card penilaian-container">
        <?php if (!empty($error_message)): ?>
          <p class="error-msg"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <?php
        if (isset($_SESSION['error_message_penilaian_redirect'])) {
          echo '<p class="error-msg">' . htmlspecialchars($_SESSION['error_message_penilaian_redirect']) . '</p>';
          unset($_SESSION['error_message_penilaian_redirect']);
        }
        ?>

        <form id="formPenilaianDosen" action="penilaian_dosen.php?dosen_id=<?php echo $lecturer_id; ?>" method="POST">
          <input type="hidden" name="lecturer_id_hidden" value="<?php echo $lecturer_id; ?>">

          <?php foreach ($questions_by_category as $category_letter => $category_questions): ?>
            <?php
            $descriptive_category_name = isset($category_names[$category_letter]) ? $category_names[$category_letter] : "Kategori " . $category_letter;
            ?>
            <h3
              style="margin-top: 20px; margin-bottom:10px; padding-bottom:5px; border-bottom: 1px solid var(--tertiary-color);">
              <?php echo htmlspecialchars($descriptive_category_name); ?>
            </h3>
            <ul class="pertanyaan-list">
              <?php foreach ($category_questions as $index => $question_text):
                $question_number_in_category = $index + 1;
                $input_name = '';
                $input_id_prefix = '';
                if ($category_letter == 'A') {
                  $input_name = 'pertanyaan_a_' . $question_number_in_category;
                  $input_id_prefix = 'pa' . $question_number_in_category;
                } elseif ($category_letter == 'B') {
                  $input_name = 'pertanyaan_b_' . $question_number_in_category;
                  $input_id_prefix = 'pb' . $question_number_in_category;
                } elseif ($category_letter == 'C') {
                  $input_name = 'pertanyaan_c_' . $question_number_in_category;
                  $input_id_prefix = 'pc' . $question_number_in_category;
                }
                ?>
                <li class="pertanyaan-item">
                  <p><?php echo htmlspecialchars($question_text); ?></p>
                  <div class="opsi-penilaian">
                    <?php foreach ($options as $option_value => $option_label): ?>
                      <input type="radio" id="<?php echo $input_id_prefix; ?>_<?php echo strtolower($option_value); ?>"
                        name="<?php echo $input_name; ?>" value="<?php echo $option_value; ?>" required>
                      <label for="<?php echo $input_id_prefix; ?>_<?php echo strtolower($option_value); ?>">
                        <?php echo $option_value . '. ' . htmlspecialchars($option_label); ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endforeach; ?>

          <div class="komentar-section">
            <h3 style="margin-top: 20px;">Komentar atau Saran Tambahan:</h3>
            <textarea name="komentar" placeholder="Tuliskan komentar atau saran Anda di sini..."></textarea>
          </div>

          <button type="submit" class="btn-kirim-penilaian">
            <i class="fas fa-paper-plane"></i> Kirim Penilaian
          </button>
        </form>
      </section>
    </main>
  </div>
  <script src="../js/script.js"></script>
</body>

</html>
<?php if (isset($conn))
  $conn->close(); ?>