<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 3/30/2018
 * Time: 10:40 AM
 */
namespace app\controllers;
use api\models\VproAuth;
use app\models\ModelFactory;
use app\models\VproAuthTable;
use yii\base\Exception;
use yii\web\Controller;

class DataController extends CombaseController {
    public function init()
    {
        parent::init();
    }
    public function actionAuthMysqlToRedis() {
//        $auth = new VproAuth();
//        $info = $auth::find()->asArray()->all();
//        return json_encode($info);
//        exit();
        $auth_count = VproAuthTable::find()->count();
        $auth_per_size = 100;
        $page_total = ceil($auth_count/$auth_per_size);
        for($i = 0; $i < $page_total; $i++){
            $condition = VproAuthTable::find()->select(['auth_id'])->orderBy('auth_id')->limit(1)->offset($i * $auth_per_size)->one();
            $res = VproAuthTable::find()
                ->select([
                    'vpro_auth.auth_id',
                    'vpro_auth.auth_appid',
                    'vpro_auth.auth_appkey',
                    'vr.roles_name'
                ])
                ->joinWith('vproRoles vr')
                ->where(['>=', 'auth_id', $condition['auth_id']])
                ->limit($auth_per_size)
//                ->createCommand()
//                ->getRawSql();
                ->asArray()
                ->all();
            $this->mysqlToRedis($res);
        }
        return json_encode(['code' => 20000, 'data' => true]);
    }
    public function mysqlToRedis($data) {
        foreach($data as $value) {
            $vpro_auth_redis = new VproAuth();
            foreach($value as $k=>$v){
                if($k === 'vproRoles')continue;
                if($k === 'auth_appid')$v = urlencode($v);
                $vpro_auth_redis->$k = $v;
            }
            $vpro_auth_redis->save();
        }
    }
}