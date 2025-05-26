-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 26, 2025 at 08:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `evados`
--

-- --------------------------------------------------------

--
-- Table structure for table `auth_users`
--

CREATE TABLE `auth_users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('mahasiswa','dosen','kajur','admin') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = tidak aktif, 1 = aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_users`
--

INSERT INTO `auth_users` (`user_id`, `username`, `email`, `password_hash`, `role`, `full_name`, `is_active`) VALUES
(1, 'k4mic2023', 'k4mic2023@gmail.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'mahasiswa', 'Wahyu Wahid Nugroho', 1),
(2, 'k4mid2023', 'k4mid2023@gmail.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'mahasiswa', 'Fakhri Irawan', 1),
(3, NULL, 'dosen.A@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Nurlaili Rahmi, M.Si.', 1),
(4, NULL, 'dosen.B@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Kurniati, M.Kom.', 1),
(5, NULL, 'dosen.C@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Egga Asoka, S.Si., M.M.S.I.', 1),
(6, NULL, 'kajur.mi@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'kajur', 'Sony Oktapriandi,S.Kom.,M.Kom', 1),
(7, 'wk4mic2023', 'wk4mic2023@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'mahasiswa', 'Mochamad Raechan Albani', 1),
(8, 'sk14mic2023', 'sk14mic2023@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'mahasiswa', 'Sherli Gusvita Risbar', 1),
(9, 'sk24mic2023', 'sk24mic2023@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'mahasiswa', 'Zaskia Putri Aulia', 1),
(10, 'bd14mic2023', 'bd14mic2023@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'mahasiswa', 'Putri Safira', 1),
(11, 'bd24mic2023', 'bd24mic2023@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'mahasiswa', 'Feni Febriyani', 1),
(12, 'sk4mid2023', 'sk4mid2023@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'mahasiswa', 'Nyayu Diah Khairunnisa', 1),
(13, 'bd4mid2023', 'bd4mid2023@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'mahasiswa', 'Destiarana Putri', 1),
(14, NULL, 'dosen.D@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Dr. Hetty Meileni, S.Kom., M.T.', 1),
(15, NULL, 'dosen.E@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Deri Darfin, S.Sos., M.Si.', 1),
(16, NULL, 'dosen.F@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Leni Novianti, S.Kom., M.Kom.', 1),
(17, NULL, 'dosen.G@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Ferizka Tiara Devani, M.T.I.', 1),
(18, NULL, 'dosen.H@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Andre Mariza Putra, S.Kom., M.Kom.', 1),
(19, NULL, 'dosen.I@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'M. Zulkarnain, S.E., M.Si.', 1),
(20, NULL, 'dosen.J@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'M. Arief Rahman, S.E., M.M.', 1),
(21, NULL, 'dosen.K@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Yulia Hapsari, M.Kom.', 1),
(22, NULL, 'dosen.L@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Aurantia Marina, S.P., M.M.', 1),
(23, NULL, 'dosen.M@example.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'dosen', 'Rika Sadariawati, S.Si., M.Si.', 1),
(25, NULL, 'admin@evados.com', '$2y$10$S0qjooIzpgIuc.YiM3vP4.ovhpGBk9VtT2bkknqvjfOlQSr8XtxAq', 'admin', 'Administrator Sistem', 1);

-- --------------------------------------------------------

--
-- Table structure for table `dosen`
--

CREATE TABLE `dosen` (
  `user_id` int(11) NOT NULL,
  `nidn` varchar(30) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `profile_photo_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dosen`
--

INSERT INTO `dosen` (`user_id`, `nidn`, `department`, `profile_photo_url`) VALUES
(3, '0011223301', 'Manajemen Informatika Polsri', NULL),
(4, '0011223302', 'Manajemen Informatika Polsri', NULL),
(5, '0011223303', 'Manajemen Informatika Polsri', NULL),
(14, '0011223304', 'Manajemen Informatika Polsri', NULL),
(15, '0011223305', 'Manajemen Informatika Polsri', NULL),
(16, '0011223306', 'Manajemen Informatika Polsri', NULL),
(17, '0011223307', 'Manajemen Informatika Polsri', NULL),
(18, '0011223308', 'Manajemen Informatika Polsri', NULL),
(19, '0011223309', 'Manajemen Informatika Polsri', NULL),
(20, '0011223310', 'Manajemen Informatika Polsri', NULL),
(21, '0011223311', 'Manajemen Informatika Polsri', NULL),
(22, '0011223312', 'Manajemen Informatika Polsri', NULL),
(23, '0011223313', 'Manajemen Informatika Polsri', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `evaluation_id` int(11) NOT NULL,
  `student_user_id` int(11) NOT NULL,
  `lecturer_user_id` int(11) NOT NULL,
  `q1_score` tinyint(4) DEFAULT NULL,
  `q2_score` tinyint(4) DEFAULT NULL,
  `q3_score` tinyint(4) DEFAULT NULL,
  `q4_score` tinyint(4) DEFAULT NULL,
  `q5_score` tinyint(4) DEFAULT NULL,
  `q6_score` tinyint(4) DEFAULT NULL,
  `q7_score` tinyint(4) DEFAULT NULL,
  `q8_score` tinyint(4) DEFAULT NULL,
  `q9_score` tinyint(4) DEFAULT NULL,
  `q10_score` tinyint(4) DEFAULT NULL,
  `q11_score` tinyint(4) DEFAULT NULL,
  `q12_score` tinyint(4) DEFAULT NULL,
  `qb1_score` tinyint(4) DEFAULT NULL,
  `qb2_score` tinyint(4) DEFAULT NULL,
  `qb3_score` tinyint(4) DEFAULT NULL,
  `qb4_score` tinyint(4) DEFAULT NULL,
  `qb5_score` tinyint(4) DEFAULT NULL,
  `qc1_score` tinyint(4) DEFAULT NULL,
  `qc2_score` tinyint(4) DEFAULT NULL,
  `qc3_score` tinyint(4) DEFAULT NULL,
  `submission_average` decimal(3,2) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `evaluation_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`evaluation_id`, `student_user_id`, `lecturer_user_id`, `q1_score`, `q2_score`, `q3_score`, `q4_score`, `q5_score`, `q6_score`, `q7_score`, `q8_score`, `q9_score`, `q10_score`, `q11_score`, `q12_score`, `qb1_score`, `qb2_score`, `qb3_score`, `qb4_score`, `qb5_score`, `qc1_score`, `qc2_score`, `qc3_score`, `submission_average`, `comment`, `evaluation_date`) VALUES
(1, 1, 5, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4.00, 'wow', '2025-05-26 06:35:11');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_mengajar`
--

CREATE TABLE `jadwal_mengajar` (
  `jadwal_id` int(11) NOT NULL,
  `dosen_user_id` int(11) NOT NULL,
  `mk_id` int(11) NOT NULL,
  `nama_kelas` varchar(20) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `tahun_ajaran` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jadwal_mengajar`
--

INSERT INTO `jadwal_mengajar` (`jadwal_id`, `dosen_user_id`, `mk_id`, `nama_kelas`, `semester`, `tahun_ajaran`) VALUES
(1, 3, 1, '4MIC', 'Genap 2024/2025', '2024/2025'),
(13, 3, 1, '4MID', 'Genap 2024/2025', '2024/2025'),
(2, 4, 2, '4MIC', 'Genap 2024/2025', '2024/2025'),
(14, 4, 2, '4MID', 'Genap 2024/2025', '2024/2025'),
(3, 4, 3, '4MIC', 'Genap 2024/2025', '2024/2025'),
(15, 4, 3, '4MID', 'Genap 2024/2025', '2024/2025'),
(4, 5, 4, '4MIC', 'Genap 2024/2025', '2024/2025'),
(5, 14, 5, '4MIC', 'Genap 2024/2025', '2024/2025'),
(6, 15, 6, '4MIC', 'Genap 2024/2025', '2024/2025'),
(18, 15, 6, '4MID', 'Genap 2024/2025', '2024/2025'),
(7, 16, 7, '4MIC', 'Genap 2024/2025', '2024/2025'),
(19, 16, 7, '4MID', 'Genap 2024/2025', '2024/2025'),
(8, 16, 8, '4MIC', 'Genap 2024/2025', '2024/2025'),
(20, 16, 8, '4MID', 'Genap 2024/2025', '2024/2025'),
(16, 17, 4, '4MID', 'Genap 2024/2025', '2024/2025'),
(9, 17, 9, '4MIC', 'Genap 2024/2025', '2024/2025'),
(10, 18, 10, '4MIC', 'Genap 2024/2025', '2024/2025'),
(22, 18, 10, '4MID', 'Genap 2024/2025', '2024/2025'),
(11, 19, 11, '4MIC', 'Genap 2024/2025', '2024/2025'),
(12, 20, 12, '4MIC', 'Genap 2024/2025', '2024/2025'),
(17, 21, 5, '4MID', 'Genap 2024/2025', '2024/2025'),
(21, 22, 9, '4MID', 'Genap 2024/2025', '2024/2025'),
(23, 23, 11, '4MID', 'Genap 2024/2025', '2024/2025'),
(24, 23, 12, '4MID', 'Genap 2024/2025', '2024/2025');

-- --------------------------------------------------------

--
-- Table structure for table `kajur`
--

CREATE TABLE `kajur` (
  `user_id` int(11) NOT NULL,
  `department_managed` varchar(100) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kajur`
--

INSERT INTO `kajur` (`user_id`, `department_managed`, `start_date`, `end_date`) VALUES
(6, 'Manajemen Informatika Polsri', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `user_id` int(11) NOT NULL,
  `npm` varchar(30) NOT NULL,
  `angkatan` varchar(4) DEFAULT NULL,
  `jabatan_kelas` varchar(50) DEFAULT NULL,
  `kelas` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mahasiswa`
--

INSERT INTO `mahasiswa` (`user_id`, `npm`, `angkatan`, `jabatan_kelas`, `kelas`) VALUES
(1, '062340833133', '2023', 'Ketua Kelas', '4MIC'),
(2, '11122002', '2023', 'Ketua Kelas', '4MID'),
(7, '062340833121', '2023', 'Wakil Ketua Kelas', '4MIC'),
(8, '062340833131', '2023', 'Sekretaris', '4MIC'),
(9, '062340833134', '2023', 'Wakil Sekretaris', '4MIC'),
(10, '062340833128', '2023', 'Bendahara', '4MIC'),
(11, '062340833116', '2023', 'Wakil Bendahara', '4MIC'),
(12, '062340833156', '2023', 'Sekretaris', '4MID'),
(13, '062340833142', '2023', 'Bendahara', '4MID');

-- --------------------------------------------------------

--
-- Table structure for table `mata_kuliah`
--

CREATE TABLE `mata_kuliah` (
  `mk_id` int(11) NOT NULL,
  `kode_mk` varchar(20) NOT NULL,
  `nama_mk` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mata_kuliah`
--

INSERT INTO `mata_kuliah` (`mk_id`, `kode_mk`, `nama_mk`) VALUES
(1, 'ME401', 'Matematika Ekonomi'),
(2, 'BD402', 'Basis Data II'),
(3, 'PBD403', 'Praktikum Basis Data II'),
(4, 'SIM404', 'Sistem Informasi Manajemen'),
(5, 'APBO405', 'Analisis Perancangan Berorientasi Objek'),
(6, 'PK406', 'Pendidikan Kewarganegaraan'),
(7, 'SIG407', 'Sistem Informasi Geografis'),
(8, 'PSIG408', 'Praktikum Sistem Informasi Geografis'),
(9, 'KW409', 'Kewirausahaan'),
(10, 'DW410', 'Praktikum Desain dan Pemrograman Web'),
(11, 'AK401', 'Akuntansi'),
(12, 'PAK412', 'Praktikum Akuntansi');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `receiver_user_id` int(11) NOT NULL,
  `message_type` enum('pesan','peringatan','panggilan') NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_user_id`, `receiver_user_id`, `message_type`, `subject`, `content`, `sent_at`, `is_read`, `read_at`) VALUES
(1, 6, 5, 'pesan', 'a', 'b', '2025-05-26 06:38:00', 1, '2025-05-26 06:38:33');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`, `last_updated`) VALUES
('batas_akhir_penilaian', '2025-09-20', 'Batas akhir periode penilaian (format YYYY-MM-DD).', '2025-05-26 13:33:52'),
('semester_aktif', 'Genap 2024/2025', 'Semester aktif yang ditampilkan di sistem evaluasi.', '2025-05-26 14:23:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `auth_users`
--
ALTER TABLE `auth_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `nidn` (`nidn`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`evaluation_id`),
  ADD UNIQUE KEY `unique_evaluation` (`student_user_id`,`lecturer_user_id`),
  ADD KEY `lecturer_user_id` (`lecturer_user_id`);

--
-- Indexes for table `jadwal_mengajar`
--
ALTER TABLE `jadwal_mengajar`
  ADD PRIMARY KEY (`jadwal_id`),
  ADD UNIQUE KEY `unique_slot` (`dosen_user_id`,`mk_id`,`nama_kelas`,`semester`,`tahun_ajaran`),
  ADD KEY `mk_id` (`mk_id`);

--
-- Indexes for table `kajur`
--
ALTER TABLE `kajur`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `npm` (`npm`);

--
-- Indexes for table `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  ADD PRIMARY KEY (`mk_id`),
  ADD UNIQUE KEY `kode_mk` (`kode_mk`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_user_id` (`sender_user_id`),
  ADD KEY `receiver_user_id` (`receiver_user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `auth_users`
--
ALTER TABLE `auth_users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `evaluation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jadwal_mengajar`
--
ALTER TABLE `jadwal_mengajar`
  MODIFY `jadwal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  MODIFY `mk_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dosen`
--
ALTER TABLE `dosen`
  ADD CONSTRAINT `dosen_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`student_user_id`) REFERENCES `auth_users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`lecturer_user_id`) REFERENCES `auth_users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `jadwal_mengajar`
--
ALTER TABLE `jadwal_mengajar`
  ADD CONSTRAINT `jadwal_mengajar_ibfk_1` FOREIGN KEY (`dosen_user_id`) REFERENCES `auth_users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_mengajar_ibfk_2` FOREIGN KEY (`mk_id`) REFERENCES `mata_kuliah` (`mk_id`) ON DELETE CASCADE;

--
-- Constraints for table `kajur`
--
ALTER TABLE `kajur`
  ADD CONSTRAINT `kajur_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD CONSTRAINT `mahasiswa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_user_id`) REFERENCES `auth_users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_user_id`) REFERENCES `auth_users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
