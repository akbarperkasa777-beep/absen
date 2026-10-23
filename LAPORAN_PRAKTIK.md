# LAPORAN PRAKTIK PEMROGRAMAN BERBASIS WEB & WEB API

---

## COVER

- **Judul Proyek:** Portal Presensi Karyawan — Premium Workforce Management & WhatsApp Gateway Fonnte dengan Konfigurasi `.env`
- **Nama Siswa:** [Nama Lengkap Siswa]
- **Kelas:** XII Rekayasa Perangkat Lunak (RPL)
- **Nomor Absen:** [Nomor Absen]
- **Mata Pelajaran:** Pemrograman Web dan Perangkat Bergerak / Pemrograman Berbasis API
- **Kompetensi Keahlian:** Rekayasa Perangkat Lunak (RPL)
- **Nama Sekolah:** SMK Negeri / Swasta [Nama Sekolah]
- **Tahun Pelajaran:** 2026/2027

---

## BAB I — PENDAHULUAN

### 1.1 Latar Belakang
Pengelolaan data kepegawaian dan pencatatan presensi kerja merupakan aspek vital dalam operasional industri modern. Di era revolusi industri 4.0, efisiensi sistem presensi tidak hanya ditentukan oleh akurasi pencatatan waktu masuk dan pulang, namun juga oleh transparansi informasi, kenyamanan antarmuka pengguna (*User Experience*), keamanan kredensial, serta kemampuan integrasi dengan platform pesan instan seperti WhatsApp.

Aplikasi **Portal Presensi Karyawan** versi 2.0 ini ditingkatkan menjadi konsep **Premium Workforce Management Dashboard** dengan standar tampilan modern SaaS (*Software as a Service*). Sistem memadukan bahasa pemrograman **PHP Native 8.x**, basis data relasional **MySQL/MariaDB**, pustaka antarmuka **Bootstrap 5.3**, visualisasi data interaktif **Chart.js**, serta pengamanan variabel lingkungan rahasia melalui file **`.env`** dengan library **`vlucas/phpdotenv`** via Composer.

Sistem juga telah menyelesaikan permasalahan penampilan format teks mentah (*raw HTML tag bug*), menghadirkan modul khusus karyawan (Dashboard Karyawan, Riwayat Presensi dengan multi-filter & ekspor CSV, Kalender Kehadiran, serta Manajemen Profil & Keamanan), serta memperkaya kapabilitas administrator (Koreksi Presensi Resmi, Manajemen Status Akun, Alat Uji Coba WhatsApp Gateway Fonnte, dan Audit Trail Log Aktivitas).

### 1.2 Tujuan
1. Mengamankan seluruh kredensial basis data dan token WhatsApp Gateway Fonnte menggunakan file `.env` dan pustaka `vlucas/phpdotenv`.
2. Menyempurnakan desain antarmuka menjadi standar *Premium Workforce Management Dashboard* dengan identitas warna Midnight Navy, Electric Blue, dan Cyan accents.
3. Memperbaiki bug penampilan teks HTML mentah pada notifikasi login dan flash messages secara aman dari celah XSS.
4. Membangun modul khusus karyawan yang terisolasi: Dashboard Karyawan, Riwayat Presensi Saya (multi-filter & ekspor CSV), Kalender Kehadiran Bulanan, serta Profil Saya dengan fitur Ganti Email dan Ganti Password.
5. Membangun modul tingkat lanjut administrator: Visualisasi tren 7 hari (Chart.js), Koreksi Absensi Resmi beserta alasan audit, Detail Karyawan dengan toggle status akun (aktif/nonaktif), Alat Uji Kirim WhatsApp Gateway, dan Log Aktivitas Sistem (*audit trail*).
6. Melakukan pengujian sistem secara komprehensif terhadap 20 skenario wajib.

### 1.3 Manfaat
- **Bagi Siswa:** Memahami standar industri dalam manajemen rahasia konfigurasi (`.env`), arsitektur PHP Native terstruktur, dan teknik perancangan antarmuka SaaS modern.
- **Bagi Penguji/Guru:** Memperoleh bukti implementasi kode yang lengkap, aman, teruji, dan sesuai jobsheet uji kompetensi.
- **Bagi Pengguna/Karyawan:** Memberikan kenyamanan pemantauan jam kerja mandiri secara transparan dan responsif.

---

## BAB II — LANDASAN TEORI

### 2.1 Web API & RESTful Architecture
Web API adalah antarmuka berbasis web yang memfasilitasi pertukaran data antar sistem. RESTful API memanfaatkan metode HTTP standar (`POST`, `GET`) dengan format pertukaran data JSON. Layanan WhatsApp Gateway Fonnte mengimplementasikan arsitektur ini melalui endpoint `https://api.fonnte.com/send`.

### 2.2 Environment Variables & Library Dotenv
Penyimpanan kredensial sensitif langsung pada source code (*hardcoded credentials*) merupakan pelanggaran standar keamanan perangkat lunak. Konsep *Twelve-Factor App* mewajibkan konfigurasi dipisahkan dari kode program. Library `vlucas/phpdotenv` memungkinkan aplikasi PHP Native memuat variabel lingkungan dari file `.env` ke array `$_ENV` dan fungsi `getenv()` secara aman.

### 2.3 Keamanan Web Modern (Bcrypt, CSRF, XSS Prevention)
- **Bcrypt (`password_hash()`):** Fungsi hashing satu arah dengan salt dinamis untuk menyimpan kata sandi.
- **Cross-Site Request Forgery (CSRF):** Serangan pemaksaan eksekusi instruksi tanpa disadari pengguna. Diatasi dengan validasi token acak kriptografis pada setiap request POST.
- **Cross-Site Scripting (XSS):** Serangan injeksi skrip berbahaya pada halaman web. Diatasi dengan sanitasi ketat melalui fungsi `htmlspecialchars()`.

### 2.4 Audit Trail (Log Aktivitas)
Audit trail adalah rekaman log kronologis yang mendokumentasikan siapa (*who*), melakukan apa (*what*), kapan (*when*), dan dari mana (*where/IP address*). Fitur ini penting untuk akuntabilitas operasional dan penelusuran jika terjadi anomali data.

---

## BAB III — PERANCANGAN SISTEM

### 3.1 Struktur Basis Data Terkini (`db_karyawan`)

```
┌─────────────────────────┐          ┌─────────────────────────┐
│        karyawan         │          │         absensi         │
├─────────────────────────┤          ├─────────────────────────┤
│ PK  id (INT)            │ 1      N │ PK  id (INT)            │
│     nik (VARCHAR 30)    │──────────│ FK  id_karyawan (INT)   │
│     nama (VARCHAR 100)  │          │     tanggal (DATE)      │
│     jabatan (VARCHAR 100│          │     jam_masuk (TIME)    │
│     no_hp (VARCHAR 20)  │          │     jam_pulang (TIME)   │
│     email (VARCHAR 100) │          │     status (VARCHAR 30) │
│     password (VARCHAR)  │          │     keterangan (VARCHAR)│
│     role (ENUM)         │          │     dikoreksi_oleh(INT) │
│     status_akun (ENUM)  │          │     alasan_koreksi(TEXT)│
│     foto (VARCHAR)      │          │     waktu_koreksi(DATET)│
│     created_at (DATETIME│          │     created_at (DATETIME│
│     updated_at (DATETIME│          └─────────────────────────┘
└─────────────────────────┘
             │
             │ 1                     ┌─────────────────────────┐
             │                       │     log_aktivitas       │
             │ N                     ├─────────────────────────┤
             └───────────────────────│ PK  id (INT)            │
                                     │     id_user (INT)       │
                                     │     nama_user (VARCHAR) │
                                     │     role (VARCHAR)      │
                                     │     aksi (VARCHAR)      │
                                     │     keterangan (TEXT)   │
                                     │     ip_address (VARCHAR)│
                                     │     created_at (DATETIME│
                                     └─────────────────────────┘
```

*Tabel `absensi` memiliki constraint unik `uq_karyawan_tanggal (id_karyawan, tanggal)`.*

---

## BAB IV — IMPLEMENTASI

### 4.1 Implementasi Konfigurasi `.env`
Kredensial database dan token Fonnte dimuat menggunakan:
```php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
```
Akses langsung ke `.env` melalui peramban web diblokir via konfigurasi Apache `.htaccess`:
```apache
<FilesMatch "^\.env|\.sql|\.git|composer\.(json|lock)">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### 4.2 Perbaikan Bug Format HTML Mentah
Fungsi `display_flash()` telah diperbaiki dengan memisahkan judul notifikasi tebal, simbol pemisah, dan teks pesan tanpa melakukan double-escaping:
```php
$titlePart = $title !== '' ? "<strong>{$title}</strong> &mdash; " : "";
```
Hasil pada antarmuka: **Login Berhasil** — Selamat datang kembali, Budi Pratama! (bersih dan rapi).

### 4.3 Modul Karyawan
- **Dashboard Karyawan:** Menampilkan sapaan waktu dinamis, status presensi hari ini, kartu metrik kehadiran bulanan, jam server digital, dan aksi cepat.
- **Riwayat Presensi Saya:** Menampilkan data presensi terisolasi (`id_karyawan = $_SESSION['user']['id']`), didukung filter tanggal, bulan, tahun, status, modal detail, dan ekspor CSV.
- **Profil & Keamanan:** Menyediakan formulir ganti email dengan verifikasi kata sandi lama dan formulir ganti kata sandi terenkripsi `password_hash()`.
- **Kalender Kehadiran:** Menampilkan matriks bulanan interaktif dengan indikator kehadiran riil.

### 4.4 Modul Administrator
- **Advanced Dashboard:** Dilengkapi grafik tren 7 hari (Chart.js), kartu statistik agregat database, dan widget status gateway Fonnte.
- **Manajemen Karyawan:** Pencarian nama/NIK/email/HP, filter jabatan, filter status akun, dan sorting.
- **Koreksi Presensi:** Admin dapat merevisi jam masuk/pulang presensi dengan kewajiban mencatat alasan koreksi.
- **Gateway Fonnte & Uji Coba:** Menampilkan kartu status konfigurasi `.env`, log riwayat audit, dan form uji kirim WhatsApp.
- **Log Aktivitas:** Menampilkan tabel audit seluruh aktivitas sistem secara transparan.

---

## BAB V — PENGUJIAN SISTEM (20 SKENARIO RESMI)

| No | Skenario Pengujian | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status |
|:--:|:---|:---|:---|:---|:---:|
| 1 | Login admin dan karyawan | Uji kredensial default admin & karyawan | Autentikasi hash berhasil | Password hash terverifikasi | **BERHASIL** |
| 2 | Akses admin ditolak untuk karyawan | Karyawan coba akses URL admin | Akses ditolak dan di-redirect | Di-redirect ke portal karyawan | **BERHASIL** |
| 3 | Dashboard karyawan data mandiri | Query absensi berdasar id_karyawan | Hanya data pribadi yang muncul | Data terisolasi sesuai sesi | **BERHASIL** |
| 4 | Riwayat presensi filter tanggal | Filter tanggal hari ini | Data tanggal terpilih tampil | Query filter tanggal sukses | **BERHASIL** |
| 5 | Riwayat filter bulan & tahun | Filter bulan berjalan & tahun 2026 | Hanya data bulan & tahun tampil | Filter bulan/tahun sukses | **BERHASIL** |
| 6 | Filter kosong / tidak ada hasil | Filter tanggal lampau tanpa data | Muncul empty state informatif | Empty state tampil rapi | **BERHASIL** |
| 7 | Ganti email valid | Input email baru yang belum terdaftar | Email berhasil diperbarui di DB | Email berhasil diubah | **BERHASIL** |
| 8 | Ganti email sudah terpakai | Input email yang sudah dipakai akun lain | Sistem menolak duplikasi | Unique constraint DB menolak | **BERHASIL** |
| 9 | Ganti password lama salah | Input password verifikasi salah | Perubahan ditolak | `password_verify` FALSE | **BERHASIL** |
| 10 | Ganti password beda konfirmasi | Input password baru & konfirmasi beda | Validasi form menolak simpan | Form mendeteksi ketidakcocokan | **BERHASIL** |
| 11 | Login dengan password baru | Login menggunakan kata sandi baru | Login berhasil | Hash baru terverifikasi | **BERHASIL** |
| 12 | Statistik admin angka riil DB | Query COUNT data karyawan & absen | Angka kartu bersumber dari DB | Metrik kartu sesuai DB | **BERHASIL** |
| 13 | Pencarian & filter karyawan | Cari keyword "Budi" di tabel karyawan | Data Budi Pratama ditemukan | Pencarian akurat & cepat | **BERHASIL** |
| 14 | Koreksi presensi & audit log | Admin koreksi jam masuk & isi alasan | Jam terupdate & log audit tercatat | Jam terkoreksi & log audit ada | **BERHASIL** |
| 15 | Ekspor laporan sesuai filter | Ekspor CSV laporan tanggal tertentu | File CSV hanya berisi data terpilih | CSV terunduh sesuai filter | **BERHASIL** |
| 16 | Notifikasi bebas tag HTML mentah | Render flash message selamat datang | Teks tampil bersih tanpa `<strong>` | Output alert bersih & rapi | **BERHASIL** |
| 17 | Tampilan responsif CSS | Cek media query & offcanvas mobile | Tata letak menyesuaikan layar | Responsif di desktop/tablet/HP | **BERHASIL** |
| 18 | CSRF & otorisasi endpoint | POST form dengan token CSRF salah | Request dibatalkan | validate_csrf() tolak POST | **BERHASIL** |
| 19 | Error handling API Fonnte | Kirim WA via fungsi kirim_wa_fonnte | Mode simulasi / error tertangani | Sistem stabil, log tercatat | **BERHASIL** |
| 20 | Kredensial & `.env` terlindungi | Cek .gitignore & aturan .htaccess | .env aman dari akses browser | HTTP 403 Forbidden ke .env | **BERHASIL** |

---

## BAB VI — PENUTUP

### 6.1 Kesimpulan
Proyek redesain total **Portal Presensi Karyawan** telah berhasil diselesaikan dengan keunggulan:
1. Konfigurasi kredensial database dan API Fonnte telah 100% dialihkan ke file `.env` menggunakan library `vlucas/phpdotenv` melalui Composer.
2. Desain antarmuka telah ditransformasi menjadi konsep *Premium Workforce Management Dashboard* yang profesional, elegan, responsif, dan konsisten di seluruh halaman.
3. Bug teks HTML mentah pada notifikasi login telah diperbaiki dengan pendekatan sanitasi yang aman dari serangan XSS.
4. Fitur mandiri karyawan dan fitur tingkat lanjut administrator berfungsi secara nyata dan telah divalidasi melalui 20 skenario pengujian dengan tingkat kelulusan 100%.

### 6.2 Saran
1. Pada pengembangan berikutnya, dapat ditambahkan modul integrasi pembacaan sidik jari hardware (*biometric fingerprint scanner*) atau pemindaian QR Code dinamis.
2. Penambahan push notification berbasis Progressive Web App (PWA) untuk melengkapi notifikasi WhatsApp Gateway Fonnte.

---

## LAMPIRAN

### Daftar Screenshot Pengujian:
1. File `.env` dan `.env.example` pada Visual Studio Code.
2. Uji Coba Blokir Akses Web `.env` (HTTP 403 Forbidden).
3. Halaman Login Pengguna (Bebas dari Bug HTML Mentah).
4. Dashboard Karyawan (Sapaan Waktu, Status Hari Ini, dan Metrik Kehadiran).
5. Layar Presensi Mandiri Karyawan (Jam Server Real-Time).
6. Halaman Riwayat Presensi Saya dengan Filter Tanggal & Status.
7. File Hasil Ekspor CSV Riwayat Presensi Karyawan.
8. Modal Rincian Catatan Presensi Karyawan.
9. Kalender Kehadiran Bulanan Karyawan.
10. Formulir Ganti Email dan Ganti Password pada Halaman Profil.
11. Bukti Notifikasi WhatsApp Perubahan Email.
12. Dashboard Administrator dengan Grafik Kehadiran 7 Hari (Chart.js).
13. Manajemen Master Karyawan dengan Fitur Pencarian dan Filter Jabatan.
14. Halaman Detail Karyawan dan Toggle Status Akun (Aktif/Nonaktif).
15. Halaman Data & Koreksi Presensi Harian oleh Admin.
16. Modal Koreksi Presensi Beserta Input Alasan Audit.
17. Halaman Laporan Rekapitulasi Presensi dengan Filter Multi-Parameter.
18. File Hasil Ekspor Excel / CSV Laporan Presensi Admin.
19. Halaman Pemantauan Gateway Fonnte dan Form Uji Kirim WhatsApp.
20. Halaman Log Aktivitas Sistem (Audit Trail Lengkap).
