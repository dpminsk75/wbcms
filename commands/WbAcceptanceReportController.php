<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Синхронизация отчёта по платной приёмке WB
 *
 * Алгоритм WB (асинхронный отчёт):
 *   1) GET /api/v1/acceptance_report?dateFrom=YYYY-MM-DD&dateTo=YYYY-MM-DD -> {data:{id: taskId}}
 *   2) GET /api/v1/acceptance_report/tasks/{taskId}/status -> {data:{id, status: done|...}}
 *   3) GET /api/v1/acceptance_report/tasks/{taskId}/download -> JSON array отчёта
 *
 * Пример строки отчёта:
 *   {"count":40,"giCreateDate":"2025-03-04","incomeId":11834106,"nmID":123456789,
 *    "shkCreateDate":"2025-03-14","subjectName":"Добавки пищевые","total":873.04}
 *
 * Запуск:
 *   php yii wb-acceptance-report/sync
 *   php yii wb-acceptance-report/sync --from=2025-03-01 --to=2025-03-31
 *   php yii wb-acceptance-report/sync 2025-03-01 2025-03-31
 *
 * По умолчанию — за вчера.
 */
class WbAcceptanceReportController extends Controller
{
    public $from;
    public $to;
    public $cache = false;
    public $dumpFile;

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
     * @param string|null $from
     * @param string|null $to
     */
    public function actionSync($from = null, $to = null)
    {
        $db = Yii::$app->db;

        $from = $from ?: $this->from;
        $to = $to ?: $this->to;

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $dateFrom = $this->normalizeDate($from) ?: $yesterday;
        $dateTo = $this->normalizeDate($to) ?: $yesterday;

        if ($from && !$to && $this->normalizeDate($from) && $this->from === null && $to === null) {
            $dateTo = $dateFrom;
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

        $this->stdout("=== Платная приёмка: $dateFrom .. $dateTo ===\n", Console::FG_CYAN);

        $chunks = $this->getDateChunks($dateFrom, $dateTo, 31);
        if (count($chunks) > 1) {
            $this->stdout("Период >31 дня, бьём на " . count($chunks) . " кусков по 31 дню: ", Console::FG_YELLOW);
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
                        $this->stderr("  Не удалось создать задачу для '{$companyName}' кусок {$chunkFrom}..{$chunkTo}.\n", Console::FG_RED);
                        $hasErrors = true;
                        continue;
                    }

                    $this->stdout("  Задача создана: {$taskId}. Ожидаем готовности...\n");

                    $ready = $this->waitForTask($apiKey, $companyId, $taskId);
                    if (!$ready) {
                        $this->stderr("  Задача {$taskId} не перешла в done для '{$companyName}'.\n", Console::FG_RED);
                        $hasErrors = true;
                        continue;
                    }

                    $rows = $this->downloadReport($apiKey, $companyId, $taskId);
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
CREATE TABLE IF NOT EXISTS `wb_acceptance_report` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `incomeId` bigint NOT NULL,
  `nmId` int NOT NULL,
  `count` int DEFAULT NULL,
  `giCreateDate` date DEFAULT NULL,
  `shkCreateDate` date DEFAULT NULL,
  `subjectName` varchar(255) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_company_income_nm_dates` (`company_id`,`incomeId`,`nmId`,`giCreateDate`,`shkCreateDate`),
  KEY `idx_company_gi_date` (`company_id`,`giCreateDate`),
  KEY `idx_nmId` (`nmId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL;
        $db->createCommand($sql)->execute();
    }

    private function createTask(string $apiKey, int $companyId, string $dateFrom, string $dateTo): ?string
    {
        $url = $this->baseUrl . '/api/v1/acceptance_report';
        $params = ['dateFrom' => $dateFrom, 'dateTo' => $dateTo];

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
            goto createTaskParse;
        }
        $this->stderr("  createTask: превышен лимит попыток 429\n", Console::FG_RED);
        return null;
        createTaskParse:
        $decoded = $decoded ?? [];

        $taskId = $decoded['data']['id'] ?? $decoded['data']['taskId'] ?? $decoded['id'] ?? $decoded['taskId'] ?? null;
        if (empty($taskId) || !is_string($taskId)) {
            $this->stderr("  createTask: не найден taskId в ответе: " . json_encode($decoded, JSON_UNESCAPED_UNICODE) . "\n", Console::FG_RED);
            return null;
        }

        return $taskId;
    }

    private function waitForTask(string $apiKey, int $companyId, string $taskId): bool
    {
        $url = $this->baseUrl . "/api/v1/acceptance_report/tasks/{$taskId}/status";
        $maxAttempts = 60;
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
            }

            if ($attempt < $maxAttempts) {
                sleep($intervalSec);
            }
        }

        return false;
    }

    /**
     * @return array|null
     */
    private function downloadReport(string $apiKey, int $companyId, string $taskId): ?array
    {
        $url = $this->baseUrl . "/api/v1/acceptance_report/tasks/{$taskId}/download";
        $response = Yii::$app->wbHttpClient->get($url, [], $apiKey, $companyId);
        $httpCode = (int)$response->getStatusCode();

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->stderr("  download HTTP {$httpCode}: " . substr((string)$response->content, 0, 1000) . "\n", Console::FG_RED);
            return null;
        }

        $content = $response->content;
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            if (isset($decoded['data']) && is_array($decoded['data']) && array_key_exists(0, $decoded['data'])) {
                return $decoded['data'];
            }
            if (array_key_exists(0, $decoded) || empty($decoded)) {
                return $decoded;
            }
            return [$decoded];
        }

        if ($decoded === null && trim((string)$content) !== '') {
            $this->stderr("  download: не удалось декодировать JSON: " . substr((string)$content, 0, 500) . "\n", Console::FG_RED);
            return null;
        }

        return [];
    }

    private function saveRows($db, int $companyId, array $rows): int
    {
        $total = count($rows);
        if ($total === 0) return 0;

        $startTime = microtime(true);
        $this->stdout("  Сохранение {$total} строк пачками по 500...\n");

        $batchSize = 500;
        $columns = ['company_id','incomeId','nmId','count','giCreateDate','shkCreateDate','subjectName','total'];
        $updateCols = ['count','subjectName','total'];

        $saved = 0;
        $processed = 0;
        $batch = [];

        $flushBatch = function(array $batch) use ($db, $columns, $updateCols): int {
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
            $updateExpr = implode(', ', array_map(fn($c) => "`$c`=VALUES(`$c`)", $updateCols));
            $sql = "INSERT INTO `wb_acceptance_report` ($colList) VALUES " . implode(',', $placeholders) . " ON DUPLICATE KEY UPDATE $updateExpr";
            try {
                $db->createCommand($sql, $params)->execute();
                return count($batch);
            } catch (\Throwable $e) {
                $this->stderr("  batch upsert ошибка: " . $e->getMessage() . " — пробуем построчно\n", Console::FG_YELLOW);
                $ok = 0;
                foreach ($batch as $r) {
                    try { $db->createCommand()->upsert('wb_acceptance_report', $r, array_intersect_key($r, array_flip($updateCols)))->execute(); $ok++; } catch (\Throwable $ignored) {}
                }
                return $ok;
            }
        };

        foreach ($rows as $row) {
            $processed++;
            $nmId = $row['nmID'] ?? $row['nmId'] ?? null;
            $incomeId = $row['incomeId'] ?? null;
            if (empty($incomeId) || empty($nmId)) continue;

            $insertValues = [
                'company_id'    => $companyId,
                'incomeId'      => (int)$incomeId,
                'nmId'          => (int)$nmId,
                'count'         => isset($row['count']) ? (int)$row['count'] : null,
                'giCreateDate'  => $this->toDate($row['giCreateDate'] ?? null),
                'shkCreateDate' => $this->toDate($row['shkCreateDate'] ?? null),
                'subjectName'   => $row['subjectName'] ?? null,
                'total'         => isset($row['total']) ? (float)$row['total'] : null,
            ];
            $batch[] = $insertValues;

            if (count($batch) >= $batchSize || $processed === $total) {
                $saved += $flushBatch($batch);
                $percent = (int)round($processed / $total * 100);
                $elapsed = round(microtime(true) - $startTime, 1);
                $this->stdout("\r  Прогресс сохранения: {$processed}/{$total} ({$percent}%) пачек сохранено {$saved}, {$elapsed}с   ");
                $batch = [];
            }
        }
        if (!empty($batch)) { $saved += $flushBatch($batch); }
        $this->stdout("\n");
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
        return $dir . "/acceptance_{$companyId}_{$dateFrom}_{$dateTo}.json";
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
