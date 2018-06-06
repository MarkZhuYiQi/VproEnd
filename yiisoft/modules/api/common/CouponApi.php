<?php
namespace api\common;
use app\models\ModelFactory;
use common\RedisInstance;
use Exception;

/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 6/6/2018
 * Time: 5:59 PM
 */
class CouponApi {
    private $redis;
    public function __construct()
    {
        $this->redis = RedisInstance::getRedis();
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
    function checkUserCouponExisted($user_id) {
        if(!$this->redis->exists('coupon_'.$user_id) || !$this->redis->exists('coupon_isexisted_'.$user_id)) {
            $vpro_user_coupon = ModelFactory::loadModel('vpro_user_coupon');
            $user_coupons = $vpro_user_coupon->findOne(['user_coupon_auth_id' => $user_id]);
            if($user_coupons) {
                $this->redis->multi()
                    ->set('coupon_'.$user_id, $this->_convertDecStr2AsciiStr($user_coupons->user_coupon_bit))
                    ->set('coupon_isexisted_'.$user_id, $this->_convertDecStr2AsciiStr($user_coupons->user_coupon_isexisted_bit))
                    ->exec();
            } else {
                $vpro_user_coupon->user_coupon_auth_id = $user_id;
                $new_user_coupon_bit = $this->_newUserCouponBit();
                $vpro_user_coupon->user_coupon_bit = $new_user_coupon_bit;
                $vpro_user_coupon->user_coupon_isexisted_bit = $new_user_coupon_bit;
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
            $user_coupon_isexisted = $this->redis->get('coupon_isexisted_' . $user_id);
            if($user_coupon_isexisted === null)throw new Exception("user coupons existing string lost, retry puting order again",[],28);
            if($this->redis->setBit('coupon_isexisted_' . $user_id, 0))throw new Exception("ALERT! User Coupon has already been used!");
            $res = ['log_operation'=>2,'log_coupon_id'=>$coupon_id,'log_user_id'=>$user_id, 'log_order_id'=>$order_id];
            $this->redis->lPush('vpro_coupon_logs',json_encode($res));
        }catch(Exception $e){
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
        return '64';
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
        if($coupon_id!==false) {
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
}