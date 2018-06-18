<?php
/**
 * Created by PhpStorm.
 * User: Red
 * Date: 2018/6/6
 * Time: 23:45
 */
namespace api\common;

use app\common\Common;
use app\models\ModelFactory;
use app\models\VproCourses;
use Codeception\Module\Redis;
use common\RedisInstance;

class CourseApi{
    /**
     * ----------------------------------------------------------------------------------------------------------------
     * Course Check
     *-----------------------------------------------------------------------------------------------------------------
     */
    private $redis;
    function __construct()
    {
        $this->redis = RedisInstance::getRedis();
    }

    /**
     * 传入id，返回课程相关信息，注意：这里id如果不存在不会报错而是忽略
     * @param $course_ids
     * 传入数组，课程ids，先去redis查看课程是否存在并且不是空字符串，放入新数组；
     * 如果没找到，去数据库找，找到了就写入redis并返回给新数组， 没找到就往redis写入一个空字符串，防止缓存穿透
     * @return array $check_res 返回一个包含所有传入课程id的数组
     *
     * 这个空字符串需要定时清理
     *
     */
    function checkCourses($course_ids){
        $check_res=[];
        foreach($course_ids as $v){
            $redis_str= $this->redis->hGet('VproCourses', $v);
            if($redis_str === null || $redis_str === ""){
                $res = VproCourses::_getDetail($v);
                if($res){
//                    $this->redis->hset("VproCourses", $v, json_encode($res));
                    array_push($check_res, $res);
                }else{
                    //这里后台需要准备一个程序，定时运行检查hash中的空字符串，找到就删除字段
                    if($redis_str === null)$this->redis->hSet("VproCourses", $v, "");
                }
            }else{
                $res = json_decode($redis_str);
                array_push($check_res, $res);
            }
        }
        return $check_res;
    }
    /**
     * 检查课程id数组和实际返回数组一致性
     * @param $course_ids
     * @param $courses
     * @return $res
     * 返回格式：["difference"=>[xxx,xxx,xxx],"consistency"=>true|false]
     */
    function checkCoursesConsistency($course_ids, $courses){
        $res=[];
        foreach($courses as $item){
            if(!in_array($item->course_id, $course_ids)){
                array_push($res['difference'],$item->course_id);
            }
        }
        if(count($res)>0){
            //存在difference成员，说明不一致
            $res['consistency']=false;
            return $res;
        }
        //difference成员存在，说明一致
        $res['consistency']=true;
        return $res;
    }
    /**
     * @param $course_id
     * @return array|\yii\db\ActiveRecord[]
     * 获得课程下的详细课时列表
     */
    public function getCourseLessonList($course_id){
        if(RedisInstance::checkRedisKey($course_id, 'VproLessonsList') || RedisInstance::checkExpired($course_id, 'VproLessonsList')) {
            $vproCourseLessonList = ModelFactory::loadModel('vpro_courses_lesson_list');
//            $l_res = $vproCourseLessonList::find()->where(['lesson_course_id'=>$course_id])->asArray()->all();
            $l_res = $vproCourseLessonList::find()->alias('vl')
                ->select(['vl.*', 'vv.*', 'vd.*', 'vf.*'])
                ->join('LEFT JOIN', 'vpro_video as vv', 'vv.video_lesson_id = vl.lesson_id and vl.lesson_is_chapter_head = 0')
                ->join('LEFT JOIN', 'vpro_video_detail AS vd', 'vd.detail_lesson_id = vl.lesson_id and vl.lesson_is_chapter_head = 0')
                ->join('LEFT JOIN', 'vpro_video_files AS vf', 'vf.video_file_lesson_id = vl.lesson_id and vl.lesson_is_chapter_head = 0')
                ->where(['lesson_course_id'=>$course_id])
//                ->createCommand()->getRawSql();
                ->asArray()->all();
            if (count($l_res) === 0) common::failScoring(\Yii::$app->params['DB_MISS']);
//            RedisInstance::hsetex('VproLessonsList', $course_id, RedisInstance::expired_time(60*12, 60*24), json_encode($l_res));
            RedisInstance::hsetex('VproLessonsList', $course_id, RedisInstance::expired_time(0, 0), json_encode($l_res));
        } else {
            $l_res = json_decode(RedisInstance::hgetex('VproLessonsList', $course_id));
        }
        return $l_res;
    }

    /**
     * 拿到课程的信息和课时信息
     * 课程存在redis的VproCourses中
     * 课时存在redis的hash表（表名为课程id）[courseId][lessonId]中
     */
    public function getCourseLesson($courseId, $lessonId) {
        if (!RedisInstance::checkRedisKey($courseId, 'VproCourses')) {
            $vproCourses = new VproCourses();
        } else {
            $courseInfo = $this->redis->hGet('VproCourses', $courseId);
        }
        if (!RedisInstance::checkRedisKey($lessonId, $courseId)) {

        } else {
            $lessonInfo = $this->redis->hGet($courseId, $lessonId);
        }
    }

    function getCourses($courseId) {
        if (!RedisInstance::checkRedisKey($courseId, 'VproCourses')) {
            $vproCourses = new VproCourses();
            $courseInfo = $vproCourses::find()->alias('vc')
                ->select([
                    'vc.course_id',
                    'vc.course_title',
                    'vc.course_pid',
                    'vc.course_time',
                    'vc.course_status',
                    'vc.course_price',
                    'vc.course_discount_price',
                    'vcc.course_cover_address',
                    'va.auth_appid as course_author',
                ])
                ->join('LEFT JOIN', 'vpro_courses_cover as vcc', 'vc.course_id = vcc.course_cover_id')
                ->join('LEFT JOIN', 'vpro_auth as va', 'vc.course_author = va.auth_id')
                ->where(['vc.course_id' => $courseId])->limit(1)
//                ->createCommand()->getRawSql();
                ->asArray()->one();
            if ($courseInfo === null)common::failScoring(\Yii::$app->params['DB_MISS']);
            RedisInstance::getRedis()->hSet('VproCourses', $courseInfo['course_id'], json_encode($courseInfo));
        } else {
            $courseInfo = ((array)json_decode(RedisInstance::getRedis()->hGet('VproCourses', $courseId)));
        }
        return $courseInfo;
    }

    public function getRecCourses($courseId) {
        $course = $this->getCourses($courseId);
        $navId = $course['course_pid'];
        if (RedisInstance::checkRedisKey('rec' . $navId)) return ((array)json_decode(RedisInstance::getRedis()->get('rec' . $navId)));
        $vproCourses = new VproCourses();
        $res = $vproCourses::find()
            ->alias('vc')
            ->select([
                'vc.course_id',
                'vc.course_title',
                'vc.course_pid',
                'vc.course_time',
                'vc.course_status',
                'vc.course_price',
                'vc.course_discount_price',
                'vcc.course_cover_address',
                'va.auth_appid as course_author',
            ])
            ->join('LEFT JOIN', 'vpro_courses_temp_detail as vt', 'vt.course_id = vc.course_id')
            ->join('LEFT JOIN', 'vpro_courses_cover as vcc', 'vc.course_id = vcc.course_cover_id')
            ->join('LEFT JOIN', 'vpro_auth as va', 'vc.course_author = va.auth_id')
            ->where('vc.course_pid = ' . $navId)
            ->orderBy(['vt.course_clickNum' => SORT_DESC, 'vt.course_score' => SORT_DESC])
            ->limit(5)
//            ->createCommand()->getRawSql();
            ->asArray()->all();
        RedisInstance::getRedis()->setex('rec' . $navId, 3600, json_encode($res));
        return $res;
    }
}