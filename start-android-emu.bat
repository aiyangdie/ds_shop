@echo off
set ANDROID_SDK_ROOT=C:\Android\Sdk
set ANDROID_HOME=C:\Android\Sdk
set ANDROID_EMULATOR_HOME=C:\Android\emu-home
set ANDROID_AVD_HOME=C:\Android\avd
set ANDROID_EMU_ENABLE_CRASH_REPORTING=0
set PATH=%ANDROID_SDK_ROOT%\emulator;%ANDROID_SDK_ROOT%\platform-tools;%PATH%
echo Starting Android 11 emulator (C:\Android\avd\ds_shop_test)...
echo First boot 2-5 minutes. Drag APK onto the window or: adb install xxx.apk
emulator -avd ds_shop_test -gpu host -accel on -no-snapshot -no-boot-anim -netdelay none -netspeed full
pause
