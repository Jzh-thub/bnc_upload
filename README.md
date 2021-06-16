# Upload 


## 安装

> composer require blue-nest-cloud/bnc-upload

## 配置

> 配置文件位于 `config/bncUpload.php`

### 使用方式

> 使用前 请修改 UploadService中的 sys_config 配置

> 初始化
>
> $upload = UploadService::init($type); $type:1本地 2:七牛云 3:阿里云 4:腾讯云
>
> $res   = $upload->to($path)->validate()->move();
>
> $path 是指保存路径; validate() 是否开启校验;(如果是分片上传则无效);move()上传; stream()流上传


> 分片上传 
> 
> 参数:
> 
> fileHash 文件唯一标识
> 
> chunk 当前文件分片编号 (默认:0)
> 
> chunks 总分片数 (默认:1)
> 
> size 总大小
> 
> filename 文件名 带后缀
> 
### 下面写个例子
```
class Index{

    public function index(Request $request)
    {
        $path = 'uploads'
        $upload = UploadService::init(1);
        $res    = $upload->to($path)->validate()->move('file');
        if (!$res)
            $errorInfo = $upload->getError();//内部错误信息
        else
            print_r((array)$res);
    }
}



