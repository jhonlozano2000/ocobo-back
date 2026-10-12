@echo off
cd /d C:\laragon\www\ocobo-back
php artisan schedule:run >> storage\logs\scheduler.log 2>&1
