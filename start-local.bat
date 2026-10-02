@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo Starting ds_shop on http://127.0.0.1:8080 ...
echo Admin: http://127.0.0.1:8080/admin/login.php  (admin / 123456)
echo API Dock: http://127.0.0.1:8080/admin/api_dock.php
echo.
echo NOTE: Also run start-supplier.bat (port 8081) for real API docking.
echo Press Ctrl+C to stop.
"C:\tools\php74\php.exe" -d display_errors=0 -S 127.0.0.1:8080 router.php
