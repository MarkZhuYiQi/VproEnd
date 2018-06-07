<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/3/28
 * Time: 20:52
 */
namespace app\models;
use yii\db\ActiveRecord;
class VproOrderSub extends ActiveRecord{
    public static function tableName()
    {
        return 'vpro_order_sub';
    }
    public function scenarios()
    {
        return [
            'add'  =>  ['order_id', 'course_id', 'course_price']
        ];
    }
    public function rules()
    {
        return [
            [['order_id', 'course_id', 'course_price'], 'required', 'on' => 'add']
        ];
    }
}