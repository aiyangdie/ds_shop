@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo ========================================
echo  ds_shop 主站（档位 A，只开这个就能卖货）
echo  http://127.0.0.1:8080/
echo  后台: http://127.0.0.1:8080/admin/login.php
echo ========================================
echo 可选：对接货源再运行 start-supplier.bat（8081）
echo 可选：打 APK 再运行 tools\appbuild\run_worker.bat
echo 按 Ctrl+C 停止
echo.
"C:\tools\php74\php.exe" -d display_errors=0 -S 127.0.0.1:8080 router.php
