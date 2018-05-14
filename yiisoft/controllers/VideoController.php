<?php
namespace app\controllers;
use app\models\ModelFactory;
use Qiniu\QiniuUtil;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\web\Controller;
use app\models\Uploader;
use yii\web\UploadedFile;
class VideoController extends CombaseController
{
    public function init()
    {
        parent::init();
        $this->enableCsrfValidation=false;
    }
    public function actionChangeused(){
        $img=Yii::$app->request->getBodyParam('videos_img');
        if(Yii::$app->request->isPost){
            $imgRecord=ModelFactory::loadModel('vpro_video_cover');
            $imgRecord=$imgRecord->findOne($img['video_cover_id']);
            $imgRecord->video_cover_isused=$imgRecord->video_cover_isused=='1'?0:1;
            if($imgRecord->save()){
                $res=new \stdClass();
                $res->status='success';
                $res->video_cover_id=$imgRecord->video_cover_id;
                Yii::$app->response->format=\yii\web\Response::FORMAT_JSON;
                return $res;
            }
        }
    }
    public function actionSubmitvideo()
    {
        try{
            $model=ModelFactory::loadModel('vpro_video');
            $video=Yii::$app->request->getBodyParam('video');
            if(Yii::$app->request->isPost){
                $model->video_title=$this->_validateRules("/[\s\S]{3,30}/",$video,'video_title');
                $model->video_class=$this->_validateRules("/\d{1,2}/",$video,'video_class');
                $model->video_desc=$this->_validateRules("/[\s\S]{6,300}/",$video,'video_desc');
                $model->video_cover=$this->_validateRules("/\d{1,5}/",$video['video_cover'],'id');
                $model->video_price=$this->_validateRules("/\d{1,3}/",$video,'video_price');
                $model->video_file=$this->_validateRules("/\d{1,5}/",$video,'video_file_id');
                $model->video_author=$this->_validateRules("/\d{1,5}/",$video,'video_author');
                $model->video_tag=implode(',',$video['video_tag']);
                $model->video_up_time=time();
                if($model->save()){
                    $videos_files=ModelFactory::loadModel('vpro_video_files');
                    $file=$videos_files->findOne(['video_file_id'=>$model->video_file]);
                    $file->video_file_isused=1;
                    $fileUsed=$file->save();
                    $videos_img=ModelFactory::loadModel('vpro_video_cover');
                    $img=$videos_img->findOne(['video_cover_id'=>$model->video_cover]);
                    $img->video_cover_isused=1;
                    $imgUsed=$img->save();
                    if($fileUsed&&$imgUsed){
                        $res=new \stdClass();
                        $res->status='success';
                        $res->id=$model->video_id;
                        Yii::$app->response->format=\yii\web\Response::FORMAT_JSON;
                        return $res;
                    }
                }
            }
        }catch(Exception $e){
            $res=new \stdClass();
            $res->status='error!';
            $res->msg=$e->getMessage();
            Yii::$app->response->format=\yii\web\Response::FORMAT_JSON;
            return $res;
        }
    }
    public function actionModifyvideo(){
        try{
            $model=ModelFactory::loadModel('vpro_video');
            $postData=Yii::$app->request->post('video');
            $list=$model->findOne(['video_id'=>6]);
//            return json_encode($postData);
            foreach($postData as $key=>$value){
                if(!is_array($value)){
                    $list->$key=$value;
                }else{
                    if(count($value)>0){
                        $list->$key=$this->_validateRules("/\d{1,5}/",$model[$key]['id']);
                    }
                }
            }
            if($list->save()){
                $res=new \stdClass();
                $res->status='success';
                $res->id=$list->video_id;
                Yii::$app->response->format=\yii\web\Response::FORMAT_JSON;
                return $res;
            }
        }catch(Exception $e){
            $res=new \stdClass();
            $res->status='error';
            $res->msg=$e->getMessage();
            Yii::$app->response->format=\yii\web\Response::FORMAT_JSON;
            return $res;
        }
    }
    public function _validateRules($rule,$value,$key){
        if(preg_match($rule,$value[$key])){
            return $value[$key];
        }else{
            throw new Exception('Error!data mismatch! -> '.$key);
        }
    }
    //这里实例化对象时，会判断传递过来的文件类型，如果在规则里没有允许，那么下面的对象实体是收不到的。
    public function actionUploadpic()
    {
//        return file_get_contents('php://input');
        $request=Yii::$app->request;
        $auth_id=$request->get('auth_id',false);
        $model = new Uploader();
        if ($request->isPost) {
//            Uploader[file]
            $model->imageFile = UploadedFile::getInstance($model,'file');
            return $model->upload($auth_id);
        }
    }
    public function actionUploadVideo(){

    }
    //用于上传video的token
    public function actionUptoken(){
        $userid=0;//默认是0，代表超级管理员
        $util=new QiniuUtil();
        $ret=$util->getUploadToken($userid);
        return json_encode($ret);
    }
    public function actionImgcallback(){
        if(Yii::$app->request->isPost){
            $model=ModelFactory::loadModel('vpro_video_cover');
            $vi=$model::find()->where(['video_cover_key'=>Yii::$app->request->post('key')])->one();
            if($vi){
                $vi->video_cover_isuploaded=1;
                if($vi->save()){
                    $result=new \stdClass();
                    $result->response='success';
                    Yii::$app->response->format=\yii\web\Response::FORMAT_JSON;
                    return $result;
                }
            }
        }else{
            $result=new \stdClass();
            $result->response='failed';
            return json_encode($result);
        }
    }
    public function actionVideocallback(){
        if(Yii::$app->request->isPost){
            $model=ModelFactory::loadModel('vpro_video_files');
            if($model){
                $model->video_file_key=Yii::$app->request->post('key');
                $model->video_file_uptime=time();
                $model->video_file_isuploaded=1;
                $model->save();
                $result=new \stdClass();
                $result->response='success';
                $result->video_file_id=$model->video_file_id;
                $result->key=Yii::$app->request->post('key');
//                Yii::$app->response->format=\yii\web\Response::FORMAT_JSON;
                return json_encode($result);
            }
            return json_encode(['response'=>'failed']);
        }else{
            $result=new \stdClass();
            $result->response='failed';
            return json_encode($result);
        }
    }
    public function actionImg163callback(){
        if(Yii::$app->request->isPost){
            $model=ModelFactory::loadModel('vpro_courses_cover');
            $vi=$model::find()->where(['course_cover_key'=>Yii::$app->request->post('key')])->one();
            if($vi){
                $vi->course_cover_isuploaded=1;
                $vi->course_cover_uptime=time();
                if($vi->save()){
                    $result=new \stdClass();
                    $result->response='success';
                    Yii::$app->response->format=\yii\web\Response::FORMAT_JSON;
                    return $result;
                }
            }
        }else{
            $result=new \stdClass();
            $result->response='failed';
            return json_encode($result);
        }
    }
}

