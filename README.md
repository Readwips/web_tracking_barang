# LogiTrack AI

LogiTrack AI adalah aplikasi web untuk melacak perjalanan kontainer dan mengelola operasional pengiriman. Pelanggan dapat melihat status, ETA, dan timeline tanpa login, sedangkan admin serta operator dapat memperbarui pengiriman melalui dashboard.

## Fitur utama

- Tracking publik menggunakan nomor kontainer.
- Timeline perjalanan, rute, ETA, status terakhir, dan peringatan keterlambatan.
- Dashboard operasional dengan akses berdasarkan peran admin, operator, dan pelanggan.
- Aksi cepat untuk melaporkan keterlambatan, menandai kedatangan, dan menambah pembaruan.
- Deteksi terlambat berdasarkan laporan petugas atau ETA yang sudah terlewati.
- Notifikasi melalui email dan webhook apabila kanal pengiriman dikonfigurasi.
- AI opsional untuk membantu menyusun pesan pelanggan dan ringkasan operasional.

AI tidak menentukan apakah pengiriman terlambat. Kondisi tersebut selalu dihitung dari data pengiriman atau laporan petugas agar hasilnya konsisten dan dapat diperiksa.

## Teknologi

- PHP 8.2 atau lebih baru
- Laravel 12
- Blade, Tailwind CSS, dan Alpine.js
- Vite dan Node.js 22
- MySQL sebagai database utama
- Laravel Queue dan Scheduler
- OpenAI API opsional

## Menjalankan secara lokal

Clone repository dan instal dependency:

```bash
git clone https://github.com/Readwips/web_tracking_barang.git
cd web_tracking_barang
composer install
npm install
```

Salin `.env.example` menjadi `.env`, lalu sesuaikan koneksi `DB_*` dengan database lokal. Setelah itu jalankan:

```bash
php artisan key:generate
php artisan migrate --seed
npm run build
```

Jalankan aplikasi:

```bash
composer run dev
```

Pada terminal terpisah, jalankan scheduler agar pemeriksaan keterlambatan aktif:

```bash
php artisan schedule:work
```

Aplikasi dapat dibuka melalui `http://127.0.0.1:8000`. Halaman tracking publik tersedia di `/tracking`.

### Menjalankan di Windows dengan klik

Untuk penggunaan lokal di Windows, tersedia file launcher:

- `start-logitrack-web.bat` menjalankan web dan Vite.
- `start-logitrack-all.bat` menjalankan web, Vite, queue, dan scheduler agar notifikasi keterlambatan ikut diproses.

Double-click salah satu file tersebut dari folder project. Setelah aktif, buka `http://127.0.0.1:8000`.

## Akun dan data demo

Seeder menyediakan akun berikut untuk pengembangan lokal:

| Peran | Email | Password |
| --- | --- | --- |
| Admin | `admin@logitrack.test` | `password` |
| Operator | `operator@logitrack.test` | `password` |
| Pelanggan | `pelanggan@logitrack.test` | `password` |

Nomor kontainer yang dapat dicoba:

- `TANTO-CT-000124`
- `TANTO-CT-000125`

Semua akun dan data tersebut hanya untuk demo. Ganti atau hapus kredensial bawaan sebelum menggunakan aplikasi di production.

## Keterlambatan dan notifikasi

Pengiriman dianggap terlambat ketika petugas memilih **Laporkan keterlambatan** atau mulai hari setelah ETA terlewati, selama pengiriman belum tiba atau selesai. Tracking publik diperbarui segera setelah perubahan disimpan.

Email dan webhook memerlukan penerima yang valid, queue worker, scheduler, serta konfigurasi kanal di environment. Konfigurasi bawaan menggunakan `MAIL_MAILER=log`, sehingga email hanya ditulis ke log dan tidak masuk ke inbox.

Untuk pengiriman email nyata, atur variabel `MAIL_*` dan `DELAY_ALERT_EMAILS` berdasarkan contoh pada [`.env.example`](.env.example). `OPENAI_API_KEY` tidak wajib karena aplikasi memiliki pesan fallback lokal.

## REST API

Tracking satu pengiriman tersedia tanpa login melalui endpoint `GET /api/shipments/{nomor-kontainer}`. Endpoint operasional dilindungi autentikasi dan hanya dapat digunakan oleh admin atau operator. Gunakan HTTPS dan kredensial production yang aman saat menghubungkan sistem lain.

Contoh request tersedia pada [`postman/LogiTrackAI.postman_collection.json`](postman/LogiTrackAI.postman_collection.json).

## Production

Sebelum deployment:

- Gunakan kredensial database, email, dan akun pengguna yang sebenarnya.
- Simpan semua secret hanya di `.env`; jangan commit file tersebut.
- Set `APP_ENV=production`, `APP_DEBUG=false`, dan gunakan HTTPS.
- Jalankan `php artisan migrate --force` dan `npm run build`.
- Jalankan queue worker secara terus-menerus dan panggil `php artisan schedule:run` setiap menit.

## Pengujian

```bash
composer test
npm run build
vendor/bin/pint --test
```
