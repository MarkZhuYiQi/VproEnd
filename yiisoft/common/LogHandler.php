<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/12/2018
 * Time: 4:45 PM
 */
namespace app\common;

use app\models\VproOrderLogs;
class LogHandler {
    const ORDER_LOGS = 'vpro_order_logs';
    private static $_logHandlerModel;
    private function __construct()
    {
    }
    private function __clone()
    {
        // TODO: Implement __clone() method.
    }
    static function getLogHandler() {
        if (!self::$_logHandlerModel instanceof self)
        {
            self::$_logHandlerModel = new self;
        }
        return self::$_logHandlerModel;
    }
    static private function instantiateLogModel() {
        return new VproOrderLogs();
    }
    static function saveLog($log_operation, $log_time, $log_message, $user_id, $order_id=null) {
        $model = self::instantiateLogModel();
        $model->log_operation = $log_operation;
        $model->log_time = $log_time;
        $model->log_message = $log_message;
        $model->user_id = $user_id;
        $model->order_id = $order_id;
        $model->insert();
    }


}