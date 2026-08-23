<?php

namespace app\repositories;

use yii\db\Query;

class WeeklyReportRepository
{
    /**
     * @param int|array $nmid — один ID или массив [123, 456, ...]
     */
    public function getWeeklyReportByNmid($nmid, string $dateFrom, string $dateTo): array
    {
        // Общие поля для селекта
        $commonSelect = [
            'week_key'              => 'DATE_FORMAT(sale_dt, "%X-%V")',
            'retail_price'          => 'SUM(COALESCE(dd.retail_price, 0))',
            'retail_amount'         => 'SUM(COALESCE(retail_amount, 0))',
            'commission_percent'    => 'AVG(COALESCE(commission_percent, 0))',
            'ppvz_spp_prc'          => 'AVG(COALESCE(ppvz_spp_prc, 0))',
            'ppvz_sales_commission' => 'SUM(COALESCE(ppvz_sales_commission, 0))',
            'ppvz_reward'           => 'SUM(COALESCE(ppvz_reward, 0))',
            'acquiring_fee'         => 'SUM(COALESCE(acquiring_fee, 0))',
            'ppvz_vw'               => 'SUM(COALESCE(ppvz_vw, 0))',
            'ppvz_vw_nds'           => 'SUM(COALESCE(ppvz_vw_nds, 0))',
            'delivery_rub'          => 'SUM(COALESCE(delivery_rub, 0))',
            'rebill_logistic_cost'  => 'SUM(COALESCE(rebill_logistic_cost, 0))',
            'ppvz_for_pay'          => 'SUM(COALESCE(ppvz_for_pay, 0))',
            'retail_sum'            => 'SUM(retail_amount)',
            'for_pay'               => 'SUM(ppvz_for_pay)',
            'delivery'              => 'SUM(delivery_amount)',
        ];

        // 1. Запрос для "Продажа"
        $dataP = (new Query())
            ->select(array_merge($commonSelect, [
                'rows_count'   => 'COUNT(*)',
                'sales_count'  => 'SUM(CASE WHEN doc_type_name = "Продажа" THEN 1 ELSE 0 END)',
                'return_count' => 'SUM(CASE WHEN doc_type_name = "Возврат" THEN 1 ELSE 0 END)',
            ]))
            ->from(['dd' => 'detail_by_period'])
            ->where(['dd.nm_id' => $nmid]) // Фильтр по nmid
            ->andWhere(['between', 'dd.sale_dt', $dateFrom, $dateTo])
            ->andWhere(['dd.supplier_oper_name' => 'Продажа'])
            ->groupBy(['week_key']);

        // 2. Запрос для "Логистика"
        $dataL = (new Query())
            ->select(array_merge($commonSelect, [
                'rows_count'   => 'SUM(0)',
                'sales_count'  => 'SUM(0)',
                'return_count' => 'SUM(0)',
            ]))
            ->from(['dd' => 'detail_by_period'])
            ->where(['dd.nm_id' => $nmid]) // Фильтр по nmid
            ->andWhere(['between', 'dd.sale_dt', $dateFrom, $dateTo])
            ->andWhere(['dd.supplier_oper_name' => 'Логистика'])
            ->groupBy(['week_key']);

        // 3. Финальная агрегация UNION'а
        return (new Query())
            ->select([
                'week_key',
                'retail_price'          => 'SUM(COALESCE(retail_price, 0))',
                'retail_amount'         => 'SUM(COALESCE(retail_amount, 0))',
                'commission_percent'    => 'SUM(COALESCE(commission_percent, 0))',
                'ppvz_spp_prc'          => 'SUM(COALESCE(ppvz_spp_prc, 0))',
                'ppvz_sales_commission' => 'SUM(COALESCE(ppvz_sales_commission, 0))',
                'ppvz_reward'           => 'SUM(COALESCE(ppvz_reward, 0))',
                'acquiring_fee'         => 'SUM(COALESCE(acquiring_fee, 0))',
                'ppvz_vw'               => 'SUM(COALESCE(ppvz_vw, 0))',
                'ppvz_vw_nds'           => 'SUM(COALESCE(ppvz_vw_nds, 0))',
                'delivery_rub'          => 'SUM(COALESCE(delivery_rub, 0))',
                'rebill_logistic_cost'  => 'SUM(COALESCE(rebill_logistic_cost, 0))',
                'ppvz_for_pay'          => 'SUM(COALESCE(ppvz_for_pay, 0))',
                'rows_count'            => 'SUM(rows_count)',
                'sales_count'           => 'SUM(sales_count)',
                'return_count'          => 'SUM(return_count)',
                'retail_sum'            => 'SUM(retail_sum)',
                'for_pay'               => 'SUM(for_pay)',
                'delivery'              => 'SUM(delivery)',
            ])
            ->from(['u' => $dataL->union($dataP, true)])
            ->orderBy(['week_key' => SORT_ASC])
            ->groupBy(['week_key'])
            ->all();
    }

    public function getChartCountryDataByNmid(array $nmidList, string $dateFrom, string $dateTo): array
    {
        return (new Query())
            ->select([
                'countryName' => 'COALESCE(ws.countryName, "Неизвестно")',
                // Если p и q больше не нужны, считаем просто количество строк
                'sales_count' => 'COUNT(*)', 
            ])
            ->from(['dd' => 'detail_by_period'])
            ->leftJoin(['ws' => 'wb_sales'], 'dd.srid = ws.srid')
            ->where(['dd.nm_id' => $nmidList]) // Фильтр напрямую по таблице данных
            ->andWhere(['between', 'dd.sale_dt', $dateFrom, $dateTo])
            ->andWhere(['dd.supplier_oper_name' => 'Продажа'])
            ->andWhere(['dd.doc_type_name' => 'Продажа'])
            ->groupBy(['countryName'])
            ->orderBy(['sales_count' => SORT_DESC])
            ->limit(5) 
            ->all();
    }


/**
 * Данные по регионам для графика (Топ-15)
 * 
 * @param array $nmidList — массив [123, 456, ...]
 */
    public function getChartRegionDataByNmid(array $nmidList, string $dateFrom, string $dateTo): array
    {
        return (new \yii\db\Query())
            ->select([
                'region'        => 'COALESCE(ws.regionName, "Неизвестно")',
                'retail_amount' => 'SUM(COALESCE(dd.retail_amount, 0))', // Без * pp.p/100
                'ppvz_for_pay'  => 'SUM(COALESCE(dd.ppvz_for_pay, 0))',  // Без * pp.p/100
                'sales_count'   => 'SUM(CASE WHEN dd.doc_type_name = "Продажа" THEN 1 ELSE 0 END)',
            ])
            ->from(['dd' => 'detail_by_period'])
            ->leftJoin(['ws' => 'wb_sales'], 'dd.srid = ws.srid')
            ->where(['dd.nm_id' => $nmidList])
            ->andWhere(['between', 'dd.sale_dt', $dateFrom, $dateTo])
            ->andWhere(['dd.supplier_oper_name' => 'Продажа'])
            ->groupBy(['region'])
            ->orderBy(['sales_count' => SORT_DESC])
            ->limit(15) 
            ->all();
    }


/**
 * Данные для графика динамики по неделям (Timeline)
 * 
 * @param array $nmidList
 */
    public function getChartTimelineDataByNmid(array $nmidList, string $dateFrom, string $dateTo): array
    {
        return (new \yii\db\Query())
            ->select([
                'week_key'      => 'DATE_FORMAT(sale_dt, "%X-%V")',
                'retail_amount' => 'AVG(COALESCE(dd.retail_amount, 0))',
                'ppvz_for_pay'  => 'AVG(COALESCE(dd.ppvz_for_pay, 0))',
                'sales_count'   => 'SUM(CASE WHEN dd.doc_type_name = "Продажа" THEN 1 ELSE 0 END)',
            ])
            ->from(['dd' => 'detail_by_period'])
            ->where(['dd.nm_id' => $nmidList])
            ->andWhere(['between', 'dd.sale_dt', $dateFrom, $dateTo])
            ->andWhere(['dd.supplier_oper_name' => 'Продажа'])
            ->groupBy(['week_key'])
            ->orderBy(['week_key' => SORT_ASC])
            ->all();
    }


}
