<?php
namespace app\common;
class GlobalFunctions{
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
}
