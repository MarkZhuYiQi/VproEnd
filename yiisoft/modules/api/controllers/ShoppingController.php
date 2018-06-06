<?php
/**
 * Created by PhpStorm.
 * User: Red
 * Date: 2018/1/4
 * Time: 22:23
 */

namespace api\controllers;

use api\common\CouponApi;
use app\controllers\IdController;
use app\controllers\SnowflakeController;
use app\controllers\VproController;
use app\models\ModelFactory;
use app\models\VproCourses;
use app\models\VproOrder;
use app\models\VproOrderLogs;
use app\models\VproOrderSub;
use yii\db\Exception;

Class ShoppingController extends ShoppingBaseController {
//    function behaviors(){
//        $behaviors=parent::behaviors();
//        $behaviors['authenticator']=[
//            'class'=>JwtAuth::className(),
//            /*
//             *因为此post请求的 content-type不是one of the “application/x-www-form-urlencoded, multipart/form-data, or text/plain”, 所以Preflighted requests被发起。
//             * “preflighted” requests first send an HTTP OPTIONS request header to the resource on the other domain, in order to determine whether the actual request is safe to send.
//             * 然后得到服务器response许可之后，再发起其post请求。
//             */
//            'except'=>[]
//        ];
//        return $behaviors;
//    }
    protected $redis;
    protected $courseApi;
    function init(){
        $init = parent::init();
        $this->redis = \Yii::$app->get('redis');
        $this->courseApi = new CouponApi();
    }
    function actionUsercart(){
        
        $request=\Yii::$app->request;
        $cart_userid=$request->get('cart_userid');
        $cart_cookieid=$request->get('cart_cookieid');
        if($cart_userid==null){
            $id="cookiecart".$cart_cookieid;
        }else{
            $id="cart".$cart_userid;
        }
        if($this->redis->keys($id)){
            return json_encode($this->redis->smembers($id));
        }
        return json_encode([]);
    }
    function actionDelcartdetail(){
        $request = \Yii::$app->request;
        $detail=$request->bodyParams;
        if(count($detail)==0)return json_encode([]);
        return $this->delCartDetail($detail);
    }
    function actionAddcartdetail(){
        $request=\Yii::$app->request;
        $detail = $request->bodyParams;
        $this->addCartDetail($detail);
    }
    function actionAddcart(){
        $request=\Yii::$app->request;
        $cart_ref=$request->bodyParams;
        if(count($cart_ref)==0)return json_encode([]);
        $vproCart=ModelFactory::loadModel('vpro_cart');
        $cart = $vproCart::findOne(['cart_id'=>$cart_ref['cart_id']]);
        if($cart){
//            $vproCart->cart_payment = $vproCart->cart_payment;

            $payment=$this->addCartdetail($cart_ref, $cart->cart_payment);
            return $cart->save();

        }else{
            $payment = $this->addCartDetail($cart_ref);
            $vproCart=ModelFactory::loadModel('vpro_cart');
            $vproCart->cart_id=$cart_ref['cart_id'];
            $vproCart->cart_userid=$cart_ref['cart_userid'];
            $vproCart->cart_payment=$payment;
            $vproCart->cart_status = 1;
            $vproCart->cart_addtime=time();
            return $vproCart->save();
        }
    }
    function actionCheckcourses(){
        $request=\Yii::$app->request;
        $orderInfo=$request->bodyParams;
        if(count($orderInfo)==0)return json_encode([]);
        $check_res = $this->checkCourses($orderInfo["order_course_ids"]);
//        var_export($check_res);
        return json_encode($check_res);
    }
    function actionPutOrder(){
        $request=\Yii::$app->request;
        $orderInfo=$request->bodyParams;
        if(count($orderInfo)==0)return json_encode([]);
        try{
            //拿到所有课程的内容
            $courses = $this->courseApi->checkCourses($orderInfo['order_course_ids']);
            //检查拿出来的课程是否和传递来的课程id一致
            $consistency = $this->courseApi->checkCoursesConsistency($orderInfo['order_course_ids'], $courses);
            //检查课程一致性
            if(!$consistency['consistency'])
                throw new Exception('courses did not matched with courses sent from front-side.', [], 21);
            $res=[];
            $res['course_price'] = 0;
            //获得总价
            foreach($courses as $c){
                $res['course_price']+= $c->course_price;
            }
            if($orderInfo['course_price'] != $res['course_price'])throw new Exception('order price did not match with the price transferred from front side.', [], 27);
            $this->redis=\Yii::$app->get('redis');
            if($orderInfo['order_coupon_selected']) {
                /*
                 * 使用了优惠券的情况
                 * 课程一致性通过， 接下来就是判断优惠券
                 */
                // 判断用户认领优惠券条目是否存在，没有就创建，如果还是失败，报错
                if (!$this->checkUserCouponExisted($orderInfo["user_id"]))
                    throw new Exception('the coupon used by user is not existed', [], 22);
                //得到该用户指定使用的优惠券
                $coupon_valid = $this->_getvalidCoupons($orderInfo["user_id"], $orderInfo['order_coupon_selected'])[0];
                //判断优惠券是否可用
                if (!count($coupon_valid))
                    throw new Exception('', [], 23);
                //判断优惠券是否在可使用时间段
                if ($coupon_valid['coupon_start_date'] > time() || $coupon_valid['coupon_end_date'] < time())
                    throw new Exception('the coupon does not match with date which could be used', [], 24);
                //判断总价是否达到优惠券要求
                if ($coupon_valid['coupon_limit'] > $res['order_price'])
                    throw new Exception('total price does not reach the limit', [], 25);
                $res['coupon_used'] = $coupon_valid;
                $res['price_discount'] = $coupon_valid['coupon_discount'];
                $res['summary_price'] = $res['course_price'] - $res['price_discount'];
            }
            $res['user_id'] = $orderInfo['user_id'];
            $res['summary_price'] = $res['course_price'];
            $res['courses'] = $courses;
            if ($res['summary_price'] < 0)
                throw new Exception('total price lower than 0', [], 26);
            $payment = $this->putOrder($res);

            if($res["coupon_used"])$this->changeCouponStatus($coupon_valid['coupon_id'], $payment['order_id'], $orderInfo['user_id']);
            $cart_del_info = [];
            $cart_del_info['cart_detail']=[];
            foreach($orderInfo['order_course_ids'] as $v){
                array_push($cart_del_info['cart_detail'],['cart_course_id'=>$v, 'cart_parent_id'=>$orderInfo['cart_parent_id']]);
            }
            $cart_del_info['is_login']=true;
            $cart_del_info['cart_userid']=$res['user_id'];
            $this->delCartDetail($cart_del_info);
            return $payment;
        }catch(\Exception $e){
            /**
             * errcode:
             * 21: 课程和前台传来的不一致
             * 22: 优惠券认领条目不存在，插入新的失败
             * 23: 优惠券不存在或者已被使用
             * 24: 优惠券使用日期不符
             * 25: 总价格未达到优惠券限额
             * 26: 总价格小于0
             * 27: 课程总价和前台不相同
             */
            $msg=$e->getMessage();
            $code=$e->getCode();
            return json_encode([
                'message'=>$msg,
                'code'=>$code,
                'status'=>false
            ]);
        }

    }

    function putOrder($orderInfo){
        if(count($orderInfo)>0){
            $vproOrder = new VproOrder();
            $transaction = VproOrder::getDb()->beginTransaction();
            try{
                if(isset($orderInfo['coupon_used'])) {
                    $vproOrder->order_coupon_used = $orderInfo['coupon_used']['coupon_id'];
                    $vproOrder->order_discount = $orderInfo['coupon_used']['coupon_discount'];
                }
                $subject = array_map(function($value){
                    return $value->course_title;
                }, $orderInfo['courses']);
                $subject = substr(preg_replace("/\s/","",implode("", $subject)),0,128);
//                $order_id = IdController::getOrderId($this->redis);
                $order_id = (new SnowflakeController())->getOrderId($workId=1);
                $vproOrder->order_id = $order_id;
                $vproOrder->order_price = $orderInfo['summary_price'];
                $vproOrder->order_time = time();
                $vproOrder->user_id = $orderInfo['user_id'];
                $vproOrder->order_title = $subject;
                $payData = [
                    'body'=>$subject,
                    'subject'=>$subject,
                    'order_no'=>(string)$order_id,
                    'amount'=>$orderInfo['summary_price'],
                    'timeout_express'=>time()+86400,
                    'return_param'=>(string)$order_id,
                    'goods_type'=>1,
                ];
                $payUrl = \Yii::$app->runAction("api/pay/put-web-pay",['orderInfo'=>json_encode($payData)]);
                $vproOrder->order_pay_url = $payUrl;
                $payData['order_pay_url']=$payUrl;
                if(!$this->putSubOrder($orderInfo['courses'], $order_id))throw new Exception('sub order error!');
                if(!$this->putOrderLogs($orderInfo, $order_id, 'generate'))throw new Exception('log record error!');
                var_export($payData);
                exit();
                $vproOrder->insert();
                $transaction->commit();
                $payData['status']=true;
                return $payData;

            }catch(\Exception $e){
                $transaction->rollBack();
                var_export($e->getMessage());
            }
        }
    }

    /**
     * 写入子订单信息
     * @param $courses
     * @param $order_id
     * @return bool
     */
    function putSubOrder($courses, $order_id){
        $vpro_order_sub = new VproOrderSub();
        foreach($courses as $item){
            $vpro_order_sub->order_id = $order_id;
            $vpro_order_sub->course_id = $item->course_id;
            $vpro_order_sub->course_price = $item->course_price;
            if(!$vpro_order_sub->insert())return false;
        }
        return true;
    }

    /**
     * 写入订单状态
     * @param $orderInfo
     * @param $order_id
     * @param $operation
     * @return bool
     */
    //关键字：消息队列
    function putOrderLogs($orderInfo, $order_id, $operation){
        $vproOrderLogs = new VproOrderLogs();
        $vproOrderLogs->log_operation=$operation;
        $vproOrderLogs->log_time=time();
        $vproOrderLogs->user_id=$orderInfo['user_id'];
        $vproOrderLogs->order_id = $order_id;
        return $vproOrderLogs->insert();
    }
    /**
     * @param $coupon_id
     * @param $user_id
     * @param $order_id
     * @param $operation
     * @return bool
     */
    //这种log应该使用消息队列 RPOPLPUSH, rabbitMQ
    function putCouponLogs($coupon_id, $user_id, $order_id, $operation){
        $vpro_coupon_logs = ModelFactory::loadModel('vpro_coupon_logs');
        //1:add 2:use 3:del 4:timeout 对应log_operation
        $vpro_coupon_logs->log_operation=$operation;
        $vpro_coupon_logs->log_coupon_id = $coupon_id;
        $vpro_coupon_logs->log_user_id = $user_id;
        $vpro_coupon_logs->log_order_id = $order_id;
        return $vpro_coupon_logs->insert();
    }
    function changeCouponStatus($coupon_id, $order_id, $user_id){
        try{
            $user_coupon_isexisted = $this->redis->get('coupon_isexisted_'.$user_id);
            if($user_coupon_isexisted==null)throw new Exception("user coupons existing string lost, retry puting order again",[],28);
            if($this->redis->setbit('coupon_isexisted_'.$user_id, 0))throw new Exception("ALERT!User Coupon has already been used!");
            $res=['log_operation'=>2,'log_coupon_id'=>$coupon_id,'log_user_id'=>$user_id, 'log_order_id'=>$order_id];
            $this->redis->lpush('vpro_coupon_logs',json_encode($res));
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
            $redis_str= $this->redis->hget('VproCourses', $v);
            if($redis_str==null || $redis_str==""){
                $res = VproCourses::_getDetail($v);
                if($res){
//                    $this->redis->hset("VproCourses", $v, json_encode($res));
                    array_push($check_res, $res);
                }else{
                    //这里后台需要准备一个程序，定时运行检查hash中的空字符串，找到就删除字段
                    if($redis_str==null)$this->redis->hset("VproCourses", $v, "");
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
     * 返回格式：["difference"=>[xxx,xxx,xxx], "consistency"=>true|false]
     */
    function checkCoursesConsistency($course_ids, $courses){
        $res=[];
        foreach($courses as $item){
            if(!in_array($item->course_id, $course_ids)){
                array_push($res['difference'],$item->course_id);
            }
        }
        if(count($res)>0){
            $res['consistency']=false;
            return $res;
        }
        $res['consistency']=true;
        return $res;
    }
    function delCartDetail($detail){
        $vproCartDetail = ModelFactory::loadModel('vpro_cart_detail');
        $transaction = $vproCartDetail::getDb()->beginTransaction();
        try{
            if($detail){
                if(count($detail["cart_detail"])>0){
                    foreach($detail["cart_detail"] as $v){
                        if($record = $vproCartDetail::findOne(['cart_course_id'=>$v['cart_course_id'], 'cart_parent_id'=>$v['cart_parent_id']])){
                            $cart_name = $detail['is_login']?"cart".$detail['cart_userid']:'cookiecart'.$detail['cart_id'];
                            $res=$record->delete();
                            if($res){
                                $res = $this->redis->smembers($cart_name);
                                foreach($res as $key =>$value){
                                    if(json_decode($value)->cart_course_id==$v['cart_course_id']){
                                        $this->redis->srem($cart_name, $value);
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
                }
            }
        }catch(Exception $e){

        }
    }
    function addCartDetail($detail, $payment=0){
        
        $vproCartDetail = ModelFactory::loadModel('vpro_cart_detail');
        $transaction = $vproCartDetail::getDb()->beginTransaction();
        try{
            if($detail){
                if(count($detail['cart_detail'])>0){
                    foreach($detail['cart_detail'] as $v){
                        if(!$vproCartDetail::findOne(["cart_course_id"=>$v['cart_course_id'], "cart_parent_id"=>$detail['cart_id']])){
                            $vproCartDetail->cart_parent_id = $detail['cart_id'];
                            $vproCartDetail->cart_course_id = $v['cart_course_id'];
                            $vproCartDetail->cart_add_time  = time();
                            if(isset($v['cart_is_cookie']))$vproCartDetail->$v["cart_is_cookie"];
                            $vproCartDetail->save();
                            $detailInfo=$this->getDetailInfo($v);
                            if(key_exists("cart_userid",$detail)){
                                $this->redis->sadd("cart".$detail["cart_userid"],json_encode($detailInfo));
                            }else{
                                $this->redis->sadd("cookiecart".$detail["cart_id"],json_encode($detailInfo));
                            }
                            $payment = $payment+$detailInfo['cart_course_price'];
                        }
                    }
                    $transaction->commit();
                }else{
                    $transaction->rollBack();
                }
            }
            return $payment;
        }catch(Exception $e){
            var_export($e);
        }
    }
    function getDetailInfo($cart_detail){
        $query = <<<QUERY
SELECT
	c.course_title,
	c.course_price,
	cc.course_cover_address
FROM
	vpro_courses AS c
LEFT JOIN vpro_courses_cover AS cc ON c.course_id = cc.course_cover_id
WHERE
	c.course_id = :course_id
QUERY;
        $info = \Yii::$app->db->createCommand($query)->bindValue(':course_id', $cart_detail["cart_course_id"])->queryOne();
        if($info){
            $cart_detail['cart_course_title']=$info["course_title"];
            $cart_detail['cart_course_price']=$info["course_price"];
            $cart_detail['cart_course_cover_address']=$info["course_cover_address"];
            return $cart_detail;
        }
        return false;
    }


    function actionGetcoupon(){
        $request=\Yii::$app->request;
        $user_info = $request->bodyParams;
        if(!array_key_exists('user_id',$user_info))return json_encode([]);
        if($this->checkUserCouponExisted($this->redis, $user_info["user_id"])) {
            //得到该用户所有可使用的优惠券
            $coupon_valid = $this->_getvalidCoupons($this->redis, $user_info);
        }
        return json_encode($coupon_valid);
    }

    /**
     * 检查用户优惠券认领条目是否存在，不存在就插入一条新的
     * @param $this->redis
     * @param $user_info
     * @return boolean
     */
    function checkUserCouponExisted($user_id){
        if(!($this->redis->exists('coupon_'.$user_id)&&$this->redis->exists('coupon_isexisted_'.$user_id))){
            $vpro_user_coupon=ModelFactory::loadModel('vpro_user_coupon');
            $user_coupons=$vpro_user_coupon->findOne(['user_coupon_auth_id'=>$user_id]);
            if($user_coupons){
                $this->redis->set('coupon_'.$user_id, $this->_convertDecStr2AsciiStr($vpro_user_coupon->user_coupon_bit));
                $this->redis->set('coupon_isexisted_'.$user_id, $this->_convertDecStr2AsciiStr($vpro_user_coupon->user_coupon_isexisted_bit));
            }else{
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
        $this->redis->bitop('AND', 'coupon_valid_'.$user_info['user_id'], 'coupon_'.$user_info['user_id'], 'coupon_isexisted_'.$user_info['user_id']);
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
     * 对应每个用户，如果这个用户领了这张优惠券，那么在bitmap里设置优惠券id这一位为1
     * 比如券id为1，那就setbit coupon_1的1号位设置为1。
     * 但是setbit是从左往右设置，而且从0开始，所以最后的bit结果是"01000......", 而转换字节的话是8个bit为一个byte，
     * 设置setbit返回值是该位置上上一次的值
     *
     * 所以是0x"0100 0000"=>dec"64"
     *
     * 存入数据库的办法：以字节的方式存储，8个bit变成一个byte。
     * 比如id为1和id为21的优惠券有效，最后存入的结果就是：64，0， 8
     *
     * select 认领bit from 用户优惠券认领表 where 用户id = '1'
     */
    function actionTestcoupon(){
        $request=\Yii::$app->request;
        //包含coupon_id, auth_id
        $coupon_info = $request->bodyParams;
        $vpro_auth=ModelFactory::loadModel("vpro_auth");
        $user_ids=$vpro_auth::find()->select('auth_id')->orderBy('auth_id')->asArray()->all();
        
        var_export($this->_getCouponIds($this->_convertAsciiStr2DecStr($this->redis->get('coupon_1'))));
        exit();
        foreach($user_ids as $v){
            $this->redis->setbit('coupon_'.$v['auth_id'], 1, 1);
            $this->redis->setbit('coupon_isexisted_'.$v['auth_id'], 1, 1);
            $vpro_user_coupon=ModelFactory::loadModel('vpro_user_coupon');
            if(!$vpro_user_coupon::findOne(['user_coupon_auth_id'=>$v['auth_id']])){
                $vpro_user_coupon->user_coupon_auth_id=$v['auth_id'];
                $vpro_user_coupon->user_coupon_bit=$this->_convertAsciiStr2DecStr($this->redis->get('coupon_'.$v['auth_id']));
                $vpro_user_coupon->user_coupon_isexisted_bit=$this->_convertAsciiStr2DecStr($this->redis->get('coupon_isexisted_'.$v['auth_id']));
                $vpro_user_coupon->insert();
            }
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
    function actionTtt(){
//        var_export(\Yii::$app->runAction("api/pay/put-web-pay",['orderInfo'=>1]));
//        var_export(\Yii::$app->runAction("api/order/test",['info'=>1]));
//        var_export(\Yii::$app->helper->saveByErrorHandler('123'));
        var_export($this->getPrimaryKey());
    }

}