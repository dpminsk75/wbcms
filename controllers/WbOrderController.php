<?php

namespace app\controllers;

use Yii;
use app\models\WbOrder;
use app\models\WbOrderSearch;
use app\models\WbOrderFeedSearch;
use app\models\DPFilterForm;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * WbOrderController реализует просмотр данных из таблицы wb_order.
 */
class WbOrderController extends Controller
{
    /**
     * Настройка поведения (например, доступ только через POST для удаления, если оно будет)
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

    public function actionIndex()
    {
        $searchModel = new WbOrderSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionData() {
        $searchModel = new WbOrderSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

//        echo count($dataProvider->getModels()); die();

        return $this->render('data', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Лента заказов: карточка товара + статус (fbs/fbw) + откуда/куда +
     * цены + факты по комиссии/эквайрингу/логистике.
     *
     * Фильтр — существующий виджет getDPWidget (артикул + период), но по
     * умолчанию период — только сегодняшний день (в отличие от остальных
     * разделов, где по умолчанию последние 15/30 дней), и фильтр по
     * карточке по умолчанию не установлен.
     */
    public function actionFeed()
    {
        $filterModel = new DPFilterForm();
        $filterModel->load(Yii::$app->request->get());

        if (!$filterModel->date_from) {
            $filterModel->date_from = date('Y-m-d');
        }
        if (!$filterModel->date_to) {
            $filterModel->date_to = date('Y-m-d');
        }

        $searchModel = new WbOrderFeedSearch();
        // Подхватываем значения фильтров грида (status/warehouse_name/
        // region_name), которые kartik присылает как WbOrderFeedSearch[...]
        // в query string при отправке строки фильтров.
        $searchModel->load(Yii::$app->request->queryParams);
        $dataProvider = $searchModel->search([
            'nm_id' => $filterModel->nm_id,
            'date_from' => $filterModel->date_from,
            'date_to' => $filterModel->date_to,
        ]);

        return $this->render('feed', [
            'filterModel' => $filterModel,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Вспомогательный метод для поиска модели по ID
     * @param integer $id
     * @return WbOrder
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        if (($model = WbOrder::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('Заказ не найден.');
    }
}