<?php
namespace common;
use Redis;
use Yii;

class RedisInstance {
    private static $_instance = null;
    private function __construct() {}
    static public function getRedis() {
        if(!self::$_instance instanceof self)
        {
            self::$_instance = new self;
        }
        $temp = self::$_instance;
        return $temp->connect();
    }
    static private function connect() {
        try{
            $redis = new Redis();
            $redis->connect('192.168.1.160', 7000);
            return $redis;
        } catch (\RedisException $e) {
            return false;
        }
    }
    private function __clone()
    {
        // TODO: Implement __clone() method.
    }
}