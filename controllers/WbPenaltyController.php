<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\DetailByPeriod;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use kartik\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

class WbPenaltyController extends Controller
{
    public function actionLogistics()
    {
        $request = Yii::$app->request;
        $dateFrom = $request->get('date_from', date('Y-m-d', strtotime('-7 days')));
        $dateTo = $request->get('date_to', date('Y-m-d'));
        $target = $request->get('_target');

        $searchModel = new DetailByPeriod();

        // Оставляем AJAX только для загрузки блока "Статистика по дням" (если он тебе еще нужен)
        if ($request->isAjax && $target === 'days') {
            return GridView::widget([
                'dataProvider' => new ArrayDataProvider([
                    'allModels' => $searchModel->getSummaryLogistics($dateFrom, $dateTo),
                    'pagination' => false,
                ]),
                'panel' => ['type' => GridView::TYPE_INFO, 'heading' => 'Статистика по дням'],
                'columns' => [
                    ['attribute' => 'date_only', 'label' => 'Дата', 'group' => true],
                    ['attribute' => 'bonus_type_name', 'label' => 'Причина'],
                    ['attribute' => 'items_count', 'label' => 'Кол-во', 'hAlign' => 'center'],
                    ['attribute' => 'total_sum', 'label' => 'Сумма', 'format' => ['decimal', 2], 'hAlign' => 'right'],
                ],
            ]);
        }
        
        $periodSummaryProvider = new ArrayDataProvider([
            'allModels' => $searchModel->getSummaryByPeriod($dateFrom, $dateTo),
            'pagination' => false,
        ]);

        return $this->render('logistics', [ 
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'periodSummaryProvider' => $periodSummaryProvider,
            'productSummaryProvider' => $this->getProductSummary($dateFrom, $dateTo),
            'searchModel' => $searchModel,
        ]);
    }

    protected function getProductSummary($dateFrom, $dateTo)
    {
        $query = DetailByPeriod::find()->alias('t')
            ->select([
                't.nm_id',
                'card_name' => 'c.title',
                'items_count' => 'COUNT(DISTINCT t.srid)',
                'to_client_cancel' =>   'SUM(CASE WHEN t.bonus_type_name = "К клиенту при отмене"    THEN t.delivery_rub ELSE 0 END)',
                'from_client_cancel' => 'SUM(CASE WHEN t.bonus_type_name = "От клиента при отмене"   THEN t.delivery_rub ELSE 0 END)',
                'from_client_return' => 'SUM(CASE WHEN t.bonus_type_name = "От клиента при возврате" THEN t.delivery_rub ELSE 0 END)',
                'defect_return' => 'SUM(CASE WHEN t.bonus_type_name LIKE "%брак%" THEN t.delivery_rub ELSE 0 END)',
                'other_sum' => 'SUM(CASE WHEN t.bonus_type_name NOT IN ("К клиенту при отмене", "От клиента при отмене", "От клиента при возврате") AND t.bonus_type_name NOT LIKE "%брак%" THEN t.delivery_rub ELSE 0 END)',
                'total_product_sum' => 'SUM(t.delivery_rub)'
            ])
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = t.nm_id')
            ->where(['t.supplier_oper_name' => 'Логистика'])
            ->andWhere(['or', ['doc_type_name' => ''], ['doc_type_name' => null]])
            ->andWhere(['not', ['bonus_type_name' => 'К клиенту при продаже']])
            ->andWhere(['between', 't.rr_dt', $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->groupBy(['t.nm_id', 'c.title']);

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 50],
            'sort' => [
                'defaultOrder' => ['total_product_sum' => SORT_DESC],
                'attributes' => ['nm_id', 'card_name', 'items_count', 'total_product_sum']
            ],
        ]);
    }

    protected function getFilterLists($dateFrom, $dateTo, $nmID = null)
    {
        // 1. Создаем фундамент с "Логистикой" и датами
        $baseQuery = \app\models\DetailByPeriod::find()->alias('t')
            ->where(['t.supplier_oper_name' => 'Логистика'])
            ->andWhere(['or', ['t.doc_type_name' => ''], ['t.doc_type_name' => null]])
            ->andWhere(['not', ['t.bonus_type_name' => 'К клиенту при продаже']])
            ->andWhere(['between', 't.rr_dt', $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($nmID) {
            $baseQuery->andWhere(['t.nm_id' => $nmID]);
        }

        return [
            'offices' => \yii\helpers\ArrayHelper::map(
                (clone $baseQuery)
                    ->select('t.office_name')
                    ->distinct()
                    // ИСПОЛЬЗУЕМ andWhere, чтобы не стереть базовый запрос!
                    ->andWhere(['not', ['t.office_name' => null]])
                    ->andWhere(['not', ['t.office_name' => '']])
                    ->asArray()->all(),
                'office_name', 'office_name'
            ),
            'reasons' => \yii\helpers\ArrayHelper::map(
                (clone $baseQuery)
                    ->select('t.bonus_type_name')
                    ->distinct()
                    // ИСПОЛЬЗУЕМ andWhere!
                    ->andWhere(['not', ['t.bonus_type_name' => null]])
                    ->andWhere(['not', ['t.bonus_type_name' => '']])
                    ->asArray()->all(),
                'bonus_type_name', 'bonus_type_name'
            ),
        ];
    }

public function actionDetail()
{
    $request = Yii::$app->request;
    
    // Получаем массив параметров от виджета
    $filterParams = $request->get('DPFilterForm', []);
    
    // Извлекаем значения. Если их нет в массиве формы, пробуем взять напрямую из GET (на всякий случай)
    $nmID     = $filterParams['nmID'] ?? $request->get('nmID');
    $dateFrom = $filterParams['date_from'] ?? $request->get('date_from', date('Y-m-d', strtotime('-7 days')));
    $dateTo   = $filterParams['date_to'] ?? $request->get('date_to', date('Y-m-d'));

    // Подготовка текста для инициализации виджета (чтобы в строке поиска сразу было название)
    $initValueText = '';
    if ($nmID) {
        $card = \app\models\WbCard::findOne(['nmID' => $nmID]);
        $initValueText = $card ? $card->nmID . ' (' . $card->title . ')' : $nmID;
    }

    $searchModel = new DetailByPeriod();


    $dataProvider = $searchModel->searchPenaltyLogistics($request->getQueryParams());

    $dataProvider->query->where(['t.supplier_oper_name' => 'Логистика']);
    $dataProvider->query->andWhere(['or', ['t.doc_type_name' => ''], ['t.doc_type_name' => null]]);
    $dataProvider->query->andWhere(['not', ['t.bonus_type_name' => 'К клиенту при продаже']]);

    // ВАЖНО: используем andWhere, чтобы НЕ ЗАТЕРЕТЬ условия из searchPenaltyLogistics
    $dataProvider->query->andWhere(['between', 't.rr_dt', $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
    
    if ($nmID) {
        $dataProvider->query->andWhere(['t.nm_id' => $nmID]);
    }

    $gridFilters = $request->get('DetailByPeriod', []);
    
    if (!empty($gridFilters['office_name'])) {
        $dataProvider->query->andWhere(['t.office_name' => $gridFilters['office_name']]);
        $searchModel->office_name = $gridFilters['office_name']; // Чтобы значение осталось в Select2
    }
    
    if (!empty($gridFilters['bonus_type_name'])) {
        $dataProvider->query->andWhere(['t.bonus_type_name' => $gridFilters['bonus_type_name']]);
        $searchModel->bonus_type_name = $gridFilters['bonus_type_name']; // Чтобы значение осталось в Select2
    }

// --- БЛОК ОТЛАДКИ ---
    // 1. Получаем SQL запрос со всеми вставленными параметрами
    $finalSql = $dataProvider->query->createCommand()->rawSql;
    
    // 2. Получаем результат выполнения (первые 3 записи для проверки)
    $resultData = $dataProvider->getModels();
/*
     // Раскомментируй эти строки, чтобы увидеть результат на экране и остановить выполнение:
    echo "<h3>SQL Query:</h3><pre>" . $finalSql . "</pre>";
    echo "<h3>Data Sample:</h3><pre>"; 
    print_r(array_slice($resultData, 0, 3)); 
    echo "</pre>";
    die(); 
*/
    // --- КОНЕЦ БЛОКА ОТЛАДКИ ---

    // Получаем списки для фильтров (склады, причины)
    $filterLists = $this->getFilterLists($dateFrom, $dateTo, $nmID);

    return $this->render('detail', [
        'searchModel'   => $searchModel,
        'dataProvider'  => $dataProvider,
        'filterLists'   => $filterLists,
        'dateFrom'      => $dateFrom,
        'dateTo'        => $dateTo,
        'initValueText' => $initValueText,
    ]);
}

}