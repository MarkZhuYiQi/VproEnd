<?php
namespace app\controllers;
use Redis;

/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 5/17/2018
 * Time: 11:23 AM
 */
class RedisController{
    public static function connect() {
        try{
            $redis = new Redis();
            $redis->connect('192.168.1.160', 7000);
            return $redis;
        } catch (\RedisException $e) {
            return false;
        }
    }
}