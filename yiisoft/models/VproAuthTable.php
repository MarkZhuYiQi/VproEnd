<?php
namespace app\models;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use Lcobucci\JWT;

class VproAuthTable extends ActiveRecord {
    public static function tableName()
    {
        return 'vpro_auth';
    }
    public function getVproRoles(){
        return $this->hasOne(VproRoles::className(), ['roles_id' => 'auth_roles_id']);
    }
}