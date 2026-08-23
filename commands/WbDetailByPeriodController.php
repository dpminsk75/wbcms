<?php
namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use app\components\WbApiStats;
use Yii;

class WbDetailByPeriodController extends Controller
{
    private function formatDate($dateStr)
    {
        if (empty($dateStr)) {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime($dateStr));
    }

    public function actionSync($from = null, $to = null)
    {
        ini_set('memory_limit', '1024M');

        if (Yii::$app->hasModule('debug')) {
            Yii::$app->getModule('debug')->instance = null;
        }
        Yii::$app->db->enableLogging = false;
        Yii::$app->db->enableProfiling = false;

        $dateFrom = $from ?: date('Y-m-d', strtotime('-7 days'));
        $dateTo = $to ?: date('Y-m-d');

        $companies = Yii::$app->companyManager->getActiveCompanies();
        if (empty($companies)) {
            $this->stderr("Ошибка: не найдено активных компаний в таблице companies.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Начало синхронизации за период: $dateFrom - $dateTo\n");

        $failedCompanyIds = [];
        foreach ($companies as $company) {
            $companyId = (int) $company['id'];
            $companyName = $company['name'];
            $token = $company['api_key'] ?? null;

            if (!$token) {
                $this->stdout("Пропуск компании '{$companyName}': отсутствует api_key.\n");
                continue;
            }

            Yii::$app->companyManager->setCurrentId($companyId);
            $this->stdout("\n>>> СИНХРОНИЗАЦИЯ КОМПАНИИ: {$companyName} (ID: {$companyId}) <<<\n");

            $exitCode = $this->syncCompany($dateFrom, $dateTo, $companyId, $token);
            if ($exitCode !== ExitCode::OK) {
                $failedCompanyIds[] = $companyId;
                $this->stderr("Компания '{$companyName}' (ID: {$companyId}) не синхронизирована, переходим к следующей.\n");
            }
        }

        $this->actionSyncAddresses();

        if (!empty($failedCompanyIds)) {
            $this->stderr("Синхронизация завершена с ошибками. Неудачные компании: " . implode(', ', $failedCompanyIds) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    private function syncCompany(string $dateFrom, string $dateTo, int $companyId, string $token): int
    {
        $service = new WbApiStats(['token' => $token]);
        $rrdid = 0;
        $totalProcessed = 0;

        while (true) {
            $loopStartTime = microtime(true);
            $this->stdout("Запрос с rrdid = $rrdid... ");

            try {
                $result = $service->getDetailByPeriod($dateFrom, $dateTo, $rrdid);
            } catch (\Throwable $e) {
                $this->stderr("Исключение при запросе к API WB для компании ID {$companyId}: {$e->getMessage()}\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $status = (int) ($result['status'] ?? 0);
            $data = $result['data'] ?? null;

            $this->stdout("HTTP Код: $status\n");

            if ($status === 429) {
                $this->stdout("Лимит запросов исчерпан (429). Ожидаем 60 сек...\n");
                sleep(65);
                continue;
            }

            if ($status === 204 || empty($data)) {
                $this->stdout("Все данные компании загружены. Итого: $totalProcessed строк.\n");
                break;
            }

            if ($status !== 200) {
                $this->stderr("Ошибка API (код $status). Прерывание для компании ID {$companyId}.\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $count = count($data);
            $this->stdout("Получено $count строк. Обработка...\n");

            $chunks = array_chunk($data, 2000);
            unset($data);

            $currentBatchProcessed = 0;
            $startTime = microtime(true);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $row) {
                    $preparedRow = [
                        'company_id' => $companyId,
                        'date_from' => $this->formatDate($row['date_from'] ?? null),
                        'date_to' => $this->formatDate($row['date_to'] ?? null),
                        'create_dt' => $this->formatDate($row['create_dt'] ?? null),
                        'order_dt' => $this->formatDate($row['order_dt'] ?? null),
                        'sale_dt' => $this->formatDate($row['sale_dt'] ?? null),
                        'rr_dt' => $this->formatDate($row['rr_dt'] ?? null),
                        'realizationreport_id' => $row['realizationreport_id'],
                        'suppliercontract_code' => $row['suppliercontract_code'] ?? null,
                        'rrd_id' => $row['rrd_id'],
                        'gi_id' => $row['gi_id'],
                        'dlv_prc' => $row['dlv_prc'],
                        'subject_name' => $row['subject_name'] ?? null,
                        'nm_id' => $row['nm_id'],
                        'brand_name' => $row['brand_name'] ?? null,
                        'sa_name' => $row['sa_name'] ?? null,
                        'ts_name' => $row['ts_name'] ?? null,
                        'barcode' => $row['barcode'] ?? null,
                        'doc_type_name' => $row['doc_type_name'] ?? null,
                        'quantity' => $row['quantity'] ?? 0,
                        'retail_price' => $row['retail_price'] ?? 0,
                        'retail_amount' => $row['retail_amount'] ?? 0,
                        'sale_percent' => $row['sale_percent'] ?? 0,
                        'commission_percent' => $row['commission_percent'] ?? 0,
                        'office_name' => $row['office_name'] ?? null,
                        'supplier_oper_name' => $row['supplier_oper_name'] ?? null,
                        'shk_id' => $row['shk_id'] ?? 0,
                        'retail_price_withdisc_rub' => $row['retail_price_withdisc_rub'] ?? 0,
                        'delivery_amount' => $row['delivery_amount'] ?? 0,
                        'return_amount' => $row['return_amount'] ?? 0,
                        'delivery_rub' => $row['delivery_rub'] ?? 0,
                        'gi_box_type_name' => $row['gi_box_type_name'] ?? null,
                        'ppvz_spp_prc' => $row['ppvz_spp_prc'] ?? 0,
                        'ppvz_kvw_prc_base' => $row['ppvz_kvw_prc_base'] ?? 0,
                        'ppvz_kvw_prc' => $row['ppvz_kvw_prc'] ?? 0,
                        'ppvz_sales_commission' => $row['ppvz_sales_commission'] ?? 0,
                        'ppvz_for_pay' => $row['ppvz_for_pay'] ?? 0,
                        'ppvz_reward' => $row['ppvz_reward'] ?? 0,
                        'ppvz_vw' => $row['ppvz_vw'] ?? 0,
                        'ppvz_vw_nds' => $row['ppvz_vw_nds'] ?? 0,
                        'ppvz_office_id' => $row['ppvz_office_id'] ?? null,
                        'ppvz_office_name' => $row['ppvz_office_name'] ?? null,
                        'ppvz_supplier_id' => $row['ppvz_supplier_id'] ?? null,
                        'ppvz_supplier_name' => $row['ppvz_supplier_name'] ?? null,
                        'ppvz_inn' => $row['ppvz_inn'] ?? null,
                        'declaration_number' => $row['declaration_number'] ?? null,
                        'sticker_id' => $row['sticker_id'] ?? null,
                        'site_country' => $row['site_country'] ?? null,
                        'penalty' => $row['penalty'] ?? 0,
                        'additional_payment' => $row['additional_payment'] ?? 0,
                        'rebill_logistic_cost' => $row['rebill_logistic_cost'] ?? 0,
                        'rebill_logistic_org' => $row['rebill_logistic_org'] ?? null,
                        'storage_fee' => $row['storage_fee'] ?? 0,
                        'deduction' => $row['deduction'] ?? 0,
                        'acceptance' => $row['acceptance'] ?? 0,
                        'bonus_type_name' => $row['bonus_type_name'] ?? null,
                        'kiz' => $row['kiz'] ?? null,
                        'srid' => $row['srid'] ?? null,
                        'product_discount_for_report' => $row['product_discount_for_report'] ?? 0,
                        'supplier_promo' => $row['supplier_promo'] ?? 0,
                        'sup_rating_prc_up' => $row['sup_rating_prc_up'] ?? 0,
                        'is_kgvp_v2' => $row['is_kgvp_v2'] ?? 0,
                        'srv_dbs' => (int) ($row['srv_dbs'] ?? 0),
                        'acquiring_fee' => $row['acquiring_fee'] ?? 0,
                        'acquiring_percent' => $row['acquiring_percent'] ?? 0,
                        'payment_processing' => $row['payment_processing'] ?? null,
                        'acquiring_bank' => $row['acquiring_bank'] ?? null,
                        'report_type' => $row['report_type'] ?? 0,
                        'delivery_method' => $row['delivery_method'] ?? null,
                        'wibes_wb_discount_percent' => $row['wibes_wb_discount_percent'] ?? 0,
                        'cashback_amount' => $row['cashback_amount'] ?? 0,
                        'cashback_discount' => $row['cashback_discount'] ?? 0,
                        'cashback_commission_change' => $row['cashback_commission_change'] ?? 0,
                        'order_uid' => $row['order_uid'] ?? null,
                        'payment_schedule' => $row['payment_schedule'] ?? 0,
                        'seller_promo_id' => $row['seller_promo_id'] ?? 0,
                        'seller_promo_discount' => $row['seller_promo_discount'] ?? 0,
                        'loyalty_id' => $row['loyalty_id'] ?? 0,
                        'loyalty_discount' => $row['loyalty_discount'] ?? 0,
                        'uuid_promocode' => $row['uuid_promocode'] ?? null,
                        'sale_price_promocode_discount_prc' => $row['sale_price_promocode_discount_prc'] ?? 0,
                    ];

                    Yii::$app->db->createCommand()
                        ->upsert('detail_by_period', $preparedRow, true)
                        ->execute();

                    $rrdid = $row['rrd_id'];
                    $currentBatchProcessed++;
                    $totalProcessed++;

                    if ($currentBatchProcessed % 100 === 0 || $currentBatchProcessed === $count) {
                        $percent = round(($currentBatchProcessed / $count) * 100);
                        $this->stdout("\r   Прогресс: $currentBatchProcessed из $count ($percent%) ");
                    }
                }
                unset($chunk);
            }

            $elapsed = microtime(true) - $loopStartTime;
            $neededWait = 61;
            $executionTime = round(microtime(true) - $startTime, 2);
            $this->stdout("\nГотово! Обработка заняла $executionTime сек. ");

            if ($elapsed < $neededWait) {
                $rest = round($neededWait - $elapsed, 2);
                $this->stdout("\nОбработка заняла $elapsed сек. Ждем остаток: $rest сек...\n");
                sleep((int) ceil($rest));
            } else {
                $this->stdout("\nОбработка заняла $elapsed сек (дольше лимита). Продолжаем без паузы.\n");
            }
        }

        return ExitCode::OK;
    }

    public function actionSyncAddresses()
    {
        Yii::$app->runAction('/address-processor/process');
        Yii::$app->runAction('/address/fix');
        Yii::$app->runAction('/geo-fill/full-repair');
        Yii::$app->runAction('/aggregate/update');
    }
}
