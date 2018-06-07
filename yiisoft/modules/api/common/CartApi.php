<?php
/**
 * Created by PhpStorm.
 * User: Red
 * Date: 2018/6/7
 * Time: 22:20
 */
namespace api\common;

use app\common\LogHandler;
use app\models\ModelFactory;
use common\RedisInstance;
use Exception;

class CartApi{
    private $redis;
    public function __construct()
    {
        $this->redis = RedisInstance::getRedis();
    }
    /**
     * -----------------------------------------------------------------------------------------------------------
     * cartController
     * -----------------------------------------------------------------------------------------------------------
     */
    /**
     * 根据提供的detail信息删除购物车对应信息
     * detail格式：
     * ['is_login'=>true|false,'cart_userid'=>xx,'cart_detail'=>[]]
     * @param $detail
     * @return string
     */
    function delCartDetail($detail){
        $vproCartDetail = ModelFactory::loadModel('vpro_cart_detail');
        $transaction = $vproCartDetail::getDb()->beginTransaction();
        try {
            if(count($detail["cart_detail"]) <= 0) throw new \Exception("could not delete the cart products due to lack of cart_detail.", 'CART_COURSES_DELETE_LOST');
            var_export($detail);
            foreach($detail["cart_detail"] as $v){
                var_export($vproCartDetail::findOne(['cart_course_id' => $v['cart_course_id'], 'cart_parent_id' => $v['cart_parent_id']]));
                if($record = $vproCartDetail::findOne(['cart_course_id' => $v['cart_course_id'], 'cart_parent_id' => $v['cart_parent_id']])){
                    $cart_name = $detail['is_login'] ? "cart" . $detail['cart_userid'] : 'cookiecart' . $detail['cart_id'];
                    $record->delete();
                    $res = $this->redis->sMembers($cart_name);
                    foreach($res as $key =>$value){
                        if(json_decode($value)->cart_course_id === $v['cart_course_id']){
                            $this->redis->sRem($cart_name, $value);
                        }
                    }
                }
            }
            exit();
            $transaction->commit();
            return true;
        }catch(Exception $e){
            $transaction->rollBack();
            LogHandler::saveLog('DEL_CART_ITEM', time(), $e->getMessage(), $detail['cart_userid']);
            return false;
        }
    }
    /**
     * @param $course_id
     * @return array|\yii\db\ActiveRecord[]
     * 获得课程下的详细课时列表
     */
    public function getCourseLessonList($course_id){
        if(!RedisInstance::checkRedisKey($course_id, 'VproLessonsList')) {
            $vproCourseLessonList = ModelFactory::loadModel('vpro_courses_lesson_list');
            $l_res = $vproCourseLessonList::find()->where(['lesson_course_id'=>$course_id])->asArray()->all();
            RedisInstance::hsetex('VproLessonsList', $course_id, RedisInstance::expired_time(60*12, 60*24), json_encode($l_res));
        } else {
            $l_res = json_decode(RedisInstance::hgetex('VproLessonsList', $course_id));
        }
        return $l_res;
    }
}