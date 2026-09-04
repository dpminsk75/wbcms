<?php

/*
php yii wb/sync-cards 
php yii wb/sync-nds
php yii wb/sync-subjects
*/

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\httpclient\Client;
use yii\helpers\Json;
use yii\helpers\Console;
use app\models\WbSubjectCatalog;

/**
 * Консольные команды для работы с Wildberries с поддержкой мультикомпанейности.
 */
class WbController extends Controller
{
    /**
     * Синхронизация карточек товаров Wildberries в таблицу wbcards по всем активным компаниям.
     *
     * Запуск: php yii wb/sync-cards
     *
     * @return int
     */
    public function actionSyncCards(): int
    {
        $db = Yii::$app->db;
        
        // 1. Получаем список всех активных компаний из таблицы companies
        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key']) // Исправлено на api_key
            ->from('companies')
            ->where(['is_active' => 1])
            ->all($db);

        if (empty($companies)) {
            $this->stderr("Не найдено активных компаний в таблице companies для синхронизации.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $totalProcessedAllCompanies = 0;

        // 2. Запускаем цикл по всем кабинетам (компаниям)
        foreach ($companies as $company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
            $token = $company['api_key'] ?? null; // Исправлено на api_key

            if (!$token) {
                $this->stdout("[-] Пропуск компании '{$companyName}' (ID: {$companyId}): отсутствует токен (api_key).\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("\n>>> НАЧАЛО СИНХРОНИЗАЦИИ КАРТОЧЕК ДЛЯ: {$companyName} (ID: {$companyId}) <<<\n", Console::FG_CYAN);

            // Момент начала синхронизации по этой компании.
            // Используется, чтобы после выгрузки понять, какие карточки
            // WB больше не вернул (не удаляем их, а помечаем is_active = 0).
            $syncStartedAt = date('Y-m-d H:i:s');

            $limit = 500;
            $cursorUpdatedAt = null;
            $cursorNmID = null;
            $totalFetched = 0;
            $syncFailed = false;

            while (true) {
                $body = [
                    'settings' => [
                        'sort' => [
                            'ascending' => true,
                        ],
                        'filter' => [
                            'withPhoto' => -1,
                        ],
                        'cursor' => [
                            'limit' => $limit,
                        ],
                    ],
                ];

                if ($cursorUpdatedAt !== null && $cursorNmID !== null) {
                    $body['settings']['cursor']['updatedAt'] = $cursorUpdatedAt;
                    $body['settings']['cursor']['nmID'] = $cursorNmID;
                }

                // Используем WbHttpClient (CurlTransport + ретраи 429/5xx/сеть как в остальных командах)
                // StreamTransport падает на SSL handshake timeout без ретраев - исправлено.
                try {
                    $response = Yii::$app->wbHttpClient->post(
                        'https://content-api.wildberries.ru/content/v2/get/cards/list',
                        $body,
                        $token,
                        $companyId
                    );
                } catch (\Throwable $e) {
                    $this->stderr("WB API сетевой сбой для '{$companyName}': " . $e->getMessage() . " - повтор через 5 сек...\n", Console::FG_RED);
                    sleep(5);
                    try {
                        $response = Yii::$app->wbHttpClient->post(
                            'https://content-api.wildberries.ru/content/v2/get/cards/list',
                            $body,
                            $token,
                            $companyId
                        );
                    } catch (\Throwable $e2) {
                        $this->stderr("WB API повторный сбой для '{$companyName}': " . $e2->getMessage() . "\n", Console::FG_RED);
                        $syncFailed = true;
                        continue 2;
                    }
                }

                if (!$response->isOk) {
                    if ((int)$response->statusCode === 429) {
                        $this->stdout("  429 Too Many Requests - ждём 65 сек и повторяем страницу...\n", Console::FG_YELLOW);
                        sleep(65);
                        continue; // повторить ту же страницу, не пропускать компанию
                    }
                    $this->stderr("WB API error для компании '{$companyName}': HTTP {$response->statusCode} " . substr($response->content, 0, 500) . "\n", Console::FG_RED);
                    $syncFailed = true;
                    continue 2;
                }

                $data = $response->data;
                $cards = $data['cards'] ?? [];
                $cursor = $data['cursor'] ?? null;

                $count = count($cards);
                $totalFetched += $count;
                $this->stdout("Компания '{$companyName}': загружено {$count} карточек (всего по кабинету: {$totalFetched})\n");

                if ($count === 0) {
                    break;
                }

                foreach ($cards as $card) {
                    $this->saveCard($card, $companyId);
                }

                if ($cursor === null || ($cursor['total'] ?? 0) < $limit || $count < $limit) {
                    break;
                }

                $cursorUpdatedAt = $cursor['updatedAt'] ?? null;
                $cursorNmID = $cursor['nmID'] ?? null;

                if ($cursorUpdatedAt === null || $cursorNmID === null) {
                    break;
                }
            }

            // Деактивируем (НЕ удаляем) карточки, которые WB перестал отдавать.
            // Делаем это только если синхронизация по компании прошла без ошибок API -
            // иначе рискуем пометить как пропавшие карточки, которые просто не
            // успели попасть в частично загруженный ответ.
            if (!$syncFailed) {
                try {
                    // Деактивируем только если WB перестал отдавать И нет заказов 30д.
                    // Карточки с заказами оставляем активными (маскирование WB, ошибка выгрузки).
                    $orderDays = 30;
                    $cutoffOrderDate = date('Y-m-d H:i:s', strtotime("-$orderDays days"));
                    $db->createCommand("
                        INSERT INTO wbcards_history (company_id, nmID, field, old_value, new_value, changed_at)
                        SELECT company_id, nmID, 'is_active', '1', '0', :changedAt
                        FROM wbcards w
                        WHERE w.company_id = :companyId AND w.is_active = 1 AND w.last_seen_at < :syncStartedAt
                          AND NOT EXISTS (
                            SELECT 1 FROM wb_order o
                            WHERE o.nm_id = w.nmID AND o.date >= :cutoff
                          )
                    ", [
                        ':changedAt'     => date('Y-m-d H:i:s'),
                        ':companyId'     => $companyId,
                        ':syncStartedAt' => $syncStartedAt,
                        ':cutoff'        => $cutoffOrderDate,
                    ])->execute();

                    $deactivated = $db->createCommand("
                        UPDATE wbcards w
                        SET w.is_active = 0
                        WHERE w.company_id = :companyId AND w.is_active = 1 AND w.last_seen_at < :syncStartedAt
                          AND NOT EXISTS (
                            SELECT 1 FROM wb_order o
                            WHERE o.nm_id = w.nmID AND o.date >= :cutoff
                          )
                    ", [
                        ':companyId' => $companyId,
                        ':syncStartedAt' => $syncStartedAt,
                        ':cutoff' => $cutoffOrderDate,
                    ])->execute();

                    if ($deactivated > 0) {
                        $this->stdout("Компания '{$companyName}': помечено как неактивные (пропали в ответе WB): {$deactivated}\n", Console::FG_YELLOW);
                    }
                } catch (\Exception $e) {
                    $this->stderr("Не удалось деактивировать пропавшие карточки для компании '{$companyName}': " . $e->getMessage() . "\n", Console::FG_RED);
                }
            } else {
                $this->stdout("Компания '{$companyName}': синхронизация завершилась с ошибкой API, деактивация пропавших карточек пропущена.\n", Console::FG_YELLOW);
            }

            $totalProcessedAllCompanies += $totalFetched;
            $this->stdout(">>> Синхронизация кабинета '{$companyName}' успешно завершена. <<<\n", Console::FG_GREEN);
        }

        // 3. Автоматический запуск синхронизации ставок НДС с учетом компаний
        $this->stdout("\nЗапуск синхронизации ставок НДС для всех компаний...\n", Console::FG_CYAN);
        $this->actionSyncNds();

        $this->stdout("\n[ОК] Вся синхронизация карточек по всем брендам завершена! Обработано карточек: $totalProcessedAllCompanies\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Вспомогательный метод сохранения карточки с привязкой к company_id
     */
    protected function saveCard(array $card, $companyId): void
    {
        if (empty($card['nmID'])) {
            $this->stderr("Skip card: nmID is empty\n");
            return;
        }

        $nmID = $card['nmID'];

        $photoUrls = [];
        if (!empty($card['photos'])) {
            foreach ($card['photos'] as $photo) {
                $photoUrls[] = $photo['big'] ?? $photo['c246x328'] ?? ($photo['url'] ?? null);
            }
        }

        $columns = [
            'company_id'      => $companyId,
            'nmID'            => $nmID,
            'imtID'           => $card['imtID'] ?? null,
            'nmUUID'          => $card['nmUUID'] ?? null,
            'subjectID'       => $card['subjectID'] ?? null,
            'subjectName'     => $card['subjectName'] ?? null,
            'vendorCode'      => $card['vendorCode'] ?? null,
            'brand'           => $card['brand'] ?? null,
            'title'           => $card['title'] ?? null,
            'description'     => $card['description'] ?? null,
            'photos'          => Json::encode(array_values(array_filter($photoUrls))),
            'video'           => $card['video'] ?? null,
            'dimensions'      => isset($card['dimensions']) ? Json::encode($card['dimensions']) : null,
            'characteristics' => isset($card['characteristics']) ? Json::encode($card['characteristics']) : null,
            'sizes'           => isset($card['sizes']) ? Json::encode($card['sizes']) : null,
            'tags'            => isset($card['tags']) ? Json::encode($card['tags']) : null,
            'last_seen_at'    => date('Y-m-d H:i:s'),
            'is_active'       => 1,
        ];

        // Поля, изменения которых фиксируем в wbcards_history.
        $trackedFields = [
            'title', 'description', 'photos', 'video', 'dimensions',
            'characteristics', 'sizes', 'tags', 'subjectName', 'brand', 'vendorCode',
        ];

        $db = Yii::$app->db;
        $now = $columns['last_seen_at'];

        try {
            $existing = (new \yii\db\Query())
                ->select(array_merge(['is_active'], $trackedFields))
                ->from('wbcards')
                ->where(['company_id' => $companyId, 'nmID' => $nmID])
                ->one($db);

            $historyRows = [];

            if ($existing === false) {
                // Карточка встречена впервые - фиксируем сам факт появления,
                // без построчного диффа (сравнивать не с чем).
                $historyRows[] = [$companyId, $nmID, '_created', null, null, $now];
            } else {
                foreach ($trackedFields as $field) {
                    $oldVal = $existing[$field] ?? null;
                    $newVal = $columns[$field] ?? null;
                    if ((string)$oldVal !== (string)$newVal) {
                        $historyRows[] = [$companyId, $nmID, $field, $oldVal, $newVal, $now];
                    }
                }

                if ((int)($existing['is_active'] ?? 1) === 0) {
                    // Карточка снова появилась у WB после того, как пропадала.
                    $historyRows[] = [$companyId, $nmID, 'is_active', '0', '1', $now];
                }
            }

            if (!empty($historyRows)) {
                $db->createCommand()->batchInsert(
                    'wbcards_history',
                    ['company_id', 'nmID', 'field', 'old_value', 'new_value', 'changed_at'],
                    $historyRows
                )->execute();
            }

            Yii::$app->db->createCommand()->upsert('wbcards', $columns, [
                'imtID'           => $columns['imtID'],
                'nmUUID'          => $columns['nmUUID'],
                'subjectID'       => $columns['subjectID'],
                'subjectName'     => $columns['subjectName'],
                'vendorCode'      => $columns['vendorCode'],
                'brand'           => $columns['brand'],
                'title'           => $columns['title'],
                'description'     => $columns['description'],
                'photos'          => $columns['photos'],
                'video'           => $columns['video'],
                'dimensions'      => $columns['dimensions'],
                'characteristics' => $columns['characteristics'],
                'sizes'           => $columns['sizes'],
                'tags'            => $columns['tags'],
                'last_seen_at'    => $columns['last_seen_at'],
                'is_active'       => $columns['is_active'],
            ])->execute();

            // Гибрид: JSON в wbcards.sizes остаётся источником, wb_card_size — индексируемая развёртка для остатков FBS
            try {
                \app\models\WbCardSize::syncForCard($nmID, $card['sizes'] ?? null);
            } catch (\Throwable $e) {
                $this->stderr("  -> WbCardSize sync failed nmID={$nmID}: " . $e->getMessage() . "\n");
            }

        } catch (\Exception $e) {
            $this->stderr("Failed to upsert card nmID={$nmID} for company {$companyId}: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Синхронизация ставок НДС из характеристик wbcards в таблицу wbcards_nds с учетом company_id.
     *
     * Запуск отдельно: php yii wb/sync-nds
     *
     * @return int
     */

public function actionSyncNds(): int
    {
        $db = Yii::$app->db;
        $currentDate = date('Y-m-d');
        
        $this->stdout("Начало обработки ставок НДС за {$currentDate}...\n", Console::FG_CYAN);

        $limit = 500;
        $lastNmID = 0;
        $processedCount = 0;
        $updatedCount = 0;

        while (true) {
            $cards = (new \yii\db\Query())
                ->select(['nmID', 'characteristics']) // Выбираем только то, что есть в структуре wbcards
                ->from('wbcards')
                ->where(['>', 'nmID', $lastNmID])
                ->orderBy(['nmID' => SORT_ASC])
                ->limit($limit)
                ->all($db);

            if (empty($cards)) {
                break;
            }

            $transaction = $db->beginTransaction();
            try {
                foreach ($cards as $card) {
                    $nmID = $card['nmID'];
                    $lastNmID = $nmID;

                    $characteristicsRaw = $card['characteristics'];
                    if (empty($characteristicsRaw)) {
                        continue;
                    }

                    $characteristics = Json::decode($characteristicsRaw);
                    if (is_string($characteristics)) {
                        $characteristics = Json::decode($characteristics);
                    }
                    
                    if (!is_array($characteristics)) {
                        continue;
                    }

                    $ndsValue = null;
                    foreach ($characteristics as $char) {
                        if (isset($char['name']) && trim($char['name']) === 'Ставка НДС') {
                            if (isset($char['value'])) {
                                $val = $char['value'];
                                $ndsValue = is_array($val) ? reset($val) : $val;
                            }
                            break;
                        }
                    }

                    if ($ndsValue === null || $ndsValue === '') {
                        continue;
                    }

                    $currentNds = (float)$ndsValue;

                    // УБРАЛИ company_id из WHERE, так как в wbcards_nds его нет
                    $lastNdsRecord = (new \yii\db\Query())
                        ->select(['nds'])
                        ->from('wbcards_nds')
                        ->where(['nmID' => $nmID])
                        ->orderBy(['id' => SORT_DESC])
                        ->one($db);

                    if (!$lastNdsRecord || (float)$lastNdsRecord['nds'] !== $currentNds) {
                        // УБРАЛИ company_id из сохранения
                        $db->createCommand()->upsert('wbcards_nds', [
                            'load_date'  => $currentDate,
                            'nmID'       => $nmID,
                            'nds'        => $currentNds,
                        ], [
                            'nds'        => $currentNds,
                        ])->execute();

                        $updatedCount++;
                    }

                    $processedCount++;
                }

                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                $this->stderr("Ошибка при обработке НДС для nmID={$lastNmID}: " . $e->getMessage() . "\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $this->stdout("Обработка НДС завершена. Успешно: {$processedCount}, записано изменений: {$updatedCount}\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Бекфилл развёртки wbcards.sizes -> wbcards_sizes.
     * Запуск: php yii wb/backfill-card-sizes
     */
    public function actionBackfillCardSizes(): int
    {
        $db = Yii::$app->db;
        $total = (int)(new \yii\db\Query())->from('wbcards')->count('*', $db);
        $this->stdout("Бекфилл wbcards_sizes: всего карточек $total\n", Console::FG_CYAN);

        $done = 0;
        $inserted = 0;
        $batch = 500;
        $lastNmID = 0;

        while (true) {
            $rows = (new \yii\db\Query())
                ->select(['nmID', 'sizes'])
                ->from('wbcards')
                ->where(['>', 'nmID', $lastNmID])
                ->orderBy(['nmID' => SORT_ASC])
                ->limit($batch)
                ->all($db);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $r) {
                $lastNmID = (int)$r['nmID'];
                try {
                    $cnt = \app\models\WbCardSize::syncForCard((int)$r['nmID'], $r['sizes'] ?? null);
                    $inserted += $cnt;
                } catch (\Throwable $e) {
                    $this->stderr("  nmID {$r['nmID']}: " . $e->getMessage() . "\n");
                }
                $done++;
                if ($done % 500 === 0) {
                    $this->stdout("  ... $done / $total, sku вставлено $inserted\n");
                }
            }
        }

        $this->stdout("Готово: карточек $done, всего sku $inserted\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Синхронизация каталога предметов (категорий).
     *
     * Запуск: php yii wb/sync-subjects
     *
     * @return int
     */
    public function actionSyncSubjects(): int
    {
        $token = Yii::$app->params['wbApiTokenContent'] ?? null;
        if (!$token) {
            $db = Yii::$app->db;
            $company = (new \yii\db\Query())
                ->select(['api_key']) // Исправлено на api_key
                ->from('companies')
                ->where(['is_active' => 1])
                ->one($db);
            $token = $company['api_key'] ?? null; // Исправлено на api_key
        }

        if (!$token) {
            $this->stderr("Параметр 'wbApiTokenContent' не задан и в таблице companies нет доступных токенов.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $client = new Client([
            'baseUrl' => 'https://content-api.wildberries.ru',
        ]);

        $limit = 1000;
        $offset = 0;
        $hasMore = true;
        $totalProcessed = 0;

        $this->stdout("Начало синхронизации каталога предметов...\n", Console::FG_CYAN);

        while ($hasMore) {
            $this->stdout("Запрос данных с offset $offset...\n");

            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl('/content/v2/object/all')
                ->setHeaders([
                    'Content-Type'  => 'application/json',
                    'Authorization' => $token,
                ])
                ->setData([
                    'limit'  => $limit,
                    'offset' => $offset,
                    'locale' => 'ru',
                ])
                ->send();

            if (!$response->isOk) {
                $this->stderr("Ошибка API: HTTP {$response->statusCode}\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $data = $response->data;
            $items = $data['data'] ?? [];
            $count = count($items);

            if ($count === 0) {
                $hasMore = false;
                break;
            }

            $db = Yii::$app->db;
            $transaction = $db->beginTransaction();

            try {
                foreach ($items as $item) {
                    $db->createCommand()->upsert(WbSubjectCatalog::tableName(), [
                        'subject_id'   => $item['subjectID'],
                        'subject_name' => $item['subjectName'],
                        'parent_id'    => $item['parentID'],
                        'parent_name'  => $item['parentName'],
                    ], [
                        'subject_name' => $item['subjectName'],
                        'parent_id'    => $item['parentID'],
                        'parent_name'  => $item['parentName'],
                    ])->execute();
                }
                $transaction->commit();
                
                $totalProcessed += $count;
                $offset += $limit;

                if ($count < $limit) {
                    $hasMore = false;
                }

            } catch (\Exception $e) {
                $transaction->rollBack();
                $this->stderr("Ошибка БД: " . $e->getMessage() . "\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $this->stdout("Синхронизация каталога предметов завершена! Всего обработано: $totalProcessed записей.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
}