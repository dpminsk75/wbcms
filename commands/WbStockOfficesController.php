<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class WbStockOfficesController extends Controller
{
    /**
     * Синхронизация остатков по складам/регионам:
     *   php yii wb-stock-offices/sync
     *   php yii wb-stock-offices/sync --stockType=wb
     *
     * Источник: POST /api/v2/stocks-report/offices
     *
     * ВАЖНО:
     * - метод отдаёт текущий снимок остатков "на сейчас", currentPeriod почти
     *   не влияет на stockCount/stockSum (подтверждено на форуме WB API);
     * - собственные склады продавца (FBS/DBS/DBW/Самовывоз) приходят одним
     *   агрегатом с regionName "Маркетплейс", без разбивки по конкретным складам -
     *   такие строки пишем с office_id = 0;
     * - без nmIDs (пустой массив) метод возвращает агрегат по ВСЕМ товарам сразу -
     *   то, что нам и нужно для сводки по складам.
     */
    public $stockType = ''; // '' - все, 'wb' - склады WB, 'mp' - склады продавца

    public function options($actionID)
    {
        return ['stockType'];
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
        $today = date('Y-m-d');

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
            $apiKey = $company['api_key'] ?? null;

            if (!$apiKey) {
                $this->stdout("Пропуск компании '{$companyName}': отсутствует токен api_key.\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("\nОстатки по складам для '{$companyName}'...\n", Console::FG_CYAN);

            $regions = $this->fetchOfficesReport($apiKey, $companyId, $today);

            if ($regions === null) {
                continue; // ошибка уже выведена
            }

            $rowsCount = $this->upsertRows($regions, $snapshotDate, $companyId);

            $this->stdout("Записано строк (регион+склад): $rowsCount.\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    private function upsertRows($regions, $snapshotDate, $companyId)
    {
        $db = Yii::$app->db;
        $rowsCount = 0;

        foreach ($regions as $region) {
            $regionName = $region['regionName'];
            $offices = $region['offices'] ?? [];

            if (empty($offices)) {
                // Регион без разбивки по складам (например "Маркетплейс") - пишем итог по региону
                $this->upsertOneRow($db, $companyId, $snapshotDate, $regionName, 0, null, $region['metrics'] ?? []);
                $rowsCount++;
                continue;
            }

            foreach ($offices as $office) {
                $this->upsertOneRow(
                    $db,
                    $companyId,
                    $snapshotDate,
                    $regionName,
                    (int)$office['officeID'],
                    $office['officeName'] ?? null,
                    $office['metrics'] ?? []
                );
                $rowsCount++;
            }
        }

        return $rowsCount;
    }

    private function upsertOneRow($db, $companyId, $snapshotDate, $regionName, $officeId, $officeName, $metrics)
    {
        $insertValues = [
            'company_id'        => $companyId,
            'date'              => $snapshotDate,
            'region_name'       => $regionName,
            'office_id'         => $officeId,
            'office_name'       => $officeName,
            'stock_count'       => (int)($metrics['stockCount'] ?? 0),
            'stock_sum'         => (float)($metrics['stockSum'] ?? 0),
            'sale_rate_days'    => $metrics['saleRate']['days'] ?? null,
            'sale_rate_hours'   => $metrics['saleRate']['hours'] ?? null,
            'to_client_count'   => (int)($metrics['toClientCount'] ?? 0),
            'from_client_count' => (int)($metrics['fromClientCount'] ?? 0),
        ];

        $updateAttributes = [
            'office_name'       => $officeName,
            'stock_count'       => $insertValues['stock_count'],
            'stock_sum'         => $insertValues['stock_sum'],
            'sale_rate_days'    => $insertValues['sale_rate_days'],
            'sale_rate_hours'   => $insertValues['sale_rate_hours'],
            'to_client_count'   => $insertValues['to_client_count'],
            'from_client_count' => $insertValues['from_client_count'],
        ];

        $db->createCommand()->upsert('wb_stocks_offices', $insertValues, $updateAttributes)->execute();
    }

    /**
     * @return array|null Список регионов (data.regions), null при ошибке
     */
    private function fetchOfficesReport($apiKey, $companyId, $today)
    {
        $url = "https://seller-analytics-api.wildberries.ru/api/v2/stocks-report/offices";

        $payload = [
            'nmIDs'         => [],
            'currentPeriod' => ['start' => $today, 'end' => $today],
            'stockType'     => $this->stockType,
            'skipDeletedNm' => false,
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

        return $decoded['data']['regions'] ?? [];
    }
}
