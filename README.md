# DNY Partnership

Aplikasi DNY Partnership menggunakan Laravel 13, Vue 3, dan Vite.
README ini berisi panduan setup lokal, menjalankan aplikasi.

## Kebutuhan Sistem

Pastikan perangkat sudah memiliki:

- PHP 8.3 atau lebih baru beserta extension yang dibutuhkan Laravel;
- Composer 2;
- Node.js 20.19+ atau 22.12+ dan npm;
- MySQL 8;
- FFmpeg untuk kompresi foto dan video.

## Setup Pertama Kali

Semua command berikut dijalankan dari folder `backend`.

### 1. Pasang dependency

```powershell
composer install
npm install
```

### 2. Buat file environment

```powershell
Copy-Item .env.example .env
```

Isi minimal konfigurasi aplikasi dan akun awal di `.env`:

```dotenv
APP_NAME="DNY Partnership"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DNY_INITIAL_ADMIN_USERNAME=admin
DNY_INITIAL_ADMIN_PASSWORD=PasswordAdmin123
DNY_INITIAL_MEMBER_USERNAME=member
DNY_INITIAL_MEMBER_PASSWORD=PasswordMember123
DNY_DEVELOPMENT_APPROVAL_PASSWORD=PasswordMember123
DNY_INITIAL_MEMBER_PIN=123456
```

Gunakan password development milik masing-masing dan jangan commit password asli ke repository.

Password sementara untuk member yang dibuat melalui approval registrasi atau penambahan Distributor mengikuti aturan berikut:

- `local`, `development`, dan `testing`: memakai `DNY_DEVELOPMENT_APPROVAL_PASSWORD`;
- `staging` dan `production`: memakai tanggal lahir member dalam format `DDMMYYYY`.

### 3. Atur database

Untuk setup lokal paling sederhana, gunakan SQLite:

```dotenv
DB_CONNECTION=sqlite
```

Pastikan file database tersedia:

```powershell
if (-not (Test-Path database/database.sqlite)) {
    New-Item -ItemType File database/database.sqlite
}
```

Jika menggunakan MySQL, ganti konfigurasi database di `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dny
DB_USERNAME=root
DB_PASSWORD=
```

Database MySQL harus sudah dibuat sebelum migration dijalankan.

### 4. Buat key dan JWT secret

```powershell
php artisan key:generate
php artisan jwt:secret
php artisan optimize:clear
```

### 5. Buat tabel dan data awal

```powershell
php artisan migrate --seed --no-interaction
php artisan storage:link
```

Seeder utama mengisi data referensi, menu dan hak akses, konfigurasi sistem dan komisi, produk, administrator awal, member awal, warehouse, serta rekening perusahaan.

### 6. Build frontend

```powershell
npm run build
```

## Menjalankan Aplikasi Lokal

Untuk menjalankan server Laravel, Vite, log, dan proses development lain secara bersamaan:

```powershell
composer run dev
```

Jika hanya membutuhkan server aplikasi dan Vite, jalankan pada dua terminal:

```powershell
php artisan serve
```

```powershell
npm run dev
```

Aplikasi dapat dibuka melalui:

- Admin: `http://localhost/admin/login`
- Member: `http://localhost/member/login`

Pengiriman email dan pemrosesan media aplikasi berjalan langsung, sehingga queue worker tidak wajib untuk alur tersebut.

## Instalasi PWA Member di Android

Aplikasi member menyediakan manifest dan service worker agar dapat dipasang dari browser Android. Pada server live, aplikasi wajib diakses melalui HTTPS.

Setelah menjalankan `npm run build`, buka URL member melalui Chrome Android, lalu pilih **Instal aplikasi** atau **Tambahkan ke layar utama**. Aplikasi akan terpasang dengan nama **DNY Mitra** dan terbuka dalam mode standalone.

## Data Simulasi Development

`DevelopmentSeeder` menyediakan jaringan member, stok, transaksi regular dan PO, pembayaran, pengiriman, penerimaan, serta data reward yang saling terhubung.

Untuk membuat database development dari awal:

```powershell
php artisan migrate:fresh --seed --no-interaction
php artisan db:seed --class=DevelopmentSeeder --no-interaction
```

`migrate:fresh` menghapus seluruh isi database. Command ini hanya boleh dijalankan pada database lokal atau development.

`DevelopmentSeeder` dibuat sebagai data simulasi satu kali. Jika perlu mengulang seluruh skenario, gunakan kembali `migrate:fresh --seed` sebelum menjalankannya.

## Simulasi Reward Bulanan

Proses reward bulanan dijalankan otomatis oleh scheduler setiap tanggal 1 pukul 00:05 untuk menutup periode bulan sebelumnya.

Untuk menjalankannya manual:

```powershell
php artisan rewards:close-month
```

Untuk mensimulasikan periode tertentu:

```powershell
php artisan rewards:close-month --period=2026-08
```

Format periode adalah `YYYY-MM`. Gunakan bulan yang sudah selesai, bukan bulan berjalan atau bulan yang akan datang.

Proses ini menghitung pencapaian member, reward bulanan, reward Stokis, sharing profit, dan kualifikasi upgrade. Command bersifat idempotent sehingga periode yang sama dapat dijalankan kembali ketika proses sebelumnya gagal atau perlu diperbarui.

Alur simulasi reward lengkap:

```powershell
php artisan migrate:fresh --seed --no-interaction
php artisan db:seed --class=DevelopmentSeeder --no-interaction
php artisan rewards:close-month --period=2026-08
```

Sesuaikan periode dengan tanggal transaksi yang dibuat oleh seeder.

## Simulasi Upgrade dan Downgrade

Perubahan level atau jaringan yang sudah disetujui dan sudah mencapai tanggal efektif diterapkan dengan:

```powershell
php artisan members:apply-upgrade-downgrade
```

Pada alur normal, approval tetap dilakukan melalui API atau halaman admin terlebih dahulu. Command ini hanya menerapkan data yang sudah berstatus siap dijadwalkan.

## Menjalankan Scheduler

Scheduler dibutuhkan agar penutupan reward dan perubahan level berjalan otomatis:

```powershell
php artisan schedule:work
```

Untuk melihat seluruh jadwal yang aktif:

```powershell
php artisan schedule:list
```

Jadwal utama aplikasi:

- `rewards:close-month` setiap tanggal 1 pukul 00:05;
- `members:apply-upgrade-downgrade` setiap hari pukul 00:20;
- `telescope:prune` setiap hari untuk membersihkan data Telescope lama.

## Email Lokal

Untuk melihat email tanpa mengirim ke alamat asli, gunakan log mailer:

```dotenv
MAIL_MAILER=log
MAIL_FROM_ADDRESS="notifikasi@example.test"
MAIL_FROM_NAME="${APP_NAME}"
```

Isi email dapat dilihat di `storage/logs/laravel.log`.

Jika ingin menguji SMTP, isi `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, dan `MAIL_FROM_ADDRESS` sesuai akun SMTP development, lalu jalankan:

```powershell
php artisan optimize:clear
```

Jangan menyimpan kredensial SMTP asli di README atau commit repository.

## Integrasi Pengiriman

Konfigurasi layanan pengiriman diambil dari `.env`. Contoh konfigurasi development:

```dotenv
STC_BASE_URL="https://url.stc.co.id/"
STC_TOKEN=""
STC_CLIENT_ID="1"
STC_CLIENT_CODE="WHPE"
STC_CONNECT_TIMEOUT=3
STC_TIMEOUT=10
```

Setelah konfigurasi berubah, bersihkan cache:

```powershell
php artisan optimize:clear
```

Pastikan service pengiriman development sudah berjalan sebelum melakukan simulasi pengiriman ekspres, tracking, atau callback selesai.

## Menjalankan Pengujian

Jalankan seluruh test:

```powershell
php artisan test
```

Jalankan file test tertentu:

```powershell
php artisan test tests/Feature/NamaTest.php
```

Periksa format kode backend:

```powershell
vendor/bin/pint --test
```

Perbaiki format kode backend:

```powershell
vendor/bin/pint
```

Periksa build frontend:

```powershell
npm run build
```

## Command Setup Cepat

Untuk instalasi lokal baru dengan SQLite:

```powershell
composer install
npm install
Copy-Item .env.example .env
if (-not (Test-Path database/database.sqlite)) {
    New-Item -ItemType File database/database.sqlite
}
php artisan key:generate
php artisan jwt:secret
php artisan optimize:clear
php artisan migrate --seed --no-interaction
php artisan storage:link
npm run build
composer run dev
```

Pastikan `.env` sudah diisi sebelum migration dan seeder dijalankan.
