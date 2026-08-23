<?php

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use Yii;
use app\components\WbApi;
use app\models\WbCard;
use app\models\WbSalesFunnelHistory;

class WbFunnelController extends Controller
{
    /**
     * Запуск полной синхронизации всех товаров
     * Вызов: php yii wb-funnel/sync
     */
    public function actionSync($dateFrom = null, $dateTo = null)
    {
//        $dateFrom = date('Y-m-d', strtotime('-7 days'));
//        $dateTo = date('Y-m-d');
        $dateFrom = $dateFrom ?? date('Y-m-d', strtotime('-7 days'));
        $dateTo   = $dateTo   ?? date('Y-m-d');

        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all();

        if (empty($companies)) {
            $this->stdout("Ошибка: Нет активных компаний.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $apiKey = $company['api_key'];
            
            $this->stdout("\n=== КОМПАНИЯ: {$company['name']} ===\n");

            // Инициализация API с токеном компании
            // Если WbApi работает иначе (например, нужен setter), поправь эту строку
            $api = new WbApi($apiKey); 

            $allNmIds = WbCard::find()
                ->select('nmId')
                ->where(['company_id' => $companyId])
                ->column();
            
            if (empty($allNmIds)) {
                $this->stdout("Таблица wbcards пуста для этой компании.\n");
                continue;
            }

            $this->processChunks($allNmIds, $api, $dateFrom, $dateTo, $companyId);
        }

        return ExitCode::OK;
    }

    /**
     * Догрузка только недостающих данных
     * Вызов: php yii wb-funnel/sync-missing
     */
    public function actionSyncMissing()
    {
        $dateFrom = date('Y-m-d', strtotime('-7 days'));
        $dateTo = date('Y-m-d');

        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all();

        if (empty($companies)) {
            $this->stdout("Ошибка: Нет активных компаний.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $apiKey = $company['api_key'];

            $this->stdout("\n=== КОМПАНИЯ: {$company['name']} ===\n");

            $api = new WbApi($apiKey);

            $missingNmIds = WbCard::find()
                ->select('wbcards.nmId')
                ->leftJoin('wb_sales_funnel_history', 'wbcards.nmId = wb_sales_funnel_history.nmId AND wb_sales_funnel_history.date >= :dateFrom AND wb_sales_funnel_history.company_id = :companyId', [
                    ':dateFrom' => $dateFrom,
                    ':companyId' => $companyId
                ])
                ->where([
                    'wbcards.company_id' => $companyId,
                    'wb_sales_funnel_history.nmId' => null
                ])
                ->distinct()
                ->column();

            if (empty($missingNmIds)) {
                $this->stdout("Все данные для компании уже загружены.\n");
                continue;
            }

            $this->processChunks($missingNmIds, $api, $dateFrom, $dateTo, $companyId);
        }

        return ExitCode::OK;
    }

    /**
     * Общая логика обработки чанков
     */
    private function processChunks($nmIds, $api, $dateFrom, $dateTo, $companyId)
    {
        $chunks = array_chunk($nmIds, 20);
        $total = count($chunks);
        $updatedCount = 0;

        foreach ($chunks as $index => $chunk) {
            $this->stdout("Обработка чанка " . ($index + 1) . " из $total...\n");
            
            $this->stdout("DEBUG: Запрос к API | Даты: $dateFrom - $dateTo | nmIds count: " . count($chunk) . "\n");
            $data = $api->getFunnelHistory($chunk, $dateFrom, $dateTo);

            $this->stdout("DEBUG: Ответ получен. Тип данных: " . gettype($data) . "\n");
            if (is_array($data)) {
                $this->stdout("DEBUG: Количество элементов в ответе: " . count($data) . "\n");
            } else {
                $this->stdout("DEBUG: Некорректный ответ от API: " . print_r($data, true) . "\n");
            }

            if (is_array($data)) {
                foreach ($data as $item) {
/*
                    if (isset($item['product']) && isset($item['history'])) {
                        $this->saveData($item, $companyId);
                        $updatedCount++;
                    }
*/
                    if (isset($item['product']['nmId']) && isset($item['history'])) {
                                $nmId = $item['product']['nmId'];
                                
                                // Собираем список дат, которые пришли для этого товара
                                $datesFound = array_column($item['history'], 'date');
                                $datesString = implode(', ', $datesFound);
                                
                                $this->stdout(" -> Товар nmID: $nmId | Даты: $datesString\n");
                                
                                $this->saveData($item, $companyId);
                                $updatedCount++;
                            }
                        }
                    }

            if (($index + 1) < $total) {
                $this->stdout("Ожидание 15 секунд...\n");
                sleep(15);
            }
        }

        $this->stdout("Готово! Обработано товаров: $updatedCount\n");
    }

    private function saveData($item, $companyId)
    {
        $nmId = $item['product']['nmId'];
        
        foreach ($item['history'] as $dayData) {
            $sql = "INSERT INTO wb_sales_funnel_history (company_id, nmId, date, openCount, cartCount, orderCount, orderSum, buyoutCount, buyoutSum) 
                    VALUES (:companyId, :nmId, :date, :open, :cart, :orders, :orderssum, :buyout, :buyoutSum)
                    ON DUPLICATE KEY UPDATE 
                    openCount = VALUES(openCount), 
                    cartCount = VALUES(cartCount), 
                    orderCount = VALUES(orderCount),
                    orderSum = VALUES(orderSum),
                    buyoutCount = VALUES(buyoutCount),
                    buyoutSum = VALUES(buyoutSum)";
            
            Yii::$app->db->createCommand($sql, [
                ':companyId' => $companyId,
                ':nmId'      => $nmId,
                ':date'      => $dayData['date'],
                ':open'      => $dayData['openCount'] ?? 0,
                ':cart'      => $dayData['cartCount'] ?? 0,
                ':orders'    => $dayData['orderCount'] ?? 0,
                ':orderssum' => $dayData['orderSum'] ?? 0,
                ':buyout'    => $dayData['buyoutCount'] ?? 0,
                ':buyoutSum' => $dayData['buyoutSum'] ?? 0,
            ])->execute();
        }
    }

/**
 * Запуск загрузки исторических данных за период
 * Вызов: php yii wb-funnel/sync-history 2026-04-01 2026-04-22
 */
//    public function actionSyncHistory($date)
    public function actionSyncHistory($dateFrom, $dateTo)
    {

        $startTimestamp = strtotime($dateFrom);
        $endTimestamp = strtotime($dateTo);

        if (!$startTimestamp || !$endTimestamp || $startTimestamp > $endTimestamp) {
            $this->stdout("Ошибка: Некорректный период дат. Убедитесь, что формат Y-m-d и дата 'с' меньше или равна дате 'по'.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all();

        foreach ($companies as $company) {
            $this->stdout("Обработка компании: {$company['name']}\n");
            $api = new WbApi($company['api_key']);
            
            // Получаем все nmID для этой компании
            $allNmIds = \app\models\WbCard::find()
                ->select('nmId')
                ->where(['company_id' => $company['id']])
                ->column();

            $chunks = array_chunk($allNmIds, 100);
            $total = count($chunks);


            $currentTimestamp = $startTimestamp;
            while ($currentTimestamp <= $endTimestamp) {
                $currentDate = date('Y-m-d', $currentTimestamp);
                $this->stdout("\n[Дата: $currentDate]\n");

                foreach ($chunks as $index => $chunk) {
                    $this->stdout("Обработка чанка " . ($index + 1) . " из $total...\n");

                    $data = $api->getFunnelDataByDate($chunk, $currentDate);
                    
                    // Меняем $dataResponse на $data
                    if (isset($data['data']['products']) && is_array($data['data']['products'])) {
                        // Перебираем массив товаров
                        foreach ($data['data']['products'] as $item) {
                            $this->saveDataForHistory($item, $company['id'], $currentDate);
                        }
                        
                    } else {
                        $this->stdout("Внимание: Пустой ответ или неверная структура для этой пачки.\n");
                    }
                    $this->stdout("Ожидание 15 секунд...\n");
                    sleep(15); // Не забываем про задержку
                }
            $currentTimestamp = strtotime('+1 day', $currentTimestamp);
            }

        }
        $this->stdout("Загрузка за период $dateFrom — $dateTo завершена.\n");
    }


private function saveDataForHistory($item, $companyId, $date)
{
    // Достаем nmId
    $nmId = $item['product']['nmId'] ?? null;
    
    // Метрики лежат внутри statistic -> selected
    $stats = $item['statistic']['selected'] ?? [];
    
    if (!$nmId) {
        $this->stdout("Ошибка: Не удалось найти nmId для товара\n");
        return;
    }

    $sql = "INSERT INTO wb_sales_funnel_history 
            (company_id, nmId, date, openCount, cartCount, orderCount, orderSum, buyoutCount, buyoutSum) 
            VALUES (:companyId, :nmId, :date, :open, :cart, :orders, :orderssum, :buyout, :buyoutSum)
            ON DUPLICATE KEY UPDATE 
            openCount = VALUES(openCount), 
            cartCount = VALUES(cartCount), 
            orderCount = VALUES(orderCount), 
            orderSum = VALUES(orderSum),
            buyoutCount = VALUES(buyoutCount), 
            buyoutSum = VALUES(buyoutSum)";
            
    Yii::$app->db->createCommand($sql, [
        ':companyId' => $companyId,
        ':nmId'      => $nmId,
        ':date'      => $date,
        // Берем значения из объекта stats (который указывает на selected)
        ':open'      => $stats['openCount'] ?? 0,
        ':cart'      => $stats['cartCount'] ?? 0,
        ':orders'    => $stats['orderCount'] ?? 0,
        ':orderssum' => $stats['orderSum'] ?? 0,
        ':buyout'    => $stats['buyoutCount'] ?? 0,
        ':buyoutSum' => $stats['buyoutSum'] ?? 0,
    ])->execute();
    
    // Добавим вывод для наглядности
    $this->stdout(" -> Сохранен nmID: $nmId (Открытия: " . ($stats['openCount'] ?? 0) . ", Заказы: " . ($stats['orderCount'] ?? 0) . ")\n");
}



}