<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class WbStockController extends Controller
{
    /**
     * Синхронизация остатков: php yii wb-stock/sync
     *
     * Метод WB GET /api/v1/supplier/stocks отключён.
     * Используем замену: POST /api/analytics/v1/stocks-report/wb-warehouses
     * (1 строка ответа = 1 размер товара (chrtId) на 1 складе WB)
     */
    public function actionSync()
    {
        $db = Yii::$app->db;

        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all($db);

        if (empty($companies)) {
            $this->stderr("Ошибка: Не найдено активных компаний в таблице companies.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $snapshotDate = date('Y-m-d');

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
            $apiKey = $company['api_key'] ?? null;

            if (!$apiKey) {
                $this->stdout("Пропуск компании '{$companyName}': отсутствует токен api_key.\n", Console::FG_YELLOW);
                continue;
            }

            $totalProcessed = 0;
            $offset = 0;
            $limit = 1000; // максимум разрешён больше, но пагинация страницами удобнее для upsert

            $this->stdout("\nНачинаем синхронизацию остатков для компании '{$companyName}' за $snapshotDate...\n", Console::FG_CYAN);

            while (true) {
                $this->stdout("Запрос данных offset=$offset... ");

                $data = $this->fetchWbStocks($apiKey, $companyId, $offset, $limit);

                if ($data === null) {
                    // Ошибка запроса - fetchWbStocks уже вывел детали, прерываем цикл по этой компании
                    break;
                }

                if (empty($data)) {
                    $this->stdout("Данных больше нет. Синхронизация для {$companyName} завершена.\n", Console::FG_GREEN);
                    break;
                }

                $count = count($data);
                $this->stdout("Получено $count строк. Выполняем UPSERT...\n");

                $this->upsertRows($data, $snapshotDate, $companyId);

                $totalProcessed += $count;
                $offset += $limit;

                $this->stdout("Всего обработано для {$companyName}: $totalProcessed.\n", Console::FG_YELLOW);

                // Если вернулось меньше строк, чем limit - это последняя страница
                if ($count < $limit) {
                    $this->stdout("Синхронизация для {$companyName} завершена.\n", Console::FG_GREEN);
                    break;
                }

                // Лимит нового метода: 1 запрос в 20 секунд на аккаунт продавца
                sleep(21);
            }
        }

        return ExitCode::OK;
    }

    /**
     * Массовое обновление/вставка данных с учетом company_id
     *
     * ВАЖНО: новый отчёт WB не отдаёт tech_size текстом - только chrtId (числовой ID размера).
     * Кладём chrtId в колонку tech_size как временное решение, чтобы не менять уникальный
     * индекс uid_company_stock (company_id, date, warehouse_name, nm_id, tech_size).
     * Поля barcode/supplier_article/category/subject/brand/price/discount/is_supply/
     * is_realization/sc_code новый отчёт не содержит - пишем пустые значения по умолчанию,
     * они больше не обновляются этим методом.
     */
    private function upsertRows($rows, $snapshotDate, $companyId)
    {
        $db = Yii::$app->db;

        foreach ($rows as $item) {
            $quantity = (int)$item['quantity'];
            $inWayToClient = (int)$item['inWayToClient'];
            $inWayFromClient = (int)$item['inWayFromClient'];

            $insertValues = [
                'company_id'         => $companyId,
                'date'               => $snapshotDate,
                'last_change_date'   => date('Y-m-d H:i:s'), // новый отчёт не отдаёт lastChangeDate по строке
                'warehouse_name'     => $item['warehouseName'],
                'supplier_article'   => null,
                'nm_id'              => $item['nmId'],
                'barcode'            => null,
                'quantity'           => $quantity,
                'in_way_to_client'   => $inWayToClient,
                'in_way_from_client' => $inWayFromClient,
                'quantity_full'      => $quantity + $inWayToClient + $inWayFromClient,
                'category'           => null,
                'subject'            => null,
                'brand'              => null,
                'tech_size'          => (string)$item['chrtId'], // временно: числовой ID размера вместо текста
                'price'              => 0,
                'discount'           => 0,
                'is_supply'          => 0,
                'is_realization'     => 0,
                'sc_code'            => null,
            ];

            $updateAttributes = [
                'last_change_date'   => $insertValues['last_change_date'],
                'quantity'           => $quantity,
                'in_way_to_client'   => $inWayToClient,
                'in_way_from_client' => $inWayFromClient,
                'quantity_full'      => $insertValues['quantity_full'],
            ];

            $db->createCommand()->upsert('wb_stocks', $insertValues, $updateAttributes)->execute();
        }
    }

    /**
     * Запрос к Seller Analytics API: остатки по складам WB
     * POST https://seller-analytics-api.wildberries.ru/api/analytics/v1/stocks-report/wb-warehouses
     *
     * @return array|null Массив строк data.items, [] если данных больше нет, null при ошибке запроса
     */
    private function fetchWbStocks($apiKey, $companyId, $offset, $limit)
    {
        $url = "https://seller-analytics-api.wildberries.ru/api/analytics/v1/stocks-report/wb-warehouses";

        $payload = [
            'locale' => 'ru',
            'offset' => $offset,
            'limit'  => $limit,
        ];

        $response = Yii::$app->wbHttpClient->post($url, $payload, $apiKey, $companyId);
        $httpCode = (int)$response->getStatusCode();
        $decoded = $response->data;
        if ($decoded === null) {
            $decoded = json_decode($response->content, true);
        }
        $rawContent = $response->content;

        if ($httpCode !== 200) {
            $this->stderr("\nОшибка API: HTTP $httpCode. Ответ: " . ($rawContent ?: json_encode($decoded)) . "\n", Console::FG_RED);
            return null;
        }

        return $decoded['data']['items'] ?? [];
    }
}