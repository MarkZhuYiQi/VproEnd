<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 4/20/2018
 * Time: 11:15 AM
 */
namespace back\controllers;

use app\common\Common;
use app\models\VproKeywords;

class KeywordController extends BackBaseController {
    public $modelClass = 'app\models\VproKeywords';
    function actions() {
        $actions = parent::actions();
        unset($actions['create'], $actions['index']);
        return $actions;
    }
    function actionIndex() {
        $res = VproKeywords::find()->asArray()->all();
        return $this->returnInfo($res);
    }
}