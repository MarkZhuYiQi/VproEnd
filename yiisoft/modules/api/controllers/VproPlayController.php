<?php
namespace api\controllers;
use api\controllers\ShoppingBaseController;
use app\models\ModelFactory;
use app\models\VproCourses;
use Yii;

class VproController extends ShoppingBaseController
{
//------------------------------------------------------------------------------------------------------------------

//----------------------------------------------------------------------------------------------------------------------
    public function init()
    {
        parent::init();
        $this->enableCsrfValidation=false;
    }

    public function actionVproauth(){
        $request=Yii::$app->request;
        $table=$request->get('table','');
        $key = $request->get('key',false);
        if($table || $key){
            $res=$this->redis->hget($table,$key);
        }
        return json_encode($res);
    }
    public function actionVprodetail(){
        $video_id=Yii::$app->request->get('videoid',false);
        if($video_id){
            if($video_info=$this->redis->hget('newestVideos',$video_id)){
                return $video_info;
            }
        }else{
            return json_encode(['video_id'=>false]);
        }
    }
    public function actionGetLessonsList() {
        if($body = $this->checkParams(['course_id'], 'get')) {
            $list = $this->getCourseLessonList($body['course_id']);
            return json_encode(['data' => $list]);
        }
        return json_encode(['data' => 'error']);
    }
    public function actionGetVideo() {

    }
}