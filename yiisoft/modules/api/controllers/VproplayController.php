<?php
namespace api\controllers;
use api\common\CourseApi;
use api\controllers\ShoppingBaseController;
use app\models\ModelFactory;
use app\models\VproCourses;
use Yii;

class VproplayController extends ShoppingBaseController
{
//------------------------------------------------------------------------------------------------------------------

//----------------------------------------------------------------------------------------------------------------------
    private $courseApi;
    public function init()
    {
        parent::init();
        $this->enableCsrfValidation=false;
        $this->courseApi = new CourseApi();
    }
    public function actionVproauth(){
        $request=Yii::$app->request;
        $table=$request->get('table','');
        $key = $request->get('key',false);
        if($table || $key){
            $res=$this->redis->hGet($table,$key);
        }
        return json_encode($res);
    }
    public function actionVprodetail(){
        $body = $this->checkParams(['videoid'], 'get');
        if(!$body) return json_encode($this->returnInfo('params transfer error', 'PARAMS_ERROR'));
        $video_id = $body['video_id'];
        if($video_info=$this->redis->hGet('newestVideos',$video_id)) {
            return json_encode($this->returnInfo($video_info));
        }
    }

    /**
     * 获得课程列表
     * @return string
     */
    public function actionGetLessonsList() {
        if($body = $this->checkParams(['course_id'], 'get')) {
            $list = $this->courseApi->getCourseLessonList($body['course_id']);
            return json_encode($this->returnInfo($list));
        }
        return json_encode($this->returnInfo('params missing', $this->params['PARAMS_ERROR']));
    }

    /**
     * 获得视频列表
     * @return string
     */
    public function actionGetVideosList() {
        $body = $this->checkParams(['course_id', 'lesson_id'], 'post');
        if(!$body) return json_encode($this->returnInfo('params transfer error', 'PARAMS_ERROR'));
        $list = $this->courseApi->getCourseLessonList($body['course_id']);
        foreach($list as $key => $value) {
            if($value->lesson_is_chapter_head) {
                unset($list[$key]);
            }
        }
        var_export($list);
    }
}