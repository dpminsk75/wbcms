<?php
namespace app\commands;

use Yii;
use yii\console\Controller;
use app\models\WbSales;
use app\models\WbOrder;
use yii\helpers\Json;

use DateTime;
use DateInterval;
use DatePeriod;
use yii\helpers\Console;


class WbSalesController extends Controller
{
    /**
     * Загрузка продаж. Пример: php yii wb-sales/fetch 2024-01-01
     */
    public function actionFetch($dateFrom = null)
    {
        if (!$dateFrom) {
            $dateFrom = date('Y-m-d', strtotime('-3 days'));
        }

        $companies = Yii::$app->companyManager->getActiveCompanies();

        if (empty($companies)) {
            $this->stderr("Ошибка: Не найдено активных компаний в таблице companies.\n", Console::FG_RED);
            return \yii\console\ExitCode::UNSPECIFIED_ERROR;
        }

        $totalErrors = 0;

        // eachActiveCompany сам переключает Yii::$app->companyManager->setCurrentId()
        // для каждой компании и сбрасывает контекст после foreach. Это гарантирует,
        // что WbSales::find()/findOne()/unique-валидация (через CompanyScopedTrait)
        // всегда смотрят в правильную компанию, а не в "залипший" или пустой контекст.
        Yii::$app->companyManager->eachActiveCompany(function ($company) use ($dateFrom, &$totalErrors) {
            $companyId = $company['id'];
            $companyName = $company['name'];
            $token = $company['api_key'] ?? null;

            if (!$token) {
                $this->stdout("Пропуск компании '{$companyName}': отсутствует токен api_key.\n", Console::FG_YELLOW);
                return;
            }

            $this->stdout("\nЗагрузка данных для компании '{$companyName}' с даты: $dateFrom\n", Console::FG_CYAN);

            // Используем flag=0 или 1 в зависимости от логики.
            // Для обычного fetch обычно берут flag=0 (все данные с даты),
            // но для синхронизации мы использовали flag=1.
            $url = "https://statistics-api.wildberries.ru/api/v1/supplier/sales?flag=0&dateFrom=" . urlencode($dateFrom);

            $response = $this->makeRequest($url, $token);

            if (!$response) {
                $this->stderr("Не удалось получить данные для компании '{$companyName}'\n", Console::FG_RED);
                return;
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                $this->stderr("Ошибка декодирования JSON для компании '{$companyName}'\n", Console::FG_RED);
                return;
            }

            $this->stdout("Получено " . count($data) . " записей от API. Начинаем обработку...\n");

            // Статистика по датам "как пришло от API" — до всякой фильтрации.
            $receivedByDate = [];
            foreach ($data as $item) {
                $d = date('Y-m-d', strtotime($item['date']));
                $receivedByDate[$d] = ($receivedByDate[$d] ?? 0) + 1;
            }
            ksort($receivedByDate);
            $this->stdout("Пришло от API по датам:\n");
            foreach ($receivedByDate as $d => $cnt) {
                $this->stdout("  {$d}: {$cnt}\n");
            }

            [$count, $errors, $newByDate, $updatedByDate, $ordersUpdated, $ordersNotFound] = $this->processData($data, $companyId);
            $totalErrors += $errors;

            ksort($newByDate);
            ksort($updatedByDate);

            $this->stdout("Записали новых:\n");
            if (empty($newByDate)) {
                $this->stdout("  (нет)\n");
            }
            foreach ($newByDate as $d => $cnt) {
                $this->stdout("  {$d}: {$cnt}\n");
            }

            $this->stdout("Обновили:\n");
            if (empty($updatedByDate)) {
                $this->stdout("  (нет)\n");
            }
            foreach ($updatedByDate as $d => $cnt) {
                $this->stdout("  {$d}: {$cnt}\n");
            }

            $this->stdout("Успешно сохранено/обновлено строк для {$companyName}: {$count}" .
                ($errors ? " (ошибок: {$errors})" : "") . "\n", $errors ? Console::FG_YELLOW : Console::FG_GREEN);

            $this->stdout("Обновлено заказов в wb_order (проставлен sale_id/income_id/sale_date): {$ordersUpdated}\n", Console::FG_GREEN);
            if ($ordersNotFound > 0) {
                $this->stdout("Не найдено соответствующих заказов в wb_order по srid: {$ordersNotFound}\n", Console::FG_YELLOW);
            }

            sleep(1);
        });

        return $totalErrors > 0 ? \yii\console\ExitCode::UNSPECIFIED_ERROR : \yii\console\ExitCode::OK;
    }

    /**
     * Вспомогательный метод для запроса
     */
    private function makeRequest($url, $token)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $token,
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->stdout("HTTP CODE: $httpCode\n");

        // Выводим начало ответа (или весь, если он короткий)
        $this->stdout("RAW RESPONSE: " . mb_strimwidth($response, 0, 1000, "...") . "\n");

        if ($httpCode !== 200) {
            $this->stderr("!!! Ошибка API WB (Код: $httpCode)\n", Console::FG_RED);
            return null;
        }

        return $response;
    }

    /**
     * Сохранение с учетом регистра nmId и привязки к компании.
     *
     * @return array [int $savedCount, int $errorCount, array $newByDate, array $updatedByDate, int $ordersUpdated, int $ordersNotFound]
     */
    private function processData($data, $companyId)
    {
        $count = 0;
        $errors = 0;
        $newByDate = [];
        $updatedByDate = [];
        $ordersUpdated = 0;
        $ordersNotFound = 0;

        foreach ($data as $item) {
            $itemDate = date('Y-m-d', strtotime($item['date']));

            try {
                // Ищем существующую запись по saleID (теперь корректно в рамках
                // текущей компании, т.к. companyManager->setCurrentId() уже вызван
                // в actionFetch через eachActiveCompany).
                $existing = WbSales::findOne(['saleID' => $item['saleID']]);
                $model = $existing ?: new WbSales();
                $isNew = $existing === null;

                // Массовое заполнение оригинальных атрибутов
                $model->attributes = $item;

                // Исправленный регистр колонки (nmId)
                if (isset($item['nmId'])) {
                    $model->nmId = $item['nmId'];
                }

                // Записываем ID компании в поле company_id
                $model->company_id = $companyId;

                if ($model->save()) {
                    $count++;
                    if ($isNew) {
                        $newByDate[$itemDate] = ($newByDate[$itemDate] ?? 0) + 1;
                    } else {
                        $updatedByDate[$itemDate] = ($updatedByDate[$itemDate] ?? 0) + 1;
                    }

                    // Проставляем данные о реализации в исходный заказ (wb_order)
                    // по srid, в рамках текущей компании.
                    if (!empty($item['srid'])) {
                        $affected = $this->linkSaleToOrder($item, $companyId);
                        if ($affected > 0) {
                            $ordersUpdated++;
                        } else {
                            $ordersNotFound++;
                        }
                    }
                } else {
                    $errors++;
                    $this->stderr("Ошибка валидации saleID {$item['saleID']}: " . json_encode($model->getErrors()) . "\n", Console::FG_RED);
                }
            } catch (\Throwable $e) {
                // Не даём одной проблемной записи (например, конфликт уникального
                // индекса saleID в БД) уронить весь процесс и остановить обработку
                // оставшихся компаний.
                $errors++;
                $this->stderr("Исключение при сохранении saleID {$item['saleID']}: " . $e->getMessage() . "\n", Console::FG_RED);
            }
        }

        return [$count, $errors, $newByDate, $updatedByDate, $ordersUpdated, $ordersNotFound];
    }

    /**
     * Проставляет в wb_order признаки реализации (sale_id, income_id, sale_date)
     * для заказа с соответствующим srid.
     *
     * Используем updateAll(), а не поиск+сохранение AR-модели: это одна лёгкая
     * UPDATE-команда без лишней загрузки/валидации, а srid уникален, так что
     * обновится максимум одна строка. company_id в условии — доп. защита от
     * обновления чужой компании, если вдруг где-то встретится дублирующийся srid.
     *
     * @return int количество затронутых строк (0, если заказ с таким srid ещё не загружен)
     */
    private function linkSaleToOrder($item, $companyId)
    {
        try {
            return WbOrder::updateAll(
                [
                    'sale_id' => $item['saleID'],
                    'income_id' => $item['incomeID'] ?? null,
                    'sale_date' => date('Y-m-d H:i:s', strtotime($item['date'])),
                ],
                [
                    'srid' => $item['srid'],
                    'company_id' => $companyId,
                ]
            );
        } catch (\Throwable $e) {
            $this->stderr("Исключение при обновлении wb_order (srid {$item['srid']}): " . $e->getMessage() . "\n", Console::FG_RED);
            return 0;
        }
    }
}
