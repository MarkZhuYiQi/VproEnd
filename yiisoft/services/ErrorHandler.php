<?php

use services\Services;

/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/12/2018
 * Time: 10:19 AM
 */

class ErrorHandler extends Services{
    protected $_errorHandlerModelName = '';
    protected $_errorHandlerModel;
    public function init()
    {
        $init = parent::init(); // TODO: Change the autogenerated stub



        return $init;
    }
    public function getPrimaryKey(){

    }
    /**
     * @property $code | Int, http 错误码
     * @property $message | String, 错误的具体信息
     * @property $file | string, 发生错误的文件
     * @property $line | Int, 发生错误所在文件的代码行
     * @property $created_at | Int, 发生错误的执行时间戳
     * @property $ip | string, 访问人的ip
     * @property $name | string, 错误的名字
     * @property $trace_string | string, 错误的追踪信息
     * @return string 返回错误存储到mongodb的id，作为前端显示的错误编码
     * 该函数从errorHandler得到错误信息，然后保存到mongodb中。
     */
    public function saveByErrorHandler(
        $code, $message, $file, $line, $created_at,
        $ip, $name, $trace_string, $url, $req_info=[]
    ){
        $category = Yii::$app->params['AppName'];
        $model = new $this->_errorHandlerModelName();
        $model->category     = $category;
        $model->code         = $code;
        $model->message      = $message;
        $model->file         = $file;
        $model->line         = $line;
        $model->created_at   = $created_at;
        $model->ip           = $ip;
        $model->name         = $name;
        $model->url          = $url;
        $model->request_info = $req_info;
        $model->trace_string = $trace_string;
        $model->save();
        return (string)$model[$this->getPrimaryKey()];

    }
    public function coll($filter=''){
        $query = $this->_errorHandlerModel->find();
        $query = Yii::$service->helper->ar->getCollByFilter($query, $filter);
        $coll = $query->all();
        if (!empty($coll)) {
            foreach ($coll as $k => $one) {
                $coll[$k] = $one;
            }
        }
        return [
            'coll' => $coll,
            'count'=> $query->limit(null)->offset(null)->count(),
        ];
    }
}