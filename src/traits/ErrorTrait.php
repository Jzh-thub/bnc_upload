<?php

namespace bnc\upload\traits;

/**
 *
 * Trait ErrorTrait
 *
 * @package core\traits
 */
trait ErrorTrait
{
    /**
     * 错误信息
     * @var string
     */
    protected $error;

    /**
     * 设置错误信息
     * @param string|null $error
     * @return false
     */
    protected function setError(?string $error = null): bool
    {
        $this->error = $error ?: "未知错误";
        return false;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getError(): string
    {
        $error       = $this->error;
        $this->error = null;
        return $error;
    }
}