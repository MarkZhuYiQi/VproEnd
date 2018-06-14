<?php
namespace app\common;
use common\RedisInstance;

class Common{
    public static function ip(){
        switch (true) {
            case isset($_SERVER["HTTP_X_FORWARDED_FOR"]):
                $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
                break;
            case isset($_SERVER["HTTP_CLIENT_IP"]):
                $ip = $_SERVER["HTTP_CLIENT_IP"];
                break;
            default:
                $ip = $_SERVER["REMOTE_ADDR"] ? $_SERVER["REMOTE_ADDR"] : '127.0.0.1';
        }
        if (strpos($ip, ', ') > 0) {
            $ips = explode(', ', $ip);
            $ip = $ips[0];
        }
        return $ip;
    }
    public static function checkCourseString($str) {
        $pattern = '/\s+/';
        if (preg_match($pattern, $str, $matches)) {
            return true;
        }
        return false;
    }
    public static function genCourseId() {
        $date = time();
        $date_arr = str_split($date, 1);
        $res = $date_arr[0] . $date_arr[3] . $date_arr[5] . $date_arr[7] . $date_arr[9] . rand(10, 99) . $date_arr[8] . rand(10, 99);
        return $res;
    }
    public static function decrypt($data)
    {
        $params = \Yii::$app->params;
        $pi_key = openssl_pkey_get_private($params['private_key']);//这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id
        $pu_key = openssl_pkey_get_public($params['public_key']);//这个函数可用来判断公钥是否是可用的
        $decrypt = '';
        openssl_private_decrypt(base64_decode($data), $decrypt, $pi_key);//私钥解密
        return $decrypt;
    }
    public static function forbiddenAccess() {
        return !!(RedisInstance::getRedis()->get('p' . self::ip()) > \Yii::$app->params['MISS_THRESHOLD']);
    }
}
