# Sistem Absensi KKN Tematik Unper

Sistem Absensi KKN Tematik Universitas Perjuangan (Unper) adalah aplikasi web berbasis PHP yang dirancang untuk mengelola dan memantau kehadiran mahasiswa selama kegiatan Kuliah Kerja Nyata (KKN) Tematik. Aplikasi ini memungkinkan mahasiswa untuk melakukan absensi masuk dan pulang, serta menyediakan dashboard admin untuk verifikasi, pengelolaan data, dan pelaporan.

## Fitur Utama

### Untuk Mahasiswa (Publik):
* **Absensi Masuk & Pulang:** Mahasiswa dapat mencatatkan waktu masuk dan pulang.
* **Unggah Foto:** Terdapat fitur untuk mengunggah foto saat melakukan absensi (wajib saat absen pulang, opsional saat absen masuk).
* **Pencatatan Lokasi:** Sistem mencatat koordinat geografis (latitude, longitude) saat absensi (dengan penanganan khusus untuk perangkat iOS yang mungkin tidak selalu menyediakan lokasi).
* **Catatan Tambahan:** Mahasiswa dapat menambahkan catatan saat absensi.
* **Lihat Laporan Pribadi:** Mahasiswa dapat melihat rekapitulasi absensi pribadi berdasarkan NIM dan rentang tanggal.
* **Export Laporan ke PDF:** Mahasiswa dapat mengunduh laporan absensi pribadi dalam format PDF (dihasilkan di sisi klien menggunakan jsPDF).

### Untuk Administrator:
* **Login Admin:** Akses aman ke panel admin.
* **Dashboard Admin:**
    * Ringkasan dan statistik absensi.
    * Filter data absensi berdasarkan rentang tanggal dan status verifikasi.
* **Verifikasi Absensi:** Admin dapat memverifikasi data absensi yang masuk.
* **Manajemen Data Absensi:**
    * Edit detail absensi mahasiswa.
    * Hapus data absensi.
* **Pelaporan (Admin):**
    * Export daftar absensi yang difilter ke PDF (menggunakan placeholder TCPDF, perlu implementasi TCPDF sebenarnya).
* **Galeri Foto Absensi:**
    * Melihat foto-foto yang diunggah mahasiswa saat absensi.
    * Filter foto berdasarkan rentang tanggal, nama, atau NIM.
    * Hapus foto/data absensi terkait.
* **Peta Lokasi Absensi:**
    * Visualisasi lokasi absensi mahasiswa (masuk dan pulang) pada peta (LeafletJS).
    * Filter data pada peta berdasarkan tanggal dan NIM.
* **Manajemen Pengguna (Dasar):**
    * Melihat daftar pengguna (non-admin).
    * Menghapus pengguna (non-admin).
    * (Pendaftaran pengguna umum dengan domain email `@unper.ac.id` tersedia melalui `register.php`, meskipun tidak ditautkan langsung dari halaman login).
* **Setup Awal:**
    * Pembuatan tabel database otomatis jika belum ada.
    * Pembuatan akun admin default (`admin`/`admin`) jika belum ada.

## Teknologi yang Digunakan

* **Backend:** PHP
* **Database:** MySQL / MariaDB (sesuai `absensi.sql` yang menggunakan MariaDB)
* **Frontend:** HTML, CSS, JavaScript
* **Styling & UI:**
    * Bootstrap 5 (digunakan di beberapa halaman admin)
    * Font Awesome (untuk ikon)
    * Poppins Font
* **Pustaka JavaScript:**
    * LeafletJS (untuk peta interaktif)
    * Flatpickr (untuk pemilihan tanggal)
    * jsPDF & jsPDF-AutoTable (untuk pembuatan PDF di sisi klien pada laporan publik)
* **PDF Generation (Server-side):** TCPDF (saat ini menggunakan file placeholder, memerlukan instalasi library sebenarnya).

## Prasyarat

* Web Server (Apache, Nginx, atau sejenisnya) dengan dukungan PHP.
* PHP versi 7.2 atau lebih tinggi (disarankan versi terbaru yang stabil).
* Database Server (MySQL 5.7+ atau MariaDB 10.2+).
* Ekstensi PHP: `mysqli`, `gd` (untuk pemrosesan gambar jika ada manipulasi), `json`.
* Browser web modern (Chrome, Firefox, Safari, Edge).

## Struktur Folder & File Utama

absensi/
├── absensi.sql                     # File dump skema dan data database awal
├── index.php                       # Halaman utama publik untuk absensi & navigasi
├── README.md                       # File README ini
└── src/
└── php-backend/
├── admin_attendance.php        # (Kemungkinan fitur verifikasi admin atau laporan, mungkin tumpang tindih dengan dashboard)
├── attendance_map.php          # Halaman peta lokasi absensi (admin)
├── attendance_public.php       # Logika untuk absensi masuk/pulang publik
├── attendance_report.php       # Halaman laporan absensi publik per NIM
├── config.php                  # Konfigurasi database dan koneksi, setup admin awal
├── dashboard.php               # Dashboard utama admin (verifikasi, edit, delete absensi)
├── delete-user.php             # Skrip untuk menghapus pengguna (admin)
├── get-users.php               # Skrip untuk mendapatkan daftar pengguna (admin)
├── login.php                   # Halaman login admin
├── logout.php                  # Skrip untuk logout admin
├── photos.php                  # Galeri foto absensi (admin)
├── register.php                # Halaman pendaftaran pengguna (dengan validasi email @unper.ac.id)
├── setup.php                   # Skrip untuk setup tabel 'attendance' (tabel lain dibuat di config/attendance_public)
├── uploads/                    # (Direktori ini akan dibuat oleh attendance_public.php untuk foto unggahan)
└── assets/
├── css/
│   └── style.css           # CSS kustom (beberapa halaman menggunakannya)
└── vendor/
└── tcpdf/
└── tcpdf.php       # File placeholder untuk library TCPDF


## Instalasi dan Setup

1.  **Clone atau Unduh Proyek:**
    Letakkan semua file proyek ke direktori root web server Anda (misalnya, `htdocs/absensi` untuk Apache atau `www/absensi` untuk Nginx).

2.  **Konfigurasi Web Server:**
    Pastikan web server Anda dikonfigurasi untuk menjalankan file PHP. Untuk Apache, pastikan `mod_rewrite` diaktifkan jika Anda berencana menggunakan URL yang lebih bersih di masa mendatang.

3.  **Setup Database:**
    * Buat database baru di server MySQL/MariaDB Anda (misalnya, bernama `if0_37990694_absensi` sesuai `absensi.sql`).
    * Impor file `absensi.sql` ke dalam database yang baru saja Anda buat. Ini akan membuat tabel-tabel yang diperlukan (`users`, `attendance`, `public_attendance`).
    * **Penting:** Perbarui detail koneksi database dalam file `src/php-backend/config.php`:
        ```php
        define('DB_HOST', 'your_db_host'); // e.g., 'localhost' or 'sql113.infinityfree.com'
        define('DB_USER', 'your_db_user'); // e.g., 'root' or 'if0_37990694'
        define('DB_PASS', 'your_db_password');
        define('DB_NAME', 'your_db_name'); // e.g., 'if0_37990694_absensi'
        ```

4.  **Setup Awal (Otomatis):**
    * Saat pertama kali file `src/php-backend/config.php` diakses (misalnya, saat membuka halaman login), skrip akan mencoba membuat tabel `users` jika belum ada dan membuat akun admin default (`username: admin`, `password: admin`).
    * Saat file `src/php-backend/attendance_public.php` diakses, skrip akan mencoba membuat tabel `public_attendance` jika belum ada.
    * Anda juga dapat menjalankan `src/php-backend/setup.php` sekali melalui browser Anda (`http://localhost/absensi/src/php-backend/setup.php`) untuk memastikan tabel `attendance` (meskipun tabel ini tampaknya tidak banyak digunakan dibandingkan `public_attendance`) juga dibuat. Namun, mengimpor `absensi.sql` adalah cara yang lebih komprehensif.

5.  **Hak Akses Direktori:**
    Pastikan direktori `src/php-backend/uploads/` dapat ditulis oleh server web agar foto absensi dapat diunggah. Jika direktori ini belum ada, skrip di `attendance_public.php` akan mencoba membuatnya.

6.  **Akses Aplikasi:**
    * Buka aplikasi melalui browser Anda: `http://localhost/absensi/` (atau alamat yang sesuai dengan konfigurasi server Anda).
    * Untuk login sebagai admin, navigasikan ke `http://localhost/absensi/src/php-backend/login.php` dan gunakan kredensial default (`admin`/`admin`) jika ini adalah setup pertama kali.

7.  **(Opsional) Instal TCPDF:**
    File `src/php-backend/assets/vendor/tcpdf/tcpdf.php` saat ini adalah placeholder. Untuk fungsionalitas penuh ekspor PDF dari sisi server (seperti di `dashboard.php` atau `admin_attendance.php`), Anda perlu menginstal library TCPDF yang sebenarnya. Cara termudah adalah menggunakan Composer:
    ```bash
    composer require tecnickcom/tcpdf
    ```
    Kemudian, sesuaikan `require_once` di file PHP yang menggunakan TCPDF untuk menunjuk ke file autoload Composer atau file utama TCPDF.

## Penggunaan

### Mahasiswa:
1.  Buka halaman utama (`index.php`).
2.  Pilih "Absen Masuk" atau "Absen Pulang".
3.  Isi formulir dengan nama lengkap, NIM, program studi.
4.  Unggah foto jika diminta/diperlukan.
5.  Sistem akan mencoba mendapatkan lokasi Anda.
6.  Klik "Submit".
7.  Untuk melihat laporan, klik "Lihat Laporan", masukkan NIM dan rentang tanggal.

### Admin:
1.  Buka halaman login admin (`src/php-backend/login.php`).
2.  Masukkan username dan password admin.
3.  Setelah login, Anda akan diarahkan ke dashboard admin.
4.  Gunakan menu navigasi untuk mengakses fitur verifikasi, laporan, galeri foto, peta lokasi, dan manajemen pengguna.

## Catatan Penting

* **Keamanan:** Password admin default (`admin`/`admin`) harus segera diubah setelah login pertama. Pertimbangkan untuk menambahkan fitur ubah password di panel admin. Implementasi keamanan tambahan seperti validasi input yang lebih ketat, proteksi terhadap XSS dan CSRF, serta penggunaan prepared statements secara konsisten sangat disarankan.
* **Placeholder TCPDF:** Fitur ekspor PDF dari sisi server di panel admin tidak akan berfungsi sepenuhnya sampai library TCPDF yang asli diinstal dan diintegrasikan.
* **Error Handling:** Tingkatkan mekanisme pelaporan dan penanganan error untuk pengalaman pengguna yang lebih baik dan debugging yang lebih mudah.

---

Semoga README ini membantu!