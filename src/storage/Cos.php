<?php

namespace bnc\upload\storage;

use bnc\upload\base\BaseUpload;
use bnc\upload\UploadValidate;
use Qcloud\Cos\Client;
use QCloud\COSSTS\Sts;
use think\Exception;
use think\exception\ValidateException;

/**
 * 腾讯云COS文件上传
 * Class Cos
 *
 * @package core\services\upload\storage
 */
class Cos extends BaseUpload
{

    /**
     * accessKey
     *
     * @var
     */
    protected $accessKey;

    /**
     * secretKey
     *
     * @var
     */
    protected $secretKey;

    /**
     * 句柄
     *
     * @var
     */
    protected $handle;

    /**
     * 空间域名 Domain
     *
     * @var
     */
    protected $uploadUrl;

    /**
     * 存储空间名称 公开空间
     *
     * @var
     */
    protected $storageName;

    /**
     * COS 使用 所属地域
     *
     * @var
     */
    protected $storageRegion;

    /**
     * 初始化
     *
     * @param array $config
     * @return mixed|void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);
        $this->accessKey     = $config['accessKey'] ?? null;
        $this->secretKey     = $config['secretKey'] ?? null;
        $this->uploadUrl     = $this->checkUploadUrl($config['uploadUrl'] ?? '');
        $this->storageName   = $config['storageName'] ?? null;
        $this->storageRegion = $config['storageRegion'] ?? null;
    }

    /**
     * 实例化cos
     *
     * @return mixed|Client
     */
    protected function app()
    {
        if (!$this->accessKey || !$this->secretKey) {
           $this->setError('Please configure accessKey and secretKey');
        }
        $this->handle = new Client(['region' => $this->storageRegion, 'credentials' => [
            'secretId' => $this->accessKey, 'secretKey' => $this->secretKey
        ]]);
        return $this->handle;
    }

    /**
     * 上传文件
     *
     * @param string|null $file
     * @param bool        $isStream 是否为流上传
     * @param string|null $fileContent 流内容
     * @return array|false
     */
    protected function upload(string $file = null, bool $isStream = false, string $fileContent = null)
    {
        if (!$isStream) {
            /** @var UploadValidate $uploadValidate */
            $uploadValidate = app()->make(UploadValidate::class);
            [$fileHandle, $error] = $uploadValidate->validate($file, $this->validate);
            if ($error)
                return $this->setError($error);
            $key  = $this->saveFileName($fileHandle->getRealPath(), $fileHandle->getOriginalExtension());
            $body = fopen($fileHandle->getRealPath(), 'rb');
        } else {
            $key  = $file;
            $body = $fileContent;
        }
        try {
            $this->fileInfo->uploadInfo   = $this->app()->putObject([
                'Bucket' => $this->storageName,
                'key'    => $key,
                'Body'   => $body
            ]);
            $this->fileInfo->filePath     = $this->uploadUrl . '/' . $key;
            $this->fileInfo->fileName     = $key;
            $this->fileInfo->originalName = $fileHandle->getOriginalName();
            return $this->fileInfo;
        } catch (\RuntimeException $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 文件流上传 TODO 未获取原始文件名
     *
     * @param string      $fileContent
     * @param string|null $key
     * @return array|bool
     */
    public function stream(string $fileContent, string $key = null)
    {
        if (!$key) {
            $key = $this->saveFileName();
        }
        return $this->upload($key, true, $fileContent);
    }

    /**
     * 文件上传
     *
     * @param string $file
     * @return array|bool
     */
    public function move(string $file = 'file')
    {
        return $this->upload($file);
    }

    /**
     * 删除资源
     *
     * @param string $filePath
     * @return bool|object
     */
    public function delete(string $filePath)
    {
        try {
            return $this->app()->deleteObject(['Bucket' => $this->storageName, 'key' => $filePath]);
        } catch (\Exception $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 获取腾讯云上传密钥
     * @return array
     *
     */
    public function getTempKeys(): array
    {
        $sts    = new Sts();
        $config = [
            'url'             => 'https://sts.tencentcloudapi.com/',
            'domain'          => 'sts.tencentcloudapi.com',
            'proxy'           => '',
            'secretId'        => $this->accessKey,     // 固定密钥
            'secretKey'       => $this->secretKey,     // 固定密钥
            'bucket'          => $this->storageName,   // 换成你的 bucket
            'region'          => $this->storageRegion, // 换成 bucket 所在园区
            'durationSeconds' => 1800,                 // 密钥有效期
            'allowPrefix'     => '*',                  // 这里改成允许的路径前缀，可以根据自己网站的用户登录态判断允许上传的具体路径，例子： a.jpg 或者 a/* 或者 * (使用通配符*存在重大安全风险, 请谨慎评估使用)
            // 密钥的权限列表。简单上传和分片需要以下的权限，其他权限列表请看 https://cloud.tencent.com/document/product/436/31923
            'allowActions'    => [
                // 简单上传
                'name/cos:PutObject',
                'name/cos:PostObject',
                // 分片上传
                'name/cos:InitiateMultipartUpload',
                'name/cos:ListMultipartUploads',
                'name/cos:ListParts',
                'name/cos:UploadPart',
                'name/cos:CompleteMultipartUpload'
            ]
        ];
        // 获取临时密钥，计算签名
        try {
            $result = $sts->getTempKeys($config);
        } catch (\Exception $e) {
        }
        $result['url']    = $this->uploadUrl . '/';
        $result['type']   = 'COS';
        $result['bucket'] = $this->storageName;
        $result['region'] = $this->storageRegion;
        return $result;
    }

    /**
     * 计算临时密钥用的签名
     *
     * @param $opt
     * @param $key
     * @param $method
     * @param $config
     * @return string
     */
    public function getSignature($opt, $key, $method, $config): string
    {
        $formatString = $method . $config['domain'] . '/?' . $this->json2str($opt, 1);
        $sign         = hash_hmac('sha1', $formatString, $key);
        return base64_encode($this->_hex2bin($sign));
    }

    public function _hex2bin($data)
    {
        $len = strlen($data);
        return pack("H" . $len, $data);
    }

    // obj 转 query string
    public function json2str($obj, $notEncode = false)
    {
        ksort($obj);
        $arr = array();
        if (!is_array($obj)) {
            return $this->setError($obj . " must be a array");
        }
        foreach ($obj as $key => $val) {
            array_push($arr, $key . '=' . ($notEncode ? $val : rawurlencode($val)));
        }
        return join('&', $arr);
    }
}
