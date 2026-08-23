<?php

namespace app\controllers;

use Yii;
use app\models\WbSales;
use app\models\WbSalesSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * WbSalesController реализует CRUD действия для модели WbSales.
 */
class WbSalesController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Списки всех продаж с фильтрацией (Kartik GridView использует этот метод)
     * URL: /wb-sales/index
     */
    public function actionIndex()
    {
        $searchModel = new WbSalesSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Отображение одной записи (Сырые данные)
     * URL: /wb-sales/view?id=...
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Поиск модели по первичному ключу (saleID)
     */
    protected function findModel($id)
    {
        if (($model = WbSales::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('Запрошенная страница не существует.');
    }
}