<?php
/**
 * Created by PhpStorm.
 * User: Red
 * Date: 2018/1/2
 * Time: 23:03
 */
namespace api\myactions;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\ValidationData;
use Yii;
use yii\rest\Action;
class AuthAction extends Action{
    /**
     * 返回格式：
     * {auth_token: '' ,  code : xxxxx, status: ''}
     */
    const TOKEN_SUCCESS = 20000;
    const TOKEN_EXIST = 50012;
    const TOKEN_ILLEGAL = 50008;
    const TOKEN_EXPRIRED = 50014;
    const TOKEN_NULL = 50016;
    public function run(){
        $model=$this->modelClass;
        $request=Yii::$app->request;
        if($request->isPost){
            $userInfo=$request->bodyParams;
            $auth_appid=$userInfo['user_name'];
            $auth_appkey=$userInfo['user_pass'];
        }
        //如果用户名和密码为空，返回空token；将该记录找出来
        if(!$auth_appid||!$auth_appkey){
            return
                [
                    'code'=>self::TOKEN_NULL,
                    'data'=>[
                        'auth_token'=>false,
                        'status'=>'username or userpass is null',
                    ]
                ];
        }
        else{
            $model=$model::findOne(['auth_appid'=>urlencode($auth_appid),'auth_appkey'=>$auth_appkey]);
//            $model->auth_token="";
//            $model->update();
        }
        //判断该记录中的认证时间是否超过限定时间，没有超过直接返回该记录，超过了就生成新字符串、认证时间和IP保存到数据库，然后返回记录。
        if($model){
            $signer=new Sha256();
            $key=\Yii::$app->getModule('api')->params['securityKey'];
            $jwt = $model->auth_token ? (new Parser())->parse((string)$model->auth_token):false;
            if($jwt){
                $vd=new ValidationData();
                $vd->setAudience('zhu');
                $vd->setIssuer('mark');
                $vd->setId("1111111");
                $vd->setSubject('everyone');
                $vd->setCurrentTime(time());
                if($jwt->verify($signer, $key)&&$jwt->validate($vd)){
//                    return ['auth_token'=>$model->auth_token, 'status'=>'exist', 'code'=>self::TOKEN_EXIST];
                    return ['data'=>['auth_token'=>$model->auth_token, 'status'=>'exist'], 'code'=>self::TOKEN_SUCCESS];
                }
//                return ['auth_token'=>false, 'err'=>'token expired'];
            }
            $token=(new Builder())->setIssuer("mark")      //iss, jwt签发者
            ->setAudience("zhu")       //aud 接收jwt的一方
            ->setSubject("everyone")         //sub面向的用户
            ->setExpiration(time()+\Yii::$app->getModule('api')->params['expireTime'])        //exp过期时间
            ->setIssuedAt(time())                       //iat签发时间，以上是标准中注册的声明
            ->setId("1111111", true)    //给头部加入一个键值对
            ->set("auth_id", $model->auth_id)
            ->set("auth_appid",$model->auth_appid)             //新注册一个声明
            ->sign($signer, $key)
            ->getToken();
            $model->auth_token=(string)$token;
            $model->update();
            return ['data'=>['auth_token'=>(string)$token, 'status'=>'new'], 'code'=>self::TOKEN_SUCCESS];
        }else{
            return ['data'=>['auth_token'=>false, 'status'=>'user '.$auth_appid.' could not found'], 'code'=>self::TOKEN_ILLEGAL];
        }
    }
}