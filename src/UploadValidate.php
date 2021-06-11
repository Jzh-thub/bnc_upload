<?php


use think\exception\ValidateException;
use think\file\UploadedFile;

class UploadValidate
{
    use \bnc\upload\traits\ErrorTrait;

    /**
     * 验证
     * @param $file
     * @param $validate
     * @return array|false|UploadedFile
     */
    public function validate($file, $validate)
    {
        $fileHandle = app()->request->file($file);
        if (!$fileHandle)
            return $this->setError('Upload file does not exist');
        if ($validate) {
            try {
                $error = [
                    $file . '.filesize' => 'Upload filesize error',
                    $file . '.fileExt'  => 'Upload fileExt error',
                    $file . '.fileMime' => 'Upload fileMime error'
                ];
                validate([$file => $validate], $error)->check([$file => $fileHandle]);
            } catch (ValidateException $e) {
                return $this->setError($e->getMessage());
            }
        }
        return $fileHandle;
    }

}