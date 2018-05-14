<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/3/28
 * Time: 20:52
 */
namespace app\models;
use yii\db\ActiveRecord;
class VproVideo extends ActiveRecord{
    public static function tableName()
    {
        return 'vpro_video';
    }
    public function getVproVideoFiles() {
        return $this->hasOne(VproVideoFiles::class, ['video_file_lesson_id' => 'video_lesson_id']);
    }
    public function getVproVideoDetail() {
        return $this->hasOne(VproVideoDetail::class, ['detail_lesson_id' => 'video_lesson_id']);
    }
}