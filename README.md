# LogiTrack AI

Sistem manajemen pengiriman dan tracking kontainer berbasis Laravel, Blade, Tailwind, MySQL, REST API, Chart.js, dan AI assistant.

## Fitur

- Login Laravel Breeze dengan role `admin`, `operator`, dan `customer`.
- CRUD master data: pelanggan, kapal, pelabuhan, kontainer, jenis barang, dan jadwal keberangkatan.
- Transaksi pengiriman dengan booking number, kontainer, pelanggan, rute, kapal, tanggal, ETA, dan status.
- Tracking publik berdasarkan nomor kontainer dengan timeline histori.
- Dashboard operasional: aktif, selesai, terlambat, pelanggan, rute terbanyak, grafik bulanan Chart.js.
- REST API: list, detail tracking, create shipment, update status.
- AI assistant untuk draft pesan pelanggan dan ringkasan operasional. Jika `OPENAI_API_KEY` kosong, aplikasi memakai fallback lokal agar demo tetap berjalan.

## Setup Lokal

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Untuk verifikasi cepat tanpa MySQL, ubah `.env` menjadi:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

## Akun Demo

Semua password: `password`

- Admin: `admin@logitrack.test`
- Petugas: `operator@logitrack.test`
- Pelanggan: `pelanggan@logitrack.test`

Nomor kontainer demo: `TANTO-CT-000124`

## REST API

```http
GET /api/shipments
GET /api/shipments/TANTO-CT-000124
POST /api/shipments
PUT /api/shipments/{id}/status
```

Koleksi Postman tersedia di `postman/LogiTrackAI.postman_collection.json`.

## AI

Tambahkan key berikut di `.env` untuk memakai OpenAI API:

```env
OPENAI_API_KEY=isi_api_key
OPENAI_MODEL=gpt-4.1-mini
```

## Testing

```bash
php artisan test
```
