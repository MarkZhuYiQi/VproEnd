<?php

$params = require(__DIR__ . '/params.php');

$config = [
    //应用唯一标识ID
    'id' => 'basic',
    //指定应用跟目录
    'basePath' => dirname(__DIR__),
    //指定启动阶段需要运行的组件
    'bootstrap' => [
        //应用组件id或者模块id，类名，配置数组，匿名函数
        'log',

    ],
    'modules'=>[
        'api'=>[
            'class'=>'api\Module',
        ],
        'back' => [
            'class'=>'back\Module'
        ]
    ],
    //定义别名
    'aliases' => [
        //custom namespace
        '@services'=>'@app/services',
        '@Qiniu' => '@app/Qiniu',
        '@common' => '@app/common',
        '@api' => '@app/modules/api',
        '@back' => '@app/modules/back',
    ],
    'components' => [
        'redis' => [
            'class' => 'yii\redis\Connection',
            'hostname' => '192.168.1.160',
            'port' => 7000,
            'database' => 0,
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'markZhuCookie',
            //启用json输入，RESTful WEB服务->快速入门
            //如未配置，API仅可分辨application/x-www-form-urlencoded 和 multipart/form-data 输入格式
            'parsers'=>[
                'application/json'=>'yii\web\JsonParser',
            ]
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
//        'user' => [
//            'identityClass' => 'app\models\User',
//            'enableAutoLogin' => true,
//        ],
        //module的user组件在module里面指定了直接
        'user' => [
//            'identityClass' => 'app\models\client',
            'enableSession' => false,
            'identityClass' => 'app\models\Videoauth'
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => require(__DIR__ . '/db.php'),
        'urlManager' => [
            'enablePrettyUrl' => true,
            //该选项决定是否显示入口脚本（eg. index.php）
            'showScriptName' => false,
            //是否启用严格匹配模式，打开后不匹配就扔错误
            'enableStrictParsing' => false,
            'rules' => [
                [
                    'class'=>'yii\rest\UrlRule',
                    //这里面都是
                    'controller'=>[
                        'user',
                        'info',
                        'token',
                        'navbar',
                        'auth',
                        'videosuser',
                        'manage',
                        'api/test',
                        'api/auth',
                        'api/pay',
                        'api/order',
                        'back/course',
                        'back/lesson',
                        'back/qiniu',
                        'back/keyword'
                    ],
                ],
                [
                    'class'=>'yii\rest\UrlRule',
                    'controller'=>'videolist',
                    'extraPatterns'=>['POST deletefile'=>'deletefile']
                ],
            ],
        ],
        'helper' => [
            'class'=>'app\common\ErrorHandler',
        ],

    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}
return $config;
