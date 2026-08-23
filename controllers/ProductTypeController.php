<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\data\ArrayDataProvider;
use app\models\ProductType;
use yii\db\Transaction;

class ProductTypeController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Список/редактирование типов продуктов (tabular).
     */
    public function actionIndex(): \yii\web\Response|string 
    {
        $models = ProductType::find()->orderBy(['name' => SORT_ASC])->all();
        $models[] = new ProductType(); // пустая строка для нового

        if (Yii::$app->request->isPost) {
            $postRows = Yii::$app->request->post('ProductType', []);
            $tx = Yii::$app->db->beginTransaction(Transaction::SERIALIZABLE);
            try {
                foreach ($postRows as $row) {
                    $id = $row['id'] ?? null;
//                    $deleteFlag = isset($row['_delete']) && $row['_delete'];
                    $deleteFlag = !empty($row['_delete']); // Упрощенная проверка чекбокса
                    $name = trim((string)($row['name'] ?? ''));

                    if ($id) {
                        $model = ProductType::findOne($id);
                        if (!$model) {
                            continue;
                        }
                        if ($deleteFlag) {
                            $model->delete();
                            continue;
                        }
//                        $model->load(['ProductType' => $row]);
//                        if ($model->validate()) {
//                            $model->save(false);
//                        }
                        // Загружаем данные и сохраняем только если валидно
                        if ($model->load($row, '')) { // Используем пустой префикс, так как $row уже массив атрибутов
                            $model->save(false);
                        }
                    } else {
                        if ($deleteFlag || $name === '') {
                            continue;
                        }
                        $model = new ProductType();
//                        $model->load(['ProductType' => $row]);
//                        if ($model->validate()) {
//                            $model->save(false);
//                        }
                        if ($model->load($row, '')) {
                            $model->save(false);
                        }
                    }
                }
                $tx->commit();
                Yii::$app->session->setFlash('success', 'Изменения сохранены');
                return $this->redirect(['index']);
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::$app->session->setFlash('error', 'Ошибка: ' . $e->getMessage());
                throw $e;
            }
        }

        $dataProvider = new ArrayDataProvider([
            'allModels' => $models,
            'pagination' => false,
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }
}

