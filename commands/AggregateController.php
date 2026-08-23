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
     * Запуск ПОЛНОГО обновления всех агрегатов по очереди (в рамках одной транзакции)
     * @param string|null $dateFrom Начальная дата (ГГГГ-ММ-ДД)
     * @param string|null $dateTo Конечная дата (ГГГГ-ММ-ДД)
     */
    public function actionUpdate($dateFrom = null, $dateTo = null)
    {
        $this->prepareDates($dateFrom, $dateTo);
        $this->stdout("=== НАЧАЛО ПОЛНОГО ОБНОВЛЕНИЯ АГРЕГАТОВ ===\n", Console::FG_CYAN);
        $this->stdout("Период: с $dateFrom по $dateTo\n\n", Console::FG_CYAN);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $this->runUpdateSales($dateFrom, $dateTo);
            $this->runUpdateOrders($dateFrom, $dateTo);
            $this->runUpdateDailySummary($dateFrom, $dateTo);

            $transaction->commit();
            
            // Сбрасываем кэш дашборда
            Yii::$app->cache->delete('monthly_finance_dashboard_data');
            
            $this->stdout("\n=== ВСЕ АГРЕГАТЫ УСПЕШНО ОБНОВЛЕНЫ ===\n", Console::FG_GREEN);
            return ExitCode::OK;
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->stderr("\n[ОШИБКА ОБЩЕЙ ТРАНЗАКЦИИ]: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
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
                    company_id, sdate, nm_id, qnt, amount, `return`, commission, f_retail_amount, f_acquiring_fee,
                    f_acceptance, f_delivery, f_storage_fee, f_penalty, f_deduction, f_otziv, f_adv, f_cashback, 
                    net_profit, f_nds, f_cost_price
                )
                SELECT 
                    COALESCE(p.company_id, 1) AS company_id,
                    DATE(p.sale_dt) AS sdate,
                    p.nm_id,
                    SUM(CASE 
                            WHEN p.supplier_oper_name = 'Продажа' THEN 1 
                            WHEN p.supplier_oper_name = 'Возврат' THEN -1 
                            ELSE 0 
                        END) AS qnt,
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

                    -- 1. Выделение НДС с учетом company_id
                    CASE 
                        WHEN MAX(DATE(p.sale_dt)) < '2026-01-01' THEN 0.00
                        ELSE ROUND((
                            SUM(CASE WHEN p.supplier_oper_name = 'Продажа' THEN p.retail_price_withdisc_rub ELSE 0 END) - 
                            SUM(CASE WHEN p.supplier_oper_name = 'Возврат' THEN p.retail_price_withdisc_rub ELSE 0 END)
                        ) * COALESCE((
                            SELECT n.nds 
                            FROM wbcards_nds n 
                            WHERE n.nmID = p.nm_id AND n.load_date <= MAX(DATE(p.sale_dt))
                            ORDER BY n.load_date DESC, n.id DESC 
                            LIMIT 1
                        ), 0) / (100 + COALESCE((
                            SELECT n.nds 
                            FROM wbcards_nds n 
                            WHERE n.nmID = p.nm_id AND n.load_date <= MAX(DATE(p.sale_dt))
                            ORDER BY n.load_date DESC, n.id DESC 
                            LIMIT 1
                        ), 0)), 2)
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
                GROUP BY p.company_id, DATE(p.sale_dt), p.nm_id
                ON DUPLICATE KEY UPDATE 
                    qnt = VALUES(qnt),
                    amount = VALUES(amount),
                    `return` = VALUES(`return`),
                    commission = VALUES(commission),
                    f_retail_amount = VALUES(f_retail_amount),
                    f_acquiring_fee = VALUES(f_acquiring_fee),
                    f_acceptance = VALUES(f_acceptance),
                    f_delivery = VALUES(f_delivery),
                    f_storage_fee = VALUES(f_storage_fee),
                    f_penalty = VALUES(f_penalty),
                    f_deduction = VALUES(f_deduction),
                    f_otziv = VALUES(f_otziv),
                    f_adv = VALUES(f_adv),
                    f_cashback = VALUES(f_cashback),
                    net_profit = VALUES(net_profit),
                    f_nds = VALUES(f_nds),
                    f_cost_price = VALUES(f_cost_price)";

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
                SELECT 
                    COALESCE(company_id, 1) as company_id,
                    DATE(sale_dt) as sale_date,
                    nm_id as nmID,
                    MAX(subject_name),
                    MAX(brand_name),
                    SUM(CASE WHEN doc_type_name = 'Продажа' THEN quantity ELSE 0 END),
                    SUM(CASE WHEN doc_type_name = 'Возврат' THEN quantity ELSE 0 END),
                    SUM(retail_amount),
                    SUM(ppvz_for_pay),
                    SUM(delivery_rub),
                    SUM(penalty)
                FROM detail_by_period
                WHERE sale_dt BETWEEN :from AND :to
                GROUP BY company_id, DATE(sale_dt), nm_id
                ON DUPLICATE KEY UPDATE 
                    sales_qty = VALUES(sales_qty),
                    returns_qty = VALUES(returns_qty),
                    retail_amount_sum = VALUES(retail_amount_sum),
                    ppvz_for_pay_sum = VALUES(ppvz_for_pay_sum),
                    delivery_rub_sum = VALUES(delivery_rub_sum),
                    penalty_sum = VALUES(penalty_sum)";

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
                SELECT 
                    COALESCE(company_id, 1) as company_id,
                    DATE(order_dt) as order_date,
                    nm_id as nmID,
                    MAX(subject_name),
                    MAX(brand_name),
                    site_country,
                    SUM(quantity),
                    AVG(retail_price),
                    SUM(retail_amount),
                    SUM(retail_price_withdisc_rub),
                    SUM(ppvz_for_pay),
                    SUM(quantity * dlv_prc)
                FROM detail_by_period
                WHERE order_dt BETWEEN :from AND :to
                  AND doc_type_name = 'Продажа'
                GROUP BY company_id, DATE(order_dt), nm_id, site_country
                ON DUPLICATE KEY UPDATE 
                    orders_qty = VALUES(orders_qty),
                    retail_amount_sum = VALUES(retail_amount_sum),
                    retail_with_disc_sum = VALUES(retail_with_disc_sum),
                    ppvz_for_pay_sum = VALUES(ppvz_for_pay_sum),
                    delivery_forecast_rub = VALUES(delivery_forecast_rub)";

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
        $db->createCommand("UPDATE `agg_daily_summary` 
                            SET `ff_otziv` = 0.00 
                            WHERE `sdate` BETWEEN :from_date AND :to_date", [
            ':from_date' => $dateFrom,
            ':to_date' => $dateTo
        ])->execute();

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
                                 VALUES (:company_id, :sdate, :nm_id, :cost)
                                 ON DUPLICATE KEY UPDATE `ff_otziv` = VALUES(`ff_otziv`)";

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
                SELECT 
                    nms.`company_id`,
                    s.`date` AS sdate,
                    nms.`nm_id` AS nm_id,
                    SUM(nms.`sum`) AS ff_adv
                FROM `wb_campaign_stats_nms` nms
                INNER JOIN `wb_campaign_stats` s ON nms.`parent_id` = s.`id` AND nms.`company_id` = s.`company_id`
                WHERE s.`date` BETWEEN :from AND :to
                GROUP BY nms.`company_id`, s.`date`, nms.`nm_id`
                ON DUPLICATE KEY UPDATE 
                    ff_adv = VALUES(ff_adv)";

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
}