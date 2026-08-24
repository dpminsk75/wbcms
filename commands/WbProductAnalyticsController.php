<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class WbProductAnalyticsController extends Controller
{
    /**
     * Синхронизация аналитики по товарам (остатки, скорость продаж, доступность):
     *   php yii wb-product-analytics/sync
     *   php yii wb-product-analytics/sync --days=30
     *   php yii wb-product-analytics/sync --stockType=wb
     *
     * Источник: POST /api/v2/stocks-report/products/products
     *
     * ВАЖНО:
     * - stockCount/stockSum отдаются "на сегодня" независимо от периода;
     * - avgOrders/ordersCount и т.п. считаются за currentPeriod (WB сам взвешивает
     *   последний месяц сильнее), поэтому окно стоит брать от 30 дней и больше;
     * - availability считается WB по своему внутреннему окну (последние 7 дней),
     *   не зависит от переданного currentPeriod.
     */
    public $days = 30;
    public $stockType = ''; // '' - все, 'wb' - склады WB, 'mp' - склады продавца

    public function options($actionID)
    {
        return ['days', 'stockType'];
    }

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
        $periodEnd = date('Y-m-d');
        $periodStart = date('Y-m-d', strtotime("-" . ((int)$this->days - 1) . " days"));

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
            $limit = 1000;

            $this->stdout("\nАналитика по товарам для '{$companyName}', период {$periodStart}..{$periodEnd}...\n", Console::FG_CYAN);

            while (true) {
                $this->stdout("Запрос offset=$offset... ");

                $items = $this->fetchProductsReport($apiKey, $companyId, $periodStart, $periodEnd, $offset, $limit);

                if ($items === null) {
                    break; // ошибка уже выведена в fetchProductsReport
                }

                if (empty($items)) {
                    $this->stdout("Данных больше нет. Готово для {$companyName}.\n", Console::FG_GREEN);
                    break;
                }

                $count = count($items);
                $this->stdout("Получено $count строк. UPSERT...\n");

                $this->upsertRows($items, $snapshotDate, $companyId);

                $totalProcessed += $count;
                $offset += $limit;

                $this->stdout("Всего обработано для {$companyName}: $totalProcessed.\n", Console::FG_YELLOW);

                if ($count < $limit) {
                    $this->stdout("Синхронизация для {$companyName} завершена.\n", Console::FG_GREEN);
                    break;
                }

                // Лимит запросов методов stocks-report/sales-funnel обычно 3 в минуту -
                // проверьте фактический лимит по заголовкам/ошибке 429 и скорректируйте при необходимости.
                sleep(20);
            }
        }

        return ExitCode::OK;
    }

    private function upsertRows($items, $snapshotDate, $companyId)
    {
        $db = Yii::$app->db;

        foreach ($items as $item) {
            $metrics = $item['metrics'] ?? [];
            $avgOrdersByMonth = $metrics['avgOrdersByMonth'] ?? [];

            $insertValues = [
                'company_id'                => $companyId,
                'date'                      => $snapshotDate,

                'nm_id'                     => $item['nmID'],
                'vendor_code'               => $item['vendorCode'] ?? null,
                'brand_name'                => $item['brandName'] ?? null,
                'subject_name'              => $item['subjectName'] ?? null,
                'name'                      => $item['name'] ?? null,
                'main_photo'                => $item['mainPhoto'] ?? null,
                'has_sizes'                 => !empty($item['hasSizes']) ? 1 : 0,
                'is_deleted'                => !empty($item['isDeleted']) ? 1 : 0,

                'orders_count'              => (int)($metrics['ordersCount'] ?? 0),
                'orders_sum'                => (float)($metrics['ordersSum'] ?? 0),
                'avg_orders'                => (float)($metrics['avgOrders'] ?? 0),
                'avg_orders_by_month'       => json_encode($avgOrdersByMonth, JSON_UNESCAPED_UNICODE),
                'buyout_count'              => (int)($metrics['buyoutCount'] ?? 0),
                'buyout_sum'                => (float)($metrics['buyoutSum'] ?? 0),
                'buyout_percent'            => (float)($metrics['buyoutPercent'] ?? 0),

                'stock_count'               => (int)($metrics['stockCount'] ?? 0),
                'stock_sum'                 => (float)($metrics['stockSum'] ?? 0),
                'sale_rate_days'            => $metrics['saleRate']['days'] ?? null,
                'sale_rate_hours'           => $metrics['saleRate']['hours'] ?? null,
                'avg_stock_turnover_days'   => $metrics['avgStockTurnover']['days'] ?? null,
                'avg_stock_turnover_hours'  => $metrics['avgStockTurnover']['hours'] ?? null,
                'to_client_count'           => (int)($metrics['toClientCount'] ?? 0),
                'from_client_count'         => (int)($metrics['fromClientCount'] ?? 0),
                'office_missing_days'       => $metrics['officeMissingTime']['days'] ?? 0,
                'office_missing_hours'      => $metrics['officeMissingTime']['hours'] ?? 0,

                'lost_orders_count'         => (int)($metrics['lostOrdersCount'] ?? 0),
                'lost_orders_sum'           => (float)($metrics['lostOrdersSum'] ?? 0),
                'lost_buyouts_count'        => (int)($metrics['lostBuyoutsCount'] ?? 0),
                'lost_buyouts_sum'          => (float)($metrics['lostBuyoutsSum'] ?? 0),

                'min_price'                 => (float)($metrics['currentPrice']['minPrice'] ?? 0),
                'max_price'                 => (float)($metrics['currentPrice']['maxPrice'] ?? 0),
                'availability'              => $metrics['availability'] ?? null,
            ];

            $updateAttributes = $insertValues;
            unset($updateAttributes['company_id'], $updateAttributes['date'], $updateAttributes['nm_id']);

            $db->createCommand()->upsert('wb_products_analytics', $insertValues, $updateAttributes)->execute();
        }
    }

    /**
     * @return array|null Список item'ов, [] если данных больше нет, null при ошибке
     */
    private function fetchProductsReport($apiKey, $companyId, $periodStart, $periodEnd, $offset, $limit)
    {
        $url = "https://seller-analytics-api.wildberries.ru/api/v2/stocks-report/products/products";

        $payload = [
            'nmIDs'               => [],
            'currentPeriod'       => ['start' => $periodStart, 'end' => $periodEnd],
            'stockType'           => $this->stockType,
            'skipDeletedNm'       => true,
            'orderBy'             => ['field' => 'avgOrders', 'mode' => 'desc'],
            'availabilityFilters' => ['deficient', 'actual', 'balanced', 'nonActual', 'nonLiquid', 'invalidData'],
            'limit'               => $limit,
            'offset'              => $offset,
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
