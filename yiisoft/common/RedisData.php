<?php
namespace common;
use Yii;

class RedisData{
    public $redis;
    function __construct()
    {
        $this->redis=new \Redis();
        $this->redis->connect('192.168.1.160',7000);
    }
    function getAll($component){
        $res=[];
        $all = $this->redis->hGetAll($component);
        return $all;
        if(!isset($all)||count($all)==0)return false;
        $obj='false';
        foreach($all as $key=>$value){
            if(preg_match("/^([0-9]+)\_?(.*)$/",$key,$match)){
                if(isset($$obj)&&$obj!='obj'.$match[1]) {
                    array_push($res, $$obj);
                }
                $obj='obj'.$match[1];
                $item=$match[2];
                if(!isset($$obj)||!is_object($$obj)){
                    $$obj=new \stdClass();
                }
                $$obj->$item=$value;
            }
        }
        array_push($res, $$obj);
        return $res;
    }
    function hget($table_name,$key){
        if( $table_name!='' && $key){
            $value=$this->redis->hGet($table_name,$key);
            return $value;
        }
        return false;
    }
    function get($key){
        if($key){
            return $this->redis->get($key);
        }
        return false;
    }
    function hset($table_name,$key,$value){
        if($table_name && $key && $value){
            return $this->redis->hSet($table_name,$key,$value);
        }
    }
    function set($key,$value){
        if($key && $value){
            return $this->redis->set($key,$value);
        }
    }
    function __call($fname, $args){
        return $this->redis->$fname(...$args);
    }
}