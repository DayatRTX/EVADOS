# Evados - Sistem Evaluasi Dosen Online

Evados adalah aplikasi web berbasis PHP yang dirancang untuk memfasilitasi proses evaluasi dosen oleh mahasiswa. Sistem ini juga menyediakan dasbor dan fungsionalitas manajemen untuk berbagai peran pengguna seperti Dosen, Ketua Jurusan (Kajur), dan Administrator.

## Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Peran Pengguna](#peran-pengguna)
- [Struktur Direktori](#struktur-direktori)
- [Persyaratan Sistem](#persyaratan-sistem)
- [Instalasi](#instalasi)
  - [Langkah-langkah Setup](#langkah-langkah-setup)
  - [Konfigurasi Database](#konfigurasi-database)
- [Mengakses Aplikasi](#mengakses-aplikasi)
  - [Kredensial Default (Contoh)](#kredensial-default-contoh)
- [Struktur Database](#struktur-database)
- [Kontributor Tim](#kontributor-tim)

## Fitur Utama

* **Evaluasi Dosen:** Mahasiswa dapat memberikan penilaian terhadap dosen yang mengajar mereka berdasarkan berbagai aspek kompetensi.
* **Manajemen Pengguna:** Admin dapat mengelola semua akun pengguna (Mahasiswa, Dosen, Kajur, Admin lain).
* **Manajemen Data Akademik:** Admin dapat mengelola data Dosen, Mahasiswa, Mata Kuliah, dan Jadwal Mengajar.
* **Dasbor Analitik:**
    * **Mahasiswa:** Melihat daftar dosen yang perlu dievaluasi dan progres penilaian.
    * **Dosen:** Melihat ringkasan hasil evaluasi diri, rata-rata skor per aspek, dan komentar anonim dari mahasiswa.
    * **Kajur:** Memantau hasil evaluasi dosen di jurusannya, rata-rata skor jurusan, dan mengirim pesan/notifikasi ke dosen.
    * **Admin:** Melihat statistik keseluruhan sistem (jumlah pengguna, evaluasi masuk, dll.) dan informasi sistem.
* **Pengaturan Sistem:** Admin dapat mengatur parameter penting seperti semester aktif dan batas akhir periode penilaian.
* **Komunikasi:** Kajur dapat mengirim pesan/peringatan/panggilan ke dosen di jurusannya. Dosen menerima notifikasi ini.
* **Antarmuka Responsif:** Dirancang agar dapat diakses dengan baik di berbagai perangkat.

## Peran Pengguna

1.  **Mahasiswa (Perangkat Kelas):**
    * Melakukan penilaian terhadap dosen yang mengajar di kelasnya.
    * Melihat daftar dosen dan status penilaiannya.
2.  **Dosen:**
    * Melihat ringkasan hasil evaluasi yang diterima.
    * Melihat rata-rata skor per aspek penilaian.
    * Membaca komentar anonim dari mahasiswa.
    * Menerima notifikasi dari Ketua Jurusan.
3.  **Ketua Jurusan (Kajur):**
    * Melihat statistik evaluasi dosen di tingkat jurusan.
    * Melihat detail evaluasi per dosen di jurusannya.
    * Mengirim pesan/notifikasi ke dosen di jurusannya.
    * Melihat riwayat pesan yang dikirim.
4.  **Administrator:**
    * Mengelola data master (Pengguna, Dosen, Mahasiswa, Mata Kuliah, Jadwal).
    * Mengonfigurasi pengaturan sistem (semester aktif, batas penilaian).
    * Melihat statistik penggunaan sistem secara keseluruhan.
    * Akses penuh ke semua fitur manajemen.

## Persyaratan Sistem

* Web Server (Apache direkomendasikan, bagian dari XAMPP/WAMP)
* PHP 7.4 atau lebih tinggi (proyek ini dikembangkan dengan PHP 8.2.12 menurut `evados.sql`)
* MySQL atau MariaDB
* Browser Web Modern (Chrome, Firefox, Edge, Safari)

## Instalasi

### Langkah-langkah Setup

1.  **Clone atau Unduh Repositori:**
    ```bash
    git clone [https://github.com/username/evados.git](https://github.com/username/evados.git)
    ```
    Atau unduh file ZIP dan ekstrak.

2.  **Pindahkan ke Direktori Web Server:**
    Pindahkan folder `evados` ke direktori root web server Anda (misalnya, `htdocs/` untuk XAMPP, `www/` untuk WAMP).

3.  **Buat Database:**
    * Buka phpMyAdmin atau alat manajemen database lainnya.
    * Buat database baru dengan nama `evados`.

4.  **Impor Database:**
    * Pilih database `evados` yang baru saja dibuat.
    * Impor file `database/evados.sql` ke dalam database tersebut. Ini akan membuat semua tabel yang diperlukan dan mengisi beberapa data contoh.

5.  **Konfigurasi Koneksi Database:**
    * Buka file `config/db.php`.
    * Pastikan detail koneksi database (server, username, password, nama database) sesuai dengan pengaturan lingkungan Anda:
        ```php
        define('DB_SERVER', 'localhost'); //
        define('DB_USERNAME', 'root'); // Ganti jika username database Anda berbeda
        define('DB_PASSWORD', '');      // Ganti jika password database Anda berbeda
        define('DB_NAME', 'evados'); //
        ```
    * Secara default, konfigurasi di atas cocok untuk setup XAMPP standar.

### Konfigurasi Database

File konfigurasi utama untuk database adalah `config/db.php`. Pastikan pengaturan `DB_SERVER`, `DB_USERNAME`, `DB_PASSWORD`, dan `DB_NAME` sudah benar.

## Mengakses Aplikasi

1.  Pastikan web server (misalnya Apache dari XAMPP) dan MySQL sudah berjalan.
2.  Buka browser Anda dan arahkan ke:
    `http://localhost/evados/`
    atau
    `http://localhost/nama_folder_proyek_anda/` jika Anda mengganti nama folder `evados`.

    Anda akan diarahkan ke halaman `login.php`.

### Kredensial Default (Contoh dari `evados.sql`)

Beberapa akun contoh telah disertakan dalam file `evados.sql`. Umumnya, password untuk akun contoh adalah `password123` (hash `$2y$10$dvV0WcJn8e0dJLtl9nn/pue2gzTTJdXDpQcdWKlQ/VteMGEfdYW8i`).

* **Mahasiswa (Ketua Kelas 4MIC):**
    * Username: `k4mic2023`
    * Password: `password123`
* **Mahasiswa (Ketua Kelas 4MID):**
    * Username: `k4mid2023`
    * Password: `password123`
* **Dosen (Contoh):**
    * Email: `nurlaili@contoh.com`
    * Password: `password123`
* **Kajur (Contoh):**
    * Email: `kajur.mi@example.com`
    * Password: `password123`
* **Admin:**
    * Email: `admin@evados.com`
    * Password: `password123`

## Struktur Database

Berikut adalah tabel utama dalam database `evados`:

* `auth_users`: Menyimpan informasi login dan detail dasar semua pengguna.
* `dosen`: Detail spesifik untuk dosen.
* `mahasiswa`: Detail spesifik untuk mahasiswa.
* `kajur`: Detail spesifik untuk Ketua Jurusan.
* `mata_kuliah`: Daftar mata kuliah.
* `jadwal_mengajar`: Menghubungkan dosen, mata kuliah, dan kelas.
* `evaluations`: Menyimpan semua data hasil penilaian.
* `messages`: Menyimpan pesan/notifikasi.
* `system_settings`: Menyimpan pengaturan global sistem.

## Kontributor Tim

Proyek ini dikembangkan oleh:

1.  Adellia Nurain - `062340833108`
2.  Ahmad Farhan Hidayatullah - `062340833109`
3.  Andhika Pratama - `062340833112`
4.  M. Rayshad Naufal Putra - `062340833123`
5.  Rika Anggraini - `062340833130`
6.  Sherli Gusvita Risbar - `062340833131`

---

Semoga README ini bermanfaat!
