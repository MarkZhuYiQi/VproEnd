<?php
namespace app\common;
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
}
