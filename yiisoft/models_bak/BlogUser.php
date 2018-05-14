<?php
/**
 * Created by PhpStorm.
 * User: szl4zsy
 * Date: 2/6/2017
 * Time: 10:31 AM
 */
namespace app\models;
use yii\db\ActiveRecord;
class BlogUser extends ActiveRecord
{
    public static function tableName()
    {
        return 'blog_user';
    }
    public function scenarios()
    {
        //允许user_name,user_pass,user_id通过post提交写入数据库信息
        return [
            'default'=>['user_name','user_pass','user_id']
        ];
    }
}