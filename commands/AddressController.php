<?php
/*

работа через wb_sales
php yii address/prepare 500 
*/


namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Json;
use yii\db\Query;

class AddressController extends Controller
{
    /**
     * Обработка адресов через Dadata (Метод Подсказки)
     * @param int $limit Лимит записей за один запуск
     */
    public function actionSync($limit = 100)
    {
        $apiKey = Yii::$app->params['dadataApiKey'] ?? null;

        if (!$apiKey) {
            $this->stderr("Ошибка: Не задан dadataApiKey в params.php\n", Console::FG_RED);
            return ExitCode::CONFIG;
        }

        $this->stdout("Поиск новых адресов...\n", Console::FG_CYAN);
        
        // Подзапрос для исключения уже обработанных по хэшу
        $subQuery = (new Query())->select(['hash'])->from('dadata_address_cache');
        
        $addresses = (new Query())
            ->select(['ppvz_office_name', 'site_country'])
            ->distinct()
            ->from('detail_by_period')
            ->where(['not', ['ppvz_office_name' => null]])
            ->andWhere(['not', ['ppvz_office_name' => '']])
            ->andWhere(['not in', 'MD5(ppvz_office_name)', $subQuery])
            ->limit($limit)
            ->all();

        $totalFound = count($addresses);
        if ($totalFound === 0) {
            $this->stdout("Новых адресов для обработки нет.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout("К обработке: $totalFound\n\n", Console::FG_YELLOW);

        $successCount = 0;
        $errorCount = 0;

        foreach ($addresses as $index => $row) {
            $source = $row['ppvz_office_name'];
            $country = $row['site_country'];
            $num = $index + 1;
            $hash = md5($source);

            $this->stdout("[$num/$totalFound] ", Console::FG_GREY);
            $this->stdout(mb_strimwidth($source, 0, 45, "...") . " [$country] -> ");

            // Если не Россия — сохраняем как есть
            if ($country !== 'Россия') {
                $this->saveToCache([
                    'source_text' => $source,
                    'country'     => $country,
                    'hash'        => $hash,
                    'result'      => $source,
                    'is_error'    => 0
                ]);
                $this->stdout("SKIP (not Russia)\n", Console::FG_YELLOW);
                $successCount++;
                continue;
            }

            try {
                $rawResponse = $this->queryDadataSuggest($source, $apiKey);
                $responseArray = Json::decode($rawResponse);

                // Если API вернуло ошибку (например, 403 или 401)
                /*
                if (isset($responseArray['error'])) {
                    $this->stdout("API ERROR: " . ($responseArray['message'] ?? $responseArray['code']) . "\n", Console::FG_RED);
                    $errorCount++;
                    continue;
                }
                */

                if (isset($responseArray['error']) || isset($responseArray['reason'])) {
                    $reason = $responseArray['reason'] ?? $responseArray['error'] ?? 'Unknown error';
                    $message = $responseArray['message'] ?? '';

                    // Если лимит исчерпан или сервис отключен для токена
                    if ($reason === 'Forbidden' || mb_stripos($message, 'disabled for token') !== false) {
                        $this->stdout("\n[!] ОСТАНОВКА: Исчерпан лимит или сервис SUGGESTIONS недоступен.\n", Console::FG_RED);
                        $this->stdout("Сообщение: $message\n", Console::FG_YELLOW);
                        break; // Прекращаем цикл foreach, команда завершается
                    }

                    $this->stdout("API ERROR: $reason ($message)\n", Console::FG_RED);
                    $errorCount++;
                    continue;
                }

                $suggestion = $responseArray['suggestions'][0] ?? null;
                $data = $suggestion['data'] ?? null;

                if ($data) {
                    $city = $data['city'] ?? null;
                    $cityType = $data['city_type'] ?? null;
                    $cityTypeFull = $data['city_type_full'] ?? null;

                    // Нормализация Москвы, СПБ, Севастополя
                    if (empty($city) && isset($data['region'])) {
                        if (in_array($data['region'], ['Москва', 'Санкт-Петербург', 'Севастополь'])) {
                            $city = $data['region'];
                            $cityType = 'г';
                            $cityTypeFull = 'город';
                        }
                    }

                    $this->saveToCache([
                        'source_text'            => $source,
                        'country'                => $country,
                        'hash'                   => $hash,
                        'result'                 => $suggestion['value'] ?? $source,
                        'postal_code'            => $data['postal_code'] ?? null,
                        'federal_district'       => $data['federal_district'] ?? null,
                        'region'                 => $data['region'] ?? null,
                        'city'                   => $city,
                        'city_type'              => $cityType,
                        'city_type_full'         => $cityTypeFull,
                        'settlement'             => $data['settlement'] ?? null,
                        'settlement_type'        => $data['settlement_type'] ?? null,
                        'settlement_type_full'   => $data['settlement_type_full'] ?? null,
                        'city_district'           => $data['city_district'] ?? null,
                        'city_district_type'      => $data['city_district_type'] ?? null,
                        'city_district_type_full' => $data['city_district_type_full'] ?? null,
                        'city_area'               => $data['city_area'] ?? null,
                        'full_json'               => $rawResponse,
                        'is_error'                => 0
                    ]);

                    $display = $city ?? $data['settlement'] ?? 'н/п';
                    $this->stdout("OK", Console::FG_GREEN);
                    $this->stdout(" ($display)\n");
                    $successCount++;
                } else {
                    $this->saveToCache([
                        'source_text' => $source,
                        'country'     => $country,
                        'hash'        => $hash,
                        'result'      => 'NOT_FOUND',
                        'is_error'    => 1,
                        'full_json'   => $rawResponse
                    ]);
                    $this->stdout("NOT FOUND\n", Console::FG_RED);
                    // Опционально: сохраняем пустой результат, чтобы не дергать API снова
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $this->stdout("EXCEPTION: " . $e->getMessage() . "\n", Console::FG_RED);
                $errorCount++;
            }

            usleep(150000); // 0.15 сек пауза
        }

        $this->stdout("\nГотово! Успешно: $successCount, Ошибок: $errorCount\n", Console::BOLD);
        return ExitCode::OK;
    }

    /**
     * Запрос к API Подсказок
     */
    private function queryDadataSuggest($address, $apiKey)
    {
        $ch = curl_init("https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json",
            "Authorization: Token " . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, Json::encode([
            "query" => $address,
            "count" => 1
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return Json::encode(['error' => 'HTTP_ERROR', 'code' => $httpCode, 'message' => $result]);
        }

        return $result;
    }

/**
     * Обратное геокодирование через Dadata
     */
    protected function queryDadataGeolocate($lat, $lon, $apiKey)
    {
        $url = "https://suggestions.dadata.ru/suggestions/api/4_1/rs/geolocate/address";
        $fields = [
            "lat" => $lat,
            "lon" => $lon,
            "count" => 1
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json",
            "Authorization: Token " . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return Json::encode(['error' => 'HTTP_ERROR', 'code' => $httpCode, 'message' => $result]);
        }

        return $result;
    }

    /**
     * Вспомогательный метод сохранения
     */
/*
    private function saveToCache($attributes)
    {
        return Yii::$app->db->createCommand()
            ->insert('dadata_address_cache', $attributes)
            ->execute();
    }
*/
private function saveToCache($attributes)
{
    // upsert проверит уникальный индекс (hash) 
    // и если он совпадет, просто обновит поля новыми значениями
    return Yii::$app->db->createCommand()
        ->upsert('dadata_address_cache', $attributes)
        ->execute();
}
/**
 * Восстановление данных из таблицы wb_sales для ошибок и других стран
 * Запуск: php yii address/repair 500
 */
    public function actionRepair($limit = 500)
    {
        $this->stdout("Начинаем процесс восстановления из wb_sales...\n", Console::FG_CYAN);

        // Ищем записи в кеше, которые либо помечены как ошибка (1), 
        // либо это другие страны, где регионы еще не заполнены
        $cacheEntries = (new Query())
            ->from('dadata_address_cache')
            ->where(['is_error' => 1])
            ->orWhere(['and', 
                ['not', ['country' => 'Россия']], 
                ['region' => null]
            ])
            ->limit($limit)
            ->all();

        if (empty($cacheEntries)) {
            $this->stdout("Нет данных для восстановления.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $repairedCount = 0;

        foreach ($cacheEntries as $entry) {
            $source = $entry['source_text'];
            $this->stdout("Проверка: " . mb_strimwidth($source, 0, 40, "...") . " -> ");

            // Ищем связь: Cache -> detail_by_period -> wb_sales
            // Берем первую попавшуюся запись, так как регионы для одного адреса должны совпадать
            $wbData = (new Query())
                ->select(['s.oblastOkrugName', 's.regionName'])
                ->from(['d' => 'detail_by_period'])
                ->innerJoin(['s' => 'wb_sales'], 's.srid = d.srid')
                ->where(['d.ppvz_office_name' => $source])
                ->andWhere(['not', ['s.regionName' => null]])
                ->one();

            if ($wbData) {
                Yii::$app->db->createCommand()->update('dadata_address_cache', [
                    'federal_district' => $wbData['oblastOkrugName'],
                    'region'           => $wbData['regionName'],
                    'is_error'         => 2, // Статус: Восстановлено из продаж
                ], ['id' => $entry['id']])->execute();

                $this->stdout("FIXED (" . $wbData['regionName'] . ")\n", Console::FG_GREEN);
                $repairedCount++;
            } else {
                $this->stdout("not found in sales\n", Console::FG_GREY);
            }
        }

        $this->stdout("\nВосстановление завершено. Обновлено записей: $repairedCount\n", Console::BOLD);
        return ExitCode::OK;
    }

/**
 * Нормализация федеральных округов
 * Запуск: php yii address/normalize
 */

/**
 * Нормализация федеральных округов с детальным выводом в консоль
 * Запуск: php yii address/normalize
 */
public function actionNormalize()
{
    $db = Yii::$app->db;

    $this->stdout("ЭТАП 0: Маппинг регионов...\n", Console::FG_GREY);
        
        $regionFixes = [
//            'Ненецкий автономный округ' => 'Ямало-Ненецкий автономный округ', // Если нужно объединить логику
            'Еврейская Аобл'            => 'Еврейская автономная область',
            'Еврейская АО'              => 'Еврейская автономная область',
            'Еврейская'                 => 'Еврейская автономная область',
            'Саха (Якутия)'             => 'Республика Саха (Якутия)',

            'г Москва'                      => 'Москва',
            'Респ Башкортостан'             => 'Республика Башкортостан',
            'Респ Татарстан'                => 'Республика Татарстан',
            'Северная Осетия - Алания'      => 'Республика Северная Осетия — Алания',
            'Респ Северная Осетия - Алания' => 'Республика Северная Осетия — Алания',
            'Респ Мордовия'                 => 'Республика Мордовия',
//            'Чувашская Республика -'    => 'Чувашская Республика',
//            'Чувашия'                   => 'Чувающая Республика',
            'Ханты-Мансийский Автономный округ - Югра' => 'Ханты-Мансийский автономный округ',
            'Кемеровская область - Кузбасс'            => 'Кемеровская область',
        ];
/*
    foreach ($regionFixes as $old => $new) {
            $db->createCommand()->update('dadata_address_cache', ['region' => $new], ['region' => $old])->execute();
        }
*/

    $totalUpdated = 0;

    foreach ($regionFixes as $old => $new) {
        // Выполняем обновление и получаем количество затронутых строк
        $count = $db->createCommand()
            ->update('dadata_address_cache', ['region' => $new], ['region' => $old])
            ->execute();
        
        if ($count > 0) {
            // Вывод строки: Название старое -> Название новое (количество)
            // FG_GREEN сделает текст зеленым
            $this->stdout("Исправлено: ");
            $this->stdout("'$old'", Console::FG_YELLOW);
            $this->stdout(" -> ");
            $this->stdout("'$new'", Console::FG_GREEN);
            $this->stdout(" ($count шт.)\n");
            
            $totalUpdated += $count;
        }
    }

    $this->stdout("\n---------------------------------\n", Console::BOLD);
    $this->stdout("Всего записей обновлено: $totalUpdated\n", Console::FG_CYAN, Console::BOLD);

    // --- ЭТАП 1: Заполнение NULL по маппингу Регион -> Округ ---
    $this->stdout(">>> ЭТАП 1: Заполнение пустых округов по регионам\n", Console::FG_CYAN, Console::BOLD);
    
    // Вытягиваем уникальные пары, где округ есть
    $mapping = (new Query())
        ->select(['region', 'federal_district'])
        ->from('dadata_address_cache')
        ->where(['country' => 'Россия'])
        ->andWhere(['not', ['region' => null]])
        ->andWhere(['not', ['federal_district' => null]])
        ->andWhere(['!=', 'federal_district', ''])
        ->distinct()
        ->all();

    $totalMapping = count($mapping);
    $this->stdout("Найдено уникальных связей регион-округ: $totalMapping\n", Console::FG_YELLOW);

    $updatedCount = 0;
    foreach ($mapping as $index => $row) {
        $region = $row['region'];
        $district = $row['federal_district'];
        $num = $index + 1;

        // Выполняем обновление
        $count = $db->createCommand()
            ->update('dadata_address_cache', 
                ['federal_district' => $district], 
                [
                    'country' => 'Россия', 
                    'region' => $region, 
                    'federal_district' => null
                ]
            )->execute();

        if ($count > 0) {
            $this->stdout("[$num/$totalMapping] ", Console::FG_GREY);
            $this->stdout(str_pad($region, 30, " "), Console::FG_GREY);
            $this->stdout(" -> заполнено записей: ", Console::FG_GREY);
            $this->stdout("$count\n", Console::FG_GREEN);
            $updatedCount += $count;
        }
    }
    $this->stdout("Итого на первом этапе обновлено: $updatedCount\n\n", Console::FG_GREEN, Console::BOLD);


    // --- ЭТАП 2: Стандартизация названий округов ---
    $this->stdout(">>> ЭТАП 2: Приведение к полному названию '... федеральный округ'\n", Console::FG_CYAN, Console::BOLD);
    
    $districts = [
        'Дальневосточный',
        'Приволжский',
        'Северо-Западный',
        'Северо-Кавказский',
        'Сибирский',
        'Уральский',
        'Центральный',
        'Южный'
    ];

    $standardizedCount = 0;
    foreach ($districts as $name) {
        $fullName = $name . " федеральный округ";
        $this->stdout("Обработка: ", Console::FG_GREY);
        $this->stdout(str_pad($name, 20, " "), Console::FG_GREY);

        // Обновляем те, что равны короткому имени или начинаются с него (но не равны полному)
        $count = $db->createCommand("
            UPDATE dadata_address_cache 
            SET federal_district = :fullName 
            WHERE country = 'Россия' 
              AND federal_district IS NOT NULL
              AND federal_district != :fullName
              AND (federal_district = :shortName OR federal_district LIKE :shortNameLike)
        ", [
            ':fullName' => $fullName,
            ':shortName' => $name,
            ':shortNameLike' => $name . ' %'
        ])->execute();
        
        if ($count > 0) {
            $this->stdout(" -> исправлено: ", Console::FG_GREY);
            $this->stdout("$count\n", Console::FG_GREEN);
        } else {
            $this->stdout(" -> без изменений\n", Console::FG_GREY);
        }
        
        $standardizedCount += $count;
    }

    $this->stdout("\nВСЁ ГОТОВО!\n", Console::FG_GREEN, Console::BOLD);
    $this->stdout("Всего исправлено: " . ($updatedCount + $standardizedCount) . "\n");


    $this->stdout("\n>>> ЭТАП 3: Нормализация городов федерального значения\n", Console::FG_CYAN, Console::BOLD);

    $federalCities = [
        'Москва'          => 'Центральный федеральный округ',
        'Санкт-Петербург' => 'Северо-Западный федеральный округ',
        'Севастополь'     => 'Южный федеральный округ',
    ];

// Москва
        $db->createCommand("UPDATE dadata_address_cache SET 
            region = 'Москва', 
            city = 'Москва',
            city_type = 'г',
            city_type_full = 'город'
            WHERE country = 'Россия' 
            AND (region LIKE 'Центральный%' OR region = 'ЦФО')
            AND (city = 'г Москва')")->execute();

// Санкт-Петербург
        $db->createCommand("UPDATE dadata_address_cache SET 
            region = 'Санкт-Петербург', 
            city = 'Санкт-Петербург',
            city_type = 'г',
            city_type_full = 'город'
            WHERE country = 'Россия' 
            AND (region LIKE 'Северо-Западный%' OR region = 'СЗФО')
            AND (city = 'г Санкт-Петербург')")->execute();

    foreach ($federalCities as $name => $district) {
        $this->stdout("Обработка: ", Console::FG_GREY);
        $this->stdout(str_pad($name, 20, " "));

        $count = $db->createCommand()->update('dadata_address_cache', [
            'city'             => $name,
            'city_type'        => 'г',
            'city_type_full'   => 'город',
            'federal_district' => $district
        ], [
            'country' => 'Россия',
            'region'  => $name,
            'city'    => null // Обновляем только там, где город пуст
        ])->execute();

        if ($count > 0) {
            $this->stdout(" -> исправлено: ", Console::FG_GREY);
            $this->stdout("$count\n", Console::FG_GREEN);
        } else {
            $this->stdout(" -> ок\n", Console::FG_GREY);
        }
    }

    // --- ЭТАП 4: Простановка типов городов (г / город) ---
    $this->stdout("\n>>> ЭТАП 4: Заполнение city_type для городов\n", Console::FG_CYAN, Console::BOLD);

    // Ищем записи, где город есть, а типа города нет
    $count = $db->createCommand("
        UPDATE dadata_address_cache 
        SET city_type = 'г', 
            city_type_full = 'город' 
        WHERE country = 'Россия' 
          AND city IS NOT NULL 
          AND city != '' 
          AND (city_type IS NULL OR city_type = '')
    ")->execute();

    if ($count > 0) {
        $this->stdout("Для городов проставлен тип 'г.': ", Console::FG_GREY);
        $this->stdout("$count записей\n", Console::FG_GREEN);
    } else {
        $this->stdout("Все типы городов уже заполнены.\n", Console::FG_GREY);
    }


// --- ЭТАП 5: Синхронизация названий регионов с эталоном из wb_sales ---

$this->stdout("\n>>> ЭТАП 5: Упрощение названий регионов (под стандарт wb_sales)\n", Console::FG_CYAN, Console::BOLD);

    // 1. Получаем уникальные "простые" названия из вашей таблицы продаж
    $wbRegions = (new Query())
        ->select(['regionName'])
        ->from('wb_sales')
        ->where(['not', ['regionName' => null]])
        ->andWhere(['not in', 'regionName', ['область Абай', 'Абайская область']])
        ->distinct()
        ->column();

    $this->stdout("Загружено целевых названий из WB: " . count($wbRegions) . "\n", Console::FG_YELLOW);

    $syncCount = 0;

    foreach ($wbRegions as $targetName) {
        // Создаем "базовое" слово для поиска (например, из "Кемеровская область" берем "Кемеровская")
        // Убираем общие слова, чтобы осталось только уникальное ядро
        $coreName = trim(str_replace(['область', 'край', 'Республика', 'республика', 'автономный округ', 'автономная область', 'АО'], '', $targetName));
        
        if (mb_strlen($coreName) < 3) continue; // Защита от слишком коротких слов

        $count = $db->createCommand("
            UPDATE dadata_address_cache 
            SET region = :target 
            WHERE country = 'Россия' 
              AND region != :target
              -- ^ это начало строки, $ это конец строки. 
              -- Таким образом 'Омская' никогда не найдет 'Томская'
              AND region REGEXP :pattern
        ", [
            ':target'  => $targetName, // "Омская область"
            ':pattern' => '^' . $coreName . '$' // Конструируем прямо здесь, чтобы не было Undefined variable
        ])->execute();

        if ($count > 0) {
            $this->stdout("Синхронизация: ", Console::FG_GREY);
            $this->stdout(str_pad($targetName, 35, " "), Console::FG_GREEN);
            $this->stdout(" <- исправлено вариаций: $count\n", Console::FG_GREY);
            $syncCount += $count;
        }
    }

    // 2. Точечные "хирургические" правки (если автоматика где-то не дотянула)
    $manualFixes = [
        // Если в WB "Ханты-Мансийский автономный округ", а в кеше "Ханты-Мансийский Автономный округ - Югра"
        'Ханты-Мансийский Автономный округ - Югра' => 'Ханты-Мансийский автономный округ',
        'Кемеровская область — Кузбасс'            => 'Кемеровская область',
        'Чувашия'                                  => 'Чувашская Республика',
        'Татарстан'                                => 'Республика Татарстан',

    ];

    foreach ($manualFixes as $long => $simple) {
        // Проверяем, есть ли вообще такое простое название в WB, прежде чем менять
        if (in_array($simple, $wbRegions)) {
            $count = $db->createCommand()->update('dadata_address_cache', 
                ['region' => $simple], 
                ['region' => $long, 'country' => 'Россия']
            )->execute();
            
            if ($count > 0) {
                $this->stdout("Ручная фиксация: ", Console::FG_GREY);
                $this->stdout(str_pad($simple, 35, " ") . " <- убрано '$long'\n", Console::FG_YELLOW);
                $syncCount += $count;
            }
        }
    }

    $this->stdout("\nУпрощение завершено. Всего приведено к стандарту WB: $syncCount\n", Console::BOLD);

    $this->stdout("\n=== ВСЯ НОРМАЛИЗАЦИЯ ЗАВЕРШЕНА ===\n", Console::FG_GREEN, Console::BOLD);
    return ExitCode::OK;
}


/**
     * Ручная корректировка зарубежных адресов (СНГ) по паттернам
     * Запуск: php yii address/fix-foreign
     */
    public function actionFixForeign()
    {
        $db = Yii::$app->db;
        $this->stdout(">>> Начало ручной корректировки зарубежных адресов...\n", Console::FG_CYAN, Console::BOLD);

        // --- КОНФИГУРАЦИЯ МППИНГА ---
        // Структура: 'Страна' => [ 'Область' => ['Город1', 'Город2', ... ] ]

        $capitals = [
            'Беларусь'    => ['Минск'],
            'Казахстан'   => ['Астана', 'Алматы', 'Шымкент'],
            'Кыргызстан'  => ['Бишкек', 'Ош'],
            'Узбекистан'  => ['Ташкент'],
            'Армения'     => ['Ереван'],
            'Таджикистан' => ['Душанбе'],
            'Азербайджан' => ['Баку'],
            'Грузия'      => ['Тбилиси', 'Батуми'],
        ];

        $capTotal = 0;
        foreach ($capitals as $country => $cities) {
            $countrySearch = ($country === 'Кыргызстан') ? ['Кыргызстан', 'Киргизия'] : [$country];

            foreach ($cities as $city) {
                // Варианты: "Астана Астана %", "город республиканского значения Астана Астана %"
                $patterns = [
                    $city . ' ' . $city . ' %',
                    'город республиканского значения ' . $city . ' ' . $city . ' %',
                    'город республиканского значения ' . $city . ', ' . $city . ' %',
                    'город республиканского значения ' . $city . ', ' . $city . ',%',
                    'город республиканского подчинения ' . $city . ' ' . $city . ' %', 
                    'город республиканского подчинения ' . $city . ', ' . $city . ' %', 
                    'город республиканского подчинения ' . $city . ', ' . $city . ',%', 
                    'муниципалитет ' . $city . ' ' . $city . ' %', 
                    'город ' . $city . ' ' . $city . ' %',
                    'г. ' . $city . ' ' . $city . ' %',
                    'г. ' . $city . ', %',
                    'г. ' . $city . ' %',
                    'г ' . $city . ', %',
                    'г ' . $city . ' %',
                    $city . ', %', 
                    $city . ' %' 
                ];

                foreach ($patterns as $p) { // $this->stdout("  [*] $p \n", Console::FG_GREEN);
                    $count = $db->createCommand()->update('dadata_address_cache', [
                        'region'         => $city,
                        'city'           => $city,
                        'city_type'      => 'г',
                        'city_type_full' => 'город',
                        'country'        => $country,
                        'is_error'       => 7
                    ], [
                        'and',
                        ['country' => $countrySearch],
                        ['like', 'source_text', $p, false],
                        ['<', 'is_error', 7]
//                        ['is_error' => 2]

                    ])->execute();
                    $capTotal += $count;
                }
            }
        }
        if ($capTotal > 0) $this->stdout("  [*] Столицы и города респ. значения: $capTotal\n", Console::FG_GREEN);

        $map = [
            'Беларусь' => [
                'Минск' => [ 'Минск' ],
                'Брестская область' => [
                    'Брест', 'Барановичи', 'Пинск', 'Кобрин', 'Береза', 'Лунинец', 
                    'Ивацевичи', 'Пружаны', 'Иваново', 'Дрогичин', 'Ганцевичи', 'Жабинка', 'Столин'
                ],
                'Гомельская область' => [
                    'Гомель', 'Мозырь', 'Жлобин', 'Светлогорск', 'Речица', 'Калинковичи', 
                    'Добруш', 'Рогачёв', 'Хойники', 'Петриков', 'Ельск', 'Буда-Кошелево'
                ],
                'Минская область' => [
                    'Борисов', 'Солигорск', 'Молодечно', 'Жодино', 'Слуцк', 'Заславль', 
                    'Березино', 'Дзержинск', 'Смолевичи', 'Узда', 'Фаниполь', 'Воложин', 
                    'Копыль', 'Вилейка', 'Марьина Горка', 'Столбцы', 'Несвиж', 'Любань', 'Старые Дороги', 'Клецк',
                    'Смолевичи'
                ],
                'Гродненская область' => [
                    'Гродно', 'Лида', 'Слоним', 'Волковыск', 'Сморгонь', 'Дятлово', 
                    'Новогрудок', 'Ошмяны', 'Щучин', 'Мосты', 'Скидель', 'Островец', 'Сморгонь'
                ],
                'Могилевская область' => [
                    'Могилёв', 'Бобруйск', 'Горки', 'Осиповичи', 'Кировск', 'Костюковичи', 
                    'Кричев', 'Славгород', 'Шклов', 'Быхов', 'Климовичи', 'Мстиславль', 'Чаусы', 'Белыничи'
                ],
                'Витебская область' => [
                    'Витебск', 'Орша', 'Полоцк', 'Новополоцк', 'Лепель', 'Поставы', 
                    'Глубокое', 'Городок', 'Бешенковичи', 'Толочин', 'Браслав'
                ],
            ],
            'Казахстан' => [
                'Абайская область' => ['Семей', 'Аягоз'],
                'область Абай' => ['Семей', 'Аягоз'],

                'Жетысуская область' => ['Талдыкорган', 'Текели', 'Жаркент'],
                'область Жетысу' => ['Талдыкорган', 'Текели', 'Жаркент'],

                'Акмолинская область' => ['Кокшетау', 'Степногорск', 'Щучинск'],
                'Актюбинская область' => ['Актобе', 'Хромтау', 'Кандыагаш'],
                'Алматинская область' => ['Конаев', 'Каскелен', 'Талгар', 'Есик'],
                'Атырауская область' => ['Атырау', 'Кульсары'],
                'Западно-Казахстанская область' => ['Уральск', 'Аксай'],
                'Жамбылская область' => ['Тараз', 'Шу', 'Каратау'],


                'Карагандинская область' => ['Караганда', 'Темиртау', 'Балхаш', 'Сарань'],
                'Костанайская область' => ['Костанай', 'Рудный', 'Аркалык', 'Житикара'],
                'Кызылординская область' => ['Кызылорда', 'Байконур'],
                'Мангистауская область' => ['Актау', 'Жанаозен'],
                'Павлодарская область' => ['Павлодар', 'Экибастуз', 'Аксу'],
                'Северо-Казахстанская область' => ['Петропавловск'],
                'Туркестанская область' => ['Туркестан', 'Кентау', 'Арыс', 'Сарыагаш'],
                'Улытауская область' => ['Жезказган', 'Сатпаев', 'Каражал'],
                'область Улытау' => ['Жезказган', 'Сатпаев', 'Каражал'],

                'Восточно-Казахстанская область' => ['Усть-Каменогорск', 'Риддер', 'Алтай'],
            ],
            'Кыргызстан' => [
                'Баткенская область' => ['Баткен', 'Кызыл-Кия'],
                'Джалал-Абадская область' => ['Джалал-Абад', 'Кара-Куль', 'Таш-Кумыр'],
                'Иссык-Кульская область' => ['Каракол', 'Балыкчы'],
                'Нарынская область' => ['Нарын'],
                'Ошская область' => ['Кара-Суу', 'Узген'],
                'Таласская область' => ['Талас'],
                'Чуйская область' => ['Токмок', 'Кант', 'Кара-Балта'],
            ],
            'Узбекистан' => [
                'Андижанская область' => ['Андижан', 'Асака'],
                'Бухарская область' => ['Бухара', 'Гиждуван'],
                'Джизакская область' => ['Джизак'],
                'Кашкадарьинская область' => ['Карши', 'Шахрисабз'],
                'Навоийская область' => ['Навои', 'Зарафшан'],
                'Наманганская область' => ['Наманган', 'Чуст'],
                'Самаркандская область' => ['Самарканд', 'Каттакурган'],
                'Сурхандарьинская область' => ['Термез', 'Денау'],
                'Сырдарьская область' => ['Гулистан', 'Янгиер'],
                'Ташкентская область' => ['Ангрен', 'Алмалык', 'Чирчик', 'Бекабад'],
                'Ферганская область' => ['Фергана', 'Коканд', 'Маргилан'],
                'Хорезмская область' => ['Ургенч', 'Хива'],
                'Республика Каракалпакстан' => ['Нукус', 'Ходжейли'],
            ],
            // Для Армении, Таджикистана и Грузии обычно приходят города напрямую, 
            // но можно добавить области (Марзы в Армении)
            'Армения' => [
                'Ереван' => ['Ереван'],
                'Арагацотнская область' => ['Аштарак', 'Апаран', 'Талин'], // Добавили Талин
                'Ширакская область' => ['Гюмри', 'Артик'],
                'Лорийская область' => ['Ванадзор', 'Спитак', 'Алаверди'],
                'Сюникская область' => ['Капан', 'Горис', 'Сисиан'],
                'Тавушская область' => ['Иджеван', 'Дилижан'],
                'Араратская область' => ['Арташат', 'Арарат'],
                'Армавирская область' => ['Армавир', 'Вагаршапат'],
                'Котайкская область' => ['Раздан', 'Абовян', 'Чаренцаван'],
                'Гегаркуникская область' => ['Гавар', 'Севан'],
                'Вайоцдзорская область' => ['Ехегнадзор', 'Вайк'],
                'Гехаркуникская область' => [],
            ],
        ];

        $totalFixed = 0;
        foreach ($map as $country => $regions) {
            $countrySearch = ($country === 'Кыргызстан') ? ['Кыргызстан', 'Киргизия'] : [$country];

            foreach ($regions as $regionName => $cities) {
                // Префикс для поиска (убираем " область" для более гибкого LIKE)
//                $cleanRegion = str_replace([' область', ' область'], '', $regionName);

                foreach ($cities as $cityName) {
                    $pattern = $regionName . ' ' . $cityName . ' %';
                    
                    $count = $db->createCommand()->update('dadata_address_cache', [
                        'region'         => $regionName,
                        'city'           => $cityName,
                        'city_type'      => 'г',
                        'city_type_full' => 'город',
                        'country'        => $country,
                        'is_error'       => 7
                    ], [
                        'and',
                        ['country' => $countrySearch],
                        ['like', 'source_text', $pattern, false],
                        ['<', 'is_error', 7]
//                        ['is_error' => 2]
                    ])->execute();

                    if ($count > 0) {
                        $this->stdout("  [+] $country | $regionName -> $cityName ($count)\n", Console::FG_GREEN);
                        $totalFixed += $count;
                    }

                    $pattern = 'г. '.$cityName . ', %';
                    
                    $count = $db->createCommand()->update('dadata_address_cache', [
                        'region'         => $regionName,
                        'city'           => $cityName,
                        'city_type'      => 'г',
                        'city_type_full' => 'город',
                        'country'        => $country,
                        'is_error'       => 9
                    ], [
                        'and',
                        ['country' => $countrySearch],
                        ['like', 'source_text', $pattern, false],
                        ['<', 'is_error', 9]
//                        ['is_error' => 2]
                    ])->execute();

                    if ($count > 0) {
                        $this->stdout("  [+] $country | $regionName -> $cityName ($count)\n", Console::FG_GREEN);
                        $totalFixed += $count;
                    }

                    $pattern = 'г '.$cityName . ', %';
                    
                    $count = $db->createCommand()->update('dadata_address_cache', [
                        'region'         => $regionName,
                        'city'           => $cityName,
                        'city_type'      => 'г',
                        'city_type_full' => 'город',
                        'country'        => $country,
                        'is_error'       => 9
                    ], [
                        'and',
                        ['country' => $countrySearch],
                        ['like', 'source_text', $pattern, false],
                        ['<', 'is_error', 9]
//                        ['is_error' => 2]
                    ])->execute();

                    if ($count > 0) {
                        $this->stdout("  [+] $country | $regionName -> $cityName ($count)\n", Console::FG_GREEN);
                        $totalFixed += $count;
                    }

                    $pattern = $cityName . ', %';
                    
                    $count = $db->createCommand()->update('dadata_address_cache', [
                        'region'         => $regionName,
                        'city'           => $cityName,
                        'city_type'      => 'г',
                        'city_type_full' => 'город',
                        'country'        => $country,
                        'is_error'       => 9
                    ], [
                        'and',
                        ['country' => $countrySearch],
                        ['like', 'source_text', $pattern, false],
                        ['<', 'is_error', 9]
//                        ['is_error' => 2]
                    ])->execute();

                    if ($count > 0) {
                        $this->stdout("  [+] $country | $regionName -> $cityName ($count)\n", Console::FG_GREEN);
                        $totalFixed += $count;
                    }


                }


                // Фоллбэк: если город не в списке, но область совпала
                $db->createCommand()->update('dadata_address_cache', [
                    'region'   => $regionName,
                    'is_error' => 9
                ], [
                    'and',
                    ['country' => $country],
//                    ['like', 'source_text', '%' . $cleanRegion . '%', false],
                    ['like', 'source_text', $regionName . ' %', false],
//                    ['region' => null],
                    ['<', 'is_error', 9]
//                        ['is_error' => 2]
                ])->execute();
            }
        }

        $db->createCommand()->update('dadata_address_cache', 
            ['region' => 'Жетысуская область'], 
            ['region' => 'область Жетысу']
        )->execute();

        $db->createCommand()->update('dadata_address_cache', 
            ['region' => 'Жетысуская область'], 
            ['region' => 'Жетысуская']
        )->execute();


        $db->createCommand()->update('dadata_address_cache', 
            ['region' => 'Абайская область'], 
            ['region' => 'область Абай']
        )->execute();

        $db->createCommand()->update('dadata_address_cache', 
            ['region' => 'Абайская область'], 
            ['region' => 'Абайская']
        )->execute();



$this->stdout("\n>>> ЭТАП 5: Упрощение названий регионов (под стандарт wb_sales)\n", Console::FG_CYAN, Console::BOLD);

    // 1. Получаем уникальные "простые" названия из вашей таблицы продаж
    $wbRegions = (new Query())
        ->select(['regionName'])
        ->from('wb_sales')
        ->where(['not', ['regionName' => null]])
        ->andWhere(['not in', 'regionName', ['область Абай', 'Абайская область']])
        ->andWhere(['!=', 'countryName', 'Россия']) 
        ->distinct()
        ->column();

    $this->stdout("Загружено целевых названий из WB: " . count($wbRegions) . "\n", Console::FG_YELLOW);

    $syncCount = 0;

    foreach ($wbRegions as $targetName) {
        // Создаем "базовое" слово для поиска (например, из "Кемеровская область" берем "Кемеровская")
        // Убираем общие слова, чтобы осталось только уникальное ядро
        $coreName = trim(str_replace(['область', 'край', 'Республика', 'республика', 'автономный округ', 'автономная область', 'АО'], '', $targetName));
        
        if (mb_strlen($coreName) < 3) continue; // Защита от слишком коротких слов

        $count = $db->createCommand("
            UPDATE dadata_address_cache 
            SET region = :target 
            WHERE country != 'Россия' 
              AND region != :target
              AND is_error = 7
              -- ^ это начало строки, $ это конец строки. 
              -- Таким образом 'Омская' никогда не найдет 'Томская'
              AND region REGEXP :pattern
        ", [
            ':target'  => $targetName, // "Омская область"
            ':pattern' => '^' . $coreName . '$' // Конструируем прямо здесь, чтобы не было Undefined variable
        ])->execute();

        if ($count > 0) {
            $this->stdout("Синхронизация: ", Console::FG_GREY);
            $this->stdout(str_pad($targetName, 35, " "), Console::FG_GREEN);
            $this->stdout(" <- исправлено вариаций: $count\n", Console::FG_GREY);
            $syncCount += $count;
        }
    }





        $this->stdout("\nИТОГО исправлено зарубежных адресов: " . ($totalFixed + $capTotal) . "\n", Console::BOLD);
    }


/**
     * Комплексная подготовка географии: 
     * 1. Синхронизация новых адресов из wb_sales (бесплатно)
     * 2. Прямой запрос в Dadata для новых ПВЗ РФ (платно)
     * 3. Нормализация (округа, "Кузбасс/Югра", СНГ)
     * 4. Массовая привязка address_id в detail_by_period
     * * Запуск: php yii address/prepare 500
     */
    public function actionPrepare($limit = 500)
    {
        $db = Yii::$app->db;
        $apiKey = Yii::$app->params['dadataApiKey'] ?? null;

        // --- 1. СБОР НОВЫХ АДРЕСОВ ---
        $this->stdout(">>> Шаг 1: Поиск новых уникальных адресов в деталях...\n", Console::FG_CYAN);
        
        // Выбираем ПВЗ из деталей, которых еще нет в нашем кеше по MD5-хэшу
        $newAddresses = (new Query())
            ->select(['ppvz_office_name', 'site_country'])
            ->distinct()
            ->from('detail_by_period')
            ->where(['not', ['ppvz_office_name' => null]])
            ->andWhere(['not', ['ppvz_office_name' => '']])
            ->andWhere(['not in', 'MD5(ppvz_office_name)', (new Query())->select(['hash'])->from('dadata_address_cache')])
            ->all();

        if (empty($newAddresses)) {
            $this->stdout("Новых адресов для кэширования не обнаружено.\n", Console::FG_GREEN);
        } else {
            $totalNew = count($newAddresses);
            $this->stdout("Найдено новых уникальных адресов: $totalNew\n", Console::FG_YELLOW);
            
            foreach ($newAddresses as $index => $addr) {
                $source = $addr['ppvz_office_name'];
                $country = $addr['site_country'];
                $hash = md5($source);
                $num = $index + 1;

                $this->stdout("[$num/$totalNew] " . mb_strimwidth($source, 0, 40, "...") . " -> ");

                // А. Пытаемся найти этот адрес в wb_sales (бесплатный способ через srid)
                $wbData = (new Query())
                    ->select(['s.regionName', 's.oblastOkrugName'])
                    ->from(['d' => 'detail_by_period'])
                    ->innerJoin(['s' => 'wb_sales'], 's.srid = d.srid')
                    ->where(['d.ppvz_office_name' => $source])
                    ->andWhere(['not', ['s.regionName' => null]])
                    ->one();

                if ($wbData) {
                    // Если нашли в wb_sales - сохраняем с пометкой "восстановлено" (is_error = 2)
                    $this->saveToCache([
                        'source_text'      => $source,
                        'country'          => $country,
                        'hash'             => $hash,
                        'region'           => $wbData['regionName'],
                        'federal_district' => $wbData['oblastOkrugName'],
                        'is_error'         => 2
                    ]);
                    $this->stdout("OK (из wb_sales)\n", Console::FG_GREEN);
                } 
                // Б. Если не Россия и нет в продажах — сохраняем для ручной обработки, не мучая Dadata
                elseif ($country !== 'Россия') {
                    $this->saveToCache([
                        'source_text' => $source,
                        'country'     => $country,
                        'hash'        => $hash,
                        'is_error'    => 1 // Помечаем как требующее внимания
                    ]);
                    $this->stdout("SKIP (Зарубежье: $country)\n", Console::FG_YELLOW);
                } 
                // В. Если это Россия и в продажах пусто — идем в Dadata (платно)
                else {
                    if (!$apiKey) {
                        $this->stdout("SKIP (нет API ключа)\n", Console::FG_RED);
                        continue;
                    }

                    $this->stdout("Запрос Dadata... ", Console::FG_GREY);
                    
                    try {
                        $rawResponse = $this->queryDadataSuggest($source, $apiKey);
                        $responseArray = Json::decode($rawResponse);

                        // Проверка на лимиты баланса
                        if (isset($responseArray['reason']) && $responseArray['reason'] === 'Forbidden') {
                            $this->stdout("STOP: Лимит Dadata исчерпан!\n", Console::FG_RED);
                            break; // Прерываем цикл новых адресов
                        }

                        $suggestion = $responseArray['suggestions'][0] ?? null;
                        
                        if ($suggestion) {
                            $data = $suggestion['data'];
                            $this->saveToCache([
                                'source_text'      => $source,
                                'hash'             => $hash,
                                'country'          => $data['country'] ?? $country,
                                'federal_district' => $data['federal_district'],
                                'region'           => $data['region_with_type'] ?? $data['region'],
                                'city'             => $data['city'] ?? $data['settlement'],
                                'city_type'        => $data['city_type'] ?? $data['settlement_type'],
                                'city_type_full'   => $data['city_type_full'] ?? $data['settlement_type_full'],
                                'is_error'         => 0
                            ]);
                            $this->stdout("OK (API)\n", Console::FG_GREEN);
                        } else {
                            // Если и Dadata не знает адрес
                            $this->saveToCache([
                                'source_text' => $source,
                                'country'     => $country,
                                'hash'        => $hash,
                                'is_error'    => 1
                            ]);
                            $this->stdout("NOT FOUND\n", Console::FG_YELLOW);
                        }
                    } catch (\Exception $e) {
                        $this->stdout("ERROR: " . $e->getMessage() . "\n", Console::FG_RED);
                    }
                } // конец блока Dadata
            } // конец цикла foreach
        }

        // --- 2. ОБРАБОТКА ОСТАТКОВ (is_error = 1) ---
        // Дополнительный проход по записям, которые были созданы ранее как ошибочные
//        $this->stdout("\n>>> Шаг 2: Дополнительная синхронизация (Sync)...\n", Console::FG_CYAN);
//        $this->actionSync($limit);

        // 2. Чистим старые ошибки и нормализуем названия (Этап 0 внутри)
        $this->actionNormalize();

        // 3. Исправляем Россию (Города, Области, Округа)
        $this->actionFixRussia();

        // 4. Исправляем СНГ (Кыргызстан, Беларусь и др.)
        $this->actionFixForeign();

        // 5. Проставляем ID в таблицу продаж
        $this->actionLinkAddresses();
        
//        $this->stdout("Связано записей в деталях: $count\n", Console::FG_GREEN, Console::BOLD);
        $this->stdout("\nПОДГОТОВКА ГЕОГРАФИИ ЗАВЕРШЕНА!\n", Console::FG_GREEN, Console::BOLD);
    }


    protected function getInternalMapping()
    {
        return [
            'federalDistricts' => [
                'Москва' => 'Центральный',
                'Московская область' => 'Центральный',
                'Санкт-Петербург' => 'Северо-Западный',
                'Ленинградская область' => 'Северо-Западный',
                // ... (скопируйте сюда весь массив $federalDistricts из вашей функции actionFixRussia)
            ],
            'regions' => [
                'Москва' => ['Москва'],
                'Санкт-Петербург' => ['Санкт-Петербург'],
                'Севастополь' => ['Севастополь'],
                // --- ЦЕНТРАЛЬНЫЙ ФО ---
                'Белгородская область' => ['Белгород', 'Старый Оскол', 'Губкин', 'Шебекино', 'Алексеевка', 'Валуйки'],
                'Брянская область' => ['Брянск', 'Клинцы', 'Новозыбков', 'Дятьково', 'Унеча'],
                'Владимирская область' => ['Владимир', 'Ковров', 'Муром', 'Александров', 'Гусь-Хрустальный', 'Кольчугино', 'Вязники', 'Киржач'],
                'Воронежская область' => ['Воронеж', 'Борисоглебск', 'Россошь', 'Лиски', 'Острогожск', 'Нововоронеж', 'Семилуки', 'Бутурлиновка'],
                'Ивановская область' => ['Иваново', 'Кинешма', 'Шуя', 'Вичуга', 'Фурманов', 'Тейково'],
                'Калужская область' => ['Калуга', 'Обнинск', 'Людиново', 'Киров', 'Малоярославец', 'Балабаново', 'Таруса'],
                'Костромская область' => ['Кострома', 'Шарья', 'Нерехта', 'Буй'],
                'Курская область' => ['Курск', 'Железногорск', 'Курчатов', 'Льгов'],
                'Липецкая область' => ['Липецк', 'Елец', 'Грязи', 'Данков'],
                'Московская область' => [
                    'Балашиха', 'Подольск', 'Химки', 'Мытищи', 'Королев', 'Люберцы', 'Красногорск', 'Электросталь', 'Коломна', 'Одинцово', 
                    'Домодедово', 'Серпухов', 'Щелково', 'Орехово-Зуево', 'Раменское', 'Долгопрудный', 'Пушкино', 'Реутов', 'Лобня', 'Ивантеевка', 
                    'Видное', 'Ступино', 'Павловский Посад', 'Дзержинский', 'Солнечногорск', 'Котельники', 'Нахабино'
                ],
                'Орловская область' => ['Орел', 'Ливны', 'Мценск'],
                'Рязанская область' => ['Рязань', 'Касимов', 'Скопин', 'Сасово'],
                'Смоленская область' => ['Смоленск', 'Вязьма', 'Рославль', 'Ярцево', 'Сафоново'],
                'Тамбовская область' => ['Тамбов', 'Мичуринск', 'Рассказово', 'Моршанск'],
                'Тверская область' => ['Тверь', 'Ржев', 'Вышний Волочек', 'Кимры', 'Торжок', 'Конаково', 'Удомля'],
                'Тульская область' => ['Тула', 'Новомосковск', 'Донской', 'Алексин', 'Щекино', 'Узловая', 'Ефремов'],
                'Ярославская область' => ['Ярославль', 'Рыбинск', 'Тутаев', 'Переславль-Залесский', 'Углич', 'Ростов'],

                // --- СЕВЕРО-ЗАПАДНЫЙ ФО ---
                'Архангельская область' => ['Архангельск', 'Северодвинск', 'Котлас', 'Новодвинск', 'Коряжма'],
                'Вологодская область' => ['Вологда', 'Череповец', 'Сокол', 'Великий Устюг'],
                'Калининградская область' => ['Калининград', 'Советск', 'Черняховск', 'Балтийск', 'Гусев', 'Светлый'],
                'Республика Карелия' => ['Петрозаводск', 'Кондопога', 'Сегежа', 'Костомукша'],
                'Республика Коми' => ['Сыктывкар', 'Ухта', 'Воркута', 'Печора', 'Усинск'],
                'Ленинградская область' => ['Гатчина', 'Выборг', 'Всеволожск', 'Сосновый Бор', 'Тихвин', 'Кириши', 'Мурино', 'Кудрово', 'Сертолово'],
                'Мурманская область' => ['Мурманск', 'Апатиты', 'Североморск', 'Мончегорск', 'Кандалакша'],
                'Новгородская область' => ['Великий Новгород', 'Боровичи', 'Старая Русса'],
                'Псковская область' => ['Псков', 'Великие Луки'],

                // --- ЮЖНЫЙ И КАВКАЗСКИЙ ФО ---
                'Краснодарский край' => ['Краснодар', 'Сочи', 'Новороссийск', 'Армавир', 'Анапа', 'Геленджик', 'Кропоткин', 'Славянск-на-Кубани', 'Туапсе', 'Лабинск'],
                'Ставропольский край' => ['Ставрополь', 'Пятигорск', 'Кисловодск', 'Невинномысск', 'Ессентуки', 'Михайловск', 'Минеральные Воды'],
                'Ростовская область' => ['Ростов-на-Дону', 'Таганрог', 'Шахты', 'Новочеркасск', 'Волгодонск', 'Батайск', 'Новошахтинск', 'Азов'],
                'Волгоградская область' => ['Волгоград', 'Волжский', 'Камышин', 'Михайловка'],
                'Астраханская область' => ['Астрахань', 'Ахтубинск'],
                'Республика Крым' => ['Симферополь', 'Керчь', 'Евпатория', 'Ялта', 'Феодосия', 'Джанкой'],
                'Республика Дагестан' => ['Махачкала', 'Хасавюрт', 'Дербент', 'Каспийск', 'Буйнакск'],
                'Чеченская Республика' => ['Грозный', 'Шали', 'Урус-Мартан'],
                'Республика Северная Осетия — Алания' => ['Владикавказ'],

                // --- ПРИВОЛЖСКИЙ ФО ---
                'Нижегородская область' => ['Нижний Новгород', 'Дзержинск', 'Арзамас', 'Саров', 'Бор', 'Кстово'],
                'Республика Татарстан' => ['Казань', 'Набережные Челны', 'Нижнекамск', 'Альметьевск', 'Зеленодольск', 'Бугульма'],
                'Республика Башкортостан' => ['Уфа', 'Стерлитамак', 'Салават', 'Нефтекамск', 'Октябрьский', 'Белорецк'],
                'Самарская область' => ['Самара', 'Тольятти', 'Сызрань', 'Новокуйбышевск', 'Чапаевск'],
                'Саратовская область' => ['Саратов', 'Энгельс', 'Балаково', 'Балашов'],
                'Пермский край' => ['Пермь', 'Березники', 'Соликамск', 'Чайковский', 'Кунгур', 'Лысьва'],
                'Удмуртская Республика' => ['Ижевск', 'Сарапул', 'Воткинск', 'Глазов'],
                'Оренбургская область' => ['Оренбург', 'Орск', 'Новотроицк', 'Бузулук'],
                'Пензенская область' => ['Пенза', 'Кузнецк', 'Заречный'],
                'Кировская область' => ['Киров', 'Кирово-Чепецк', 'Вятские Поляны'],
                'Ульяновская область' => ['Ульяновск', 'Димитровград'],
                'Чувашская Республика' => ['Чебоксары', 'Новочебоксарск', 'Канаш'],
                'Республика Мордовия' => ['Саранск', 'Рузаевка'],
                'Республика Марий Эл' => ['Йошкар-Ола', 'Волжск'],

                // --- УРАЛЬСКИЙ ФО ---
                'Свердловская область' => ['Екатеринбург', 'Нижний Тагил', 'Каменск-Уральский', 'Первоуральск', 'Серов', 'Новоуральск', 'Верхняя Пышма'],
                'Челябинская область' => ['Челябинск', 'Магнитогорск', 'Златоуст', 'Миасс', 'Копейск'],
                'Тюменская область' => ['Тюмень', 'Тобольск', 'Ишим'],
                'Ханты-Мансийский автономный округ' => ['Сургут', 'Нижневартовск', 'Нефтеюганск', 'Ханты-Мансийск', 'Когалым', 'Нягань'],
                'Ямало-Ненецкий автономный округ' => ['Новый Уренгой', 'Ноябрьск', 'Салехард', 'Надым'],
                'Курганская область' => ['Курган', 'Шадринск'],

                // --- СИБИРСКИЙ ФО ---
                'Новосибирская область' => ['Новосибирск', 'Бердск', 'Искитим', 'Куйбышев'],
                'Омская область' => ['Омск', 'Тара', 'Исилькуль'],
                'Красноярский край' => ['Красноярск', 'Норильск', 'Ачинск', 'Канск', 'Железногорск'],
                'Иркутская область' => ['Иркутск', 'Братск', 'Ангарск', 'Усть-Илимск', 'Усолье-Сибирское', 'Шелехов'],
                'Кемеровская область' => ['Кемерово', 'Новокузнецк', 'Прокопьевск', 'Междуреченск', 'Ленинск-Кузнецкий'],
                'Алтайский край' => ['Барнаул', 'Бийск', 'Рубцовск', 'Новоалтайск'],
                'Республика Алтай' => ['Горно-Алтайск'],
                'Томская область' => ['Томск', 'Северск', 'Стрежевой'],
                'Республика Хакасия' => ['Абакан', 'Черногорск', 'Саяногорск'],

                // --- ДАЛЬНЕВОСТОЧНЫЙ ФО ---
                'Приморский край' => ['Владивосток', 'Уссурийск', 'Находка', 'Артем'],
                'Хабаровский край' => ['Хабаровск', 'Комсомольск-на-Амуре', 'Амурск'],
                'Республика Бурятия' => ['Улан-Удэ', 'Северобайкальск'],
                'Забайкальский край' => ['Чита', 'Краснокаменск'],
                'Республика Саха (Якутия)' => ['Якутск', 'Нерюнгри', 'Мирный'],
                'Амурская область' => ['Благовещенск', 'Белогорск', 'Свободный'],
                'Сахалинская область' => ['Южно-Сахалинск', 'Холмск', 'Корсаков'],
                'Камчатский край' => ['Петропавловск-Камчатский', 'Елизово'],
                'Еврейская автономная область' => ['Биробиджан'],
            ]
        ];
    }


/**
     * Ручная корректировка российских адресов по паттернам.
     * Расставляет город, если он есть в source_text, но не распознан Dadata.
     */
    public function actionFixRussia()
    {
        $db = Yii::$app->db;
        $this->stdout(">>> Начало корректировки российских адресов...\n", Console::FG_CYAN, Console::BOLD);

        $districts = [
            'Центральный федеральный округ' => ['Белгородская область', 'Брянская область', 'Владимирская область', 'Воронежская область', 'Ивановская область', 'Калужская область', 'Костромская область', 'Курская область', 'Липецкая область', 'Московская область', 'Орловская область', 'Рязанская область', 'Смоленская область', 'Тамбовская область', 'Тверская область', 'Тульская область', 'Ярославская область', 'Москва'],
            'Северо-Западный федеральный округ' => ['Архангельская область', 'Вологодская область', 'Калининградская область', 'Республика Карелия', 'Республика Коми', 'Ленинградская область', 'Мурманская область', 'Новгородская область', 'Псковская область', 'Ненецкий автономный округ', 'Санкт-Петербург'],
            'Южный федеральный округ' => ['Республика Адыгея', 'Астраханская область', 'Волгоградская область', 'Республика Калмыкия', 'Краснодарский край', 'Ростовская область', 'Республика Крым', 'Севастополь'],
            'Северо-Кавказский федеральный округ' => ['Республика Дагестан', 'Республика Ингушетия', 'Кабардино-Балкарская Республика', 'Карачаево-Черкесская Республика', 'Республика Северная Осетия — Алания', 'Ставропольский край', 'Чеченская Республика'],
            'Приволжский федеральный округ' => ['Республика Башкортостан', 'Кировская область', 'Республика Марий Эл', 'Республика Мордовия', 'Нижегородская область', 'Оренбургская область', 'Пензенская область', 'Пермский край', 'Самарская область', 'Саратовская область', 'Республика Татарстан', 'Удмуртская Республика', 'Ульяновская область', 'Чувашская Республика'],
            'Уральский федеральный округ' => ['Курганская область', 'Свердловская область', 'Тюменская область', 'Ханты-Мансийский автономный округ', 'Челябинская область', 'Ямало-Ненецкий автономный округ'],
            'Сибирский федеральный округ' => ['Алтайский край', 'Республика Алтай', 'Иркутская область', 'Кемеровская область', 'Красноярский край', 'Новосибирская область', 'Омская область', 'Томская область', 'Республика Тыва', 'Республика Хакасия'],
            'Дальневосточный федеральный округ' => ['Амурская область', 'Республика Бурятия', 'Еврейская автономная область', 'Забайкальский край', 'Камчатский край', 'Магаданская область', 'Приморский край', 'Республика Саха (Якутия)', 'Сахалинская область', 'Хабаровский край', 'Чукотский автономный округ'],
        ];

        $this->stdout(">>> ЭТАП 1: Заполнение столиц\n", Console::FG_CYAN, Console::BOLD);
        $federalCities = ['Москва', 'Санкт-Петербург', 'Севастополь'];
        $fedTotal = 0;

        foreach ($federalCities as $city) {
            $patterns = [
                $city . ' ' . $city . ' %', // "Москва Москва ..."
                'г. ' . $city . ' ' . $city . ' %',
                'г.' . $city . ' %',
                'г.' . $city . ', %',
                'г. ' . $city . ' %',
                'г. ' . $city . ', %',
                 $city . ', %',
            ];

            foreach ($patterns as $p) {  $this->stdout(">>> $city | $p  \n", Console::FG_CYAN);
                $count = $db->createCommand()->update('dadata_address_cache', [
                    'region'         => $city,
                    'city'           => $city,
                    'city_type'      => 'г',
                    'city_type_full' => 'город',
                    'is_error'       => 8
                ], [
                    'and',
                    ['country' => 'Россия'],
                    ['like', 'source_text', $p, false],
                    ['<', 'is_error', 9]
//                    ['is_error' => 1]
                ])->execute();
                $fedTotal += $count;
            }
        }
        if ($fedTotal > 0) $this->stdout("  [*] Города фед. значения исправлены: $fedTotal\n", Console::FG_GREEN);

        $map = [
            // --- ЦЕНТРАЛЬНЫЙ ФО ---
            'Белгородская область' => ['Белгород', 'Старый Оскол', 'Губкин', 'Шебекино', 'Алексеевка', 'Валуйки'],
            'Брянская область' => ['Брянск', 'Клинцы', 'Новозыбков', 'Дятьково', 'Унеча'],
            'Владимирская область' => ['Владимир', 'Ковров', 'Муром', 'Александров', 'Гусь-Хрустальный', 'Кольчугино', 'Вязники', 'Киржач'],
            'Воронежская область' => ['Воронеж', 'Борисоглебск', 'Россошь', 'Лиски', 'Острогожск', 'Нововоронеж', 'Семилуки', 'Бутурлиновка'],
            'Ивановская область' => ['Иваново', 'Кинешма', 'Шуя', 'Вичуга', 'Фурманов', 'Тейково'],
            'Калужская область' => ['Калуга', 'Обнинск', 'Людиново', 'Киров', 'Малоярославец', 'Балабаново', 'Таруса'],
            'Костромская область' => ['Кострома', 'Шарья', 'Нерехта', 'Буй'],
            'Курская область' => ['Курск', 'Железногорск', 'Курчатов', 'Льгов'],
            'Липецкая область' => ['Липецк', 'Елец', 'Грязи', 'Данков'],
            'Московская область' => [
                'Балашиха', 'Подольск', 'Химки', 'Мытищи', 'Королев', 'Люберцы', 'Красногорск', 'Электросталь', 'Коломна', 'Одинцово', 
                'Домодедово', 'Серпухов', 'Щелково', 'Орехово-Зуево', 'Раменское', 'Долгопрудный', 'Пушкино', 'Реутов', 'Лобня', 'Ивантеевка', 
                'Видное', 'Ступино', 'Павловский Посад', 'Дзержинский', 'Солнечногорск', 'Котельники', 'Нахабино'
            ],
            'Орловская область' => ['Орел', 'Ливны', 'Мценск'],
            'Рязанская область' => ['Рязань', 'Касимов', 'Скопин', 'Сасово'],
            'Смоленская область' => ['Смоленск', 'Вязьма', 'Рославль', 'Ярцево', 'Сафоново'],
            'Тамбовская область' => ['Тамбов', 'Мичуринск', 'Рассказово', 'Моршанск'],
            'Тверская область' => ['Тверь', 'Ржев', 'Вышний Волочек', 'Кимры', 'Торжок', 'Конаково', 'Удомля'],
            'Тульская область' => ['Тула', 'Новомосковск', 'Донской', 'Алексин', 'Щекино', 'Узловая', 'Ефремов'],
            'Ярославская область' => ['Ярославль', 'Рыбинск', 'Тутаев', 'Переславль-Залесский', 'Углич', 'Ростов'],

            // --- СЕВЕРО-ЗАПАДНЫЙ ФО ---
            'Архангельская область' => ['Архангельск', 'Северодвинск', 'Котлас', 'Новодвинск', 'Коряжма'],
            'Вологодская область' => ['Вологда', 'Череповец', 'Сокол', 'Великий Устюг'],
            'Калининградская область' => ['Калининград', 'Советск', 'Черняховск', 'Балтийск', 'Гусев', 'Светлый'],
            'Республика Карелия' => ['Петрозаводск', 'Кондопога', 'Сегежа', 'Костомукша'],
            'Республика Коми' => ['Сыктывкар', 'Ухта', 'Воркута', 'Печора', 'Усинск'],
            'Ленинградская область' => ['Гатчина', 'Выборг', 'Всеволожск', 'Сосновый Бор', 'Тихвин', 'Кириши', 'Мурино', 'Кудрово', 'Сертолово'],
            'Мурманская область' => ['Мурманск', 'Апатиты', 'Североморск', 'Мончегорск', 'Кандалакша'],
            'Новгородская область' => ['Великий Новгород', 'Боровичи', 'Старая Русса'],
            'Псковская область' => ['Псков', 'Великие Луки'],

            // --- ЮЖНЫЙ И КАВКАЗСКИЙ ФО ---
            'Краснодарский край' => ['Краснодар', 'Сочи', 'Новороссийск', 'Армавир', 'Анапа', 'Геленджик', 'Кропоткин', 'Славянск-на-Кубани', 'Туапсе', 'Лабинск'],
            'Ставропольский край' => ['Ставрополь', 'Пятигорск', 'Кисловодск', 'Невинномысск', 'Ессентуки', 'Михайловск', 'Минеральные Воды'],
            'Ростовская область' => ['Ростов-на-Дону', 'Таганрог', 'Шахты', 'Новочеркасск', 'Волгодонск', 'Батайск', 'Новошахтинск', 'Азов'],
            'Волгоградская область' => ['Волгоград', 'Волжский', 'Камышин', 'Михайловка'],
            'Астраханская область' => ['Астрахань', 'Ахтубинск'],
            'Республика Крым' => ['Симферополь', 'Керчь', 'Евпатория', 'Ялта', 'Феодосия', 'Джанкой'],
            'Республика Дагестан' => ['Махачкала', 'Хасавюрт', 'Дербент', 'Каспийск', 'Буйнакск'],
            'Чеченская Республика' => ['Грозный', 'Шали', 'Урус-Мартан'],
            'Республика Северная Осетия — Алания' => ['Владикавказ'],

            // --- ПРИВОЛЖСКИЙ ФО ---
            'Нижегородская область' => ['Нижний Новгород', 'Дзержинск', 'Арзамас', 'Саров', 'Бор', 'Кстово'],
            'Республика Татарстан' => ['Казань', 'Набережные Челны', 'Нижнекамск', 'Альметьевск', 'Зеленодольск', 'Бугульма'],
            'Республика Башкортостан' => ['Уфа', 'Стерлитамак', 'Салават', 'Нефтекамск', 'Октябрьский', 'Белорецк'],
            'Самарская область' => ['Самара', 'Тольятти', 'Сызрань', 'Новокуйбышевск', 'Чапаевск'],
            'Саратовская область' => ['Саратов', 'Энгельс', 'Балаково', 'Балашов'],
            'Пермский край' => ['Пермь', 'Березники', 'Соликамск', 'Чайковский', 'Кунгур', 'Лысьва'],
            'Удмуртская Республика' => ['Ижевск', 'Сарапул', 'Воткинск', 'Глазов'],
            'Оренбургская область' => ['Оренбург', 'Орск', 'Новотроицк', 'Бузулук'],
            'Пензенская область' => ['Пенза', 'Кузнецк', 'Заречный'],
            'Кировская область' => ['Киров', 'Кирово-Чепецк', 'Вятские Поляны'],
            'Ульяновская область' => ['Ульяновск', 'Димитровград'],
            'Чувашская Республика' => ['Чебоксары', 'Новочебоксарск', 'Канаш'],
            'Республика Мордовия' => ['Саранск', 'Рузаевка'],
            'Республика Марий Эл' => ['Йошкар-Ола', 'Волжск'],

            // --- УРАЛЬСКИЙ ФО ---
            'Свердловская область' => ['Екатеринбург', 'Нижний Тагил', 'Каменск-Уральский', 'Первоуральск', 'Серов', 'Новоуральск', 'Верхняя Пышма'],
            'Челябинская область' => ['Челябинск', 'Магнитогорск', 'Златоуст', 'Миасс', 'Копейск'],
            'Тюменская область' => ['Тюмень', 'Тобольск', 'Ишим'],
            'Ханты-Мансийский автономный округ' => ['Сургут', 'Нижневартовск', 'Нефтеюганск', 'Ханты-Мансийск', 'Когалым', 'Нягань'],
            'Ямало-Ненецкий автономный округ' => ['Новый Уренгой', 'Ноябрьск', 'Салехард', 'Надым'],
            'Курганская область' => ['Курган', 'Шадринск'],

            // --- СИБИРСКИЙ ФО ---
            'Новосибирская область' => ['Новосибирск', 'Бердск', 'Искитим', 'Куйбышев'],
            'Омская область' => ['Омск', 'Тара', 'Исилькуль'],
            'Красноярский край' => ['Красноярск', 'Норильск', 'Ачинск', 'Канск', 'Железногорск'],
            'Иркутская область' => ['Иркутск', 'Братск', 'Ангарск', 'Усть-Илимск', 'Усолье-Сибирское', 'Шелехов'],
            'Кемеровская область' => ['Кемерово', 'Новокузнецк', 'Прокопьевск', 'Междуреченск', 'Ленинск-Кузнецкий'],
            'Алтайский край' => ['Барнаул', 'Бийск', 'Рубцовск', 'Новоалтайск'],
            'Республика Алтай' => ['Горно-Алтайск'],
            'Томская область' => ['Томск', 'Северск', 'Стрежевой'],
            'Республика Хакасия' => ['Абакан', 'Черногорск', 'Саяногорск'],

            // --- ДАЛЬНЕВОСТОЧНЫЙ ФО ---
            'Приморский край' => ['Владивосток', 'Уссурийск', 'Находка', 'Артем'],
            'Хабаровский край' => ['Хабаровск', 'Комсомольск-на-Амуре', 'Амурск'],
            'Республика Бурятия' => ['Улан-Удэ', 'Северобайкальск'],
            'Забайкальский край' => ['Чита', 'Краснокаменск'],
            'Республика Саха (Якутия)' => ['Якутск', 'Нерюнгри', 'Мирный'],
            'Амурская область' => ['Благовещенск', 'Белогорск', 'Свободный'],
            'Сахалинская область' => ['Южно-Сахалинск', 'Холмск', 'Корсаков'],
            'Камчатский край' => ['Петропавловск-Камчатский', 'Елизово'],
            'Еврейская автономная область' => ['Биробиджан'],
        ];

        $this->stdout(">>> ЭТАП 2: Заполнение пустых городов\n", Console::FG_CYAN, Console::BOLD);
        $totalFixed = 0;
        foreach ($map as $regionName => $cities) {
            foreach ($cities as $cityName) {
                // Ищем паттерны типа "Московская обл Люберцы" или просто "г. Люберцы"
                // Используем mb_substr для регионов, чтобы ловить "Московская" и "Моск."
                $regionPart = mb_substr($regionName, 0, 6);
//                $pattern = '%' . $regionPart . '%' . $cityName . '%';
                $pattern = $regionPart . '% ' . $cityName . '%';
                
                $count = $db->createCommand()->update('dadata_address_cache', [
                    'region'         => $regionName,
                    'city'           => $cityName,
                    'city_type'      => 'г',
                    'city_type_full' => 'город',
                    'is_error'       => 3
                ], [
                    'and',
                    ['country' => 'Россия'],
                    ['like', 'source_text', $pattern, false],
                    ['<', 'is_error', 3], // Обрабатываем только неразмеченные (0) или ошибочные (1)
//                    ['is_error' => 1],
                    ['or', ['city' => null], ['city' => '']] // Только там, где город пустой
                ])->execute();

                if ($count > 0) {
                    $this->stdout("  [+] РФ | $regionName -> $cityName ($count)\n", Console::FG_GREEN);
                    $totalFixed += $count;
                }
            }
        }

        $this->stdout(">>> ЭТАП 3: Заполнение пустых округов\n", Console::FG_CYAN, Console::BOLD);
        $fallbackTotal = 0;
        foreach ($districts as $districtName => $regions) {
            foreach ($regions as $regionName) {
                // Ищем строки, которые начинаются с названия области или содержат его в начале
                // Например: "Белгородская область %"
                $count = $db->createCommand()->update('dadata_address_cache', [
                    'region' => $regionName,
                    'federal_district' => $districtName,
                    'is_error' => 2 // Фиксируем результат
                ], [
                    'and',
                    ['country' => 'Россия'],
                    ['<', 'is_error', 3],
                    ['or', ['region' => null], ['region' => '']],
                    ['like', 'source_text', $regionName . '%', false] 
                ])->execute();
                
                $fallbackTotal += $count;
            }
        }

        if ($fallbackTotal > 0) {
            $this->stdout("  [~] Фоллбэк по областям РФ: $fallbackTotal\n", Console::FG_YELLOW);
        }


        $this->stdout("\nИТОГО исправлено российских адресов: $totalFixed\n", Console::BOLD);
    }
/*
    public function actionLinkAddresses()
    {
        $db = Yii::$app->db;
        $this->stdout(">>> Начало привязки address_id к продажам...\n", Console::FG_CYAN);

        // Обновляем только те записи, где ID еще не проставлен
        $sql = "UPDATE detail_by_period d
                JOIN dadata_address_cache c ON MD5(d.ppvz_office_name) = c.hash
                SET d.address_id = c.id
                WHERE d.address_id IS NULL";

        $count = $db->createCommand($sql)->execute();

        $this->stdout("Успешно привязано адресов: $count\n", Console::FG_GREEN, Console::BOLD);
    }
*/

/**
     * Шаг 5: Привязка address_id только для записей ПРОДАЖ.
     */
/**
     * Шаг 5: Привязка address_id к продажам.
     */
    public function actionLinkByOfficeId()
    {
        $db = Yii::$app->db;
        $this->stdout("\n>>> Привязка address_id к продажам по ppvz_office_id ...\n", Console::FG_CYAN);

        $sql = "UPDATE detail_by_period d
                JOIN dadata_address_cache c ON d.ppvz_office_id = c.ppvz_office_id
                SET d.address_id = c.id
                WHERE d.doc_type_name = 'Продажа'
                  AND d.supplier_oper_name = 'Продажа'
                  AND d.address_id IS NULL
                  AND d.ppvz_office_id IS NOT NULL";

//                  AND d.payment_processing != 'Перевыставление эквайринга'


        $count = $db->createCommand($sql)->execute();

        if ($count > 0) {
            $this->stdout("  [OK] Успешно привязано адресов: $count\n", Console::FG_GREEN, Console::BOLD);
        } else {
            $this->stdout("  [?] Привязано 0. \n", Console::FG_RED);
        }
    }


    public function actionLinkAddresses()
    {
        $db = Yii::$app->db;
        $this->stdout("\n>>> Шаг 5: Привязка address_id к продажам...\n", Console::FG_CYAN);

        // 1. Считаем записи, которые реально имеют смысл для гео-аналитики
        $countToLink = (new \yii\db\Query())
            ->from('detail_by_period')
            ->where([
                'doc_type_name' => 'Продажа',
                'supplier_oper_name' => 'Продажа',
                'address_id' => null
            ])
            ->andWhere(['!=', 'payment_processing', 'Перевыставление эквайринга'])
            ->andWhere(['not', ['ppvz_office_name' => null]])
            ->andWhere(['>', 'LENGTH(TRIM(ppvz_office_name))', 0])
            ->count();

        $this->stdout("  [-] Найдено целевых продаж без address_id: $countToLink\n", Console::FG_YELLOW);

        if ($countToLink == 0) {
            $this->stdout("  [!] Нет данных для привязки (записи отсутствуют или уже обработаны).\n", Console::FG_GREY);
            return;
        }

        // 2. Выполняем UPDATE только для физических продаж
        $sql = "UPDATE detail_by_period d
                JOIN dadata_address_cache c ON MD5(d.ppvz_office_name) = c.hash
                SET d.address_id = c.id
                WHERE d.doc_type_name = 'Продажа'
                  AND d.supplier_oper_name = 'Продажа'
                  AND d.payment_processing != 'Перевыставление эквайринга'
                  AND d.address_id IS NULL
                  AND d.ppvz_office_name IS NOT NULL 
                  AND d.ppvz_office_name != ''";

        $count = $db->createCommand($sql)->execute();

        if ($count > 0) {
            $this->stdout("  [OK] Успешно привязано адресов: $count\n", Console::FG_GREEN, Console::BOLD);
        } else {
            $this->stdout("  [?] Привязано 0. Проверьте, прошел ли Sync для новых ПВЗ.\n", Console::FG_RED);
            
            // Диагностика на 1 примере (уже не пустом)
            $sample = (new \yii\db\Query())
                ->select(['ppvz_office_name', 'MD5(ppvz_office_name) as h'])
                ->from('detail_by_period')
                ->where(['doc_type_name' => 'Продажа', 'address_id' => null])
                ->andWhere(['!=', 'payment_processing', 'Перевыставление эквайринга'])
                ->andWhere(['>', 'LENGTH(ppvz_office_name)', 0])
                ->limit(1)
                ->one();
                
            if ($sample) {
                $this->stdout("      Пример ненайденного ПВЗ: '{$sample['ppvz_office_name']}'\n");
                $this->stdout("      Хэш для поиска в кеше: {$sample['h']}\n");
            }
        }
    }

    public function actionRecoverAddressIds()
    {
        $db = Yii::$app->db;
        $this->stdout("\n>>> Доп. шаг: Восстановление address_id по ppvz_office_id...\n", Console::FG_CYAN);

        // Этот запрос находит соответствие ppvz_office_id -> address_id 
        // там, где адрес есть, и обновляет там, где его нет.
        $sql = "UPDATE detail_by_period d
                INNER JOIN (
                    -- Создаем временную карту: какой офис к какому адресу привязан
                    -- Берем максимальный ID адреса для каждого офиса (на случай дублей)
                    SELECT ppvz_office_id, MAX(address_id) as found_address_id
                    FROM detail_by_period
                    WHERE ppvz_office_id > 0 
                      AND address_id IS NOT NULL
                    GROUP BY ppvz_office_id
                ) map ON d.ppvz_office_id = map.ppvz_office_id
                SET d.address_id = map.found_address_id
                WHERE d.address_id IS NULL
                  AND d.doc_type_name = 'Продажа'
                  AND d.supplier_oper_name = 'Продажа'";

        $count = $db->createCommand($sql)->execute();

        if ($count > 0) {
            $this->stdout("  [OK] Восстановлено адресов по ID офиса: $count\n", Console::FG_GREEN, Console::BOLD);
        } else {
            $this->stdout("  [-] Не найдено новых совпадений по ppvz_office_id.\n", Console::FG_GREY);
        }
    }

/**
     * Доп. шаг: Восстановление ppvz_office_name из кэша по известному address_id.
     */
    public function actionRestoreOfficeNames()
    {
        $db = Yii::$app->db;
        $this->stdout("\n>>> Доп. шаг: Восстановление названий офисов (ppvz_office_name)...\n", Console::FG_CYAN);

        // Используем JOIN с таблицей кэша, чтобы взять исходный текст адреса
        $sql = "UPDATE detail_by_period d
                JOIN dadata_address_cache c ON d.address_id = c.id
                SET d.ppvz_office_name = c.source_text
                WHERE (d.ppvz_office_name IS NULL OR d.ppvz_office_name = '')
                  AND d.address_id IS NOT NULL
                  AND d.doc_type_name = 'Продажа'";

        $count = $db->createCommand($sql)->execute();

        if ($count > 0) {
            $this->stdout("  [OK] Восстановлено названий офисов: $count\n", Console::FG_GREEN, Console::BOLD);
        } else {
            $this->stdout("  [-] Нет записей для восстановления имен.\n", Console::FG_GREY);
        }
    }


/**
     * Обогащение записей с пустым ppvz_office_name через справочники pvz_2023/2026.
     * Запуск: php yii address/sync-with-pvz
     */
    public function actionSyncWithPvz()
    {
        $db = Yii::$app->db;
        $apiKey = Yii::$app->params['dadataApiKey'] ?? null;

        if (!$apiKey) {
            $this->stderr("Ошибка: Не задан dadataApiKey в params.php\n", Console::FG_RED);
            return ExitCode::CONFIG;
        }

        $this->stdout(">>> Запуск обогащения пустых ppvz_office_name...\n", Console::FG_CYAN);

        // 1. Находим уникальные ID офисов, где имя пустое (только для Продаж)
        $missingOffices = (new Query())
            ->select(['ppvz_office_id'])
            ->from('detail_by_period')
            ->where([
                'doc_type_name' => 'Продажа',
                'supplier_oper_name' => 'Продажа',
                'ppvz_office_name' => ['', null]
            ])
            ->andWhere(['>', 'ppvz_office_id', 0])
            ->distinct()
            ->all();

        $this->stdout("  [-] Найдено офисов для восстановления: " . count($missingOffices) . "\n");

        foreach ($missingOffices as $office) {
            $officeId = $office['ppvz_office_id'];
            
            // 2. Поиск адреса в справочниках (сначала 2023, потом 2026)
            $address = (new Query())->select(['address'])->from('pvz_2026_99')->where(['wb_id' => $officeId])->scalar();
//            if (!$address) {
//                $address = (new Query())->select(['address'])->from('pvz_2026')->where(['wb_id' => $officeId])->scalar();
//            }

            if ($address) {
                // 3. Обновляем ppvz_office_name в продажах
                $db->createCommand()->update('detail_by_period', 
                    ['ppvz_office_name' => $address], 
                    ['ppvz_office_id' => $officeId, 'ppvz_office_name' => ['', null], 'doc_type_name' => 'Продажа', 'supplier_oper_name' => 'Продажа']
                )->execute();

                // 4. Синхронизация с Dadata (используем существующий механизм)
                $hash = md5($address);
                $addressId = (new Query())->select(['id'])->from('dadata_address_cache')->where(['hash' => $hash])->scalar();

                if (!$addressId) {
                    $rawResponse = $this->queryDadataSuggest($address, $apiKey);
                    $responseArray = Json::decode($rawResponse);


                    if (isset($responseArray['reason']) && $responseArray['reason'] === 'Forbidden') {
                        $this->stdout("STOP: Лимит Dadata исчерпан!\n", Console::FG_RED);
                        break; // Выходим из цикла офисов
                    }

                    if (isset($responseArray['suggestions'][0])) {
                        $suggestion = $responseArray['suggestions'][0];
                        $data = $suggestion['data'];

                        $city = $data['city'] ?? null;
                        $cityType = $data['city_type'] ?? null;
                        $cityTypeFull = $data['city_type_full'] ?? null;

                        if (!$city && !empty($data['settlement'])) {
                            $city = $data['settlement'];
                            $cityType = $data['settlement_type'];
                            $cityTypeFull = $data['settlement_type_full'];
                        }

                        $addressId = $this->saveToCache([
                            'hash'                    => $hash,
                            'source_text'             => $address,
                            'ppvz_office_id'          => $officeId, 
                            'result'                  => $suggestion['value'] ?? $address,
                            'postal_code'             => $data['postal_code'] ?? null,
                            'federal_district'        => $data['federal_district'] ?? null,
                            'region'                  => $data['region'] ?? null,
                            'city'                    => $city,
                            'city_type'               => $cityType,
                            'city_type_full'          => $cityTypeFull,
                            'settlement'              => $data['settlement'] ?? null,
                            'settlement_type'         => $data['settlement_type'] ?? null,
                            'settlement_type_full'    => $data['settlement_type_full'] ?? null,
                            'city_district'           => $data['city_district'] ?? null,
                            'city_district_type'      => $data['city_district_type'] ?? null,
                            'city_district_type_full' => $data['city_district_type_full'] ?? null,
                            'city_area'               => $data['city_area'] ?? null,
                            'country'                 => $data['country'] ?? 'Россия',
                            'is_error'                => 7, // Найден через обогащение
                            'full_json'               => $rawResponse,
                        ]);
                    } else {
                        $addressId = $this->saveToCache([
                            'hash'                    => $hash,
                            'source_text'             => $address,
                            'ppvz_office_id'          => $officeId, 
                            'result'                  => $address,
                            'postal_code'             => null,
                            'federal_district'        => null,
                            'region'                  => null,
                            'city'                    => null,
                            'city_type'               => null,
                            'city_type_full'          => null,
                            'settlement'              => null,
                            'settlement_type'         => null,
                            'settlement_type_full'    => null,
                            'city_district'           => null,
                            'city_district_type'      => null,
                            'city_district_type_full' => null,
                            'city_area'               => null,
                            'country'                 => 'Россия',
                            'is_error'                => 8, // Не найден через обогащение
                            'full_json'               => $rawResponse,
                        ]);
                    }
                }

                // 5. Проставляем address_id в таблицу продаж
                if ($addressId) {
                    $db->createCommand()->update('detail_by_period', 
                        ['address_id' => $addressId], 
                        ['ppvz_office_id' => $officeId, 'ppvz_office_name' => $address, 'doc_type_name' => 'Продажа', 'supplier_oper_name' => 'Продажа']
                    )->execute();
                    $this->stdout("  [+] ID $officeId восстановлен: $address\n", Console::FG_GREEN);
                }
            }
        }
        $this->stdout("Обогащение завершено.\n", Console::FG_CYAN);
    }

/**
     * Повторная проверка адресов с ошибкой (is_error = 6).
     * В случае успеха ставим статус 7 и привязываем к продажам.
     */
    public function actionRetryErrors($limit = 200)
    {
        $db = Yii::$app->db;
        $apiKey = Yii::$app->params['dadataApiKey'] ?? null;

        $this->stdout(">>> Перепроверка адресов со статусом 6 (ошибки)...\n", Console::FG_CYAN);

        // 1. Выбираем записи из кэша, которые ранее не были найдены
        $errorRecords = (new Query())
            ->from('dadata_address_cache')
            ->where(['is_error' => 6])
            ->limit($limit)
            ->all();

        if (empty($errorRecords)) {
            $this->stdout("  [!] Записей со статусом 6 не найдено.\n", Console::FG_GREY);
            return;
        }

        $successCount = 0;

        foreach ($errorRecords as $record) {
            $source = $record['source_text'];
            $hash = $record['hash'];
            $recordId = $record['id'];

            $rawResponse = $this->queryDadataSuggest($source, $apiKey);
            $responseArray = Json::decode($rawResponse);

            // Проверка лимита (как в ваших прошлых методах)
            if (isset($responseArray['reason']) && $responseArray['reason'] === 'Forbidden') {
                $this->stdout("STOP: Лимит Dadata исчерпан!\n", Console::FG_RED);
                break;
            }

            if (isset($responseArray['suggestions'][0])) {
                $suggestion = $responseArray['suggestions'][0];
                $data = $suggestion['data'];
                
                $city = $data['city'] ?? $data['settlement'] ?? null;

                // 2. Обновляем запись в кэше: статус 7 (исправлено) и новые данные
                $db->createCommand()->update('dadata_address_cache', [
                    'result'         => $suggestion['value'] ?? $source,
                    'postal_code'    => $data['postal_code'] ?? null,
                    'federal_district' => $data['federal_district'] ?? null,
                    'region'         => $data['region'] ?? null,
                    'city'           => $city,
                    'city_type'      => $data['city_type'] ?? null,
                    'settlement'     => $data['settlement'] ?? null,
                    'is_error'       => 7, // Статус: успешно перепроверено
                    'full_json'      => $rawResponse
//                    'updated_at'     => date('Y-m-d H:i:s')
                ], ['id' => $recordId])->execute();

                // 3. Обновляем address_id в продажах там, где он был NULL
                $linked = $db->createCommand()->update('detail_by_period', 
                    ['address_id' => $recordId], 
                    [
                        'doc_type_name' => 'Продажа',
                        'supplier_oper_name' => 'Продажа',
                        'address_id' => null,
                        'ppvz_office_name' => $source
                    ]
                )->execute();

                $this->stdout("  [OK] Исправлено: '$source' (Привязано строк: $linked)\n", Console::FG_GREEN);
                $successCount++;
            } else {
                $this->stdout("  [-] По-прежнему не найден: '$source'\n", Console::FG_GREY);
            }
        }

        $this->stdout("\nИТОГО исправлено адресов: $successCount\n", Console::BOLD);
    }


    public function actionFixFromMapping()
    {
        $db = Yii::$app->db;
        $this->stdout(">>> Ручной разбор адресов (is_error=6) через справочник...\n", Console::FG_CYAN);

        $mapping = $this->getInternalMapping();
        $regions = $mapping['regions'];
        $federalDistricts = $mapping['federalDistricts'];

        // Выбираем только те, что Dadata не смогла разобрать
        $records = (new Query())
            ->from('dadata_address_cache')
            ->where(['is_error' => 7])
            ->all();

        if (empty($records)) {
            $this->stdout("  [-] Нет записей для обработки.\n", Console::FG_GREY);
            return;
        }

        $fixedCount = 0;

        foreach ($records as $record) {
            $source = $record['source_text'];
            $detectedRegion = null;
            $detectedCity = null;

            // 1. Сначала ищем вхождение города (это дает максимальную точность)
            foreach ($regions as $regionName => $cities) {
                foreach ($cities as $city) {
                    // Используем регистронезависимый поиск
                    if (mb_stripos($source, $city) !== false) {
                        $detectedCity = $city;
                        $detectedRegion = $regionName;
                        break 2;
                    }
                }
            }

            // 2. Если город не найден в списке, ищем хотя бы название региона
            if (!$detectedRegion) {
                foreach (array_keys($regions) as $regionName) {
                    if (mb_stripos($source, $regionName) !== false) {
                        $detectedRegion = $regionName;
                        break;
                    }
                }
            }

            if ($detectedRegion) {
                // Обновляем кэш: ставим регион, город, округ и статус 8
                $db->createCommand()->update('dadata_address_cache', [
                    'region' => $detectedRegion,
                    'city' => $detectedCity,
                    'federal_district' => $federalDistricts[$detectedRegion] ?? null,
                    'is_error' => 8, // Статус: Исправлено по внутреннему маппингу
                    'country' => 'Россия'
//                    'updated_at' => date('Y-m-d H:i:s')
                ], ['id' => $record['id']])->execute();

                // Принудительно линкуем продажи с этим ID адреса
                $linked = $db->createCommand()->update('detail_by_period', 
                    ['address_id' => $record['id']], 
                    [
                        'address_id' => null, 
                        'ppvz_office_name' => $source,
                        'doc_type_name' => 'Продажа',
                        'supplier_oper_name' => 'Продажа'
                    ]
                )->execute();

                $this->stdout("  [OK] Исправлено: '$source' -> $detectedRegion" . ($detectedCity ? " ($detectedCity)" : "") . "\n", Console::FG_GREEN);
                $fixedCount++;
            }
        }

        $this->stdout("\nИТОГО исправлено через маппинг: $fixedCount\n", Console::FG_CYAN, Console::BOLD);
    }

/**
     * Восстановление адресов через координаты (lat/lon) из справочников.
     */
    public function actionFixByGeolocate($limit = 200)
    {
        $db = Yii::$app->db;
        $apiKey = Yii::$app->params['dadataApiKey'] ?? null;

        $this->stdout(">>> Обратное геокодирование для адресов со статусом 6...\n", Console::FG_CYAN);

        // 1. Находим записи в кэше с ошибкой
        $errorRecords = (new Query())
            ->from('dadata_address_cache')
            ->where(['is_error' => 6])
            ->limit($limit)
            ->all();

        foreach ($errorRecords as $record) {
            $source = $record['source_text'];
            $recordId = $record['id'];

            // 2. Пытаемся найти координаты для этого текстового адреса в справочниках
            // Ищем через таблицу продаж, чтобы выйти на office_id
            $officeData = (new Query())
                ->select(['ppvz_office_id'])
                ->from('detail_by_period')
                ->where(['ppvz_office_name' => $source])
                ->andWhere(['>', 'ppvz_office_id', 0])
                ->one();

            if (!$officeData) continue;

            $officeId = $officeData['ppvz_office_id'];
            
            // Ищем lat/lon в справочниках
            $geo = (new Query())
                ->select(['lat', 'lon'])
                ->from('pvz_2026_99')
                ->where(['wb_id' => $officeId])
                ->andWhere(['not', ['lat' => null]])
                ->one();
/*
            if (!$geo) {
                $geo = (new Query())
                    ->select(['lat', 'lon'])
                    ->from('pvz_2026')
                    ->where(['wb_id' => $officeId])
                    ->andWhere(['not', ['lat' => null]])
                    ->one();
            }
*/
            if ($geo && $geo['lat'] && $geo['lon']) {
                $rawResponse = $this->queryDadataGeolocate($geo['lat'], $geo['lon'], $apiKey);
                $responseArray = Json::decode($rawResponse);

                // Проверка лимита Forbidden
                if (isset($responseArray['reason']) && $responseArray['reason'] === 'Forbidden') {
                    $this->stdout("STOP: Лимит Dadata исчерпан!\n", Console::FG_RED);
                    break;
                }

                if (isset($responseArray['suggestions'][0])) {
                    $suggestion = $responseArray['suggestions'][0];
                    $data = $suggestion['data'];
                    
                    $city = $data['city'] ?? $data['settlement'] ?? null;

                    // 3. Обновляем кэш со статусом 9 (Geolocated)
                    $db->createCommand()->update('dadata_address_cache', [
                        'result'         => $suggestion['value'],
                        'postal_code'    => $data['postal_code'] ?? null,
                        'region'         => $data['region'] ?? null,
                        'city'           => $city,
                        'city_type'      => $data['city_type'] ?? null,
                        'settlement'     => $data['settlement'] ?? null,
                        'is_error'       => 9, // Статус: Восстановлено по координатам
                        'full_json'      => $rawResponse,
//                        'updated_at'     => date('Y-m-d H:i:s')
                    ], ['id' => $recordId])->execute();

                    // 4. Обновляем продажи
                    $db->createCommand()->update('detail_by_period', 
                        ['address_id' => $recordId], 
                        ['ppvz_office_name' => $source, 'address_id' => null]
                    )->execute();

                    $this->stdout("  [GEO] $source -> " . $suggestion['value'] . "\n", Console::FG_GREEN);
                } else {
                    $this->stdout("  [-] Координаты есть, но Dadata не узнала адрес: $source\n", Console::FG_GREY);
                }
            } else {
                $this->stdout("  [-] Нет координат для ID $officeId ($source)\n", Console::FG_GREY);
            }
        }
        $this->stdout("Геокодирование завершено.\n", Console::FG_CYAN);
    }


/**
     * Заполняет ppvz_office_id в таблице кэша на основе существующих связей.
     */
    public function actionFillOfficeIds()
    {
        $db = Yii::$app->db;
        $this->stdout(">>> Синхронизация ppvz_office_id в кэше...\n", Console::FG_CYAN);

        // Обновляем кэш, подтягивая office_id из таблицы продаж по совпадению текста
        $sql = "UPDATE dadata_address_cache c
                INNER JOIN (
                    SELECT DISTINCT ppvz_office_name, ppvz_office_id 
                    FROM detail_by_period 
                    WHERE ppvz_office_id > 0
                ) sales ON c.source_text = sales.ppvz_office_name
                SET c.ppvz_office_id = sales.ppvz_office_id
                WHERE c.ppvz_office_id IS NULL";

        $count = $db->createCommand($sql)->execute();
        
        $this->stdout("  [+] Обновлено записей в кэше: $count\n", Console::FG_GREEN);

        // Вторым шагом — проставляем пропущенные address_id в самих продажах
        $this->stdout(">>> Простановка пропущенных address_id в продажах...\n", Console::FG_CYAN);
        
        $sqlSales = "UPDATE detail_by_period s
                     INNER JOIN dadata_address_cache c ON s.ppvz_office_name = c.source_text
                     SET s.address_id = c.id
                     WHERE s.address_id IS NULL";
                     
        $countSales = $db->createCommand($sqlSales)->execute();
        $this->stdout("  [+] Привязано строк в продажах: $countSales\n", Console::FG_GREEN);
    }

/**
     * Сопоставление адресов по чистому тексту (без учета hash).
     * Чистит пробелы, регистр и спецсимволы.
     */
/**
     * Связывание продаж с кэшем по текстовому совпадению (ppvz_office_name).
     * Полезно для ретроспективного заполнения address_id в старых записях.
     */
    public function actionLinkByTextMatch($limit = 1000)
    {
        $db = Yii::$app->db;
        $this->stdout(">>> Сопоставление по тексту (ppvz_office_name)... \n", Console::FG_CYAN);

        // Находим уникальные имена офисов из продаж, у которых нет address_id
        $missing = (new Query())
            ->select(['ppvz_office_name'])
            ->from('detail_by_period')
            ->where(['address_id' => null])
            ->andWhere(['not', ['ppvz_office_name' => ['', null]]])
            ->distinct()
            ->limit($limit)
            ->all();

        if (empty($missing)) {
            $this->stdout("Все записи уже связаны.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $linkedCount = 0;
        foreach ($missing as $item) {
            $name = $item['ppvz_office_name'];
            
            // Ищем это имя в кэше (в поле source_text)
            $cacheId = (new Query())
                ->select(['id'])
                ->from('dadata_address_cache')
                ->where(['source_text' => $name])
                ->scalar();

            if ($cacheId) {
                // Если нашли — обновляем все записи в продажах с этим именем
                $updated = $db->createCommand()->update(
                    'detail_by_period', 
                    ['address_id' => $cacheId], 
                    ['ppvz_office_name' => $name, 'address_id' => null]
                )->execute();

                if ($updated > 0) {
                    $this->stdout("  [+] Связано: '$name' -> ID кэша $cacheId (Строк: $updated)\n", Console::FG_GREEN);
                    $linkedCount += $updated;
                }
            }
        }

        $this->stdout("\nЗавершено. Всего обновлено строк: $linkedCount\n", Console::BOLD);
        return ExitCode::OK;
    }


/**
     * Исправление записей в кэше.
     * Сначала пробует Подсказки по тексту, если нет — ищет координаты в ПВЗ и делает Геокодирование.
     */
    public function actionFixMissingCities($limit = 500)
    {
        $db = Yii::$app->db;
        $apiKey = Yii::$app->params['dadataApiKey'] ?? null;

        if (!$apiKey) {
            $this->stderr("Ошибка: dadataApiKey не найден.\n", Console::FG_RED);
            return ExitCode::CONFIG;
        }

        $this->stdout(">>> Исправление пустых городов (Suggest + Geolocation)...\n", Console::FG_CYAN);

        $records = (new Query())
            ->from('dadata_address_cache')
            ->where(['is_error' => 8])
            ->andWhere(['country' => 'Россия'])
            ->andWhere(['region' => null])
            ->all();

        if (empty($records)) {
            $this->stdout("Проблемных записей не найдено.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        foreach ($records as $record) {
            $source = $record['source_text'];
            $recordId = $record['id'];
            $officeId = $record['ppvz_office_id'];
            
            $this->stdout("Обработка [$recordId] (Office ID: $officeId): $source -> ");

            $rawResponse = null;
            $foundMethod = 'none';

            // ШАГ 1: Пробуем обычные подсказки по тексту

            if (!empty($source)) {
                $rawResponse = $this->queryDadataSuggest($source, $apiKey);
                $response = Json::decode($rawResponse);
                if (!empty($response['suggestions'][0])) {
                    $foundMethod = 'suggest';
                }
            }

            // ШАГ 2: Если по тексту пусто — идем в справочники ПВЗ
            if ($foundMethod === 'none' && $officeId > 0) {
                $pvz = (new Query())
                    ->select(['lat', 'lon'])
                    ->from('pvz_2026_99')
                    ->where(['wb_id' => $officeId])
                    ->one();
/*
                if (!$pvz) {
                    $pvz = (new Query())
                        ->select(['lat', 'lon'])
                        ->from('pvz_2026')
                        ->where(['wb_id' => $officeId])
                        ->one();
                }
*/
                if ($pvz && $pvz['lat'] && $pvz['lon']) {
                    $this->stdout("[Поиск по координатам] ", Console::FG_YELLOW);
                    $rawResponse = $this->queryDadataGeolocate($pvz['lat'], $pvz['lon'], $apiKey);
                    $response = Json::decode($rawResponse);
                    if (!empty($response['suggestions'][0])) {
                        $foundMethod = 'geolocate';
                    }
                }
            }

            // ШАГ 3: Если данные найдены любым способом — обновляем
            if ($foundMethod !== 'none') {
                $suggestion = $response['suggestions'][0];
                $data = $suggestion['data'];

                // Твоя логика обработки города (Москва/СПБ/Севастополь)
                $city = $data['city'] ?? null;
                $cityType = $data['city_type'] ?? null;
                $cityTypeFull = $data['city_type_full'] ?? null;

                if (empty($city) && isset($data['region'])) {
                    if (in_array($data['region'], ['Москва', 'Санкт-Петербург', 'Севастополь'])) {
                        $city = $data['region'];
                        $cityType = 'г';
                        $cityTypeFull = 'город';
                    }
                }

                $db->createCommand()->update('dadata_address_cache', [
                    'result'                  => $suggestion['value'] ?? $source,
                    'postal_code'             => $data['postal_code'] ?? null,
                    'federal_district'        => $data['federal_district'] ?? null,
                    'region'                  => $data['region'] ?? null,
                    'city'                    => $city,
                    'city_type'               => $cityType,
                    'city_type_full'          => $cityTypeFull,
                    'settlement'              => $data['settlement'] ?? null,
                    'settlement_type'         => $data['settlement_type'] ?? null,
                    'settlement_type_full'    => $data['settlement_type_full'] ?? null,
                    'city_district'           => $data['city_district'] ?? null,
                    'city_district_type'      => $data['city_district_type'] ?? null,
                    'city_district_type_full' => $data['city_district_type_full'] ?? null,
                    'city_area'               => $data['city_area'] ?? null,
                    'full_json'               => $rawResponse,
                    'is_error'                => ($foundMethod === 'geolocate' ? 7 : 7) // 8 - через гео, 7 - через текст
                ], ['id' => $recordId])->execute();

                // Обновляем продажи
                $db->createCommand()->update('detail_by_period', 
                    ['address_id' => $recordId], 
                    ['address_id' => null, 'ppvz_office_name' => $source]
                )->execute();

                $this->stdout("OK (" . ($city ?? $data['settlement'] ?? 'н/д') . ")\n", Console::FG_GREEN);
            } else {
                $this->stdout("не удалось найти ни по адресу, ни по координатам\n", Console::FG_GREY);
            }
        }

        return ExitCode::OK;
    }


public function actionFixIso()
{
    // Берем записи, где есть json, но нет кода
    $rows = (new \yii\db\Query())
        ->select(['id', 'full_json'])
        ->from('dadata_address_cache')
        ->where(['region_iso_code' => null])
        ->andWhere(['is not', 'full_json', null])
        ->all();

    $count = 0;
    foreach ($rows as $row) {
        // Учитывая экранирование в вашем примере, декодируем
        $data = json_decode($row['full_json'], true);
        
        // Если пришло как строка внутри строки, может понадобиться второй проход:
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        $isoCode = $data['region_iso_code'] ?? null;

        if ($isoCode) {
            Yii::$app->db->createCommand()
                ->update('dadata_address_cache', 
                    ['region_iso_code' => $isoCode], 
                    ['id' => $row['id']]
                )->execute();
            $count++;
        }
    }

    return "Готово! Обновлено записей: " . $count;
}

    public function actionFix() {
        // 3. Исправляем Россию (Города, Области, Округа)
        $this->actionFixRussia();

        // 2. Чистим старые ошибки и нормализуем названия (Этап 0 внутри)
        $this->actionNormalize();

        // 4. Исправляем СНГ (Кыргызстан, Беларусь и др.)
        $this->actionFixForeign();

        // 5. Проставляем ID в таблицу продаж
        $this->actionLinkAddresses();

        $this->actionLinkByOfficeId();
/*

php yii geo-fill/full-repair
*/
    }

}