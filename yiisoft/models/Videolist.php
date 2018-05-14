<?php
/**
 * Created by PhpStorm.
 * User: szl4zsy
 * Date: 3/28/2017
 * Time: 3:58 PM
 */
namespace app\models;
use yii\db\ActiveRecord;
class Videolist extends ActiveRecord{
    public static function tableName()
    {
        return 'vpro_video';
    }
    public function getVideosImg(){
        return $this->hasOne(VideosImg::className(),['video_cover_id'=>'video_cover']);
    }
    public function getVideosFiles(){
        return $this->hasOne(VideosFiles::className(),['video_file_id'=>'video_file']);
    }
}