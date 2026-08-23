<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\components\WbProfitService;

use yii\db\Expression;
use yii\db\Query;
use yii\data\ArrayDataProvider;

class WbProfitController extends Controller
{
    public function actionIndex()
    {
        // Инициализируем наш новый сервис
        $financeService = new WbProfitService();

        return $this->render('index', [
            'MonthlyProfitProvider' => $financeService->getMonthlyProfitProvider(),
            'top20SKUProvider'       => $financeService->getSkuProvider(SORT_DESC),
            'last20SKUProvider'      => $financeService->getSkuProvider(SORT_ASC, true),
        ]);
    }
    
    // actionTopProducts тоже можно переписать на использование $financeService при необходимости


    public function actionTopProducts()
    {
        $request = Yii::$app->request;

        // 1. Получаем и нормализуем параметры фильтрации
        $dateFrom = $request->get('dateFrom', date('Y-m-d', strtotime('-30 days')));
        $dateTo   = $request->get('dateTo', date('Y-m-d'));
        $sortBy   = $request->get('sortBy', 'qnt'); // qnt, amount, net_profit
        $limit    = (int)$request->get('limit', 20);  // 20, 50, 200

        // Валидация сортировки во избежание инъекций
        if (!in_array($sortBy, ['qnt', 'amount', 'net_profit','clean_margin'])) {
            $sortBy = 'qnt';
        }
        if (!in_array($limit, [20, 50, 200, 500, 1000])) {
            $limit = 20;
        }

        // 2. Строим быстрый агрегированный запрос
        $query = (new \yii\db\Query())
            ->select([
/*
                'nm_id'           => 'p.nm_id',
                'title'           => 'c.title',
                'brand'           => 'c.brand',
                'vendorCode'      => 'c.vendorCode',
                'qnt'             => new Expression("SUM(p.qnt)"),
                'amount'          => new Expression("SUM(p.amount)"),
                'return'          => new Expression("SUM(p.`return`)"),
                'commission'      => new Expression("SUM(p.commission)"),
                'f_delivery'      => new Expression("SUM(p.f_delivery)"),
                'f_adv'           => new Expression("SUM(p.f_adv)"),
                'f_penalty'       => new Expression("SUM(p.f_penalty)"),
                'net_profit'      => new Expression("SUM(p.net_profit)"),
                
                // Показатели на единицу товара
                'amount_per_item' => new Expression("SUM(p.amount) / NULLIF(SUM(p.qnt), 0)"),
                'profit_per_item' => new Expression("SUM(p.net_profit) / NULLIF(SUM(p.qnt), 0)"),
*/
                'nm_id'             => 'p.nm_id',
                'title'             => 'c.title', // Вывод названия товара из wbcards
                'brand'             => 'c.brand',
                'vendorCode'        => 'c.vendorCode', // Вывод бренда из wbcards
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
                'f_adv'             => new Expression("SUM(IFNULL(ff_adv, 0))"),
                'f_cashback'        => new Expression("SUM(p.f_cashback)"),
//                'net_profit'        => new Expression("SUM(p.net_profit) - -SUM(ff_otziv)-SUM(ff_adv)"),
                'net_profit'        => new Expression("SUM(p.net_profit) - SUM(IFNULL(ff_otziv, 0)) - SUM(IFNULL(ff_adv, 0))"),
                // 1. Сумма НДС из готового поля агрегата
                'total_nds'       => new Expression("SUM(IFNULL(f_nds, 0))"),
                
                // 2. Себестоимость из готового поля агрегата
                'total_cost'      => new Expression("SUM(IFNULL(f_cost_price, 0))"),
                
                // 4. Прибыль до налогов = net_profit - НДС - себестоимость
                'profit_before_tax' => new Expression("SUM(net_profit) - SUM(IFNULL(f_nds, 0)) - SUM(IFNULL(f_cost_price, 0)) - SUM(IFNULL(ff_otziv, 0)) - SUM(IFNULL(ff_adv, 0))"),
                
                // 5. Сумма налога на прибыль (7% от прибыли до налогов, если она больше 0)
                'tax_amount'      => new Expression("GREATEST(0, SUM(net_profit) - SUM(IFNULL(f_nds, 0)) - SUM(IFNULL(f_cost_price, 0)) - SUM(IFNULL(ff_otziv, 0)) - SUM(IFNULL(ff_adv, 0))) * 0.07"),
                
                // 6. Маржа (Итог) = Прибыль до налогов - Сумма налога на прибыль
                'clean_margin'    => new Expression("(SUM(net_profit) - SUM(IFNULL(f_nds, 0)) - SUM(IFNULL(f_cost_price, 0)) - SUM(IFNULL(ff_otziv, 0)) - SUM(IFNULL(ff_adv, 0))) - (GREATEST(0, SUM(net_profit) - SUM(IFNULL(f_nds, 0)) - SUM(IFNULL(f_cost_price, 0)) - SUM(IFNULL(ff_otziv, 0)) - SUM(IFNULL(ff_adv, 0))) * 0.07)"),

                'amount_per_item'   => new Expression("SUM(amount) / NULLIF(SUM(qnt), 0)"),
                'profit_per_item'   => new Expression("SUM(net_profit) / NULLIF(SUM(qnt), 0)"),
                'clear_per_item'    => new Expression("((SUM(net_profit) - SUM(IFNULL(f_nds, 0)) - SUM(IFNULL(f_cost_price, 0)) - SUM(IFNULL(ff_otziv, 0)) - SUM(IFNULL(ff_adv, 0))) - (GREATEST(0, SUM(net_profit) - SUM(IFNULL(f_nds, 0)) - SUM(IFNULL(f_cost_price, 0)) - SUM(IFNULL(ff_otziv, 0)) - SUM(IFNULL(ff_adv, 0))) * 0.07)) / NULLIF(SUM(qnt), 0)"),
                'cost_per_item'     => new Expression("SUM(IFNULL(f_cost_price, 0)) / NULLIF(SUM(qnt), 0)"), 

            ])
            ->from(['p' => 'agg_daily_summary'])
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = p.nm_id') // Используем c.nmID согласно структуре wbcards
            ->where(['between', 'p.sdate', $dateFrom, $dateTo])
            ->groupBy(['p.nm_id', 'c.title', 'c.brand', 'c.vendorCode'])
            ->orderBy([$sortBy => SORT_DESC])
            ->limit($limit);

        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels' => $query->all(),
            'pagination' => false, // Для ТОП-листов пагинация обычно отключается
            'sort' => false, // Сортировка уже жестко задана в БД по параметру фильтра
        ]);

        return $this->render('top_products', [
            'dataProvider' => $dataProvider,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
            'sortBy'       => $sortBy,
            'limit'        => $limit,
        ]);
    }

}