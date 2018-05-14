<?php
namespace app\models;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use common\RedisData;
class Videoauth extends ActiveRecord implements IdentityInterface {
    public static $current_user_id=0;
    public function emptyToken(){
        return ['auth_token'=>''];
    }
    public function toToken($model){
//        return ['auth_token'=>$model->user_auth_token];
        return ['auth_token'=>$model->auth_token];
    }
    public static function tableName(){
        return 'videos_user';
//        return 'vpro_auth';
    }
    /**
     * Finds an identity by the given ID.
     * @param string|int $id the ID to be looked for
     * @return IdentityInterface the identity object that matches the given ID.
     * Null should be returned if such an identity cannot be found
     * or the identity is not in an active state (disabled, deleted, etc.)
     */
    public static function findIdentity($id)
    {
        // TODO: Implement findIdentity() method.
        // static可以理解为静态的$this
        return static::findOne($id);
    }

    /**
     * Finds an identity by the given token.
     * @param mixed $token the token to be looked for
     * @param mixed $type the type of the token. The value of this parameter depends on the implementation.
     * For example, [[\yii\filters\auth\HttpBearerAuth]] will set this parameter to be `yii\filters\auth\HttpBearerAuth`.
     * @return IdentityInterface the identity object that matches the given token.
     * Null should be returned if such an identity cannot be found
     * or the identity is not in an active state (disabled, deleted, etc.)
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        if(!$token)return false;
//        $redis=new RedisData();
        // TODO: Implement findIdentityByAccessToken() method.
        $getRes = static::findOne(['auth_token'=>$token]);
//        $getRes = json_decode($redis->get($token));
        if($getRes){
//            if($getRes->auth_time+3600*24<time()){
            if($getRes->auth_time+3600<time()){
                return false;
            }
            self::$current_user_id=$getRes->auth_id;
        }else{
            self::$current_user_id=0;
        }
        return $getRes;
    }
    /**
     * Returns an ID that can uniquely identify a user identity.
     * @return string|int an ID that uniquely identifies a user identity.
     */
    public function getId()
    {
        // TODO: Implement getId() method.
//        return $this->id;
    }

    /**
     * Returns a key that can be used to check the validity of a given identity ID.
     *
     * The key should be unique for each individual user, and should be persistent
     * so that it can be used to check the validity of the user identity.
     *
     * The space of such keys should be big enough to defeat potential identity attacks.
     *
     * This is required if [[User::enableAutoLogin]] is enabled.
     * @return string a key that is used to check the validity of a given identity ID.
     * @see validateAuthKey()
     */
    public function getAuthKey()
    {
        // TODO: Implement getAuthKey() method.
    }

    /**
     * Validates the given auth key.
     *
     * This is required if [[User::enableAutoLogin]] is enabled.
     * @param string $authKey the given auth key
     * @return bool whether the given auth key is valid.
     * @see getAuthKey()
     */
    public function validateAuthKey($authKey)
    {
        // TODO: Implement validateAuthKey() method.
    }
}