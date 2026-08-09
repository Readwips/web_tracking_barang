# LogiTrack AI

LogiTrack AI adalah aplikasi web untuk membantu mengelola dan memantau pengiriman kontainer. Pelanggan dapat mengecek perjalanan kontainer, sedangkan admin dan petugas operasional dapat mengelola data serta memperbarui proses pengiriman.

## Yang Bisa Dilakukan

- Melacak kontainer tanpa login menggunakan nomor kontainer dan melihat riwayat perjalanannya.
- Mengelola pengiriman, mulai dari nomor booking, pelanggan, kontainer, kapal, rute, jadwal, estimasi tiba, hingga status pengiriman.
- Memperbarui status serta menambahkan lokasi dan catatan pada timeline pengiriman.
- Mengelola data pelanggan, kapal, pelabuhan, kontainer, jenis barang, dan jadwal keberangkatan.
- Melihat dashboard operasional yang menampilkan pengiriman aktif, selesai, terlambat, rute tersibuk, dan grafik pengiriman bulanan.
- Membatasi akses berdasarkan peran admin, operator, dan pelanggan. Pelanggan hanya dapat melihat pengiriman miliknya.
- Mengakses tracking publik dan endpoint operasional terautentikasi melalui REST API.
- Membuat draft pemberitahuan pelanggan dan ringkasan operasional dengan bantuan AI. Draft pada halaman AI Assistant tidak dikirim otomatis; aplikasi tetap dapat memberikan hasil sederhana saat layanan AI tidak tersedia.
- Mendeteksi pengiriman yang melewati ETA atau dilaporkan terlambat oleh petugas, lalu mengirim pemberitahuan otomatis melalui email pelanggan, email operasional, dan webhook generik.

## Aksi cepat pengiriman

Pada halaman **Edit Pengiriman**, admin dan operator tidak perlu mengubah seluruh data booking untuk pembaruan sehari-hari. Gunakan panel **Aksi cepat** untuk:

- **Laporkan keterlambatan**: mencatat kondisi terlambat, menambahkan histori netral, memperbarui tracking publik, dan menyiapkan notifikasi tanpa mengubah ETA atau status perjalanan.
- **Tandai tiba**: mencatat tanggal kedatangan, mengubah status menjadi `Tiba di pelabuhan tujuan`, dan menutup kondisi keterlambatan.
- **Tambah pembaruan**: menambahkan lokasi atau catatan timeline tanpa mengubah fakta booking.
- **Tutup laporan keterlambatan**: menghapus laporan manual; apabila ETA sudah lewat, deteksi otomatis tetap menganggap pengiriman terlambat.

Booking, pelanggan, kontainer, rute, kapal, jadwal, dan tanggal tetap dapat diubah melalui bagian **Detail lanjutan**. Pemisahan ini mencegah pembaruan status sederhana menimpa data inti yang sudah tersimpan.

## Notifikasi keterlambatan

Pengiriman dianggap terlambat apabila dilaporkan melalui Aksi cepat atau mulai hari setelah tanggal `estimated_arrival`, selama belum memiliki `actual_arrival` dan statusnya belum `Tiba di pelabuhan tujuan` atau `Selesai`. Laporan manual disimpan terpisah dari status perjalanan dan tidak memundurkan ETA. Pemeriksaan notifikasi dijalankan setiap 15 menit oleh Laravel Scheduler.

Halaman tracking publik juga menampilkan banner keterlambatan, ETA, dan status terakhir secara langsung. Jumlah hari ditampilkan hanya setelah ETA benar-benar terlewati; laporan sebelum ETA diberi keterangan `Keterlambatan dilaporkan` agar tidak membuat klaim waktu yang keliru. Banner dapat dilihat tanpa menunggu email atau webhook.

AI hanya digunakan untuk menyusun kalimat pendamping yang netral dan empatik; detail booking, kontainer, rute, ETA, jumlah hari, dan status selalu diambil langsung dari database saat pesan dikirim. Data pengiriman tersebut tidak dikirim ke AI untuk notifikasi otomatis. Keputusan apakah pengiriman terlambat juga tetap dihitung dari database. Jika `OPENAI_API_KEY` kosong atau layanan AI gagal, aplikasi memakai pesan lokal sehingga notifikasi tetap berjalan.

Setiap tujuan dicatat per pengiriman, ETA, siklus laporan, kanal, dan penerima. Scheduler dapat dijalankan berulang tanpa mengirim pesan yang sama berkali-kali. Menutup lalu melaporkan keterlambatan lagi membuat siklus notifikasi baru, sedangkan job dari siklus lama dibatalkan. Perubahan ETA juga membuat kejadian baru; job lama dibatalkan jika pengiriman sudah tiba, selesai, ETA berubah, atau laporan sudah digantikan oleh siklus yang lebih baru.

### Konfigurasi kanal

Salin `.env.example` menjadi `.env`, lalu atur mailer Laravel. Contoh SMTP:

```dotenv
APP_URL=https://tracking.example.com
APP_TIMEZONE=Asia/Jakarta

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_SCHEME=smtp
MAIL_FROM_ADDRESS=tracking@example.com
MAIL_FROM_NAME="LogiTrack AI"
```

Konfigurasi penerima notifikasi:

```dotenv
DELAY_ALERT_ENABLED=true
DELAY_ALERT_NOTIFY_CUSTOMER=true
DELAY_ALERT_EMAILS=operasional@example.com,supervisor@example.com
DELAY_ALERT_MAX_ATTEMPTS=5
DELAY_ALERT_RETRY_AFTER_MINUTES=60
DELAY_ALERT_PROCESSING_TIMEOUT_MINUTES=5

# Opsional: n8n, Make, Zapier, Slack bridge, WhatsApp gateway, dan sebagainya.
DELAY_ALERT_WEBHOOK_URL=https://automation.example.com/webhooks/logitrack-delay
DELAY_ALERT_WEBHOOK_SECRET=replace-with-a-long-random-secret
DELAY_ALERT_WEBHOOK_TIMEOUT=10
```

Email pelanggan diambil dari akun pengguna yang terhubung ke data pelanggan. `DELAY_ALERT_EMAILS` sebaiknya selalu diisi sebagai tujuan operasional dan fallback bagi pelanggan yang belum memiliki akun/email. Sebelum mengirim, worker memeriksa kembali apakah fitur, pengiriman, dan penerima masih aktif agar pesan antrean tidak dikirim ke alamat lama. Pengiriman yang gagal dicoba maksimal lima kali secara total dengan jeda redispatch 60 menit.

Mailer `log` dan `array` hanya cocok untuk development/testing dan tidak mengirim pesan ke inbox. Halaman Aksi cepat menampilkan peringatan apabila salah satu mode tersebut masih aktif; gunakan SMTP atau transport production lain untuk pengiriman nyata.

Webhook dikirim sebagai JSON, hanya menerima respons HTTP 2xx sebagai keberhasilan, tidak mengikuti redirect, dan pada production harus menggunakan HTTPS. Jika secret dikonfigurasi, payload ditandatangani pada header `X-LogiTrack-Signature` menggunakan HMAC SHA-256. Timeout HTTP dibatasi maksimum 30 detik agar tetap berada di bawah timeout queue job.

Rahasia SMTP, OpenAI, dan webhook hanya boleh disimpan di environment server dan tidak boleh di-commit.

### Menjalankan pemeriksaan

Setelah menjalankan migrasi, pemeriksaan dapat diuji tanpa mengubah data:

```bash
php artisan migrate
php artisan shipments:notify-delays --dry-run
```

Untuk mengirim langsung tanpa menunggu worker:

```bash
php artisan shipments:notify-delays --sync
```

Jika konfigurasi kanal sudah diperbaiki setelah suatu delivery mencapai batas percobaan, reset dan antrekan kembali secara eksplisit dengan:

```bash
php artisan shipments:notify-delays --retry-failed
```

Pada production, jalankan scheduler setiap menit dan queue worker secara terus-menerus:

```cron
* * * * * cd /path/to/logitrack && php artisan schedule:run >> /dev/null 2>&1
```

```bash
php artisan queue:work --tries=3
```

Gunakan process manager seperti Supervisor atau systemd untuk queue worker. Scheduler memakai lock cache bersama untuk mencegah proses tumpang tindih; konfigurasi default database cache sudah mendukung pola ini setelah migrasi dijalankan.

## Akses REST API

Tracking satu pengiriman tetap dapat dibaca tanpa login melalui `GET /api/shipments/{nomor-booking-atau-kontainer}`. Respons publik hanya berisi informasi tracking dan tidak mengekspos data pelanggan.

Daftar pengiriman, pembuatan booking, dan perubahan status dilindungi HTTP Basic Authentication serta hanya menerima akun `admin` atau `operator`:

```text
GET  /api/shipments
POST /api/shipments
PUT  /api/shipments/{id}/status
```

Semua endpoint API dibatasi maksimal 60 request per menit per client. Perubahan status wajib mengirim `expected_version` dari respons pengiriman terbaru. Apabila versi sudah berubah, API menolak request agar data lama tidak menimpa pembaruan operator lain. Untuk integrasi production berskala besar, ganti HTTP Basic dengan personal access token atau OAuth melalui HTTPS.

## Rencana ke depan

Fitur berikut merupakan rencana pengembangan dan belum tersedia pada versi saat ini:

- [x] Notifikasi otomatis keterlambatan melalui email atau webhook.
- [ ] Notifikasi setiap perubahan status dan integrasi WhatsApp langsung.
- [ ] Pelacakan posisi kontainer secara real-time melalui GPS dan peta.
- [ ] Pencarian, filter, dan ekspor laporan pengiriman ke PDF atau Excel.
- [ ] Manajemen akun dan hak akses yang lebih lengkap.
- [x] Pembatasan endpoint operasional REST API untuk admin dan operator.
- [ ] Personal access token atau OAuth untuk integrasi REST API production.
