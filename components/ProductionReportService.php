<?php

namespace app\components;

use Yii;

/**
 * Собирает сырые агрегаты по товарам для отчёта производственного планирования.
 * Бизнес-логику (статус, дней до конца, рекомендованное количество) НЕ считает -
 * этим занимается контроллер/вьюха, сервис только достаёт числа из БД.
 */
class ProductionReportService
{
    /**
     * @param int[] $nmIds Список nm_id (уже отфильтрованных по компании на уровне плана)
     * @param string $periodDate 'Y-m-01' - начало выбранного периода (месяца)
     * @param int|null $companyId null = без фильтра по company_id (режим "Все компании")
     * @return array<int, array{
     *     smolensk_start: float,
     *     smolensk_movements: float,
     *     wb_start: float,
     *     orders_since_period: float,
     *     orders_last_30: float
     * }>
     */
    public static function buildReport(array $nmIds, string $periodDate, ?int $companyId): array
    {
        $nmIdsInt = array_values(array_unique(array_map('intval', $nmIds)));
        if (empty($nmIdsInt)) {
            return [];
        }

        $db = Yii::$app->db;
        $today = date('Y-m-d');
        $thirtyDaysAgoDt = date('Y-m-d H:i:s', strtotime('-30 days'));
        $nowDt = date('Y-m-d H:i:s');
        $periodStartDt = $periodDate . ' 00:00:00';

        $idsListInt = implode(',', $nmIdsInt); // int-список - безопасно, значения уже приведены к (int)
        $idsListStr = "'" . implode("','", $nmIdsInt) . "'"; // nm_id в wb_stocks/wb_order - varchar

        // --- 1. Смоленск: остаток на начало периода (последний снапшот <= periodDate) ---
        $smolenskStart = self::fetchMap($db, "
            SELECT s1.nm_id AS nm_id, s1.qty_start AS val
            FROM {{%stock_snapshot}} s1
            INNER JOIN (
                SELECT nm_id, MAX(period_date) AS max_date
                FROM {{%stock_snapshot}}
                WHERE nm_id IN ({$idsListInt})
                  AND period_date <= :periodDate
                GROUP BY nm_id
            ) s2 ON s1.nm_id = s2.nm_id AND s1.period_date = s2.max_date
        ", [':periodDate' => $periodDate]);

        // --- 2. Смоленск: приход/корректировка/списание за период (после periodDate до сегодня) ---
        $smolenskMovements = self::fetchMap($db, "
            SELECT nm_id AS nm_id, SUM(qty) AS val
            FROM {{%stock_movement}}
            WHERE nm_id IN ({$idsListInt})
              AND movement_date >= :periodDate
              AND movement_date <= :today
            GROUP BY nm_id
        ", [':periodDate' => $periodDate, ':today' => $today]);

        // --- 3. WB: остаток на начало периода (последний срез <= periodDate), сумма по складам ---
        $companyClauseWb = $companyId !== null ? ' AND company_id = :companyId' : '';
        $wbStart = self::fetchMap($db, "
            SELECT ws.nm_id AS nm_id, SUM(ws.quantity) AS val
            FROM {{%wb_stocks}} ws
            INNER JOIN (
                SELECT nm_id, MAX(date) AS max_date
                FROM {{%wb_stocks}}
                WHERE nm_id IN ({$idsListStr})
                  AND date <= :periodDate
                  {$companyClauseWb}
                GROUP BY nm_id
            ) latest ON ws.nm_id = latest.nm_id AND ws.date = latest.max_date
            WHERE ws.nm_id IN ({$idsListStr})
              {$companyClauseWb}
            GROUP BY ws.nm_id
        ", array_merge([':periodDate' => $periodDate], $companyId !== null ? [':companyId' => $companyId] : []));

        // --- 4. Заказы с начала периода (для расчёта остатка "на сегодня") ---
        $companyClauseOrder = $companyId !== null ? ' AND company_id = :companyId' : '';
        $ordersSincePeriod = self::fetchMap($db, "
            SELECT nm_id AS nm_id, COUNT(*) AS val
            FROM {{%wb_order}}
            WHERE nm_id IN ({$idsListStr})
              AND date >= :fromDt
              AND date <= :toDt
              AND (is_cancel IS NULL OR is_cancel = 0)
              {$companyClauseOrder}
            GROUP BY nm_id
        ", array_merge(
            [':fromDt' => $periodStartDt, ':toDt' => $nowDt],
            $companyId !== null ? [':companyId' => $companyId] : []
        ));

        // --- 5. Заказы за последние 30 дней (для средней скорости продаж) ---
        $ordersLast30 = self::fetchMap($db, "
            SELECT nm_id AS nm_id, COUNT(*) AS val
            FROM {{%wb_order}}
            WHERE nm_id IN ({$idsListStr})
              AND date >= :fromDt
              AND date <= :toDt
              AND (is_cancel IS NULL OR is_cancel = 0)
              {$companyClauseOrder}
            GROUP BY nm_id
        ", array_merge(
            [':fromDt' => $thirtyDaysAgoDt, ':toDt' => $nowDt],
            $companyId !== null ? [':companyId' => $companyId] : []
        ));

        $result = [];
        foreach ($nmIdsInt as $nmId) {
            $result[$nmId] = [
                'smolensk_start' => $smolenskStart[$nmId] ?? 0.0,
                'smolensk_movements' => $smolenskMovements[$nmId] ?? 0.0,
                'wb_start' => $wbStart[$nmId] ?? 0.0,
                'orders_since_period' => $ordersSincePeriod[$nmId] ?? 0.0,
                'orders_last_30' => $ordersLast30[$nmId] ?? 0.0,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, float> nm_id => val
     */
    private static function fetchMap($db, string $sql, array $params): array
    {
        $rows = $db->createCommand($sql, $params)->queryAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['nm_id']] = isset($row['val']) ? (float)$row['val'] : 0.0;
        }
        return $map;
    }
}