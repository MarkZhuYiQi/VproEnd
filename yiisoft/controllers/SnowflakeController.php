<?php

namespace app\controllers;
use yii\db\Exception;

/**
 * Created by PhpStorm.
 * User: szl4zsy
 * Date: 1/26/2018
 * Time: 11:42 AM
 */

/**
 * twitter雪花算法：分布式唯一id取值
 * 由1位不使用的bit，41位时间戳（理论可用69年），10位机器序列号（最多1023台），12为序列代号（并发量，最大4095个）组成
 * 理论上可以在1毫秒内产生4095个不重复的序号，并发量巨大
 * Class SnowflakeController
 * @package app\controllers
 */
class SnowflakeController{
    //const 不可修改，static 可修改
    //设定一个时间戳
    const twepoch = 1516896000;
    //分布式中的机器代号，最大1023台
    const machineIdBits=10;
    //一个毫秒中并发数量，最大是12bits也就是4095个并发
    const sequenceBits=12;
    //获得一个机器代号
    protected $workId=0;
    //初始化上一个时间戳，精确到毫秒
    static $lastTimeStamp= -1;
    //初始化并发序列，（不超过4095）
    static $sequence = 0;

    static $redis=false;

    function __construct()
    {
//        self::$redis=\Yii::$app->get('redis');
        self::$redis = RedisController::connect();
        self::$lastTimeStamp=self::$redis->get('order_id:timestamp');
    }

    function getOrderId($workId=1){
//        $request = \Yii::$app->request;
//        $workId = $request->get('machineId');
//        self::$lastTimeStamp=(\Yii::$app->get('redis'))->get('')
        $this->checkMachineId($workId);
        return $this->genId();
    }

    /**
     * 检查机器序号是否小于1023
     * @param $workId int 机器序号
     * @throws Exception
     */
    function checkMachineId($workId){
        $maxWorkId = -1 ^ (-1 << self::machineIdBits);
        if($workId > $maxWorkId || $workId < 0){
            throw new Exception("workId can't be greater than ".$maxWorkId." or less than 0");
        }
        $this->workId = $workId;
    }

    /**
     * 生产id
     * 首先获得当前的毫秒级时间戳，如果当前时间比上一个时间早，那报错
     * 其次判断当前时间戳和上一个时间戳，如果一致，则给序列代号加上1，否则将序列号置0
     * 最后组合时间戳，机器代号，和序列号，组成一个id
     *
     * @return int
     * @throws Exception
     */
    function genId(){
        $timeStamp = $this->timeGen();
        $lastTimeStamp = self::$lastTimeStamp;
        if( $timeStamp < $lastTimeStamp){
            throw new Exception('clock moved backwards!refusing to generate id for %d millisecods', ($lastTimeStamp-$timeStamp));
        }
        //生成唯一序列，时间戳相同就自增序列号，时间戳不同则将序列号置0
        if ($lastTimeStamp == $timeStamp) {
            //这两行代码！非常机智，将sequence锁定在0-4095之间，4095就是12个1，保证1毫秒中有4095个并发也不会冲突
            $sequenceMask = -1 ^ (-1 << self::sequenceBits);
            self::$sequence = (self::$sequence + 1) & $sequenceMask;

            if (self::$sequence == 0) {
                $timeStamp = $this->nextMillisecond($lastTimeStamp);
            }
        } else {
            self::$sequence = 0;
        }
        self::$lastTimeStamp = $timeStamp;
        self::$redis->set('order:timestamp', $timeStamp);
        //
        //时间毫秒/数据中心ID/机器ID,要左移的位数
        $timestampLeftShift = self::sequenceBits + self::machineIdBits;
        $workerIdShift = self::sequenceBits;
        //组合3段数据返回: 时间戳.工作机器.序列
        $nextId = (($timeStamp - self::twepoch) << $timestampLeftShift) | ($this->workId << $workerIdShift) | self::$sequence;
        return $nextId;
    }
    /**
     * 得到当前时间毫秒
     * @return float
     */
    function timeGen(){
        return (float)sprintf('%.0f', microtime(true)*1000);
    }

    /**
     * 得到下一毫秒
     * @param $lastTimeStamp
     * @return float
     */
    function nextMillisecond($lastTimeStamp){
        $timeStamp = $this->timeGen();
        while($timeStamp <= $lastTimeStamp){
            $timeStamp = $this->timeGen();
        }
        return $timeStamp;
    }

}