<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 2/10/2017
 * Time: 3:28 PM
 */
namespace app\controllers;
use yii\rest\ActiveController;
use yii\web\Response;

class TokenController extends ActiveController
{
    public $modelClass='app\models\client';
    public function behaviors(){
        $behaviors=parent::behaviors();
        //该选项决定返回给用户的是xml还是json
        $behaviors['contentNegotiator']['formats']['text/html'] = Response::FORMAT_JSON;
        return $behaviors;
    }
    public function actions(){
        return [
            'index' => [
                'class' => 'app\myactions\TokenAction',
                'modelClass' => $this->modelClass,
            ]
        ];
    }
}
