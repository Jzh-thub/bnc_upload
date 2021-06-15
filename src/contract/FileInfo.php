<?php


namespace bnc\upload\contract;


class FileInfo
{
    public $identifier;     //文件唯一标识
    public $chunkNumber = 1;//当前文件分片编号
    public $totalChunks = 1;//总分片数
    public $totalSize   = 0;//文件大小
}