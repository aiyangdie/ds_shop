@echo off
chcp 65001 >nul
cd /d "%~dp0supplier"
echo [可选] 资源站 API — 不开主站也能卖货，只在对接/测货源时需要
echo http://127.0.0.1:8081/api.php?act=ping
echo 账号: supplier / supplier123
echo 请保持本窗口开启。
"C:\tools\php74\php.exe" -d display_errors=0 -S 127.0.0.1:8081
