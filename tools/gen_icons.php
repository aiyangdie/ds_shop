<?php
$w = 192;
$h = 192;
$im = imagecreatetruecolor($w, $h);
$bg = imagecolorallocate($im, 31, 111, 235);
$fg = imagecolorallocate($im, 255, 255, 255);
imagefilledrectangle($im, 0, 0, $w, $h, $bg);
imagefilledellipse($im, 96, 96, 120, 120, $fg);
imagefilledellipse($im, 96, 96, 80, 80, $bg);
$dirs = array(
    __DIR__ . '/appshell/app/src/main/res/mipmap-hdpi',
    __DIR__ . '/appshell/app/src/main/res/mipmap-mdpi',
    __DIR__ . '/appshell/app/src/main/res/mipmap-xhdpi',
    __DIR__ . '/appshell/app/src/main/res/mipmap-xxhdpi',
    __DIR__ . '/appshell/app/src/main/res/drawable',
    dirname(__DIR__) . '/assets/uploads/apps',
);
foreach ($dirs as $d) {
    if (!is_dir($d)) mkdir($d, 0755, true);
}
imagepng($im, __DIR__ . '/appshell/app/src/main/res/mipmap-hdpi/ic_launcher.png');
imagepng($im, __DIR__ . '/appshell/app/src/main/res/mipmap-mdpi/ic_launcher.png');
imagepng($im, __DIR__ . '/appshell/app/src/main/res/mipmap-xhdpi/ic_launcher.png');
imagepng($im, __DIR__ . '/appshell/app/src/main/res/mipmap-xxhdpi/ic_launcher.png');
imagepng($im, __DIR__ . '/appshell/app/src/main/res/drawable/splash.png');
imagepng($im, dirname(__DIR__) . '/assets/uploads/apps/default_icon.png');
imagedestroy($im);
echo "icons ok\n";
