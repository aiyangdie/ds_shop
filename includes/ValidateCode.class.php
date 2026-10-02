<?php
//验证码类
class ValidateCode {
private $charset = 'abcdefghkmnprstuvwxyzABCDEFGHKMNPRSTUVWXYZ23456789';    //随机因子
    private $code;                            //验证码
    private $codelen = 4;                    //验证码长度
    private $width = 130;                    //宽度
    private $height = 50;                    //高度
    private $img;                                //图形资源句柄
    private $font;                                //指定的字体
    private $fontsize = 20;                //指定字体大小
    private $fontcolor;                        //指定字体颜色
    private $use_ttf = false;             //是否使用 TTF 字体

    //构造方法初始化
    public function __construct() {
        $candidates = array(
            (defined('ROOT_PATH') ? ROOT_PATH : __DIR__) . '/../assets/font/elephant.ttf',
            dirname(__DIR__) . '/assets/font/elephant.ttf',
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/Arial.ttf',
            'C:/Windows/Fonts/consola.ttf',
            'C:/Windows/Fonts/simhei.ttf',
        );
        foreach ($candidates as $font) {
            if ($font && is_file($font) && function_exists('imagettftext')) {
                $this->font = $font;
                $this->use_ttf = true;
                break;
            }
        }
    }

    //生成随机码
    private function createCode() {
        $_len = strlen($this->charset)-1;
        for ($i=0;$i<$this->codelen;$i++) {
            $this->code .= $this->charset[mt_rand(0,$_len)];
        }
    }

    //生成背景
    private function createBg() {
        $this->img = imagecreatetruecolor($this->width, $this->height);
        $color = imagecolorallocate($this->img, mt_rand(157,255), mt_rand(157,255), mt_rand(157,255));
        imagefilledrectangle($this->img,0,$this->height,$this->width,0,$color);
    }

    //生成文字
    private function createFont() {
        $_x = $this->width / $this->codelen;
        for ($i=0;$i<$this->codelen;$i++) {
            $this->fontcolor = imagecolorallocate($this->img,mt_rand(0,120),mt_rand(0,120),mt_rand(0,120));
            $drawn = false;
            if ($this->use_ttf) {
                $box = @imagettftext($this->img,$this->fontsize,mt_rand(-20,20),(int)($_x*$i+mt_rand(5,10)),(int)($this->height / 1.4),$this->fontcolor,$this->font,$this->code[$i]);
                $drawn = ($box !== false);
            }
            if (!$drawn) {
                // TTF 失败时回退内置字体，保证字符可见
                imagestring($this->img, 5, (int)($_x*$i + 12), (int)($this->height/2 - 8), $this->code[$i], $this->fontcolor);
            }
        }
    }

    //生成线条、雪花（减少干扰，保证可读）
    private function createLine() {
        for ($i=0;$i<4;$i++) {
            $color = imagecolorallocate($this->img,mt_rand(100,180),mt_rand(100,180),mt_rand(100,180));
            imageline($this->img,mt_rand(0,$this->width),mt_rand(0,$this->height),mt_rand(0,$this->width),mt_rand(0,$this->height),$color);
        }
        for ($i=0;$i<30;$i++) {
            $color = imagecolorallocate($this->img,mt_rand(200,255),mt_rand(200,255),mt_rand(200,255));
            imagestring($this->img,mt_rand(1,3),mt_rand(0,$this->width),mt_rand(0,$this->height),'*',$color);
        }
    }

    //输出
    private function outPut() {
        header('Content-type:image/png');
        imagepng($this->img);
        imagedestroy($this->img);
    }

    //对外生成
    public function doimg() {
        $this->createBg();
        $this->createCode();
        $this->createLine();
        $this->createFont();
        $this->outPut();
    }

    //获取验证码
    public function getCode() {
        return strtolower($this->code);
    }
}