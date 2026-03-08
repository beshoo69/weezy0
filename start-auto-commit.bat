@echo off
REM تشغيل Auto-Commit Script
REM هذا الملف يشغل السكريبت بشكل سهل

cls
echo.
echo ================================================
echo   🚀 Auto-Commit and Auto-Deploy Script
echo ================================================
echo.
echo هذا السكريبت سيراقب التغييرات ويرفعها تلقائياً
echo والـ Webhook سيقوم بالنشر على السيرفر
echo.
echo اضغط اي مفتاح للبدء...
pause

powershell -NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\fayez-movie\auto-commit.ps1"

pause
