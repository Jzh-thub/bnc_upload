<?php

namespace bnc\upload\base;

use think\facade\Config;

abstract class BaseUpload extends BaseStorage
{
    /**
     * 图片信息
     * @var array
     */
    protected $fileInfo;

    /**
     * 验证配置
     * @var string
     */
    protected $validate;

    /**
     * 保存路径
     * @var string
     */
    protected $path = '';

    protected function initialize(array $config)
    {
        $this->fileInfo = new \stdClass();
    }

    /**
     * 上传保存路径
     * @param string $path
     * @return $this
     */
    public function to(string $path): BaseUpload
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 获取文件信息
     * @return array
     */
    public function getFileInfo(): array
    {
        return $this->fileInfo;
    }

    /**
     * 验证合法上传路径
     * @param string $url
     * @return string
     */
    protected function checkUploadUrl(string $url): string
    {
        if ($url && strstr($url, 'http') === false)
            $url = 'https://' . $url;
        return $url;
    }

    /**
     * 获取系统配置
     * @return mixed
     */
    protected function getConfig()
    {
        $config = Config::get($this->configFile . '.stores.' . $this->name, []);
        if (empty($config)) {
            $config['filesize'] = Config::get($this->configFile . '.filesize', []);
            $config['fileExt']  = Config::get($this->configFile . '.fileExt', []);
            $config['fileMime'] = Config::get($this->configFile . '.fileMime', []);
        }
        return $config;
    }

    /**
     * 设置验证规则
     * @param array|null $validate
     * @return $this
     */
    public function validate(?array $validate = null): BaseUpload
    {
        if (is_null($validate)) {
            $validate = $this->getConfig();
        }
        $this->extractValidate($validate);
        return $this;
    }

    /**
     * 提取上传验证
     * @param array $validateArray
     */
    protected function extractValidate(array $validateArray)
    {
        $validate = [];
        foreach ($validateArray as $key => $value) {
            $validate[] = $key . ':' . (is_array($value) ? implode(',', $value) : $value);
        }
        $this->validate = implode('|', $validate);
        unset($validate);
    }

    /**
     * 设置/提取文件名
     * @param string|null $path
     * @param string      $ext
     * @return string
     */
    protected function saveFileName(string $path = null, string $ext = 'jpg'): string
    {
        return ($path ? substr(md5($path), 0, 5) : "") . date('YmdHis') . rand(0, 9999) . '.' . $ext;
    }

    /**
     * 获取文件类型和大小
     * @param string $url
     * @param bool   $isData 是否获取原始数据
     * @return array
     *
     */
    protected function getFileHeaders(string $url, bool $isData = true): array
    {
        stream_context_set_default(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $header['size'] = 0;
        $header['type'] = '';
        if (!$isData) return $header;
        try {
            $headerArray = get_headers(str_replace('\\', '/', $url), true);
            if (isset($headerArray['Content-Length'])) {
                $header['size'] = $headerArray['Content-Length'];
            }
            if (isset($headerArray['Content-Type'])) {
                $header['type'] = $headerArray['Content-Type'];
            }
            if (is_array($headerArray['Content-Length']) && count($headerArray['Content-Length']) == 2) {
                $header['size'] = $headerArray['Content-Length'][1];
            }
            if (is_array($headerArray['Content-Type']) && count($headerArray['Content-Type']) == 2) {
                $header['type'] = $headerArray['Content-Type'][1];
            }
        } catch (\Exception $exception) {
        }
        return $header;
    }

    public function getUploadInfo()
    {
        if (isset($this->fileInfo->filePath)) {
            if (strstr($this->fileInfo->filePath, 'http') === false) {
                $url = request()->domain() . $this->fileInfo->filePath;
            } else {
                $url = $this->fileInfo->filePath;
            }
            $headers = $this->getFileHeaders($url);
            return [
                'original_name' => $this->fileInfo->originalName ?? '',
                'name'          => $this->fileInfo->fileName,
                'size'          => $headers['size'] ?? 0,
                'type'          => $headers['type'] ?? 'image/jpeg',
                'dir'           => $this->fileInfo->filePath,
                'time'          => time(),
                'ext'           => $this->fileInfo->fileExt,
                //TODO 是否需要上传类型
            ];
        } else {
            return [];
        }
    }

    /**
     * 实例化上传
     * @return mixed
     */
    abstract protected function app();

    /**
     * 文件上传
     * @param string $file
     * @return mixed
     */
    abstract public function move(string $file = "file");

    /**
     * 文件流上传
     * @param string      $fileContent
     * @param string|null $key
     * @return mixed
     */
    abstract public function stream(string $fileContent, string $key = null);

    /**
     * 删除文件
     * @param string $filePath
     * @return mixed
     */
    abstract public function delete(string $filePath);

    /**
     * 获取上传密钥
     * @return mixed
     */
    abstract public function getTempKeys();
}
