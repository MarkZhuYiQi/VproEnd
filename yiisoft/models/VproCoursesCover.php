<?php
namespace app\models;
use yii\db\ActiveRecord;

/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/13/2018
 * Time: 11:44 AM
 */

class VproCoursesCover extends ActiveRecord{
    public static function tableName(){
        return 'vpro_courses_cover';
    }
}