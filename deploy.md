# Deploy DNY Partnership

Panduan ini dijalankan dari direktori `backend` pada server. Aplikasi memakai Laravel 13, Vue 3/Vite, PHP minimal 8.3, MySQL 8, Composer 2, Node.js 20.19+ atau 22.12+, npm, dan FFmpeg. Arahkan document root web server ke `backend/public`, **bukan** ke direktori `backend`.

Contoh path `dnydev` dan PHP 8.4 di bawah berasal dari konfigurasi development; periksa path, versi PHP, user proses, dan domain server tujuan sebelum menyalin perintah. Jangan menyalin `.env` atau database dari development ke production.

## Tech stack server

| Komponen | Kebutuhan aplikasi |
| --- | --- |
| Web server | Nginx sebagai pintu masuk HTTP/HTTPS, document root ke `backend/public` |
| Runtime PHP | PHP-FPM 8.3+ beserta extension Laravel; PHP CLI harus memakai versi dan extension yang sesuai |
| Backend | Laravel 13, Composer 2, JWT/Sanctum |
| Frontend | Vue 3, Vite, Vuetify, Tailwind; Node.js dan npm dibutuhkan saat build, bukan untuk melayani request hasil build |
| Database | MySQL 8 untuk server; SQLite hanya opsi setup lokal |
| Proses latar | Database queue (`jobs`) dengan worker terpisah, serta cron Laravel Scheduler tiap menit |
| Integrasi | SMTP/mailer, layanan STC, FFmpeg untuk media |

Nginx/PHP-FPM adalah **rancangan deploy**, bukan klaim bahwa server yang sekarang sudah memakai konfigurasi ini. Cek versi, socket, dan service yang benar pada host tujuan.

Periksa tool pada host dengan `nginx -v`, `php -v`, `php -m`, `composer --version`, `node --version`, `npm --version`, `mysql --version`, dan `ffmpeg -version`. PHP-FPM dan PHP CLI harus mempunyai extension yang dibutuhkan Laravel, termasuk cURL, DOM, Fileinfo, Mbstring, OpenSSL, PDO MySQL, dan XML.

### Contoh virtual host Nginx

Contoh ini untuk direktori development di atas. Ganti domain dan socket PHP-FPM sesuai server, lalu aktifkan sertifikat HTTPS dan pengalihan HTTP ke HTTPS melalui konfigurasi TLS server Anda. Jangan memakai blok HTTP ini sendirian untuk production.

```nginx
server {
    listen 80;
    server_name dnydev.example.com;
    root /var/www/html/dnydev/public_html/public;
    index index.php;
    charset utf-8;

    client_max_body_size 16m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 180s;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Upload media memakai chunk dan proses penyelesaian yang dapat menjalankan FFmpeg secara sinkron. Sesuaikan `client_max_body_size`, `fastcgi_read_timeout`, dan timeout PHP-FPM dengan ukuran request serta `MEDIA_COMPRESSION_TIMEOUT_SECONDS` (contoh `.env.example`: 120 detik). Pastikan socket Nginx sama dengan socket pool PHP-FPM dan user pool dapat menulis `storage` serta `bootstrap/cache`. Sebelum reload, validasi konfigurasi dengan `nginx -t`; kemudian reload Nginx melalui mekanisme service server. Contoh Nginx ini mengikuti pola [panduan deploy Laravel 13](https://laravel.com/framework/docs/deployment) dan [dokumentasi FastCGI Nginx](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html).

## Sebelum deploy

1. Pastikan branch/commit yang akan dipasang dan periksa perubahan lokal di server (`git status --short`). Jangan menimpa perubahan yang belum dicadangkan.
2. Cadangkan database dan `.env` di lokasi aman di luar document root. Simpan juga release/asset sebelumnya untuk rollback kode.
3. Pastikan `storage` dan `bootstrap/cache` dapat ditulis oleh user PHP/web server; jangan gunakan izin `777`.
4. Siapkan konfigurasi `.env` sesuai lingkungan: `APP_ENV`, `APP_DEBUG=false`, `APP_URL` HTTPS, `APP_TIMEZONE=Asia/Jakarta`, database, `QUEUE_CONNECTION=database`, `DB_QUEUE_RETRY_AFTER=3700`, mailer, serta kredensial STC. Rahasiakan semua password dan token.
5. Pastikan `APP_KEY` dan `JWT_SECRET` yang sudah dipakai **tetap sama** saat deploy ulang. Menggantinya dapat memutus sesi/token atau membuat data terenkripsi tidak terbaca.

## Instalasi pertama pada database baru

Isi `.env` lebih dahulu. `DNY_INITIAL_ADMIN_PASSWORD` dan `DNY_INITIAL_MEMBER_PASSWORD` harus minimal 8 karakter serta mengandung huruf besar, huruf kecil, dan angka. Tinjau juga identitas, rekening, alamat, dan produk yang dibuat `DatabaseSeeder` sebelum memakainya di lingkungan nyata.

```bash
cd /path/ke/backend
composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan key:generate --no-interaction
php artisan jwt:secret --no-interaction
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
php artisan storage:link --no-interaction
php artisan optimize
```

Jalankan `key:generate` dan `jwt:secret` **hanya pada instalasi pertama** jika secret belum ada. Jangan jalankan `DevelopmentSeeder` di staging/production; seeder itu khusus data simulasi. Jangan gunakan `migrate:fresh` pada database yang berisi data.

## Deploy pembaruan

Setelah backup dan pemeriksaan status Git, aktifkan mode maintenance **sebelum** mengganti kode pada direktori yang sedang melayani trafik. Contoh berikut untuk checkout Git yang sudah berada pada branch yang disetujui; bila memakai paket release, ganti baris `git pull` dengan proses aktivasi release tersebut.

```bash
cd /path/ke/backend
php artisan down
git pull --ff-only
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan config:clear
npm ci
npm run build
php artisan migrate --force --no-interaction
php artisan optimize
php artisan queue:restart
php artisan up
```

Jika ada perintah yang gagal, **jangan lanjutkan ke `php artisan up` sebelum penyebabnya diperiksa**, kecuali sedang memulihkan layanan dengan release lama. Jangan jalankan `php artisan db:seed` penuh pada setiap deploy: seeder tersebut juga mengisi/memperbarui data awal. Untuk perubahan menu admin seperti pada pembaruan ini, setelah backup jalankan secara khusus:

```bash
php artisan db:seed --class=AccessControlSeeder --force --no-interaction
```

`AccessControlSeeder` menyelaraskan menu dan hak akses Administrator, menghapus menu yang tidak lagi tercantum di seeder, serta memperbarui level/grup member. Tinjau perubahan tersebut sebelum menjalankannya pada database yang sudah aktif.

**Catatan production:** Telescope berada di `require-dev`, tetapi `routes/console.php` masih menjadwalkan `telescope:prune` setiap hari. Jika akan memasang dependency dengan `composer install --no-dev`, jadwal itu harus dibuat kondisional atau dinonaktifkan dahulu agar scheduler tidak memanggil command yang tidak tersedia. Perintah `composer install` di atas mengikuti keadaan repo saat ini; jangan menganggap konfigurasi `--no-dev` sudah siap tanpa pengecekan ini.

## Scheduler dan queue

`schedule:run` dan `queue:work` adalah dua proses **terpisah**. Scheduler menjalankan `rewards:close-month` tanggal 1 pukul 00:05 dan `members:apply-upgrade-downgrade` setiap hari pukul 00:20 (timezone aplikasi `Asia/Jakarta`). Queue worker memproses pekerjaan asynchronous, termasuk email reset password yang tertunda. Periksa isi antrean sebelum mengaktifkan worker pada server yang sudah memiliki data.

Jika server memakai cron dan path-nya benar-benar `/var/www/html/dnydev/public_html`, contoh crontab berikut dapat dipakai. Ganti seluruh path untuk lingkungan lain; pastikan `/usr/bin/php8.4` dan `/usr/bin/flock` tersedia di server.

```cron
* * * * * cd /var/www/html/dnydev/public_html && /usr/bin/php8.4 artisan schedule:run >> /var/www/html/dnydev/public_html/storage/logs/schedule-cron.log 2>&1
* * * * * /usr/bin/flock -n /tmp/dnydev-queue.lock /usr/bin/php8.4 /var/www/html/dnydev/public_html/artisan queue:work database --queue=default --stop-when-empty --tries=3 --timeout=3600 >> /var/www/html/dnydev/public_html/storage/logs/queue-cron.log 2>&1
```

Gunakan nama lock yang berbeda untuk tiap lingkungan. Worker memiliki timeout maksimum 3600 detik; `DB_QUEUE_RETRY_AFTER` harus **lebih besar** (contoh repo: 3700 detik) agar job tidak diambil ulang saat masih berjalan. Jika tersedia process manager seperti Supervisor, worker persisten lebih sesuai daripada cron kedua; tetap jalankan cron scheduler setiap menit dan restart worker melalui `queue:restart` setiap deploy.

## Verifikasi setelah deploy

```bash
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
```

Periksa juga `public/build/manifest.json`, `public/storage`, endpoint `/up`, halaman login admin dan member, serta `storage/logs/laravel.log` dan log cron. Uji satu alur yang relevan dengan release (misalnya login atau transaksi), bukan hanya respons HTTP halaman awal. Jika ada job gagal, periksa penyebabnya sebelum menjalankan ulang; jangan melakukan retry massal tanpa menilai efek samping transaksi atau email.

## Pemulihan

Jika deploy gagal, pasang kembali release kode dan aset build sebelumnya, bersihkan/bangun ulang cache aplikasi untuk release tersebut, lalu restart worker. Migrasi database tidak selalu aman untuk dibalik otomatis; pulihkan dari backup atau lakukan perbaikan terencana sesuai perubahan skema. Jangan gunakan `git reset --hard`, `migrate:fresh`, atau penghapusan data sebagai langkah rollback rutin.
