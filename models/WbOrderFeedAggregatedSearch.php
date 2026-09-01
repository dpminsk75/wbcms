<?php

namespace app\models;

use yii\data\ActiveDataProvider;
use Yii;

/**
 * Поисковая модель "Сводки по товарам" (агрегированный аналог ленты заказов).
 *
 * Группирует wb_order по nm_id за период и считает по каждому товару:
 * количество заказов, отмены, средние цены/скидку/СПП, суммы и средние
 * по "цене со скидкой"/"цене продажи", комиссию/эквайринг/логистику
 * (факт, а где факта нет — прогноз из detail_by_period_forecast, как в ленте).
 *
 * Джойны повторяют WbOrderFeedSearch: wbcards (карточка) и
 * detail_by_period_forecast (прогнозные % комиссии/эквайринга и логистика).
 */
class WbOrderFeedAggregatedSearch extends WbOrder
{
    public $date_from;
    public $date_to;

    /** Сортировка: 'count' — по количеству заказов, 'sum' — по общей сумме */
    public $sort_by = 'count';

    public function rules()
    {
        return [
            [['date_from', 'date_to'], 'safe'],
            [['nm_id'], 'integer'],
            [['sort_by'], 'in', 'range' => ['count', 'sum']],
        ];
    }

    public static function getSortOptions()
    {
        return [
            'count' => 'По количеству',
            'sum' => 'По сумме',
        ];
    }

    /**
     * Сводка по ВСЕМ заказам под текущими фильтрами (период + артикул + компания) —
     * те же цифры, что в ленте: количество, сумма, средний СПП, разбивка FBS/FBO.
     * Используется для 4 карточек над таблицей.
     *
     * @return array{count:int, sum:float, avg_spp:float, fbs_count:int, fbo_count:int}
     */
    public function getSummaryStats()
    {
        $query = WbOrder::find()->alias('o');
        $query->andFilterWhere(['o.nm_id' => $this->nm_id]);
        if (!empty($this->date_from)) {
            $query->andWhere(['>=', 'o.date', $this->date_from . ' 00:00:00']);
        }
        if (!empty($this->date_to)) {
            $query->andWhere(['<=', 'o.date', $this->date_to . ' 23:59:59']);
        }
        if (Yii::$app->has('companyManager')) {
            Yii::$app->companyManager->applyToQuery($query, 'o');
        }

        $row = $query
            ->select([
                'COUNT(*) AS cnt',
                'SUM(o.price_with_disc) AS sum_price_with_disc',
                'AVG(o.spp) AS avg_spp',
                "SUM(CASE WHEN o.warehouse_type = 'Склад продавца' THEN 1 ELSE 0 END) AS fbs_count",
                "SUM(CASE WHEN o.warehouse_type <> 'Склад продавца' OR o.warehouse_type IS NULL THEN 1 ELSE 0 END) AS fbo_count",
            ])
            ->asArray()
            ->one();

        return [
            'count' => (int)($row['cnt'] ?? 0),
            'sum' => (float)($row['sum_price_with_disc'] ?? 0),
            'avg_spp' => (float)($row['avg_spp'] ?? 0),
            'fbs_count' => (int)($row['fbs_count'] ?? 0),
            'fbo_count' => (int)($row['fbo_count'] ?? 0),
        ];
    }

    /**
     * Воронка заказов — те же 4+1 категории что в кабинете WB и в WbController::actionDetail:
     *   bought (выкуплен, s.saleID NOT LIKE 'R%'), delivery (в доставке, не отменён и нет продажи),
     *   canceled (is_cancel=1), returns (s.saleID LIKE 'R%').
     * Возвращает counts/sums/проценты и общий процент выкупа.
     *
     * sums: total/delivery/cancel — по o.finished_price (fallback price_with_disc), bought/returns — по s.finishedPrice.
     */
    public function getFunnelStats(): array
    {
        // Важно: wb_sales.srid не уникален — у одного заказа может быть и S-sale и R-return.
        // LEFT JOIN без дедупликации раздувает COUNT(*) (см. скрин 4564 vs 4572). Используем EXISTS
        // и скалярные подзапросы по индексу idx_srid, чтобы каждый заказ считался ровно один раз
        // с приоритетом: returns (R) > bought (S) > delivery/cancel.
        $q = WbOrder::find()->alias('o')
            ->andFilterWhere(['o.nm_id' => $this->nm_id]);
        if (!empty($this->date_from)) {
            $q->andWhere(['>=', 'o.date', $this->date_from . ' 00:00:00']);
        }
        if (!empty($this->date_to)) {
            $q->andWhere(['<=', 'o.date', $this->date_to . ' 23:59:59']);
        }
        if (Yii::$app->has('companyManager')) {
            Yii::$app->companyManager->applyToQuery($q, 'o');
        }

        $row = $q->select([
            'total_cnt'    => 'COUNT(*)',
            'total_sum'    => 'SUM(COALESCE(o.finished_price, o.price_with_disc, 0))',
            // bought = есть S-sale и нет R-return
            'bought_cnt'   => "SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM wb_sales sr WHERE sr.srid=o.srid AND sr.saleID LIKE 'R%') AND EXISTS (SELECT 1 FROM wb_sales ss WHERE ss.srid=o.srid AND ss.saleID NOT LIKE 'R%') THEN 1 ELSE 0 END)",
            'bought_sum'   => "SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM wb_sales sr WHERE sr.srid=o.srid AND sr.saleID LIKE 'R%') AND EXISTS (SELECT 1 FROM wb_sales ss WHERE ss.srid=o.srid AND ss.saleID NOT LIKE 'R%') THEN COALESCE((SELECT COALESCE(ss.finishedPrice, ss.priceWithDisc,0) FROM wb_sales ss WHERE ss.srid=o.srid AND ss.saleID NOT LIKE 'R%' LIMIT 1),0) ELSE 0 END)",
            'delivery_cnt' => "SUM(CASE WHEN o.is_cancel = 0 AND NOT EXISTS (SELECT 1 FROM wb_sales sd WHERE sd.srid=o.srid) THEN 1 ELSE 0 END)",
            'delivery_sum' => "SUM(CASE WHEN o.is_cancel = 0 AND NOT EXISTS (SELECT 1 FROM wb_sales sd WHERE sd.srid=o.srid) THEN COALESCE(o.finished_price, o.price_with_disc, 0) ELSE 0 END)",
            'cancel_cnt'   => "SUM(CASE WHEN o.is_cancel = 1 THEN 1 ELSE 0 END)",
            'cancel_sum'   => "SUM(CASE WHEN o.is_cancel = 1 THEN COALESCE(o.finished_price, o.price_with_disc, 0) ELSE 0 END)",
            'returns_cnt'  => "SUM(CASE WHEN EXISTS (SELECT 1 FROM wb_sales sr WHERE sr.srid=o.srid AND sr.saleID LIKE 'R%') THEN 1 ELSE 0 END)",
            'returns_sum'  => "SUM(COALESCE((SELECT COALESCE(sr.finishedPrice, sr.priceWithDisc,0) FROM wb_sales sr WHERE sr.srid=o.srid AND sr.saleID LIKE 'R%' LIMIT 1),0))",
        ])->asArray()->one();

        $totalCnt = (int)($row['total_cnt'] ?? 0);
        $boughtCnt = (int)($row['bought_cnt'] ?? 0);
        $deliveryCnt = (int)($row['delivery_cnt'] ?? 0);
        $cancelCnt = (int)($row['cancel_cnt'] ?? 0);
        $returnsCnt = (int)($row['returns_cnt'] ?? 0);

        $totalSum = (float)($row['total_sum'] ?? 0);
        $boughtSum = (float)($row['bought_sum'] ?? 0);
        $deliverySum = (float)($row['delivery_sum'] ?? 0);
        $cancelSum = (float)($row['cancel_sum'] ?? 0);
        $returnsSum = (float)($row['returns_sum'] ?? 0);

        $pct = function ($part, $whole) {
            return $whole > 0 ? round($part / $whole * 100, 2) : 0;
        };

        // проценты долей от общего числа заказов (для карточек и прогресс-бара)
        $boughtPct   = $pct($boughtCnt, $totalCnt);
        $deliveryPct = $pct($deliveryCnt, $totalCnt);
        $cancelPct   = $pct($cancelCnt, $totalCnt);
        $returnsPct  = $pct($returnsCnt, $totalCnt);

        // процент выкупа: выкупленные / (выкупленные + отменённые + возвраты)
        // если возвратов нет — классика bought/(bought+cancel); с возвратами — как на скрине WB
        $buyoutDenom = $boughtCnt + $cancelCnt + $returnsCnt;
        $buyoutPct = $buyoutDenom > 0 ? round($boughtCnt / $buyoutDenom * 100, 2) : 0;

        return [
            'total_cnt' => $totalCnt, 'total_sum' => $totalSum,
            'bought_cnt' => $boughtCnt, 'bought_sum' => $boughtSum, 'bought_pct' => $boughtPct,
            'delivery_cnt' => $deliveryCnt, 'delivery_sum' => $deliverySum, 'delivery_pct' => $deliveryPct,
            'cancel_cnt' => $cancelCnt, 'cancel_sum' => $cancelSum, 'cancel_pct' => $cancelPct,
            'returns_cnt' => $returnsCnt, 'returns_sum' => $returnsSum, 'returns_pct' => $returnsPct,
            'buyout_pct' => $buyoutPct,
        ];
    }

    /**
     * Ежедневная разбивка по статусам для stacked-Bar графика.
     * Возвращает массив строк: ['date' => 'Y-m-d', 'bought_cnt'=>int, 'delivery_cnt'=>int, 'cancel_cnt'=>int, 'returns_cnt'=>int, 'total_cnt'=>int, ... sums]
     * Пустые даты между date_from и date_to заполняются нулями.
     */
    public function getDailyStatusChartData(): array
    {
        if (empty($this->date_from) || empty($this->date_to)) {
            return [];
        }

        $q = WbOrder::find()->alias('o')
            ->andFilterWhere(['o.nm_id' => $this->nm_id])
            ->andWhere(['>=', 'o.date', $this->date_from . ' 00:00:00'])
            ->andWhere(['<=', 'o.date', $this->date_to . ' 23:59:59']);
        if (Yii::$app->has('companyManager')) {
            Yii::$app->companyManager->applyToQuery($q, 'o');
        }

        $raw = $q->select([
            'd'            => 'DATE(o.date)',
            'total_cnt'    => 'COUNT(*)',
            'bought_cnt'   => "SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM wb_sales sr WHERE sr.srid=o.srid AND sr.saleID LIKE 'R%') AND EXISTS (SELECT 1 FROM wb_sales ss WHERE ss.srid=o.srid AND ss.saleID NOT LIKE 'R%') THEN 1 ELSE 0 END)",
            'delivery_cnt' => "SUM(CASE WHEN o.is_cancel = 0 AND NOT EXISTS (SELECT 1 FROM wb_sales sd WHERE sd.srid=o.srid) THEN 1 ELSE 0 END)",
            'cancel_cnt'   => "SUM(CASE WHEN o.is_cancel = 1 THEN 1 ELSE 0 END)",
            'returns_cnt'  => "SUM(CASE WHEN EXISTS (SELECT 1 FROM wb_sales sr WHERE sr.srid=o.srid AND sr.saleID LIKE 'R%') THEN 1 ELSE 0 END)",
            'total_sum'    => 'SUM(COALESCE(o.finished_price, o.price_with_disc, 0))',
            'bought_sum'   => "SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM wb_sales sr WHERE sr.srid=o.srid AND sr.saleID LIKE 'R%') AND EXISTS (SELECT 1 FROM wb_sales ss WHERE ss.srid=o.srid AND ss.saleID NOT LIKE 'R%') THEN COALESCE((SELECT COALESCE(ss.finishedPrice, ss.priceWithDisc,0) FROM wb_sales ss WHERE ss.srid=o.srid AND ss.saleID NOT LIKE 'R%' LIMIT 1),0) ELSE 0 END)",
            'delivery_sum' => "SUM(CASE WHEN o.is_cancel = 0 AND NOT EXISTS (SELECT 1 FROM wb_sales sd WHERE sd.srid=o.srid) THEN COALESCE(o.finished_price, o.price_with_disc, 0) ELSE 0 END)",
            'cancel_sum'   => "SUM(CASE WHEN o.is_cancel = 1 THEN COALESCE(o.finished_price, o.price_with_disc, 0) ELSE 0 END)",
            'returns_sum'  => "SUM(COALESCE((SELECT COALESCE(sr.finishedPrice, sr.priceWithDisc,0) FROM wb_sales sr WHERE sr.srid=o.srid AND sr.saleID LIKE 'R%' LIMIT 1),0))",
        ])->groupBy('d')->orderBy(['d' => SORT_ASC])->asArray()->all();

        $byDate = [];
        foreach ($raw as $r) {
            $byDate[$r['d']] = $r;
        }

        $result = [];
        $cur = strtotime($this->date_from);
        $end = strtotime($this->date_to);
        while ($cur <= $end) {
            $dStr = date('Y-m-d', $cur);
            if (isset($byDate[$dStr])) {
                $row = $byDate[$dStr];
                $result[] = [
                    'date' => $dStr,
                    'total_cnt' => (int)($row['total_cnt'] ?? 0),
                    'bought_cnt' => (int)($row['bought_cnt'] ?? 0),
                    'delivery_cnt' => (int)($row['delivery_cnt'] ?? 0),
                    'cancel_cnt' => (int)($row['cancel_cnt'] ?? 0),
                    'returns_cnt' => (int)($row['returns_cnt'] ?? 0),
                    'total_sum' => (float)($row['total_sum'] ?? 0),
                    'bought_sum' => (float)($row['bought_sum'] ?? 0),
                    'delivery_sum' => (float)($row['delivery_sum'] ?? 0),
                    'cancel_sum' => (float)($row['cancel_sum'] ?? 0),
                    'returns_sum' => (float)($row['returns_sum'] ?? 0),
                ];
            } else {
                $result[] = [
                    'date' => $dStr,
                    'total_cnt' => 0, 'bought_cnt' => 0, 'delivery_cnt' => 0, 'cancel_cnt' => 0, 'returns_cnt' => 0,
                    'total_sum' => 0, 'bought_sum' => 0, 'delivery_sum' => 0, 'cancel_sum' => 0, 'returns_sum' => 0,
                ];
            }
            $cur = strtotime('+1 day', $cur);
        }

        return $result;
    }

    /**
     * @param array $params ['nm_id' => int|null, 'date_from' => 'Y-m-d', 'date_to' => 'Y-m-d']
     */
    public function search($params)
    {
        $this->nm_id = $params['nm_id'] ?? null;
        $this->date_from = $params['date_from'] ?? null;
        $this->date_to = $params['date_to'] ?? null;

        $forecastBase = 'COALESCE(o.price_with_disc, o.finished_price, 0)';

        $query = WbOrder::find()
            ->alias('o')
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = o.nm_id')
            // Прогноз по сегменту (склад/тип + регион + категория) за последние
            // 10 дней — тот же подход, что в ленте заказов (см. WbOrderFeedSearch).
            ->leftJoin(
                ['fcst' => "(
                    SELECT
                        company_id, warehouse_type, warehouse_name, region_name, category,
                        SUM(sum_sales_commission) / NULLIF(SUM(sum_retail_amount), 0) AS forecast_commission_pct,
                        SUM(sum_delivery_rub) / NULLIF(SUM(orders_count), 0) AS forecast_delivery_rub,
                        SUM(sum_acquiring_fee) / NULLIF(SUM(sum_retail_amount), 0) AS forecast_acquiring_pct
                    FROM detail_by_period_forecast
                    WHERE stat_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 10 DAY) AND DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                    GROUP BY company_id, warehouse_type, warehouse_name, region_name, category
                )"],
                'fcst.company_id = o.company_id
                 AND fcst.warehouse_type = o.warehouse_type
                 AND fcst.warehouse_name = o.warehouse_name
                 AND fcst.region_name = o.region_name
                 AND fcst.category = o.category'
            )
            ->select([
                'nm_id' => 'o.nm_id',
                'card_title' => 'c.title',
                'card_vendor_code' => 'c.vendorCode',
                'card_subject_name' => 'c.subjectName',
                'card_brand' => 'c.brand',
                'card_photos' => 'c.photos',

                'orders_cnt' => 'COUNT(*)',
                'cancelled_cnt' => 'SUM(CASE WHEN o.is_cancel > 0 THEN 1 ELSE 0 END)',

                'avg_total_price' => 'AVG(o.total_price)',
                'avg_discount' => 'AVG(o.discount_percent)',

                'sum_price_with_disc' => 'SUM(o.price_with_disc)',
                'avg_price_with_disc' => 'AVG(o.price_with_disc)',

                // СПП считаем только там, где она задана (ненулевая)
                'avg_spp' => 'AVG(CASE WHEN o.spp > 0 THEN o.spp END)',

                'sum_finished' => 'SUM(o.finished_price)',
                // Цена продажи — тоже только где задана (AVG сам игнорирует NULL)
                'avg_finished' => 'AVG(o.finished_price)',

                // Комиссия/эквайринг: факт, а где факта нет — прогноз по сегменту
                // Для вывода "сумма сверху / процент снизу" нужны оба
                'sum_commission' => "SUM(COALESCE(o.commission_fee, ROUND(fcst.forecast_commission_pct * {$forecastBase}, 2)))",
                'avg_commission' => "AVG(COALESCE(o.commission_fee, ROUND(fcst.forecast_commission_pct * {$forecastBase}, 2)))",
                'avg_commission_pct' => "AVG(COALESCE(o.commission_percent, fcst.forecast_commission_pct*100))",
                'sum_acquiring' => "SUM(COALESCE(o.acquiring_fee, ROUND(fcst.forecast_acquiring_pct * {$forecastBase}, 2)))",
                'avg_acquiring' => "AVG(COALESCE(o.acquiring_fee, ROUND(fcst.forecast_acquiring_pct * {$forecastBase}, 2)))",
                'avg_acquiring_pct' => "AVG(COALESCE(o.acquiring_percent, fcst.forecast_acquiring_pct*100))",

                // Логистика: и сумма, и средняя — факт + прогноз (расчётные — курсивом)
                'sum_delivery' => "SUM(COALESCE(o.delivery_rub, fcst.forecast_delivery_rub))",
                'avg_delivery' => "AVG(COALESCE(o.delivery_rub, fcst.forecast_delivery_rub))",
            ])
            ->groupBy('o.nm_id')
            ->asArray();

        $query->andFilterWhere(['o.nm_id' => $this->nm_id]);

        if (!empty($this->date_from)) {
            $query->andWhere(['>=', 'o.date', $this->date_from . ' 00:00:00']);
        }
        if (!empty($this->date_to)) {
            $query->andWhere(['<=', 'o.date', $this->date_to . ' 23:59:59']);
        }

        // Скоуп компании: выбрана конкретная — только она, глобальный режим — все
        if (Yii::$app->has('companyManager')) {
            Yii::$app->companyManager->applyToQuery($query, 'o');
        }

        if ($this->sort_by === 'count') {
            $query->orderBy(['orders_cnt' => SORT_DESC]);
        } else {
            $query->orderBy(['sum_price_with_disc' => SORT_DESC]);
        }

        return new ActiveDataProvider([
            'query' => $query,
            // Строки — агрегаты без PK "id": ключом делаем nm_id,
            // иначе ActiveDataProvider падает на Undefined key "id"
            'key' => 'nm_id',
            'pagination' => ['pageSize' => 50],
        ]);
    }
}
