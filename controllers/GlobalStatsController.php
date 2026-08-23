<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;

class GlobalStatsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'], // Доступ только авторизованным
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        // Сбрасываем выбор компании, чтобы система понимала: сейчас "глобальный режим"
        Yii::$app->companyManager->resetCurrentId();
        
        // Теперь здесь делай свой SQL-запрос по всем компаниям
        return $this->render('index');
    }
}