<?php
namespace app\models;
use Qiniu\QiniuUtil;
use yii\base\Model;
use yii\web\UploadedFile;
class Uploader extends Model
{
    /**
     * @var UploadedFile
     */
    public $imageFile;
    public $qiniu_address="http://omxvb7tjv.bkt.clouddn.com/";
    public function rules()
    {
        return [
            [['imageFile'], 'file', 'skipOnEmpty' => false, 'extensions' => 'png, jpg','checkExtensionByMimeType'=>false],
        ];
    }
    public function upload($auth_id){

        if ($this->validate()) {
            $this->imageFile->saveAs('videos/images/' . $this->imageFile->baseName . '.' . $this->imageFile->extension);
            return 666666;
        } else {
//            return json_encode($this->getErrors());
            return 1111111;
        }





        $result=new \stdClass();
        //配合前台elementui的显示file-list需要的属性
        $result->name='';
        $result->url='';
        $result->state=0;
        $result->id=0;
        $imgName=date('YmdHis').$auth_id.'.'.$this->imageFile->extension;
        if($this->validate()){
            $cover=ModelFactory::loadModel('vpro_video_cover');
            $cover->video_cover_key=$imgName;
            $cover->video_cover_author=$auth_id;
            $cover->video_cover_address=$this->qiniu_address.$cover->video_cover_key;
            $cover->video_cover_uptime=time();
            if($cover->save()){
                $qutil=new QiniuUtil();
                $file_path='videos/images/'.$imgName;
                $this->imageFile->saveAs($file_path);
                if($qutil->uploadImg($cover->video_cover_key,$file_path)){
                    $result->name=$cover->video_cover_key;
                    $result->url=$cover->video_cover_address;
                    $result->state=1;
                    $result->id=$cover->video_cover_id;
                    return json_encode($result);
                }
            }
        }
        return json_encode($result);
    }
}