<?php
namespace app\myactions;
use app\common\Common;
use Yii;
use yii\rest\Action;
/*
 * 想法：
 * */
class AuthAction extends Action{
    public function run(){
        $model=$this->modelClass;
        $request=Yii::$app->request;
        //获得传过来的用户名和密码
        if($request->isPost){
            $userInfo=$request->bodyParams;
            $auth_appid=$userInfo['user_name'];
            $auth_appkey=$userInfo['user_pass'];
        }
        //如果用户名和密码为空，返回空token；将该记录找出来
        if(!$auth_appid||!$auth_appkey){
            return (new $model())->emptyToken();
        }
        else{
            $model=$model::findOne(['auth_appid'=>$auth_appid,'auth_appkey'=>$auth_appkey]);
        }
        //判断该记录中的认证时间是否超过限定时间，没有超过直接返回该记录，超过了就生成新字符串、认证时间和IP保存到数据库，然后返回记录。
        if($model){
            if(intval($model->auth_time)+5>time()){
                return (new $model())->toToken($model);
            }
            $model->auth_token=\Yii::$app->security->generateRandomString();
            $model->auth_time=time();
            $model->auth_ip=Common::ip();
            if($model->save()){
                return (new $model())->toToken($model);
            }
        }else{
            return ['auth_token'=>false];
        }
    }
    function decrypt($data)
    {
        $pi_key = openssl_pkey_get_private(('PRIVATE_KEY'));//这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id
        $pu_key = openssl_pkey_get_public(config('PUBLIC_KEY'));//这个函数可用来判断公钥是否是可用的
        $decrypt = '';
        openssl_private_decrypt(base64_decode($data), $decrypt, $pi_key);//私钥解密
        return $decrypt;
    }
}