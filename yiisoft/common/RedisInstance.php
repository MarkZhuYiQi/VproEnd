<?php
namespace common;
use Redis;
use Yii;

class RedisInstance {
    private static $_instance = null;
    private function __construct() {}
    static public function getRedis() {
        if(!self::$_instance instanceof Redis)
        {
            self::$_instance = self::connect();
        }
        return self::$_instance;
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
    //超时时间存放key时的后缀
    const EXPIRED_KEY_SUFFIX = '_expired';

    /**
     * 过期时间，默认传入时间是分钟
     * @param int $min
     * @param int $max
     * @return int
     */
    static public function expired_time($min=0, $max=0){
        return time() + rand($min*3600, $max*3600);
    }

    /**
     * 判断key是否存在
     * @param $key
     * @param bool $database
     * @return bool
     */
    public static function checkRedisKey($key, $database=false){
        if(!$database){
            return self::getRedis()->exists($key);
        }else{
            return self::getRedis()->exists($database) && self::getRedis()->hExists($database, $key);
        }
    }

    /**
     * 判断key是否过期, 过期返回false，可以使用返回true
     * @param $key
     * @param bool $database
     * @return bool
     */
    public static function checkExpired($key, $database=false){
        if(!$database){
            return self::getRedis()->ttl($key.self::EXPIRED_KEY_SUFFIX) < time();
        }else{
            return self::getRedis()->hGet($database.self::EXPIRED_KEY_SUFFIX, $key) < time();
        }
    }

    /**
     * hash表设置键值对带上过期功能
     * @param $database
     * @param $key
     * @param $expire_time
     * @param $value
     */
    public static function hsetex($database, $key, $expire_time, $value){
        self::getRedis()->hSet($database, $key, $value);
        self::getRedis()->hSet($database.self::EXPIRED_KEY_SUFFIX, $key, time()+$expire_time);
    }

    /**
     * 获得带过期时间的哈希表键值对
     * @param $database
     * @param $key
     * @return bool
     */
    public static function hgetex($database, $key){
        $expired_time = self::getRedis()->hGet($database.self::EXPIRED_KEY_SUFFIX, $key);
        if(time() < $expired_time && self::checkRedisKey($key, $database)){
            return self::getRedis()->hGet($database, $key);
        }
        self::getRedis()->hSet($database, $key, '');
        return false;
    }
}