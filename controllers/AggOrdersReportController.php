<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\data\ArrayDataProvider;

class AggOrdersReportController extends Controller
{
    public function actionCompare()
    {
        $yesterdayTime = strtotime('-1 day');
        $day = (int)date('d', $yesterdayTime);
        $month = (int)date('m', $yesterdayTime);
        $currentYear = (int)date('Y', $yesterdayTime);

        $years = [
            $currentYear - 2, // Позапрошлый
            $currentYear - 1, // Прошлый
            $currentYear      // Текущий
        ];

        // 1. Данные для главного графика по месяцам (исключаем nmID = 0)
        $rawData = Yii::$app->db->createCommand("
            SELECT 
                MONTH(order_date) as m,
                YEAR(order_date) as y,
                SUM(orders_qty) as total_qty,
                SUM(retail_amount_sum) as total_amount
            FROM agg_orders_daily_sku
            WHERE YEAR(order_date) IN (2024, 2025, 2026) AND nmID != 0
            GROUP BY y, m
            ORDER BY m ASC, y ASC
        ")->queryAll();

        $monthsNames = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель', 
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август', 
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
        ];

        // Опеределяем прошлый месяц и его корректные календарные года (учитываем переход января)
        $prevMonthNum = $month - 1 == 0 ? 12 : $month - 1;
        $pmYears = [
            $month - 1 == 0 ? $years[0] - 1 : $years[0],
            $month - 1 == 0 ? $years[1] - 1 : $years[1],
            $month - 1 == 0 ? $years[2] - 1 : $years[2],
        ];

        // Максимальные дни в текущем и прошлом месяцах для прошлых лет (чтобы не выходить за рамки календаря)
        $maxDayCurrent0 = min($day, (int)date('t', strtotime("{$years[0]}-{$month}-01")));
        $maxDayCurrent1 = min($day, (int)date('t', strtotime("{$years[1]}-{$month}-01")));
        $maxDayCurrent2 = $day;

        // 1.1 Сводная строка прошлого месяца
        $pmSummaryRaw = Yii::$app->db->createCommand("
            SELECT 
                SUM(CASE WHEN YEAR(order_date) = :y0 THEN orders_qty ELSE 0 END) as qty_0,
                SUM(CASE WHEN YEAR(order_date) = :y0 THEN retail_amount_sum ELSE 0 END) as amt_0,
                SUM(CASE WHEN YEAR(order_date) = :y1 THEN orders_qty ELSE 0 END) as qty_1,
                SUM(CASE WHEN YEAR(order_date) = :y1 THEN retail_amount_sum ELSE 0 END) as amt_1,
                SUM(CASE WHEN YEAR(order_date) = :y2 THEN orders_qty ELSE 0 END) as qty_2,
                SUM(CASE WHEN YEAR(order_date) = :y2 THEN retail_amount_sum ELSE 0 END) as amt_2
            FROM agg_orders_daily_sku
            WHERE (YEAR(order_date) = :y0 AND MONTH(order_date) = :m)
               OR (YEAR(order_date) = :y1 AND MONTH(order_date) = :m)
               OR (YEAR(order_date) = :y2 AND MONTH(order_date) = :m)
        ", [
            ':y0' => $pmYears[0], ':y1' => $pmYears[1], ':y2' => $pmYears[2], ':m' => $prevMonthNum
        ])->queryOne();

        $pmSummary = [
            'period_name' => "Полный прошлый месяц ({$monthsNames[$prevMonthNum]})",
            'qty_0' => (int)$pmSummaryRaw['qty_0'], 'amt_0' => (float)$pmSummaryRaw['amt_0'],
            'qty_1' => (int)$pmSummaryRaw['qty_1'], 'amt_1' => (float)$pmSummaryRaw['amt_1'],
            'qty_2' => (int)$pmSummaryRaw['qty_2'], 'amt_2' => (float)$pmSummaryRaw['amt_2'],
            'change_1' => $pmSummaryRaw['amt_0'] > 0 ? (($pmSummaryRaw['amt_1'] - $pmSummaryRaw['amt_0']) / $pmSummaryRaw['amt_0']) * 100 : 0,
            'change_2' => $pmSummaryRaw['amt_1'] > 0 ? (($pmSummaryRaw['amt_2'] - $pmSummaryRaw['amt_1']) / $pmSummaryRaw['amt_1']) * 100 : 0,
        ];


        // 2.1 Сводная строка текущего месяца (MTD)
        $cmSummaryRaw = Yii::$app->db->createCommand("
            SELECT 
                SUM(CASE WHEN YEAR(order_date) = :y0 AND DAY(order_date) <= :d0 THEN orders_qty ELSE 0 END) as qty_0,
                SUM(CASE WHEN YEAR(order_date) = :y0 AND DAY(order_date) <= :d0 THEN retail_amount_sum ELSE 0 END) as amt_0,
                SUM(CASE WHEN YEAR(order_date) = :y1 AND DAY(order_date) <= :d1 THEN orders_qty ELSE 0 END) as qty_1,
                SUM(CASE WHEN YEAR(order_date) = :y1 AND DAY(order_date) <= :d1 THEN retail_amount_sum ELSE 0 END) as amt_1,
                SUM(CASE WHEN YEAR(order_date) = :y2 AND DAY(order_date) <= :d2 THEN orders_qty ELSE 0 END) as qty_2,
                SUM(CASE WHEN YEAR(order_date) = :y2 AND DAY(order_date) <= :d2 THEN retail_amount_sum ELSE 0 END) as amt_2
            FROM agg_orders_daily_sku
            WHERE MONTH(order_date) = :m AND YEAR(order_date) IN (:y0, :y1, :y2)
        ", [
            ':m' => $month, ':y0' => $years[0], ':y1' => $years[1], ':y2' => $years[2],
            ':d0' => $maxDayCurrent0, ':d1' => $maxDayCurrent1, ':d2' => $maxDayCurrent2
        ])->queryOne();

        $cmSummary = [
            'period_name' => "Текущий месяц ({$monthsNames[$month]} с 01 по " . sprintf('%02d', $day) . ")",
            'qty_0' => (int)$cmSummaryRaw['qty_0'], 'amt_0' => (float)$cmSummaryRaw['amt_0'],
            'qty_1' => (int)$cmSummaryRaw['qty_1'], 'amt_1' => (float)$cmSummaryRaw['amt_1'],
            'qty_2' => (int)$cmSummaryRaw['qty_2'], 'amt_2' => (float)$cmSummaryRaw['amt_2'],
            'change_1' => $cmSummaryRaw['amt_0'] > 0 ? (($cmSummaryRaw['amt_1'] - $cmSummaryRaw['amt_0']) / $cmSummaryRaw['amt_0']) * 100 : 0,
            'change_2' => $cmSummaryRaw['amt_1'] > 0 ? (($cmSummaryRaw['amt_2'] - $cmSummaryRaw['amt_1']) / $cmSummaryRaw['amt_1']) * 100 : 0,
        ];

        $processedData = [];
        for ($i = 1; $i <= 12; $i++) {
            $processedData[$i] = [
                'month_name' => $monthsNames[$i],
                'qty_2024' => 0, 'amount_2024' => 0,
                'qty_2025' => 0, 'amount_2025' => 0,
                'qty_2026' => 0, 'amount_2026' => 0,
            ];
        }


        foreach ($rawData as $row) {
            $m = (int)$row['m'];
            $y = $row['y'];
            $processedData[$m]["qty_$y"] = (int)$row['total_qty'];
            $processedData[$m]["amount_$y"] = (float)$row['total_amount'];
        }

        // --- ЛОГИКА ДЛЯ СРАВНИТЕЛЬНЫХ ТАБЛИЦ (ПРОШЛЫЙ И ТЕКУЩИЙ МЕСЯЦ) ---
        // Определяем прошлый месяц и текущий месяц относительно сегодняшнего дня
        $currentMonth = (int)date('m');
        $lastMonth = $currentMonth - 1 === 0 ? 12 : $currentMonth - 1;

        // Шаг 1: Находим ТОП-20 nmID за прошлый и текущий месяцы по всем годам через UNION (исключаем 0)
        $topIdsRows = Yii::$app->db->createCommand("
            SELECT nmID FROM (
                (SELECT nmID FROM agg_orders_daily_sku WHERE MONTH(order_date) = :lm AND YEAR(order_date) = 2024 AND nmID != 0 GROUP BY nmID ORDER BY SUM(retail_amount_sum) DESC LIMIT 20)
                UNION
                (SELECT nmID FROM agg_orders_daily_sku WHERE MONTH(order_date) = :lm AND YEAR(order_date) = 2025 AND nmID != 0 GROUP BY nmID ORDER BY SUM(retail_amount_sum) DESC LIMIT 20)
                UNION
                (SELECT nmID FROM agg_orders_daily_sku WHERE MONTH(order_date) = :lm AND YEAR(order_date) = 2026 AND nmID != 0 GROUP BY nmID ORDER BY SUM(retail_amount_sum) DESC LIMIT 20)
                UNION
                (SELECT nmID FROM agg_orders_daily_sku WHERE MONTH(order_date) = :cm AND YEAR(order_date) = 2024 AND nmID != 0 GROUP BY nmID ORDER BY SUM(retail_amount_sum) DESC LIMIT 20)
                UNION
                (SELECT nmID FROM agg_orders_daily_sku WHERE MONTH(order_date) = :cm AND YEAR(order_date) = 2025 AND nmID != 0 GROUP BY nmID ORDER BY SUM(retail_amount_sum) DESC LIMIT 20)
                UNION
                (SELECT nmID FROM agg_orders_daily_sku WHERE MONTH(order_date) = :cm AND YEAR(order_date) = 2026 AND nmID != 0 GROUP BY nmID ORDER BY SUM(retail_amount_sum) DESC LIMIT 20)
            ) as t
        ", [':lm' => $lastMonth, ':cm' => $currentMonth])->queryColumn();

        $lastMonthData = [];
        $currentMonthData = [];

        if (!empty($topIdsRows)) {
            $idsString = implode(',', array_map('intval', $topIdsRows));
            
            // Шаг 2: Тянем данные по продажам с JOIN таблицы wbcards по полю nmID
            $detailsRaw = Yii::$app->db->createCommand("
                SELECT 
                    s.nmID,
                    c.title,
                    MONTH(s.order_date) as m,
                    YEAR(s.order_date) as y,
                    SUM(s.orders_qty) as total_qty,
                    SUM(s.retail_amount_sum) as total_amount,
                    AVG(s.retail_amount_sum / s.orders_qty) as avg_price
                FROM agg_orders_daily_sku s
                LEFT JOIN wbcards c ON c.nmID = s.nmID
                WHERE s.nmID IN ($idsString) AND MONTH(s.order_date) IN (:lm, :cm) AND YEAR(s.order_date) IN (2024, 2025, 2026)
                GROUP BY s.nmID, c.title, m, y
            ", [':lm' => $lastMonth, ':cm' => $currentMonth])->queryAll();

            // Распределяем по двум массивам месяцев
            foreach ($detailsRaw as $row) {
                $nmID = $row['nmID'];
                $m = (int)$row['m'];
                $y = $row['y'];

                if ($m === $lastMonth) {
                    if (!isset($lastMonthData[$nmID])) {
                        $lastMonthData[$nmID] = [
                            'nmID' => $nmID,
                            'title' => $row['title'] ?? 'Без названия',
                            'qty_2024' => 0, 'amount_2024' => 0, 'avg_price_2024' => 0,
                            'qty_2025' => 0, 'amount_2025' => 0, 'avg_price_2025' => 0,
                            'qty_2026' => 0, 'amount_2026' => 0, 'avg_price_2026' => 0,
                        ];
                    }
                    $lastMonthData[$nmID]["qty_$y"] = (int)$row['total_qty'];
                    $lastMonthData[$nmID]["amount_$y"] = (float)$row['total_amount'];
                    $lastMonthData[$nmID]["avg_price_$y"] = (float)$row['avg_price'];
                }

                if ($m === $currentMonth) {
                    if (!isset($currentMonthData[$nmID])) {
                        $currentMonthData[$nmID] = [
                            'nmID' => $nmID,
                            'title' => $row['title'] ?? 'Без названия',
                            'qty_2024' => 0, 'amount_2024' => 0, 'avg_price_2024' => 0,
                            'qty_2025' => 0, 'amount_2025' => 0, 'avg_price_2025' => 0,
                            'qty_2026' => 0, 'amount_2026' => 0, 'avg_price_2026' => 0,
                        ];
                    }
                    $currentMonthData[$nmID]["qty_$y"] = (int)$row['total_qty'];
                    $currentMonthData[$nmID]["amount_$y"] = (float)$row['total_amount'];
                    $currentMonthData[$nmID]["avg_price_$y"] = (float)$row['avg_price'];
                }
            }

            // Шаг 3: Сортируем массивы по сумме за текущий год (2026) по убыванию
            uasort($lastMonthData, function ($a, $b) {
                return $b['amount_2026'] <=> $a['amount_2026'];
            });
            uasort($currentMonthData, function ($a, $b) {
                return $b['amount_2026'] <=> $a['amount_2026'];
            });
        }

        return $this->render('compare', [
            'dataProvider' => new ArrayDataProvider(['allModels' => array_values($processedData), 'pagination' => false]),
            'lastMonthProvider' => new ArrayDataProvider(['allModels' => array_values($lastMonthData), 'pagination' => false]),
            'currentMonthProvider' => new ArrayDataProvider(['allModels' => array_values($currentMonthData), 'pagination' => false]),
            'chartData' => array_values($processedData),
            'lastMonthName' => $monthsNames[$lastMonth],
            'currentMonthName' => $monthsNames[$currentMonth],

            'pmSummaryProvider' => new ArrayDataProvider(['allModels' => [$pmSummary], 'pagination' => false]),
            'cmSummaryProvider' => new ArrayDataProvider(['allModels' => [$cmSummary], 'pagination' => false]),

            'pmYears' => $pmYears,
            'years' => $years,
            'prevMonthName' => $monthsNames[$prevMonthNum],
            'currMonthName' => $monthsNames[$month]
        ]);
    }
}