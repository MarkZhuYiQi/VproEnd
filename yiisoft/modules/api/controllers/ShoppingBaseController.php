<?php
namespace api\controllers;

use app\common\JwtAuth;
use app\common\LogHandler;
use app\controllers\CombaseController;
use app\controllers\SnowflakeController;
use common\RedisInstance;

/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/12/2018
 * Time: 3:42 PM
 */

class ShoppingBaseController extends CombaseController{
    protected $redis;
    protected $db;
    protected $params;
    protected $logHandler;
    function init()
    {
        parent::init();
        $this->redis = RedisInstance::getRedis() ? $this->redis : $this->returnInfo('database connect error!', 'REDIS_CONNECT_ERROR');
        $this->db = \Yii::$app->db;
        $this->params = \Yii::$app->params;
        $this->logHandler = LogHandler::getLogHandler();
    }
    /**
     * 通过twitter雪花算法获得订单编号
     * @return int
     */
    protected function getOrderId() {
        return (new SnowflakeController())->getOrderId();
    }

    /**
     * 通过订单编号获得订单信息
     * @param int $primaryKey
     * @return mixed
     */
    protected function getOrderByPrimaryKey(int $primaryKey) {
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
    protected function getOrderPrimaryKey() {
        return 'order_id';
    }

}