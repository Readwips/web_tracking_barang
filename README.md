# LogiTrack AI

LogiTrack AI adalah aplikasi web untuk mengelola dan melacak perjalanan kontainer. Pelanggan dapat melihat status pengiriman tanpa login, sedangkan admin dan operator dapat mengelola data pengiriman melalui dashboard.

## Fitur

- Tracking publik menggunakan nomor kontainer.
- Informasi status, rute, ETA, dan riwayat perjalanan.
- Dashboard untuk admin dan operator.
- Pengelolaan booking, kontainer, pelanggan, kapal, pelabuhan, dan jadwal.
- Aksi cepat untuk mencatat pembaruan, keterlambatan, dan kedatangan.
- Notifikasi keterlambatan melalui email atau webhook.
- AI Assistant untuk merapikan catatan operasional dan menyusun pesan pelanggan.
- REST API untuk kebutuhan integrasi.

## Teknologi

- PHP 8.2+
- Laravel 12
- PostgreSQL atau MySQL
- Blade, Tailwind CSS, dan Alpine.js
- Vite
- OpenAI API (opsional)

## Instalasi Lokal

Clone repository dan instal dependensi:

```bash
git clone https://github.com/Readwips/web_tracking_barang.git
cd web_tracking_barang
composer install
npm install
```

Salin konfigurasi environment:

```bash
copy .env.example .env
php artisan key:generate
```

Atur koneksi database pada file `.env`, lalu jalankan:

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

Aplikasi dapat diakses melalui `http://127.0.0.1:8000`.

Untuk pengembangan frontend, jalankan pada terminal terpisah:

```bash
npm run dev
```

## Akun Demo

| Peran | Email | Password |
| --- | --- | --- |
| Admin | `admin@logitrack.test` | `password` |
| Operator | `operator@logitrack.test` | `password` |
| Pelanggan | `pelanggan@logitrack.test` | `password` |

Nomor kontainer yang dapat digunakan untuk mencoba tracking:

- `TANTO-CT-000124`
- `TANTO-CT-000125`

Akun dan data tersebut hanya digunakan untuk pengujian. Ganti kredensial bawaan sebelum aplikasi digunakan pada lingkungan production.

## Konfigurasi AI

Tambahkan API key OpenAI pada file `.env` jika ingin menggunakan fitur AI:

```env
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4.1-mini
```

Jika API key tidak tersedia, aplikasi tetap dapat berjalan menggunakan pesan bawaan.

## Pengujian

```bash
php artisan test
npm run build
vendor/bin/pint --test
```

## Deployment

Aplikasi saat ini dapat dijalankan di Vercel dengan database PostgreSQL dari Neon. Konfigurasi production disimpan melalui Environment Variables di Vercel dan tidak boleh dimasukkan ke repository.

Pastikan variabel utama berikut sudah tersedia:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-aplikasi.vercel.app
DB_CONNECTION=pgsql
DB_SSLMODE=require
SESSION_DRIVER=database
CACHE_STORE=array
QUEUE_CONNECTION=sync
```

Detail koneksi database, `APP_KEY`, dan API key harus disimpan sebagai secret pada platform deployment.

## API

Contoh request REST API tersedia pada:

```text
postman/LogiTrackAI.postman_collection.json
```
