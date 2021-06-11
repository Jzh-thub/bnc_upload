<?php


use think\facade\Config;

/**
 * Class Upload
 * @mixin \bnc\upload\storage\Local
 * @mixin \bnc\upload\storage\Oss
 * @mixin \bnc\upload\storage\Cos
 * @mixin \bnc\upload\storage\Qiniu
 */
class Upload extends \bnc\upload\base\BaseManager
{
    protected $namespace = '\\bnc\\upload\\storage\\';

    /**
     * 设置默认上传类型
     * @return mixed
     */
    protected function getDefaultDriver()
    {
        return Config::get('upload.default', 'local');
    }
}