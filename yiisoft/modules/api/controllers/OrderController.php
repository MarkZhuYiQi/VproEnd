<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/7/2018
 * Time: 11:09 AM
 */

namespace api\controllers;

use app\controllers\BaseController;
use app\controllers\CombaseController;
use app\controllers\SnowflakeController;
use app\models\VproOrder;
use app\models\VproOrderLogs;
use app\models\VproOrderSub;
use Exception;

class OrderController extends ShoppingBaseController {
//    public $modelClass='app\models\VproOrder';
    const PAGINATION_LIMIT=10;
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
        $offset = ($p-1)*self::PAGINATION_LIMIT;
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
    function actionWebpay(){
        $request = \Yii::$app->request;
        $order_id = $request->get('order_id', false);
        $user_id = $request->get('user_id', false);
        $order_id=6367528482225786880;
        $user_id=1;
        if(!$order_id || !$user_id)return false;
        $VproOrder = new VproOrder();
        $order_instance = $VproOrder::findOne(['order_id'=>$order_id, 'user_id'=>$user_id]);
        $res = $this->genPayUrl($order_instance->order_id, $order_instance->order_title, $order_instance->order_price, 1800, $order_instance->order_id);
        var_export($res);
    }
    /**
     * 下单
     * @return array|string
     */
    function actionPutOrder(){
        $request=\Yii::$app->request;
        $orderInfo=$request->bodyParams;
        if(count($orderInfo)==0)return json_encode([]);
        try{
            //拿到所有课程的内容
            $courses = $this->checkCourses($orderInfo['order_course_ids']);
            //检查拿出来的课程是否和传递来的课程id一致
            $consistency = $this->checkCoursesConsistency($orderInfo['order_course_ids'], $courses);
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
                $payData = $this->genPayUrl($order_id, $subject, $orderInfo['summary_price'], 1800, $order_id);
                if(!$this->putSubOrder($orderInfo['courses'], $order_id))throw new Exception('sub order error!');
                if(!$this->putOrderLogs($orderInfo, $order_id, 'generate'))throw new Exception('log record error!');
//                var_export($payData);
                $vproOrder->insert();
//                exit();
                $transaction->commit();
                $payData['status']=true;
                return json_encode($payData);

            }catch(\Exception $e){
                $transaction->rollBack();
                var_export($e->getMessage());
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
            'body'=>(string)$order_subject,
            'subject'=>(string)$order_subject,
            'order_no'=>(string)$order_id,
            'amount'=>(string)$price,
            'timeout_express'=>(string)(time()+$timeout_express),
            'return_param'=>(string)$return_param,
            'goods_type'=>(string)$goods_type,
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
}