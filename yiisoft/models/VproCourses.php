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

class VproCourses extends ActiveRecord {
    public static function tableName(){
        return 'vpro_courses';
    }
    public function getVproCoursesCover(){
        return $this->hasOne(VproCoursesCover::className(), ['course_cover_id'=>'course_id']);
    }
    public function getVproCoursesTempDetail(){
        return $this->hasOne(VproCoursesTempDetail::className(), ['course_id'=>'course_id']);
    }
    public function getVproAuth(){
        return $this->hasOne(VproAuthTable::className(), ['auth_id'=>'course_author']);
    }
    public function getVproCoursesContent() {
        return $this->hasOne(VproCoursesContent::className(), ['course_id' => 'course_id']);
    }






    public static function _getDetail($course_id){
        $course_detail = <<<QUERY
SELECT
	a.course_id, 
	a.course_title, 
	a.course_price, 
	a.course_author as course_author_id, 
	c.course_cover_address,
    va.auth_appid as course_author	
FROM
	vpro_courses as a
LEFT JOIN
	vpro_courses_cover as c ON c.course_cover_id = a.course_id
LEFT JOIN
	vpro_courses_temp_detail as d on d.course_id = a.course_id
LEFT JOIN 
    vpro_auth as va on va.auth_id = a.course_author
WHERE
	a.course_id = "$course_id";
QUERY;
        $db=Yii::$app->db;
        $d_res=$db->createCommand($course_detail)->queryOne();
        return $d_res;
    }

}