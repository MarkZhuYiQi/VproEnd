<?php
namespace api\controllers;
use app\controllers\CombaseController;
use app\controllers\RedisController;
use app\controllers\SnowflakeController;
use app\models\ModelFactory;
use app\models\VproCourses;
use Exception;

/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/12/2018
 * Time: 3:42 PM
 */

class ShoppingBaseController extends CombaseController{
    protected $redis;
    protected $db;
    function init()
    {
        parent::init();
        $this->redis = RedisController::connect() ? $this->redis : $this->returnInfo('database connect error!', 'REDIS_CONNECT_ERROR');
//        $this->redis = \Yii::$app->get('redis');
        $this->db = \Yii::$app->db;
        $this->params = \Yii::$app->getModule('api')->params;
    }
    /**
     * 通过twitter雪花算法获得订单编号
     * @return int
     */
    protected function getOrderId(){
        return (new SnowflakeController())->getOrderId();
    }

    /**
     * 通过订单编号获得订单信息
     * @param int $primaryKey
     * @return mixed
     */
    protected function getOrderByPrimaryKey(int $primaryKey){
        $one = $this->_orderModel->findOne($primaryKey);
        $primaryKey = $this->getOrderPrimaryKey();
        if($one[$primaryKey]){
            return $one;
        }else{
            return new $this->_orderModelName();
        }
    }
    /**
     * 获得订单表主键
     * @return string
     */
    protected function getOrderPrimaryKey(){
        return 'order_id';
    }

    /**
     * ----------------------------------------------------------------------------------------------------------------
     * Course Check
     *-----------------------------------------------------------------------------------------------------------------
     */

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
    protected function checkCourses($course_ids){
        $check_res=[];
        foreach($course_ids as $v){
            $redis_str= $this->redis->hGet('VproCourses', $v);
            if($redis_str==null || $redis_str==""){
                $res = VproCourses::_getDetail($v);
                if($res){
//                    $this->redis->hset("VproCourses", $v, json_encode($res));
                    array_push($check_res, $res);
                }else{
                    //这里后台需要准备一个程序，定时运行检查hash中的空字符串，找到就删除字段
                    if($redis_str==null)$this->redis->hSet("VproCourses", $v, "");
                }
            }else{
                $res=json_decode($redis_str);
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
    protected function checkCoursesConsistency($course_ids, $courses){
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
     * ----------------------------------------------------------------------------------------------------------
     * coupon check && modify
     * ----------------------------------------------------------------------------------------------------------
     */
    /**
     * 检查用户优惠券认领条目是否存在，不存在就插入一条新的
     * @param $this->redis
     * @param $user_info
     * @return boolean
     */
    function checkUserCouponExisted($user_id){
        if(!$this->redis->exists('coupon_'.$user_id) || !$this->redis->exists('coupon_isexisted_'.$user_id)) {
//        if($this->redis->exists('coupon_'.$user_id) || !$this->redis->exists('coupon_isexisted_'.$user_id)) {
            $vpro_user_coupon = ModelFactory::loadModel('vpro_user_coupon');
            $user_coupons=$vpro_user_coupon->findOne(['user_coupon_auth_id' => $user_id]);
            if($user_coupons) {
                $this->redis->multi()
                    ->set('coupon_'.$user_id, $this->_convertDecStr2AsciiStr($vpro_user_coupon->user_coupon_bit))
                    ->set('coupon_isexisted_'.$user_id, $this->_convertDecStr2AsciiStr($vpro_user_coupon->user_coupon_isexisted_bit))
                    ->exec();
            } else {
                $vpro_user_coupon->user_coupon_auth_id = $user_id;
                $new_user_coupon_bit = $this->_newUserCouponBit();
                $vpro_user_coupon->user_coupon_bit=$new_user_coupon_bit;
                $vpro_user_coupon->user_coupon_isexisted_bit=$new_user_coupon_bit;
                if($vpro_user_coupon->insert())return true;
                return false;
            }
        }
        return true;
    }
    /**
     * @param $coupon_id    优惠券ID
     * @param $order_id     订单编号
     * @param $user_id      用户id
     * @return string
     * 如果coupon存在，修改该位置为0表示已经被使用了，否则报错
     */
    function changeCouponStatus($coupon_id, $order_id, $user_id){
        try{
            $user_coupon_isexisted = $this->redis->get('coupon_isexisted_'.$user_id);
            if($user_coupon_isexisted==null)throw new Exception("user coupons existing string lost, retry puting order again",[],28);
            if($this->redis->setBit('coupon_isexisted_'.$user_id, 0))throw new Exception("ALERT!User Coupon has already been used!");
            $res=['log_operation'=>2,'log_coupon_id'=>$coupon_id,'log_user_id'=>$user_id, 'log_order_id'=>$order_id];
            $this->redis->lPush('vpro_coupon_logs',json_encode($res));
        }catch(\Exception $e){
            $msg=$e->getMessage();
            $code=$e->getCode();
            return json_encode([
                'message'=>$msg,
                'code'=>$code,
                'status'=>false
            ]);
        }
    }


    /**
     * 这是一种意外错误情况，在注册时需要给用户创建一个优惠券认领记录，如果没有创建，在这里临时创建。
     * 这个初始值应该在外部设置静态值统一管理。
     * @return int
     */
    function _newUserCouponBit(){
        return 0;
    }

    /**
     * 获得有效的优惠券信息
     * 将coupon_#user_id#和coupon_isexisted_#user_id#进行与运算，如果两者同时为1，说明该coupon可用 ，结果存入coupoon_valid_#user_id#
     * @param $this->redis
     * @param $user_info
     * @param bool $coupon_id
     * @return array
     */
    function _getvalidCoupons($user_info, $coupon_id=false){
        $this->redis->bitOp('AND', 'coupon_valid_'.$user_info['user_id'], 'coupon_'.$user_info['user_id'], 'coupon_isexisted_'.$user_info['user_id']);
        $coupon_valid_bit=$this->redis->get('coupon_valid_'.$user_info['user_id']);
        //获得优惠券id数组
        $couponIds=$this->_getCouponIds($this->_convertAsciiStr2DecStr($coupon_valid_bit));
        if($coupon_id!==false){
            if(in_array($coupon_id, $couponIds)){
                $coupon_valid = (ModelFactory::loadModel('vpro_coupon'))->findAll([$coupon_id]);
            }
        }else{
            //从数据库中找出所有优惠券
            $coupon_valid = (ModelFactory::loadModel('vpro_coupon'))->findAll($couponIds);
        }
        if(count($coupon_valid)){
            $coupon_valid_arrs=[];
            foreach($coupon_valid as $v){
                $coupon_valid_arrs[]=[
                    'coupon_id'=>$v->coupon_id,
                    'coupon_condition'=>$v->coupon_condition,
                    'coupon_limit' => $v->coupon_limit,
                    'coupon_discount' => $v->coupon_discount,
                    'coupon_amount' => $v->coupon_amount,
                    'coupon_provide' => $v->coupon_provide,
                    'coupon_start_date' => $v->coupon_start_date,
                    'coupon_end_date' => $v->coupon_end_date,
                ];
            }
            return $coupon_valid_arrs;
        }else{
            return [];
        }
    }
    /**
     * 数据库中十进制数字字符串转换成ascii字符串还给redis
     * 将数据库拿出来的bit转换成字符串
     * @param $arr_string
     * @return string
     */
    function _convertDecStr2AsciiStr($arr_string){
        $bit_arr=explode(',',$arr_string);
        $str='';
        foreach($bit_arr as $k => $v){
            $str.=chr($v);
        }
        return $str;
    }

    /**
     * 将redis拿过来的ascii字符串转换成10进制字符串
     * @param $org_string
     * @return string
     */
    function _convertAsciiStr2DecStr($org_string){
        $org_arr=str_split($org_string);
        foreach($org_arr as $k=>$v){
            $org_arr[$k]=ord($v);
        }
        return implode(',',$org_arr);

    }
    /**
     * 将十进制字符串分割成数组，根据位置，计算出每一位值为1的bit对应的位置，这个位置就是优惠券id
     * @param $coupon_str, 十进制，由8个字节bit组成，逗号隔开
     * @return array 返回包含所有有效优惠券id的数组
     */
    function _getCouponIds($coupon_str){
        $coupon_ids=[];
        foreach(explode(',',$coupon_str) as $key=>$value){
            $value=decbin($value);
            if(strlen($value)<8)$value=sprintf('%08s', $value);
            foreach(str_split($value) as $k=>$v){
                if($v==1)array_push($coupon_ids, $key*8+$k);
            }
        }
        return $coupon_ids;
    }
    /**
     * -----------------------------------------------------------------------------------------------------------
     * cartController
     * -----------------------------------------------------------------------------------------------------------
     */
    /**
     * 根据提供的detail信息删除购物车对应信息
     * detail格式：
     * ['is_login'=>true|false,'cart_userid'=>xx,'cart_detail'=>[]]
     * @param $detail
     * @return string
     */
    function delCartDetail($detail){
        $vproCartDetail = ModelFactory::loadModel('vpro_cart_detail');
        $transaction = $vproCartDetail::getDb()->beginTransaction();
        try{
            if(!count($detail["cart_detail"])>0)throw new \Exception("could not delete the cart products due to lack of cart_detail.");
            foreach($detail["cart_detail"] as $v){
                if($record = $vproCartDetail::findOne(['cart_course_id'=>$v['cart_course_id'], 'cart_parent_id'=>$v['cart_parent_id']])){
                    $cart_name = $detail['is_login']?"cart".$detail['cart_userid']:'cookiecart'.$detail['cart_id'];
                    $res=$record->delete();
                    if($res){
                        $res = $this->redis->sMembers($cart_name);
                        foreach($res as $key =>$value){
                            if(json_decode($value)->cart_course_id==$v['cart_course_id']){
                                $this->redis->sRem($cart_name, $value);
                            }
                        }
                        $transaction->commit();
                        return json_encode(["status"=>true, "course_id"=>$v["cart_course_id"]]);
                    }else{
                        $transaction->rollBack();
                        return json_encode(["status"=>false]);
                    }
                }
            }
        }catch(Exception $e){

        }
    }
    /**
     * @param $course_id
     * @return array|\yii\db\ActiveRecord[]
     * 获得课程下的详细课时列表
     */
    public function getCourseLessonList($course_id){
        if(!$this->checkRedisKey($course_id, 'VproLessonsList')) {
            $vproCourseLessonList = ModelFactory::loadModel('vpro_courses_lesson_list');
            $l_res = $vproCourseLessonList::find()->where(['lesson_course_id'=>$course_id])->asArray()->all();
            $this->hsetex('VproLessonsList', $course_id, $this->expired_time(60*12, 60*24), json_encode($l_res));
        } else {
            $l_res = json_decode($this->hgetex('VproLessonsList', $course_id));
        }
        return $l_res;
    }

}