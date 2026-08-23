<?php

namespace app\models;

use yii\data\ActiveDataProvider;

/**
 * Поисковая модель для "Ленты заказов".
 *
 * Джойнит wb_order с:
 *  - wbcards (карточка товара — фото, название, категория, бренд)
 *  - wb_orders_fbs + wb_orders_fbs_statuses (последний известный статус
 *    сборочного задания — актуально только для FBS-заказов, где
 *    warehouse_type = 'Склад продавца')
 *  - detail_by_period_forecast (прогнозные % комиссии/эквайринга и средняя
 *    стоимость логистики по сегменту склад+регион+категория — используется
 *    во вьюхе для заказов без фактов)
 *
 * ВАЖНО: сознательно НЕ используем LATERAL JOIN (были проблемы на боевом
 * MySQL 8.0.44) — оба джойна ниже сделаны как обычные производные таблицы
 * (агрегат считается один раз для всего запроса, дальше — простое равенство
 * в ON). Это стандартный SQL без версийных сюрпризов, но с одним нюансом
 * для прогноза — см. комментарий у джойна fcst.
 */
class WbOrderFeedSearch extends WbOrder
{
    public $date_from;
    public $date_to;

    // Поля для фильтров в шапке грида (kartik filterModel).
    public $status;
    public $warehouse_name;
    public $region_name;

    public function rules()
    {
        return [
            [['date_from', 'date_to'], 'safe'],
            [['nm_id'], 'integer'],
            [['status', 'warehouse_name', 'region_name'], 'safe'],
        ];
    }

    /**
     * Список статусов для выпадающего фильтра — контролируемый словарь,
     * ярлыки строго те же, что возвращает $resolveStatus во view.
     */
    public static function getStatusOptions()
    {
        return [
            'Новый' => 'Новый',
            'Сборка' => 'Сборка',
            'В пути' => 'В пути',
            'На ПВЗ' => 'На ПВЗ',
            'Выкуплен' => 'Выкуплен',
            'Отменён' => 'Отменён',
            'Отмена клиентом' => 'Отмена клиентом',
            'Брак' => 'Брак',
        ];
    }

    /**
     * Список складов отгрузки для выпадающего фильтра "Откуда" — с учётом
     * ВСЕХ остальных активных фильтров (период, артикул, статус, уже
     * выбранный регион), кроме самого себя. Иначе в списке будут склады,
     * для которых при текущих условиях нет ни одного заказа — вводит в
     * заблуждение.
     */
    public function getWarehouseOptions()
    {
        $query = $this->buildBaseQuery();
        $query->andFilterWhere(['o.region_name' => $this->region_name]);

        $values = $query
            ->select('o.warehouse_name')
            ->distinct()
            ->andWhere(['not', ['o.warehouse_name' => null]])
            ->orderBy('o.warehouse_name')
            ->column();

        return array_combine($values, $values);
    }

    /**
     * Список регионов назначения для выпадающего фильтра "Куда" — с учётом
     * всех остальных активных фильтров, кроме самого себя (см. комментарий
     * у getWarehouseOptions).
     */
    public function getRegionOptions()
    {
        $query = $this->buildBaseQuery();
        $query->andFilterWhere(['o.warehouse_name' => $this->warehouse_name]);

        $values = $query
            ->select('o.region_name')
            ->distinct()
            ->andWhere(['not', ['o.region_name' => null]])
            ->orderBy('o.region_name')
            ->column();

        return array_combine($values, $values);
    }

    /**
     * Сводка по ВСЕМ заказам, подходящим под текущие фильтры (не только по
     * текущей странице грида) — количество, сумма итоговых цен, средний
     * СПП, разбивка FBS/FBO. Использует те же фильтры, что и search()
     * (даты, nm_id, статус, склад, регион).
     *
     * @return array{count:int, sum:float, avg_spp:float, fbs_count:int, fbo_count:int}
     */
    public function getSummaryStats()
    {
        $query = $this->buildBaseQuery();
        $query->andFilterWhere(['o.warehouse_name' => $this->warehouse_name]);
        $query->andFilterWhere(['o.region_name' => $this->region_name]);

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
     * SQL-условие для фильтра по статусу — повторяет логику $resolveStatus
     * из feed.php, но на стороне БД. ВАЖНО: тут нельзя ссылаться на алиасы
     * из SELECT (fbs_wb_status и т.п.) — MySQL не разрешает использовать
     * алиасы SELECT в WHERE, поэтому обращаемся напрямую к столбцам
     * джойненных таблиц (f.*, ls.*, o.*).
     */
    private function buildStatusCondition($label)
    {
        $fbs = ['o.warehouse_type' => 'Склад продавца'];
        $notFbs = ['<>', 'o.warehouse_type', 'Склад продавца'];
        $supplyEmpty = ['or', ['f.supply_id' => null], ['f.supply_id' => '']];
        $notCancelled = ['or', ['o.is_cancel' => null], ['o.is_cancel' => 0]];
        $cancelled = ['>', 'o.is_cancel', 0];
        $noSaleDate = ['o.sale_date' => null];
        $hasSaleDate = ['not', ['o.sale_date' => null]];

        switch ($label) {
            case 'Новый':
                return ['or',
                    ['and', $fbs, $supplyEmpty],
                    ['and', $fbs, ['ls.wb_status' => 'waiting'], ['ls.supplier_status' => 'new']],
                ];
            case 'Сборка':
                return ['and', $fbs, ['ls.wb_status' => 'waiting'], ['ls.supplier_status' => 'confirm']];
            case 'В пути':
                return ['or',
                    ['and', $fbs, ['ls.wb_status' => 'waiting'], ['ls.supplier_status' => 'complete']],
                    ['and', $fbs, ['ls.wb_status' => 'sorted']],
                    ['and', $notFbs, $notCancelled, $noSaleDate],
                ];
            case 'На ПВЗ':
                return ['and', $fbs, ['ls.wb_status' => 'ready_for_pickup']];
            case 'Выкуплен':
                return ['or',
                    ['and', $fbs, ['ls.wb_status' => 'sold']],
                    ['and', $notFbs, $hasSaleDate],
                ];
            case 'Отменён':
                return ['or',
                    ['and', $fbs, ['ls.wb_status' => 'waiting'], ['ls.supplier_status' => 'cancel']],
                    ['and', $fbs, ['ls.wb_status' => 'canceled']],
                    ['and', $notFbs, $cancelled],
                ];
            case 'Отмена клиентом':
                return ['and', $fbs, ['ls.wb_status' => 'canceled_by_client']];
            case 'Брак':
                return ['and', $fbs, ['ls.wb_status' => 'defect']];
        }

        // Неизвестный ярлык (например, руками подсунули в URL) — не должен
        // молча игнорировать фильтр и показывать всё подряд.
        return '1=0';
    }

    /**
     * Базовый запрос: только даты + nm_id + статус + джойны, необходимые
     * для статуса (f, ls). Переиспользуется в search() (там сверху
     * добавляются ещё wbcards/forecast джойны и фильтр по складу/региону)
     * и в getWarehouseOptions()/getRegionOptions() (там достаточно этого
     * базового набора — карточка/прогноз для списка опций не нужны).
     */
    private function buildBaseQuery()
    {
        $query = WbOrder::find()
            ->alias('o')
            ->leftJoin(['f' => 'wb_orders_fbs'], 'f.rid = o.srid')
            // См. подробный комментарий про отказ от LATERAL в search().
            ->leftJoin(
                ['ls' => "(
                    SELECT s1.wb_order_id, s1.supplier_status, s1.wb_status, s1.created_at AS status_changed_at
                    FROM wb_orders_fbs_statuses s1
                    JOIN (
                        SELECT wb_order_id, MAX(id) AS max_id
                        FROM wb_orders_fbs_statuses
                        GROUP BY wb_order_id
                    ) m ON m.wb_order_id = s1.wb_order_id AND m.max_id = s1.id
                )"],
                'ls.wb_order_id = f.wb_order_id'
            );

        $query->andFilterWhere(['o.nm_id' => $this->nm_id]);

        if (!empty($this->status)) {
            $query->andWhere($this->buildStatusCondition($this->status));
        }

        if (!empty($this->date_from)) {
            $query->andWhere(['>=', 'o.date', $this->date_from . ' 00:00:00']);
        }
        if (!empty($this->date_to)) {
            $query->andWhere(['<=', 'o.date', $this->date_to . ' 23:59:59']);
        }

        return $query;
    }

    /**
     * @param array $params ['nm_id' => int|null, 'date_from' => 'Y-m-d', 'date_to' => 'Y-m-d']
     */
    public function search($params)
    {
        $this->nm_id = $params['nm_id'] ?? null;
        $this->date_from = $params['date_from'] ?? null;
        $this->date_to = $params['date_to'] ?? null;

        $query = $this->buildBaseQuery()
            ->select([
                'o.*',
                'c.title AS card_title',
                'c.vendorCode AS card_vendor_code',
                'c.subjectName AS card_subject_name',
                'c.brand AS card_brand',
                'c.photos AS card_photos',
                'f.supply_id AS fbs_supply_id',
                'ls.supplier_status AS fbs_supplier_status',
                'ls.wb_status AS fbs_wb_status',
                'ls.status_changed_at AS fbs_status_changed_at',
                'fcst.forecast_commission_pct',
                'fcst.forecast_delivery_rub',
                'fcst.forecast_acquiring_pct',
            ])
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = o.nm_id')
            // Прогнозные показатели (detail_by_period_forecast) — средние по
            // сегменту (склад/тип + регион + категория) за последние 1-10
            // дней ДО СЕГОДНЯ (фиксированное окно, не per-order — без
            // LATERAL нельзя привязать окно к дате конкретного заказа
            // равенством в ON). Лента по умолчанию и так показывает
            // сегодняшние заказы, так что разница на практике
            // несущественна — прогноз в любом случае приблизительный.
            // Используется во вьюхе только там, где ещё нет факта
            // (commission_fee IS NULL и т.п.).
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
            ->asArray();

        $query->andFilterWhere(['o.warehouse_name' => $this->warehouse_name]);
        $query->andFilterWhere(['o.region_name' => $this->region_name]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => ['date' => SORT_DESC],
                'attributes' => [
                    'date' => [
                        'asc' => ['o.date' => SORT_ASC],
                        'desc' => ['o.date' => SORT_DESC],
                        'default' => SORT_DESC,
                    ],
                ],
            ],
            'pagination' => [
                'pageSize' => 50,
            ],
        ]);

        return $dataProvider;
    }
}