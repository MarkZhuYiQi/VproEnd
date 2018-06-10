<?php
namespace api\controllers;
use api\common\CartApi;
use app\models\ModelFactory;
use app\models\VproCart;

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
    function actionTest() {
        var_export(VproCart::find()->select(['cart_id'])->where(['cart_userid' => 33])->asArray()->all());
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
     *
     * 登陆状态：
     *      去redis的usercart表寻找对应用户id的购物车id，如果返回false说明还未设置，去数据库查找，如果没找到则设置-1，否则设置查找结果；
     *      然后将该cart_id: [-1|xxxxxx]返回前台，前台根据该结果决定是否要创建新的cart_id
     *      根据cart_id，如果cart_id有值，就去寻找购物车信息，塞入结果中
     * 未登录状态：
     *
     * @return string
     */
    function actionUsercart()
    {
        $body = $this->request->bodyParams;
        $cartId = -1;
        if (isset($body['cart_cookieid'])) {
            // cookiecart[]
            $id = "cookiecart" . $body['cart_cookieid'];
        } elseif (isset($body['cart_userid'])) {
            // 在登陆状态下，去redis usercart表下找userid对应的购物车id，如果返回false说明没有，需要去数据库里找一找，找不到，需要生成；如果有，取出来
            // cart[]
            $id = "cart" . $body['cart_userid'];
            // 设置redis hash表中的cart_id
            // usercart:[user_id]->[cart_id]
            if(($cartId = $this->redis->hGet('usercart', $body['cart_userid'])) === false)
            {
                $vproCart = new VproCart();
                $res = $vproCart::find()->select(['cart_id'])->where(['cart_userid' => $body['cart_userid']])->asArray()->one();
                if(count($res) === 0) {
                    $cartId = -1;
                } else {
                    $cartId = $res['cart_id'];
                }
                $this->redis->hSet('usercart', $body['cart_userid'], $cartId);
            }
        } else {
            // 传输错误
            return json_encode($this->returnInfo('params missing', $this->params['PARAMS_ERROR']));
        }
        $cartInfo = [];
        // 如果有条目，放到购物车信息中
        if ($this->redis->keys($id)) {
            foreach($this->redis->sMembers($id) as $value) {
                array_push($cartInfo, json_decode($value));
            }
        }
        return json_encode($this->returnInfo([
            'cartInfo'  =>  $cartInfo,
            'cartId'    =>  $cartId
        ]));
    }

    /**
     * 添加信息到购物车
     * @return bool|string
     */
    function actionAddcart()
    {
        $request = $this->request;
        $cart_ref = $request->bodyParams;
        if (count($cart_ref['cart_detail']) === 0) return json_encode($this->returnInfo('unknown products!', 'CART_ADD_PRODUCT_ERROR'));
        if ($cart_ref['cart_is_existed'] === 0) {
            $res = $this->createUserCart($cart_ref);
            if(!$res)return json_encode($this->returnInfo('create cart info error', $this->params['CART_CREATE_ERROR']));
        }
        $payment = $this->cartApi->addCartdetail($cart_ref);
        return $payment ? json_encode($this->returnInfo(true)) : json_encode($this->returnInfo('save cart error', $this->params['CART_ITEM_SAVE_ERROR']));
    }

    function createUserCart($cart_ref) {
        $vproCart = new VproCart();
        $vproCart->cart_id = $cart_ref['cart_id'];
        $vproCart->cart_userid = $cart_ref['cart_userid'];
        $vproCart->cart_status = 1;
        $vproCart->cart_addtime = time();
        $this->redis->hSet('usercart', $cart_ref['cart_userid'], $cart_ref['cart_id']);
        return $vproCart->save();

    }
}