<?php

namespace app\controllers;

use Yii;
use app\models\WbStocks;
use app\models\WbSales;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;
/**
 * WbSalesController реализует CRUD действия для модели WbSales.
 */
class WbStockController extends Controller
{

public function actionAnalytics()
{
    // Получаем сводные данные (остатки + продажи)
    $reportData = \app\models\WbStocks::getTurnoverReport();

    // 1. ДЕФИЦИТ: Остаток есть, но скоро кончится (запас <= 7 дней)
    // Обязательно проверяем, что продажи идут (daily_speed > 0)
    $shortageData = array_filter($reportData, function($item) {
        return $item['current_stock'] > 0 
            && $item['daily_speed'] > 0 
            && $item['days_left'] <= 7;
    });
    \yii\helpers\ArrayHelper::multisort($shortageData, 'days_left', SORT_ASC);

    // 2. OUT-OF-STOCK: Товары, которые кончились, но имеют скорость продаж
    // Это ваша упущенная выгода
    $outOfStockData = array_filter($reportData, function($item) {
        return $item['current_stock'] <= 0 && $item['daily_speed'] > 0;
    });
    \yii\helpers\ArrayHelper::multisort($outOfStockData, 'daily_speed', SORT_DESC);

    // 3. ИЗЛИШКИ: Запас > 60 дней или продажи встали (daily_speed = 0)
    $excessData = array_filter($reportData, function($item) {
        $isExcess = $item['days_left'] > 60 || $item['daily_speed'] == 0;
        return $isExcess && $item['current_stock'] > 0;
    });
    \yii\helpers\ArrayHelper::multisort($excessData, 'current_stock', SORT_DESC);

    // Провайдеры данных
    $shortageProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $shortageData,
        'pagination' => ['pageSize' => 20],
    ]);

    $outOfStockProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $outOfStockData,
        'pagination' => ['pageSize' => 20],
    ]);

    $excessProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $excessData,
        'pagination' => ['pageSize' => 50],
    ]);

    return $this->render('analytics', [
        'shortageProvider' => $shortageProvider,
        'outOfStockProvider' => $outOfStockProvider,
        'excessProvider' => $excessProvider,
    ]);
}



public function actionWarehouseAnalytics($nmId = null, $days = 14)
{
    $days = (int)$days;
    $allData = \app\models\WbStocks::getWarehouseTurnover($nmId, $days);

    // Подготавливаем списки, используя nmID (как в wbcards)
    $gridNmIds = [];
    $gridCardNames = [];
    foreach ($allData as $row) {
        // Гарантируем, что ключ в данных называется nmID
        $nmIdentifier = $row['nmID'] ?? $row['nm_id'] ?? null;
        if ($nmIdentifier) {
            $gridNmIds[$nmIdentifier] = $nmIdentifier;
            $gridCardNames[$row['card_name']] = $row['card_name'];
        }
    }
    ksort($gridNmIds);
    asort($gridCardNames);

    $searchModel = new \yii\base\DynamicModel(['filterNmId', 'filterCardName']);
    $searchModel->addRule(['filterNmId', 'filterCardName'], 'safe');
    $searchModel->load(Yii::$app->request->get());

    // Фильтрация массива
    if ($searchModel->filterNmId) {
        $allData = array_filter($allData, function($item) use ($searchModel) {
            $val = $item['nmID'] ?? $item['nm_id'] ?? '';
            return (string)$val === (string)$searchModel->filterNmId;
        });
    }
    if ($searchModel->filterCardName) {
        $allData = array_filter($allData, function($item) use ($searchModel) {
            return ($item['card_name'] ?? '') === $searchModel->filterCardName;
        });
    }

    // Принудительно приводим ключи массива к nmID для GridView
    $finalData = array_map(function($item) {
        if (!isset($item['nmID']) && isset($item['nm_id'])) {
            $item['nmID'] = $item['nm_id'];
        }
        return $item;
    }, array_values($allData));

    return $this->render('warehouse_analytics', [
        'dataProvider' => new \yii\data\ArrayDataProvider([
            'allModels' => $finalData,
            'pagination' => ['pageSize' => 100],
            'sort' => ['attributes' => ['nmID', 'card_name', 'current_stock', 'warehouse_name']],
        ]),
        'searchModel' => $searchModel,
        'gridNmIds' => $gridNmIds,
        'gridCardNames' => $gridCardNames,
        'selectedNmId' => $nmId,
        'currentThreshold' => $days,
        'allCards' => \app\models\WbCard::getListForSelect(),
/*
        'allCards' => \yii\helpers\ArrayHelper::map(
            (new \yii\db\Query())->select(['nmID', 'title'])->from('wbcards')->all(), 
            'nmID', 'title'
        ),
*/
    ]);
}



/**
 * Отчет по ТОП-товарам в разрезе складов WB (Остатки и Продажи)
 */
public function actionTopWarehouseReport()
{
    $request = Yii::$app->request;
    $days = (int)$request->get('days', 14);
    $limit = (int)$request->get('limit', 50);
//    $minStock = (int)$request->get('minStock', 0);
    $minStock = $request->get('minStock', 0);

    if (!in_array($limit, [20, 50, 100, 500])) {
        $limit = 50;
    }

    $db = Yii::$app->db;

    $maxDate = $db->createCommand("SELECT MAX(`date`) FROM `wb_stocks`")->queryScalar();
    if (!$maxDate) {
        $maxDate = date('Y-m-d');
    }

    // 1. Выбираем ТОП товаров
    $topItemsQuery = (new \yii\db\Query())
        ->select([
            's.nm_id AS nmID',
            'c.title AS title',
            'c.vendorCode as vendorCode',
            'SUM(s.quantity) AS total_stock'
        ])
        ->from(['s' => 'wb_stocks'])
        ->leftJoin(['c' => 'wbcards'], 'c.nmID = s.nm_id')
        ->where(['s.date' => $maxDate])
        ->groupBy(['s.nm_id', 'c.title', 's.subject', 's.supplier_article'])
        ->orderBy(['total_stock' => SORT_DESC])
        ->limit($limit);

    if ($minStock > 0) {
        $topItemsQuery->having(['>', 'SUM(s.quantity)', $minStock]);
    }

    $topItems = $topItemsQuery->all();

    if (empty($topItems)) {
        return $this->render('top_warehouse_report', [
            'dataProvider' => new \yii\data\ArrayDataProvider(['allModels' => []]),
            'warehouses' => [],
            'days' => $days,
            'limit' => $limit,
            'minStock' => $minStock
        ]);
    }

    $topNmIds = ArrayHelper::getColumn($topItems, 'nmID');

    // 2. Выбираем оригинальные названия складов из базы данных
    $warehousesQuery = (new \yii\db\Query())
        ->select(['warehouse_name', 'SUM(quantity) as total_wh_stock'])
        ->from('wb_stocks')
        ->where(['date' => $maxDate, 'nm_id' => $topNmIds])
        ->andWhere(['not', ['warehouse_name' => null]])
        ->groupBy('warehouse_name');

    if ($minStock > 0) {
        $warehousesQuery->andWhere(['>', 'quantity', $minStock]);
    }

    $warehousesData = $warehousesQuery->orderBy(['total_wh_stock' => SORT_DESC])->all();
    $warehouses = ArrayHelper::getColumn($warehousesData, 'warehouse_name');

    if (empty($warehouses)) {
        return $this->render('top_warehouse_report', [
            'dataProvider' => new \yii\data\ArrayDataProvider(['allModels' => []]),
            'warehouses' => [],
            'days' => $days,
            'limit' => $limit,
            'minStock' => $minStock
        ]);
    }

    // 3. Остатки на сегодня
    $stocksDataQuery = (new \yii\db\Query())
        ->select(['nm_id', 'warehouse_name', 'SUM(quantity) AS stock'])
        ->from('wb_stocks')
        ->where(['date' => $maxDate, 'nm_id' => $topNmIds, 'warehouse_name' => $warehouses])
        ->groupBy(['nm_id', 'warehouse_name']);

    if ($minStock > 0) {
        $stocksDataQuery->andWhere(['>', 'quantity', $minStock]);
    }
    $stocksData = $stocksDataQuery->all();

    // 4. Продажи за период
    $startDate = date('Y-m-d', strtotime("-$days days"));
    $salesData = (new \yii\db\Query())
        ->select(['nmId', 'warehouseName', 'COUNT(*) AS sales_qty'])
        ->from('wb_sales')
        ->where(['between', 'date', $startDate, date('Y-m-d')])
        ->andWhere(['nmId' => $topNmIds, 'warehouseName' => $warehouses])
        ->andWhere(['warehouseType' => 'Склад WB'])
        ->groupBy(['nmId', 'warehouseName'])
        ->all();

    // Сборка матрицы (используем оригинальное имя склада в ключе)
    $matrix = [];
    foreach ($topItems as $item) {
        $nmID = $item['nmID'];
        $matrix[$nmID] = [
            'nmID' => $nmID,
            'title' => $item['title'],
            'vendorCode' => $item['vendorCode'],
            'total_stock' => $item['total_stock'],
        ];
        foreach ($warehouses as $wh) {
            $matrix[$nmID]["wh_{$wh}_stock"] = 0;
            $matrix[$nmID]["wh_{$wh}_sales"] = 0;
        }
    }

    // Заполнение остатков
    foreach ($stocksData as $row) {
        $nmID = $row['nm_id'];
        $wh = $row['warehouse_name'];
        if (isset($matrix[$nmID])) {
            $matrix[$nmID]["wh_{$wh}_stock"] = (int)$row['stock'];
        }
    }

    // Заполнение продаж
    foreach ($salesData as $row) {
        $nmID = $row['nmId'];
        $wh = $row['warehouseName'];
        if (isset($matrix[$nmID])) {
            $matrix[$nmID]["wh_{$wh}_sales"] = (int)$row['sales_qty'];
        }
    }
/*
    // Фильтрация пустых строк, если выбран minStock
    if ($minStock > 0) {
        $matrix = array_filter($matrix, function($row) use ($warehouses) {
            foreach ($warehouses as $wh) {
                if ($row["wh_{$wh}_stock"] > 0) {
                    return true;
                }
            }
            return false;
        });
    }
*/

// Фильтрация пустых строк или критических остатков
    if ($minStock === 'critical') {
        $matrix = array_filter($matrix, function($row) use ($warehouses) {
            foreach ($warehouses as $wh) {
                $stock = $row["wh_{$wh}_stock"] ?? 0;
                $sales = $row["wh_{$wh}_sales"] ?? 0;
                // Критичный остаток: остаток > 0, но меньше продаж
                if ($stock > 0 && $stock < $sales) {
                    return true;
                }
            }
            return false;
        });
    } elseif ((int)$minStock > 0) {
        $matrix = array_filter($matrix, function($row) use ($warehouses, $minStock) {
            foreach ($warehouses as $wh) {
                if (($row["wh_{$wh}_stock"] ?? 0) > (int)$minStock) {
                    return true;
                }
            }
            return false;
        });
    }

    $dataProvider = new \yii\data\ArrayDataProvider([
        'allModels' => array_values($matrix),
        'pagination' => [
            'pageSize' => $limit,
        ],
    ]);

    return $this->render('top_warehouse_report', [
        'dataProvider' => $dataProvider,
        'warehouses' => $warehouses,
        'days' => $days,
        'limit' => $limit,
        'minStock' => $minStock
    ]);
}

}