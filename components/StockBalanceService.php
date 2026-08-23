<?php

namespace app\components;

use app\models\StockMovement;
use app\models\StockSnapshot;

/**
 * Расчёт текущего расчётного остатка на Смоленске для карточки wbcards (по nmID):
 *   баланс = qty_start последнего снапшота
 *          + SUM(движений всех типов) за период ПОСЛЕ даты снапшота, до указанной даты включительно
 */
class StockBalanceService
{
    public static function getCurrentBalance(int $nmId, ?string $onDate = null): int
    {
        $onDate = $onDate ?: date('Y-m-d');

        $snapshot = StockSnapshot::findLatestFor($nmId, $onDate);
        $qtyStart = $snapshot ? (int)$snapshot->qty_start : 0;
        $sinceDate = $snapshot ? $snapshot->period_date : '1970-01-01';

        $movementsSum = (int) StockMovement::find()
            ->where(['nm_id' => $nmId])
            ->andWhere(['>=', 'movement_date', $sinceDate])
            ->andWhere(['<=', 'movement_date', $onDate])
            ->sum('qty');

        return $qtyStart + $movementsSum;
    }

    /**
     * Разбивка баланса на составляющие - удобно показывать в отчёте/сверке.
     */
    public static function getBalanceBreakdown(int $nmId, ?string $onDate = null): array
    {
        $onDate = $onDate ?: date('Y-m-d');
        $snapshot = StockSnapshot::findLatestFor($nmId, $onDate);
        $qtyStart = $snapshot ? (int)$snapshot->qty_start : 0;
        $sinceDate = $snapshot ? $snapshot->period_date : '1970-01-01';

        $query = StockMovement::find()
            ->where(['nm_id' => $nmId])
            ->andWhere(['>=', 'movement_date', $sinceDate])
            ->andWhere(['<=', 'movement_date', $onDate]);

        $productionIn = (int) (clone $query)->andWhere(['type' => StockMovement::TYPE_PRODUCTION_IN])->sum('qty');
        $adjustment = (int) (clone $query)->andWhere(['type' => StockMovement::TYPE_ADJUSTMENT])->sum('qty');
        $loss = (int) (clone $query)->andWhere(['type' => StockMovement::TYPE_LOSS])->sum('qty');

        return [
            'period_date' => $sinceDate,
            'qty_start' => $qtyStart,
            'production_in' => $productionIn,
            'adjustment' => $adjustment,
            'loss' => $loss,
            'balance' => $qtyStart + $productionIn + $adjustment + $loss,
        ];
    }
}