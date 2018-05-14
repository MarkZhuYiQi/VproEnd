<?php
/**
 * Created by PhpStorm.
 * User: szl4zsy
 * Date: 1/19/2018
 * Time: 3:26 PM
 */

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class VproCoursesContent extends ActiveRecord {
    public static function tableName(){
        return 'vpro_courses_content';
    }
}