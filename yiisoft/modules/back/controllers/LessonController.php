<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 3/30/2018
 * Time: 9:15 AM
 */
namespace back\controllers;

use app\common\JwtAuth;
use app\models\VproCourses;
use app\models\VproCoursesLessonList;
use app\models\VproVideo;
use app\models\VproVideoDetail;
use app\models\VproVideoFiles;
use yii\db\Exception;

class LessonController extends BackBaseController {
    public $modelClass = 'app\models\VproCoursesLessonList';
    function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => JwtAuth::className(),
            /*
             *因为此post请求的 content-type不是one of the “application/x-www-form-urlencoded, multipart/form-data, or text/plain”, 所以Preflighted requests被发起。
             * “preflighted” requests first send an HTTP OPTIONS request header to the resource on the other domain, in order to determine whether the actual request is safe to send.
             * 然后得到服务器response许可之后，再发起其post请求。
             */
            'except' => ['create', 'options', 'index']
        ];
        return $behaviors;
    }
    function actions() {
        $actions = parent::actions();
        unset($actions['index']);
        return $actions;
    }
    /**
     * 获得课程下的课时列表。
     * 在获得课时列表的同时，需要获得课时的视频信息
     *
     * code: 70001: the course_author does not meet with the author
     */
    function actionIndex() {
        $author = $this->request->get('author', -1);
        $course_id = $this->request->get('courseid', -1);
        $course_author = VproCourses::find()->select(['course_author'])->where(['course_id' => $course_id])->limit(1)->asArray()->one();
        // 查看编辑这个课程的人是否是发布人
        if(intval($course_author['course_author']) !== intval($author))return $this->returnInfo('you do not have rights to edit this course', 'AUTHOR_MISMATCH');
        return $this->returnInfo(VproCoursesLessonList::find()->where(['lesson_course_id' => $course_id])->asArray()->all());
    }
    function actionGetVideoInfo() {
        $lesson_id = $this->request->get('lessonId', false);
        $videoInfo = VproVideo::find()
            ->joinWith(['vproVideoFiles', 'vproVideoDetail'])
            ->where(['vpro_video.video_lesson_id' => $lesson_id])
//            ->createCommand()->getRawSql();
            ->asArray()
            ->one();
        if ($videoInfo === null) {
            $videoInfo = [
                'vproVideoFiles' => [
                    'video_file_key' => 'error'
                ],
                'vproVideoDetail' => [
                    'detail_content' => ''
                ]
            ];
        }
        return $this->returnInfo($videoInfo);
    }
    function actionSetLessonInfo() {
        /**
        course_id: "1262004",
        edit_info: {lesson_info: {lesson_author: "1", detail_content: "1122", lesson_title: "React概叙1"}, videoList: {}}
        lesson_id: "91639"
        user_id: "1"
        **/
        $new_info = $this->request->post('new_info', false);
        $edit_info = $this->request->post('edit_info', false);
        $lesson_id = $this->request->post('lesson_id', false);
        $course_id = $this->request->post('course_id', false);
        $user_id = $this->request->post('user_id', false);
        if(!$course_id || !$user_id) return $this->returnInfo('lesson information is missing!', 'INFO_MISS');
        $transaction=$this->db->beginTransaction();
        try {
            if($edit_info) {
                // 设置视频vprovideo信息, 这是用于测试的，实际环境不会有这种情况发生-------------------------------------------
                $video_info = VproVideo::findOne(['video_lesson_id' => $lesson_id]);
                if (!$video_info) {
                    $video_detail_id = $this->setVproVideoDetail(['detail_lesson_id' => $lesson_id, 'detail_content' => '']);
                    $this->setVproVideo(['video_file_id' => $edit_info['video_file_id'], 'video_author' => $user_id, 'video_detail_id' => $video_detail_id, 'video_lesson_id' => $lesson_id]);
                }
                //------------------------------------------------------------------------------------------------------
                // 设置视频详细content信息
                if (isset($edit_info['lesson_info']['detail_content'])) {
                    $video_detail_instance = VproVideoDetail::findOne(['detail_lesson_id' => $lesson_id]);
                    $video_detail_info = ['detail_content' => $edit_info['lesson_info']['detail_content']];
                    $video_detail_id = $this->setVproVideoDetail($video_detail_info, $video_detail_instance);
                }
                // -----------------------------------------------------------------------------------------------------
                // 设置lesson相关信息
                if (isset($edit_info['lesson_info']['lesson_title'])) {
                    $lesson_instance = VproCoursesLessonList::findOne(['lesson_id' => $lesson_id, 'lesson_course_id' => $course_id]);
                    $lesson_info = ['lesson_title' => $edit_info['lesson_info']['lesson_title']];
                    $this->setVproCoursesLessonList($lesson_info, $lesson_instance);
                }
            }
            if($new_info) {
                $lessonValues = [
                    'lesson_lid' => $new_info['lesson_lid'],
                    'lesson_pid' => $new_info['lesson_pid'],
                    'lesson_title' => $new_info['lesson_title'],
                    'lesson_is_chapter_head' => 0,
                    'lesson_course_id' => $course_id
                ];
                $lesson_id = $this->setVproCoursesLessonList($lessonValues);
                $video_file_id = $this->setVproVideoFiles([
                    'video_file_lesson_id' => $lesson_id
                ], VproVideoFiles::findOne(['video_file_key' => $new_info['video_file_key']]));
                if (isset($new_info['detail_content'])) {
                    $video_detail_id = $this->setVproVideoDetail([
                        'detail_lesson_id' => $lesson_id,
                        'detail_content' => $new_info['detail_content'],
                    ]);
                }
                $vproVideo = $this->setVproVideo([
                    'video_file_id' => $video_file_id,
                    'video_author' => $user_id,
                    'video_lesson_id' => $lesson_id,
                    'video_lesson_isused' => 1
                ]);
            }
            $transaction->commit();
            return $this->returnInfo(true);
        } catch(Exception $e) {
            $transaction->rollBack();
            return $this->returnInfo($e->getMessage(), 'DATEBASE_INSERT_ERROR');
            throw $e;
        }
    }
    function setVproVideo($attributes, $instance=null) {
        $instance = $instance!==null ? $instance : new VproVideo();
        foreach($attributes as $key => $value) {
            $instance->$key = $value;
        }$instance->save();
        return $instance->video_id;
    }
    function setVproVideoFiles($attributes, $instance=null) {
        $instance = $instance!==null ? $instance : new VproVideoFiles();
        foreach($attributes as $key => $value) {
            $instance->$key = $value;
        }$instance->save();
        return $instance->video_file_id;
    }
    function setVproVideoDetail($attributes, $instance=null) {
        $instance = $instance!==null ? $instance : new VproVideoDetail();
        foreach($attributes as $key => $value) {
            $instance->$key = $value;
        }
        $instance->save();
        return $instance->detail_id;
    }
    function setVproCoursesLessonList($attributes, $instance=null) {
        $instance = $instance!==null ? $instance : new VproCoursesLessonList();
        foreach($attributes as $key => $value) {
            $instance->$key = $value;
        }
        $instance->save();
        return $instance->lesson_id;
    }
    function actionDelLessons() {
        if (!$body = $this->checkParams(['lesson_ids'], 'post')) return $this->returnInfo('data transfer missing', 'INFO_MISS');
        $transaction=$this->db->beginTransaction();
        try {
            foreach($body['lesson_ids'] as $k) {
                $lesson = VproCoursesLessonList::findOne(['lesson_id' => $k]);
                $videoInfo = VproVideo::find()
                    ->select([
                        'vpro_video.video_id',
                        'vpro_video_detail.detail_id',
                        'vpro_video_files.video_file_id'
                    ])
                    ->joinWith(['vproVideoFiles', 'vproVideoDetail'])
                    ->where(['vpro_video.video_lesson_id' => $k])
//            ->createCommand()->getRawSql();
                    ->asArray()
                    ->one();
                if($videoInfo != null) {
                    $vproVideoFiles = VproVideoFiles::findOne(['video_file_id' => $videoInfo['video_file_id']])->delete();
                    $vproVideoDetail = VproVideoDetail::findOne(['detail_lesson_id' => $videoInfo['detail_id']])->delete();
                    $vproVideo = VproVideo::findOne(['video_id' => $videoInfo['video_id']])->delete();
                }
            }
            $transaction->commit();
            return $this->returnInfo('delete success!');
        } catch(Exception $e) {
            $transaction->rollback();
            return $this->returnInfo('delete lesson failed!', 'DELETE_FAILED');
        }
    }
}
