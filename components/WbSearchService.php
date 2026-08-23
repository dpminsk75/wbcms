<?php

namespace app\components;

use Yii;
use app\models\WbSrReportItemPhrases;

class WbSearchService
{
    /**
     * Формирует матрицу позиций и заказов по поисковым фразам для конкретного nmID за период
     *
     * @param int $nmId Артикул товара (WB)
     * @param string $dateFrom Дата начала периода (Y-m-d)
     * @param string $dateTo Дата окончания периода (Y-m-d)
     * @return array Массив с ключами 'models' (данные для DataProvider) и 'uniqueDates' (список дат)
     */
    public function getCardPhrasesMatrix($nmId, $dateFrom, $dateTo)
    {
        $models = [];
        $uniqueDates = [];

        $data = WbSrReportItemPhrases::find()
            ->select([
                'phrase', 
                'date', 
                'avg_position', 
                'clicks',
                'orders',
                'week_frequency',
                'SUM(clicks) OVER (PARTITION BY phrase) as total_clicks',
                'SUM(orders) OVER (PARTITION BY phrase) as total_orders',
                'AVG(week_frequency) OVER (PARTITION BY phrase) as avg_week_freq'
            ])
            ->where(['nmID' => $nmId])
            ->andWhere(['between', 'date', $dateFrom, $dateTo])
            ->orderBy(['avg_week_freq' => SORT_DESC, 'date' => SORT_ASC])
            ->asArray()
            ->all();

        $matrix = [];
        $phraseStats = []; 

        foreach ($data as $row) {
            $matrix[$row['phrase']][$row['date']] = [
                'pos' => (int)$row['avg_position'],
                'orders' => (int)$row['orders']
            ];
            $phraseStats[$row['phrase']] = [
                'clicks' => (int)$row['total_clicks'],
                'orders' => (int)$row['total_orders'],
                'freq' => (int)$row['avg_week_freq']
            ];
            $uniqueDates[$row['date']] = true;
        }

        $uniqueDates = array_keys($uniqueDates);
        sort($uniqueDates);

        foreach ($matrix as $phrase => $dates) {
            $row = [
                'phrase' => $phrase,
                'avg_freq' => $phraseStats[$phrase]['freq'] ?? 0,
                'total_clicks' => $phraseStats[$phrase]['clicks'] ?? 0,
                'total_orders' => $phraseStats[$phrase]['orders'] ?? 0,
            ];
            foreach ($uniqueDates as $date) {
                $row[$date] = $dates[$date] ?? null;
            }
            $models[] = $row;
        }

        return [
            'models' => $models,
            'uniqueDates' => $uniqueDates
        ];
    }
}