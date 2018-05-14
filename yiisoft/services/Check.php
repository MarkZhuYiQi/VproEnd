<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/9/2018
 * Time: 5:19 PM
 */
namespace services;

use app\models\VproCourses;

class Check extends Services{
    /**
     * 传入id，返回课程相关信息，注意：这里id如果不存在不会报错而是忽略
     * @param $course_ids
     * 传入数组，课程ids，先去redis查看课程是否存在并且不是空字符串，放入新数组；
     * 如果没找到，去数据库找，找到了就写入redis并返回给新数组， 没找到就往redis写入一个空字符串，防止缓存穿透
     * @return array $check_res 返回一个包含所有传入课程id的数组
     *
     * 这个空字符串需要定时清理
     */
    function checkCourses($course_ids){
        $check_res=[];
        foreach($course_ids as $v){
            $redis_str= $this->redis->hget('VproCourses', $v);
            if($redis_str==null || $redis_str==""){
                $res = VproCourses::_getDetail($v);
                if($res){
//                    $this->redis->hset("VproCourses", $v, json_encode($res));
                    array_push($check_res, $res);
                }else{
                    //这里后台需要准备一个程序，定时运行检查hash中的空字符串，找到就删除字段
                    if($redis_str==null)$this->redis->hset("VproCourses", $v, "");
                }
            }else{
                $res=json_decode($redis_str);
                array_push($check_res, $res);
            }
        }
        return $check_res;
    }

    /**
     * 检查课程id数组和实际返回数组一致性
     * @param $course_ids
     * @param $courses
     * @return $res
     * 返回格式：["difference"=>[xxx,xxx,xxx], "consistency"=>true|false]
     */
    function checkCoursesConsistency($course_ids, $courses){
        $res=[];
        foreach($courses as $item){
            if(!in_array($item->course_id, $course_ids)){
                array_push($res['difference'],$item->course_id);
            }
        }
        if(count($res)>0){
            $res['consistency']=false;
            return $res;
        }
        $res['consistency']=true;
        return $res;
    }
}