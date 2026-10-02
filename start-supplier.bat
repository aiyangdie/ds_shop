@echo off
chcp 65001 >nul
cd /d "%~dp0supplier"
echo Starting supplier API on http://127.0.0.1:8081 ...
echo Account: supplier / supplier123
echo Endpoint: http://127.0.0.1:8081/api.php?act=goodslist
echo Keep this window open while testing docking.
"C:\tools\php74\php.exe" -d display_errors=0 -S 127.0.0.1:8081
