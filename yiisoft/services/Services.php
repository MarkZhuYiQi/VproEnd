<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/9/2018
 * Time: 3:40 PM
 */
namespace services;

use yii\base\InvalidCallException;
use yii\base\Object;

class Services extends Object{
    protected $redis;
    public function init()
    {
        $init = parent::init(); // TODO: Change the autogenerated stub
        $this->redis=\Yii::$app->get('redis');
        return $init;
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement __call() method.
        if(method_exists($this, $name)){
            //这里可以做日志，开始
            $return = call_user_func_array([$this, $name], $arguments);
            //这里可以做日志的结束
            return $return;
        }else{
            throw new InvalidCallException('services method is not existed.'.get_class($this)."::$name");
        }

    }

}