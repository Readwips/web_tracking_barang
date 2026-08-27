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

## Alat

- PHP 8.2+
- Laravel 12
- PostgreSQL atau MySQL
- Blade, Tailwind CSS, dan Alpine.js
- Vite
- OpenAI API

## Konfigurasi Vercel

Aplikasi dijalankan di Vercel dan menggunakan PostgreSQL dari Neon. Hubungkan repository ini ke Vercel, kemudian tambahkan konfigurasi berikut melalui menu **Settings → Environment Variables**:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-aplikasi.vercel.app
APP_TIMEZONE=Asia/Jakarta
APP_KEY=
LOG_CHANNEL=stderr
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
DB_SSLMODE=require
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=array
QUEUE_CONNECTION=sync
BCRYPT_ROUNDS=12
```

Isi `APP_KEY` dan detail `DB_*` sesuai konfigurasi aplikasi dan database Neon. Simpan seluruh informasi rahasia sebagai Environment Variables dan jangan memasukkannya ke repository.

Setelah konfigurasi disimpan, lakukan redeploy tanpa menggunakan build cache.

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

Tambahkan variabel berikut di Vercel jika ingin menggunakan fitur AI:

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

## API

Contoh request REST API tersedia pada:

```text
postman/LogiTrackAI.postman_collection.json
```
