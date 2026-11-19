# EREPO — Digital Repository S1 Teknik Elektro UDINUS

**EREPO** (*Electronic Repository / Knowledge Bank*) adalah platform repositori digital Program Studi S1 Teknik Elektro Universitas Dian Nuswantoro (UDINUS) untuk mengelola, memeriksa kelengkapan dokumen secara otomatis menggunakan AI, memverifikasi, serta mengarsipkan laporan **Capstone Design / Tugas Akhir (PDF)** dan **Source Code Program (Script ZIP/RAR)**.

> **Versi Saat Ini:** `v1.2.0`

---

## Arsitektur & Teknologi Utama

| Komponen | Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend & Runtime** | Laravel 13, PHP 8.3.36, Laravel Octane (Swoole) | Performa tinggi berbasis worker *in-memory* |
| **Frontend UI** | React 18, Inertia.js v2, Tailwind CSS v4, Vite | Antarmuka SPA modern bergaya *glassmorphic* |
| **Database & Cache/Queue** | MySQL 8.0 & Redis (Alpine) | Penyimpanan relasional & pemrosesan antrean AI/Email di *background* |
| **Pemeriksa Dokumen AI** | Ollama (`qwen3.8:27b` Vision & Text) + `poppler-utils` | Validasi otomatis tanda tangan pengesahan (termasuk hasil *scan*) & kelengkapan nomor halaman Daftar Isi |
| **Layanan Email** | Google SMTP (`smtp.gmail.com`) Terenkripsi DB | Dikonfigurasi langsung oleh Admin via menu *Settings* tanpa restart container |
| **Web Server** | Nginx (Alpine) | Port default aplikasi web: `http://localhost:4008` |

---

## Fitur Utama

### 1. Portal Katalog Publik Tanpa Login (`/`) & Proteksi Anti-Turnitin
- **Katalog Publik Langsung (`/`):** Pengunjung dapat langsung melihat statistik repositori terverifikasi (`approved`), memfilter arsip berdasarkan **Tahun Ajaran**, serta menelusuri **Repository PDF** (`/explore/capstone`) dan **Repository Script** (`/explore/script`) dengan pilihan *pagination* `10 / 25 / 50` data.
- **Pratinjau Khusus Halaman Abstrak:** Tombol **View Abstrak** mengekstrak secara otomatis hanya halaman **Intisari / Abstrak / Abstract** sebagai gambar pratinjau tanpa membuka keseluruhan isi PDF.
- **Token Unduh PDF 1 Hari (Khusus Email UDINUS):** Pengunjung dengan email resmi `@mhs.dinus.ac.id` atau `@dsn.dinus.ac.id` dapat meminta **Token Download (aktif 1 hari / 24 jam)** ke emailnya untuk mengunduh PDF penuh.
- **Verifikasi Unduh Source Code (Script):** Permintaan unduh *source code* dari katalog publik wajib diverifikasi terlebih dahulu melalui akun **Dosen Pembimbing, Koordinator TA, atau Admin** pada menu **Verifikasi Permintaan Download** sebelum token 1 hari dikirimkan ke email pemohon.
- **Proteksi Anti-Turnitin:** Akses URL statis `/storage/*` diblokir (`403 Forbidden`) di level Nginx, seluruh file disajikan melalui route Laravel terverifikasi, serta dilindungi `robots.txt` dan header `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet` agar dokumen mahasiswa aman dari pengindeksan *crawler* eksternal (*self-plagiarism*).

### 2. Manajemen Kelompok & Login Token Mahasiswa (`/login`)
- **Menu `Add Capstone Team` (`/capstone-teams`):** Koordinator TA dan Admin dapat mendaftarkan kelompok Capstone (menentukan **Kode Capstone**, Tahun Ajaran, Dosen Pembimbing, serta memilih atau menambahkan anggota mahasiswa baru secara langsung).
- **Login Mahasiswa Berbasis Kode Capstone + Token 1 Bulan:** Mahasiswa masuk melalui `/login` cukup menggunakan **Kode Capstone** dan **Token Email yang aktif selama 1 bulan (30 hari)**. Saat mengunggah dokumen, data kelompok, pembimbing, tahun ajaran, dan seluruh anggota otomatis terisi dan tersinkron di akun satu kelompok.
- **Login Staff Berbasis Password:** Akun `admin`, `koordinator_ta`, `dosen`, dan `kaprodi` tetap menggunakan autentikasi NPP/Email + Password.

### 3. Pemeriksaan Kelengkapan PDF Otomatis (Qwen AI) & Alur Verifikasi
- **Hybrid PDF Checker (`pdftotext` + `pdftoppm` + Qwen Vision):**
  - Memeriksa keberadaan tanda tangan pada **Halaman Persetujuan, Halaman Pengesahan, dan Lembar Dokumentasi Produk TA (E-100 s/d E-500)** — mendukung halaman digital maupun hasil *scan* gambar tanpa teks OCR.
  - Memastikan **Daftar Isi** tersedia dan seluruh baris bab/sub-bab memiliki nomor halaman yang valid (mendeteksi nomor kosong atau `Error! Bookmark not defined`).
- **Penahanan Otomatis & Override dengan Catatan:**
  - Dokumen yang belum lengkap otomatis ditahan (*Wajib Upload Ulang*) disertai rincian kekurangan di layar dan via email.
  - Khusus **Koordinator TA** dan **Admin**, tersedia fitur **Terima dengan Catatan** untuk tetap menyetujui dokumen tertentu dengan menyimpan catatan penerimaan ke database sekaligus mengirimkannya via email ke Mahasiswa & Dosen Pembimbing.
  - Pemeriksa juga dapat menekan tombol **Minta Upload Ulang** dengan catatan revisi manual kapan saja.

---

## Hak Akses Peran Pengguna

| Peran | Kelola Akun (`/assign-account`) | Pengaturan Google SMTP | Kelola Tim Capstone | Verifikasi Capstone, Script & Permintaan Download |
| :--- | :---: | :---: | :---: | :---: |
| **Admin (`admin`)** | Ya | Ya | Ya | Semua Kelompok (termasuk *Terima dgn Catatan*) |
| **Koordinator TA (`koordinator_ta`)** | Tidak | Tidak | Ya | Semua Kelompok (termasuk *Terima dgn Catatan*) |
| **Kaprodi (`kaprodi`)** | Tidak | Tidak | Tidak | Semua Kelompok |
| **Dosen Pembimbing (`dosen`)** | Tidak | Tidak | Tidak | Khusus Kelompok Bimbingannya |
| **Mahasiswa / Tim Capstone** | Tidak | Tidak | Tidak | Unggah & Revisi Berkas Kelompoknya |

---

## Panduan Instalasi & Menjalankan Aplikasi

### Prasyarat
1. **Docker** & **Docker Compose** telah terpasang.
2. **Node.js** (`>= 20.x`) & **npm** di mesin host (untuk *build* aset frontend Vite).
3. *(Opsional namun direkomendasikan)* **Ollama** berjalan di host lokal (`http://127.0.0.1:11434`) dengan model Vision `qwen3.8:27b`:
   ```bash
   ollama pull qwen3.8:27b
   ```

### Langkah Instalasi

1. **Clone Repositori**
   ```bash
   git clone https://github.com/arivanhi/erepo.git
   cd erepo
   ```

2. **Salin & Sesuaikan Konfigurasi `.env`**
   ```bash
   cp .env.example .env
   ```
   Pastikan konfigurasi database MySQL & Redis pada `.env` sesuai dengan `docker-compose.yml`:
   ```env
   APP_NAME=EREPO
   APP_URL=http://localhost:4008
   APP_TIMEZONE=Asia/Jakarta

   DB_CONNECTION=mysql
   DB_HOST=db
   DB_PORT=3306
   DB_DATABASE=knowledge_bank
   DB_USERNAME=root
   DB_PASSWORD=root

   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   SESSION_DRIVER=redis
   REDIS_HOST=redis
   REDIS_PORT=6379

   OLLAMA_BASE_URL=http://host.docker.internal:11434
   OLLAMA_MODEL=qwen3.8:27b
   ```

3. **Build & Jalankan Container Docker**
   ```bash
   docker compose up -d --build
   ```
   Layanan yang berjalan:
   - Aplikasi Web EREPO (Nginx -> Octane): `http://localhost:4008`
   - phpMyAdmin: `http://localhost:4010`
   - MySQL 8.0: port host `4009`
   - Redis: port host `6380`
   - Queue Worker (`knowledge_bank_queue`): memproses pengecekan AI PDF & antrean email di belakang layar.

4. **Instal Dependensi Backend, Generate Key & Migrasi Database**
   ```bash
   docker exec knowledge_bank_app composer install
   docker exec knowledge_bank_app php artisan key:generate
   docker exec knowledge_bank_app php artisan migrate --seed
   ```
   *(Akun Admin awal hasil seeder: `admin@example.com` / password: `password` — segera ubah email & password setelah login pertama).*

5. **Instal Dependensi Frontend & Build Aset Produksi**
   ```bash
   npm install --legacy-peer-deps
   npm run build
   docker exec knowledge_bank_app php artisan optimize:clear
   docker exec knowledge_bank_app php artisan octane:reload
   ```

6. **Aktifkan Google SMTP (Melalui Web Admin)**
   - Buka `http://localhost:4008/login` -> pilih tab **Dosen / Koordinator / Admin** -> masuk menggunakan akun Admin.
   - Buka menu **Settings** -> pada bagian **Pengaturan Google SMTP**, masukkan alamat Gmail dan **16 karakter Google App Password**, klik **Simpan Pengaturan SMTP**, lalu uji menggunakan tombol **Kirim Email Tes**.

### Menjalankan Pengujian Otomatis (PHPUnit)
Pengujian berjalan terisolasi menggunakan SQLite `:memory:` sehingga aman dan tidak pernah menyentuh database MySQL aktif:
```bash
docker exec knowledge_bank_app php artisan test
```

---

## Changelog

### `v1.2.0` — 19 November 2026
- **Portal Katalog Publik Tanpa Login (`/`):** Memisahkan halaman utama katalog publik (`/`, `/explore/capstone`, `/explore/script`) dari halaman login internal (`/login`), lengkap dengan filter Tahun Ajaran dan pilihan *pagination* `10 / 25 / 50` data.
- **Ekstraksi Otomatis Pratinjau Abstrak (`AbstractExtractorService`):** Tombol *View Abstrak* pada katalog publik otomatis merender khusus halaman *Intisari / Abstrak / Abstract* saja.
- **Proteksi Anti-Turnitin:** Memblokir akses publik langsung ke `/storage/*` (`403 Forbidden`), menambahkan middleware `PreventSearchIndexing` (`X-Robots-Tag: noindex, nofollow, noarchive, nosnippet`), dan mengunci `robots.txt`.
- **Token Unduh PDF 1 Hari (Domain UDINUS):** Pengunduhan PDF penuh dari katalog publik dibatasi untuk email `@mhs.dinus.ac.id` dan `@dsn.dinus.ac.id` menggunakan token berbatas waktu 24 jam.
- **Menu Baru `Verifikasi Permintaan Download` (`/download-requests`):** Permohonan unduh *Repository Script* dari publik mengirimkan email notifikasi ke Dosen Pembimbing, Koordinator TA, dan Admin untuk diverifikasi terlebih dahulu melalui tabel verifikasi di akun masing-masing sebelum token 1 hari dikirimkan ke pemohon.
- **Menu Baru `Add Capstone Team` (`/capstone-teams`) & Login Token Mahasiswa 1 Bulan:** Koordinator TA dan Admin dapat mengatur kelompok & Kode Capstone; mahasiswa login cukup menggunakan **Kode Capstone + Token Email aktif 1 bulan** dengan pengisian atribut kelompok otomatis saat unggah berkas.
- **Fitur Override `Terima dengan Catatan`:** Koordinator TA dan Admin dapat tetap menerima PDF yang tertahan oleh pemeriksaan AI dengan menyimpan catatan penerimaan dan mengirimkannya via email ke Mahasiswa serta Dosen Pembimbing.
- **Penyempurnaan UI Tabel Repositori:** Menampilkan *Status* dan *Action* dalam bentuk ikon ringkas disertai *floating tooltip* instan (`HoverTooltip`) untuk peran Admin, Koordinator TA, dan Dosen agar kolom judul dokumen tampil jauh lebih lega.

### `v1.1.0` — 11–19 November 2026
- **Role Baru `koordinator_ta`:** Memiliki hak kelola dan verifikasi repositori setara Admin namun diblokir penuh (`403`) dari manajemen akun pengguna (`/assign-account`).
- **Sinkronisasi Anggota Kelompok (`capstone_project_user`):** Mendukung banyak anggota mahasiswa dalam satu kelompok Capstone sehingga status unggahan, dashboard, dan koneksi Script tersinkron otomatis ke seluruh akun anggota serta menampilkan nama lengkap seluruh anggota di tabel.
- **Integrasi Pemeriksa Kelengkapan PDF AI (`qwen3.8:27b` Vision + `poppler-utils`):** Pemeriksaan asinkron via Redis Queue untuk mendeteksi kelengkapan tanda tangan pengesahan (digital maupun hasil *scan* gambar) dan kelengkapan nomor halaman Daftar Isi, menahan dokumen yang belum lengkap agar wajib diunggah ulang.
- **Pengaturan Google SMTP Terenkripsi & Notifikasi Email Lengkap:** Panel konfigurasi Google SMTP + fitur *Tes Kirim Email* di halaman *Settings* Admin, aksi manual *Minta Upload Ulang* dengan catatan revisi, notifikasi email lengkap ke verifikator serta mahasiswa di setiap perubahan status, dan penyesuaian zona waktu ke `Asia/Jakarta` (**WIB**).
- **Pembaruan Keamanan Runtime:** Upgrade container aplikasi dan worker ke `PHP 8.3.36` beserta pengerasan konfigurasi PHP & phpMyAdmin.

### `v1.0.0` — Rilis Awal
- Sistem dasar manajemen akun (`admin`, `kaprodi`, `dosen`, `mahasiswa`) beserta impor massal pengguna Excel/CSV.
- Unggah, persetujuan (*approval*), dan unduh berkas dasar untuk *Capstone Design* dan *Script Repository*.
