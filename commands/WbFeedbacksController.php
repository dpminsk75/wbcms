<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Json;

/** * Контроллер для импорта и синхронизации отзывов Wildberries.
 # Пример с коротким флагом за конкретный период
php yii wb-feedbacks/sync -a 2026-04-01 2026-04-30

# Пример с длинным флагом
php yii wb-feedbacks/sync --archiveOnly=1 2026-01-01 2026-03-01
 */
class WbFeedbacksController extends Controller
{
    // Свойство для опции "только архив"
    public $archiveOnly = false;

    /**
     * Регистрируем опции для консольной команды
     */
    public function options($actionID)
    {
        return ['archiveOnly'];
    }

    /**
     * Короткие алиасы для удобного вызова флагов
     */
    public function optionAliases()
    {
        return [
            'a' => 'archiveOnly',
        ];
    }

    /**
     * Синхронизация отзывов WB (Актуальные и/или Архивные).
     * @param string|null $dateFrom Дата начала периода (ГГГГ-ММ-ДД)
     * @param string|null $dateTo Дата окончания периода (ГГГГ-ММ-ДД)
     */
    public function actionSync($dateFrom = null, $dateTo = null)
    {
        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all();

        if (empty($companies)) {
            $this->stderr("Ошибка: Нет активных компаний в БД.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($dateFrom === null) {
            $dateFrom = date('Y-m-d', strtotime('-10 days'));
        }
        if ($dateTo === null) {
            $dateTo = date('Y-m-d');
        }

        $startTimestamp = strtotime($dateFrom . ' 00:00:00');
        $endTimestamp = strtotime($dateTo . ' 23:59:59');

        if ($this->archiveOnly) {
            $this->stdout("Запуск синхронизации ТОЛЬКО АРХИВНЫХ отзывов WB за период ({$dateFrom} - {$dateTo})\n", Console::FG_YELLOW);
        } else {
            $this->stdout("Запуск ПОЛНОЙ синхронизации отзывов WB за период ({$dateFrom} - {$dateTo})\n", Console::FG_GREEN);
        }

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $token = $company['api_key'] ?? null;
            $companyName = $company['name'];

            if (!$token) {
                $this->stdout("\nПропуск {$companyName} - отсутствует токен api_key.\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("\n=== СИНХРОНИЗАЦИЯ КОМПАНИИ: {$companyName} (ID: {$companyId}) ===\n", Console::FG_CYAN);

            $totalProcessed = 0;
            $take = 500;

            // =====================================================================
            // БЛОК 1: ААКТУАЛЬНЫЕ ОТЗЫВЫ (Пропускаем, если передан флаг archiveOnly)
            // =====================================================================
            if (!$this->archiveOnly) {
                $statuses = [
                    'false' => 'НЕОТВЕЧЕННЫЕ',
                    'true'  => 'ОТВЕЧЕННЫЕ'
                ];

                foreach ($statuses as $isAnswered => $statusLabel) {
                    $this->stdout("\n--- НАЧАЛО СБОРА: {$statusLabel} ОТЗЫВЫ ({$companyName}) ---\n", Console::FG_YELLOW);
                    
                    $skip = 0;
                    $hasMore = true;

                    while ($hasMore) {
                        $this->stdout("ФОРМИРОВАНИЕ ЗАПРОСА (skip={$skip}, take={$take}):\n", Console::FG_CYAN);
                        
                        $response = $this->fetchFeedbacks($token, $startTimestamp, $endTimestamp, $skip, $take, $isAnswered);

                        if (empty($response) || !isset($response['data']['feedbacks'])) {
                            $this->stdout("Данные от API не получены или структура ответа неверна.\n", Console::FG_YELLOW);
                            break;
                        }

                        $feedbacks = $response['data']['feedbacks'];
                        $count = count($feedbacks);
                        
                        if ($count === 0) {
                            $this->stdout("Для статуса '{$statusLabel}' список отзывов пуст.\n", Console::FG_YELLOW);
                            break;
                        }

                        $this->stdout("API вернуло {$count} актуальных отзывов. Запись в БД...\n", Console::FG_GREEN);
                        
                        $savedCount = $this->saveFeedbacksToDb($feedbacks, false, $companyId);
                        $totalProcessed += $savedCount;

                        $this->stdout("Обработано в этой пачке: {$savedCount}\n", Console::FG_GREEN);

                        if ($count < $take) {
                            $hasMore = false;
                        } else {
                            $skip += $take;
                        }
                    }
                }
            } else {
                $this->stdout("\n[ИНФО] Сбор актуальных отзывов пропущен (активна опция --archive-only).\n", Console::FG_GREY);
            }

            // ==========================================
            // БЛОК 2: ОТДЕЛЬНЫЙ ЗАПРОС ДЛЯ АРХИВНЫХ ОТЗЫВОВ
            // ==========================================
            $this->stdout("\n--- НАЧАЛО СБОРА: АРХИВНЫЕ ОТЗЫВЫ ({$companyName}) ---\n", Console::FG_YELLOW);
            
            $skipArchive = 0;
            $hasMoreArchive = true;

            while ($hasMoreArchive) {
                $this->stdout("ФОРМИРОВАНИЕ ЗАПРОСА К АРХИВУ (skip={$skipArchive}, take={$take}):\n", Console::FG_CYAN);
                
                $response = $this->fetchArchiveFeedbacks($token, $skipArchive, $take);

                if (empty($response) || !isset($response['data']['feedbacks'])) {
                    $this->stdout("Данные архива от API не получены или структура ответа неверна.\n", Console::FG_YELLOW);
                    break;
                }

                $feedbacks = $response['data']['feedbacks'];
                $count = count($feedbacks);
                
                if ($count === 0) {
                    $this->stdout("Архив пуст или достигнут его конец.\n", Console::FG_YELLOW);
                    break;
                }

                $filteredFeedbacks = [];
                $stopArchiveBatch = false;

                foreach ($feedbacks as $fb) {
                    $fbTime = isset($fb['createdDate']) ? strtotime($fb['createdDate']) : 0;

                    // Если отзыв новее, чем нам надо, пропускаем его и прокручиваем дальше
                    if ($fbTime > $endTimestamp) {
                        continue;
                    }

                    // Внутри диапазона — сохраняем
                    if ($fbTime >= $startTimestamp && $fbTime <= $endTimestamp) {
                        $filteredFeedbacks[] = $fb;
                    }

                    // Архив отсортирован от новых к старым. Если отзыв стал СТАРШЕ dateFrom, 
                    // значит дальше пойдут только старые. Выставляем флаг выхода.
                    if ($fbTime < $startTimestamp) {
                        $stopArchiveBatch = true;
                    }
                }

                $filteredCount = count($filteredFeedbacks);
                $this->stdout("Получено архивных: {$count}. Попало в диапазон дат: {$filteredCount}\n", Console::FG_CYAN);

                if ($filteredCount > 0) {
                    $this->stdout("Запись фильтрованных архивных отзывов в БД...\n", Console::FG_GREEN);
                    $savedCount = $this->saveFeedbacksToDb($filteredFeedbacks, true, $companyId);
                    $totalProcessed += $savedCount;
                    $this->stdout("Сохранено из этой пачки архива: {$savedCount}\n", Console::FG_GREEN);
                }

                if ($count < $take || $stopArchiveBatch) {
                    if ($stopArchiveBatch) {
                        $this->stdout("Достигнуты архивные отзывы старше даты {$dateFrom}. Сбор архива завершен.\n", Console::FG_YELLOW);
                    }
                    $hasMoreArchive = false;
                } else {
                    $skipArchive += $take;
                }
            }

            $this->stdout("\n[УСПЕХ] Синхронизация для {$companyName} завершена. Записано/обновлено: {$totalProcessed}\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * Запрос к стандартному WB API v1/feedbacks
     */
    protected function fetchFeedbacks($token, $dateFrom, $dateTo, $skip, $take, $isAnswered)
    {
        $url = 'https://feedbacks-api.wildberries.ru/api/v1/feedbacks';
        $params = [
            'isAnswered' => $isAnswered, 
            'take' => $take,
            'skip' => $skip,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ];

        return $this->sendCurlRequest($url . '?' . http_build_query($params), $token);
    }

    /**
     * Запрос к архивному WB API v1/feedbacks/archive
     */
    protected function fetchArchiveFeedbacks($token, $skip, $take)
    {
        $url = 'https://feedbacks-api.wildberries.ru/api/v1/feedbacks/archive';
        $params = [
            'take' => $take,
            'skip' => $skip,
        ];

        return $this->sendCurlRequest($url . '?' . http_build_query($params), $token);
    }

    /**
     * Универсальная отправка cURL запроса с логированием для поддержки
     */
    private function sendCurlRequest($fullUrl, $token)
    {
        $curlDebug = sprintf(
            "curl -X GET \"%s\" \\\n  -H \"Authorization: %s\" \\\n  -H \"Content-Type: application/json\"",
            $fullUrl,
            '***ваша_авторизация***'
        );
        
        $this->stdout("Запрос URL: {$fullUrl}\n", Console::FG_GREY);
        $this->stdout("cURL команда для техподдержки:\n{$curlDebug}\n\n", Console::FG_GREY);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->stdout("Ошибка WB API (HTTP Код: {$httpCode})\n", Console::FG_RED);
            $this->stdout("Ответ сервера: {$response}\n\n", Console::FG_RED);
            return null;
        }

        return Json::decode($response);
    }

    /**
     * Сохранение/обновление отзывов через UPSERT с привязкой к компании
     */
    protected function saveFeedbacksToDb(array $feedbacks, $isArchive, $companyId)
    {
        $db = Yii::$app->db;
        $now = time();
        $successCount = 0;

        foreach ($feedbacks as $item) {
            $id = $item['id'] ?? null;
            if (!$id) {
                continue;
            }

            $nmID = $item['productDetails']['nmId'] ?? 0;
            $createdDate = isset($item['createdDate']) ? date('Y-m-d H:i:s', strtotime($item['createdDate'])) : date('Y-m-d H:i:s');
            $updatedDate = isset($item['updatedDate']) ? date('Y-m-d H:i:s', strtotime($item['updatedDate'])) : null;

            $isPay = isset($item['isPay']) ? ($item['isPay'] ? 1 : 0) : 0;
            $fCost = $item['fCost'] ?? $item['bableCost'] ?? null;

            $insertData = [
                'id' => $id,
                'company_id' => $companyId,
                'imtId' => $item['imtId'] ?? 0,
                'nmID' => $nmID,
                'subjectId' => $item['subjectId'] ?? null,
                'subjectName' => $item['subjectName'] ?? null,
                'userName' => $item['userName'] ?? null,
                'matchingSize' => $item['matchingSize'] ?? null,
                'color' => $item['color'] ?? null,
                'text' => $item['text'] ?? null,
                'pros' => $item['pros'] ?? null,
                'cons' => $item['cons'] ?? null,
                'productValuation' => $item['productValuation'] ?? 0,
                'isNew' => isset($item['isNew']) ? ($item['isNew'] ? 1 : 0) : 1,
                'state' => $item['state'] ?? null,
                'status' => $item['status'] ?? null,
                'orderStatus' => $item['orderStatus'] ?? null,
                'is_pay' => $isPay,
                'f_cost' => $fCost,
                'is_archive' => $isArchive ? 1 : 0,
                'createdDate' => $createdDate,
                'updatedDate' => $updatedDate,
                
                'productDetails' => Json::encode($item['productDetails'] ?? []),
                'photoLinks' => isset($item['photoLinks']) ? Json::encode($item['photoLinks']) : null,
                'video' => isset($item['video']) ? Json::encode($item['video']) : null,
                'answer' => isset($item['answer']) ? Json::encode($item['answer']) : null,
                'bables' => isset($item['bables']) ? Json::encode($item['bables']) : null,
                
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $updateData = [
                'subjectId' => $insertData['subjectId'],
                'subjectName' => $insertData['subjectName'],
                'userName' => $insertData['userName'],
                'matchingSize' => $insertData['matchingSize'],
                'color' => $insertData['color'],
                'text' => $insertData['text'],
                'pros' => $insertData['pros'],
                'cons' => $insertData['cons'],
                'productValuation' => $insertData['productValuation'],
                'isNew' => $insertData['isNew'],
                'state' => $insertData['state'],
                'status' => $insertData['status'],
                'orderStatus' => $insertData['orderStatus'],
                'is_pay' => $insertData['is_pay'],
                'f_cost' => $insertData['f_cost'],
                'is_archive' => $insertData['is_archive'],
                'updatedDate' => $insertData['updatedDate'],
                'productDetails' => $insertData['productDetails'],
                'photoLinks' => $insertData['photoLinks'],
                'video' => $insertData['video'],
                'answer' => $insertData['answer'],
                'bables' => $insertData['bables'],
                'updated_at' => $now,
            ];

            try {
                $affectedRows = $db->createCommand()->upsert('{{%wb_feedbacks}}', $insertData, $updateData)->execute();
                
                if ($affectedRows == 1) {
                    $this->stdout("  ID {$id} (nmID: {$nmID}) -> Добавлен [" . ($isArchive ? "АРХИВНЫЙ" : "АКТУАЛЬНЫЙ") . "]\n", Console::FG_GREEN);
                } elseif ($affectedRows == 2) {
                    $this->stdout("  ID {$id} (nmID: {$nmID}) -> Обновлен (is_archive = " . ($isArchive ? "1" : "0") . ")\n", Console::FG_GREEN);
                } else {
                    $this->stdout("  ID {$id} (nmID: {$nmID}) -> Без изменений\n", Console::FG_GREY);
                }
                
                $successCount++;
            } catch (\Exception $e) {
                $this->stdout("  [ОШИБКА БД] Не удалось сохранить отзыв ID {$id}. Текст ошибки: " . $e->getMessage() . "\n", Console::FG_RED);
            }
        }

        return $successCount;
    }
}