<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/2/5
 * Time: 11:30
 */
namespace app\models;
use yii\db\ActiveRecord;
class BlogMeta extends ActiveRecord
{
    public static function tableName()
    {
        return 'blog_meta';
    }
}