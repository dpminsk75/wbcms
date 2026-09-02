<?php

namespace app\controllers;

use Yii;
use app\models\WbOrder;
use app\models\WbOrderSearch;
use app\models\WbOrderFeedSearch;
use app\models\WbOrderFeedAggregatedSearch;
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

    public function actionFeedAggregated()
    {
        $filterModel = new DPFilterForm();
        $filterModel->load(Yii::$app->request->get());

        if (!$filterModel->date_from) {
            $filterModel->date_from = date('Y-m-d', strtotime('-6 days'));
        }
        if (!$filterModel->date_to) {
            $filterModel->date_to = date('Y-m-d');
        }

        $searchModel = new WbOrderFeedAggregatedSearch();
        $searchModel->load(Yii::$app->request->queryParams);
        $dataProvider = $searchModel->search([
            'nm_id' => $filterModel->nm_id,
            'date_from' => $filterModel->date_from,
            'date_to' => $filterModel->date_to,
        ]);

        // данные для воронки над таблицей (график + цифры) — отдельный блок
        $funnelStats = $searchModel->getFunnelStats();
        $chartData = $searchModel->getDailyStatusChartData();

        return $this->render('feed-aggregated', [
            'filterModel' => $filterModel,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'funnelStats' => $funnelStats,
            'chartData' => $chartData,
        ]);
    }

    /**
     * Тепловая карта заказов 7×24 — агрегация по дню недели и часу.
     * Данные — wb_order (час из поля date), фильтр — getDPWidget (nm_id + период).
     */
    public function actionHeatmap()
    {
        $params = \app\components\getDPWidget::getParams(14);
        $card = null;
        if (!empty($params['nm_id'])) {
            $card = \app\models\WbCard::findOne(['nmID' => $params['nm_id']]);
        }

        $dateFrom = $params['date_from'];
        $dateTo   = $params['date_to'];

        // 7×24 матрица: WEEKDAY(date) 0=Пн..6=Вс, HOUR(date) 0..23
        $q = (new \yii\db\Query())
            ->select([
                'wd'  => 'WEEKDAY(date)',
                'hr'  => 'HOUR(date)',
                'cnt' => 'COUNT(*)',
                'sum_price' => 'SUM(COALESCE(finished_price, price_with_disc, 0))',
                'cancel_cnt' => 'SUM(CASE WHEN is_cancel=1 THEN 1 ELSE 0 END)',
            ])
            ->from('wb_order')
            ->where(['between', 'date', $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->andFilterWhere(['nm_id' => $params['nm_id']])
            ->groupBy(['wd', 'hr']);
        if (Yii::$app->has('companyManager')) {
            Yii::$app->companyManager->applyToQuery($q, '');
        }
        $rows = $q->all();

        $matrix = array_fill(0, 7, array_fill(0, 24, ['cnt' => 0, 'sum' => 0, 'cancel_cnt' => 0]));
        $maxCnt = 0;
        $totalCnt = 0;
        $totalSum = 0;
        foreach ($rows as $r) {
            $wd = (int)$r['wd'];
            $hr = (int)$r['hr'];
            $cnt = (int)$r['cnt'];
            $sum = (float)$r['sum_price'];
            $canc = (int)($r['cancel_cnt'] ?? 0);
            $matrix[$wd][$hr] = ['cnt' => $cnt, 'sum' => $sum, 'cancel_cnt' => $canc];
            $maxCnt = max($maxCnt, $cnt);
            $totalCnt += $cnt;
            $totalSum += $sum;
        }

        // --- Рекомендации лучших окон (2-час скользящее, агрегируем все 7 дней) ---
        $windows = [];
        for ($h0 = 0; $h0 < 24; $h0++) {
            $h1 = ($h0 + 1) % 24;
            $cnt = 0; $sum = 0; $canc = 0;
            for ($wd = 0; $wd < 7; $wd++) {
                $cnt  += $matrix[$wd][$h0]['cnt']  + $matrix[$wd][$h1]['cnt'];
                $sum  += $matrix[$wd][$h0]['sum']  + $matrix[$wd][$h1]['sum'];
                $canc += $matrix[$wd][$h0]['cancel_cnt'] + $matrix[$wd][$h1]['cancel_cnt'];
            }
            // при 2-часе каждая ячейка 7×24 учтена дважды — это ок для ранжирования, доля считается от реального totalCnt
            // но cnt для окна = сумма двух часов по всем дням (как на скрине: 21-23 → 134)
            $windows[] = [
                'h0' => $h0, 'h1' => $h1,
                'label' => sprintf('%02d:00–%02d:00', $h0, ($h1+1)%24),
                'label_short' => sprintf('%02d:00–%02d:00', $h0, ($h1+1)%24),
                'cnt' => $cnt,
                'sum' => $sum,
                'avg' => $cnt > 0 ? $sum / $cnt : 0,
                'cancel_cnt' => $canc,
                'cancel_rate' => $cnt > 0 ? $canc / $cnt * 100 : 0,
                'share' => $totalCnt > 0 ? $cnt / $totalCnt * 100 : 0,
            ];
        }
        // фильтр шума: окна с <3 заказами не рассматриваем для avg/cancel
        $byVolume = $windows;
        usort($byVolume, fn($a,$b)=> $b['cnt'] <=> $a['cnt']);
        $byAvg = array_filter($windows, fn($w)=> $w['cnt'] >= 5);
        if (empty($byAvg)) $byAvg = $windows;
        usort($byAvg, fn($a,$b)=> $b['avg'] <=> $a['avg']);
        $byReli = array_filter($windows, fn($w)=> $w['cnt'] >= 5);
        if (empty($byReli)) $byReli = $windows;
        usort($byReli, fn($a,$b)=> $a['cancel_rate'] <=> $b['cancel_rate']);

        $recommend = [
            'byVolume' => ['best' => $byVolume[0] ?? null, 'top3' => array_slice($byVolume,0,3)],
            'byAvg'    => ['best' => $byAvg[0] ?? null, 'top3' => array_slice($byAvg,0,3)],
            'byReli'   => ['best' => $byReli[0] ?? null, 'top3' => array_slice($byReli,0,3)],
        ];

        return $this->render('heatmap', [
            'card' => $card,
            'matrix' => $matrix,
            'maxCnt' => $maxCnt,
            'totalCnt' => $totalCnt,
            'totalSum' => $totalSum,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'params'   => $params,
            'recommend' => $recommend,
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