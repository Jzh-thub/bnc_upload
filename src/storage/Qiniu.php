<?php


namespace bnc\upload\storage;


use bnc\upload\base\BaseUpload;
use Qiniu\Auth;
use Qiniu\Config;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use think\exception\ValidateException;

/**
 * 七牛云上传
 * Class Qiniu
 * @package bnc\upload\storage
 */
class Qiniu extends BaseUpload
{
    /**
     * accessKey
     *
     * @var mixed
     */
    protected $accessKey;

    /**
     * secretKey
     *
     * @var mixed
     */
    protected $secretKey;

    /**
     * 句柄
     *
     * @var object
     */
    protected $handle;

    /**
     * 空间域名 Domain
     *
     * @var mixed
     */
    protected $uploadUrl;

    /**
     * 存储空间名称  公开空间
     *
     * @var mixed
     */
    protected $storageName;

    /**
     * COS使用  所属地域
     *
     * @var mixed|null
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
     * 实例化七牛云
     *
     * @return object|Auth
     */
    protected function app()
    {
        if (!$this->accessKey || !$this->secretKey) {
            throw new \RuntimeException('Please configure accessKey and secretKey');
        }
        $this->handle = new Auth($this->accessKey, $this->secretKey);
        return $this->handle;
    }

    /**
     * 上传文件
     *
     * @param string $file
     * @return array|bool|mixed
     * @throws \Exception
     */
    public function move(string $file = 'file')
    {
        /** @var \UploadValidate $uploadValidate */
        $uploadValidate = app()->make(\UploadValidate::class);
        $fileHandle     = $uploadValidate->validate($file, $this->validate);
        if (!$fileHandle)
            return false;
        $key   = $this->saveFileName($fileHandle->getRealPath(), $fileHandle->getOriginalExtension());
        $token = $this->app()->uploadToken($this->storageName);
        try {
            $uploadMgr = new UploadManager();
            [$result, $error] = $uploadMgr->putFile($token, $key, $fileHandle->getRealPath());
            if ($error !== null) {
                return $this->setError($error->message());
            }
            $this->fileInfo->uploadInfo   = $result;
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
     * @return array|bool|mixed|\StdClass
     */
    public function stream(string $fileContent, string $key = null)
    {
        $token = $this->app()->uploadToken($this->storageName);
        if (!$key) {
            $key = $this->saveFileName();
        }
        try {
            $uploadMgr = new UploadManager();
            [$result, $error] = $uploadMgr->put($token, $key, $fileContent);
            if ($error !== null) {
                return $this->setError($error->message());
            }
            $this->fileInfo->uploadInfo = $result;
            $this->fileInfo->filePath   = $this->uploadUrl . '/' . $key;
            $this->fileInfo->fileName   = $key;
            return $this->fileInfo;
        } catch (\RuntimeException $e) {
            return $this->setError($e->getMessage());
        }
    }


    /**
     * TODO 删除资源
     *
     * @param string $filePath
     * @return mixed
     */
    public function delete(string $filePath)
    {
        $bucketManager = new BucketManager($this->app(), new Config());
        return $bucketManager->delete($this->storageName, $filePath);
    }

    /**
     * 获取七牛云上传密钥
     *
     * @return array|mixed
     */
    public function getTempKeys()
    {
        $token  = $this->app()->uploadToken($this->storageName);
        $domain = $this->uploadUrl;
        $key    = $this->saveFileName();
        $type   = 'QINIU';
        return compact('token', 'domain', 'key', 'type');
    }
}