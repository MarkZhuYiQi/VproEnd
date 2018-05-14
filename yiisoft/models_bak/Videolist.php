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
        return 'videos';
    }
    public function getVideosImg(){
        return $this->hasOne(VideosImg::className(),['img_id'=>'v_pic']);
    }
    public function getVideosFiles(){
        return $this->hasOne(VideosFiles::className(),['vf_id'=>'v_file']);
    }
}