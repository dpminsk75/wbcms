<?php

/**
 * Шаблон основного конфига web-приложения.
 * Разворачивание: скопировать в config/web.php и сгенерировать cookieValidationKey
 * (например, случайной строкой из 32 символов).
 * Реальный web.php не должен попадать в git (см. .gitignore).
 */

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'name' => 'Товары для WB',
    'language' => 'ru-RU',
    'runtimePath' => '@app/../runtime',
    'bootstrap' => ['log', 'companyManager'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],

    'components' => [
        'request' => [
            'cookieValidationKey' => 'ЗАМЕНИТЕ_НА_СЛУЧАЙНУЮ_СТРОКУ',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'companyManager' => [
            'class' => 'app\components\CompanyManager',
        ],
        'wbHttpClient' => [
            'class' => 'app\components\WbHttpClient',
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
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
        'db' => $db,
        'assetManager' => [
            'linkAssets' => true,
        ],

        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
    ],
    'modules' => [
        'user' => [
            'class' => 'Da\User\Module',
            'administrators' => ['admin'],
        ],
        'gridview' => [
            'class' => '\kartik\grid\Module',
        ],
        'dynagrid' => [
            'class' => '\kartik\dynagrid\Module',
        ],
    ],

    'as access' => [
        'class' => 'yii\filters\AccessControl',
        'except' => ['site/login', 'site/error'],
        'rules' => [
            [
                'allow' => true,
                'roles' => ['@'],
            ],
        ],
    ],

    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        'allowedIPs' => ['*'],
    ];
}

return $config;
