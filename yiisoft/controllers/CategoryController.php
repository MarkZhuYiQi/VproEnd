<?php
namespace app\controllers;
use common\RedisData;
use app\models\ModelFactory;
use app\models\Customer;
use Yii;
use yii\base\Exception;
use yii\web\Controller;
use yii\web\UnauthorizedHttpException;

class CategoryController extends CombaseController
{
//------------------------------------------------------------------------------------------------------------------

//----------------------------------------------------------------------------------------------------------------------
    public function init()
    {
        parent::init();
        $this->enableCsrfValidation=false;
    }
    /**
     * 从数据库获得导航内容
     * @return array
     */
    public function getNav()
    {
        $vpro_navbar = ModelFactory::loadModel("vpro_navbar");
        $originNav = $vpro_navbar::find()->orderBy('nav_id')->asArray()->all();
        return $originNav;
    }
    /**
     * 生成导航层级关系
     * @return array
     */
    public function genNavTree()
    {
        $navTree = [];
        $navList = $this->getNav();
        foreach ($navList as $nav)
        {
            if($nav['nav_pid'] === 0)
            {
                $children = $this->genNavChild($nav, $navList);
                if(count($children) > 0) $nav['children'] = $children;
                array_push($navTree, $nav);
            }
        }
        $this->redis->setex('VproNavbar', 3600 * rand(8, 20), json_encode($navTree));
        return $navTree;
    }

    /**
     * 递归，通过父元素查找其子导航，将所有子导航找出来，该函数服务于genNavTree
     * @param $nav              $nav顶级元素
     * @param array $navList    导航列表
     * @return array            返回子元素数组
     */
    private function genNavChild($nav, $navList=[])
    {
        $res = [];
        foreach($navList as $v)
        {
            // 找出父元素的child
            if($v['nav_pid'] === $nav['nav_id'])
            {
                $children = $this->genNavChild($v, $navList);
                if(count($children) > 0) $v['children'] = $children;
                array_push($res, $v);
            }
        }
        return $res;
    }






    public function actionCategories(){
        $request=Yii::$app->request;
        $page = $request->get("p", 1);
        $nav_key = $request->get("category", 'index');
        $hash_name = "VproCourses_".$nav_key;
        if($this->checkRedisKey($hash_name) && $this->checkRedisKey($hash_name, $page) && $this->checkExpired($hash_name)){
            if($this->redis->hExists($hash_name, $page)) {
                return json_encode($this->returnInfo(json_decode($this->hgetex($hash_name, $page)), $this->params['RETURN_SUCCESS']));
            }
            // redis中没找到该分类下的对应页，可能是瞎传页码数据
            return json_encode($this->returnInfo("page not found", $this->params['PARAMS_ERROR']));
        }
        //如果nav_id没有找到说明有人瞎访问,直接返回首页
        $nav_id = $this->redis->hGet("VproNavbarList", $nav_key) ? $this->redis->hGet("VproNavbarList", $nav_key) : 9999;
        $res = json_decode($this->redis->get("VproNavbar"));
        //拿到了所需要的导航对象，根据这个集合去取值
        $request_nav = $this->_getSubNavs($res, $nav_id);
        //导航下的所有子导航的IDs
        $allNavs = $this->_getAllNavIds($request_nav);
        //把所有子导航数组转变成字符串,等待sql查询。
        $allNavs = implode(",",$allNavs);
        //获得该导航下所有课程
        if($this->_getCoursesInfo($nav_key, $allNavs)){
            if($c_res=$this->hgetex($hash_name, $page)){
                return json_encode($this->returnInfo(json_decode($c_res)));
            }
            // 数据库里也得不到对应的数据
            return json_encode($this->returnInfo("mpage not found", $this->params['PARAMS_ERROR']));
        }
        // 数据库里没有这个课程
        return json_encode($this->returnInfo("failed when get courses!", $this->params['QUERY_FAILURE']));
    }
    public function actionGetpage(){
        $request=Yii::$app->request;
        if($nav_key = $request->get("category", "")){
            return json_encode($this->returnInfo($this->redis->hLen("VproCourses_".$nav_key)), $this->params['RETURN_SUCCESS']);
        }
        return json_encode('miss params', $this->params['PARAMS_ERROR']);

    }
    /**
     * @param $res      导航集合
     * @param $nav_id   需要找到的导航id
     * @return mixed    返回的是一个包含当前寻找的导航内容的导航对象
     * 递归导航集合，找到需要的导航id以后返回。
     *
     */
    public function _getSubNavs($res, $nav_id=0){
        if($nav_id==9999){
//            return $res;
            return new class($res){
                public $children=[];
                function __construct($res)
                {
                    $this->children=$res;
                }
            };
        }
        foreach($res as $value){
            if($value->nav_id==$nav_id){
                $request_nav = $value;
                break;
            }
            elseif(is_array($value->children)){
                $request_nav=$this->_getSubNavs($value->children, $nav_id);
            }
            if($request_nav)break;
        }
        return $request_nav;
    }

    /**
     * 获得下一级的所有导航
     * @param $req_nav
     * @return array
     */
    public function _getSubNav($req_nav){
        if(is_array($req_nav)){
            $temp=$req_nav;
        }elseif(is_array($req_nav->children)){
            $temp = $req_nav->children;
        }
        $nav_ids=[];
        if(isset($temp)){
            foreach($temp as $key => $value){
                if("children"!==$key){
                    $nav_ids[$value->nav_id]=new class($value){
                        function __construct($value)
                        {
                            foreach($value as $k=>$v){
                                if($k != "children")$this->$k = $v;
                            }
                        }
                    };
                }
            }
        }
        return $nav_ids;
    }


    //获得该导航下所有的导航子ID
    //用于查找导航下的全部课程使用
    public function _getAllNavIds($req_nav,$nav_ids=[]){
        if(is_array($req_nav->children)){
            $nav_ids=$this->_getAllNavIds($req_nav->children,$nav_ids);
        }else{
            if(is_array($req_nav)){
                foreach ($req_nav as $value){
                    if(is_array($value->children)){
                        $nav_ids=$this->_getAllNavIds($value->children, $nav_ids);
                    }else{
                        $nav_ids[]=$value->nav_id;
                    }
                }
            }else{
                $nav_ids[]=$req_nav->nav_id;
            }
            return $nav_ids;
        }
        return $nav_ids;
    }

    /**
     * 拿到分类下的全部课程信息
     * @param $nav_key      $string 分类名称
     * @param $allNavs
     * @return bool
     */
    public function _getCoursesInfo($nav_key, $allNavs)
    {
        $db = Yii::$app->db;
        $query = <<<QUERY
SELECT
	course.course_id,
	course.course_author,
	course.course_time,
	course.course_price,
	course.course_title,
	course_cover.course_cover_key,
	course_cover.course_cover_address,
	course_cover.course_cover_isuploaded,
	course_cover.course_cover_isused,
	course_detail.course_score,
	course_detail.course_clickNum
FROM
	vpro_courses_temp_detail AS course_detail
LEFT JOIN vpro_courses_cover AS course_cover ON course_detail.course_id = course_cover.course_cover_id
LEFT JOIN vpro_courses AS course ON course_detail.course_id = course.course_id
WHERE
	course_detail.course_id IN (
		SELECT
			course_id
		FROM
			vpro_courses_temp_detail
		WHERE
			course_pid IN ($allNavs)
	)
QUERY;
        $database = "VproCourses";
        if($res = $db->createCommand($query)->queryAll()){
            $pageRange = 40;
            $pageCount = ceil(count($res) / $pageRange);
            for ($i = 1; $i <= $pageCount; $i++) {
                $this->hsetex($database . "_" . $nav_key, $i, $this->expired_time(0,0), json_encode(array_slice($res, ($i - 1) * $pageRange, $pageRange)));
            }
            return true;
        }
        $this->hsetex($database . "_" . $nav_key, 1, $this->expired_time(0,0), '');
        return false;
    }
}