<?php
namespace lib\Storage;

/**
 * 统一存储驱动接口
 */
interface Driver
{
    /**
     * @param string $localPath 临时本地文件路径
     * @param string $objectKey 对象键，如 cs/2026/01/xx.jpg
     * @param string $mime
     * @return array{ok:bool,url?:string,error?:string,size?:int}
     */
    public function put($localPath, $objectKey, $mime = 'application/octet-stream');
}
