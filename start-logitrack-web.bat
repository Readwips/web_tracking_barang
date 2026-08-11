@echo off
setlocal
title LogiTrack AI - Web
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

start "LogiTrack AI - Vite" cmd /k "cd /d ""%~dp0"" && npm run dev"
start "" "http://127.0.0.1:8000"

echo Web LogiTrack AI berjalan di http://127.0.0.1:8000
echo Tutup server dengan Ctrl+C pada jendela ini.
php artisan serve --host=127.0.0.1 --port=8000

pause
