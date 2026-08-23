<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\db\Query;

class ReportController extends Controller
{
    /**
     * Отчет "Сравнение лет" (Year-over-Year)
     */
    public function actionCompare($baseYear = 2026, $compYear = 2025, $metric = 'final_net', $nmId = null)
    {
        // Список доступных лет (исходя из ваших данных)
        $availableYears = [2024, 2025, 2026];
        
        // Доступные метрики для построения отчета
        $metrics = [
            'gross_retail' => 'В розничных ценах (retail_price)',
            'net_retail'   => 'После скидок (retail_amount)',
            'ppvz'         => 'К перечислению (ppvz_for_pay)',
            'final_net'    => 'Чистая прибыль (PPVZ - Логистика)'
        ];

        // Основной запрос к БД
        $dateField = 'sale_dt'; 

        $query = (new Query())
            ->select([
                'month' => "MONTH($dateField)",
                'year' => "YEAR($dateField)",
                'gross_retail' => 'SUM(CASE WHEN doc_type_name = "Продажа" AND supplier_oper_name = "Продажа" THEN retail_price ELSE 0 END)',
                'net_retail'   => 'SUM(CASE WHEN doc_type_name = "Продажа" AND supplier_oper_name = "Продажа" THEN retail_amount ELSE 0 END)',
                'ppvz'         => 'SUM(CASE WHEN doc_type_name = "Продажа" AND supplier_oper_name = "Продажа" THEN ppvz_for_pay ELSE 0 END)',
                'logistics'    => 'SUM(CASE WHEN supplier_oper_name = "Логистика" THEN delivery_rub ELSE 0 END)',
            ])
            ->from('detail_by_period')
            ->where(["YEAR($dateField)" => [$baseYear, $compYear]])
            ->groupBy(['year', 'month'])
            ->orderBy(['month' => SORT_ASC]);

        if ($nmId) {
            $query->andWhere(['nm_id' => $nmId]);
        }

        $rawData = $query->all();

        // Формируем пустую сетку на 12 месяцев
        $report = [];
        for ($m = 1; $m <= 12; $m++) {
            foreach ([$baseYear, $compYear] as $y) {
                $report[$m][$y] = [
                    'gross_retail' => 0,
                    'net_retail'   => 0,
                    'ppvz'         => 0,
                    'logistics'    => 0,
                    'final_net'    => 0
                ];
            }
        }

        // Наполняем данными
        foreach ($rawData as $row) {
            $m = (int)$row['month'];
            $y = (int)$row['year'];
            $ppvz = (float)$row['ppvz'];
            $log = (float)$row['logistics'];

            $report[$m][$y] = [
                'gross_retail' => (float)$row['gross_retail'],
                'net_retail'   => (float)$row['net_retail'],
                'ppvz'         => $ppvz,
                'logistics'    => $log,
                'final_net'    => $ppvz - $log
            ];
        }

        return $this->render('compare', [
            'report' => $report,
            'baseYear' => (int)$baseYear,
            'compYear' => (int)$compYear,
            'availableYears' => $availableYears,
            'currentMetric' => $metric,
            'metrics' => $metrics,
            'nmId' => $nmId
        ]);
    }
}