<?php


namespace bnc\upload\base;


use bnc\upload\traits\ErrorTrait;

abstract class BaseStorage
{
    use ErrorTrait;

    /**
     * 驱动名称
     * @var string
     */
    protected $name;

    /**
     * 驱动配置文件名
     * @var string
     */
    protected $configFile;

    /**
     *
     * BasicStorage constructor.
     *
     * @param string      $name 驱动名
     * @param array       $config 驱动配置名
     * @param string|null $configFile 其他配置
     */
    public function __construct(string $name, array $config = [], string $configFile = null)
    {
        $this->name       = $name;
        $this->configFile = $configFile;
        $this->initialize($config);
    }

    /**
     * 初始化
     * @param array $config
     * @return mixed
     */
    abstract protected function initialize(array $config);
}