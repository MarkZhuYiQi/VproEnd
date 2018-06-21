<?php
namespace app\common;
use common\RedisInstance;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\ValidationData;

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
    public static function encrypt($data)
    {
        $params = \Yii::$app->params;
        $pi_key = openssl_pkey_get_private($params['private_key']);//这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id
        $pu_key = openssl_pkey_get_public($params['public_key']);//这个函数可用来判断公钥是否是可用的
        $encrypt = '';
        openssl_public_encrypt($data, $encrypt, $pu_key);//私钥解密
        return base64_encode($encrypt);
    }
    public static function forbiddenAccess() {
        return !!(RedisInstance::getRedis()->get('p' . self::ip()) > \Yii::$app->params['MISS_THRESHOLD']);
    }
    public static function failScoring($score) {
        RedisInstance::getRedis()->incrBy('p' . self::ip(), $score);
        RedisInstance::getRedis()->expire('p' . self::ip(), \Yii::$app->params['SCORE_EXPIRE']);
    }
    public static function verifyToken($token)
    {
        $signer=new Sha256();
        $key=\Yii::$app->params['securityKey'];
        $jwt = (new Parser())->parse((string)$token);
        // 判断token是否有效，false是jwt无效验证，需要返回错误
        if($jwt){
            $vd=new ValidationData();
            $vd->setAudience('zhu');
            $vd->setIssuer('mark');
            $vd->setSubject('everyone');
            $vd->setId("1111111");
            $vd->setCurrentTime(time());
            // 判断字符串是否过期并且是否有效
            if($jwt->verify($signer, $key) && $jwt->validate($vd))return true;
            return false;
        }
        return false;
    }


    public static function testToken()
    {
        $signer = new Sha256();
        $key=\Yii::$app->params['securityKey'];
        $token = (new Builder())->setIssuer("mark")      //iss, jwt签发者
            ->setAudience("zhu")       //aud 接收jwt的一方
            ->setSubject("everyone")         //sub面向的用户
            ->setExpiration(time() + 3600)        //exp过期时间
            ->setIssuedAt(time())                       //iat签发时间，以上是标准中注册的声明
            ->setId("1111111", true)    //给头部加入一个键值对
            ->set("auth_id", '1')
            ->set("auth_appid", 'mark')             //新注册一个声明
            ->sign($signer, $key)
            ->getToken();
        echo $token;
        $jwt = (new Parser())->parse((string)$token);
//        var_export($jwt);
        if($jwt){
            //
            $vd=new ValidationData();
            $vd->setAudience('zhu');
            $vd->setIssuer('mark');
            $vd->setSubject('everyone');
            $vd->setId("1111111");
            $vd->setCurrentTime(time());
            // 判断字符串是否过期并且是否有效
//            var_export($jwt->verify($signer, $key));
//            var_export($jwt->validate($vd));
            if($jwt->verify($signer, $key)&&$jwt->validate($vd)){

            }
        }
    }
}
