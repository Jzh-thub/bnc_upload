<?php
namespace bnc\tests;

use bnc\upload\UploadService;

include __DIR__.'/../vendor/autoload.php';
print_r(\think\facade\Config::get('upload'));
class index{

    public function index(){
       $upload = UploadService::init(1);
       $res = $upload->to($path)->validate()->move($file);

    }
}

