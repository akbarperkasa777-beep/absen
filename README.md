# Portal Presensi Karyawan — Premium Workforce Management & WhatsApp Gateway

Aplikasi enterprise berbasis web modern yang dibangun menggunakan **PHP Native 8.x**, **MySQL / MariaDB (PDO)**, **Bootstrap 5.3**, **Bootstrap Icons 1.11**, **Chart.js 4.4**, dan integrasi **WhatsApp Gateway Fonnte REST API**. Proyek ini menerapkan konsep visual **Premium Workforce Management Dashboard** dan menggunakan variabel lingkungan **`.env`** melalui library **`vlucas/phpdotenv`** via Composer untuk proteksi kredensial.

---

## 1. DAFTAR TEKNOLOGI & DEPENDENSI

- **Backend:** PHP Native 8.2+ (tanpa framework Laravel/CodeIgniter).
- **Environment Manager:** `vlucas/phpdotenv` (Composer).
- **Database:** MySQL / MariaDB (Driver: PDO dengan Prepared Statements).
- **Web Server:** Apache via XAMPP (dilengkapi proteksi `.htaccess`).
- **Frontend Framework:** HTML5, CSS3, JavaScript ES6, Bootstrap 5.3.3 (CDN).
- **Ikon Antarmuka:** Bootstrap Icons 1.11.3 (CDN).
- **Tipografi:** Google Fonts (Inter & JetBrains Mono).
- **Visualisasi Data:** Chart.js 4.4.2 (CDN).
- **Integrasi Web API:** Fonnte WhatsApp Gateway API (`https://api.fonnte.com/send`).
- **Zona Waktu Server:** `Asia/Jakarta` (WIB).

---

## 2. FITUR UTAMA SISTEM

### A. Fitur Khusus Karyawan
1. **Dashboard Karyawan:** Sapaan waktu dinamis, status presensi hari ini, metrik kehadiran bulan ini, persentase kehadiran faktual, jam server realtime, dan tombol aksi cepat.
2. **Presensi Mandiri:** Absensi masuk dengan status tepat waktu/terlambat dan menit keterlambatan, serta absensi pulang setelah batas jadwal atau izin awal yang disetujui admin.
3. **Izin Pulang Lebih Awal:** Pengajuan karyawan, keputusan admin dengan alasan penolakan, status pada dashboard, dan notifikasi WhatsApp setelah perubahan tersimpan.
4. **Riwayat Presensi Saya:** Filter rentang tanggal, filter bulan, filter tahun, filter status presensi, pagination, modal rincian presensi, dan ekspor data pribadi ke format CSV.
5. **Kalender Kehadiran:** Matriks kalender bulanan visual yang menandai kehadiran kerja harian dan jam kerja.
6. **Profil & Keamanan Akun:**
   - Melihat data profil kepegawaian (NIK, Nama, Jabatan, Email, No WA, Tanggal Terdaftar).
   - **Ganti Email:** Validasi format, pengecekan keunikan, verifikasi password saat ini, dan notifikasi WhatsApp perubahan email.
   - **Ganti Password:** Verifikasi password lama via `password_verify()`, hashing `password_hash()`, regenerasi session ID, dan show/hide toggle.

### B. Fitur Administrator
1. **Advanced Admin Dashboard:** Statistik kehadiran dan keterlambatan, grafik tren kehadiran 7 hari terakhir (Chart.js), status gateway Fonnte, dan daftar presensi hari ini.
2. **Manajemen Master Karyawan:** Pencarian multi-kolom (nama, NIK, email, no HP), filter jabatan, filter status akun (aktif/nonaktif), sorting, pagination, dan modal konfirmasi hapus.
3. **Detail Karyawan:** Profil lengkap, rekapitulasi kehadiran bulan ini, riwayat presensi, dan tombol aktivasi/nonaktivasi akun.
4. **Data & Koreksi Presensi:** Monitoring presensi harian seluruh karyawan dan fasilitas koreksi resmi oleh admin (mencatat alasan koreksi, waktu koreksi, dan nama admin).
5. **Persetujuan Izin Pulang Awal:** Daftar pengajuan, filter, badge status, alasan keputusan, dan persetujuan/penolakan ber-CSRF.
6. **Laporan Rekapitulasi Presensi:** Filter multi-parameter (tanggal, bulan, tahun, karyawan, jabatan, status), ringkasan keterlambatan, ekspor Excel (CSV), dan cetak laporan.
7. **Integrasi WhatsApp Gateway Fonnte:** Monitoring konfigurasi `.env`, kartu audit pengiriman, dan formulir pengujian kirim pesan WhatsApp langsung ke gateway.
8. **Log Aktivitas (Audit Trail):** Pencatatan kronologis seluruh aksi penting sistem (Login, Logout, Register, Absensi, Koreksi, Ganti Email, Ganti Password, Status Akun) beserta alamat IP.

---

## 3. PANDUAN INSTALASI & SETUP DENGAN FILE `.env`

### Langkah 1: Kebutuhan Sistem
- XAMPP dengan Apache dan MySQL aktif.
- PHP versi 8.0 atau lebih tinggi dengan ekstensi `pdo_mysql`, `curl`, dan `zip` aktif.
- Composer terpasang di komputer.

### Langkah 2: Instalasi Dependensi Composer
Buka terminal / Command Prompt di folder proyek `c:\xamppp\htdocs\karyawan-wa`:
```bash
composer install
```
*(atau `composer require vlucas/phpdotenv` jika menginisialisasi ulang).*

### Langkah 3: Konfigurasi File `.env`
Salin template konfigurasi:
```bash
copy .env.example .env
```
Buka file `.env` dan sesuaikan parameter berikut:
```dotenv
APP_NAME="Portal Presensi Karyawan"
APP_ENV=local
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta
APP_URL=http://localhost/karyawan-wa
ATTENDANCE_START_TIME=07:00
EARLIEST_CHECKOUT_TIME=12:00

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_karyawan
DB_USERNAME=root
DB_PASSWORD=

FONNTE_TOKEN=ISI_TOKEN_FONNTE_ANDA
FONNTE_ADMIN_NUMBER=6281234567890
DEMO_LOGIN_ENABLED=false
```

Jam masuk dan batas paling awal absen pulang dibaca terpusat dari `.env`. Nilai default masing-masing adalah `07:00` dan `12:00` (WIB). Absen masuk sebelum jadwal diizinkan dan tetap dihitung tepat waktu.

### Penerapan aturan presensi dan migrasi database

Untuk database yang sudah berisi data, impor sekali file `database_migrations/20261022_attendance_schedule_early_leave.sql` melalui phpMyAdmin atau MySQL CLI setelah memilih database `db_karyawan`. Migrasi bersifat idempoten, menambahkan tabel pengajuan dan kolom presensi tanpa menghapus riwayat, serta mengisi status keterlambatan historis berdasarkan jadwal default 07.00. Untuk instalasi baru, impor `database.sql` yang sudah menyertakan struktur terbaru.

Absen pulang biasa ditolak oleh backend sebelum batas jam di `.env`, walaupun tombol pada browser dimanipulasi. Sebelum batas itu, karyawan yang sudah absen masuk dapat mengajukan izin untuk hari yang sama dengan waktu pulang yang masih akan datang dan lebih awal dari batas normal. Admin memutuskan pengajuan melalui menu **Persetujuan Izin Pulang**. Pengajuan pending/ditolak tidak memberi akses untuk pulang lebih awal; pengajuan yang disetujui baru dapat digunakan pada atau setelah waktu yang diminta. Jam pulang aktual tetap berasal dari waktu server.

> **Keamanan Kredensial:**
> Seluruh kredensial database dan token Fonnte dibaca melalui `Dotenv`. File `.env` telah dilindungi dari akses publik browser melalui file `.htaccess` (HTTP 403 Forbidden) dan dikecualikan dari repository publik melalui `.gitignore`.

### Akun demo (opsional)

Login demo dinonaktifkan secara default. Untuk presentasi atau pengujian lokal:

1. Atur `DEMO_LOGIN_ENABLED=true` pada file `.env` yang tidak dipublikasikan.
2. Dari folder proyek, jalankan `php scripts\seed_demo_accounts.php`.
3. Buka halaman login. Tombol **Admin Demo** dan **Karyawan Demo** hanya muncul saat mode demo aktif.

Seeder idempoten: akun demo dibuat hanya jika belum tersedia, menggunakan identitas NIK/email demo tetap dan password hash acak yang tidak ditampilkan. Seeder juga menambahkan catatan contoh presensi hanya untuk Karyawan Demo. Jika ada identitas yang bertabrakan dengan akun lain, proses dihentikan tanpa mengubah akun tersebut.

Akun demo berstatus hanya-baca. Admin Demo hanya melihat dashboard, data Karyawan Demo, presensi demo, dan laporan demo; Karyawan Demo dapat melihat dashboard, profil, kalender, serta riwayatnya. Perubahan master data, koreksi, presensi, email, dan password diblokir di server. Nonaktifkan kembali dengan `DEMO_LOGIN_ENABLED=false` setelah presentasi. Akun demo yang sudah dibuat tidak otomatis dihapus.

### Notifikasi WhatsApp Fonnte

Seluruh pengiriman menggunakan service `api/fonnte.php`, dengan teks terpisah di `includes/whatsapp_templates.php`. Token hanya dibaca dari `FONNTE_TOKEN` pada `.env`, dikirim lewat header `Authorization`, dan tidak pernah disertakan pada log. Endpoint Fonnte menggunakan HTTPS, verifikasi sertifikat TLS, timeout koneksi/request, validasi nomor, serta pemeriksaan status HTTP dan respons JSON. Hasil sukses/gagal dan isi pesan dicatat ke tabel `log_notifikasi` agar pratinjau pesan tersedia di halaman admin; respons mentah gateway tidak disimpan. Isi pesan log notifikasi dapat dilihat oleh admin melalui halaman tersebut.

Untuk menguji pengiriman nyata, buka **Admin → Gateway Fonnte WA**, masukkan nomor WhatsApp milik sendiri yang aktif dengan format Indonesia (contoh `6281234567890`), tulis pesan uji, lalu kirim dan periksa hasil serta riwayat pada halaman tersebut. Pastikan token Fonnte benar dan akun gateway dapat mengirim ke nomor tujuan. Uji ini akan mengirim pesan sungguhan; jangan gunakan nomor yang tidak memberi izin. Jika gagal, periksa konfigurasi `.env`, koneksi server, ekstensi cURL/sertifikat CA, nomor tujuan, dan status pada riwayat notifikasi. Pengiriman gagal tidak membatalkan perubahan database yang sudah berhasil.

Notifikasi otomatis tersedia untuk registrasi, absen masuk/pulang (ke karyawan dan admin), perubahan data, perubahan status akun, penghapusan permanen, perubahan email, dan perubahan password. Nilai waktu absensi/pembaruan dibaca kembali dari database. Password maupun hash tidak pernah dimasukkan ke template.

### Langkah 4: Impor Database `db_karyawan`
1. Buka browser: `http://localhost/phpmyadmin/`.
2. Buat database bernama `db_karyawan`.
3. Impor file `database.sql` yang berada di direktori proyek.

---

## 4. AKUN UJI COBA DEFAULT

| Role | Email Login | Password Default | NIK | Hak Akses |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin@karyawan.local` | `admin123` | `ADM001` | Dashboard Admin, CRUD Karyawan, Koreksi Absen, Laporan, Log & Gateway |
| **Karyawan** | `budi@karyawan.local` | `password123` | `KRY001` | Dashboard Karyawan, Presensi Mandiri, Riwayat Saya, Kalender, Profil |
| **Karyawan** | `siti@karyawan.local` | `password123` | `KRY002` | Karyawan alternatif untuk pengujian ganda |

---

## 5. CARA MENGAKSES APLIKASI

Buka peramban web Google Chrome dan akses alamat URL:
```text
http://localhost/karyawan-wa/
```
Sistem akan otomatis mengenali sesi pengguna dan mengarahkan ke dashboard yang sesuai (Admin: `/admin/index.php`, Karyawan: `/karyawan/dashboard.php`).

---

## 6. MENJALANKAN PENGUJIAN OTOMATIS

Uji template WhatsApp yang tidak memerlukan database dan tidak mengirim pesan:
```bash
php tests\whatsapp_templates_test.php
```

Uji visibilitas tombol demo tanpa database:
```bash
php tests\demo_login_page_test.php true
php tests\demo_login_page_test.php false
```

Aplikasi dilengkapi dengan skrip test runner untuk memvalidasi 20 skenario fungsional dan keamanan wajib:
```bash
php test_runner.php
```
Hasil pengujian akan menampilkan status `[BERHASIL]` untuk seluruh skenario pengujian. Skrip ini melakukan perubahan data uji di database; jalankan hanya pada database lokal/non-produksi.
