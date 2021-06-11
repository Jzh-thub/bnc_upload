<?php


namespace bnc\upload\storage;


use bnc\upload\base\BaseUpload;
use think\facade\Config;

class Local extends BaseUpload
{
    /**
     * 默认存在的路径
     * @var string
     */
    protected $defaultPath;

    /**
     * 初始化
     * @param array $config
     * @return mixed|void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);
        $this->defaultPath = Config::get('filesystem.disks.' . Config::get('filesystem.default') . '.url');
    }

    /**
     * 实例化
     * @return mixed|void
     */
    protected function app()
    {
    }

    public function getTempKeys()
    {
        return $this->setError('请检查您的上传配置,云存储才会有密钥');
    }


}