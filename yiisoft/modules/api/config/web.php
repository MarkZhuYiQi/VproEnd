<?php
$params = require_once __DIR__ . "/params.php";
$config = [
    //github.com/yiisoft/yii2/issues/5141
    //应对class element 问题
    //应用唯一标识ID
    'id' => 'api',
    //指定应用跟目录
    'basePath' => dirname(__DIR__),
    //定义别名
    'components' => [
        'user' => [
            'class' => '\yii\web\User',
            'enableSession' => false,
            'enableAutoLogin' => true,
//            'identityClass' => 'api\models\VproAuth'
        ],
        'request' => [
            'class' => 'yii\web\Request',
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'markZhuCookie',
            //启用json输入，RESTful WEB服务->快速入门
            //如未配置，API仅可分辨application/x-www-form-urlencoded 和 multipart/form-data 输入格式
            'parsers'=>[
                'application/json'=>'yii\web\JsonParser',
            ]
        ],
    ],
    'params'=> $params
];


return $config;
