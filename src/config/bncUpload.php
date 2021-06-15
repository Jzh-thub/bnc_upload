<?php

return [
    //默认上传模式
    'default'  => 'local',
    //上传文件大小
    'filesize' => 2097152,
    //上传文件后缀类型
    'fileExt'  => ['jpg', 'jpeg', 'png', 'gif', 'pem', 'mp3', 'wma', 'wav', 'amr', 'mp4', 'key'],
    //上传文件类型
    'fileMime' => ['image/jpeg', 'image/gif', 'image/png', 'text/plain', 'audio/mpeg'],
    //驱动模式
    'stores'   => [
        //本地
        'local' => [
            'save_dir' => public_path(),
            'disks'    => [
                //上传保存路径
                'tmp_dir'     => root_path('runtime' . DIRECTORY_SEPARATOR . 'tmp'),//临时路径
                'slice_first' => 0,                                                 //从第几片开始
            ],
            'file'     => [
                //上传文件大小
                'filesize' => 2097152,
                //上传文件后缀类型
                'fileExt'  => ['jpg', 'jpeg', 'png', 'gif', 'pem', 'mp3', 'wma', 'wav', 'amr', 'mp4', 'key'],
                //上传文件类型
                'fileMime' => ['image/jpeg', 'image/gif', 'image/png', 'text/plain', 'audio/mpeg'],
            ]
        ],
        //七牛云
        'qiniu' => [],
        //oss
        'oss'   => [],
        //cos
        'cos'   => [],
    ],

];