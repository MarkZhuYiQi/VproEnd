<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/7/2018
 * Time: 11:09 AM
 */

namespace api\controllers;

use api\common\CartApi;
use api\common\CouponApi;
use api\common\CourseApi;
use app\controllers\SnowflakeController;
use app\models\VproOrder;
use app\models\VproOrderSub;
use Exception;

class OrderController extends ShoppingBaseController {
//    public $modelClass='app\models\VproOrder';
    const PAGINATION_LIMIT=10;
    private $courseApi;
    private $couponApi;
    private $cartApi;

    /**
     * errcode:
     * 72021: 课程和前台传来的不一致
     * 72022: 优惠券认领条目不存在，插入新的失败
     * 72023: 优惠券不存在或者已被使用
     * 72024: 优惠券使用日期不符
     * 72025: 总价格未达到优惠券限额
     * 72026: 总价格小于0
     * 72027: 课程总价和前台不相同
     */



    function init()
    {
        parent::init(); // TODO: Change the autogenerated stub
        $this->courseApi = new CourseApi();
        $this->couponApi = new CouponApi();
        $this->cartApi = new CartApi();
    }

    function actions()
    {
        $actions = parent::actions(); // TODO: Change the autogenerated stub
        unset($actions['index'], $actions['create']);
        return $actions;
    }
    function actionGetOrder(){
        $request = \Yii::$app->request;
        $user_id = $request->get('user_id',false);
        $p = $request->get('p',1);
        $offset = ($p - 1) * self::PAGINATION_LIMIT;
        $orders_sql = <<<q
SELECT
	o.order_id,
	o.order_price,
	o.order_time,
	o.user_id,
	o.order_coupon_used,
	o.order_discount,
	o.order_payment,
	o.order_title,
	sub.course_id,
	sub.course_price,
	c.course_title,
	c.course_author,
	c.course_price,
	cover.course_cover_address,
	auth.auth_appid AS course_author
FROM
	vpro_order AS o
LEFT JOIN vpro_order_sub AS sub ON sub.order_id = o.order_id
LEFT JOIN vpro_courses as c ON c.course_id = sub.course_id 
LEFT JOIN vpro_courses_cover AS cover ON cover.course_cover_id = sub.course_id
LEFT JOIN vpro_auth AS auth on auth.auth_id = c.course_author
WHERE
    o.order_id <= 
	(
		SELECT
			order_id
		FROM
			vpro_order
		WHERE
			user_id = $user_id
		ORDER BY
			order_id
        DESC
		LIMIT 1 OFFSET $offset
	)
ORDER BY
	o.order_id
DESC
LIMIT :limit;
q;
//        echo $orders_sql;
//        exit();
        $db = \Yii::$app->db;
        $orders = $db->createCommand($orders_sql)->bindValues(['limit'=>self::PAGINATION_LIMIT])->queryAll();
        $orders = $this->genOrderHistory($orders);
        $orders_count = $this->genOrderHistoryPagination($user_id);
        $res = [
            'orders'=>$orders,
            'page_count'=>$orders_count,
            'current_page'=>$p
        ];
//        var_export($orders);
        return json_encode($res);
    }

    /**
     * @param $origin_data
     * @return array example:
     * ORDER_ID:
     */
    function genOrderHistory($origin_data){
        $res = [];
        foreach($origin_data as $data) {
            if (!is_array($res[$data['order_id']])) $res[$data['order_id']] = [];
            $res[$data['order_id']] = [
                'order_price' => $data['order_price'],
                'order_time' => $data['order_time'],
                'user_id' => $data['user_id'],
                'order_coupon_used' => $data['order_coupon_used'],
                'order_discount' => $data['order_discount'],
                'order_title' => $data['order_title'],
            ];
            if (!is_array($res[$data['order_id']]['sub_order'])) $res[$data['order_id']]['sub_order'] = [];
            array_push($res[$data['order_id']]['sub_order'],
                [
                    'course_id' => $data['course_id'],
                    'course_price' => $data['course_price'],
                    'course_title' => $data['course_title'],
                    'course_author' => $data['course_author'],
                    'course_cover_address' => $data['course_cover_address']
                ]
            );
        }
        return $res;
    }
    function genOrderHistoryPagination($user_id){
        $orders = new VproOrder();
        return $count = $orders::find()->where(['user_id'=>$user_id])->count();
    }
    /**
     * 下单
     * @return array|string
     */
    function actionPutOrder(){
        $orderInfo=$this->checkParams(['cart_parent_id', 'course_price', 'order_coupon_discount', 'order_coupon_selected', 'order_course_ids', 'user_id'], 'post');
        if (!$orderInfo) return json_encode($this->returnInfo('params missing', 'PARAMS_ERROR'));
        try {
            //拿到所有购买课程的内容---------------------------------------------------------------------------------------
            $courses = $this->courseApi->checkCourses($orderInfo['order_course_ids']);
            //检查拿出来的课程是否和传递来的课程id一致
            $consistency = $this->courseApi->checkCoursesConsistency($orderInfo['order_course_ids'], $courses);
            //检查课程一致性
            if(!$consistency['consistency'])
                throw new Exception('courses did not matched with courses sent from front-side.', $this->params['COURSE_FRONT_END_MISMATCH']);

            // 初始化订单信息
            $res = [];
            $res['course_price'] = 0;

            // 检查价格一致性---------------------------------------------------------------------------------------------
            //获得总价
            foreach($courses as $c) {
                $res['course_price'] += $c->course_price;
            }
            if($orderInfo['course_price'] != $res['course_price'])throw new Exception('order price did not match with the price transferred from front side.', $this->params['ORDER_PRICE_MISMATCH_FRONT_END']);
            // 判断优惠券，据此判断最终价格--------------------------------------------------------------------------------
            if($orderInfo['order_coupon_selected'] > 0) {
                /*
                 * 使用了优惠券的情况
                 * 课程一致性通过， 接下来就是判断优惠券
                 */
                // 判断用户认领优惠券条目是否存在，没有就创建，如果还是失败，报错
                if (!$this->couponApi->checkUserCouponExisted($orderInfo["user_id"]))
                    throw new Exception('the coupon used by user is not existed', $this->params['COUPON_ENTRY_NOTEXIST_CREATD_FAILURE']);

                //得到该用户指定使用的优惠券
                $coupon_valid = $this->couponApi->_getvalidCoupons($orderInfo["user_id"], $orderInfo['order_coupon_selected'])[0];

                //判断优惠券是否可用
                if (!count($coupon_valid))
                    throw new Exception('', $this->params['COUPON_NOTEXIST_USED']);

                //判断优惠券是否在可使用时间段
                if ($coupon_valid['coupon_start_date'] > time() || $coupon_valid['coupon_end_date'] < time())
                    throw new Exception('the coupon does not match with date which could be used', $this->params['COUPON_DATE_MISMATCH']);

                //判断总价是否达到优惠券要求
                if ($coupon_valid['coupon_limit'] > $res['order_price'])
                    throw new Exception('total price does not reach the limit', $this->params['COUPON_LIMIT_MISMATCH']);

                // 设置订单是否使用优惠券
                $res['coupon_used'] = $coupon_valid;
                // 订单获得的优惠
                $res['price_discount'] = $coupon_valid['coupon_discount'];
                // 订单最终价格
                $res['summary_price'] = $res['course_price'] - $res['price_discount'];
            }
            // 订单用户信息
            $res['user_id'] = $orderInfo['user_id'];
            // 没有优惠时的订单总价
            $res['summary_price'] = $res['course_price'];
            // 所有课程信息
            $res['courses'] = $courses;
            //判断总价，总价低于0就不能购买了。
            if ($res['summary_price'] < 0)
                throw new Exception('total price lower than 0', $this->params['ORDER_PRICE_MINUS']);
            $payment = $this->putOrder($res);

            if($res["coupon_used"])$this->couponApi->changeCouponStatus($coupon_valid['coupon_id'], $payment['order_id'], $orderInfo['user_id']);
            $cart_del_info = [];
            $cart_del_info['cart_detail'] = [];
            foreach($orderInfo['order_course_ids'] as $v){
                array_push($cart_del_info['cart_detail'], ['cart_course_id'=>$v, 'cart_parent_id'=>$orderInfo['cart_parent_id']]);
            }
            $cart_del_info['is_login'] = true;
            $cart_del_info['cart_userid']=$res['user_id'];
            $this->cartApi->delCartDetail($cart_del_info);
            return json_encode($this->returnInfo($payment));
        }catch(\Exception $e){
            /**
             * errcode:
             * 72021: 课程和前台传来的不一致
             * 72022: 优惠券认领条目不存在，插入新的失败
             * 72023: 优惠券不存在或者已被使用
             * 72024: 优惠券使用日期不符
             * 72025: 总价格未达到优惠券限额
             * 72026: 总价格小于0
             * 72027: 课程总价和前台不相同
             * 72028: 子订单创建失败
             */
            $msg=$e->getMessage();
            $code=$e->getCode();
            var_export($e->getMessage());
            var_export($e->getLine());
            var_export($e->getFile());
            var_export($e->getTraceAsString());
            $this->logHandler::saveLog($this->params['CREATE_ORDER'], time(), $msg, $orderInfo['user_id']);
            return json_encode($this->returnInfo($msg, $code));
        }

    }

    /**
     * 下单！
     * @param $orderInfo
     * @return string
     */
    function putOrder($orderInfo) {
        if(count($orderInfo) > 0) {
            $vproOrder = new VproOrder();
            $transaction = VproOrder::getDb()->beginTransaction();
            try{
                // 将订单中的优惠券信息放入订单信息表
                if(isset($orderInfo['coupon_used'])) {
                    $vproOrder->order_coupon_used = $orderInfo['coupon_used']['coupon_id'];
                    $vproOrder->order_discount = $orderInfo['coupon_used']['coupon_discount'];
                }
                // 将所购买的课程的标题提取出来
                $subject = array_map(function($value) {
                    return $value->course_title;
                }, $orderInfo['courses']);
                $subject = substr(preg_replace("/\s/","",implode("", $subject)),0,128);
//                $order_id = IdController::getOrderId($this->redis);
                // twitter雪花算法获得订单号
                $order_id = (new SnowflakeController())->getOrderId($workId=1);
                // 记录订单信息
                $vproOrder->order_id = $order_id;
                $vproOrder->order_price = $orderInfo['summary_price'];
                $vproOrder->order_time = time();
                $vproOrder->user_id = $orderInfo['user_id'];
                $vproOrder->order_title = $subject;

                // 生成付款链接 ['pay_url' => xxx]
                $payData = $this->genPayUrl($order_id, $subject, $orderInfo['summary_price'], 1800, $order_id);
                foreach($orderInfo['courses'] as $value)
                {
                    $value->order_id = $order_id;
                }
                // 将课程购买信息放入子订单列表中
                if(!$this->putSubOrder($orderInfo['courses'])) throw new Exception('sub order error!', $this->params['SUB_ORDER_INSERT_ERROR']);
                // 订单日志
                $this->logHandler::saveLog($this->params['CREATE_ORDER'], time(), null, $orderInfo['user_id'], $order_id);
                $vproOrder->insert();
                $transaction->commit();
                return $payData;

            }catch(\Exception $e){
                $transaction->rollBack();
                var_export($e->getMessage());
                $this->logHandler::saveLog($this->params['CREATE_ORDER'], time(), $e->getMessage(), $orderInfo['user_id']);
            }
        }
    }

    /**
     * @param $order_id         string 订单编号
     * @param $order_subject    string 订单标题信息
     * @param $price            string 价格
     * @param $timeout_express  string 过期时间
     * @param $return_param     string 返回字符串
     * @param int $goods_type   string 商品类型
     * @return array
     */
    function genPayUrl($order_id, $order_subject, $price, $timeout_express, $return_param, $goods_type=1){
        $payData = [
            'body'              =>  (string)$order_subject,
            'subject'           =>  (string)$order_subject,
            'order_no'          =>  (string)$order_id,
            'amount'            =>  (string)$price,
            'timeout_express'   =>  (string)(time() + intval($timeout_express)),
            'return_param'      =>  (string)$return_param,
            'goods_type'        =>  (string)$goods_type,
        ];
        $payData['pay_url'] = \Yii::$app->runAction("api/pay/put-web-pay",['orderInfo'=>json_encode($payData)]);

        return $payData;
    }

    /**
     * 写入子订单信息
     * @param $courses
     * @param $order_id
     * @return bool
     */
    function putSubOrder($courses) {
        foreach($courses as $v)
        {
            $vpro_order_sub = new VproOrderSub();
            $vpro_order_sub->setScenario('add');
            $vpro_order_sub->attributes = (array)$v;
            if (!$vpro_order_sub->save())return false;
        }
        return true;
    }
}