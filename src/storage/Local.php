<?php


namespace bnc\upload\storage;


use bnc\upload\base\BaseUpload;
use bnc\upload\contract\FileInfo;
use bnc\upload\UploadValidate;
use think\facade\Config;
use think\facade\Filesystem;
use think\File;

class Local extends BaseUpload
{
    /**
     * 默认存在的路径
     * @var string
     */
    protected $defaultPath;

    /**
     * 分片信息配置
     * @var FileInfo
     */
    protected $sliceFileInfo;

    /**
     * 分片
     * @var
     */
    protected $disksInfo;

    /**
     * 初始化
     * @param array $config
     * @return mixed|void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);
        $this->defaultPath                = rtrim(Config::get('bnc_upload.stores.local.save_dir', public_path() . DIRECTORY_SEPARATOR . 'uploads'), '/') . DIRECTORY_SEPARATOR . date('Ymd');
        $this->sliceFileInfo              = new FileInfo();
        $this->sliceFileInfo->identifier  = app()->request->param('fileHash', '');            //文件唯一标识
        $this->sliceFileInfo->filename    = $filename = app()->request->param('filename', '');//文件名
        $this->sliceFileInfo->chunkNumber = app()->request->param('chunk', 0, '/d');          //当前文件分片编号 索引
        $this->sliceFileInfo->totalChunks = app()->request->param('chunk', 1, '/d');          //总分片数
        $this->sliceFileInfo->totalSize   = app()->request->param('size', 0, '/d');           //总大小
        $nameArray                        = explode('.', $filename);
        $this->sliceFileInfo->ext         = $nameArray[count($nameArray) - 1];
        $this->disksInfo                  = Config::get('bnc_upload.stores.local.disks', []);
    }

    /**
     * 实例化
     * @return mixed|void
     */
    protected function app()
    {
    }

    /**
     * 获取密钥
     * @return false|mixed
     */
    public function getTempKeys()
    {
        return $this->setError('请检查您的上传配置,云存储才会有密钥');
    }

    /**
     * 生成上传文件目录
     * @param      $path
     * @param null $root
     * @return string|string[]
     */
    protected function uploadDir($path, $root = null)
    {
        if ($root === null) $root = app()->getRootPath() . 'public/';
        return str_replace('\\', '/', $root . 'uploads/' . $path);
    }

    /**
     * 检查上传目录不存在则生成
     *
     * @param $dir
     * @return bool
     */
    protected function validDir($dir)
    {
        return is_dir($dir) == true || mkdir($dir, 0777, true) == true;
    }

    public function move(string $file = 'file')
    {
        //判断是否是分片
        if ($this->sliceFileInfo->totalChunks === 1) {
            //如果只有一个切片,则不需要合并,直接将临时文件转化保存目录下
            /** @var UploadValidate $uploadValidate */
            $uploadValidate = app()->make(UploadValidate::class);
            $fileHandle     = $uploadValidate->validate($file, $this->validate);
            if (!$fileHandle)
                return false;
            $originalName = $fileHandle->getOriginalName();
            $saveDir      = $this->defaultPath;
            if (!is_dir($saveDir))
                mkdir($saveDir, 0777, true);
            $random                       = bin2hex(random_bytes(10));
            $uploadPath                   = $saveDir . DIRECTORY_SEPARATOR . $this->sliceFileInfo->identifier . $random . '.' . $this->sliceFileInfo->ext;
            $filePath                     = Filesystem::path($uploadPath);
            $this->fileInfo->uploadInfo   = new File($filePath);
            $this->fileInfo->originalName = $originalName;
            $this->fileInfo->fileName     = $this->fileInfo->uploadInfo->getFilename();
            $this->fileInfo->filePath     = $uploadPath;
            $merge                        = false;
        } else {
            //需要合并
            //临时分片文件路径
            $filePath   = ($this->disksInfo['tmp_dir'] ?? root_path('runtime' . DIRECTORY_SEPARATOR . 'tmp')) . $this->sliceFileInfo->identifier;
            $uploadPath = $filePath . '_' . $this->sliceFileInfo->chunkNumber;//临时分片名
            $merge      = true;
        }
        $baseDir = dirname($uploadPath);
        if (!is_dir($baseDir))
            mkdir($baseDir, 0777, true);
        if (!file_exists($uploadPath)) {
            //打开php暂存文件
            if (!empty($_FILES['file'])) {
                if (!$in = fopen($_FILES['file']['tmp_name'], "rb")) {
                    throw new \RuntimeException('打开临时文件失败');
                }
            } else {
                if (!$in = fopen("php://input", "rb"))
                    throw new \RuntimeException('打开文件流失败');
            }
            //打开要写入的文件
            if (!$out = fopen($uploadPath, "wb"))
                throw new \RuntimeException('上传的路径没有写入权限');

            //执行写入
            while ($buff = fread($in, 4096)) {
                fwrite($out, $buff);
            }
            fclose($in);
            fclose($out);
        }
        if ($merge) {
            //开始合并
            $this->checkFile();
        }
        return $this->fileInfo;
    }


    public function checkFile(): array
    {
        $filePath    = ($this->disksInfo['tmp_dir'] ?? root_path('runtime' . DIRECTORY_SEPARATOR . 'tmp')) . $this->sliceFileInfo->identifier;
        $sliceFirst  = $this->disksInfo['slice_first'] ?? 1;
        $totalChunks = $this->sliceFileInfo->totalChunks;
        $loopCount   = $sliceFirst ? $totalChunks + 1 : $totalChunks;
        //检查分片是否存在
        $chunkExists = [];
        for ($index = $sliceFirst; $index < $loopCount; $index++) {
            if (file_exists("{$filePath}_{$index}")) {
                array_push($chunkExists, $index);
            }
        }
        if (count($chunkExists) == $totalChunks) {
            //全部分片存在,则直接合成
            $this->mergeFile();
        } else {
            //分片缺失,返回已存在的分片
            $this->fileInfo->uploaded = $chunkExists;

        }
    }

    /**
     * 文件流上传 TODO 未获取原始文件名
     *
     * @param string      $fileContent
     * @param string|null $key
     * @return array|bool|mixed
     */
    public function stream(string $fileContent, string $key = null)
    {
        if (!$key)
            $key = $this->saveFileName();
        $dir = $this->uploadDir($this->path);
        if (!$this->validDir($dir))
            return $this->setError('Failed to generate upload directory, please check the permission!');
        $fileName = $dir . '/' . $key;
        file_put_contents($fileName, $fileContent);
        $this->fileInfo->uploadInfo = new File($fileName);
        $this->fileInfo->fileName   = $key;
        $this->fileInfo->filePath   = $this->defaultPath . '/' . $this->path . '/' . $key;
        return $this->fileInfo;
    }

    /**
     * 删除文件
     *
     * @param string $filePath
     * @return bool|mixed
     */
    public function delete(string $filePath)
    {
        if (file_exists($filePath)) {
            try {
                unlink($filePath);
                return true;
            } catch (\RuntimeException $e) {
                return $this->setError($e->getMessage());
            }
        }
        return false;
    }


    /**
     * 合并切片
     */
    public function mergeFile()
    {
        $filePath    = ($this->disksInfo['tmp_dir'] ?? root_path('runtime' . DIRECTORY_SEPARATOR . 'tmp')) . $this->sliceFileInfo->identifier;
        $totalChunks = $this->disksInfo->totalChunks;
        $done        = true;
        $sliceFirst  = $this->disksInfo['slice_first'] ?? 1;
        $loopCount   = $sliceFirst ? $totalChunks + 1 : $totalChunks;
        //检测所有的分片是否都存在
        for ($index = $sliceFirst; $index < $loopCount; $index++) {
            if (!file_exists("{$filePath}_{$index}")) {
                $done = false;
                break;
            }
        }
        if ($done === false) {
            throw new \RuntimeException("分片缺失,无法合并;总分片数:" . $totalChunks . ',找到分片数:' . $index, 1007);
        }
        //如果所有文件分片都上传完毕,开始合并
        $saveDir = $this->defaultPath;;
        if (!is_dir($saveDir))
            mkdir($saveDir, 0777, true);
        $random     = bin2hex(random_bytes(10));
        $uploadPath = $saveDir . DIRECTORY_SEPARATOR . $this->sliceFileInfo->identifier . $random . '.' . $this->sliceFileInfo->ext;
        if (!$out = fopen($uploadPath, "wb"))
            throw new \RuntimeException("upload path is not writable", 1006);
        if (flock($out, LOCK_EX)) {
            //进行排他型锁定
            for ($index = $sliceFirst; $index < $loopCount; $index++) {
                if (!$in = fopen("{$filePath}_{$index}", "rb"))
                    break;
                while ($buff = fread($in, 4096)) {
                    fwrite($out, $buff);
                }
                fclose($in);
                unlink("{$filePath}_{$index}");
            }
            flock($out, LOCK_UN);//释放锁定
        }
        fclose($out);
        $this->fileInfo->filePath = $uploadPath;
    }
}