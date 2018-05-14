<?php
/**
 * Created by PhpStorm.
 * User: Red
 * Date: 2017/12/30
 * Time: 22:58
 */
namespace app\models;
use yii\redis\ActiveRecord;

class Customer extends ActiveRecord
{
    /**
     * @return array the list of attributes for this record
     */
    public function attributes()
    {
        return ['id', 'name', 'address', 'registration_date'];
    }

}