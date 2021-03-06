<?php

namespace bnc\upload\storage;

use bnc\upload\base\BaseUpload;
use bnc\upload\UploadValidate;
use OSS\Core\OssException;
use OSS\OssClient;


/**
 * 阿里云OSS上传
 * Class OSS
 */
class Oss extends BaseUpload
{
    /**
     * accessKey
     * @var mixed
     */
    protected $accessKey;

    /**
     * secretKey
     * @var mixed
     */
    protected $secretKey;

    /**
     * 句柄
     * @var \OSS\OssClient
     */
    protected $handle;

    /**
     * 空间域名 Domain
     * @var mixed
     */
    protected $uploadUrl;

    /**
     * 存储空间名称  公开空间
     * @var mixed
     */
    protected $storageName;

    /**
     * COS使用  所属地域
     * @var mixed|null
     */
    protected $storageRegion;

    /**
     * 初始化
     * @param array $config
     * @return mixed|void
     */
    protected function initialize (array $config)
    {
        parent::initialize($config);
        $this->accessKey = $config['accessKey'] ?? null;
        $this->secretKey = $config['secretKey'] ?? null;
        $this->uploadUrl = $this->checkUploadUrl($config['uploadUrl'] ?? '');
        $this->storageName = $config['storageName'] ?? null;
        $this->storageRegion = $config['storageRegion'] ?? null;
    }

    /**
     * 初始化oss
     * @return OssClient
     * @throws OssException
     */
    protected function app (): OssClient
    {
        if (!$this->accessKey || !$this->secretKey) {
            $this->setError('Please configure accessKey and secretKey');
        }
        $this->handle = new OssClient($this->accessKey, $this->secretKey, $this->storageRegion);
        if (!$this->handle->doesBucketExist($this->storageName)) {
            $this->handle->createBucket($this->storageName, OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE);
        }
        return $this->handle;
    }

    /**
     * 上传文件
     * @param string $file
     * @return array|false
     * @throws OssException
     */
    public function move (string $file = 'file')
    {
        /** @var UploadValidate $uploadValidate */
        $uploadValidate = app()->make(UploadValidate::class);
        [$fileHandle, $error] = $uploadValidate->validate($file, $this->validate);
        if ($error)
            return $this->setError($error);
        $key = $this->saveFileName($fileHandle->getRealPath(), $fileHandle->getOriginalExtension());
        try {
            $uploadInfo = $this->app()->uploadFile($this->storageName, $key, $fileHandle->getRealPath());
            if (!isset($uploadInfo['info']['url'])) {
                return $this->setError('Upload failure');
            }
//            $this->fileInfo->uploadInfo = $uploadInfo;
            $this->fileInfo->originalName = $fileHandle->getOriginalName();
            $this->fileInfo->filePath = $this->uploadUrl . '/' . $key;
            $this->fileInfo->fileName = $key;
            $this->fileInfo->size = $fileHandle->getSize();
            return $this->fileInfo;
        } catch (\RuntimeException $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 文件流上传
     * @param string $fileContent
     * @param string|null $key
     * @return array|false
     * @throws OssException
     */
    public function stream (string $fileContent, string $key = null)
    {
        try {
            if (!$key) {
                $key = $this->saveFileName();
            }
//            $fileContent =
//                (string)EntityBody::factory($fileContent);
            $uploadInfo = $this->app()->putObject($this->storageName, $key, $fileContent);
            if (!isset($uploadInfo['info']['url'])) {
                return $this->setError('Upload failure');
            }
//            $this->fileInfo->uploadInfo = $uploadInfo;
            $this->fileInfo->originalName = $key;
            $this->fileInfo->filePath = $this->uploadUrl . '/' . $key;
            $this->fileInfo->fileName = $key;
            return $this->fileInfo;
        } catch (\RuntimeException $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 删除资源
     * @param string $filePath
     * @return false|null
     */
    public function delete (string $filePath): ?bool
    {
        try {
            return $this->app()->deleteObject($this->storageName, $filePath);
        } catch (OssException $e) {
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 获取OSS上传密钥
     * @param string $callbackUrl
     * @param string $dir
     * @return array
     * @throws \Exception
     */
    public function getTempKeys (string $callbackUrl = '', string $dir = ''): array
    {
        $base64CallbackBody = base64_encode(json_encode([
                                                            'callbackUrl'      => $callbackUrl,
                                                            'callbackBody'     => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
                                                            'callbackBodyType' => "application/x-www-form-urlencoded"
                                                        ]));

        $policy = json_encode([
                                  'expiration' => $this->gmtIso8601(time() + 30),
                                  'conditions' =>
                                      [
                                          [0 => 'content-length-range', 1 => 0, 2 => 1048576000],
                                          [0 => 'starts-with', 1 => '$key', 2 => $dir]
                                      ]
                              ]);
        $base64Policy = base64_encode($policy);
        $signature = base64_encode(hash_hmac('sha1', $base64Policy, $this->secretKey, true));
        return [
            'accessid'  => $this->accessKey,
            'host'      => $this->uploadUrl,
            'policy'    => $base64Policy,
            'signature' => $signature,
            'expire'    => time() + 30,
            'callback'  => $base64CallbackBody,
            'type'      => 'OSS'
        ];
    }

    /**
     * 获取ISO时间格式
     * @param $time
     * @return string
     * @throws \Exception
     */
    protected function gmtIso8601 ($time): string
    {
        $dtStr = date("c", $time);
        $mydatetime = new \DateTime($dtStr);
        $expiration = $mydatetime->format(\DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);
        return $expiration . "Z";
    }
}
