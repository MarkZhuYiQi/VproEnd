<?php
/**
 * Created by PhpStorm.
 * User: szl4zsy
 * Date: 3/30/2017
 * Time: 2:20 PM
 */
namespace app\models;
use yii\db\ActiveRecord;
class VideosFiles extends ActiveRecord{
    public static function tableName()
    {
        return 'vpro_video_files';
    }
}