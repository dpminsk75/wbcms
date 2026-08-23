<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Json;
use yii\db\Query;

class AddressProcessorController extends Controller
{
    /**
     * Главный конвейер обработки адресов (замена actionPrepare и actionSync)
     * php yii address-processor/process 500
     */
    public function actionProcess($limit = 5000)
    {
        $apiKey = Yii::$app->params['dadataApiKey'] ?? null;

        $this->stdout(">>> Старт обработки новых записей (с 2026-01-01)...\n", Console::FG_CYAN);

        // 1. Выбираем целевые продажи без адреса
        $rows = (new Query())
            ->select(['id', 'ppvz_office_id', 'ppvz_office_name', 'srid', 'site_country'])
            ->from('detail_by_period')
            ->where([
                'doc_type_name' => 'Продажа',
                'supplier_oper_name' => 'Продажа',
                'address_id' => null
            ])
            ->andWhere(['>', 'ppvz_office_id', 0])
            ->andWhere(['>=', 'sale_dt', '2026-01-01'])
            ->limit($limit)
            ->all();

        if (empty($rows)) {
            $this->stdout("Нет новых записей для обработки.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        foreach ($rows as $index => $row) {
            $officeId = $row['ppvz_office_id'];
            $srid = $row['srid'];
            $sourceText = $row['ppvz_office_name'] ?: '';
            $country = $row['site_country'] ?: 'Россия';
            $hash = !empty($sourceText) ? md5($sourceText) : null;

            $detailId = $row['id'];
            $num = $index + 1;

            $this->stdout("[$num] ID:$officeId | -" . mb_strimwidth($sourceText, 0, 35, "...") . "- -> ");

            // 2. ИЩЕМ В КЭШЕ (Сначала по ID ПВЗ, потом по хэшу)
            $cacheId = (new Query())
                ->select(['id'])
                ->from('dadata_address_cache')
                ->where(['ppvz_office_id' => $officeId])
                ->one();

// Если по ID не нашли, но есть имя — ищем по хэшу имени
/*
            if (!$cacheId && $hash) {
                $cacheId = (new Query())
                    ->select(['id'])
                    ->from('dadata_address_cache')
                    ->where(['hash' => $hash])
                    ->scalar();
            }
*/
            $this->stdout("Ищем в кеше...", Console::FG_GREEN);

            if ($cacheId) {
                $this->stdout("Нашли в кеше! ", Console::FG_GREEN);
                $this->linkAddress($detailId, $cacheId['id']);
                // Если нашли по хэшу, но ppvz_office_id был пуст - дозаполняем
                Yii::$app->db->createCommand()->update('dadata_address_cache', ['ppvz_office_id' => $officeId], ['id' => $cacheId['id']])->execute();
                $this->stdout("CACHE OK\n", Console::FG_GREEN);
                continue;
            } else $this->stdout("Не нашли в кеше.", Console::FG_GREEN);
            $foundData = null;

            // 3. ЗАРУБЕЖЬЕ (как в actionPrepare)
            if ($country !== 'Россия' && !empty($sourceText)) {
                $cacheId = $this->saveToCache([
                    'ppvz_office_id' => $officeId,
                    'source_text'    => $sourceText,
                    'country'        => $country,
                    'hash'           => $hash,
                    'is_error'       => 1 // Помечаем как требующее внимания (как в prepare)
                ]);
                $this->linkAddress($detailId, $cacheId);
                $this->stdout("SKIP (Зарубежье: $country)\n", Console::FG_YELLOW);
                continue;
            } else $this->stdout("Это Россия... ", Console::FG_GREY);

            // 4. WB_SALES (Бесплатно, по srid)
            $wbSale = (new Query())
                ->select(['oblastOkrugName', 'regionName'])
                ->from('wb_sales')
                ->where(['srid' => $srid])
                ->andWhere(['not', ['regionName' => null]])
                ->one();

            if ($wbSale) {
                $cacheId = $this->processWbSale($row, $wbSale, $apiKey, $hash);
                $this->linkAddress($detailId, $cacheId);
                continue;
            } else $this->stdout("Не нашли в wb_sales...", Console::FG_GREEN);

            // 5. СПРАВОЧНИКИ ПВЗ (2023 / 2026)
            $cacheId = $this->processPvzCatalogs($row, $apiKey, $hash);
            if ($cacheId) {
                $this->linkAddress($detailId, $cacheId);
                continue;
            }else $this->stdout("Не нашли среди ПВЗ...", Console::FG_GREEN);

            // 6. НЕ НАЙДЕНО НИГДЕ (Статус 9)
/*
            $cacheId = $this->saveToCache([
                'ppvz_office_id' => $officeId,
                'source_text'    => $sourceText,
                'country'        => $country,
                'hash'           => $hash,
                'is_error'       => 9
            ]);
            $this->linkAddress($detailId, $cacheId);
*/
            $this->stdout("NOT FOUND (Status 9)\n", Console::FG_RED);
        }

        return ExitCode::OK;
    }

    /**
     * Обработка через wb_sales + попытка обогащения через Dadata
     */
    private function processWbSale($row, $wbSale, $apiKey, $hash)
    {
        $data = [
            'ppvz_office_id'   => $row['ppvz_office_id'],
            'source_text'      => $row['ppvz_office_name'],
            'federal_district' => $wbSale['oblastOkrugName'], // Исправлено на правильные поля
            'region'           => $wbSale['regionName'],
            'country'          => 'Россия',
            'hash'             => $hash,
            'is_error'         => 4 // Базовый статус: из wb_sales
        ];

        // Обогащаем (платно)
        if ($apiKey && !empty($row['ppvz_office_name'])) {
            $res = $this->queryDadata('suggest', ['query' => $row['ppvz_office_name'], 'count' => 1], $apiKey);
            if (!empty($res['suggestions'][0])) {
                $data = array_merge($data, $this->mapDadataFields($res['suggestions'][0]));
                $data['is_error'] = 5; // Обогащено Dadata
                $this->stdout("WB SALES + DADATA OK\n", Console::FG_GREEN);
            } else {
                $this->stdout("WB SALES OK (No Dadata)\n", Console::FG_YELLOW);
            }
        } else {
            $this->stdout("WB SALES OK\n", Console::FG_GREEN);
        }

        return $this->saveToCache($data);
    }

    /**
     * Обработка через справочники ПВЗ
     */
    private function processPvzCatalogs($row, $apiKey, $hash)
    {
        $officeId = $row['ppvz_office_id'];
        $pvz = (new Query())->select(['address', 'lat', 'lon'])->from('pvz_2023')->where(['wb_id' => $officeId])->one();
        if (!$pvz) {
            $pvz = (new Query())->select(['address', 'lat', 'lon'])->from('pvz_2026')->where(['wb_id' => $officeId])->one();
        }

        if (!$pvz || !$apiKey) return null;

        // А) Подсказки по адресу из справочника
        $res = $this->queryDadata('suggest', ['query' => $pvz['address'], 'count' => 1], $apiKey);
        if (!empty($res['suggestions'][0])) {
            $fields = $this->mapDadataFields($res['suggestions'][0]);
            $this->stdout("PVZ SUGGEST OK\n", Console::FG_GREEN);
            return $this->saveToCache(array_merge($fields, [
                'ppvz_office_id' => $officeId,
                'source_text'    => $row['ppvz_office_name'],
                'hash'           => $hash,
                'is_error'       => 7
            ]));
        }

        // Б) Обратное геокодирование
        if (!empty($pvz['lat']) && !empty($pvz['lon'])) {
            $res = $this->queryDadata('geolocate', ['lat' => $pvz['lat'], 'lon' => $pvz['lon']], $apiKey);
            if (!empty($res['suggestions'][0])) {
                $fields = $this->mapDadataFields($res['suggestions'][0]);
                $this->stdout("PVZ GEOLOCATE OK\n", Console::FG_GREEN);
                return $this->saveToCache(array_merge($fields, [
                    'ppvz_office_id' => $officeId,
                    'source_text'    => $row['ppvz_office_name'],
                    'hash'           => $hash,
                    'is_error'       => 8
                ]));
            }
        }

        return null;
    }

    /**
     * Точный маппинг полей из Dadata (со всеми фиксами из actionSync)
     */
    private function mapDadataFields($suggestion)
    {
        $d = $suggestion['data'];
        
        $city = $d['city'] ?? null;
        $cityType = $d['city_type'] ?? null;
        $cityTypeFull = $d['city_type_full'] ?? null;

        // ВЕРНУЛИ: Нормализация Москвы, СПБ, Севастополя
        if (empty($city) && isset($d['region'])) {
            if (in_array($d['region'], ['Москва', 'Санкт-Петербург', 'Севастополь'])) {
                $city = $d['region'];
                $cityType = 'г';
                $cityTypeFull = 'город';
            }
        }

        return [
            'result'                  => $suggestion['value'],
            'postal_code'             => $d['postal_code'] ?? null,
            'country'                 => $d['country'] ?? 'Россия',
            'federal_district'        => $d['federal_district'] ?? null,
            'region_iso_code'         => $d['region_iso_code'] ?? null,
            'region'                  => $d['region_with_type'] ?? $d['region'] ?? null,
            'city'                    => $city,
            'city_type'               => $cityType,
            'city_type_full'          => $cityTypeFull,
            'settlement'              => $d['settlement'] ?? null,
            'settlement_type'         => $d['settlement_type'] ?? null,
            'settlement_type_full'    => $d['settlement_type_full'] ?? null,
            'city_district'           => $d['city_district'] ?? null,
            'city_district_type'      => $d['city_district_type'] ?? null,
            'city_district_type_full' => $d['city_district_type_full'] ?? null,
            'city_area'               => $d['city_area'] ?? null,
//            'area'                    => $d['area'] ?? null,
//            'area_type_full'          => $d['area_type_full'] ?? null,
            'full_json'               => Json::encode($suggestion)
        ];
    }

    private function queryDadata($method, $params, $apiKey)
    {
        $url = ($method == 'suggest') 
            ? "https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address"
            : "https://suggestions.dadata.ru/suggestions/api/4_1/rs/geolocate/address";
            
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Accept: application/json", "Authorization: Token " . $apiKey]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, Json::encode($params));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = Json::decode($res);

        // ВЕРНУЛИ: Проверка Forbidden из actionSync
        if ($httpCode == 429 || (isset($decoded['reason']) && $decoded['reason'] === 'Forbidden')) {
            $this->stdout("\n[!] ОСТАНОВКА: Исчерпан лимит Dadata.\n", Console::FG_RED);
            exit; // Жесткий выход, чтобы не крутить цикл впустую
        }

        return $decoded;
    }

    /**
     * ВЕРНУЛИ: Использование UPSERT, чтобы не было дублей по hash
     */
    private function saveToCache($attributes)
    {
        $attributes['created_at'] = date('Y-m-d H:i:s');
        
        Yii::$app->db->createCommand()
            ->upsert('dadata_address_cache', $attributes)
            ->execute();

        // Поскольку upsert может обновлять, getLastInsertID() не всегда надежен.
        // Безопаснее получить ID по хэшу:
        return (new Query())
            ->select(['id'])
            ->from('dadata_address_cache')
            ->where(['hash' => $attributes['hash']])
            ->scalar();
    }

    private function linkAddress($detailId, $cacheId)
    {
        if ($cacheId) {
            Yii::$app->db->createCommand()
                ->update('detail_by_period', ['address_id' => $cacheId], ['id' => $detailId])
                ->execute();
        }
    }
}