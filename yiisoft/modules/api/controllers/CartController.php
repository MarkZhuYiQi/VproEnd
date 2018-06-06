<?php
namespace api\controllers;
use app\models\ModelFactory;
use app\models\VproCourses;
use Exception;

/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/13/2018
 * Time: 10:33 AM
 */
class CartController extends ShoppingBaseController {
    function actionTest(){
        $res=$this->getDetailInfo(['cart_course_id'=>1007]);
        var_export($res);
    }
    function actionAddcartdetail()
    {
        $request = \Yii::$app->request;
        $detail = $request->bodyParams;
        $this->addCartDetail($detail);
    }
    function actionDelcartdetail(){
        $request = \Yii::$app->request;
        $detail=$request->bodyParams;
        if(count($detail)==0)return json_encode([]);
        return $this->delCartDetail($detail);
    }
    function addCartDetail($detail, $payment=0)
    {

        $vproCartDetail = ModelFactory::loadModel('vpro_cart_detail');
        $transaction = $vproCartDetail::getDb()->beginTransaction();
        try {
            if ($detail) {
                if (count($detail['cart_detail']) > 0) {
                    foreach ($detail['cart_detail'] as $v) {
                        if (!$vproCartDetail::findOne(["cart_course_id" => $v['cart_course_id'], "cart_parent_id" => $detail['cart_id']])) {
                            $vproCartDetail->cart_parent_id = $detail['cart_id'];
                            $vproCartDetail->cart_course_id = $v['cart_course_id'];
                            $vproCartDetail->cart_add_time = time();
                            if (isset($v['cart_is_cookie'])) $vproCartDetail->$v["cart_is_cookie"];
                            $vproCartDetail->save();
                            $detailInfo = $this->getDetailInfo($v);
                            if (key_exists("cart_userid", $detail)) {
                                $this->redis->sAdd("cart" . $detail["cart_userid"], json_encode($detailInfo));
                            } else {
                                $this->redis->sAdd("cookiecart" . $detail["cart_id"], json_encode($detailInfo));
                            }
                            $payment = $payment + $detailInfo['cart_course_price'];
                        }
                    }
                    $transaction->commit();
                } else {
                    $transaction->rollBack();
                }
            }
            return $payment;
        } catch (Exception $e) {
            var_export($e);
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

    /**
     * 如果已经登录了，那么会获得user与购物车id对应的id，没有登录会得到cookieid，如果啥都没有就返回空
     * @return string
     */
    function actionUsercart()
    {
        $body = \Yii::$app->request->bodyParams;
        if (isset($body['cart_cookieid'])) {
            $id = "cookiecart" . $body['cart_cookieid'];
        } elseif (isset($body['cart_userid'])) {
            $id = "cart" . $body['cart_userid'];
        }
        $cartInfo = [];
        if ($this->redis->keys($id)) {
            foreach($this->redis->sMembers($id) as $value) {
                array_push($cartInfo, json_decode($value));
            }
            return json_encode($this->returnInfo($cartInfo));
        }
        return json_encode($this->returnInfo([]));
    }


    function actionAddcart()
    {
        $request = \Yii::$app->request;
        $cart_ref = $request->bodyParams;
        if (count($cart_ref) == 0) return json_encode([]);
        $vproCart = ModelFactory::loadModel('vpro_cart');
        $cart = $vproCart::findOne(['cart_id' => $cart_ref['cart_id']]);
        if ($cart) {
//            $vproCart->cart_payment = $vproCart->cart_payment;

            $payment = $this->addCartdetail($cart_ref, $cart->cart_payment);
            return $cart->save();

        } else {
            $payment = $this->addCartDetail($cart_ref);
            $vproCart = ModelFactory::loadModel('vpro_cart');
            $vproCart->cart_id = $cart_ref['cart_id'];
            $vproCart->cart_userid = $cart_ref['cart_userid'];
            $vproCart->cart_payment = $payment;
            $vproCart->cart_status = 1;
            $vproCart->cart_addtime = time();
            return $vproCart->save();
        }
    }

}