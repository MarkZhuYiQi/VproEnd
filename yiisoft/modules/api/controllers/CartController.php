<?php
namespace api\controllers;
use api\common\CartApi;
use app\models\ModelFactory;

/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/13/2018
 * Time: 10:33 AM
 */
class CartController extends ShoppingBaseController {
    private $cartApi;
    function init() {
        parent::init();
        $this->cartApi = new CartApi();
    }
    function actionTest(){
        $res=$this->cartApi->getDetailInfo(['cart_course_id'=>1007]);
        var_export($res);
    }
    function actionAddcartdetail()
    {
        $request = \Yii::$app->request;
        $detail = $request->bodyParams;
        $this->cartApi->addCartDetail($detail);
    }
    function actionDelcartdetail(){
        $request = \Yii::$app->request;
        $detail=$request->bodyParams;
        if(count($detail)==0)return json_encode([]);
        return $this->cartApi->delCartDetail($detail);
    }


    /**
     * 取得id下的购物车信息
     * 如果已经登录了，那么会获得user与购物车id对应的id，没有登录会得到cookieid，如果啥都没有就返回空
     * @return string
     */
    function actionUsercart()
    {
        $body = $this->request->bodyParams;
        if (isset($body['cart_cookieid'])) {
            // cookiecart[]
            $id = "cookiecart" . $body['cart_cookieid'];
        } elseif (isset($body['cart_userid'])) {
            // cart[]
            $id = "cart" . $body['cart_userid'];
        } else {
            // 传输错误
            return json_encode($this->returnInfo('params missing', $this->params['PARAMS_ERROR']));
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

    /**
     * 添加信息到购物车
     * @return bool|string
     */
    function actionAddcart()
    {
        $request = $this->request;
        $cart_ref = $request->bodyParams;
        if (count($cart_ref) == 0) return json_encode($this->returnInfo('unknown products!', 'CART_ADD_PRODUCT_ERROR'));
        $vproCart = ModelFactory::loadModel('vpro_cart');
        $cart = $vproCart::findOne(['cart_id' => $cart_ref['cart_id']]);
        // 如果购物车主信息存在
        if ($cart) {
            $payment = $this->cartApi->addCartdetail($cart_ref, $cart->cart_payment);
            return $cart->save();
        } else {
            // 购物车本来不存在
            $payment = $this->cartApi->addCartDetail($cart_ref);
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