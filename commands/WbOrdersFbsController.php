<?php
namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\db\Query;
use yii\db\Expression;
use Yii;
use app\models\WbCard;
use app\models\WbCardSize;
use app\models\WbStockBalance;
use app\models\WbStockLedger;
use app\models\WbFbsWarehouse;
use app\models\OurWarehouse;
use app\services\StockService;

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
     * Вычитает FBS-заказы из оперативного склада is_fbs через StockService по-заказно (для сверки ledger).
     * Источник заказов: wb_orders_fbs с is_deducted=0 и warehouse_id из wb_fbs_warehouse consider_orders=1 (совместимость) или our_warehouse is_fbs consider_orders=1.
     * Каждый заказ → ledger doc_type=fbs_order doc_id=wb_order_id qty_delta=-1, wb_stock_balance is_fbs.
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
            $this->logDeduct($companyId, "TEST MODE fbs_deduct_test=1: баланс/ledger обновятся, PUT только в лог");
        }
        // oper warehouse is_fbs
        $fbsId = (new Query())->select('id')->from('our_warehouse')->where(['company_id'=>$companyId,'is_fbs'=>1])->scalar($db);
        if (!$fbsId) {
            $fbsId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId,'is_fbs'=>1])->scalar();
        }
        if (!$fbsId) {
            $this->logDeduct($companyId, "skip: нет склада OurWarehouse is_fbs=1");
            return;
        }
        // consider_orders: prefer OurWarehouse, fallback wb_fbs_warehouse
        $owConsider = (int)(new Query())->select('consider_orders')->from('our_warehouse')->where(['id'=>$fbsId])->scalar($db);
        $whIds = (new Query())->select('warehouseId')->from('wb_fbs_warehouse')
            ->where(['company_id' => $companyId, 'consider_orders' => 1, 'is_deleting' => 0])->column($db);
        $fbsName = (new Query())->select('name')->from('our_warehouse')->where(['id'=>$fbsId])->scalar($db);
        $this->logDeduct($companyId, "[NEW] engine=wb_stock_balance is_fbs=$fbsId ($fbsName) owConsider=$owConsider wbConsiderWh=[".implode(',', $whIds ?: ['none'])."]");
        if (!$owConsider && empty($whIds)) {
            $this->logDeduct($companyId, "skip: нет складов с consider_orders=1 (our_warehouse is_fbs и wb_fbs_warehouse)");
            return;
        }
        // если our_warehouse consider=1 и wb_fbs_warehouse пусто — вычитаем все заказы (без фильтра по warehouse_id)
        $orderQuery = (new Query())->select(['wb_order_id','chrt_id','warehouse_id'])->from('wb_orders_fbs')
            ->where(['company_id' => $companyId, 'is_deducted' => 0]);
        if (!empty($whIds)) {
            $orderQuery->andWhere(['in','warehouse_id',$whIds]);
        }
        $orders = $orderQuery->all($db);
        if (empty($orders)) {
            $this->logDeduct($companyId, "skip: нет новых заказов для вычета");
            return;
        }
        $cntByWh = array_count_values(array_filter(array_column($orders,'warehouse_id')));
        $whNames = !empty($cntByWh) ? (new Query())->select(['warehouseId','name'])->from('wb_fbs_warehouse')->where(['in','warehouseId', array_keys($cntByWh)])->indexBy('warehouseId')->all($db) : [];
        $whParts = [];
        foreach ($cntByWh as $whId => $cnt) $whParts[] = "$whId (".($whNames[$whId]['name'] ?? '?')."): $cnt";
        $this->stdout("  [NEW] заказов ".count($orders)." для вычета через wb_stock_balance is_fbs=$fbsId\n", Console::FG_YELLOW);
        $this->logDeduct($companyId, "[NEW] start: ".count($orders)." заказов | по складам WB: ".implode(', ', $whParts ?: ['all'])." → минус с OurWarehouse is_fbs=$fbsId");

        $changedSkus = [];
        $okCount = 0; $skipLedger = 0; $failCount = 0;
        foreach ($orders as $order) {
            $orderId = (int)($order['wb_order_id'] ?? 0);
            if (!$orderId) { $failCount++; continue; }
            // dedup по ledger — уже списано
            if ((new Query())->from('wb_stock_ledger')->where(['company_id'=>$companyId,'doc_type'=>'fbs_order','doc_id'=>$orderId])->exists($db)) {
                $db->createCommand()->update('wb_orders_fbs', ['is_deducted'=>1,'deducted_at'=>new Expression('NOW()')], ['wb_order_id'=>$orderId])->execute();
                $skipLedger++;
                continue;
            }
            $chrtId = $order['chrt_id'] ?? null;
            if (empty($chrtId)) {
                $this->logDeduct($companyId, "  order $orderId: пустой chrt_id, пропуск");
                continue;
            }
            $size = WbCardSize::findOne(['chrtID' => (int)$chrtId]);
            if (!$size) {
                $this->logDeduct($companyId, "  order $orderId chrt $chrtId: sku не найден, пропуск");
                continue;
            }
            $sku = $size->sku;
            $card = WbCard::findOne(['nmID'=>$size->nmID]);
            $vendor = $card ? $card->vendorCode : '-';
            // StockService по-заказно: -1 шт, ledger doc_id=orderId для сверки
            $res = StockService::apply($companyId, (int)$fbsId, 'fbs_order', $orderId, [['sku'=>$sku,'delta'=>-1]]);
            if (!$res['ok']) {
                $this->logDeduct($companyId, "  order $orderId chrt $chrtId sku $sku vendor $vendor: FAILED ".$res['error']);
                $failCount++;
                continue;
            }
            $db->createCommand()->update('wb_orders_fbs', ['is_deducted'=>1,'deducted_at'=>new Expression('NOW()')], ['wb_order_id'=>$orderId])->execute();
            $changedSkus[$sku] = true;
            $okCount++;
            $this->logDeduct($companyId, "[NEW] order $orderId chrt $chrtId sku $sku vendor $vendor nmID {$size->nmID} wh {$order['warehouse_id']}: wb_stock_balance is_fbs $fbsId -1 ledger ok (doc_type=fbs_order doc_id=$orderId)");
        }
        $this->logDeduct($companyId, "[NEW] deduct done: ok=$okCount skipLedger=$skipLedger fail=$failCount changedSkus=".count($changedSkus)." engine=wb_stock_balance");
        if ($okCount===0 && $skipLedger===0) {
            $this->logDeduct($companyId, "no applied orders, upload skipped");
            return;
        }
        // даже если только skipLedger — остатки могли уже быть списаны ранее, но для консистентности выгружаем измененные sku
        $this->uploadChangedVirtualStocks($companyId, array_keys($changedSkus));
    }

    private function uploadChangedVirtualStocks(int $companyId, array $skus): void
    {
        $db = Yii::$app->db;
        if (empty($skus)) {
            $this->logDeduct($companyId, "upload skip: нет changed skus");
            return;
        }
        $company = (new Query())->from('companies')->where(['id'=>$companyId])->one($db);
        $token = $company['api_key'] ?? null;
        if (!$token) {
            $this->logDeduct($companyId, "upload skip: нет токена");
            return;
        }
        $warehouses = WbFbsWarehouse::find()->where(['company_id'=>$companyId,'is_virtual'=>1])->all();
        if (empty($warehouses)) {
            $this->logDeduct($companyId, "upload skip: нет виртуал. складов is_virtual=1");
            return;
        }
        $isTest = !empty($company['fbs_deduct_test']);
        if ($isTest) {
            $this->logDeduct($companyId, "TEST MODE: реальные PUT на WB пропущены (fbs_deduct_test=1)");
            $this->stdout("  [deduct] TEST MODE - только лог, без PUT\n", Console::FG_YELLOW);
        }
        $fbsId = (new Query())->select('id')->from('our_warehouse')->where(['company_id'=>$companyId,'is_fbs'=>1])->scalar($db);
        if (!$fbsId) $fbsId = OurWarehouse::find()->select('id')->where(['company_id'=>$companyId,'is_fbs'=>1])->scalar();
        $fbsName = (new Query())->select('name')->from('our_warehouse')->where(['id'=>$fbsId])->scalar($db);
        $this->logDeduct($companyId, "[NEW] upload source=wb_stock_balance is_fbs=$fbsId ($fbsName) skus=".count($skus)." → PUT на wb_fbs_warehouse is_virtual=".count($warehouses));
        $stocks = WbStockBalance::find()->where(['company_id'=>$companyId,'warehouseId'=>$fbsId])->andWhere(['in','sku',$skus])->all();
        $bySku = [];
        foreach ($stocks as $s) $bySku[$s->sku] = $s;
        // для нулевых остатков (ушли в 0) — строки в balance остались с quantity=0, но если вдруг нет — считаем 0
        $payloadStocks = [];
        foreach ($skus as $sku) {
            $s = $bySku[$sku] ?? null;
            if ($s) $payloadStocks[] = ['chrtId'=>(int)$s->chrtID, 'amount'=>(int)$s->quantity];
            else {
                $size = WbCardSize::findOne(['sku'=>$sku]);
                if ($size) $payloadStocks[] = ['chrtId'=>(int)$size->chrtID, 'amount'=>0];
            }
        }
        foreach ($warehouses as $wh) {
            $chunks = array_chunk($payloadStocks, 1000);
            foreach ($chunks as $idx=>$chunk) {
                $payload = ['stocks'=>$chunk];
                $url = "https://marketplace-api.wildberries.ru/api/v3/stocks/{$wh->warehouseId}";
                $prefix = $isTest ? "[DRY] " : "";
                $this->logDeduct($companyId, $prefix."PUT $url chunk ".($idx+1)."/".count($chunks)." ".json_encode($payload, JSON_UNESCAPED_UNICODE));
                if ($isTest) continue;
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
        // ротация — держать 3 последних дня
        $cut = time() - 3*86400;
        foreach ((glob($dir . '/wb-fbs-deduct-*.log') ?: []) as $old) {
            if (@filemtime($old) < $cut) @unlink($old);
        }
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
