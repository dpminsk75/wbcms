<?php
namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use app\components\WbApiFinance;
use yii\helpers\Console;
use Yii;

class WbDetailFinanceController extends Controller 
{
    /**
     * Преобразование строки даты в формат MySQL
     */
    private function formatDate($dateStr)
    {
        if (empty($dateStr)) return null;
        return date('Y-m-d H:i:s', strtotime($dateStr));
    }

    /**
     * Безопасное приведение к float для DECIMAL полей базы данных
     */
    private function formatDecimal($value)
    {
        if ($value === null || $value === '') {
            return 0.00;
        }
        $cleanValue = str_replace(',', '.', $value);
        return (float)$cleanValue;
    }

    /**
     * Синхронизация финансовых отчетов по всем активным компаниям
     * * Запуск: php yii wb-detail-finance/sync
     */
    public function actionSync($from = null, $to = null)
    {
        ini_set('memory_limit', '1024M');

        if (Yii::$app->hasModule('debug')) {
            Yii::$app->getModule('debug')->instance = null;
        }
        Yii::$app->db->enableLogging = false;
        Yii::$app->db->enableProfiling = false;

        $db = Yii::$app->db;

        // 1. Получаем список всех активных компаний из таблицы companies
        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all($db);

        if (empty($companies)) {
            $this->stderr("Не найдено активных компаний в таблице companies для синхронизации финансов.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $dateFrom = $from ?: date('Y-m-d', strtotime('-7 days'));
        $dateTo = $to ?: date('Y-m-d');
        
        $totalProcessedAllCompanies = 0;

        // 2. Запускаем цикл по кабинетам
        foreach ($companies as $company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
            $token = $company['api_key'] ?? null;

            if (!$token) {
                $this->stdout("[-] Пропуск компании '{$companyName}' (ID: {$companyId}): отсутствует токен api_key.\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("\n>>> НАЧАЛО ФИНАНСОВОЙ СИНХРОНИЗАЦИИ ДЛЯ: {$companyName} (ID: {$companyId}) <<<\n", Console::FG_CYAN);
            $this->stdout("Период: $dateFrom - $dateTo\n");

            // Токен компании передаётся явно в конструктор компонента —
            // без побочных эффектов через глобальные Yii::$app->params.
            $service = new WbApiFinance(['token' => $token]);
            $rrdid = 0;
            $totalProcessed = 0;
            // Набор уникальных дат (rr_dt), затронутых за эту синхронизацию —
            // по ним после загрузки пересчитаем detail_by_period_forecast.
            $affectedDates = [];
            // Набор уникальных srid, затронутых за эту синхронизацию —
            // по ним после загрузки проставим факты в wb_order (выкуп,
            // логистика, комиссия, эквайринг, кешбэк).
            $affectedSrids = [];

            while (true) {
                $loopStartTime = microtime(true);
                $this->stdout("Запрос [{$companyName}] с rrdid = $rrdid... ");

                try {
                    $result = $service->getDetailByPeriod($dateFrom, $dateTo, $rrdid);
                } catch (\Throwable $e) {
                    $this->stderr("\nИсключение при запросе к API WB для '{$companyName}': " . $e->getMessage() . "\n", Console::FG_RED);
                    break; 
                }

                $status = (int)($result['status'] ?? 0);
                $data = $result['data'] ?? null;
                $this->stdout("HTTP Код: $status\n");

                if ($status === 429) {
                    $this->stdout("Лимит запросов исчерпан (429). Ожидаем 65 сек...\n", Console::FG_YELLOW);
                    sleep(65);
                    continue;
                }

                if ($status === 204 || empty($data) || !is_array($data)) {
                    $this->stdout("Все данные для компании '{$companyName}' загружены. Итого: $totalProcessed строк.\n", Console::FG_GREEN);
                    break;
                }

                if ($status !== 200) {
                    $this->stderr("Ошибка API для компании '{$companyName}' (код $status). Пропуск кабинета.\n", Console::FG_RED);
                    continue 2; 
                }

                $count = count($data);
                $this->stdout("Получено $count строк. Обработка...\n");

                $chunks = array_chunk($data, 2000); 
                unset($data);

                $startTime = microtime(true);
                $currentBatchProcessed = 0; 

                foreach ($chunks as $chunk) {
                    foreach ($chunk as $row) {
                        
                        $preparedRow = [
                            // Привязка к конкретной компании
                            'company_id' => $companyId,

                            // BIGINT поля
                            'realizationreport_id' => isset($row['reportId']) ? $row['reportId'] : null,
                            'rrd_id' => isset($row['rrdId']) ? $row['rrdId'] : 0, 
                            'gi_id' => isset($row['giId']) ? $row['giId'] : null, 
                            'shk_id' => isset($row['shkId']) ? $row['shkId'] : null, 
                            'product_id' => isset($row['productId']) ? $row['productId'] : null,
                            'rid' => isset($row['rid']) ? $row['rid'] : null,
                            'seller_promo_id' => isset($row['sellerPromoId']) ? $row['sellerPromoId'] : null, 
                            'loyalty_id' => isset($row['loyaltyId']) ? $row['loyaltyId'] : null, 

                            // DATETIME поля
                            'date_from' => $this->formatDate($row['dateFrom'] ?? null), 
                            'date_to' => $this->formatDate($row['dateTo'] ?? null), 
                            'create_dt' => $this->formatDate($row['createDate'] ?? null),
                            'order_dt' => $this->formatDate($row['orderDt'] ?? null), 
                            'sale_dt' => $this->formatDate($row['saleDt'] ?? null), 
                            'rr_dt' => $this->formatDate($row['rrDate'] ?? null), 

                            // INT и TINYINT поля
                            'nm_id' => isset($row['nmId']) ? (int)$row['nmId'] : null, 
                            'quantity' => isset($row['quantity']) ? (int)$row['quantity'] : null, 
                            'sale_percent' => isset($row['salePercent']) ? (int)$row['salePercent'] : null, 
                            'is_kgvp_v2' => isset($row['isKgvpV2']) ? (int)$row['isKgvpV2'] : null, 
                            'srv_dbs' => isset($row['srvDbs']) ? (int)$row['srvDbs'] : null, 
                            'report_type' => isset($row['reportType']) ? (int)$row['reportType'] : null, 
                            'payment_schedule' => isset($row['paymentSchedule']) ? (int)$row['paymentSchedule'] : null, 
                            'ppvz_office_id' => isset($row['ppvzOfficeId']) ? (int)$row['ppvzOfficeId'] : null, 

                            // DECIMAL(10,2) поля
                            'retail_price' => $this->formatDecimal($row['retailPrice'] ?? 0), 
                            'retail_amount' => $this->formatDecimal($row['retailAmount'] ?? 0), 
                            'commission_percent' => $this->formatDecimal($row['commissionPercent'] ?? 0), 
                            'retail_price_withdisc_rub' => $this->formatDecimal($row['retailPriceWithDisc'] ?? 0), 
                            'delivery_amount' => $this->formatDecimal($row['deliveryAmount'] ?? 0), 
                            'return_amount' => $this->formatDecimal($row['returnAmount'] ?? 0), 
                            'delivery_rub' => $this->formatDecimal($row['deliveryService'] ?? 0), 
                            'ppvz_spp_prc' => $this->formatDecimal($row['spp'] ?? 0), 
                            'ppvz_kvw_prc_base' => $this->formatDecimal($row['kvwBase'] ?? 0), 
                            'ppvz_kvw_prc' => $this->formatDecimal($row['kvw'] ?? 0), 
                            'ppvz_sales_commission' => $this->formatDecimal($row['ppvzSalesCommission'] ?? 0), 
                            'ppvz_for_pay' => $this->formatDecimal($row['forPay'] ?? 0), 
                            'ppvz_reward' => $this->formatDecimal($row['ppvzReward'] ?? 0), 
                            'ppvz_vw' => $this->formatDecimal($row['vw'] ?? 0), 
                            'ppvz_vw_nds' => $this->formatDecimal($row['vwNds'] ?? 0), 
                            'penalty' => $this->formatDecimal($row['penalty'] ?? 0), 
                            'additional_payment' => $this->formatDecimal($row['additionalPayment'] ?? 0), 
                            'rebill_logistic_cost' => $this->formatDecimal($row['rebillLogisticCost'] ?? 0), 
                            'storage_fee' => $this->formatDecimal($row['paidStorage'] ?? 0), 
                            'deduction' => $this->formatDecimal($row['deduction'] ?? 0), 
                            'acceptance' => $this->formatDecimal($row['paidAcceptance'] ?? 0),
                            'product_discount_for_report' => $this->formatDecimal($row['productDiscountForReport'] ?? 0), 
                            'supplier_promo' => $this->formatDecimal($row['supplierPromo'] ?? 0), 
                            'sup_rating_prc_up' => $this->formatDecimal($row['supRatingUp'] ?? 0), 
                            'acquiring_fee' => $this->formatDecimal($row['acquiringFee'] ?? 0), 
                            'acquiring_percent' => $this->formatDecimal($row['acquiringPercent'] ?? 0), 
                            'wibes_wb_discount_percent' => $this->formatDecimal($row['wibesDiscountPercent'] ?? 0), 
                            'cashback_amount' => $this->formatDecimal($row['cashbackAmount'] ?? 0), 
                            'cashback_discount' => $this->formatDecimal($row['cashbackDiscount'] ?? 0), 
                            'cashback_commission_change' => $this->formatDecimal($row['cashbackCommissionChange'] ?? 0), 
                            'seller_promo_discount' => $this->formatDecimal($row['sellerPromoDiscount'] ?? 0), 
                            'loyalty_discount' => $this->formatDecimal($row['loyaltyDiscount'] ?? 0), 
                            'sale_price_promocode_discount_prc' => $this->formatDecimal($row['salePricePromocodeDiscountPrc'] ?? 0), 
                            'dlv_prc' => $this->formatDecimal($row['dlvPrc'] ?? 0), 

                            // VARCHAR / Текстовые поля
                            'subject_name' => $row['subjectName'] ?? null, 
                            'brand_name' => $row['brandName'] ?? null, 
                            'sa_name' => $row['vendorCode'] ?? null, 
                            'ts_name' => $row['techSize'] ?? null, 
                            'barcode' => $row['sku'] ?? null, 

                            'doc_type_name' => $row['docTypeName'] ?? null, 
                            'office_name' => $row['officeName'] ?? null, 
                            'supplier_oper_name' => $row['sellerOperName'] ?? null, 
                            'gi_box_type_name' => $row['giBoxTypeName'] ?? null, 
                            'ppvz_office_name' => $row['ppvzOfficeName'] ?? null, 
                            'ppvz_inn' => $row['ppvzSupplierInn'] ?? null, 
                            'declaration_number' => $row['declarationNumber'] ?? null, 
                            'sticker_id' => $row['stickerId'] ?? null, 
                            'srid' => $row['srid'] ?? null, 
                            'payment_processing' => $row['paymentProcessing'] ?? null, 
                            'acquiring_bank' => $row['acquiringBank'] ?? null, 
                            'delivery_method' => $row['deliveryMethod'] ?? null, 
                            'order_uid' => $row['orderUid'] ?? null, 
                            'uuid_promocode' => $row['uuidPromocode'] ?? null, 
                            'ppvz_supplier_name' => $row['ppvzSupplierName'] ?? null, 
                            'rebill_logistic_org' => $row['rebillLogisticOrg'] ?? null, 
                            'kiz' => $row['kiz'] ?? null, 

                            'bonus_type_name' => $row['bonusTypeName'] ?? null, 
                            'site_country' => $row['country'] ?? null, 
                            'is_b2b' => isset($row['isB2b']) ? (int)($row['isB2b'] === true ? 1 : $row['isB2b']) : 0, 
                            'installment_cofinancing_amount' => $this->formatDecimal($row['installmentCofinancingAmount'] ?? 0), 
                            'sale_price_affiliated_discount_prc' => $this->formatDecimal($row['salePriceAffiliatedDiscountPrc'] ?? 0), 
                            'sale_price_wholesale_discount_prc' => $this->formatDecimal($row['salePriceWholesaleDiscountPrc'] ?? 0), 
                            'currency' => $row['currency'] ?? null, 
                            'title' => $row['title'] ?? null, 
                            'trbx_id' => $row['trbxId'] ?? null, 
                            'article_substitution' => $row['articleSubstitution'] ?? null, 

                            'suppliercontract_code' => null,  
                            'ppvz_supplier_id' => null,  
                            'address_id' => null, 
                        ];

                        // Выполняем UPSERT. Оборачиваем в try/catch: одна проблемная
                        // строка (например, конфликт данных или превышение длины
                        // поля) не должна ронять всю многочасовую синхронизацию
                        // по всем компаниям.
                        try {
                            $db->createCommand()
                                ->upsert('detail_by_period', $preparedRow, true)
                                ->execute();

                            // Запоминаем день отгрузки (rr_dt) — по нему
                            // потом пересчитаем forecast-агрегаты. Только
                            // для успешно сохранённых строк, чтобы не
                            // гонять пересчёт по данным, которых нет в базе.
                            if (!empty($preparedRow['rr_dt'])) {
                                $statDate = substr($preparedRow['rr_dt'], 0, 10);
                                $affectedDates[$statDate] = true;
                            }
                            if (!empty($preparedRow['srid'])) {
                                // Важно: srid кладём как ЗНАЧЕНИЕ, а не как
                                // ключ массива. Если использовать его как
                                // ключ ($arr[$srid] = true), PHP молча
                                // приводит "чисто цифровые" строки-ключи к
                                // int (канонические десятичные строки без
                                // точек). Дальше такой int уходит в PDO как
                                // PARAM_INT, и MySQL при сравнении
                                // "d.srid IN (...)" пытается привести VARCHAR
                                // d.srid к DOUBLE для КАЖДОЙ строки — и падает
                                // с "Truncated incorrect DOUBLE value" на
                                // любом другом srid, где формат содержит две
                                // точки (не парсится как число).
                                $affectedSrids[] = (string)$preparedRow['srid'];
                            }
                        } catch (\Throwable $e) {
                            $rrdIdForLog = $row['rrdId'] ?? 'unknown';
                            $this->stderr("\nОшибка сохранения rrdId {$rrdIdForLog} для '{$companyName}': " . $e->getMessage() . "\n", Console::FG_RED);
                        }

                        // Курсор пагинации двигаем в любом случае, чтобы не зациклиться
                        // повторно на той же проблемной записи при следующем запуске.
                        $rrdid = isset($row['rrdId']) ? $row['rrdId'] : $rrdid; 
                        
                        $currentBatchProcessed++;
                        $totalProcessed++;

                        if ($currentBatchProcessed % 100 === 0 || $currentBatchProcessed === $count) {
                            $percent = round(($currentBatchProcessed / $count) * 100);
                            $this->stdout("\r   Прогресс кабинета: $currentBatchProcessed из $count ($percent%) ");
                        }
                    }
                    unset($chunk);
                }

                $elapsed = microtime(true) - $loopStartTime;
                $neededWait = 61; 

                $executionTime = round(microtime(true) - $startTime, 2);
                $this->stdout("\nЧасть отчета загружена! Время чанка: $executionTime сек. ");

                // Контролируем частоту запросов к API Wildberries
                if ($elapsed < $neededWait) {
                    $rest = round($neededWait - $elapsed, 2);
                    $this->stdout("\nЖдем остаток лимита времени: $rest сек...\n");
                    sleep((int)ceil($rest)); 
                } else {
                    $this->stdout("\nПродолжаем без паузы.\n");
                }
            }

            // Дедуплицируем srid именно как СТРОКИ (SORT_STRING) — на случай,
            // если один и тот же srid встретился в нескольких строках чанка.
            $affectedSrids = array_values(array_unique($affectedSrids, SORT_STRING));

            // Пересчитываем forecast-агрегаты (detail_by_period_forecast)
            // только по затронутым датам этой синхронизации — дёшево
            // даже на многомиллионной detail_by_period.
            if (!empty($affectedDates)) {
                $this->stdout("Пересчёт forecast-агрегатов для '{$companyName}' по " . count($affectedDates) . " дате(ам)...\n", Console::FG_CYAN);
                $this->updateForecastAggregates($db, $companyId, $affectedDates);
            }

            // Проставляем факты в wb_order (выкуп): логистика/возврат,
            // комиссия, эквайринг, кешбэк — по затронутым srid этой синхронизации.
            if (!empty($affectedSrids)) {
                $this->stdout("Обновление фактов wb_order для '{$companyName}' по " . count($affectedSrids) . " srid...\n", Console::FG_CYAN);
                $this->updateWbOrderFacts($db, $companyId, $affectedSrids);
            }

            $totalProcessedAllCompanies += $totalProcessed;
            $this->stdout(">>> Финансовая синхронизация кабинета '{$companyName}' завершена успешно. <<<\n", Console::FG_GREEN);
        }

        $this->stdout("\n[ОК] Сбор всех финансовых отчетов завершен. Всего обработано строк: $totalProcessedAllCompanies\n", Console::FG_GREEN);
        
        // 3. Запуск пост-обработки адресов и агрегатов.
        // Основные финансовые данные к этому моменту уже сохранены — если
        // пост-обработка упадёт, не хотим маскировать это под полный провал
        // синхронизации, но и не хотим потерять информацию об ошибке.
        $this->stdout("\nЗапуск пост-обработки адресов, отзывов и финансовых кубов...\n", Console::FG_CYAN);
        try {
            $this->actionSyncAddresses();
        } catch (\Throwable $e) {
            $this->stderr("\nОшибка в пост-обработке (адреса/агрегаты): " . $e->getMessage() . "\n", Console::FG_RED);
            $this->stdout("\n[OK, с предупреждением] Финансовые данные сохранены, но пост-обработка завершилась с ошибкой.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        return ExitCode::OK;
    }

    /**
     * Проставляет в wb_order факты, полученные после "выкупа" товара —
     * из detail_by_period, по конкретным supplier_oper_name:
     *   Логистика (delivery_amount>0)                 -> delivery_rub/delivery_method
     *   Логистика (return_amount>0)                   -> return_rub
     *   Продажа                                        -> commission_percent/commission_fee/acquiring_percent/acquiring_fee
     *   Сумма баллов, удержанных за покупку товаров    -> cashback_amount
     *
     * Важно: доставка и возврат — это две независимые, параллельно
     * существующие строки одного srid (а не корректировки друг друга):
     * доставка до ПВЗ есть всегда, а возврат на склад добавляется отдельной
     * строкой, если товар не выкупили. Поэтому "последняя запись" (по
     * максимальному rrd_id) берётся ОТДЕЛЬНО в разрезе delivery_amount>0
     * и ОТДЕЛЬНО в разрезе return_amount>0, а не одна общая на весь тип
     * "Логистика". Для Продажи и Кешбэка корректировки такого рода не
     * ожидаются, поэтому там просто берём самую свежую запись на srid.
     *
     * @param \yii\db\Connection $db
     * @param int $companyId
     * @param array $affectedSrids Плоский массив значений srid (не ассоциативный —
     *   срид никогда не используется как ключ массива, см. комментарий в actionSync
     *   про авто-приведение "числовых" строк-ключей к int в PHP).
     */
    private function updateWbOrderFacts($db, $companyId, array $affectedSrids)
    {
        // Явно приводим каждое значение к строке — дополнительная защита на
        // случай, если массив придёт из другого места вызова не в "чистом"
        // виде (например, после json_decode с числовыми srid).
        $srids = array_values(array_unique(array_map('strval', $affectedSrids), SORT_STRING));
        if (empty($srids)) {
            return;
        }

        $totalAffected = 0;
        // Чанки по 500 srid — с запасом по длине пакета и по времени блокировки строк.
        $sridChunks = array_chunk($srids, 500);

        foreach ($sridChunks as $chunkIndex => $chunk) {
            $params = [':company_id' => $companyId];
            $placeholders = [];
            // Имена плейсхолдеров дополняем ведущими нулями до фиксированной
            // длины. Это принципиально: без выравнивания ":s1" оказывается
            // текстовым префиксом ":s10", ":s11", ..., ":s100"-":s199" и при
            // подстановке значений в SQL один placeholder может "наехать" на
            // другой, склеивая куски двух разных srid в один мусорный токен
            // (именно так и родилась ошибка "Truncated incorrect DOUBLE
            // value: 'eAh.r7cd8d27293084368aeb68a7f1e848e26.0.0'").
            $chunkWidth = strlen((string)(count($chunk) - 1));
            foreach ($chunk as $i => $srid) {
                $key = ':s' . str_pad((string)$i, $chunkWidth, '0', STR_PAD_LEFT);
                $placeholders[] = $key;
                // Явный (string) — на случай, если где-то выше в цепочку
                // всё же просочится нестроковое значение: PDO должен
                // биндить srid только как PARAM_STR, никогда как число.
                $params[$key] = (string)$srid;
            }
            $inList = implode(',', $placeholders);

            // 1а) Логистика: доставка до ПВЗ (delivery_amount > 0).
            // Это отдельная, независимая от возврата запись — у одного srid
            // может параллельно существовать и строка доставки, и строка
            // возврата (если товар не выкупили), поэтому берём последнюю
            // запись ОТДЕЛЬНО в разрезе каждого условия, а не одну общую
            // "последнюю логистику" на srid.
            $sqlDelivery = "
                UPDATE wb_order o
                JOIN (
                    SELECT t.srid, t.delivery_rub, t.delivery_method
                    FROM (
                        SELECT d.srid, d.delivery_rub, d.delivery_method,
                               ROW_NUMBER() OVER (PARTITION BY d.srid ORDER BY d.rrd_id DESC) AS rn
                        FROM detail_by_period d
                        WHERE d.company_id = :company_id
                          AND d.supplier_oper_name = 'Логистика'
                          AND d.delivery_amount > 0
                          AND d.srid IN ($inList)
                    ) t
                    WHERE t.rn = 1
                ) t ON t.srid = o.srid
                SET
                    o.delivery_rub = t.delivery_rub,
                    o.delivery_method = t.delivery_method,
                    o.facts_updated_at = NOW()
                WHERE o.company_id = :company_id
            ";

            // 1б) Логистика: возврат на склад (return_amount > 0) — независимо от доставки.
            $sqlReturn = "
                UPDATE wb_order o
                JOIN (
                    SELECT t.srid, t.delivery_rub AS return_rub
                    FROM (
                        SELECT d.srid, d.delivery_rub,
                               ROW_NUMBER() OVER (PARTITION BY d.srid ORDER BY d.rrd_id DESC) AS rn
                        FROM detail_by_period d
                        WHERE d.company_id = :company_id
                          AND d.supplier_oper_name = 'Логистика'
                          AND d.return_amount > 0
                          AND d.srid IN ($inList)
                    ) t
                    WHERE t.rn = 1
                ) t ON t.srid = o.srid
                SET
                    o.return_rub = t.return_rub,
                    o.facts_updated_at = NOW()
                WHERE o.company_id = :company_id
            ";

            // 2) Продажа: комиссия и эквайринг
            $sqlSale = "
                UPDATE wb_order o
                JOIN (
                    SELECT t.srid, t.commission_percent, t.retail_price_withdisc_rub, t.acquiring_percent, t.acquiring_fee
                    FROM (
                        SELECT d.srid, d.commission_percent, d.retail_price_withdisc_rub, d.acquiring_percent, d.acquiring_fee,
                               ROW_NUMBER() OVER (PARTITION BY d.srid ORDER BY d.rrd_id DESC) AS rn
                        FROM detail_by_period d
                        WHERE d.company_id = :company_id
                          AND d.supplier_oper_name = 'Продажа'
                          AND d.srid IN ($inList)
                    ) t
                    WHERE t.rn = 1
                ) t ON t.srid = o.srid
                SET
                    o.commission_percent = t.commission_percent,
                    -- Сумма комиссии считается от цены со скидкой продавца
                    -- (retail_price_withdisc_rub), а не берётся готовым полем
                    -- ppvz_sales_commission — там другая величина.
                    o.commission_fee = ROUND(t.retail_price_withdisc_rub * t.commission_percent / 100, 2),
                    o.acquiring_percent = t.acquiring_percent,
                    o.acquiring_fee = t.acquiring_fee,
                    o.facts_updated_at = NOW()
                WHERE o.company_id = :company_id
            ";

            // 3) Кешбэк
            $sqlCashback = "
                UPDATE wb_order o
                JOIN (
                    SELECT t.srid, t.cashback_amount
                    FROM (
                        SELECT d.srid, d.cashback_amount,
                               ROW_NUMBER() OVER (PARTITION BY d.srid ORDER BY d.rrd_id DESC) AS rn
                        FROM detail_by_period d
                        WHERE d.company_id = :company_id
                          AND d.supplier_oper_name = 'Сумма баллов, удержанных за покупку товаров'
                          AND d.srid IN ($inList)
                    ) t
                    WHERE t.rn = 1
                ) t ON t.srid = o.srid
                SET
                    o.cashback_amount = t.cashback_amount,
                    o.facts_updated_at = NOW()
                WHERE o.company_id = :company_id
            ";

            foreach (['Логистика (доставка)' => $sqlDelivery, 'Логистика (возврат)' => $sqlReturn, 'Продажа' => $sqlSale, 'Кешбэк' => $sqlCashback] as $label => $sql) {
                try {
//                    $db->createCommand($sql, $params)->execute();
                    $affected = $db->createCommand($sql, $params)->execute();
                    $totalAffected += $affected;
                } catch (\Throwable $e) {
                    $this->stderr(
                        "\nОшибка обновления фактов wb_order ({$label}) для company_id={$companyId}, чанк srid #{$chunkIndex}: "
                        . $e->getMessage() . "\n",
                        Console::FG_RED
                    );
                }
            }
        }
        return $totalAffected;
    }

    /**
     * Пересчитывает агрегаты detail_by_period_forecast (день + склад/тип +
     * регион + категория/предмет) для указанной компании по конкретному
     * набору дат (rr_dt). Пересчёт полный (не инкрементальный), т.к.
     * detail_by_period может обновляться задним числом при корректировках
     * отчёта реализации от WB.
     *
     * @param \yii\db\Connection $db
     * @param int $companyId
     * @param array $affectedDates Ассоциативный массив вида ['2026-07-29' => true, ...]
     */
    private function updateForecastAggregates($db, $companyId, array $affectedDates)
    {
        $dates = array_keys($affectedDates);
        if (empty($dates)) {
            return;
        }

        // Бьём на чанки, чтобы не собирать гигантский IN(...) за раз
        // при первой синхронизации/бэкфилле на большой период.
        $dateChunks = array_chunk($dates, 30);

        foreach ($dateChunks as $chunkIndex => $chunk) {
            $params = [':company_id' => $companyId];
            $placeholders = [];
            // Тот же приём, что и в updateWbOrderFacts: фиксированная ширина
            // с ведущими нулями, чтобы ":d1" не был текстовым префиксом
            // ":d10"-":d19" и подстановка значений не искажала соседние
            // плейсхолдеры.
            $chunkWidth = strlen((string)(count($chunk) - 1));
            foreach ($chunk as $i => $date) {
                $key = ':d' . str_pad((string)$i, $chunkWidth, '0', STR_PAD_LEFT);
                $placeholders[] = $key;
                $params[$key] = $date;
            }
            $inList = implode(',', $placeholders);

/*
                  COUNT(*),
                  SUM(d.retail_amount),

SUM(d.ppvz_sales_commission),
*/
            $sql = "
                INSERT INTO detail_by_period_forecast
                  (company_id, stat_date, warehouse_type, warehouse_name, region_name, category, subject,
                   orders_count, sum_retail_amount, sum_ppvz_for_pay, sum_sales_commission,
                   sum_delivery_rub, sum_acquiring_fee, sum_storage_fee, sum_penalty, sum_deduction)
                SELECT
                  o.company_id,
                  DATE(d.rr_dt) AS stat_date,
                  o.warehouse_type, o.warehouse_name, o.region_name, o.category, o.subject,
                  COUNT(CASE WHEN d.supplier_oper_name = 'Продажа' THEN 1 END) AS orders_count,
                  SUM(d.retail_price_withdisc_rub),
                  SUM(d.ppvz_for_pay),
                  SUM(ROUND(d.retail_price_withdisc_rub * d.commission_percent / 100, 2)),
                  SUM(d.delivery_rub),
                  SUM(d.acquiring_fee),
                  SUM(d.storage_fee),
                  SUM(d.penalty),
                  SUM(d.deduction)
                FROM detail_by_period d
                JOIN wb_order o ON o.srid = d.srid
                WHERE d.company_id = :company_id
                  AND o.company_id = :company_id
                  AND d.rr_dt IS NOT NULL
                  AND DATE(d.rr_dt) IN ($inList)
                  AND d.supplier_oper_name IN ('Продажа', 'Логистика')
                GROUP BY o.company_id, DATE(d.rr_dt), o.warehouse_type, o.warehouse_name, o.region_name, o.category, o.subject
                ON DUPLICATE KEY UPDATE
                  orders_count = VALUES(orders_count),
                  sum_retail_amount = VALUES(sum_retail_amount),
                  sum_ppvz_for_pay = VALUES(sum_ppvz_for_pay),
                  sum_sales_commission = VALUES(sum_sales_commission),
                  sum_delivery_rub = VALUES(sum_delivery_rub),
                  sum_acquiring_fee = VALUES(sum_acquiring_fee),
                  sum_storage_fee = VALUES(sum_storage_fee),
                  sum_penalty = VALUES(sum_penalty),
                  sum_deduction = VALUES(sum_deduction)
            ";

            try {
                // company_id используется дважды (в JOIN-условии и в WHERE),
                // поэтому передаём его один раз через именованный параметр —
                // Yii2 сам подставит значение во все вхождения ':company_id'.
                $db->createCommand($sql, $params)->execute();
            } catch (\Throwable $e) {
                $this->stderr(
                    "\nОшибка пересчёта forecast-агрегатов для company_id={$companyId}, чанк дат #{$chunkIndex}: "
                    . $e->getMessage() . "\n",
                    Console::FG_RED
                );
            }
        }
    }



    /**
     * Обновление фактов заказов (wb_order) из detail_by_period за указанный период
     * Запуск: php yii wb-detail-finance/update-order-facts --from=2026-01-01 --to=2026-07-31
     * 
     * @param string|null $from Дата начала (YYYY-MM-DD). По умолчанию -7 дней
     * @param string|null $to Дата окончания (YYYY-MM-DD). По умолчанию сегодня
     * @param int|null $companyId ID компании (если не указан - все компании)
     * @return int ExitCode
     */
    public function actionUpdateOrderFacts($from = null, $to = null, $companyId = null)
    {
        ini_set('memory_limit', '1024M');
        
        if (Yii::$app->hasModule('debug')) {
            Yii::$app->getModule('debug')->instance = null;
        }
        Yii::$app->db->enableLogging = false;
        Yii::$app->db->enableProfiling = false;

        $db = Yii::$app->db;

        $dateFrom = $from ?: date('Y-m-d', strtotime('-7 days'));
        $dateTo = $to ?: date('Y-m-d');

        $this->stdout("\n=== ОБНОВЛЕНИЕ ФАКТОВ ЗАКАЗОВ ===\n", Console::FG_CYAN);
        $this->stdout("Период: {$dateFrom} - {$dateTo}\n");

        // Формируем запрос для получения компаний
        $query = (new \yii\db\Query())
            ->select(['id', 'name'])
            ->from('companies')
            ->where(['is_active' => 1]);

        if ($companyId) {
            $query->andWhere(['id' => $companyId]);
        }

        $companies = $query->all($db);

        if (empty($companies)) {
            $this->stderr("Не найдено активных компаний.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $totalUpdated = 0;

        foreach ($companies as $company) {
            $companyIdValue = $company['id'];
            $companyName = $company['name'];

            $this->stdout("\n>>> ОБРАБОТКА КОМПАНИИ: {$companyName} (ID: {$companyIdValue}) <<<\n", Console::FG_GREEN);

            // Получаем все уникальные srid из detail_by_period за период
            $srids = (new \yii\db\Query())
                ->select('srid')
                ->from('detail_by_period')
                ->where(['company_id' => $companyIdValue])
                ->andWhere(['supplier_oper_name' => 'Продажа'])
                ->andWhere(['>=', 'rr_dt', $dateFrom . ' 00:00:00'])
                ->andWhere(['<=', 'rr_dt', $dateTo . ' 23:59:59'])
                ->andWhere(['not', ['srid' => null]])
                ->andWhere(['<>', 'srid', ''])
                ->distinct()
                ->column($db);

            if (empty($srids)) {
                $this->stdout("  Нет srid для обновления за период.\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("  Найдено уникальных srid для обновления: " . count($srids) . "\n");

            // Вызываем существующий метод обновления фактов
            $updated = $this->updateWbOrderFacts($db, $companyIdValue, $srids);
            $totalUpdated += $updated;

            $this->stdout("  Обновлено записей в wb_order: {$updated}\n", Console::FG_GREEN);
        }

        $this->stdout("\n[ОК] Обновление фактов заказов завершено. Всего обновлено: {$totalUpdated}\n", Console::FG_GREEN);
        return ExitCode::OK;
    }


/**
     * Пересчет forecast-агрегатов (detail_by_period_forecast) за период
     * Запуск: php yii wb-detail-finance/update-forecast --from=2026-01-01 --to=2026-07-31
     * 
     * @param string|null $from Дата начала (YYYY-MM-DD). По умолчанию -7 дней
     * @param string|null $to Дата окончания (YYYY-MM-DD). По умолчанию сегодня
     * @param int|null $companyId ID компании (если не указан - все компании)
     * @return int ExitCode
     */ 
    public function actionUpdateForecast($from = null, $to = null, $companyId = null)
    {
        ini_set('memory_limit', '1024M');
        
        if (Yii::$app->hasModule('debug')) {
            Yii::$app->getModule('debug')->instance = null;
        }
        Yii::$app->db->enableLogging = false;
        Yii::$app->db->enableProfiling = false;

        $db = Yii::$app->db;

        $dateFrom = $from ?: date('Y-m-d', strtotime('-7 days'));
        $dateTo = $to ?: date('Y-m-d');

        $this->stdout("\n=== ПЕРЕСЧЁТ FORECAST-АГРЕГАТОВ ===\n", Console::FG_CYAN);
        $this->stdout("Период: {$dateFrom} - {$dateTo}\n");

        // Получаем список активных компаний
        $query = (new \yii\db\Query())
            ->select(['id', 'name'])
            ->from('companies')
            ->where(['is_active' => 1]);

        if ($companyId) {
            $query->andWhere(['id' => $companyId]);
        }

        $companies = $query->all($db);

        if (empty($companies)) {
            $this->stderr("Не найдено активных компаний.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($companies as $company) {
            $companyIdValue = $company['id'];
            $companyName = $company['name'];

            $this->stdout("\n>>> ОБРАБОТКА КОМПАНИИ: {$companyName} (ID: {$companyIdValue}) <<<\n", Console::FG_GREEN);

            // Собираем все уникальные даты rr_dt из detail_by_period за указанный период
            $rawDates = (new \yii\db\Query())
                ->select(['stat_date' => 'DATE(rr_dt)'])
                ->from('detail_by_period')
                ->where(['company_id' => $companyIdValue])
                ->andWhere(['>=', 'rr_dt', $dateFrom . ' 00:00:00'])
                ->andWhere(['<=', 'rr_dt', $dateTo . ' 23:59:59'])
                ->andWhere(['not', ['rr_dt' => null]])
                ->distinct()
                ->column($db);

            if (empty($rawDates)) {
                $this->stdout("  Нет записей с rr_dt за указанный период.\n", Console::FG_YELLOW);
                continue;
            }

            // Формируем ассоциативный массив ['YYYY-MM-DD' => true],
            // который ожидает закрытый метод updateForecastAggregates
            $affectedDates = array_fill_keys($rawDates, true);

            $this->stdout("  Найдено дат для пересчёта: " . count($affectedDates) . "\n");

            // Вызываем существующий метод пересчета
            $this->updateForecastAggregates($db, $companyIdValue, $affectedDates);

            $this->stdout("  Агрегаты успешно пересчитаны.\n", Console::FG_GREEN);
        }

        $this->stdout("\n[ОК] Пересчёт forecast-агрегатов завершён.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Пост-обработка адресов и обновление агрегатных таблиц
     */
    public function actionSyncAddresses() 
    {
        \Yii::$app->runAction('/address-processor/process');
        \Yii::$app->runAction('/address/fix');
        \Yii::$app->runAction('/geo-fill/full-repair');

//        \Yii::$app->runAction('wb-feedbacks/sync');
        \Yii::$app->runAction('/aggregate/update');
        \Yii::$app->runAction('aggregate/update-feedbacks-cost');
        \Yii::$app->runAction('aggregate/update-adv-costs');
    }
}