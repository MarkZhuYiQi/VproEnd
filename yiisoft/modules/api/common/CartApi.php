<?php
/**
 * Created by PhpStorm.
 * User: Red
 * Date: 2018/6/7
 * Time: 22:20
 */
namespace api\common;

use app\common\LogHandler;
use app\models\ModelFactory;
use app\models\VproCartDetail;
use app\models\VproCourses;
use common\RedisInstance;
use Exception;

class CartApi{
    private $redis;
    public function __construct()
    {
        $this->redis = RedisInstance::getRedis();
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
        try {
            if(count($detail["cart_detail"]) <= 0) throw new \Exception("could not delete the cart products due to lack of cart_detail.", 'CART_COURSES_DELETE_LOST');
            var_export($detail);
            foreach($detail["cart_detail"] as $v){
                var_export($vproCartDetail::findOne(['cart_course_id' => $v['cart_course_id'], 'cart_parent_id' => $v['cart_parent_id']]));
                if($record = $vproCartDetail::findOne(['cart_course_id' => $v['cart_course_id'], 'cart_parent_id' => $v['cart_parent_id']])){
                    $cart_name = $detail['is_login'] ? "cart" . $detail['cart_userid'] : 'cookiecart' . $detail['cart_id'];
                    $record->delete();
                    $res = $this->redis->sMembers($cart_name);
                    foreach($res as $key =>$value){
                        if(json_decode($value)->cart_course_id === $v['cart_course_id']){
                            $this->redis->sRem($cart_name, $value);
                        }
                    }
                }
            }
            exit();
            $transaction->commit();
            return true;
        }catch(Exception $e){
            $transaction->rollBack();
            LogHandler::saveLog('DEL_CART_ITEM', time(), $e->getMessage(), $detail['cart_userid']);
            return false;
        }
    }
    function addCartDetail($detail)
    {

        $vproCartDetail = new VproCartDetail();
        $transaction = $vproCartDetail::getDb()->beginTransaction();
        try {
            foreach ($detail['cart_detail'] as $v) {
                if (!$vproCartDetail::findOne(["cart_course_id" => $v['cart_course_id'], "cart_parent_id" => $detail['cart_id']])) {
                    $vproCartDetail->cart_parent_id = $detail['cart_id'];
                    $vproCartDetail->cart_course_id = $v['cart_course_id'];
                    $vproCartDetail->cart_add_time = time();
                    if (isset($v['cart_is_cookie'])) $vproCartDetail->cart_is_cookie = $v["cart_is_cookie"];
                    $vproCartDetail->save();
                    $detailInfo = $this->getDetailInfo($v);
                    if (key_exists("cart_userid", $detail)) {
                        $this->redis->sAdd("cart" . $detail["cart_userid"], json_encode($detailInfo));
                    } else {
                        $this->redis->sAdd("cookiecart" . $detail["cart_id"], json_encode($detailInfo));
                    }
                }
            }
            $transaction->commit();
            return true;
        }catch (Exception $e) {
            $transaction->rollBack();
            return false;
        }
    }
    /**
     * 获得商品详细信息，用于购物车
     * @param $cart_detail
     * @return bool
     * $cart_detail['cart_course_title'=>xxx, 'cart_course_price'=>xxx, 'cart_course_cover_address'=>xxx]
     */
    function getDetailInfo($cart_detail){
        $vpro_courses = new VproCourses();
        $info = $vpro_courses->find()
            ->select([
                'vpro_courses.course_title',
                'vpro_courses.course_price',
                'vpro_courses_cover.course_cover_address'
            ])
            ->joinWith(['vproCoursesCover'])
            ->where(['vpro_courses.course_id'=>$cart_detail['cart_course_id']])
            ->asArray()
            ->one();
        if($info){
            $cart_detail['cart_course_title']=$info["course_title"];
            $cart_detail['cart_course_price']=$info["course_price"];
            $cart_detail['cart_course_cover_address']=$info["course_cover_address"];
            return $cart_detail;
        }
        return false;
    }
}