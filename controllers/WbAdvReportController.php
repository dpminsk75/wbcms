<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;

use app\models\WbCampaign;
use app\models\WbCampaignItems; // Проверь название модели (item или items)
use app\models\WbCampaignStats;
use app\models\WbCampaignQuery;


class WbAdvReportController extends Controller
{

public function actionIndex($id = null, $dateFrom = null, $dateTo = null)
{

    $dateFrom = Yii::$app->request->get('date_from');
    $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-14 days'));
    $dateTo = Yii::$app->request->get('date_to');
    $dateTo = $dateTo ?: date('Y-m-d');

/*
    // Если даты не пришли, ставим дефолт (последние 14 дней)
    if (!$dateFrom) {
        $dateFrom = date('Y-m-d', strtotime('-14 days'));
    }
    if (!$dateTo) {
        $dateTo = date('Y-m-d');
    }
*/
    $campaign = null;
    $items = [];
    $stats = [];
    $queries = [];
    $ShortStats = [];
    $ChartStats = [];
    $AnotherGoodsStats = [];
    $ChartAppStats = [];

    if ($id) {
        $campaign = WbCampaign::findOne(['campaign_id' => $id]);
        
        if ($campaign) {
            // Запросы теперь используют прямые переменные $dateFrom и $dateTo
            $items = WbCampaignItems::find()
                ->alias('i')
                ->select(['i.*', 'c.title as card_name', 'c.brand', 'c.vendorCode'])
                ->leftJoin(['c' => 'wbcards'], 'c.nmID = i.nm_id')
                ->where(['i.campaign_id' => $id])
                ->asArray()
                ->all();

            $currentNmIds = \app\models\WbCampaignItems::find()
                ->select('nm_id')
                ->where(['campaign_id' => $id])
                ->column();

            $stats = (new \yii\db\Query())
                ->select([
                    'c.campaign_id',
                    'i.nm_id',
                    's.date',
                    'w.title',
                    'w.vendorCode',
                    'views'     => 'SUM(n.views)',
                    'clicks'    => 'SUM(n.clicks)',
                    'atbs'      => 'SUM(n.atbs)',
                    'orders'    => 'SUM(n.orders)',
                    'shks'      => 'SUM(n.shks)',
                    'sum'       => 'SUM(n.sum)',
                    'sum_price' => 'SUM(n.sum_price)',
                    'canceled'  => 'SUM(n.canceled)',
                ])
                ->from(['c' => 'wb_campaign'])
                ->innerJoin(['i' => 'wb_campaign_item'], 'c.campaign_id = i.campaign_id') // Учтено название из ваших инструкций
                ->innerJoin(['s' => 'wb_campaign_stats'], 'c.campaign_id = s.campaign_id')
                ->innerJoin(['n' => 'wb_campaign_stats_nms'], 's.id = n.parent_id AND i.nm_id = n.nm_id')
                ->innerJoin(['w' => 'wbcards'], 'n.nm_id = w.nmID') // Учтено c.nmID из инструкций
                ->where(['c.campaign_id' => $id])
                ->andWhere(['between', 's.date', $dateFrom, $dateTo])
                ->groupBy(['c.campaign_id', 'i.nm_id', 's.date', 'w.title', 'w.vendorCode'])
                ->orderBy(['s.date' => SORT_DESC])
                ->all();

            $ShortStats = (new \yii\db\Query())
                ->select([
                    'c.campaign_id',
                    'i.nm_id',
                    'w.title',
                    'w.vendorCode',
                    'views'     => 'SUM(n.views)',
                    'clicks'    => 'SUM(n.clicks)',
                    'atbs'      => 'SUM(n.atbs)',
                    'orders'    => 'SUM(n.orders)',
                    'shks'      => 'SUM(n.shks)',
                    'sum'       => 'SUM(n.sum)',
                    'sum_price' => 'SUM(n.sum_price)',
                    'canceled'  => 'SUM(n.canceled)',
                ])
                ->from(['c' => 'wb_campaign'])
                ->innerJoin(['i' => 'wb_campaign_item'], 'c.campaign_id = i.campaign_id') 
                ->innerJoin(['s' => 'wb_campaign_stats'], 'c.campaign_id = s.campaign_id')
                ->innerJoin(['n' => 'wb_campaign_stats_nms'], 's.id = n.parent_id AND i.nm_id = n.nm_id')
                ->innerJoin(['w' => 'wbcards'], 'n.nm_id = w.nmID') 
                ->where(['c.campaign_id' => $id])
                ->andWhere(['between', 's.date', $dateFrom, $dateTo])
                ->groupBy(['c.campaign_id', 'i.nm_id', 'w.title', 'w.vendorCode'])
                ->orderBy(['i.nm_id' => SORT_ASC])
                ->all();


            $AnotherGoodsStats = (new \yii\db\Query())
                ->select([
                    'campaign_id' => 'c.campaign_id',
                    'nm_id'       => 'n.nm_id', 
                    'title'       => 'w.title',
                    'vendorCode'  => 'w.vendorCode',
                    'views'       => 'SUM(n.views)',
                    'clicks'      => 'SUM(n.clicks)',
                    'atbs'        => 'SUM(n.atbs)',
                    'orders'      => 'SUM(n.orders)',
                    'shks'        => 'SUM(n.shks)',
                    'sum'         => 'SUM(n.sum)',
                    'sum_price'   => 'SUM(n.sum_price)',
                    'canceled'    => 'SUM(n.canceled)',
                ])
                ->from(['c' => 'wb_campaign'])
                ->innerJoin(['s' => 'wb_campaign_stats'], 'c.campaign_id = s.campaign_id')
                ->innerJoin(['n' => 'wb_campaign_stats_nms'], 's.id = n.parent_id')
                ->leftJoin(['i' => 'wb_campaign_item'], 'c.campaign_id = i.campaign_id AND n.nm_id = i.nm_id')
                ->innerJoin(['w' => 'wbcards'], 'n.nm_id = w.nmID')
                ->where(['c.campaign_id' => $id])
                ->andWhere(['between', 's.date', $dateFrom, $dateTo])
                ->andWhere(['i.nm_id' => null]) // Только те, что отсутствуют в wb_campaign_item
                ->andWhere(['>', 'n.orders', 0]) // Только с заказами
                ->groupBy(['c.campaign_id', 'n.nm_id', 'w.title', 'w.vendorCode'])
                ->orderBy(['SUM(n.orders)' => SORT_DESC, 'n.nm_id' => SORT_ASC ])
                ->all();

            $ChartStats = (new \yii\db\Query())
                ->select([
                    'odate'     => 's.date',
                    'views'     => 'SUM(n.views)',
                    'clicks'    => 'SUM(n.clicks)',
                    'atbs'      => 'SUM(n.atbs)',
                    'orders'    => 'SUM(n.orders)',
                    'shks'      => 'SUM(n.shks)',
                    'sum'       => 'SUM(n.sum)',
                    'sum_price' => 'SUM(n.sum_price)',
                    'canceled'  => 'SUM(n.canceled)',

                    // Расчетные метрики с защитой от деления на 0
                    'CPM' => 'SUM(n.sum) / NULLIF(SUM(n.views), 0) * 1000',
                    'CPC' => 'SUM(n.sum) / NULLIF(SUM(n.clicks), 0)',
                    'CPO' => 'SUM(n.sum) / NULLIF(SUM(n.orders), 0)',
                    'CTR' => 'SUM(n.clicks) / NULLIF(SUM(n.views), 0) * 100',
                    'CR'  => 'SUM(n.atbs) / NULLIF(SUM(n.clicks), 0) * 100',

                ])
                ->from(['c' => 'wb_campaign'])
                ->innerJoin(['i' => 'wb_campaign_item'], 'c.campaign_id = i.campaign_id') // Учтено название из ваших инструкций
                ->innerJoin(['s' => 'wb_campaign_stats'], 'c.campaign_id = s.campaign_id')
                ->innerJoin(['n' => 'wb_campaign_stats_nms'], 's.id = n.parent_id AND i.nm_id = n.nm_id')
                ->innerJoin(['w' => 'wbcards'], 'n.nm_id = w.nmID') // Учтено c.nmID из инструкций
                ->where(['c.campaign_id' => $id])
                ->andWhere(['between', 's.date', $dateFrom, $dateTo])
                ->groupBy(['s.date'])
                ->orderBy(['s.date' => SORT_ASC])
                ->all();

            $ChartAppStats = (new \yii\db\Query())
                ->select([
                    'app_type', 
                    'date', 
                    'sum(views) as views', 
                    'sum(clicks) as clicks', 
                    'sum(atbs) as atbs', 
                    'sum(orders) as orders', 
                    'sum(sum_price) as sum_price'
                ])
                ->from(['wb_campaign_stats'])
                ->where(['campaign_id' => $id])
                ->andWhere(['between', 'date', $dateFrom, $dateTo])
                ->groupBy(['date', 'app_type'])
                ->orderBy('date asc, app_type desc')
                ->all();

            $queries = WbCampaignQuery::find()
                ->where(['campaign_id' => $id])
                ->andWhere(['between', 'date', $dateFrom, $dateTo])
                ->orderBy(['date' => SORT_DESC])
                ->all();
        }
    }

    // Подготовка списка для Select2
    $statusMap = [
        -1 => ['label' => 'Удалена', 'class' => 'label-default'],
        4  => ['label' => 'Готова', 'class' => 'label-info'],
        7  => ['label' => 'Завершена', 'class' => 'label-primary'],
        8  => ['label' => 'Отклонена', 'class' => 'label-danger'],
        9  => ['label' => 'Активна', 'class' => 'label-success'],
        11 => ['label' => 'Пауза', 'class' => 'label-warning'],
    ];

    $statusPriority = [9, 11, 7, 4, 8, -1];

    $activeCampaignIds = \app\models\WbCampaignStats::find()
        ->select('campaign_id')
        ->distinct()
        ->column();

    $campaignList = \yii\helpers\ArrayHelper::map(
        \app\models\WbCampaign::find()
            ->select(['campaign_id', 'name', 'status'])
            ->where(['in', 'campaign_id', $activeCampaignIds])
            // Сортировка по приоритету статуса, затем по алфавиту
            ->orderBy([
                new \yii\db\Expression('FIELD(status, ' . implode(',', $statusPriority) . ')'),
                'name' => SORT_ASC
            ])
            ->all(),
        'campaign_id',
        function($model) use ($statusMap) {
            $statusName = $statusMap[$model->status]['label'] ?? '???';
            return "({$statusName}) — {$model->name} [ID: {$model->campaign_id}]";
        }
    );

$statsProvider = new ArrayDataProvider([
    'allModels' => $stats,
    'sort' => [
        'attributes' => ['date', 'views', 'clicks', 'sum', 'orders'],
        'defaultOrder' => ['date' => SORT_DESC],
    ],
    'pagination' => ['pageSize' => 50], // Обычно для статистики за период пагинация не нужна
]);

$ShortStatsProvider = new ArrayDataProvider([
    'allModels' => $ShortStats,
]);


$AnotherGoodsProvider = new ArrayDataProvider([
    'allModels' => $AnotherGoodsStats,
    'pagination' => [
            'pageSize' => 10,
        ],
]);



$queriesProvider = new ArrayDataProvider([
    'allModels' => $queries,
    'sort' => [
        'attributes' => ['date', 'query', 'clicks', 'sum', 'orders', 'views', 'atbs',],
        'defaultOrder' => [
            'date' => SORT_DESC,    // Сначала свежие даты
            'orders' => SORT_DESC,  // Внутри даты — сначала те, где больше заказов
            'views' => SORT_DESC,   // Затем по показам
            'clicks' => SORT_DESC,  // Затем по кликам
        ],
    ],
    'pagination' => ['pageSize' => 50],

]);


    return $this->render('index', [
        'campaignList' => $campaignList,
        'campaign' => $campaign,

        'statsProvider' => $statsProvider,
        'ShortStatsProvider' => $ShortStatsProvider,
        'queriesProvider' => $queriesProvider,
        'AnotherGoodsProvider' => $AnotherGoodsProvider,
        'ChartStats' => $ChartStats,
        'ChartAppStats' => $ChartAppStats,
        'items' => $items,
        'stats' => $stats,
        'queries' => $queries,
        'id' => $id,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'statusMap' => $statusMap // прокидываем мапу во View для лейблов
    ]);
}

















}