<?php
/**
 * Created by PhpStorm.
 * User: Red
 * Date: 2018/1/2
 * Time: 23:03
 */
namespace api\myactions;

use app\common\Common;
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
    public function run(){

        $model=$this->modelClass;
        $request = \Yii::$app->request;
        $params = \Yii::$app->params;
        if($request->isPost){
            $userInfo = $request->bodyParams;
            foreach($userInfo as $key => $value)
            {
                $userInfo[$key] = Common::decrypt($value);
            }
        }
        $auth_appid = $userInfo['user_name'];
        $auth_appkey = $userInfo['user_pass'];
        //如果用户名和密码为空，返回空token；将该记录找出来
        if(!$auth_appid||!$auth_appkey){
            return
                [
                    'code'  =>  $params['USER_PARAMS_NULL'],
                    'data'  =>  'username or userpass is null or wrong format'
                ];
        }
        else{
            // 这里需要做限制，防止数据库攻击
            $model=$model::findOne(['auth_appid'=>$auth_appid]);
            if ($model) {
                $passRes = Common::decrypt($model->auth_appkey) == $auth_appkey;
            }
//            $model->auth_token="";
//            $model->update();
        }
        //判断该记录中的认证时间是否超过限定时间，没有超过直接返回该记录，超过了就生成新字符串、认证时间和IP保存到数据库，然后返回记录。
        if($passRes) {
            $signer=new Sha256();
            $key=\Yii::$app->params['securityKey'];
            $jwt = $model->auth_token ? (new Parser())->parse((string)$model->auth_token):false;
            // 判断token是否有效，false是jwt无效验证，需要返回错误
            if($jwt){
                //
                $vd=new ValidationData();
                $vd->setAudience('zhu');
                $vd->setIssuer('mark');
                $vd->setId("1111111");
                $vd->setSubject('everyone');
                $vd->setCurrentTime(time());
                // 判断字符串是否过期并且是否有效
                if($jwt->verify($signer, $key)&&$jwt->validate($vd)){
                    return [
                        'data'=>['auth_token'=>$model->auth_token, 'status'=>'exist'],
                        'code'=>$params['RETURN_SUCCESS']
                    ];
                }
            }
            $token=(new Builder())->setIssuer("mark")      //iss, jwt签发者
            ->setAudience("zhu")       //aud 接收jwt的一方
            ->setSubject("everyone")         //sub面向的用户
            ->setExpiration(time() + intval(\Yii::$app->params['expireTime']))        //exp过期时间
            ->setIssuedAt(time())                       //iat签发时间，以上是标准中注册的声明
            ->setId("1111111", true)    //给头部加入一个键值对
            ->set("auth_id", $model->auth_id)
            ->set("auth_appid",$model->auth_appid)             //新注册一个声明
            ->sign($signer, $key)
            ->getToken();
            $model->auth_token=(string)$token;
            $model->update();
            return [
                'data'=>['auth_token'=>(string)$token, 'status'=>'new'],
                'code'=>$params['RETURN_SUCCESS']
            ];
        }else{
            return [
                'data'=>['auth_token'=>false, 'status'=>'user '.$auth_appid.' could not found'],
                'code'=>$params['USER_TOKEN_ILLEGAL']
            ];
        }
    }
}