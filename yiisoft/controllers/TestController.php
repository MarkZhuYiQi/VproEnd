<?php
namespace app\controllers;
use Codeception\Module\Redis;
use Yii;
// 引入鉴权类
use Qiniu\Auth;

// 引入上传类
use Qiniu\Storage\UploadManager;
class TestController extends CombaseController
{
    public function actionTest(){
        // 需要填写你的 Access Key 和 Secret Key
        $accessKey = '7vM-6UTYqbvEXCXfuXuyXPuYn0WZu4e0MAPB6ULO';
        $secretKey = 'u2OEu67YO-N31kSPyNW9BOw7RRzyl7tnB0C5sfd9';

// 构建鉴权对象
        $auth = new Auth($accessKey, $secretKey);

// 要上传的空间
        $bucket = 'vpro';

// 生成上传 Token
        $token = $auth->uploadToken($bucket);

// 要上传文件的本地路径
        $filePath = './videos/images/t.jpg';

// 上传到七牛后保存的文件名
        $key = 'my-test.jpg';

// 初始化 UploadManager 对象并进行文件的上传。
        $uploadMgr = new UploadManager();

// 调用 UploadManager 的 putFile 方法进行文件的上传。
        list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
        echo "\n====> putFile result: \n";
        if ($err !== null) {
            var_dump($err);
        } else {
            var_dump($ret);
        }
        return 123;
    }
    public function actionIp(){
        echo \app\common\Common::ip();
    }
    public function actionRedis(){
        $redis=new \Redis();
        $redis->connect('127.0.0.1',6379);


    }
}