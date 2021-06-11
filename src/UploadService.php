<?php
namespace bnc\upload;

class UploadService
{
    /**
     * @var array
     */
    protected static $upload=[];

    public static function init($type=null){
        if(is_null($type)){
            $type = 1;
        }
        if(isset(self::$upload['upload_'.$type])){
            return self::$upload['upload_'.$type];
        }
        $type = (int)$type;
        $config=[];
        switch ($type) {
            case 2://七牛
                $config = [
                    'accessKey' => sys_config('qiniu_accessKey'),
                    'secretKey' => sys_config('qiniu_secretKey'),
                    'uploadUrl' => sys_config('qiniu_uploadUrl'),
                    'storageName' => sys_config('qiniu_storage_name'),
                    'storageRegion' => sys_config('qiniu_storage_region'),
                ];
                break;
            case 3:// oss 阿里云
                $config = [
                    'accessKey' => sys_config('accessKey'),
                    'secretKey' => sys_config('secretKey'),
                    'uploadUrl' => sys_config('uploadUrl'),
                    'storageName' => sys_config('storage_name'),
                    'storageRegion' => sys_config('storage_region'),
                ];
                break;
            case 4:// cos 腾讯云
                $config = [
                    'accessKey' => sys_config('tengxun_accessKey'),
                    'secretKey' => sys_config('tengxun_secretKey'),
                    'uploadUrl' => sys_config('tengxun_uploadUrl'),
                    'storageName' => sys_config('tengxun_storage_name'),
                    'storageRegion' => sys_config('tengxun_storage_region'),
                ];
                break;
        }
        return self::$upload['upload_' . $type] = new \Upload($type, $config);
    }
}