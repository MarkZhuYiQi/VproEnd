<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/2/5
 * Time: 10:37
 */
namespace app\models;
use yii\db\ActiveRecord;
class Blog extends ActiveRecord
{
    public static function tableName()
    {
        return 'blog_detail';
    }
    function getBlogClass(){
        //这个getter方法设定了关联表，在查询blog_detail表是可以通过关联查询
        //一对一的在blog_class中取出一个blogClass.class_id=blog.detail_classId的值
        return $this->hasOne(BlogClass::className(),['class_id'=>'detail_classId']);
    }
    function getBlogMeta(){
        return $this->hasMany(BlogMeta::className(),['detail_id'=>'detail_id']);
    }
}