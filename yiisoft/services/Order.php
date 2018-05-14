<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/9/2018
 * Time: 11:42 AM
 */
namespace services;


use app\controllers\SnowflakeController;
use app\models\VproCourses;

class Order extends Services{

    protected $_orderModel = '';
    protected $_orderModelName = 'app\models\VproOrder';
    //以下是订单支付状态
    //等待付款状态
    public $payment_Status_pending = 'payment_pending';
    //付款处理中
    public $payment_status_processing = 'payment_processing';
    //收款成功
    public $payment_status_confirmed = 'payment_confirmed';
    //欺诈（支付金额和网站要求金额不一致，或者货币不一致），判断欺诈
    public $payment_status_suspected_fraud = 'payment_suspected_fraud';
    //订单支付取消（用户点击支付但未付款，或者订单超时，或者订单被后台取消）
    public $payment_status_canceled = 'payment_canceled';
    //订单审核中，（订单支付成功后，需要客服审核，才能开始发货流程，或者存在问题暂时hold）
    public $status_holded = 'holded';








}