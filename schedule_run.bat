@echo off
cd /d C:\laragon\www\ocobo-back
php artisan schedule:run >> nul 2>&1
