<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/10/2017
 * Time: 3:45 PM
 */
namespace app\myactions;
use yii\rest\Action;
class TokenAction extends Action
{
    public function run(){
        $client_appid=\Yii::$app->request->get('client_appid',false);
        $client_appkey=\Yii::$app->request->get('client_appkey',false);
        $model=$this->modelClass;
        if(!$client_appid||!$client_appkey)
        {
            //如果没得到用户名和密码就返回空的token
            return (new $model())->emptyToken();
        }
        else
        {
            $model=$model::findOne(['client_appid'=>$client_appid,'client_appkey'=>$client_appkey]);
        }
        if($model)
        {
            if($model->client_token != null)
            {
                if($model -> client_token_time + 5 > time())
                {
                    return $model->client_token;
                }
            }
            $model->client_token=\Yii::$app->security->generateRandomString();
            $model->client_token_time=time();
        }
        if($model->save()){
            return $model->toToken();
        }
    }
}
