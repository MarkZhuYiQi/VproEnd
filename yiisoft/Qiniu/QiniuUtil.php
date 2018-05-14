<?php
namespace Qiniu;
use Qiniu\Storage\UploadManager;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use yii\base\Exception;

class QiniuUtil
{
    public $acccessKey;
    public $secrectKey;
    // 要上传的空间
    public $bucket='vpro';
    public $auth;
    public $token;
    function __construct($secretKey='u2OEu67YO-N31kSPyNW9BOw7RRzyl7tnB0C5sfd9')
    {
        $this->accessKey='7vM-6UTYqbvEXCXfuXuyXPuYn0WZu4e0MAPB6ULO';
        $this->secretKey=$secretKey;
        // 构建鉴权对象
        $this->auth=new Auth($this->accessKey,$this->secretKey);
        // 生成上传 Token
        $this->token=$this->auth->uploadToken($this->bucket);
    }
    //上传视频图片
    public function uploadImg($imgName,$filePath){
// 初始化 UploadManager 对象并进行文件的上传。
        $uploadMgr = new UploadManager();
        //上传策略：回调地址，回调传参，限制类型
        $policy=array(
//            'callbackUrl'=>'http://223.112.88.211:9696/index.php/video/imgcallback',
            'callbackUrl'=>'http://markzhu.imwork.net:18176/index.php/video/imgcallback',
            'callbackBody'=>'key=$(key)',
            'mimeLimit'=>'image/jpeg;image/png'
        );
        $uptoken=$this->auth->uploadToken($this->bucket,null,3600,$policy);
// 调用 UploadManager 的 putFile 方法进行文件的上传。这都是phpsdk示例代码
        list($ret, $err) = $uploadMgr->putFile($uptoken, $imgName, $filePath);
        return true;
        if ($err !== null) {
            return false;
        } else {
            return true;
        }
    }
    /**
     * @param $userid
     * @return \stdClass
     * key是魔法变量，传过来的，https://developer.qiniu.com/kodo/manual/1235/vars#magicvar
     * 数组内是上传策略，https://developer.qiniu.com/kodo/manual/1206/put-policy
     */
    function getUploadToken($userid){
        $fileName="video".$userid.date("Ymdhis");//视频的文件名，暂时没有后缀
        //policy需要查看文档。
        $token=$this->auth->uploadToken( $this->bucket,null,3600,[
            "saveKey"=>$fileName,
//            "callbackUrl"=>"http://markzhu.imwork.net:18176/index.php/video/videocallback",
            "callbackUrl"=>"http://223.112.88.211:9696/index.php/video/videocallback",
            "callbackBody"=>"key=$(key)",
            "mimeLimit"=>'video/mp4'
        ]);
        $res=new \stdClass();
        $res->uptoken=$token;
        return $res;
    }
    function deleteFile($key){
        $bucketMgr=new BucketManager($this->auth);
        $err=$bucketMgr->delete($this->bucket,$key);
        if($err!==null){
            throw new Exception('Error!Delete file '.$key.' failed! Response Message:'.$err->message());
        }else{
            return true;
        }
    }
    function getVideoFiles(){
        //初始化BucketManager
        $bucketMgr = new BucketManager($this->auth);
        //你要测试的空间， 并且这个key在你空间中存在
        // 要列取的空间名称
        $bucket = 'vpro';
        // 要列取文件的公共前缀
        $prefix = '';
        // 上一次的位置标记
        $marker = '';
        // 	本次列举的条目数，范围为1-1000。
        $limit = 100;
        list($iterms, $marker, $err) = $bucketMgr->listFiles($bucket, $prefix, $marker, $limit);
        if ($err !== null) {
//            echo "\n====> list file err: \n";
            return $err;
        } else {
            $res['marker']=$marker;
            $res['iterms']=$iterms;
            return $res;
//            echo "Marker: $marker\n";
//            echo "\nList Iterms====>\n";
//            var_dump($iterms);
        }
    }
}