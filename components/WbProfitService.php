<?php

namespace app\components;

use Yii;
use yii\db\Query;
use yii\db\Expression;
use yii\data\ArrayDataProvider;

class WbProfitService
{
    /**
     * Получить финансовую аналитику по месяцам (с 2025 года)
     * @return ArrayDataProvider
     */
    public function getMonthlyProfitProvider()
    {
        $data = (new Query())
            ->select([
                'month'           => new Expression("DATE_FORMAT(sdate, '%Y-%m')"),
                'qnt'             => new Expression("SUM(qnt)"),
                'amount'          => new Expression("SUM(amount)"),
                'return'          => new Expression("SUM(`return`)"),
                'commission'      => new Expression("SUM(commission)"),
                'f_acquiring_fee' => new Expression("SUM(f_acquiring_fee)"),
                'f_acceptance'    => new Expression("SUM(f_acceptance)"),
                'f_delivery'      => new Expression("SUM(f_delivery)"),
                'f_storage_fee'   => new Expression("SUM(f_storage_fee)"),
                'f_penalty'       => new Expression("SUM(f_penalty)"),
                'f_deduction'     => new Expression("SUM(f_deduction)"),
                'f_otziv'         => new Expression("SUM(f_otziv)"),
                'f_adv'           => new Expression("SUM(f_adv)"),
                'f_cashback'      => new Expression("SUM(f_cashback)"),
                'net_profit'      => new Expression("SUM(net_profit)"),
                'total_nds'       => new Expression("SUM(f_nds)"),
                'total_cost'      => new Expression("SUM(f_cost_price)"),
                'profit_before_tax' => new Expression("SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)"),
                'tax_amount'      => new Expression("GREATEST(0, SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)) * 0.07"),
                'clean_margin'    => new Expression("(SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)) - (GREATEST(0, SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)) * 0.07)")
            ])
            ->from('agg_daily_summary')
            ->where(['>=', 'sdate', '2025-01-01'])
            ->groupBy(new Expression("DATE_FORMAT(sdate, '%Y-%m')"))
            ->orderBy(['month' => SORT_DESC])
            ->all();

        return new ArrayDataProvider([
            'allModels' => $data,
            'pagination' => false,
        ]);
    }

    /**
     * Получить ТОП или Худшие SKU за 60 дней
     * @param string $sortOrder SORT_DESC для топ, SORT_ASC для худших
     * @param bool $filterZeroQnt Исключать ли товары без продаж (нужно для худших)
     * @return ArrayDataProvider
     */
    public function getSkuProvider($sortOrder = SORT_DESC, $filterZeroQnt = false)
    {
        $query = (new Query())
            ->select([
                'nm_id'             => 'p.nm_id',
                'title'             => 'c.title',
                'vendorCode'        => 'c.vendorCode',
                'qnt'               => new Expression("SUM(p.qnt)"),
                'amount'            => new Expression("SUM(p.amount)"),
                'return'            => new Expression("SUM(p.`return`)"),
                'commission'        => new Expression("SUM(p.commission)"),
                'f_acquiring_fee'   => new Expression("SUM(p.f_acquiring_fee)"),
                'f_acceptance'      => new Expression("SUM(p.f_acceptance)"),
                'f_delivery'        => new Expression("SUM(p.f_delivery)"),
                'f_storage_fee'     => new Expression("SUM(p.f_storage_fee)"),
                'f_penalty'         => new Expression("SUM(p.f_penalty)"),
                'f_deduction'       => new Expression("SUM(p.f_deduction)"),
                'f_otziv'           => new Expression("SUM(p.ff_otziv)"),
                'f_adv'             => new Expression("SUM(p.ff_adv)"),
                'f_cashback'        => new Expression("SUM(p.f_cashback)"),
                'net_profit'        => new Expression("SUM(p.net_profit)"),
                'total_nds'         => new Expression("SUM(f_nds)"),
                'total_cost'        => new Expression("SUM(f_cost_price)"),
                'profit_before_tax' => new Expression("SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price) -SUM(ff_otziv)-SUM(ff_adv)"),
                'tax_amount'        => new Expression("GREATEST(0, SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price) -SUM(ff_otziv)-SUM(ff_adv)) * 0.07"),
                'clean_margin'      => new Expression("(SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price) -SUM(ff_otziv)-SUM(ff_adv)) - (GREATEST(0, SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price) -SUM(ff_otziv)-SUM(ff_adv)) * 0.07)"),
                'amount_per_item'   => new Expression("SUM(amount) / NULLIF(SUM(qnt), 0)"),
                'profit_per_item'   => new Expression("SUM(net_profit) / NULLIF(SUM(qnt), 0)"),
                'clear_per_item'    => new Expression("((SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)-SUM(ff_otziv)-SUM(ff_adv)) - (GREATEST(0, SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)-SUM(ff_otziv)-SUM(ff_adv)) * 0.07)) / NULLIF(SUM(qnt), 0)"),
                'cost_per_item'     => new Expression("SUM(f_cost_price) / NULLIF(SUM(qnt), 0)"), 
            ])
            ->from(['p' => 'agg_daily_summary'])
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = p.nm_id')
            ->where(['>=', 'p.sdate', new Expression('CURDATE() - INTERVAL 60 DAY')])
            ->groupBy('p.nm_id')
            ->orderBy(['clean_margin' => $sortOrder])
            ->limit(20);

        if ($filterZeroQnt) {
            $query->andHaving(['>', 'SUM(p.qnt)', 0]);
        }

        return new ArrayDataProvider([
            'allModels' => $query->all(),
            'pagination' => false,
        ]);
    }
}