<?php

/*
Примеры вызовов в терминале / Cron:
  Полное обновление всех таблиц:
    php yii aggregate/update
    php yii aggregate/update 2025-01-01 2025-12-31

  Обновление только финансовой сводки:
    php yii aggregate/update-daily-summary
    php yii aggregate/update-daily-summary 2026-03-01 2026-03-31

  Сбор ТОЛЬКО расходов на рекламу (ИСТОРИЧЕСКИЕ ДАННЫЕ):
    php yii aggregate/update-adv-costs 2025-01-01 2025-12-31

  Сбор ТОЛЬКО расходов на отзывы (ИСТОРИЧЕСКИЕ ДАННЫЕ):
    php yii aggregate/update-feedbacks-cost 2025-01-01 2025-12-31

  Обновление только продаж или заказов:
    php yii aggregate/update-sales
    php yii aggregate/update-orders
*/

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Команда для обновления агрегатных таблиц (OLAP-кубов) на основе detail_by_period с поддержкой мультикомпанейности
 */
class AggregateController extends Controller
{
    /**
     * Вспомогательный метод для определения дат по умолчанию
     */
    private function prepareDates(&$dateFrom, &$dateTo)
    {
        if (!$dateFrom) {
            $dateFrom = date('Y-m-d', strtotime('-50 days'));
        }
        if (!$dateTo) {
            $dateTo = date('Y-m-d');
        }
    }

    /**
     * Запуск ПОЛНОГО обновления всех агрегатов по очереди (каждый шаг независимо)
     * @param string|null $dateFrom Начальная дата (ГГГГ-ММ-ДД)
     * @param string|null $dateTo Конечная дата (ГГГГ-ММ-ДД)
     */
    public function actionUpdate($dateFrom = null, $dateTo = null)
    {
        $this->prepareDates($dateFrom, $dateTo);
        $this->stdout("=== НАЧАЛО ПОЛНОГО ОБНОВЛЕНИЯ АГРЕГАТОВ ===\n", Console::FG_CYAN);
        $this->stdout("Период: с $dateFrom по $dateTo\n\n", Console::FG_CYAN);

        $failed = [];
        foreach ([
            ['runUpdateSales', 'Продажи (agg_sales_daily_sku)'],
            ['runUpdateOrders', 'Заказы (agg_orders_daily_sku)'],
            ['runUpdateDailySummary', 'Финансовая сводка (agg_daily_summary)'],
        ] as [$method, $label]) {
            try {
                $this->$method($dateFrom, $dateTo);
            } catch (\Throwable $e) {
                $failed[] = $label;
                $this->stderr("[ОШИБКА] {$label}: " . $e->getMessage() . "\n", Console::FG_RED);
            }
        }

        // Шаги-дополнения к сводке (ff_*). ВАЖНО: runUpdateDailySummary делает
        // INSERT ... ON DUPLICATE KEY UPDATE (ff_* сохраняются), но в любом случае
        // считаем их заново — после пересборки сводки они должны быть свежими.
        foreach ([
            ['update-feedbacks-cost', 'Отзывы (ff_otziv)'],
            ['update-adv-costs', 'Реклама (ff_adv)'],
            ['update-storage-costs', 'Платное хранение (ff_storage_fee)'],
        ] as [$route, $label]) {
            try {
                $code = (int) Yii::$app->runAction("aggregate/{$route}", [$dateFrom, $dateTo]);
                if ($code !== ExitCode::OK) {
                    $failed[] = $label;
                }
            } catch (\Throwable $e) {
                $failed[] = $label;
                $this->stderr("[ОШИБКА] {$label}: " . $e->getMessage() . "\n", Console::FG_RED);
            }
        }

        // Сбрасываем кэш дашборда
        Yii::$app->cache->delete('monthly_finance_dashboard_data');

        if (!empty($failed)) {
            $this->stderr("\n=== ОБНОВЛЕНИЕ ЗАВЕРШЕНО С ОШИБКАМИ: " . implode('; ', $failed) . " ===\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\n=== ВСЕ АГРЕГАТЫ УСПЕШНО ОБНОВЛЕНЫ ===\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * ОТДЕЛЬНЫЙ ВЫЗОВ: Обновление только сводной финансовой таблицы (agg_daily_summary)
     */
    public function actionUpdateDailySummary($dateFrom = null, $dateTo = null)
    {
        $this->prepareDates($dateFrom, $dateTo);
        try {
            $this->runUpdateDailySummary($dateFrom, $dateTo);
            Yii::$app->cache->delete('monthly_finance_dashboard_data');
            $this->stdout("Готово!\n", Console::FG_GREEN);
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * ОТДЕЛЬНЫЙ ВЫЗОВ: Обновление только таблицы продаж (agg_sales_daily_sku)
     */
    public function actionUpdateSales($dateFrom = null, $dateTo = null)
    {
        $this->prepareDates($dateFrom, $dateTo);
        try {
            $this->runUpdateSales($dateFrom, $dateTo);
            $this->stdout("Готово!\n", Console::FG_GREEN);
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * ОТДЕЛЬНЫЙ ВЫЗОВ: Обновление только таблицы заказов (agg_orders_daily_sku)
     */
    public function actionUpdateOrders($dateFrom = null, $dateTo = null)
    {
        $this->prepareDates($dateFrom, $dateTo);
        try {
            $this->runUpdateOrders($dateFrom, $dateTo);
            $this->stdout("Готово!\n", Console::FG_GREEN);
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /* ===================================================================
      ВНУТРЕННИЕ МЕТОДЫ ВЫПОЛНЕНИЯ SQL (работают без создания транзакций)
      ===================================================================
    */

    private function runUpdateDailySummary($dateFrom, $dateTo)
    {
        $this->stdout("-> Обновление финансовой сводки (agg_daily_summary) за $dateFrom - $dateTo...\n");

        $sql = "INSERT INTO `agg_daily_summary` (
                    company_id, sdate, nm_id, qnt, sales_qnt, return_qnt, amount, `return`, commission, f_retail_amount, f_acquiring_fee,
                    f_acceptance, f_delivery, f_storage_fee, f_penalty, f_deduction, f_otziv, f_adv, f_cashback, 
                    net_profit, f_nds, f_cost_price
                )
                SELECT * FROM (
                    SELECT 
                        p.company_id AS company_id,
                        DATE(p.sale_dt) AS sdate,
                        p.nm_id,
                        SUM(CASE 
                                WHEN p.supplier_oper_name = 'Продажа' THEN 1 
                                WHEN p.supplier_oper_name = 'Возврат' THEN -1 
                                ELSE 0 
                            END) AS qnt,
                        SUM(CASE WHEN p.supplier_oper_name = 'Продажа' THEN COALESCE(p.quantity, 0) ELSE 0 END) AS sales_qnt,
                        SUM(CASE WHEN p.supplier_oper_name = 'Возврат' THEN COALESCE(p.quantity, 0) ELSE 0 END) AS return_qnt,
                        SUM(CASE WHEN p.supplier_oper_name = 'Продажа' THEN p.retail_price_withdisc_rub ELSE 0 END) AS amount,
                        SUM(CASE WHEN p.supplier_oper_name = 'Возврат' THEN p.retail_price_withdisc_rub ELSE 0 END) AS `return`,
                        SUM(CASE WHEN p.supplier_oper_name = 'Продажа' THEN p.retail_price_withdisc_rub * p.commission_percent / 100 ELSE 0 END) AS commission,
                        SUM(p.retail_amount) AS f_retail_amount,
                        SUM(p.acquiring_fee) AS f_acquiring_fee,
                        SUM(p.acceptance) AS f_acceptance,
                        SUM(p.delivery_rub) AS f_delivery,
                        SUM(p.storage_fee) AS f_storage_fee,
                        SUM(p.penalty) AS f_penalty,
                        SUM(p.deduction) AS f_deduction,
                        SUM(CASE WHEN p.bonus_type_name LIKE 'Списание за отзыв%' THEN p.deduction ELSE 0 END) AS f_otziv,
                        SUM(CASE WHEN p.bonus_type_name LIKE '%WB Продвижение%' THEN p.deduction ELSE 0 END) AS f_adv,
                        SUM(p.cashback_amount) AS f_cashback,
                        (
                            SUM(CASE WHEN p.supplier_oper_name = 'Продажа' THEN p.retail_price_withdisc_rub ELSE 0 END) 
                            - SUM(CASE WHEN p.supplier_oper_name = 'Возврат' THEN p.retail_price_withdisc_rub ELSE 0 END)
                            - SUM(CASE WHEN p.supplier_oper_name = 'Продажа' THEN p.retail_price_withdisc_rub * p.commission_percent / 100 ELSE 0 END)
                            - SUM(p.acquiring_fee) 
                            - SUM(p.acceptance)
                            - SUM(p.delivery_rub) 
                            - SUM(p.storage_fee)
                            - SUM(p.penalty) 
                            - SUM(p.deduction) 
                            - SUM(p.cashback_amount)
                        ) AS net_profit,

                        -- 1. Выделение НДС: база = (оборот) − (себестоимость × кол-во реализованного)
                        CASE 
                            WHEN MAX(DATE(p.sale_dt)) < '2026-01-01' THEN 0.00
                            ELSE ROUND(
                                GREATEST(
                                    COALESCE(
                                        (
                                            SUM(CASE WHEN p.supplier_oper_name = 'Продажа' THEN p.retail_price_withdisc_rub ELSE 0 END) - 
                                            SUM(CASE WHEN p.supplier_oper_name = 'Возврат' THEN p.retail_price_withdisc_rub ELSE 0 END)
                                        )
                                        -
                                        (
                                            (SUM(CASE WHEN p.supplier_oper_name = 'Продажа' THEN 1 ELSE 0 END) - 
                                             SUM(CASE WHEN p.supplier_oper_name = 'Возврат' THEN 1 ELSE 0 END))
                                            * COALESCE((
                                                SELECT c.price 
                                                FROM wbcards_costs c 
                                                WHERE c.nmID = p.nm_id AND c.load_date <= MAX(DATE(p.sale_dt))
                                                ORDER BY c.load_date DESC, c.id DESC 
                                                LIMIT 1
                                            ), 0)
                                        )
                                    , 0), 0)
                                * COALESCE((
                                    SELECT n.nds 
                                    FROM wbcards_nds n 
                                    WHERE n.nmID = p.nm_id AND n.load_date <= MAX(DATE(p.sale_dt))
                                    ORDER BY n.load_date DESC, n.id DESC 
                                    LIMIT 1
                                ), 0)
                                / (100 + COALESCE((
                                    SELECT n.nds 
                                    FROM wbcards_nds n 
                                    WHERE n.nmID = p.nm_id AND n.load_date <= MAX(DATE(p.sale_dt))
                                    ORDER BY n.load_date DESC, n.id DESC 
                                    LIMIT 1
                                ), 0))
                            , 2)
                        END AS f_nds,

                        -- 2. Общая себестоимость
                        ROUND((
                            SUM(CASE WHEN p.supplier_oper_name = 'Продажа' THEN 1 ELSE 0 END) - 
                            SUM(CASE WHEN p.supplier_oper_name = 'Возврат' THEN 1 ELSE 0 END)
                        ) * COALESCE((
                            SELECT c.price 
                            FROM wbcards_costs c 
                            WHERE c.nmID = p.nm_id AND c.load_date <= MAX(DATE(p.sale_dt))
                            ORDER BY c.load_date DESC, c.id DESC 
                            LIMIT 1
                        ), 0), 2) AS f_cost_price

                    FROM `detail_by_period` p
                    WHERE p.sale_dt BETWEEN :from AND :to
                      AND p.company_id IS NOT NULL
                    GROUP BY p.company_id, DATE(p.sale_dt), p.nm_id
                ) AS dt
                ON DUPLICATE KEY UPDATE 
                    qnt = dt.qnt,
                    sales_qnt = dt.sales_qnt,
                    return_qnt = dt.return_qnt,
                    amount = dt.amount,
                    `return` = dt.`return`,
                    commission = dt.commission,
                    f_retail_amount = dt.f_retail_amount,
                    f_acquiring_fee = dt.f_acquiring_fee,
                    f_acceptance = dt.f_acceptance,
                    f_delivery = dt.f_delivery,
                    f_storage_fee = dt.f_storage_fee,
                    f_penalty = dt.f_penalty,
                    f_deduction = dt.f_deduction,
                    f_otziv = dt.f_otziv,
                    f_adv = dt.f_adv,
                    f_cashback = dt.f_cashback,
                    net_profit = dt.net_profit,
                    f_nds = dt.f_nds,
                    f_cost_price = dt.f_cost_price";

        Yii::$app->db->createCommand($sql, [
            ':from' => $dateFrom . ' 00:00:00',
            ':to' => $dateTo . ' 23:59:59'
        ])->execute();

        $this->stdout("Финансовая сводка успешно обновлена.\n", Console::FG_GREEN);
    }

    private function runUpdateSales($dateFrom, $dateTo)
    {
        $this->stdout("-> Обновление продаж (agg_sales_daily_sku) за $dateFrom - $dateTo...\n");

        $sql = "INSERT INTO agg_sales_daily_sku (
                    company_id, sale_date, nmID, subject_name, brand_name, 
                    sales_qty, returns_qty, retail_amount_sum, 
                    ppvz_for_pay_sum, delivery_rub_sum, penalty_sum
                )
                SELECT * FROM (
                    SELECT 
                        company_id as company_id,
                        DATE(sale_dt) as sale_date,
                        nm_id as nmID,
                        MAX(subject_name) as subject_name,
                        MAX(brand_name) as brand_name,
                        SUM(CASE WHEN doc_type_name = 'Продажа' THEN quantity ELSE 0 END) as sales_qty,
                        SUM(CASE WHEN doc_type_name = 'Возврат' THEN quantity ELSE 0 END) as returns_qty,
                        SUM(retail_amount) as retail_amount_sum,
                        SUM(ppvz_for_pay) as ppvz_for_pay_sum,
                        SUM(delivery_rub) as delivery_rub_sum,
                        SUM(penalty) as penalty_sum
                    FROM detail_by_period
                    WHERE sale_dt BETWEEN :from AND :to
                      AND company_id IS NOT NULL
                    GROUP BY company_id, DATE(sale_dt), nm_id
                ) AS dt
                ON DUPLICATE KEY UPDATE 
                    sales_qty = dt.sales_qty,
                    returns_qty = dt.returns_qty,
                    retail_amount_sum = dt.retail_amount_sum,
                    ppvz_for_pay_sum = dt.ppvz_for_pay_sum,
                    delivery_rub_sum = dt.delivery_rub_sum,
                    penalty_sum = dt.penalty_sum";

        Yii::$app->db->createCommand($sql, [
            ':from' => $dateFrom . ' 00:00:00',
            ':to' => $dateTo . ' 23:59:59'
        ])->execute();
    }

    private function runUpdateOrders($dateFrom, $dateTo)
    {
        $this->stdout("-> Обновление заказов (agg_orders_daily_sku) за $dateFrom - $dateTo...\n");

        $sql = "INSERT INTO agg_orders_daily_sku (
                    company_id, order_date, nmID, subject_name, brand_name, site_country,
                    orders_qty, retail_price_avg, retail_amount_sum, 
                    retail_with_disc_sum, ppvz_for_pay_sum, delivery_forecast_rub
                )
                SELECT * FROM (
                    SELECT 
                        company_id as company_id,
                        DATE(order_dt) as order_date,
                        nm_id as nmID,
                        MAX(subject_name) as subject_name,
                        MAX(brand_name) as brand_name,
                        site_country as site_country,
                        SUM(quantity) as orders_qty,
                        AVG(retail_price) as retail_price_avg,
                        SUM(retail_amount) as retail_amount_sum,
                        SUM(retail_price_withdisc_rub) as retail_with_disc_sum,
                        SUM(ppvz_for_pay) as ppvz_for_pay_sum,
                        SUM(quantity * dlv_prc) as delivery_forecast_rub
                    FROM detail_by_period
                    WHERE order_dt BETWEEN :from AND :to
                      AND doc_type_name = 'Продажа'
                      AND company_id IS NOT NULL
                    GROUP BY company_id, DATE(order_dt), nm_id, site_country
                ) AS dt
                ON DUPLICATE KEY UPDATE 
                    orders_qty = dt.orders_qty,
                    retail_amount_sum = dt.retail_amount_sum,
                    retail_with_disc_sum = dt.retail_with_disc_sum,
                    ppvz_for_pay_sum = dt.ppvz_for_pay_sum,
                    delivery_forecast_rub = dt.delivery_forecast_rub";

        Yii::$app->db->createCommand($sql, [
            ':from' => $dateFrom . ' 00:00:00',
            ':to' => $dateTo . ' 23:59:59'
        ])->execute();
    }

    /**
     * ОТДЕЛЬНЫЙ ВЫЗОВ: Привязка удержаний из detail_by_period к конкретным отзывам и обновление сводки по nmID из отзыва
     */
    public function actionUpdateFeedbacksCost($dateFrom = null, $dateTo = null)
    {
        $this->prepareDates($dateFrom, $dateTo);
        $this->stdout("-> Связывание платных отзывов и обновление стоимости за $dateFrom - $dateTo...\n", Console::FG_CYAN);

        $db = Yii::$app->db;
        
        $sqlDetails = "SELECT company_id, sale_dt, deduction, bonus_type_name 
                       FROM `detail_by_period`
                       WHERE sale_dt BETWEEN :from AND :to
                         AND `supplier_oper_name` = 'Удержание' 
                         AND `bonus_type_name` LIKE 'Списание за отзыв%'";

        $rows = $db->createCommand($sqlDetails, [
            ':from' => $dateFrom . ' 00:00:00',
            ':to' => $dateTo . ' 23:59:59'
        ])->queryAll();

        if (empty($rows)) {
            $this->stdout("Строк удержаний за отзывы за указанный период не найдено.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Найдено строк удержаний в отчете: " . count($rows) . "\n", Console::FG_GREEN);

        // Обнуляем поле ff_otziv за этот период перед пересчетом
        // только у компаний, чьи удержания найдены (чтобы не затереть значения других компаний)
        $affectedCompanyIds = array_values(array_unique(array_map('intval', array_column($rows, 'company_id'))));
        if (!empty($affectedCompanyIds)) {
            $inPlaceholders = [];
            $resetParams = [':from_date' => $dateFrom, ':to_date' => $dateTo];
            foreach ($affectedCompanyIds as $i => $cid) {
                $ph = ':cid' . $i;
                $inPlaceholders[] = $ph;
                $resetParams[$ph] = $cid;
            }
            $db->createCommand(
                "UPDATE `agg_daily_summary` 
                 SET `ff_otziv` = 0.00 
                 WHERE `sdate` BETWEEN :from_date AND :to_date 
                   AND `company_id` IN (" . implode(', ', $inPlaceholders) . ")",
                $resetParams
            )->execute();
        }

        $updatedFeedbacksCount = 0;
        $summaryUpdates = [];

        foreach ($rows as $row) {
            $companyId = $row['company_id'];
            $bonusTypeName = $row['bonus_type_name'];
            $deduction = (float)$row['deduction'];
            $saleDate = date('Y-m-d', strtotime($row['sale_dt']));

            if (preg_match('/Списание за отзыв\s+([^:]+):\s+акция\s+№(\d+)/u', $bonusTypeName, $matches)) {
                $feedbackId = trim($matches[1]);
                $actionId = trim($matches[2]);

                // Ищем отзыв строго в рамках конкретной компании
                $feedback = $db->createCommand("SELECT `nmID` FROM `wb_feedbacks` WHERE `id` = :id AND `company_id` = :company_id", [
                    ':id' => $feedbackId,
                    ':company_id' => $companyId
                ])->queryOne();

                if (!$feedback) {
                    $this->stdout("  [ПРОПУСК] Отзыв ID {$feedbackId} не найден для компании {$companyId} в wb_feedbacks.\n", Console::FG_YELLOW);
                    continue;
                }

                $realNmId = $feedback['nmID'];

                $updateFeedbackSql = "UPDATE `wb_feedbacks` 
                                      SET `is_pay` = 1, `f_cost` = :f_cost, `f_action` = :f_action 
                                      WHERE `id` = :id AND `company_id` = :company_id";
                
                $db->createCommand($updateFeedbackSql, [
                    ':f_cost' => $deduction,
                    ':f_action' => $actionId,
                    ':id' => $feedbackId,
                    ':company_id' => $companyId
                ])->execute();

                $updatedFeedbacksCount++;

                $key = $companyId . '_' . $saleDate . '_' . $realNmId;
                if (!isset($summaryUpdates[$key])) {
                    $summaryUpdates[$key] = [
                        'company_id' => $companyId,
                        'sdate' => $saleDate,
                        'nm_id' => $realNmId,
                        'total_cost' => 0
                    ];
                }
                $summaryUpdates[$key]['total_cost'] += $deduction;
            }
        }

        if (!empty($summaryUpdates)) {
            $this->stdout("Обновление финансовой сводки (поле ff_otziv) по проверенным nmID...\n", Console::FG_CYAN);
            
            $updateSummarySql = "INSERT INTO `agg_daily_summary` (company_id, sdate, nm_id, ff_otziv)
                                 VALUES (:company_id, :sdate, :nm_id, :cost) AS new
                                 ON DUPLICATE KEY UPDATE `ff_otziv` = new.`ff_otziv`";

            foreach ($summaryUpdates as $update) {
                $db->createCommand($updateSummarySql, [
                    ':company_id' => $update['company_id'],
                    ':cost' => $update['total_cost'],
                    ':sdate' => $update['sdate'],
                    ':nm_id' => $update['nm_id']
                ])->execute();
            }
        }

        Yii::$app->cache->delete('monthly_finance_dashboard_data');
        $this->stdout("Обработка завершена. Связано отзывов: {$updatedFeedbacksCount}\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * ОТДЕЛЬНЫЙ ПУБЛИЧНЫЙ ВЫЗОВ: Сбор затрат на рекламу по дням и SKU с учетом кабинетов
     */
    public function actionUpdateAdvCosts($dateFrom = null, $dateTo = null)
    {
        $this->prepareDates($dateFrom, $dateTo);
        $this->stdout("-> Обновление затрат на рекламу (ff_adv) на основе wb_campaign_stats_nms за $dateFrom - $dateTo...\n");

        // INNER JOIN связывает таблицы строго по parent_id, дополнительно группируем и пишем company_id
        $sql = "INSERT INTO `agg_daily_summary` (company_id, sdate, nm_id, ff_adv)
                SELECT * FROM (
                    SELECT 
                        nms.`company_id` AS company_id,
                        s.`date` AS sdate,
                        nms.`nm_id` AS nm_id,
                        SUM(nms.`sum`) AS ff_adv
                    FROM `wb_campaign_stats_nms` nms
                    INNER JOIN `wb_campaign_stats` s ON nms.`parent_id` = s.`id` AND nms.`company_id` = s.`company_id`
                    WHERE s.`date` BETWEEN :from AND :to
                    GROUP BY nms.`company_id`, s.`date`, nms.`nm_id`
                ) AS dt
                ON DUPLICATE KEY UPDATE 
                    ff_adv = dt.ff_adv";

        try {
            Yii::$app->db->createCommand($sql, [
                ':from' => $dateFrom,
                ':to' => $dateTo
            ])->execute();

            $this->stdout("Затраты на рекламу успешно интегрированы в финансовую сводку.\n", Console::FG_GREEN);
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Ошибка при обновлении рекламы: " . $e->getMessage() . "\n", Console::FG_RED);
            if (Yii::$app->db->getTransaction()) {
                throw $e;
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * ОТДЕЛЬНЫЙ ПУБЛИЧНЫЙ ВЫЗОВ: Платное хранение по позициям (ff_storage_fee)
     * из отчёта WB «Платное хранение» (wb_paid_storage).
     *
     * ff_storage_fee — для ОТЧЁТОВ ПО ТОВАРАМ; f_storage_fee (детализация продаж) — для месячных.
     * Upsert создаёт строки сводки там, где по товару не было продаж в этот день
     * (начисления месячные — их нельзя терять).
     */
    public function actionUpdateStorageCosts($dateFrom = null, $dateTo = null)
    {
        $this->prepareDates($dateFrom, $dateTo);
        $this->stdout("-> Обновление платного хранения (ff_storage_fee) по позициям за $dateFrom - $dateTo...\n");

        $sql = "INSERT INTO `agg_daily_summary` (company_id, sdate, nm_id, ff_storage_fee)
                SELECT * FROM (
                    SELECT 
                        company_id AS company_id,
                        `date` AS sdate,
                        nmId AS nm_id,
                        SUM(warehousePrice) AS ff_storage_fee
                    FROM `wb_paid_storage`
                    WHERE `date` BETWEEN :from AND :to AND nmId IS NOT NULL
                    GROUP BY company_id, `date`, nmId
                ) AS dt
                ON DUPLICATE KEY UPDATE 
                    ff_storage_fee = dt.ff_storage_fee";

        try {
            Yii::$app->db->createCommand($sql, [
                ':from' => $dateFrom,
                ':to' => $dateTo
            ])->execute();

            $this->stdout("Платное хранение успешно интегрировано в финансовую сводку.\n", Console::FG_GREEN);
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Ошибка при обновлении платного хранения: " . $e->getMessage() . "\n", Console::FG_RED);
            if (Yii::$app->db->getTransaction()) {
                throw $e;
            }
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}