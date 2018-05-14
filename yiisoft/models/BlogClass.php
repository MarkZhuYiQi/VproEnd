<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/2/5
 * Time: 11:30
 */
namespace app\models;
use yii\db\ActiveRecord;
class BlogClass extends ActiveRecord
{
    public static function tableName()
    {
        return 'blog_class';
    }
    public function getBlog(){
        //这个getter方法设定了关联表，在查询blog_class表时可以通过关联查询
        //多对一的在blog中取出一个blog.detail_classId=blogClass.class_id的多维数组
        return $this->hasMany(Blog::className(),['detail_classId'=>'class_id']);
    }
}