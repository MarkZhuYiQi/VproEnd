<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/3/28
 * Time: 20:52
 */
namespace app\models;
use yii\db\ActiveRecord;
class VproOrderLogs extends ActiveRecord{
    public static function tableName()
    {
        return 'vpro_order_logs';
    }
}