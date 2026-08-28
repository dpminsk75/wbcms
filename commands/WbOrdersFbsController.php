<?php
namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\db\Query;
use yii\db\Expression;
use Yii;
use app\models\WbCardSize;
use app\models\WbVirtualStock;
use app\models\WbFbsWarehouse;

/**
 * Синхронизация FBS-заказов (сборочных заданий) и их статусов.
 *
 * php yii wb-orders-fbs/sync-orders            — сборочные задания за сегодня (или вчера+сегодня, если раньше 5 утра)
 * php yii wb-orders-fbs/sync-orders 2026-07-01 2026-07-10  — произвольный период
 * php yii wb-orders-fbs/sync-statuses          — обновление статусов "живых" заказов за последние 30 дней
 * php yii wb-orders-fbs/sync                   — сначала заказы, потом статусы
 */
class WbOrdersFbsController extends Controller
{
    /** @var string Часовой пояс WB API — все dateFrom/dateTo считаем по МСК, независимо от TZ сервера */
    private const WB_TIMEZONE = 'Europe/Moscow';

    /** @var int Максимум order id за один запрос к /orders/status (ограничение самого API) */
    private const STATUS_BATCH_SIZE = 1000;

    /** @var int Размер страницы для /orders (можно поднимать до 1000, если API это позволяет для вашего кабинета) */
    private const ORDERS_PAGE_LIMIT = 200;

    private const ACTIVE_SUPPLIER_STATUSES = ['new', 'confirm', 'complete'];
    private const ACTIVE_WB_STATUSES = ['waiting', 'sorted'];

    /**
     * Преобразование строки даты (ISO8601 из ответа WB) в формат MySQL datetime.
     */
    private function formatDate($dateStr)
    {
        if (empty($dateStr)) {
            return null;
        }
        return date('Y-m-d H:i:s', strtotime($dateStr));
    }

    /**
     * Безопасное приведение к float для DECIMAL полей.
     */
    private function formatDecimal($value)
    {
        if ($value === null || $value === '') {
            return 0.00;
        }
        return round((float)$value, 2);
    }

    /**
     * Комбинированный запуск: сначала сборочные задания, потом статусы.
     */
    public function actionSync($from = null, $to = null)
    {
        $result = $this->actionSyncOrders($from, $to);
        if ($result !== ExitCode::OK) {
            return $result;
        }
        return $this->actionSyncStatuses();
    }

    /**
     * Загрузка сборочных заданий FBS (GET /api/v3/orders) по всем активным компаниям.
     *
     * Без параметров:
     *   - если запущено раньше 5:00 по МСК — берём диапазон "вчера 00:00 МСК — сейчас";
     *   - иначе — "сегодня 00:00 МСК — сейчас".
     * С параметрами $from/$to (формат 'Y-m-d' или 'Y-m-d H:i:s') — произвольный период по МСК.
     */
    public function actionSyncOrders($from = null, $to = null)
    {
        ini_set('memory_limit', '1024M');
        Yii::$app->db->enableLogging = false;
        Yii::$app->db->enableProfiling = false;

        $db = Yii::$app->db;

        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all($db);

        if (empty($companies)) {
            $this->stderr("Не найдено активных компаний в таблице companies.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        [$dateFromTs, $dateToTs] = $this->resolveOrdersDateRange($from, $to);

        $tz = new \DateTimeZone(self::WB_TIMEZONE);
        $this->stdout(
            "Период (сборочные задания FBS, МСК): "
            . (new \DateTime("@{$dateFromTs}"))->setTimezone($tz)->format('d.m.Y H:i:s')
            . " — "
            . (new \DateTime("@{$dateToTs}"))->setTimezone($tz)->format('d.m.Y H:i:s')
            . "\n",
            Console::FG_CYAN
        );

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
            $token = $company['api_key'] ?? null;

            if (!$token) {
                $this->stdout("[-] Пропуск '{$companyName}' (ID: {$companyId}): нет токена api_key.\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("\n>>> Загрузка сборочных заданий FBS: {$companyName} (ID: {$companyId}) <<<\n", Console::FG_CYAN);

            $next = 0;
            $totalProcessed = 0;
            $limit = self::ORDERS_PAGE_LIMIT;

            while (true) {
                try {
                    $response = $this->apiRequest(
                        $token,
                        'GET',
                        'https://marketplace-api.wildberries.ru/api/v3/orders',
                        [
                            'limit' => $limit,
                            'next' => $next,
                            'dateFrom' => $dateFromTs,
                            'dateTo' => $dateToTs,
                        ],
                        null,
                        5,
                        $companyId
                    );
                } catch (\Throwable $e) {
                    $this->stderr("Ошибка запроса сборочных заданий для '{$companyName}': " . $e->getMessage() . "\n", Console::FG_RED);
                    break;
                }

                $orders = $response['orders'] ?? [];
                if (empty($orders)) {
                    break;
                }

                foreach ($orders as $order) {
                    $row = $this->prepareFbsOrderRow($order, $companyId);
                    try {
                        // is_deducted/deducted_at не в $row -> сохраняется флаг дедупликации
                        $db->createCommand()->upsert('wb_orders_fbs', $row, $row)->execute();
                        $totalProcessed++;
                    } catch (\Throwable $e) {
                        $orderIdForLog = $order['id'] ?? 'unknown';
                        $this->stderr("Ошибка сохранения сборочного задания id={$orderIdForLog} для '{$companyName}': " . $e->getMessage() . "\n", Console::FG_RED);
                    }
                }

                $newNext = $response['next'] ?? 0;
                // Защита от зацикливания: если страница короче лимита или
                // next не сдвинулся — дальше страниц нет.
                if (count($orders) < $limit || $newNext == $next) {
                    break;
                }
                $next = $newNext;
            }

            $this->stdout("Обработано сборочных заданий: {$totalProcessed}\n", Console::FG_GREEN);
        }

        // Авто-вычет из виртуал. остатков для складов с consider_orders=1
        foreach ($companies as $company) {
            try {
                $this->deductVirtualStocks((int)$company['id']);
            } catch (\Throwable $e) {
                $this->stderr("Ошибка вычета виртуал. остатков {$company['name']}: {$e->getMessage()}\n", Console::FG_RED);
                $this->logDeduct($company['id'], "ERROR deduct: {$e->getMessage()}");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Обновление статусов сборочных заданий (POST /api/v3/orders/status)
     * по всем активным компаниям — только для заказов последних 30 дней,
     * у которых последний известный статус ещё "живой" (или статус ещё
     * ни разу не проверялся).
     */
    public function actionSyncStatuses()
    {
        ini_set('memory_limit', '1024M');
        Yii::$app->db->enableLogging = false;
        Yii::$app->db->enableProfiling = false;

        $db = Yii::$app->db;

        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all($db);

        if (empty($companies)) {
            $this->stderr("Не найдено активных компаний в таблице companies.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
            $token = $company['api_key'] ?? null;

            if (!$token) {
                continue;
            }

            $this->stdout("\n>>> Обновление статусов FBS: {$companyName} (ID: {$companyId}) <<<\n", Console::FG_CYAN);

            $candidateIds = $this->getFbsStatusCandidates($db, $companyId);

            if (empty($candidateIds)) {
                $this->stdout("Нет заказов, требующих проверки статуса.\n");
                continue;
            }

            $this->stdout("К проверке: " . count($candidateIds) . " заказ(ов).\n");

            $chunks = array_chunk($candidateIds, self::STATUS_BATCH_SIZE);
            $totalChecked = 0;
            $totalChanged = 0;

            foreach ($chunks as $chunk) {
                // Последние известные статусы именно для этого чанка —
                // чтобы понять, что реально поменялось.
                $lastKnown = $this->getLatestStatuses($db, $chunk);

                try {
                    $response = $this->apiRequest(
                        $token,
                        'POST',
                        'https://marketplace-api.wildberries.ru/api/v3/orders/status',
                        [],
                        ['orders' => array_map('intval', $chunk)],
                        5,
                        $companyId
                    );
                } catch (\Throwable $e) {
                    $this->stderr("Ошибка запроса статусов для '{$companyName}': " . $e->getMessage() . "\n", Console::FG_RED);
                    continue;
                }

                $statuses = $response['orders'] ?? [];
                $rowsToInsert = [];

                foreach ($statuses as $st) {
                    $orderId = $st['id'] ?? null;
                    if (!$orderId) {
                        continue;
                    }
                    $totalChecked++;

                    $supplierStatus = $st['supplierStatus'] ?? null;
                    $wbStatus = $st['wbStatus'] ?? null;
                    $isCancellable = !empty($st['isCancellable']) ? 1 : 0;

                    $prev = $lastKnown[$orderId] ?? null;

                    // Пишем новую строку ТОЛЬКО если статус реально
                    // изменился (или это первая проверка заказа) — тогда
                    // "created_at последней строки" = момент фактической
                    // смены статуса, а не момент опроса.
                    if ($prev === null
                        || $prev['supplier_status'] !== $supplierStatus
                        || $prev['wb_status'] !== $wbStatus
                    ) {
                        $rowsToInsert[] = [$companyId, $orderId, $supplierStatus, $wbStatus, $isCancellable];
                        $totalChanged++;
                    }
                }

                if (!empty($rowsToInsert)) {
                    try {
                        $db->createCommand()->batchInsert(
                            'wb_orders_fbs_statuses',
                            ['company_id', 'wb_order_id', 'supplier_status', 'wb_status', 'is_cancellable'],
                            $rowsToInsert
                        )->execute();
                    } catch (\Throwable $e) {
                        $this->stderr("Ошибка сохранения статусов для '{$companyName}': " . $e->getMessage() . "\n", Console::FG_RED);
                    }
                }

                // Небольшая пауза между пачками — вежливо к рейт-лимитам WB.
                usleep(200000);
            }

            $this->stdout("Проверено: {$totalChecked}, изменилось: {$totalChanged}.\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * Определяет диапазон дат для /orders (в unix-таймстемпах, по МСК).
     */
    private function resolveOrdersDateRange($from, $to)
    {
        $tz = new \DateTimeZone(self::WB_TIMEZONE);

        if ($from !== null && $to !== null) {
            $dateFrom = new \DateTime($from, $tz);
            $dateTo = new \DateTime($to, $tz);
            return [$dateFrom->getTimestamp(), $dateTo->getTimestamp()];
        }

        $nowMsk = new \DateTime('now', $tz);
        $hour = (int)$nowMsk->format('H');

        if ($hour < 5) {
            $dateFrom = new \DateTime('yesterday', $tz);
        } else {
            $dateFrom = new \DateTime('today', $tz);
        }

        return [$dateFrom->getTimestamp(), time()];
    }

    /**
     * Готовит строку для upsert в wb_orders_fbs из одного элемента ответа /orders.
     */
    private function prepareFbsOrderRow(array $order, $companyId)
    {
        $options = $order['options'] ?? [];

        return [
            'company_id' => $companyId,
            'wb_order_id' => $order['id'] ?? null,
            'rid' => $order['rid'] ?? null,
            'order_uid' => $order['orderUid'] ?? null,
            'supply_id' => $order['supplyId'] ?? null,
            'delivery_type' => $order['deliveryType'] ?? null,
            'article' => $order['article'] ?? null,
            'color_code' => $order['colorCode'] ?? null,
            'warehouse_id' => $order['warehouseId'] ?? null,
            'office_id' => $order['officeId'] ?? null,
            'nm_id' => $order['nmId'] ?? null,
            'chrt_id' => $order['chrtId'] ?? null,
            // Цены в ответе WB — в копейках, приводим к рублям.
            'price' => $this->formatDecimal(($order['price'] ?? 0) / 100),
            'converted_price' => $this->formatDecimal(($order['convertedPrice'] ?? 0) / 100),
            'currency_code' => $order['currencyCode'] ?? null,
            'converted_currency_code' => $order['convertedCurrencyCode'] ?? null,
            'scan_price' => $this->formatDecimal(($order['scanPrice'] ?? 0) / 100),
            'cargo_type' => $order['cargoType'] ?? null,
            'cross_border_type' => $order['crossBorderType'] ?? null,
            'is_zero_order' => !empty($order['isZeroOrder']) ? 1 : 0,
            'is_b2b' => !empty($options['isB2B']) ? 1 : 0,
            'is_pickup_point_shipment_allowed' => !empty($order['isPickupPointShipmentAllowed']) ? 1 : 0,
            'comment' => $order['comment'] ?? null,
            'wb_created_at' => $this->formatDate($order['createdAt'] ?? null),
            'raw_address' => isset($order['address']) ? json_encode($order['address'], JSON_UNESCAPED_UNICODE) : null,
            'raw_offices' => isset($order['offices']) ? json_encode($order['offices'], JSON_UNESCAPED_UNICODE) : null,
            'raw_skus' => isset($order['skus']) ? json_encode($order['skus'], JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    /**
     * Кандидаты на проверку статуса: заказы компании за последние 30 дней,
     * у которых последний известный статус — "живой" (либо статус ещё
     * ни разу не проверялся).
     *
     * @return int[] Список wb_order_id
     */
    private function getFbsStatusCandidates($db, $companyId)
    {
        $params = [':company_id' => $companyId];

        $supplierPlaceholders = [];
        foreach (self::ACTIVE_SUPPLIER_STATUSES as $i => $status) {
            $key = ":ss{$i}";
            $supplierPlaceholders[] = $key;
            $params[$key] = $status;
        }

        $wbPlaceholders = [];
        foreach (self::ACTIVE_WB_STATUSES as $i => $status) {
            $key = ":ws{$i}";
            $wbPlaceholders[] = $key;
            $params[$key] = $status;
        }

        $sql = "
            SELECT f.wb_order_id
            FROM wb_orders_fbs f
            LEFT JOIN (
                SELECT s1.wb_order_id, s1.supplier_status, s1.wb_status
                FROM wb_orders_fbs_statuses s1
                JOIN (
                    SELECT wb_order_id, MAX(id) AS max_id
                    FROM wb_orders_fbs_statuses
                    WHERE company_id = :company_id
                    GROUP BY wb_order_id
                ) m ON m.wb_order_id = s1.wb_order_id AND m.max_id = s1.id
            ) ls ON ls.wb_order_id = f.wb_order_id
            WHERE f.company_id = :company_id
              AND f.wb_created_at >= (NOW() - INTERVAL 30 DAY)
              AND (
                    ls.wb_order_id IS NULL
                    OR ls.supplier_status IN (" . implode(',', $supplierPlaceholders) . ")
                    OR ls.wb_status IN (" . implode(',', $wbPlaceholders) . ")
                  )
        ";

        $rows = $db->createCommand($sql, $params)->queryAll();

        // wb_order_id — всегда "чистое" целое число (родное поле id из WB
        // API), поэтому, в отличие от srid/rid, использовать его как ключ
        // массива безопасно — не смешивается с текстовыми форматами.
        return array_map('intval', array_column($rows, 'wb_order_id'));
    }

    /**
     * Возвращает последний известный статус (supplier_status, wb_status)
     * для указанного набора wb_order_id.
     *
     * @return array<int, array{supplier_status: ?string, wb_status: ?string}>
     */
    private function getLatestStatuses($db, array $orderIds)
    {
        if (empty($orderIds)) {
            return [];
        }

        $params = [];
        $placeholders = [];
        // Фиксированная ширина имён плейсхолдеров — чтобы ни один не был
        // текстовым префиксом другого (см. историю с srid-плейсхолдерами).
        $width = strlen((string)(count($orderIds) - 1));
        foreach (array_values($orderIds) as $i => $id) {
            $key = ':o' . str_pad((string)$i, $width, '0', STR_PAD_LEFT);
            $placeholders[] = $key;
            $params[$key] = (int)$id;
        }
        $inList = implode(',', $placeholders);

        $sql = "
            SELECT s.wb_order_id, s.supplier_status, s.wb_status
            FROM wb_orders_fbs_statuses s
            JOIN (
                SELECT wb_order_id, MAX(id) AS max_id
                FROM wb_orders_fbs_statuses
                WHERE wb_order_id IN ($inList)
                GROUP BY wb_order_id
            ) m ON m.wb_order_id = s.wb_order_id AND m.max_id = s.id
        ";

        $rows = $db->createCommand($sql, $params)->queryAll();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['wb_order_id']] = [
                'supplier_status' => $row['supplier_status'],
                'wb_status' => $row['wb_status'],
            ];
        }
        return $result;
    }

    /**
     * Вычитает заказы с consider_orders складов из wb_virtual_stock один раз (флаг is_deducted) и выгружает изменившиеся остатки на все виртуал. склады.
     */
    private function deductVirtualStocks(int $companyId): void
    {
        $db = Yii::$app->db;
        $company = (new Query())->from('companies')->where(['id' => $companyId])->one($db);
        if (!$company || empty($company['fbs_deduct_enabled'])) {
            $this->logDeduct($companyId, "skip: fbs_deduct_enabled=0");
            return;
        }
        $isTest = !empty($company['fbs_deduct_test']);
        if ($isTest) {
            $this->logDeduct($companyId, "TEST MODE fbs_deduct_test=1: БД обновится, PUT только в лог");
        }
        $whIds = (new Query())->select('warehouseId')->from('wb_fbs_warehouse')
            ->where(['company_id' => $companyId, 'consider_orders' => 1, 'is_deleting' => 0])->column($db);
        if (empty($whIds)) {
            $this->logDeduct($companyId, "skip: нет складов с consider_orders=1");
            return;
        }
        $orders = (new Query())->select(['wb_order_id','chrt_id'])->from('wb_orders_fbs')
            ->where(['company_id' => $companyId, 'is_deducted' => 0])->andWhere(['in','warehouse_id',$whIds])->all($db);
        if (empty($orders)) {
            $this->logDeduct($companyId, "skip: нет новых заказов для вычета");
            return;
        }
        $cntByChrt = array_count_values(array_filter(array_column($orders,'chrt_id')));
        if (empty($cntByChrt)) {
            $this->logDeduct($companyId, "skip: у заказов пустой chrt_id");
            return;
        }
        $this->stdout("  [deduct] заказов ".count($orders)." chrt ".count($cntByChrt)." для вычета\n", Console::FG_YELLOW);
        $this->logDeduct($companyId, "start: ".count($orders)." заказов, ".count($cntByChrt)." chrt");

        $changedSkus = [];
        $deductDetails = [];
        foreach ($cntByChrt as $chrtId => $cnt) {
            $size = WbCardSize::findOne(['chrtID' => (int)$chrtId]);
            if (!$size) {
                $this->logDeduct($companyId, "  chrt $chrtId: sku не найден, пропуск");
                continue;
            }
            $sku = $size->sku;
            $stock = WbVirtualStock::findOne(['company_id'=>$companyId,'sku'=>$sku]);
            $oldQty = $stock ? (int)$stock->quantity : 0;
            $newQty = max(0, $oldQty - (int)$cnt);
            if ($stock) {
                $db->createCommand()->update('wb_virtual_stock', ['quantity'=>$newQty], ['company_id'=>$companyId,'sku'=>$sku])->execute();
            } else {
                $newQty = 0;
                $db->createCommand()->insert('wb_virtual_stock', ['company_id'=>$companyId,'sku'=>$sku,'nmID'=>$size->nmID,'chrtID'=>$size->chrtID,'quantity'=>0])->execute();
            }
            if ($oldQty !== $newQty) {
                $changedSkus[] = $sku;
                $deductDetails[] = "$sku (chrt $chrtId): $oldQty -> $newQty (-$cnt)";
            }
            $this->logDeduct($companyId, "  chrt $chrtId sku $sku: $oldQty -> $newQty (-$cnt)");
        }

        // помечаем заказы как вычтенные (даже в TEST — чтобы не вычитать дважды)
        $ids = array_column($orders,'wb_order_id');
        $db->createCommand()->update('wb_orders_fbs', ['is_deducted'=>1,'deducted_at'=>new Expression('NOW()')], ['in','wb_order_id',$ids])->execute();
        $this->logDeduct($companyId, "marked ".count($ids)." orders is_deducted=1");

        if (empty($changedSkus)) {
            $this->logDeduct($companyId, "no changed skus, upload skipped");
            return;
        }

        $this->logDeduct($companyId, "changed: ".implode(', ',$deductDetails));
        $this->uploadChangedVirtualStocks($companyId, $changedSkus);
    }

    private function uploadChangedVirtualStocks(int $companyId, array $skus): void
    {
        $db = Yii::$app->db;
        $company = (new Query())->from('companies')->where(['id'=>$companyId])->one($db);
        $token = $company['api_key'] ?? null;
        if (!$token) {
            $this->logDeduct($companyId, "upload skip: нет токена");
            return;
        }
        $warehouses = WbFbsWarehouse::find()->where(['company_id'=>$companyId,'is_virtual'=>1])->all();
        if (empty($warehouses)) {
            $this->logDeduct($companyId, "upload skip: нет виртуал. складов");
            return;
        }
        $isTest = !empty($company['fbs_deduct_test']);
        if ($isTest) {
            $this->logDeduct($companyId, "TEST MODE: реальные PUT на WB пропущены (fbs_deduct_test=1)");
            $this->stdout("  [deduct] TEST MODE - только лог, без PUT\n", Console::FG_YELLOW);
        }
        $stocks = WbVirtualStock::find()->where(['company_id'=>$companyId])->andWhere(['in','sku',$skus])->all();
        $payloadStocks = [];
        foreach ($stocks as $s) {
            $payloadStocks[] = ['chrtId'=>(int)$s->chrtID, 'amount'=>(int)$s->quantity];
        }
        // также sku которых не было в wb_virtual_stock но попали в changed (нулевой остаток уже создан)
        foreach ($warehouses as $wh) {
            $chunks = array_chunk($payloadStocks, 1000);
            foreach ($chunks as $idx=>$chunk) {
                $payload = ['stocks'=>$chunk];
                $url = "https://marketplace-api.wildberries.ru/api/v3/stocks/{$wh->warehouseId}";
                $prefix = $isTest ? "[DRY] " : "";
                $this->logDeduct($companyId, $prefix."PUT $url chunk ".($idx+1)."/".count($chunks)." ".json_encode($payload, JSON_UNESCAPED_UNICODE));
                if ($isTest) {
                    continue;
                }
                try {
                    $resp = Yii::$app->wbHttpClient->request('PUT', $url, $payload, $token, $companyId, null, true);
                    $ok = $resp->isOk;
                    $this->logDeduct($companyId, "  -> HTTP {$resp->statusCode} ok=".($ok?'1':'0')." ".substr($resp->content??'',0,300));
                    if ($ok) {
                        foreach ($chunk as $ps) {
                            $size = WbCardSize::findOne(['chrtID'=>$ps['chrtId']]);
                            if (!$size) continue;
                            $db->createCommand()->upsert('wb_fbs_stock', [
                                'company_id'=>$companyId,'warehouseId'=>$wh->warehouseId,'sku'=>$size->sku,'amount'=>$ps['amount'],'nmID'=>$size->nmID,'chrtID'=>$size->chrtID,
                            ], ['amount'=>$ps['amount']])->execute();
                        }
                    }
                    usleep(300000);
                } catch (\Throwable $e) {
                    $this->logDeduct($companyId, "  -> ERROR ".$e->getMessage());
                }
            }
        }
        $this->logDeduct($companyId, "upload done warehouses=".count($warehouses)." skus=".count($skus));
    }

    private function logDeduct(int $companyId, string $msg): void
    {
        $line = date('Y-m-d H:i:s')." [c$companyId] $msg\n";
        $dir = Yii::$app->params['wbFbsDeductLogDir'] ?? Yii::getAlias('@runtime/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            if (!is_dir($dir)) $dir = Yii::getAlias('@runtime/logs');
        }
        $file = $dir . '/wb-fbs-deduct-'.date('Y-m-d').'.log';
        @file_put_contents($file, $line, FILE_APPEND);
        Yii::info($msg, 'wb_fbs_deduct');
    }

    /**
     * HTTP-запрос к Marketplace API WB с базовой обработкой 429 (retry с
     * учётом заголовка Retry-After, если он есть).
     *
     * @param string $token Токен кабинета — передаётся как есть в заголовок
     *   Authorization, БЕЗ префикса "Bearer" (подтверждено рабочим тестом).
     */
    private function apiRequest($token, $method, $url, array $query = [], $body = null, $maxRetries = 5, $companyId = null)
    {
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $response = null;
        if ($method === 'POST') {
            $response = Yii::$app->wbHttpClient->post($url, $body ?? [], $token, $companyId, $maxRetries);
        } else {
            $response = Yii::$app->wbHttpClient->get($url, [], $token, $companyId, $maxRetries);
        }

        $httpCode = (int)$response->getStatusCode();
        $content = $response->content;
        $decoded = $response->data;
        if ($decoded === null && $content !== null && $content !== '') {
            $decoded = json_decode($content, true);
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("HTTP {$httpCode}: {$content}");
        }

        if ($decoded === null && $content !== null && $content !== '' && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Некорректный JSON в ответе: " . json_last_error_msg());
        }

        return $decoded;
    }
}
