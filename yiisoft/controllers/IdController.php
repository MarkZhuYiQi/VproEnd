<?php
/**
 * Created by PhpStorm.
 * User: szl4zsy
 * Date: 1/26/2018
 * Time: 5:32 PM
 */

namespace app\controllers;

use app\models\ModelFactory;

class IdController{
    private $pattern;
    function __construct($pattern)
    {
        $this->$pattern=$pattern;
    }
    static function getLastMaxId(){
        try{
            $vproIds = ModelFactory::loadModel('vpro_ids');
            $lastId = $vproIds::findOne(['id_pattern'=>'order_id']);
            return $lastId;
        }catch(\Exception $e){

        }
    }
    static function genOrderIds(){
        $timestamp=time();
        //并发，默认为0
        $concurrence=0;
        $lastId = self::getLastMaxId();
        if($timestamp == (int)substr($lastId->id_last_id, 0, 10)){
            $concurrence=(int)substr($lastId->id_last_id, 10, 4);
        }
        $orderIds=[];
        $i=$concurrence+1;
        while($i<$concurrence+1000){
            array_push($orderIds, $timestamp.sprintf('%04s', $i));
            $i++;
        }
        $lastId->id_last_id = end($orderIds);
        if($lastId->update())self::$redis->lpush('order_id', ...$orderIds);
    }
    static function getOrderId($redis){
        $redis=\Yii::$app->get('redis');
        if(!$redis->exists('order_id') || $redis->llen('order_id') <=0){
            self::genOrderIds();
        }
        return $redis->rpop('order_id');
    }
}