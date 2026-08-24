# LogiTrack AI 🚢📦

Halo! Selamat datang di repositori **LogiTrack AI**. Ini adalah aplikasi web yang dibangun untuk membantu dalam mengelola dan melacak perjalanan kontainer logistik.

## Kenapa LogiTrack AI?
Sering kali di lapangan, petugas harus mengetik pembaruan status dengan buru-buru, misalnya: *"Barang ketahan ujan di perak nih"*. Dengan integrasi AI di sistem ini, kalimat kasar semacam itu akan otomatis dikonversi menjadi bahasa resmi yang pantas dibaca oleh pelanggan saat mereka memeriksa *tracking*. Selain itu, sistem ini juga secara otomatis mendeteksi apabila kontainer mengalami keterlambatan dari perkiraan waktu tiba (ETA).

### 🌟 Fitur Utama
*   **Tracking Publik Cepat:** Pelanggan hanya perlu memasukkan nomor kontainer untuk melihat *timeline* perjalanan, detail rute, serta perkiraan tiba (ETA).
*   **Dashboard Operasional:** Akses kontrol khusus bagi Admin dan Operator. Dilengkapi dengan ringkasan data serta fitur *Quick Action* untuk memperbarui status (seperti "Menandai Tiba" atau "Melaporkan Keterlambatan") dalam sekali klik.
*   **AI Assistant:** Membantu menyusun pemberitahuan pelanggan secara otomatis, merapikan bahasa lapangan petugas, serta menyajikan ringkasan kondisi operasional (jumlah pengiriman aktif, kendala, dsb.) secara otomatis.
*   **Sistem Notifikasi (Kustom):** Tersedia pengaturan untuk pengiriman peringatan *delay* via Email atau *Webhook*.
*   **REST API:** Dilengkapi *endpoint* khusus yang siap diintegrasikan dengan aplikasi mobile maupun layanan perangkat lunak pihak ketiga lainnya.

## 🛠️ Teknologi yang Digunakan
*   **Backend:** PHP 8.2 & Laravel 12
*   **Frontend:** Blade, Tailwind CSS, Alpine.js (dibangun menggunakan Vite)
*   **Database:** MySQL (Bisa menggunakan SQLite untuk mode *local development*)
*   **Infrastruktur Tambahan:** Laravel Queue, Scheduler, dan API OpenAI.

---

## 🚀 Cara Menjalankan Aplikasi di Komputer Anda

Untuk menguji dan menjalankan aplikasi ini di sistem lokal Anda, silakan ikuti langkah-langkah berikut:

**1. Clone dan Instalasi**
```bash
git clone https://github.com/Readwips/web_tracking_barang.git
cd web_tracking_barang
composer install
npm install
```

**2. Persiapan Konfigurasi Database**
Salin file `.env.example` dan ubah namanya menjadi `.env`. Jika Anda ingin menggunakan konfigurasi SQLite agar lebih praktis di tahap *development*, ubah nilai koneksi menjadi `DB_CONNECTION=sqlite`, lalu buat file kosong dengan nama `database.sqlite` di dalam direktori `database/`. 

Selanjutnya, siapkan *Key* Laravel dan jalankan migrasi database:
```bash
php artisan key:generate
php artisan migrate --seed
npm run build
```

**3. Menjalankan Aplikasi**
Buka 2 jendela terminal secara bersamaan:
Terminal pertama (untuk server Laravel):
```bash
php artisan serve
```
Terminal kedua (untuk kompilasi aset Frontend/Vite):
```bash
npm run dev
```

*Catatan: Bagi pengguna Windows, telah disediakan skrip automasi (`.bat`). Anda cukup klik dua kali pada `start-logitrack-web.bat`, atau `start-logitrack-all.bat` (jika Anda juga ingin mengaktifkan layanan antrean pesan dan scheduler notifikasinya secara bersamaan).*

Aplikasi sekarang dapat diakses melalui `http://127.0.0.1:8000`.

---

## 🔑 Akun Uji Coba (Demo)

Dengan menjalankan perintah `--seed` pada tahapan instalasi di atas, sistem telah otomatis menyediakan akun pengujian untuk mengakses Dashboard:

| Peran | Email | Password |
| :--- | :--- | :--- |
| **Admin** | `admin@logitrack.test` | `password` |
| **Operator** | `operator@logitrack.test` | `password` |
| **Pelanggan** | `pelanggan@logitrack.test` | `password` |

Untuk menguji halaman **Tracking Publik**, Anda dapat menggunakan beberapa contoh nomor kontainer berikut:
*   `TANTO-CT-000124`
*   `TANTO-CT-000125`

*(Penting: Hapus atau ubah seluruh kredensial pengujian ini jika Anda berencana mengimplementasikan aplikasi ini pada lingkungan Production.)*

---

## 🤖 Catatan Terkait Fitur AI (OpenAI API)

Agar fitur asisten AI (seperti koreksi gaya bahasa laporan dan penyusunan ringkasan *dashboard*) dapat berfungsi sepenuhnya, Anda memerlukan kunci API (API Key) dari OpenAI.

Silakan buka file `.env` di aplikasi Anda dan tambahkan kredensial berikut:
```env
OPENAI_API_KEY=sk-xxxx-masukkan-api-key-anda-disini
```

Sistem dibangun dengan toleransi terhadap kegagalan layanan (*fault-tolerant*). Jika `OPENAI_API_KEY` dikosongkan atau layanan API sedang tidak tersedia, aplikasi **tidak akan mengalami error**. Sistem akan otomatis beralih ke fungsi *fallback* bawaan (menyimpan catatan petugas tanpa diubah bahasanya dan menggunakan laporan standar).

---

## 🧪 Pengujian Otomatis (Testing)

Aplikasi ini telah dilengkapi dengan kurang lebih 90 skenario pengujian otomatis (*Automated Tests*) untuk menjamin stabilitas fungsionalitas Backend. Anda dapat memeriksanya dengan menjalankan perintah:
```bash
php artisan test
```

## 📬 API & Postman
Bagi Anda yang ingin bereksperimen dengan REST API LogiTrack, sampel koleksi interaksi juga disertakan pada direktori `postman/LogiTrackAI.postman_collection.json`.

---

Semoga sistem aplikasi pelacakan ini dapat bermanfaat untuk manajemen logistik, serta menjadi referensi pembelajaran yang baik mengenai integrasi Laravel, Tailwind, dan OpenAI. Apabila Anda menemukan celah kesalahan (*bug*) atau memiliki gagasan pengembangan fitur, silakan buat *Issue* atau kirimkan *Pull Request*. Selamat mencoba!
