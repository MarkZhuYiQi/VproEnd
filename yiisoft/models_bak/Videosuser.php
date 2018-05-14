<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/3/28
 * Time: 20:52
 */
namespace app\models;
use yii\db\ActiveRecord;
class Videosuser extends ActiveRecord{
    public function emptyToken(){
        return [
            'auth_token'=>'',
            'auth_id'=>false
        ];
    }
    public function toToken($model){
        return [
            'auth_token'=>$model->auth_token,
            'auth_id'=>$model->auth_id
        ];
    }
    public static function tableName()
    {
//        return 'videos_user';
        return 'vpro_auth';
    }
    public function getVproAuthInfo(){
        return $this->hasMany(VproAuthInfo::className(),['info_pid'=>'auth_id']);
    }
}