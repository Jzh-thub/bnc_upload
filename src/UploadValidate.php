<?php

namespace bnc\upload;

use think\exception\ValidateException;
use think\file\UploadedFile;

class UploadValidate
{

    /**
     * @param $file
     * @param $validate
     * @return  array
     */
    public function validate ($file, $validate):array
    {
        $fileHandle = app()->request->file($file);
        if (!$fileHandle)
            return [$fileHandle, 'Upload file does not exist'];
        if ($validate) {
            try {
                $error = [
                    $file . '.filesize' => 'Upload filesize error',
                    $file . '.fileExt'  => 'Upload fileExt error',
                    $file . '.fileMime' => 'Upload fileMime error'
                ];
                validate([$file => $validate], $error)->check([$file => $fileHandle]);
            } catch (ValidateException $e) {
                return [$fileHandle, $e->getMessage()];
            }
        }
        return [$fileHandle, ''];
    }

}

