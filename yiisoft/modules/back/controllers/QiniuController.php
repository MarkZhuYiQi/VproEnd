<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 3/30/2018
 * Time: 9:15 AM
 */
namespace back\controllers;

use app\common\JwtAuth;
use app\models\ModelFactory;
use app\models\VproCourses;
use app\models\VproCoursesLessonList;
use app\models\VproVideo;
use app\models\VproVideoFiles;
use Qiniu\Auth;
use Qiniu\Config;
use Qiniu\Storage\BucketManager;
use yii\db\Exception;

class QiniuController extends BackBaseController {
    public $modelClass = 'app\models\VproCoursesLessonList';
    function actionGetSecretKey() {
        return [
            'code' => 20000,
            'secretkey' => $this->params['SECRET_KEY']
        ];
    }
    function getAuth() {
        $accessKey = $this->params['ACCESS_KEY'];
        $secretKey = $this->params['SECRET_KEY'];
        return new Auth($accessKey, $secretKey);
    }

    /**
     * 七牛覆盖上传的坑：
     * 必须保证scope里的bucket:key 这个key与
     * uploadToken生成函数第2个参数的key一致，否则不能覆盖
     * @return array
     */
    function actionGetUploadToken() {
        $bucket = $this->params['BUCKET_NAME'];
        $type = $this->request->get('type', false);
        $user_id = $this->request->get('user_id', 0);
        $course_id = $this->request->get('course_id', 0);
        $lesson_id = $this->request->get('lesson_id', 0);
        $video_key = $this->request->get('video_key', null);
        $img_key = $this->request->get('img_key', null);
        $key = null;
//         code 30001 lesson_id or user_id not set
        if(!$user_id || !$course_id) return $this->returnInfo('base information not set!', 'INFO_MISS');
        if($type === 'video') {
            $mimeLimit = 'video/*';
            $callbackUrl = 'http://223.112.88.211:9696/index.php/back/qiniu/video-callback';
            // 20180426: 在新增视频时lesson_id还没有生成，所有这里是没有的，注释掉了
//            if(!$lesson_id) return $this->returnInfo('video info set error!', 'VIDEO_INFO_ERROR');
        }else if ($type === 'image') {
            $callbackUrl = 'http://223.112.88.211:9696/index.php/back/qiniu/img-callback';
            $mimeLimit = 'image/*';
        } else {
            return $this->returnInfo('MimeType Error!', 'MIMETYPE_ERROR');
        }
        $auth = $this->getAuth();
        $policy = array(
            'deadline' => time() + 3600,
            'mimeLimit' => $mimeLimit,
            'callbackBodyType' => 'application/json',
            'callbackUrl' => $callbackUrl,
            'callbackBody' => '{"fname":"$(fname)", "key":"$(key)", "size": "$(fsize)", "courseid": '.$course_id.', "lessonid": '.$lesson_id.', "desc":"$(x:desc)"}'
        );
        // 如果有视频信息，就放入token中进行覆盖上传
        if($type === 'video'){
            if ($video_key && $video_key != 'error') {
                $policy['scope'] = $bucket . ':' . $video_key;
                $key = $video_key;
            } else {
                $policy['saveKey'] = 'video' . $user_id . substr($course_id, mb_strlen($course_id) - 5, 5). date('YmdHis');
            }
        } else {
            // 如果有图片信息，就放入token中进行覆盖上传
            if ($img_key && $img_key != 'error') {
                $policy['scope'] = $bucket . ':' . $img_key;
                $key = $img_key;
            }
        }
//        return $this->returnFormat($policy);
        $upToken = $auth->uploadToken($bucket, $key, 3600, $policy);
        return $this->returnInfo($upToken);
    }
    /**
     * 七牛主动调用的地址
     * 调用表示上传成功，获得数据写入数据库，返回数据库返回的ID
     * @return array
     */
    function actionVideoCallback() {
        $body = $this->request->bodyParams;
        $course_id = $body['courseid'];
        $lesson_id = $body['lessonid'];
        $key = $body['key'];
        $size = $body['size'];
        if ($course_id && $key) {
            $vproVideoFiles = ModelFactory::loadModel('vpro_video_files');
            $searchRes = $vproVideoFiles::findOne(['video_file_key' => $key]);
            $video_id = false;
            // 没有就新建一条记录
            if ($searchRes === null) {
                $is_exist = false;
                $vproVideoFiles->video_file_key = $key;
                $vproVideoFiles->video_file_uptime = time();
                $vproVideoFiles->video_file_size = $size;
                $vproVideoFiles->video_file_isuploaded = 1;
                $vproVideoFiles->video_file_isused = 0;
                $vproVideoFiles->video_file_size = $size;
                $vproVideoFiles->video_file_lesson_id = $lesson_id;
                $vproVideoFiles->save();
                $video_file_id = $vproVideoFiles->video_file_id;
            } else {
                $searchRes->video_file_uptime = time();
                $searchRes->video_file_size = $size;
                $is_exist = true;
                $searchRes->save();
                $video_file_id = $searchRes->video_file_id;
            }
            // 以下为测试用代码，生产环境不会有这问题-------------------------------------------

            //------------------------------------------------------------------------------
            return ['key' => $key, 'video_file_id' => $video_file_id, 'is_exist' => $is_exist];
        } else {
            return $this->returnInfo('data error, may be course_id or lesson_id or video_name not set', 'VIDEO_INFO_ERROR');
        }
    }
    function actionImgCallback() {
        $body = $this->request->bodyParams;
        return $this->returnInfo($body);
        $course_id = $body['courseid'];
        $key = $body['key'];
        $size = $body['size'];
        if ($course_id && $key) {
            $vproCoursesCover = ModelFactory::loadModel('vpro_courses_cover');
            $searchRes = $vproCoursesCover::findOne(['course_cover_id' => $course_id]);
            if ($searchRes === null) {
                $is_exist = false;
                $vproCoursesCover->course_cover_id = $course_id;
                $vproCoursesCover->course_cover_key = $key;
                $vproCoursesCover->course_cover_address = $this->params['YUN_ADDRESS'];
                $vproCoursesCover->course_cover_uptime = time();
                $vproCoursesCover->course_cover_isupload = 1;
                $vproCoursesCover->save();
                $course_cover_id = $vproCoursesCover->course_cover_id;
            } else {
                $searchRes->course_cover_uptime = time();
                $is_exist = true;
                $searchRes->save();
                $course_cover_id = $searchRes->course_cover_id;
            }
            // 以下为测试用代码，生产环境不会有这问题-------------------------------------------

            //------------------------------------------------------------------------------
            return ['key' => $key, 'course_cover_id' => $course_cover_id, 'is_exist' => $is_exist];
        } else {
            return $this->returnInfo('data error, may be course_id or img_name not set', 'VIDEO_INFO_ERROR');
        }
    }
    function actionDelCover() {
        $key = $this->request->post('course_cover_key', false);
        $auth = $this->getAuth();
        $config = new Config();
        $bucketManager = new BucketManager($auth, null);
        $err = $bucketManager->delete($this->params['BUCKET_NAME'], $key);
        if ($err) {
            return $this->returnInfo('delete failed!', 'DELETE_FAILED');
        }
        return $this->returnInfo('delete success');
    }
    function actionDelVideos() {
        $key = $this->request->post('lesson_ids', false);
        return $this->returnInfo($key);
        $auth = $this->getAuth();
        $bucketManager = new BucketManager($auth, null);
        foreach($key as $lesson_id) {
            $file = new VproVideoFiles();
            $file->findOne(['video_file_lesson_id' => $lesson_id]);
            if ($file) {
                $err = $bucketManager->delete($this->params['BUCKET_NAME'], $key);
                if ($err) {
                    return $this->returnInfo('delete failed!' . $err, 'DELETE_FAILED');
                }
            }
        }
        return $this->returnInfo('delete success');
    }
}
