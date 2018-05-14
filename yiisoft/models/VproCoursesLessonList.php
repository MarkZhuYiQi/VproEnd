<?php
namespace app\models;
use yii\db\ActiveRecord;

/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 3/19/2018
 * Time: 5:17 PM
 */
class VproCoursesLessonList extends ActiveRecord{
    public static function tableName(){
        return 'vpro_courses_lesson_list';
    }
    public function getVproVideo(){
        return $this->hasOne(VproVideo::className(), ['video_lesson_id'=>'lesson_id']);
    }
}