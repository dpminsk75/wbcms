<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Управление складами и остатками FBS (Marketplace API v3).
 *
 *  php yii wb-fbs/sync-warehouses        - GET /api/v3/warehouses
 *  php yii wb-fbs/sync-stocks            - POST /api/v3/stocks/{warehouseId} по всем складам
 *  php yii wb-fbs/sync-stocks --warehouseId=123
 *  php yii wb-fbs/sync-all               - последовательно: склады -> остатки
 */
class WbFbsController extends Controller
{
    public $warehouseId;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), $actionID === 'sync-stocks' ? ['warehouseId'] : []);
    }

    /**
     * Синхронизация списка складов продавца.
     * GET https://marketplace-api.wildberries.ru/api/v3/warehouses
     */
    public function actionSyncWarehouses(): int
    {
        $companies = Yii::$app->companyManager->getActiveCompanies();
        if (empty($companies)) {
            $this->stderr("Нет активных компаний\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $total = 0;
        foreach ($companies as $company) {
            $companyId = (int)$company['id'];
            $token = $company['api_key'] ?? null;
            if (!$token) {
                $this->stdout("Пропуск {$company['name']} - нет api_key\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("\n>>> Склады FBS: {$company['name']} (ID $companyId)\n", Console::FG_CYAN);

            try {
                $response = Yii::$app->wbHttpClient->get(
                    'https://marketplace-api.wildberries.ru/api/v3/warehouses',
                    [],
                    $token,
                    $companyId
                );
            } catch (\Throwable $e) {
                $this->stderr("Сетевой сбой warehouses {$company['name']}: {$e->getMessage()}\n", Console::FG_RED);
                continue;
            }

            if (!$response->isOk) {
                $this->stderr("WB warehouses HTTP {$response->statusCode} {$company['name']}: " . substr($response->content, 0, 500) . "\n", Console::FG_RED);
                continue;
            }

            $data = $response->data;
            // API возвращает массив складов напрямую или {"warehouses": [...]} - поддерживаем оба
            $warehouses = is_array($data) && isset($data['warehouses']) ? $data['warehouses'] : (is_array($data) ? $data : []);

            if (empty($warehouses)) {
                $this->stdout("  Нет складов у {$company['name']}\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("  Получено " . count($warehouses) . " складов\n");

            foreach ($warehouses as $wh) {
                // Поля WB могут быть: id / warehouseId / officeId / name / address
                $wId = $wh['id'] ?? $wh['warehouseId'] ?? null;
                if ($wId === null) {
                    continue;
                }
                $name = $wh['name'] ?? $wh['warehouseName'] ?? "Склад $wId";
                $address = $wh['address'] ?? null;
                $officeId = $wh['officeId'] ?? null;
                // Новые признаки из API (разные написания isDeleting/is_deleting и т.п.)
                $isDeleting = $wh['isDeleting'] ?? $wh['is_deleting'] ?? $wh['isDeleting'] ?? 0;
                $isProcessing = $wh['isProcessing'] ?? $wh['is_processing'] ?? $wh['isProcessing'] ?? 1;
                // API может не отдавать поле - считаем 0/1
                $isDeleting = (int)(bool)$isDeleting;
                $isProcessing = (int)(bool)$isProcessing;

                $row = [
                    'company_id'   => $companyId,
                    'warehouseId'  => (int)$wId,
                    'name'         => $name,
                    'address'      => $address,
                    'officeId'     => $officeId !== null ? (int)$officeId : null,
                    'isActive'     => 1,
                    'is_virtual'   => 0, // не трогаем пользовательскую метку при sync - обновится отдельным toggle
                    'is_deleting'  => $isDeleting,
                    'is_processing'=> $isProcessing,
                    'raw_json'     => json_encode($wh, JSON_UNESCAPED_UNICODE),
                ];

                Yii::$app->db->createCommand()->upsert('wb_fbs_warehouse', $row, [
                    'name'          => $row['name'],
                    'address'       => $row['address'],
                    'officeId'      => $row['officeId'],
                    'isActive'      => 1,
                    'is_deleting'   => $row['is_deleting'],
                    'is_processing' => $row['is_processing'],
                    'raw_json'      => $row['raw_json'],
                ])->execute();
                $total++;
            }
            // пауза для лимита 300/мин ~200ms
            usleep(250000);
        }

        $this->stdout("\nГотово складов: $total\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Синхронизация остатков.
     * POST https://marketplace-api.wildberries.ru/api/v3/stocks/{warehouseId}
     * Body: {"chrtIds": [726112833,...]} required <=1000 (по скрину API)
     */
    public function actionSyncStocks(): int
    {
        $companies = Yii::$app->companyManager->getActiveCompanies();
        if (empty($companies)) {
            $this->stderr("Нет активных компаний\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $filterWarehouseId = $this->warehouseId !== null ? (int)$this->warehouseId : null;
        $totalStocks = 0;

        foreach ($companies as $company) {
            $companyId = (int)$company['id'];
            $token = $company['api_key'] ?? null;
            if (!$token) {
                continue;
            }

            $whQuery = (new \yii\db\Query())
                ->from('wb_fbs_warehouse')
                ->where(['company_id' => $companyId]);
            if ($filterWarehouseId !== null) {
                $whQuery->andWhere(['warehouseId' => $filterWarehouseId]);
            }
            $warehouses = $whQuery->all();

            if (empty($warehouses)) {
                $this->stdout("Нет складов FBS для {$company['name']} - сначала sync-warehouses\n", Console::FG_YELLOW);
                continue;
            }

            // Все chrtId компании из wbcards_sizes (через wbcards.company_id) - API требует chrtIds
            $allChrtIds = (new \yii\db\Query())
                ->select('s.chrtID')
                ->from(['s' => 'wbcards_sizes'])
                ->innerJoin(['c' => 'wbcards'], 'c.nmID = s.nmID')
                ->where(['c.company_id' => $companyId])
                ->distinct()
                ->column(Yii::$app->db);
            $allChrtIds = array_map('intval', array_filter($allChrtIds));
            if (empty($allChrtIds)) {
                $this->stdout("  Нет chrtID для компании {$company['name']} - нечего запрашивать\n", Console::FG_YELLOW);
                continue;
            }
            $this->stdout("\n>>> Остатки FBS: {$company['name']} складов: " . count($warehouses) . " chrtIds: " . count($allChrtIds) . "\n", Console::FG_CYAN);

            foreach ($warehouses as $wh) {
                $wId = (int)$wh['warehouseId'];
                $url = "https://marketplace-api.wildberries.ru/api/v3/stocks/{$wId}";
                $this->stdout("  Склад $wId ({$wh['name']})\n");

                $allStocks = [];
                $chunks = array_chunk($allChrtIds, 1000);
                $this->stdout("    chrtIds " . count($allChrtIds) . " -> " . count($chunks) . " запрос(ов) по 1000\n");

                foreach ($chunks as $idx => $chrtChunk) {
                    $payload = ['chrtIds' => $chrtChunk];
                    $this->stdout("    -> POST $url payload chrtIds[" . count($chrtChunk) . "] sample " . json_encode(array_slice($chrtChunk, 0, 3)) . " chunk " . ($idx + 1) . "/" . count($chunks) . "\n");

                    try {
                        $response = Yii::$app->wbHttpClient->post($url, $payload, $token, $companyId);
                    } catch (\Throwable $e) {
                        $this->stderr("    сетевой сбой chunk " . ($idx + 1) . ": {$e->getMessage()}\n", Console::FG_RED);
                        continue;
                    }

                    $raw = $response->content ?? '';
                    $this->stdout("    <- HTTP {$response->statusCode} raw=" . substr($raw, 0, 3000) . "\n");

                    if (!$response->isOk) {
                        $this->stderr("    HTTP {$response->statusCode}: " . substr($raw, 0, 800) . "\n", Console::FG_RED);
                        continue;
                    }

                    $data = $response->data;
                    if ($data === null) {
                        $data = json_decode($raw, true);
                    }
                    $this->stdout("    parsed keys: " . (is_array($data) ? implode(',', array_keys($data)) : gettype($data)) . "\n");

                    $stocks = $data['stocks'] ?? $data['data']['stocks'] ?? $data['data'] ?? $data['result'] ?? (is_array($data) && isset($data[0]['chrtId']) ? $data : (is_array($data) && isset($data[0]['sku']) ? $data : []));
                    if (!is_array($stocks)) {
                        $stocks = [];
                    }
                    $this->stdout("    => chunk " . ($idx + 1) . ": " . count($stocks) . " stocks\n");
                    if (!empty($stocks)) {
                        $this->stdout("       sample: " . json_encode(array_slice($stocks, 0, 2), JSON_UNESCAPED_UNICODE) . "\n");
                        $allStocks = array_merge($allStocks, $stocks);
                    }
                    usleep(250000);
                }

                $stocks = $allStocks;
                $this->stdout("    => итого склад $wId: " . count($stocks) . " stocks\n");

                if (empty($stocks)) {
                    // очищаем старые остатки этого склада (товары удалили)
                    Yii::$app->db->createCommand()->delete('wb_fbs_stock', [
                        'company_id' => $companyId,
                        'warehouseId' => $wId,
                    ])->execute();
                    usleep(250000);
                    continue;
                }

                // Денормализация: API возвращает stocks по chrtIds, но нам нужен sku для wb_fbs_stock PK
                // Строим карты chrtID->row и sku->row из wbcards_sizes
                $skuList = array_column($stocks, 'sku');
                $chrtListResp = array_filter(array_map(function($s){ return $s['chrtId'] ?? $s['chrtID'] ?? $s['chrt_id'] ?? null; }, $stocks));
                $mapBySku = [];
                $mapByChrt = [];
                $rows = (new \yii\db\Query())
                    ->select(['sku', 'nmID', 'chrtID'])
                    ->from('wbcards_sizes')
                    ->where(['or', ['in', 'sku', $skuList ?: ['__none__']], ['in', 'chrtID', $chrtListResp ?: ['__none__']]])
                    ->all();
                foreach ($rows as $r) {
                    $mapBySku[$r['sku']] = $r;
                    $mapByChrt[(int)$r['chrtID']] = $r;
                }

                $upsertRows = [];
                foreach ($stocks as $s) {
                    $chrtResp = $s['chrtId'] ?? $s['chrtID'] ?? $s['chrt_id'] ?? null;
                    $sku = $s['sku'] ?? $s['barcode'] ?? null;
                    $amount = (int)($s['amount'] ?? $s['quantity'] ?? $s['stock'] ?? 0);

                    // Если sku нет, берём из карты по chrtId
                    if ($sku === null && $chrtResp !== null && isset($mapByChrt[(int)$chrtResp])) {
                        $sku = $mapByChrt[(int)$chrtResp]['sku'];
                    }
                    // Если chrtId нет в ответе, берём из карты по sku
                    $nmID = null;
                    $chrtID = $chrtResp !== null ? (int)$chrtResp : null;
                    if ($sku !== null && isset($mapBySku[$sku])) {
                        $nmID = $mapBySku[$sku]['nmID'];
                        $chrtID = $chrtID ?? (int)$mapBySku[$sku]['chrtID'];
                    } elseif ($chrtResp !== null && isset($mapByChrt[(int)$chrtResp])) {
                        $nmID = $mapByChrt[(int)$chrtResp]['nmID'];
                        $sku = $sku ?? $mapByChrt[(int)$chrtResp]['sku'];
                    }

                    if ($sku === null) {
                        continue;
                    }

                    $upsertRows[] = [
                        'company_id'  => $companyId,
                        'warehouseId' => $wId,
                        'sku'         => (string)$sku,
                        'amount'      => $amount,
                        'nmID'        => $nmID,
                        'chrtID'      => $chrtID,
                    ];
                }

                // Транзакция на склад: удаляем неактуальные sku, вставляем актуальные
                $db = Yii::$app->db;
                $tx = $db->beginTransaction();
                try {
                    // Удаляем sku которых нет в ответе (продажи в 0)
                    $currentSkus = array_column($upsertRows, 'sku');
                    if (!empty($currentSkus)) {
                        $db->createCommand()->delete('wb_fbs_stock', [
                            'and',
                            ['company_id' => $companyId, 'warehouseId' => $wId],
                            ['not in', 'sku', $currentSkus],
                        ])->execute();
                    }

                    foreach (array_chunk($upsertRows, 500) as $chunk) {
                        foreach ($chunk as $row) {
                            $db->createCommand()->upsert('wb_fbs_stock', $row, [
                                'amount' => $row['amount'],
                                'nmID'   => $row['nmID'],
                                'chrtID' => $row['chrtID'],
                            ])->execute();
                        }
                    }
                    $tx->commit();
                } catch (\Throwable $e) {
                    $tx->rollBack();
                    $this->stderr("  Ошибка записи stocks wId $wId: {$e->getMessage()}\n", Console::FG_RED);
                    continue;
                }

                $totalStocks += count($upsertRows);
                usleep(250000);
            }
        }

        $this->stdout("\nГотово остатков строк: $totalStocks\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Последовательно: склады -> остатки.
     * Удобно для cron: php yii wb-fbs/sync-all
     */
    public function actionSyncAll(): int
    {
        $this->stdout("=== FBS sync-all старт ===\n", Console::FG_CYAN);
        $code1 = $this->actionSyncWarehouses();
        if ($code1 !== ExitCode::OK) {
            $this->stderr("sync-warehouses завершился с ошибкой $code1, остатки всё равно пробуем\n", Console::FG_YELLOW);
        }
        $code2 = $this->actionSyncStocks();
        $this->stdout("=== FBS sync-all финиш ===\n", Console::FG_GREEN);
        return $code2 === ExitCode::OK ? $code1 : $code2;
    }
}
