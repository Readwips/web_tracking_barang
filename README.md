# LogiTrack AI 🚢📦

Halo! Selamat datang di repositori **LogiTrack AI**. Ini adalah aplikasi web yang saya bangun buat ngebantu ngelola dan melacak perjalanan kontainer logistik.

Idenya sederhana: pengen bikin sistem yang gampang dipakai pelanggan buat ngecek posisi barang mereka (tanpa perlu repot login), tapi di saat yang sama, ngasih alat yang powerful buat tim admin/operator di belakang layar buat *update* statusnya. Oh ya, saya juga nyelipin sedikit bantuan AI (pake OpenAI) biar kerjaan nulis catatan lapangan jadi lebih gampang dan rapi.

## Kenapa Bikin LogiTrack AI?
Sering kali kan, di lapangan petugas ngetik status tuh buru-buru. "Barang ketahan ujan di perak." Nah, berkat integrasi AI di sini, kalimat kasar kayak gitu bakal otomatis disulap jadi bahasa resmi yang pantes dibaca pelanggan pas mereka cek tracking. Selain itu, sistem ini juga otomatis ngedeteksi kalau kontainer telat dari ETA-nya.

### 🌟 Fitur Utama
*   **Tracking Publik Cepat:** Pelanggan tinggal masukin nomor kontainer, langsung keluar timeline perjalanan, rute, dan ETA-nya.
*   **Dashboard Operasional:** Buat admin & operator. Ada rangkuman data dan *quick action* buat update status (misal: "Barang Tiba" atau "Lapor Telat") cuma dengan sekali klik.
*   **AI Assistant:** Ngebantu nulis laporan resmi untuk pelanggan dan ngasih ringkasan kondisi operasional secara cerdas.
*   **Sistem Notifikasi:** (Bisa dikonfigurasi) buat ngirim email atau Webhook otomatis kalau ada kapal yang delay.
*   **REST API:** Ada endpoint-nya juga loh, kalau misal mau disambungin ke aplikasi lain atau mobile.

## 🛠️ Stack Teknologi yang Saya Pakai
*   **Backend:** PHP 8.2 & Laravel 12
*   **Frontend:** Blade, Tailwind CSS, Alpine.js (disatukan pakai Vite biar ngebut)
*   **Database:** MySQL (atau SQLite buat local dev)
*   **Tambahan:** Laravel Queue, Scheduler, dan API OpenAI.

---

## 🚀 Cara Menjalankan di Komputer Kamu

Kalo mau nyoba-nyoba jalanin aplikasinya di localhost, gampang banget. Ikuti langkah ini ya:

**1. Clone & Install**
```bash
git clone https://github.com/Readwips/web_tracking_barang.git
cd web_tracking_barang
composer install
npm install
```

**2. Siapin Database**
Copy file `.env.example` dan ubah namanya jadi `.env`. Kalo mau cepet pake SQLite, tinggal ubah `DB_CONNECTION=sqlite` trus bikin file kosong `database.sqlite` di folder `database/`. Jangan lupa siapin App Key-nya.

```bash
php artisan key:generate
php artisan migrate --seed
npm run build
```

**3. Jalanin Aplikasinya**
Buka 2 terminal ya:
Terminal pertama (buat jalanin server web):
```bash
php artisan serve
```
Terminal kedua (buat jalanin Vite/Frontend):
```bash
npm run dev
```

*Atau kalo kamu pengguna Windows, saya udah siapin file `.bat`! Cukup double-click `start-logitrack-web.bat` atau `start-logitrack-all.bat` (kalo mau jalanin bareng sistem notifikasinya).*

Aplikasi siap diakses di `http://127.0.0.1:8000`!

---

## 🔑 Akun Dummy (Buat Coba-coba)

Pas tadi kamu ngejalanin perintah `--seed`, saya udah nyiapin akun boongan biar kamu bisa langsung login ke Dashboard.

| Akses Sebagai | Email | Password |
| :--- | :--- | :--- |
| **Admin** | `admin@logitrack.test` | `password` |
| **Operator** | `operator@logitrack.test` | `password` |
| **Pelanggan** | `pelanggan@logitrack.test` | `password` |

Mau nyoba fitur **Tracking** di halaman depan? Cobain masukin nomor kontainer ini:
*   `TANTO-CT-000124`
*   `TANTO-CT-000125`

*(Tentu aja, jangan pake akun dan data ini kalo mau dinaikin ke production beneran ya!)*

---

## 🤖 Catatan soal Fitur AI (OpenAI)

Biar fitur "merapikan tulisan kasar" dan "pembuat ringkasan dashboard" bisa jalan, kamu butuh API Key dari OpenAI.
Tinggal buka file `.env` kamu, terus tambahin baris ini:
```env
OPENAI_API_KEY=sk-xxxx-apikey-kamu-disini
```
Tenang aja, kalau API key-nya kosong, sistem nggak bakal error kok. Dia cuma bakal balik ke fungsi bawaannya dan nyimpen teks asli yang kamu ketik secara aman (*fallback*).

---

## 🧪 Testing

Kalo kamu pengen ngecek *codingan* backend-nya aman atau nggak, saya udah siapin sekitar 90 pengujian otomatis (*Automated Tests*). Bisa langsung dijalankan pakai:
```bash
php artisan test
```

## 📬 API & Postman
Bagi yang butuh integrasi API, contoh request-nya udah saya sertain di dalam folder `postman/LogiTrackAI.postman_collection.json`.

---

Gitu aja dari saya. Semoga aplikasi ini bisa bermanfaat, baik dipakai beneran buat sistem logistik, maupun jadi bahan belajar bareng soal *Laravel*, *Tailwind*, dan integrasi AI sederhana! Kalo nemu bug atau punya ide fitur, boleh banget bikin *Issue* atau *Pull Request*. ✌️
