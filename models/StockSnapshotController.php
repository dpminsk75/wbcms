<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\StockSnapshot;
use app\models\search\StockSnapshotSearch;
use app\components\StockBalanceService;

class StockSnapshotController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = new StockSnapshotSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate()
    {
        $model = new StockSnapshot();
        // по умолчанию предлагаем 1-е число текущего месяца - снапшоты всегда на начало периода
        $model->period_date = date('Y-m-01');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Снапшот сохранён.');
            return $this->redirect(['index']);
        }

        return $this->render('_form', ['model' => $model]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Снапшот обновлён.');
            return $this->redirect(['index']);
        }

        return $this->render('_form', ['model' => $model]);
    }

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        Yii::$app->session->setFlash('success', 'Снапшот удалён.');
        return $this->redirect(['index']);
    }

    /**
     * Подставить в форму текущий расчётный баланс на дату снапшота (Смоленск)
     * - удобно, когда заводите снапшот вручную и хотите свериться,
     *   а не считать в уме qty_start + приходы - расходы.
     */
    public function actionSuggestBalance($nm_id, $on_date)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['balance' => StockBalanceService::getCurrentBalance((int)$nm_id, $on_date)];
    }

    protected function findModel($id): StockSnapshot
    {
        if (($model = StockSnapshot::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('Снапшот не найден.');
    }
}
