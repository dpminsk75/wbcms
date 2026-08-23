<?php

namespace app\controllers;

use Yii;
use app\models\ProductWbCard;
use app\models\ProductWbCardSearch;
use app\models\Product;
use app\models\WbCard;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\helpers\ArrayHelper;

class ProductWbCardController extends Controller
{
    public function actionIndex()
    {
        $searchModel = new ProductWbCardSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

public function actionCreate()
{
    $model = new ProductWbCard();
    $model->type = 1;

    if ($this->request->isPost) {
        $post = $this->request->post();
        $wb_nm_id = $post['ProductWbCard']['wb_nm_id'];
        $items = $post['Items'] ?? [];

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($items as $item) {
                $newLink = new ProductWbCard();
                $newLink->wb_nm_id = $wb_nm_id;
                $newLink->product_id = $item['product_id'];
                $newLink->q = $item['q'] ?? 0;
                $newLink->p = $item['p'] ?? 0;
                $newLink->type = 1;
                
                if (!$newLink->save()) {
                    throw new \Exception("Ошибка сохранения товара");
                }
            }
            $transaction->commit();
            return $this->redirect(['index']);
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
    }

    return $this->render('create', ['model' => $model]);
}

public function actionUpdate($id)
{
    // Находим запись, по которой кликнули "Редактировать"
    $mainModel = $this->findModel($id);
    $wb_nm_id = $mainModel->wb_nm_id;

    // Загружаем все существующие привязки этой карточки (тип 1)
    $existingItems = ProductWbCard::findAll(['wb_nm_id' => $wb_nm_id, 'type' => 1]);

    if ($this->request->isPost) {
        $post = $this->request->post();
        $new_wb_nm_id = $post['ProductWbCard']['wb_nm_id'];
        $items = $post['Items'] ?? [];

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 1. Удаляем все старые привязки этой карточки (тип 1)
            ProductWbCard::deleteAll(['wb_nm_id' => $wb_nm_id, 'type' => 1]);

            // 2. Сохраняем новые из формы
            foreach ($items as $item) {
                if (empty($item['product_id'])) continue; // Пропускаем пустые строки

                $newLink = new ProductWbCard();
                $newLink->wb_nm_id = $new_wb_nm_id;
                $newLink->product_id = $item['product_id'];
                $newLink->q = $item['q'] ?? 0;
                $newLink->p = $item['p'] ?? 0;
                $newLink->type = 1;

                if (!$newLink->save()) {
                    throw new \Exception("Ошибка при сохранении товара");
                }
            }
            $transaction->commit();
            return $this->redirect(['index']);
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
    }

    return $this->render('update', [
        'model' => $mainModel,
        'existingItems' => $existingItems,
    ]);
}

    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        return $this->redirect(['index']);
    }

    protected function findModel($id)
    {
        if (($model = ProductWbCard::findOne(['id' => $id])) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('Страница не найдена.');
    }
}