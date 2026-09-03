<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Синхронизация платного хранения WB
 *
 * Алгоритм WB (асинхронный отчёт):
 *   1) GET /api/v1/paid_storage?dateFrom=YYYY-MM-DD&dateTo=YYYY-MM-DD -> {data:{id: taskId}}
 *   2) GET /api/v1/paid_storage/tasks/{taskId}/status -> {data:{id, status: done|started|...}}
 *   3) GET /api/v1/paid_storage/tasks/{taskId}/download -> JSON array отчёта
 *
 * Запуск:
 *   php yii wb-paid-storage/sync
 *   php yii wb-paid-storage/sync --from=2025-03-01 --to=2025-03-31
 *   php yii wb-paid-storage/sync 2025-03-01 2025-03-31
 *
 * По умолчанию — за вчера (dateFrom = dateTo = yesterday).
 */
class WbPaidStorageController extends Controller
{
    public $from;
    public $to;
    public $cache = false;
    public $dumpFile;

    public $defaultAction = 'sync';

    private $baseUrl = 'https://seller-analytics-api.wildberries.ru';

    public function options($actionID)
    {
        return ['from', 'to', 'cache', 'dumpFile'];
    }

    public function optionAliases()
    {
        return ['f' => 'from', 't' => 'to'];
    }

    /**
     * @param string|null $from YYYY-MM-DD или null (yesterday)
     * @param string|null $to YYYY-MM-DD или null (yesterday)
     */
    public function actionSync($from = null, $to = null)
    {
        $db = Yii::$app->db;

        // Приоритет: явный аргумент -> опция --from/--to -> вчера
        $from = $from ?: $this->from;
        $to = $to ?: $this->to;

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $dateFrom = $this->normalizeDate($from) ?: $yesterday;
        $dateTo = $this->normalizeDate($to) ?: $yesterday;

        // Если передан только from, to = from (за один день)
        if ($from && !$to && $this->normalizeDate($from)) {
            // Если пользователь вызвал sync 2025-03-01 без второго аргумента — считаем диапазон 1 день
            // Но если from был через опцию, to уже определён выше как yesterday — не трогаем
            if ($this->from === null && $to === null) {
                $dateTo = $dateFrom;
            }
        }

        if ($dateFrom > $dateTo) {
            $this->stderr("Ошибка: dateFrom ($dateFrom) больше dateTo ($dateTo).\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->ensureTableExists();

        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all($db);

        if (empty($companies)) {
            $this->stderr("Ошибка: Не найдено активных компаний в таблице companies.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("=== Платное хранение: $dateFrom .. $dateTo ===\n", Console::FG_CYAN);

        // WB ограничивает paid_storage 8 днями на один отчёт — бьём длинный период на куски
        $chunks = $this->getDateChunks($dateFrom, $dateTo, 8);
        if (count($chunks) > 1) {
            $this->stdout("Период >8 дней, бьём на " . count($chunks) . " кусков по 8 дней: ", Console::FG_YELLOW);
            foreach ($chunks as $c) $this->stdout("{$c[0]}..{$c[1]} ", Console::FG_YELLOW);
            $this->stdout("\n");
        }

        $totalRows = 0;
        $hasErrors = false;

        foreach ($companies as $company) {
            $companyId = (int)$company['id'];
            $companyName = $company['name'];
            $apiKey = $company['api_key'] ?? null;

            if (!$apiKey) {
                $this->stdout("[-] Пропуск '{$companyName}' (ID: {$companyId}): нет api_key\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("\n>>> {$companyName} (ID: {$companyId}) <<<\n", Console::FG_CYAN);
            $companyRows = 0;

            foreach ($chunks as $idx => $chunk) {
                [$chunkFrom, $chunkTo] = $chunk;
                $this->stdout("\n  -- Кусок " . ($idx+1) . "/" . count($chunks) . ": {$chunkFrom}..{$chunkTo} --\n", Console::FG_CYAN);

                // Кэш: если --cache=1 и файл есть — берём из файла без API
                $cachePath = $this->getCachePath($companyId, $chunkFrom, $chunkTo);
                if ($this->cache && is_file($cachePath)) {
                    $this->stdout("  [кэш] Загружаем из файла {$cachePath} без запроса к WB...\n", Console::FG_CYAN);
                    $cached = json_decode(file_get_contents($cachePath), true);
                    $rows = is_array($cached) ? $cached : null;
                    if (!is_array($rows)) {
                        $this->stderr("  [кэш] Не удалось прочитать кэш, идём в API\n", Console::FG_YELLOW);
                        $rows = null;
                    }
                } else {
                    $rows = null;
                }

                if ($rows === null) {
                    $taskId = $this->createTask($apiKey, $companyId, $chunkFrom, $chunkTo);
                    if (!$taskId) {
                        $this->stderr("Не удалось создать задачу для '{$companyName}' кусок {$chunkFrom}..{$chunkTo}.\n", Console::FG_RED);
                        $hasErrors = true;
                        continue;
                    }

                    $this->stdout("  Задача создана: {$taskId}. Ожидаем готовности...\n");

                    $ready = $this->waitForTask($apiKey, $companyId, $taskId);
                    if (!$ready) {
                        $this->stderr("  Задача {$taskId} не перешла в done (таймаут/ошибка) для '{$companyName}'.\n", Console::FG_RED);
                        $hasErrors = true;
                        continue;
                    }

                    $rows = $this->downloadReport($apiKey, $companyId, $taskId);
                    // Сохраняем сырой ответ для отладки/кэша (по куску)
                    if (is_array($rows)) {
                        $this->saveCache($cachePath, $rows);
                        if ($this->dumpFile) {
                            $this->saveCache($this->dumpFile . ".{$chunkFrom}_{$chunkTo}.json", $rows);
                        }
                    }
                }
                if ($rows === null) {
                    $this->stderr("  Не удалось скачать отчёт для куска {$chunkFrom}..{$chunkTo}.\n", Console::FG_RED);
                    $hasErrors = true;
                    continue;
                }

                if (empty($rows)) {
                    $this->stdout("  Кусок пуст (0 строк).\n", Console::FG_YELLOW);
                    continue;
                }

                $this->stdout("  Скачано строк: " . count($rows) . ". Сохраняем...\n");
                $saved = $this->saveRows($db, $companyId, $rows);
                $this->stdout("  Сохранено: {$saved} строк за кусок {$chunkFrom}..{$chunkTo}.\n", Console::FG_GREEN);
                $companyRows += $saved;
                $totalRows += $saved;

                if ($idx < count($chunks) - 1) {
                    $this->stdout("  Пауза 65с перед следующим куском (лимит 1/мин)...\n", Console::FG_YELLOW);
                    sleep(65);
                }
            }
            $this->stdout("\n>>> Итого для '{$companyName}': {$companyRows} строк за {$dateFrom}..{$dateTo} <<<\n", Console::FG_GREEN);
            sleep(2);
        }

        $this->stdout("\n[ОК] Готово. Всего строк: {$totalRows} за период {$dateFrom}..{$dateTo} (" . count($chunks) . " кусков)\n", Console::FG_GREEN);
        return $hasErrors ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    private function normalizeDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        $ts = strtotime((string)$value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    private function ensureTableExists(): void
    {
        $db = Yii::$app->db;
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `wb_paid_storage` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `date` date NOT NULL,
  `logWarehouseCoef` decimal(5,2) DEFAULT NULL,
  `officeId` int DEFAULT NULL,
  `warehouse` varchar(255) DEFAULT NULL,
  `warehouseCoef` decimal(5,2) DEFAULT NULL,
  `giId` bigint DEFAULT NULL,
  `chrtId` bigint DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `vendorCode` varchar(255) DEFAULT NULL,
  `nmId` int DEFAULT NULL,
  `volume` decimal(10,2) DEFAULT NULL,
  `calcType` varchar(255) DEFAULT NULL,
  `warehousePrice` decimal(10,2) DEFAULT NULL,
  `barcodesCount` int DEFAULT NULL,
  `palletPlaceCode` int DEFAULT NULL,
  `palletCount` int DEFAULT NULL,
  `originalDate` date DEFAULT NULL,
  `loyaltyDiscount` int DEFAULT NULL,
  `tariffFixDate` date DEFAULT NULL,
  `tariffLowerDate` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company_date_chrt` (`company_id`,`date`,`chrtId`),
  KEY `idx_company_date` (`company_id`,`date`),
  KEY `idx_nmId` (`nmId`),
  KEY `idx_warehouse` (`warehouse`(100)),
  KEY `idx_gi` (`giId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
        $db->createCommand($sql)->execute();
        // Убираем старые UNIQUE, которые мешают 1-в-1 (дубли вида 9507->8964)
        foreach (['uid_company_date_chrt_wh_barcode_gi','uid_company_date_chrt_wh_barcode','uid_company_date_chrt_wh_barcode_gi_old'] as $idx) {
            try { $db->createCommand("ALTER TABLE `wb_paid_storage` DROP INDEX `{$idx}`")->execute(); } catch (\Throwable $ignored) {}
        }
    }

    private function createTask(string $apiKey, int $companyId, string $dateFrom, string $dateTo): ?string
    {
        $url = $this->baseUrl . '/api/v1/paid_storage';
        $params = ['dateFrom' => $dateFrom, 'dateTo' => $dateTo];

        // Для создания задачи лимит 1 запр/мин — при 429 ждём X-RateLimit-Retry и повторяем до 5 раз
        $maxCreateAttempts = 5;
        for ($createAttempt = 1; $createAttempt <= $maxCreateAttempts; $createAttempt++) {
            $response = Yii::$app->wbHttpClient->get($url, $params, $apiKey, $companyId, 3);
            $httpCode = (int)$response->getStatusCode();
            $decoded = $response->data;
            if ($decoded === null) {
                $decoded = json_decode($response->content, true);
            }

            if ($httpCode === 429) {
                $wait = $this->extractRetryAfter($response, 60);
                $this->stdout("  429 rate limit, ждём {$wait}с (попытка {$createAttempt}/{$maxCreateAttempts})...\n", Console::FG_YELLOW);
                sleep($wait);
                continue;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $this->stderr("  createTask HTTP {$httpCode}: " . substr((string)$response->content, 0, 1000) . "\n", Console::FG_RED);
                return null;
            }
            // успех — парсим taskId ниже
            goto createTaskParse;
        }
        $this->stderr("  createTask: превышен лимит попыток 429\n", Console::FG_RED);
        return null;
        createTaskParse:
        $decoded = $decoded ?? [];

        // WB может вернуть {data:{id:...}} или {id:...} или {data:{taskId:...}}
        $taskId = $decoded['data']['id'] ?? $decoded['data']['taskId'] ?? $decoded['id'] ?? $decoded['taskId'] ?? null;
        if (is_array($taskId)) {
            $taskId = $taskId['id'] ?? null;
        }
        if (empty($taskId) || !is_string($taskId)) {
            // Иногда API возвращает 200 без тела и задача ставится в очередь — пробуем распарсить иначе
            $this->stderr("  createTask: не найден taskId в ответе: " . json_encode($decoded, JSON_UNESCAPED_UNICODE) . "\n", Console::FG_RED);
            return null;
        }

        return $taskId;
    }

    private function waitForTask(string $apiKey, int $companyId, string $taskId): bool
    {
        $url = $this->baseUrl . "/api/v1/paid_storage/tasks/{$taskId}/status";
        $maxAttempts = 60; // ~10 минут при интервале 10с
        $intervalSec = 10;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = Yii::$app->wbHttpClient->get($url, [], $apiKey, $companyId);
            $httpCode = (int)$response->getStatusCode();
            $decoded = $response->data;
            if ($decoded === null) {
                $decoded = json_decode($response->content, true);
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $this->stderr("  status HTTP {$httpCode} (попытка {$attempt}/{$maxAttempts}): " . substr((string)$response->content, 0, 500) . "\n", Console::FG_YELLOW);
                // не прерываем — возможно временная ошибка, подождём
            } else {
                $status = $decoded['data']['status'] ?? $decoded['status'] ?? null;
                $status = is_string($status) ? strtolower($status) : null;

                $this->stdout("  статус [{$attempt}/{$maxAttempts}]: " . ($status ?: 'unknown') . "\n");

                if ($status === 'done') {
                    return true;
                }
                if (in_array($status, ['purged', 'canceled', 'cancelled', 'error', 'failed'], true)) {
                    $this->stderr("  задача завершена с терминальным статусом: {$status}\n", Console::FG_RED);
                    return false;
                }
                // статусы started/processing/queued — продолжаем ждать
            }

            if ($attempt < $maxAttempts) {
                sleep($intervalSec);
            }
        }

        return false;
    }

    /**
     * @return array|null массив строк отчёта, [] если пусто, null при ошибке
     */
    private function downloadReport(string $apiKey, int $companyId, string $taskId): ?array
    {
        $url = $this->baseUrl . "/api/v1/paid_storage/tasks/{$taskId}/download";
        $response = Yii::$app->wbHttpClient->get($url, [], $apiKey, $companyId);
        $httpCode = (int)$response->getStatusCode();

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->stderr("  download HTTP {$httpCode}: " . substr((string)$response->content, 0, 1000) . "\n", Console::FG_RED);
            return null;
        }

        $content = $response->content;
        $decoded = json_decode($content, true);

        // API отдаёт чистый JSON-массив [...] — json_decode вернёт array с числовыми ключами
        // Иногда обёртка {data:[...]} — поддержим оба варианта
        if (is_array($decoded)) {
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                // Если data — ассоциативный с id/status — это не отчёт, а статус
                // Но для download такого быть не должно
                if (array_key_exists(0, $decoded['data'])) {
                    return $decoded['data'];
                }
                // Если data — обёртка массива
                if (isset($decoded['data'][0]) || empty($decoded['data'])) {
                    return $decoded['data'];
                }
            }
            // Прямой массив отчёта
            if (array_key_exists(0, $decoded) || empty($decoded)) {
                return $decoded;
            }
            // Неожиданный формат — вернём как одну запись
            return [$decoded];
        }

        // Если ответ не JSON (например, gzip или пусто)
        if ($decoded === null && trim((string)$content) !== '') {
            $this->stderr("  download: не удалось декодировать JSON, первые 500 символов: " . substr((string)$content, 0, 500) . "\n", Console::FG_RED);
            return null;
        }

        return [];
    }

    private function saveRows($db, int $companyId, array $rows): int
    {
        $total = count($rows);
        if ($total === 0) {
            return 0;
        }

        $startTime = microtime(true);
        $this->stdout("  Сохранение {$total} строк пачками по 500 (1-в-1, без дедупликации)...\n");

        // 1-в-1: чистим период перед вставкой, чтобы повторный sync не дублировал
        try {
            $dates = array_filter(array_map(fn($r) => $this->toDate($r['date'] ?? null), $rows));
            if (!empty($dates)) {
                $minDate = min($dates);
                $maxDate = max($dates);
                $deleted = $db->createCommand("DELETE FROM `wb_paid_storage` WHERE `company_id`=:cid AND `date` BETWEEN :min AND :max", [':cid'=>$companyId, ':min'=>$minDate, ':max'=>$maxDate])->execute();
                if ($deleted > 0) {
                    $this->stdout("  [очистка] Удалено {$deleted} старых строк за {$minDate}..{$maxDate} для company_id={$companyId}\n", Console::FG_YELLOW);
                }
            }
        } catch (\Throwable $e) {
            $this->stderr("  [очистка] Ошибка DELETE: " . $e->getMessage() . "\n", Console::FG_YELLOW);
        }

        $batchSize = 500;
        $columns = ['company_id','date','logWarehouseCoef','officeId','warehouse','warehouseCoef','giId','chrtId','size','barcode','subject','brand','vendorCode','nmId','volume','calcType','warehousePrice','barcodesCount','palletPlaceCode','palletCount','originalDate','loyaltyDiscount','tariffFixDate','tariffLowerDate'];

        $saved = 0;
        $processed = 0;
        $batch = [];

        $flushBatch = function(array $batch) use ($db, $columns): int {
            if (empty($batch)) return 0;
            $colList = '`' . implode('`,`', $columns) . '`';
            $placeholders = [];
            $params = [];
            foreach ($batch as $i => $row) {
                $ph = [];
                foreach ($columns as $col) {
                    $key = ":{$col}_{$i}";
                    $ph[] = $key;
                    $params[$key] = $row[$col];
                }
                $placeholders[] = '(' . implode(',', $ph) . ')';
            }
            $sql = "INSERT INTO `wb_paid_storage` ($colList) VALUES " . implode(',', $placeholders);
            try {
                $db->createCommand($sql, $params)->execute();
                return count($batch);
            } catch (\Throwable $e) {
                $this->stderr("  batch insert ошибка: " . $e->getMessage() . " — пробуем построчно\n", Console::FG_YELLOW);
                $ok = 0;
                foreach ($batch as $r) {
                    try { $db->createCommand()->insert('wb_paid_storage', $r)->execute(); $ok++; } catch (\Throwable $ignored) {}
                }
                return $ok;
            }
        };

        $filtered = 0;
        foreach ($rows as $row) {
            $processed++;
            $insertValues = [
                'company_id'       => $companyId,
                'date'             => $this->toDate($row['date'] ?? null),
                'logWarehouseCoef' => isset($row['logWarehouseCoef']) ? (float)$row['logWarehouseCoef'] : null,
                'officeId'         => isset($row['officeId']) ? (int)$row['officeId'] : null,
                'warehouse'        => $row['warehouse'] ?? null,
                'warehouseCoef'    => isset($row['warehouseCoef']) ? (float)$row['warehouseCoef'] : null,
                'giId'             => isset($row['giId']) ? (int)$row['giId'] : null,
                'chrtId'           => isset($row['chrtId']) ? (int)$row['chrtId'] : null,
                'size'             => isset($row['size']) ? (string)$row['size'] : null,
                'barcode'          => $row['barcode'] ?? null,
                'subject'          => $row['subject'] ?? null,
                'brand'            => $row['brand'] ?? null,
                'vendorCode'       => $row['vendorCode'] ?? null,
                'nmId'             => isset($row['nmId']) ? (int)$row['nmId'] : null,
                'volume'           => isset($row['volume']) ? (float)$row['volume'] : null,
                'calcType'         => $row['calcType'] ?? null,
                'warehousePrice'   => isset($row['warehousePrice']) ? (float)$row['warehousePrice'] : null,
                'barcodesCount'    => isset($row['barcodesCount']) ? (int)$row['barcodesCount'] : null,
                'palletPlaceCode'  => isset($row['palletPlaceCode']) ? (int)$row['palletPlaceCode'] : null,
                'palletCount'      => isset($row['palletCount']) ? (int)$row['palletCount'] : null,
                'originalDate'     => $this->toDate($row['originalDate'] ?? null),
                'loyaltyDiscount'  => isset($row['loyaltyDiscount']) ? (int)$row['loyaltyDiscount'] : null,
                'tariffFixDate'    => $this->toDate($row['tariffFixDate'] ?? null),
                'tariffLowerDate'  => $this->toDate($row['tariffLowerDate'] ?? null),
            ];
            if (empty($insertValues['date']) || empty($insertValues['chrtId'])) {
                $filtered++;
                continue;
            }
            $batch[] = $insertValues;

            if (count($batch) >= $batchSize || $processed === $total) {
                $saved += $flushBatch($batch);
                $percent = (int)round($processed / $total * 100);
                $elapsed = round(microtime(true) - $startTime, 1);
                $this->stdout("\r  Прогресс сохранения: {$processed}/{$total} ({$percent}%) пачек сохранено {$saved}, {$elapsed}с   ");
                $batch = [];
            }
        }
        if (!empty($batch)) {
            $saved += $flushBatch($batch);
        }
        $this->stdout("\n");
        if ($filtered > 0) {
            $this->stdout("  [диагностика] Отфильтровано пустых date/chrtId: {$filtered}\n", Console::FG_YELLOW);
        }
        try {
            $dates = array_filter(array_map(fn($r) => $this->toDate($r['date'] ?? null), $rows));
            $dbCount = (new \yii\db\Query())->from('wb_paid_storage')->where(['company_id' => $companyId])->andWhere(['between','date', min($dates), max($dates)])->count('*', $db);
            $this->stdout("  [диагностика] Строк в БД на период: {$dbCount} (вход {$total})\n", Console::FG_CYAN);
            if ($dbCount != $total - $filtered) {
                $this->stdout("  [внимание] Ожидалось " . ($total - $filtered) . ", в БД {$dbCount} — проверь фильтры/триггеры\n", Console::FG_YELLOW);
            }
        } catch (\Throwable $ignored) {}
        return $saved;
    }

    private function toDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        $ts = strtotime((string)$value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }

    private function extractRetryAfter($response, int $default = 60): int
    {
        try {
            $headers = $response->getHeaders();
            foreach (['X-RateLimit-Retry','x-ratelimit-retry','Retry-After','retry-after'] as $h) {
                $val = $headers->get($h);
                if ($val !== null) {
                    $val = is_array($val) ? reset($val) : $val;
                    if (is_numeric($val)) return max(1, (int)$val);
                }
            }
            $data = $response->data ?? json_decode($response->content, true);
            if (isset($data['detail']) && preg_match('/(\d+)\s*s/i', $data['detail'], $m)) {
                return max(1, (int)$m[1]);
            }
        } catch (\Throwable $ignored) {}
        return $default;
    }

    private function getCachePath(int $companyId, string $dateFrom, string $dateTo): string
    {
        $dir = Yii::getAlias('@runtime/wb_cache');
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        return $dir . "/paid_storage_{$companyId}_{$dateFrom}_{$dateTo}.json";
    }

    private function getDateChunks(string $from, string $to, int $maxDays): array
    {
        $chunks = [];
        $cur = $from;
        while ($cur <= $to) {
            $end = date('Y-m-d', strtotime($cur . ' +' . ($maxDays - 1) . ' days'));
            if ($end > $to) $end = $to;
            $chunks[] = [$cur, $end];
            $cur = date('Y-m-d', strtotime($end . ' +1 day'));
        }
        return $chunks;
    }

    private function saveCache(string $path, array $rows): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->stdout("  [кэш] Сырой ответ сохранён: {$path} (" . count($rows) . " строк)\n", Console::FG_GREEN);
    }
}
