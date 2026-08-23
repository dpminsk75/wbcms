<?php
namespace app\controllers;

use Yii;
use DateTime;
use yii\web\Controller;
use yii\data\ActiveDataProvider;
use yii\db\Query;

class UnclaimedOrdersController extends Controller
{
    public function actionIndex()
    {

        $request = Yii::$app->request;

        $today = new DateTime('today');
        $firstDayLastMonth = (new DateTime('first day of last month'));
        $thirtyDaysAgo = (new DateTime('-30 days'));
        $defaultFrom = min($firstDayLastMonth, $thirtyDaysAgo)->format('Y-m-d');
        $defaultTo = (new DateTime('-14 days'))->format('Y-m-d');

        $dateFrom = $request->get('date_from', $defaultFrom);
        $dateTo   = $request->get('date_to', $defaultTo);
//        $dateFrom  = $request->get('date_from', date('Y-m-01'));  // Первое число текущего месяца
//        $dateTo    = $request->get('date_to', date('Y-m-d'));     // Сегодня
        $percent   = (float)$request->get('percent', 20);         // Порог % (20)
        $minOrders = (int)$request->get('min_orders', 5);         // Мин. заказов (5)

        $rateThreshold = $percent / 100;

        $query = (new Query())
            ->select([
                'o.nm_id',
                'card_name' => 'c.title', // Используем title из wbcards
                'c.vendorCode',
                'alls'      => 'SUM(o.is_realization)',
                'sLO'       => 'SUM(o.finished_price)',
                'cancel'    => 'SUM(o.is_cancel)',
                'notb'      => 'SUM(CASE WHEN s.saleID IS NULL THEN 1 ELSE 0 END)',
                'bought'    => 'SUM(CASE WHEN s.saleID IS NULL THEN 0 ELSE 1 END)',
                'sum_price' => 'SUM(s.finishedPrice)',
                'sFP'       => 'SUM(s.forPay)',
                'rate'      => '(SUM(o.is_cancel) / NULLIF(SUM(o.is_realization), 0))'
            ])
            ->from(['o' => 'wb_order'])
            ->innerJoin(['c' => 'wbcards'], 'o.nm_id = c.nmID') // Связь по nmID
            ->leftJoin(['s' => 'wb_sales'], 'o.srid = s.srid')
            ->where(['between', 'o.date', $dateFrom, $dateTo])
            ->groupBy(['o.nm_id', 'c.title', 'c.vendorCode'])
            // Фильтруем "проблемные" товары прямо в запросе
            ->having(['>', 'SUM(o.is_cancel)', 1])
            ->andHaving(['>', 'SUM(o.is_realization)', $minOrders])
            ->andHaving(['>', '(SUM(o.is_cancel) / NULLIF(SUM(o.is_realization), 0))', $rateThreshold]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 50,
            ],
            'sort' => [
                'attributes' => [
                    'nm_id',
                    'card_name',
                    'vendorCode',
                    'rate' => [
                        'asc' => ['rate' => SORT_ASC],
                        'desc' => ['rate' => SORT_DESC],
                    ],
                    'alls',
                    'cancel',
                ],
                'defaultOrder' => ['rate' => SORT_DESC],
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'params' => [
                'date_from'  => $dateFrom,
                'date_to'    => $dateTo,
                'percent'    => $percent,
                'min_orders' => $minOrders,
            ],
        ]);
    }
}