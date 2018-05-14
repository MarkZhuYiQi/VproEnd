<?php
/**
 * Created by PhpStorm.
 * User: SZL4ZSY
 * Date: 1/2/2018
 * Time: 1:54 PM
 */

namespace back;

class Module extends \yii\base\Module
{
    public function init()
    {
        $config = require(__DIR__ . '/config/web.php');
        parent::init();
        \Yii::configure($this, $config);
        //在这里指定配置文件中user组件的identityClass, 6的飞起
        \Yii::$app->user->identityClass='back\models\VproAuth';

    }
}