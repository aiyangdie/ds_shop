@echo off
setlocal EnableExtensions
cd /d "%~dp0..\.."

set "JAVA_HOME=C:\Program Files\Eclipse Adoptium\jdk-17.0.20.101-hotspot"
if not exist "%JAVA_HOME%\bin\java.exe" set "JAVA_HOME=C:\Program Files\Eclipse Adoptium\jdk-17.0.20.8-hotspot"
set "ANDROID_HOME=C:\Android\Sdk"
set "ANDROID_SDK_ROOT=%ANDROID_HOME%"
set "PATH=%JAVA_HOME%\bin;C:\Gradle\gradle-8.2\bin;%PATH%"

set "PHP=C:\tools\php74\php.exe"
if not exist "%PHP%" set "PHP=php"

echo [%date% %time%] worker start
"%PHP%" "%~dp0worker.php" --once %*
echo [%date% %time%] worker exit=%ERRORLEVEL%
endlocal
