<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\data\ActiveDataProvider;
use app\components\WbApi;
use app\models\WbCard;
use app\models\WbSalesFunnelHistory; // Новое имя модели

class WbGetSalesFunnelController extends Controller
{

    public function actionIndex()
    {
        $request = Yii::$app->request;
        $productId = $request->get('productId');
        $dateFrom = $request->get('dateFrom');
        $dateTo = $request->get('dateTo');

        $productsData = Yii::$app->db->createCommand(
            "SELECT DISTINCT product_id, product.name as product_name
             FROM product_wb_card, product
             WHERE product.name IS NOT NULL 
             and product_wb_card.product_id = product.id
             ORDER BY product_name ASC"
        )->queryAll();
        $productsList = \yii\helpers\ArrayHelper::map($productsData, 'product_id', 'product_name');


        $relatedCards = [];
        $chartData = [];
        $dataProvider = [];

        if ($productId) {

            $query = WbSalesFunnelHistory::find();

            $relatedNmIds = (new \yii\db\Query())
                ->select('wb_nm_id')
                ->from('product_wb_card')
                ->where(['product_id' => $productId])
                ->column();
                
            $query->andWhere(['in', 'nmId', $relatedNmIds]);
            if ($dateFrom) $query->andWhere(['>=', 'date', $dateFrom]);
            if ($dateTo)   $query->andWhere(['<=', 'date', $dateTo]);

            // Получаем связанные карточки (ID и Название из основной таблицы wbcards)
            $relatedCards = (new \yii\db\Query())
                ->select(['w.nmId', 'w.title as card_name', 'w.vendorCode as vendorCode'])
                ->from('product_wb_card pwc')
                ->innerJoin('wbcards w', 'pwc.wb_nm_id = w.nmId') // Связь через nmId
                ->where(['pwc.product_id' => $productId])
                ->all();

            $relatedNmIds = \yii\helpers\ArrayHelper::getColumn($relatedCards, 'nmId');
            $query->andWhere(['in', 'nmId', $relatedNmIds]);

            $dataProvider = new ActiveDataProvider([
                'query' => $query->orderBy(['date' => SORT_DESC, 'nmId' => SORT_ASC]),
                'pagination' => ['pageSize' => 50],
            ]);

// формируем график - запрос чуток другой, делаем клон
            $chartQuery = clone $query; 
            $allModels = $chartQuery->orderBy(['date' => SORT_ASC])->all();
            $grouped = [];

            foreach ($allModels as $model) {
                $d = $model->date;
                if (!isset($grouped[$d])) {
                    // Превращаем дату в миллисекунды для JS
                    $timestamp = strtotime($d) * 1000; 
                    $grouped[$d] = [
                        'date' => $timestamp, // Теперь здесь число
                        'openCount' => 0,
                        'cartCount' => 0,
                        'orderCount' => 0,
                        'orderSum' => 0
                    ];
                }
                $grouped[$d]['openCount'] += (int)$model->openCount;
                $grouped[$d]['cartCount'] += (int)$model->cartCount;
                $grouped[$d]['orderCount'] += (int)$model->orderCount;
                $grouped[$d]['orderSum'] += (float)$model->orderSum;
            }
            $chartData = array_values($grouped);
        }

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'productsList' => $productsList,
            'relatedCards' => $relatedCards,
            'productId' => $productId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'chartData' => $chartData, // Отправляем данные для графиков

        ]);

    }

    public function actionSyncMissing()
    {
    set_time_limit(3600); 
    $api = new WbApi();
    
    $dateFrom = date('Y-m-d', strtotime('-7 days'));
    $dateTo = date('Y-m-d');

        $missingNmIds = WbCard::find()
            ->select('wbcards.nmId')
            ->leftJoin('wb_sales_funnel_history', 'wbcards.nmId = wb_sales_funnel_history.nmId AND wb_sales_funnel_history.date >= :dateFrom', [':dateFrom' => $dateFrom])
            ->where(['wb_sales_funnel_history.nmId' => null])
            ->distinct()
            ->column();
        
        if (empty($missingNmIds)) {
            Yii::$app->session->setFlash('info', "Все данные за текущий период уже загружены.");
            return $this->redirect(['index']);
        }

        $allNmIds = $missingNmIds;

        // Разбиваем на порции по 20 штук
        $chunks = array_chunk($allNmIds, 20);
        $updatedCount = 0;
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $chunk) {
            // 2. Получаем данные для текущей пачки
            $data = $api->getFunnelHistory($chunk, $dateFrom, $dateTo);

            if (is_array($data)) {
                foreach ($data as $item) {
                    if (isset($item['product']) && isset($item['history'])) {
                        $this->saveProductHistory($item);
                        $updatedCount++;
                    }
                }
            } else {
                Yii::error("Ошибка или пустой ответ для чанка #" . ($index + 1));
            }

            // 3. Пауза 15 секунд между запросами (кроме последнего чанка)
            if (($index + 1) < $totalChunks) {
                sleep(15);
            }
        }

        Yii::$app->session->setFlash('success', "Синхронизация завершена. Обработано товаров: $updatedCount");

        return $this->redirect(['index']);
    }

    public function actionSync()
    {
        // Увеличиваем лимит времени, так как 15 секунд паузы между пачками
        // при большом количестве товаров потребуют много времени
        set_time_limit(3600); 
        $api = new WbApi();
        
        $dateFrom = date('Y-m-d', strtotime('-7 days'));
        $dateTo = date('Y-m-d');

        // 1. Берем ВСЕ артикулы (убрали limit)
        $allNmIds = WbCard::find()->select('nmId')->column();
        
        if (empty($allNmIds)) {
            Yii::$app->session->setFlash('error', "Таблица wbcards пуста.");
            return $this->redirect(['index']);
        }

        // Разбиваем на порции по 20 штук
        $chunks = array_chunk($allNmIds, 20);
        $updatedCount = 0;
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $chunk) {
            // 2. Получаем данные для текущей пачки
            $data = $api->getFunnelHistory($chunk, $dateFrom, $dateTo);

            if (is_array($data)) {
                foreach ($data as $item) {
                    if (isset($item['product']) && isset($item['history'])) {
                        $this->saveProductHistory($item);
                        $updatedCount++;
                    }
                }
            } else {
                Yii::error("Ошибка или пустой ответ для чанка #" . ($index + 1));
            }

            // 3. Пауза 15 секунд между запросами (кроме последнего чанка)
            if (($index + 1) < $totalChunks) {
                sleep(15);
            }
        }

        Yii::$app->session->setFlash('success', "Синхронизация завершена. Обработано товаров: $updatedCount");

        return $this->redirect(['index']);
    }

    private function saveProductHistory($item)
    {
        // Метод сохранения из вашего последнего кода
        $nmId = $item['product']['nmId'];
        foreach ($item['history'] as $dayData) {
            $sql = "INSERT INTO wb_sales_funnel_history (nmId, date, openCount, cartCount, orderCount, orderSum, buyoutCount, buyoutSum) 
                    VALUES (:nmId, :date, :open, :cart, :orders, :orderssum, :buyout, :buyoutSum)
                    ON DUPLICATE KEY UPDATE 
                    openCount = VALUES(openCount), 
                    cartCount = VALUES(cartCount), 
                    orderCount = VALUES(orderCount),
                    orderSum = VALUES(orderSum),
                    buyoutCount = VALUES(buyoutCount),
                    buyoutSum = VALUES(buyoutSum)";
            
            Yii::$app->db->createCommand($sql, [
                ':nmId'      => $nmId,
                ':date'      => $dayData['date'],
                ':open'      => $dayData['openCount'] ?? 0,
                ':cart'      => $dayData['cartCount'] ?? 0,
                ':orders'    => $dayData['orderCount'] ?? 0,
                ':orderssum' => $dayData['orderSum'] ?? 0,
                ':buyout'    => $dayData['buyoutCount'] ?? 0,
                ':buyoutSum' => $dayData['buyoutSum'] ?? 0,
            ])->execute();
        }
    }

    public function actionWbcard($nmId = null, $dateFrom = null, $dateTo = null)
    {
        $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-14 days'));
        $params = \app\components\getDPWidget::getParams(14);

        $chartData = [];
        $card = [];
        $dataProvider = null;

        if ($params['nm_id']) {
            $card = \app\models\WbCard::findOne(['nmID' => $params['nm_id']]);

            $query = \app\models\WbSalesFunnelHistory::find() 
                ->where(['nmId' => $params['nm_id']])
                ->andWhere(['between', 'date', $params['date_from'], $params['date_to']])
                ->orderBy(['date' => SORT_DESC]);
            $stats = $query->all();

            $dataProvider = new \yii\data\ArrayDataProvider([
                'allModels' => $stats,
                'pagination' => ['pageSize' => 50],
            ]);

            $chartData = [];
            $chartQuery = clone $query; 
            $allModels = $chartQuery->orderBy(['date' => SORT_ASC])->all();
            $grouped = [];

            foreach ($allModels as $model) {
                $d = $model->date;
                if (!isset($grouped[$d])) {
                    // Превращаем дату в миллисекунды для JS
                    $timestamp = strtotime($d) * 1000; 
                    $grouped[$d] = [
                        'date' => $timestamp, // Теперь здесь число
                        'openCount' => 0,
                        'cartCount' => 0,
                        'orderCount' => 0,
                        'orderSum' => 0
                    ];
                }
                $grouped[$d]['openCount'] += (int)$model->openCount;
                $grouped[$d]['cartCount'] += (int)$model->cartCount;
                $grouped[$d]['orderCount'] += (int)$model->orderCount;
                $grouped[$d]['orderSum'] += (float)$model->orderSum;
            }
            $chartData = array_values($grouped);

        }

        return $this->render('wbcard', [
            'dataProvider' => $dataProvider,
            'chartData' => $chartData,
            'card' => $card,
        ]);
    }
}