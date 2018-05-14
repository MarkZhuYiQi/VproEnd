<?php
namespace back\controllers;
use app\controllers\BaseController;

/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 3/16/2018
 * Time: 5:32 PM
 */
class BackBaseController extends BaseController
{
    protected $redis;
    protected $db;
    protected $request;
    protected $params;

    function init()
    {
        $this->params = \Yii::$app->getModule('back')->params;
        $this->request = \Yii::$app->request;
        $this->redis = \Yii::$app->get('redis');
        $this->db = \Yii::$app->db;
        $init = parent::init(); // TODO: Change the autogenerated stub
        return $init;
    }
    function returnFormat($data, $code = 20000) {
        return [
            'code' => $code,
            'data' => $data
        ];
    }
    function returnInfo($data, $return_status='RETURN_SUCCESS') {
        return [
            'code' => $this->params[$return_status],
            'data' => $data
        ];
    }
    function checkParams($keys, $method) {
        $body = [];
        foreach($keys as $k) {
            $body[$k] = $this->request->$method($k, false);
            if (!$body[$k]) {
                return false;
            }
        }
        return $body;
    }
}