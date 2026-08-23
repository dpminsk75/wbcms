<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class WbPriceController extends Controller
{
    /**
     * Задержка между запросами к API цен, сек.
     * Уточните актуальный лимит запросов в личном кабинете WB / документации
     * (discounts-prices-api имеет отдельный лимит, отличный от stocks-report).
     * Значение ниже — консервативная оценка, скорректируйте при необходимости.
     */
    private const REQUEST_DELAY_SECONDS = 6;

    /**
     * Синхронизация цен и скидок: php yii wb-price/sync
     * Опционально можно синхронизировать один артикул: php yii wb-price/sync 44589768676
     *
     * GET https://discounts-prices-api.wildberries.ru/api/v2/list/goods/filter
     */
    public function actionSync($nmId = null)
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

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
            $apiKey = $company['api_key'] ?? null;

            if (!$apiKey) {
                $this->stdout("Пропуск компании '{$companyName}': отсутствует токен api_key.\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("\nНачинаем синхронизацию цен для компании '{$companyName}'...\n", Console::FG_CYAN);

            // Карта текущих (is_latest = 1) цен компании: "nmId_sizeId" => row
            $currentMap = $this->loadCurrentPricesMap($db, $companyId);

            $oldCount = 0; // цена/скидка не изменились - просто обновили updated_at
            $newCount = 0; // новая запись (новый товар/размер или изменившаяся цена)
            $offset = 0;
            $limit = 300;

            while (true) {
                $this->stdout("Запрос данных offset=$offset... ");

                [$httpCode, $goods] = $this->fetchWbPrices($apiKey, $offset, $limit, $nmId);

                $this->stdout("HTTP $httpCode. ");

                if ($goods === null) {
                    // Ошибка запроса - детали уже выведены в fetchWbPrices
                    break;
                }

                if (empty($goods)) {
                    $this->stdout("Данных больше нет.\n");
                    break;
                }

                $count = count($goods);
                $this->stdout("Получено товаров: $count. Обрабатываем...\n");

                $this->processGoods($db, $goods, $companyId, $currentMap, $oldCount, $newCount);

                $offset += $limit;

                // Если nmId задан явно - это точечный запрос, дальше страниц не будет
                if ($nmId !== null || $count < $limit) {
                    break;
                }

                sleep(self::REQUEST_DELAY_SECONDS);
            }

            $this->stdout(
                "Синхронизация цен для '{$companyName}' завершена. "
                . "Старых (без изменений): $oldCount, новых/изменённых: $newCount.\n",
                Console::FG_GREEN
            );
        }

        return ExitCode::OK;
    }

    /**
     * Подгружает в память все текущие (is_latest = 1) цены компании,
     * чтобы не делать по SELECT на каждый размер каждого товара.
     *
     * @return array ключ "nmId_sizeId" => ['id'=>..,'price'=>..,'discounted_price'=>..,
     *               'club_discounted_price'=>..,'discount'=>..,'club_discount'=>..]
     */
    private function loadCurrentPricesMap($db, $companyId)
    {
        $rows = (new \yii\db\Query())
            ->select([
                'id', 'nm_id', 'size_id', 'price',
                'discounted_price', 'club_discounted_price',
                'discount', 'club_discount',
            ])
            ->from('wb_price_history')
            ->where(['company_id' => $companyId, 'is_latest' => 1])
            ->all($db);

        $map = [];
        foreach ($rows as $row) {
            $key = $row['nm_id'] . '_' . $row['size_id'];
            $map[$key] = $row;
        }

        return $map;
    }

    /**
     * Обрабатывает страницу товаров: сравнивает с картой текущих цен,
     * пишет в БД, обновляет счётчики и саму карту (на случай если товар
     * повторно встретится в рамках этого же запуска).
     */
    private function processGoods($db, array $goods, $companyId, array &$currentMap, &$oldCount, &$newCount)
    {
        $now = date('Y-m-d H:i:s');

        foreach ($goods as $item) {
            $nmId = $item['nmID'];
            $vendorCode = $item['vendorCode'] ?? null;
            $currency = $item['currencyIsoCode4217'] ?? null;
            $discount = (int)($item['discount'] ?? 0);
            $clubDiscount = (int)($item['clubDiscount'] ?? 0);
            $isBadTurnover = !empty($item['isBadTurnover']) ? 1 : 0;

            foreach (($item['sizes'] ?? []) as $size) {
                $sizeId = $size['sizeID'];
                $price = $size['price'];
                $discountedPrice = $size['discountedPrice'];
                $clubDiscountedPrice = $size['clubDiscountedPrice'] ?? null;
                $techSizeName = $size['techSizeName'] ?? null;

                $key = $nmId . '_' . $sizeId;
                $existing = $currentMap[$key] ?? null;

                if ($existing === null) {
                    // Новый товар/размер - просто вставляем
                    $newId = $this->insertPriceRow($db, [
                        'company_id' => $companyId,
                        'nm_id' => $nmId,
                        'vendor_code' => $vendorCode,
                        'size_id' => $sizeId,
                        'tech_size_name' => $techSizeName,
                        'price' => $price,
                        'discounted_price' => $discountedPrice,
                        'club_discounted_price' => $clubDiscountedPrice,
                        'currency_code' => $currency,
                        'discount' => $discount,
                        'club_discount' => $clubDiscount,
                        'is_bad_turnover' => $isBadTurnover,
                        'is_latest' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $newCount++;

                    $currentMap[$key] = [
                        'id' => $newId,
                        'nm_id' => $nmId,
                        'size_id' => $sizeId,
                        'price' => $price,
                        'discounted_price' => $discountedPrice,
                        'club_discounted_price' => $clubDiscountedPrice,
                        'discount' => $discount,
                        'club_discount' => $clubDiscount,
                    ];

                    continue;
                }

                $unchanged = $this->pricesEqual($existing, $price, $discountedPrice, $clubDiscountedPrice, $discount, $clubDiscount);

                if ($unchanged) {
                    // Цена/скидка не менялась - только подтверждаем updated_at
                    $db->createCommand()->update(
                        'wb_price_history',
                        ['updated_at' => $now],
                        ['id' => $existing['id']]
                    )->execute();

                    $oldCount++;
                    continue;
                }

                // Цена/скидка изменилась - закрываем старую запись, вставляем новую
                $db->createCommand()->update(
                    'wb_price_history',
                    ['is_latest' => 0, 'updated_at' => $now],
                    ['id' => $existing['id']]
                )->execute();

                $newId = $this->insertPriceRow($db, [
                    'company_id' => $companyId,
                    'nm_id' => $nmId,
                    'vendor_code' => $vendorCode,
                    'size_id' => $sizeId,
                    'tech_size_name' => $techSizeName,
                    'price' => $price,
                    'discounted_price' => $discountedPrice,
                    'club_discounted_price' => $clubDiscountedPrice,
                    'currency_code' => $currency,
                    'discount' => $discount,
                    'club_discount' => $clubDiscount,
                    'is_bad_turnover' => $isBadTurnover,
                    'is_latest' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $newCount++;

                $currentMap[$key] = [
                    'id' => $newId,
                    'nm_id' => $nmId,
                    'size_id' => $sizeId,
                    'price' => $price,
                    'discounted_price' => $discountedPrice,
                    'club_discounted_price' => $clubDiscountedPrice,
                    'discount' => $discount,
                    'club_discount' => $clubDiscount,
                ];
            }
        }
    }

    private function pricesEqual(array $existing, $price, $discountedPrice, $clubDiscountedPrice, $discount, $clubDiscount)
    {
        return (float)$existing['price'] === (float)$price
            && (float)$existing['discounted_price'] === (float)$discountedPrice
            && (float)($existing['club_discounted_price'] ?? 0) === (float)($clubDiscountedPrice ?? 0)
            && (int)$existing['discount'] === (int)$discount
            && (int)$existing['club_discount'] === (int)$clubDiscount;
    }

    private function insertPriceRow($db, array $values)
    {
        $db->createCommand()->insert('wb_price_history', $values)->execute();

        return $db->getLastInsertID();
    }

    /**
     * Запрос к discounts-prices-api: список товаров с ценами/скидками
     *
     * @return array [httpCode, items|null] items = null означает ошибку запроса
     */
    private function fetchWbPrices($apiKey, $offset, $limit, $nmId = null)
    {
        $query = [
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($nmId !== null) {
            $query['filterNmID'] = $nmId;
        }

        $url = "https://discounts-prices-api.wildberries.ru/api/v2/list/goods/filter?" . http_build_query($query);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $apiKey,
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->stderr("\nОшибка API: HTTP $httpCode. Ответ: $response\n", Console::FG_RED);
            return [$httpCode, null];
        }

        $decoded = json_decode($response, true);

        if (!empty($decoded['error'])) {
            $this->stderr("\nОшибка API: " . ($decoded['errorText'] ?? 'unknown') . "\n", Console::FG_RED);
            return [$httpCode, null];
        }

        return [$httpCode, $decoded['data']['listGoods'] ?? []];
    }
}
