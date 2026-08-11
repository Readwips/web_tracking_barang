@echo off
setlocal
title LogiTrack AI - Launcher
cd /d "%~dp0"

if not exist artisan (
    echo File artisan tidak ditemukan. Jalankan file ini dari folder project LogiTrack AI.
    pause
    exit /b 1
)

where php >nul 2>nul
if errorlevel 1 (
    echo PHP tidak ditemukan di PATH. Install PHP atau buka lewat terminal yang sudah mengenali php.
    pause
    exit /b 1
)

where npm >nul 2>nul
if errorlevel 1 (
    echo npm tidak ditemukan di PATH. Install Node.js atau buka lewat terminal yang sudah mengenali npm.
    pause
    exit /b 1
)

if not exist .env (
    echo File .env belum ada. Salin .env.example menjadi .env lalu atur database.
    pause
    exit /b 1
)

if not exist vendor\autoload.php (
    echo Folder vendor belum ada. Jalankan: composer install
    pause
    exit /b 1
)

if not exist node_modules (
    echo Folder node_modules belum ada. Jalankan: npm install
    pause
    exit /b 1
)

start "LogiTrack AI - Web" cmd /k "cd /d ""%~dp0"" && php artisan serve --host=127.0.0.1 --port=8000"
start "LogiTrack AI - Queue" cmd /k "cd /d ""%~dp0"" && php artisan queue:work --tries=1"
start "LogiTrack AI - Scheduler" cmd /k "cd /d ""%~dp0"" && php artisan schedule:work"
start "LogiTrack AI - Vite" cmd /k "cd /d ""%~dp0"" && npm run dev"

timeout /t 3 /nobreak >nul
start "" "http://127.0.0.1:8000"

echo LogiTrack AI sudah dibuka.
echo.
echo Jendela yang aktif:
echo - Web: membuka http://127.0.0.1:8000
echo - Queue: memproses notifikasi
echo - Scheduler: mengecek keterlambatan otomatis
echo - Vite: asset frontend
echo.
echo Untuk mematikan semuanya, tutup semua jendela LogiTrack AI atau tekan Ctrl+C pada masing-masing.
pause
