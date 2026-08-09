# LogiTrack AI

LogiTrack AI adalah aplikasi web untuk membantu mengelola dan memantau pengiriman kontainer. Pelanggan dapat mengecek perjalanan kontainer, sedangkan admin dan petugas operasional dapat mengelola data serta memperbarui proses pengiriman.

## Yang Bisa Dilakukan

- Melacak kontainer tanpa login menggunakan nomor kontainer dan melihat riwayat perjalanannya.
- Mengelola pengiriman, mulai dari nomor booking, pelanggan, kontainer, kapal, rute, jadwal, estimasi tiba, hingga status pengiriman.
- Memperbarui status serta menambahkan lokasi dan catatan pada timeline pengiriman.
- Mengelola data pelanggan, kapal, pelabuhan, kontainer, jenis barang, dan jadwal keberangkatan.
- Melihat dashboard operasional yang menampilkan pengiriman aktif, selesai, terlambat, rute tersibuk, dan grafik pengiriman bulanan.
- Membatasi akses berdasarkan peran admin, operator, dan pelanggan. Pelanggan hanya dapat melihat pengiriman miliknya.
- Mengakses data pengiriman melalui REST API.
- Membuat draft pemberitahuan pelanggan dan ringkasan operasional dengan bantuan AI. Draft pada halaman AI Assistant tidak dikirim otomatis; aplikasi tetap dapat memberikan hasil sederhana saat layanan AI tidak tersedia.
- Mendeteksi pengiriman yang melewati ETA dan mengirim pemberitahuan otomatis melalui email pelanggan, email operasional, dan webhook generik.

## Notifikasi keterlambatan

Pengiriman dianggap terlambat mulai hari setelah tanggal `estimated_arrival` apabila belum memiliki `actual_arrival` dan statusnya belum `Tiba di pelabuhan tujuan` atau `Selesai`. Pemeriksaan dijalankan setiap 15 menit oleh Laravel Scheduler.

Halaman tracking publik juga menampilkan banner keterlambatan, ETA, jumlah hari terlambat, dan status terakhir secara langsung. Banner dihitung dari data shipment sehingga dapat dilihat tanpa menunggu email atau webhook.

AI hanya digunakan untuk menyusun kalimat pendamping yang netral dan empatik; detail booking, kontainer, rute, ETA, jumlah hari, dan status selalu diambil langsung dari database saat pesan dikirim. Data pengiriman tersebut tidak dikirim ke AI untuk notifikasi otomatis. Keputusan apakah pengiriman terlambat juga tetap dihitung dari database. Jika `OPENAI_API_KEY` kosong atau layanan AI gagal, aplikasi memakai pesan lokal sehingga notifikasi tetap berjalan.

Setiap tujuan dicatat per pengiriman, ETA, kanal, dan penerima. Scheduler dapat dijalankan berulang tanpa mengirim pesan yang sama berkali-kali. Perubahan ETA membuat kejadian notifikasi baru, sedangkan job lama dibatalkan jika pengiriman sudah tiba, selesai, atau ETA-nya berubah sebelum pesan dikirim.

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

## Rencana ke depan

Fitur berikut merupakan rencana pengembangan dan belum tersedia pada versi saat ini:

- [x] Notifikasi otomatis keterlambatan melalui email atau webhook.
- [ ] Notifikasi setiap perubahan status dan integrasi WhatsApp langsung.
- [ ] Pelacakan posisi kontainer secara real-time melalui GPS dan peta.
- [ ] Pencarian, filter, dan ekspor laporan pengiriman ke PDF atau Excel.
- [ ] Manajemen akun dan hak akses yang lebih lengkap.
- [ ] Autentikasi token dan pembatasan akses pada REST API.
