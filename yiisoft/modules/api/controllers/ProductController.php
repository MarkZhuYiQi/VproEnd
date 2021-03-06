<?php

namespace api\controllers;
use api\common\CourseApi;
use app\common\Common;
use app\models\ModelFactory;
use app\models\VproCourses;


class ProductController extends ShoppingBaseController
{
    private $courseApi;
    public function actionTtt(){
//        $res = $this->getCourseLessonList('1007');
        $res1 = $this->getCourseDetail('1007');
        var_export($res1);
        $this->getCrumbnavbar($res1['course_pid']);
    }
    public function init()
    {
        parent::init();
        $this->enableCsrfValidation = false;
        $this->courseApi = new CourseApi();

    }
    /**
     * 课程详细信息页面的信息请求
     * 从数据库获得课程的信息和课程的目录，然后返回给前台，用于生成目录和详细信息的页面
     * @param $course_id
     * @return array
     */
    public function actionCoursedetail()
    {
        $request = $this->request;
        $course_id = $request->get("cid", false);
        if($course_id) {
            $l_res = $this->courseApi->getCourseLessonList($course_id);
            $d_res = $this->getCourseDetail($course_id);

            $c_res = $this->getCrumbnavbar($d_res['course_pid']);
            if (!count($l_res) || !count($d_res) || !count($c_res)) {}
            return json_encode($this->returnInfo(['detail'=>$d_res, 'lesson_list'=>$l_res, 'crumb'=>$c_res]));
        } else {
            return json_encode($this->returnInfo('params missing', $this->params['PARAMS_ERROR']));
        }
    }
    function actionCheckcourses()
    {
        $orderInfo = $this->request->bodyParams;
        if(count($orderInfo) === 0)return json_encode($this->returnInfo('courses not found', $this->params['COURSES_NOT_FOUND']));
        $check_res = $this->courseApi->checkCourses($orderInfo["order_course_ids"]);
        return json_encode($this->returnInfo($check_res));
    }

    /**
     * @param $course_id
     * @return array|null|\yii\db\ActiveRecord
     * 获得课程的详细信息
     */
    public function getCourseDetail($course_id)
    {
        $VproCourses = new VproCourses();
        $res = $VproCourses::find()
            ->select([
                'vpro_courses.course_id',
                'vpro_courses.course_pid',
                'vpro_courses.course_title',
                'vpro_courses.course_price',
                'vpro_courses.course_author',
                'vpro_courses.course_discount_price',
                'vpro_courses_cover.course_cover_address',
                'vpro_auth.auth_appid',
                'vpro_courses_temp_detail.course_score',
                'vpro_courses_temp_detail.course_clickNum'
            ])
            ->joinWith(['vproCoursesTempDetail', 'vproCoursesCover', 'vproAuth'])->where(['vpro_courses.course_id'=>$course_id])->asArray()->one();
        if ($res === null) {
            // 这里进行计分，如果db没有命中，说明是没有意义的课程id，涉嫌恶意访问，进行恶意积分累加
            common::failScoring($this->params['DB_MISS']);
        }
        return $res;
    }

    /**
     * 面包屑导航位置
     * @param $course_pid
     * @return array
     */
    public function getCrumbnavbar($course_pid)
    {
        if ($vproCrumbNavbar = $this->redis->get("VproCrumbNavbar")) {
            $originNav = json_decode($vproCrumbNavbar);
        } else {
            $vpro_navbar = ModelFactory::loadModel("vpro_navbar");
            $originNav = $vpro_navbar::find()->asArray()->all();
            $this->redis->set("VproCrumbNavbar", json_encode($originNav));
        }
        if ($course_pid) {
            $crumb = [];
            $crumb = $this->_crumbRecursion($course_pid, $originNav);
            rsort($crumb);
            return $crumb;
        }
    }

    /**
     * 根据当前导航，一级一级往上寻找父级导航，一直到顶层
     * @param $course_pid
     * @param $originNav
     * @param array $res
     * @return array
     */
    public function _crumbRecursion($course_pid, $originNav, $res=[]){
        foreach($originNav as $value){
            if($value->nav_id ==$course_pid){
                array_push($res, (array)$value);
                if($value->nav_pid!=0)$res=$this->_crumbRecursion($value->nav_pid, $originNav, $res);
            }
        }
        return $res;
    }
}