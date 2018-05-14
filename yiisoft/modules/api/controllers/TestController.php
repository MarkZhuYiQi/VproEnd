<?php
namespace api\controllers;

use api\models\VproAuth;

use app\common\JwtAuth;
use app\controllers\BaseController;
use app\models\ModelFactory;


class TestController extends BaseController {
    public $modelClass="api\models\VproAuth";
    public function init(){
        parent::init();
        $this->enableCsrfValidation=false;
    }
    public function behaviors(){
        $behaviors = parent::behaviors();
        $behaviors['authenticator']=[
            'class' => JwtAuth::className(),
            'except'=>['create']
        ];
        return $behaviors;
    }
    public function actions(){
        $actions=parent::actions();
        unset($actions['index'], $actions['create']);
        $actions['create']=[
            'class' => 'api\myactions\AuthAction',
            'modelClass'=>$this->modelClass
        ];
        return $actions;
    }
    public function actionIndex(){
        $key='auth_appid';
        $vpro_auth=(ModelFactory::loadModel('vpro_auth'))::find()->asArray()->all();
        if(count($vpro_auth)){
            foreach($vpro_auth as $v){
                foreach($v as $key => $value){
                    $value=$value!=null?$value:"";
                    $va->$key=urlencode($value);
                    $va->insert();
                }
            }
        }else{
            echo 'db is empty';
        }
    }

}