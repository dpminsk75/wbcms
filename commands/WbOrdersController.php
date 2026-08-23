<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;
use yii\helpers\Console;

class WbOrdersController extends Controller
{
    /**
     * Загрузка заказов. Пример: php yii wb-orders/fetch 2024-01-01 2024-01-07
     *
     * ВАЖНО: API WB не поддерживает фильтр "по" (dateTo) на своей стороне —
     * он всегда отдаёт все заказы начиная с dateFrom. Поэтому верхняя граница
     * диапазона применяется здесь, на нашей стороне, после получения ответа.
     */
    public function actionFetch($dateFrom = null, $dateTo = null)
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

        if (!$dateFrom) {
            $dateFrom = date('Y-m-d', strtotime('-3 days'));
        }

        $totalErrors = 0;

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
            $token = $company['api_key'] ?? null;

            if (!$token) {
                $this->stdout("Пропуск компании '{$companyName}': отсутствует токен api_key.\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("Загрузка данных для компании '{$companyName}' с {$dateFrom}...\n", Console::FG_CYAN);

            $url = "https://statistics-api.wildberries.ru/api/v1/supplier/orders?dateFrom=" . urlencode($dateFrom);

            $this->stdout("url: $url \n", Console::FG_CYAN);
            $response = $this->makeRequest($url, $token);

            if ($response === null) {
                $this->stderr("Не удалось получить данные для компании '{$companyName}'\n", Console::FG_RED);
                continue;
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                $this->stderr("Ошибка: Не удалось декодировать JSON или пустой ответ.\n", Console::FG_RED);
                continue;
            }

            $this->printResponseStats($data, $companyName);

            [$count, $errors] = $this->processData($db, $data, $companyId, $dateFrom, $dateTo);
            $totalErrors += $errors;

            $this->stdout("Успешно обработано строк для {$companyName}: {$count}" .
                ($errors ? " (ошибок: {$errors})" : "") . "\n", $errors ? Console::FG_YELLOW : Console::FG_GREEN);

            sleep(1);
        }

        return $totalErrors > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Загрузка заказов через новый метод WB (идёт на смену supplier/orders).
     * Пример: php yii wb-orders/fetch-order-feed 2026-07-23 2026-07-31
     *
     * По умолчанию период — с 00:00 3 дня назад по 23:59:59 текущей даты (МСК).
     *
     * В отличие от supplier/orders, этот метод поддерживает верхнюю границу
     * периода (end) на стороне API, поэтому дополнительная клиентская
     * фильтрация по dateTo здесь не требуется.
     *
     * Метод отдаёт заметно меньше полей, чем supplier/orders (нет gNumber,
     * артикула, баркода, размера, сумм скидок и т.д.). Если заказ с таким
     * srid уже есть в БД (например, загружен через supplier/orders) — при
     * повторной загрузке обновляются только статус и поля, которых нет в
     * старом API (last_change_date, status, cancel_type, is_cancel,
     * cancel_date, is_mp, is_b2b, chrt_id). Остальное на апдейте не трогается.
     * Подробности маппинга — в комментарии к processOrderFeedData().
     */
    public function actionFetchOrderFeed($dateFrom = null, $dateTo = null)
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

        if (!$dateFrom) {
            $dateFrom = date('Y-m-d', strtotime('-3 days'));
        }
        if (!$dateTo) {
            $dateTo = date('Y-m-d');
        }

        $tz = new \DateTimeZone('+03:00');
        $periodStart = (new \DateTime($dateFrom . ' 00:00:00', $tz))->format('Y-m-d\TH:i:s.00000P');
        $periodEnd = (new \DateTime($dateTo . ' 23:59:59', $tz))->format('Y-m-d\TH:i:s.00000P');

        $totalErrors = 0;

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
            $token = $company['api_key'] ?? null;

            if (!$token) {
                $this->stdout("Пропуск компании '{$companyName}': отсутствует токен api_key.\n", Console::FG_YELLOW);
                continue;
            }

            $this->stdout("Загрузка заказов (order-feed) для компании '{$companyName}' за {$periodStart} - {$periodEnd}...\n", Console::FG_CYAN);

            $url = "https://seller-analytics-api.wildberries.ru/api/analytics/v1/order-feed";
            $selectedPeriod = [
                'start' => $periodStart,
                'end'   => $periodEnd,
            ];

            $result = $this->fetchAllOrderFeedOrders($url, $token, $selectedPeriod, $companyName);

            if ($result === null) {
                $this->stderr("Не удалось получить данные для компании '{$companyName}'\n", Console::FG_RED);
                continue;
            }

            $orders = $result['orders'];

            if ($result['snapshotTime']) {
                $this->stdout("snapshotTime: {$result['snapshotTime']}\n", Console::FG_CYAN);
            }
            $this->stdout("Страниц загружено: {$result['pages']}. Всего заказов (все страницы): " . count($orders) . "\n", Console::FG_CYAN);

            $this->printResponseStats($orders, $companyName, 'createdAt');

            [$count, $errors] = $this->processOrderFeedData($db, $orders, $companyId);
            $totalErrors += $errors;

            $this->stdout("Успешно обработано строк (order-feed) для {$companyName}: {$count}" .
                ($errors ? " (ошибок: {$errors})" : "") . "\n", $errors ? Console::FG_YELLOW : Console::FG_GREEN);

            sleep(1);
        }

        return $totalErrors > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * ТЕСТОВЫЙ запрос к order-feed: за один день, с pagination.limit,
     * без записи в БД — просто печатает сырой ответ API в консоль, чтобы
     * посмотреть реальную структуру данных перед тем, как править маппинг.
     *
     * Пример: php yii wb-orders/test-order-feed
     * Пример: php yii wb-orders/test-order-feed 2026-08-08 5
     * Пример: php yii wb-orders/test-order-feed 2026-08-08 2 3   (3 - id компании)
     */
    public function actionTestOrderFeed($date = null, $limit = 2, $companyId = null)
    {
        $db = Yii::$app->db;

        if (!$date) {
            $date = date('Y-m-d');
        }

        $query = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1]);

        if ($companyId) {
            $query->andWhere(['id' => $companyId]);
        }

        $company = $query->one($db);

        if (!$company || empty($company['api_key'])) {
            $this->stderr("Не найдена активная компания с токеном api_key" . ($companyId ? " (id={$companyId})" : "") . ".\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $tz = new \DateTimeZone('+03:00');
        $periodStart = (new \DateTime($date . ' 00:00:00', $tz))->format('Y-m-d\TH:i:s.00000P');
        $periodEnd = (new \DateTime($date . ' 23:59:59', $tz))->format('Y-m-d\TH:i:s.00000P');

        $this->stdout("Тестовый запрос order-feed. Компания: '{$company['name']}'. Период: {$periodStart} - {$periodEnd}. limit={$limit}\n", Console::FG_CYAN);

        $url = "https://seller-analytics-api.wildberries.ru/api/analytics/v1/order-feed";
        $body = [
            'selectedPeriod' => [
                'start' => $periodStart,
                'end'   => $periodEnd,
            ],
            'pagination' => [
                'limit' => (int)$limit,
            ],
        ];

        $response = $this->makePostRequest($url, $company['api_key'], $body);

        if ($response === null) {
            $this->stderr("Не удалось получить ответ от API.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $this->stdout("Сырой ответ (не удалось декодировать как JSON):\n{$response}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("--- Сырой JSON-ответ ---\n", Console::FG_GREEN);
        $this->stdout(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

        return ExitCode::OK;
    }

    /**
     * Запрос к API WB с retry на 429 (Too Many Requests)
     */
    private function makeRequest($url, $token)
    {
        $doRequest = function () use ($url, $token) {
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

            return [$response, $httpCode];
        };

        [$response, $httpCode] = $doRequest();

        if ($httpCode == 429) {
            $this->stdout("Превышен лимит запросов (429). Ждем 61 секунду...\n", Console::FG_YELLOW);
            sleep(61);
            [$response, $httpCode] = $doRequest();
        }

        if ($httpCode != 200) {
            $this->stderr("Ошибка выполнения запроса: HTTP код {$httpCode}\n", Console::FG_RED);
            return null;
        }

        return $response;
    }

    /**
     * POST-запрос к API WB с JSON-телом и retry на 429 (Too Many Requests).
     */
    private function makePostRequest($url, $token, array $body)
    {
        $payload = Json::encode($body);

        $doRequest = function () use ($url, $token, $payload) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $token,
                'Accept: application/json',
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [$response, $httpCode];
        };

        [$response, $httpCode] = $doRequest();

        if ($httpCode == 429) {
            $this->stdout("Превышен лимит запросов (429). Ждем 61 секунду...\n", Console::FG_YELLOW);
            sleep(61);
            [$response, $httpCode] = $doRequest();
        }

        if ($httpCode != 200) {
            $this->stderr("Ошибка выполнения запроса: HTTP код {$httpCode}. Ответ: " . substr((string)$response, 0, 500) . "\n", Console::FG_RED);
            return null;
        }

        return $response;
    }

    /**
     * Постраничная загрузка ВСЕХ заказов из order-feed за период.
     *
     * По умолчанию метод отдаёт максимум 50 заказов за запрос — чтобы
     * получить всё, листаем через pagination.offset/limit, пока страница
     * не вернёт меньше limit заказов (значит, это последняя страница).
     *
     * snapshotTime из первого ответа передаётся во все последующие
     * запросы — так все страницы согласованы между собой на один и тот
     * же момент времени (без него офсет мог бы "поехать", если между
     * запросами появятся/изменятся заказы).
     *
     * @return array{orders: array, snapshotTime: ?string, pages: int}|null null при ошибке запроса
     */
    private function fetchAllOrderFeedOrders(string $url, string $token, array $selectedPeriod, string $companyName): ?array
    {
        $limit = 1000;
        $offset = 0;
        $snapshotTime = null;
        $allOrders = [];
        $page = 0;
        $maxPages = 1000; // защита от зацикливания, если API вдруг перестанет отдавать корректную последнюю страницу

        do {
            $page++;

            $pagination = ['limit' => $limit, 'offset' => $offset];
            if ($snapshotTime !== null) {
                $pagination['snapshotTime'] = $snapshotTime;
            }

            $body = [
                'selectedPeriod' => $selectedPeriod,
                'pagination'     => $pagination,
            ];

            $response = $this->makePostRequest($url, $token, $body);
            if ($response === null) {
                $this->stderr("Ошибка получения страницы {$page} (offset={$offset}) для '{$companyName}'.\n", Console::FG_RED);
                return $allOrders ? ['orders' => $allOrders, 'snapshotTime' => $snapshotTime, 'pages' => $page - 1] : null;
            }

            $decoded = json_decode($response, true);
            $pageOrders = $decoded['data']['orders'] ?? null;
            if (!is_array($pageOrders)) {
                $this->stderr("Ошибка: страница {$page} — не удалось декодировать JSON или отсутствует data.orders.\n", Console::FG_RED);
                break;
            }

            if ($snapshotTime === null && !empty($decoded['data']['snapshotTime'])) {
                $snapshotTime = $decoded['data']['snapshotTime'];
            }

            $received = count($pageOrders);
            $this->stdout("  Страница {$page}: offset={$offset}, получено {$received} заказ(ов).\n", Console::FG_CYAN);

            $allOrders = array_merge($allOrders, $pageOrders);
            $offset += $received;

            if ($received < $limit) {
                break; // последняя страница — заказов меньше, чем лимит
            }

            if ($page >= $maxPages) {
                $this->stderr("Достигнут предел в {$maxPages} страниц — останавливаемся во избежание зацикливания.\n", Console::FG_YELLOW);
                break;
            }

            sleep(1); // бережём лимит запросов API между страницами
        } while (true);

        return ['orders' => $allOrders, 'snapshotTime' => $snapshotTime, 'pages' => $page];
    }

    /**
     * Вывод в консоль статистики по сырому ответу API (до применения
     * клиентского фильтра по dateTo): сколько всего заказов, разбивка
     * по датам и дата/время самого свежего заказа.
     *
     * @param string $dateField Имя поля с датой заказа в элементе массива
     *                          ('date' для statistics-api, 'createdAt' для order-feed).
     */
    private function printResponseStats(array $data, string $companyName, string $dateField = 'date'): void
    {
        $total = count($data);
        $this->stdout("Ответ API для '{$companyName}': всего заказов в ответе: {$total}\n", Console::FG_CYAN);

        if ($total === 0) {
            return;
        }

        $byDate = [];
        $latestDateTime = null;

        foreach ($data as $item) {
            $dateTime = (string)($item[$dateField] ?? '');
            $day = substr($dateTime, 0, 10);

            if ($day !== '') {
                $byDate[$day] = ($byDate[$day] ?? 0) + 1;
            }

            if ($dateTime !== '' && ($latestDateTime === null || $dateTime > $latestDateTime)) {
                $latestDateTime = $dateTime;
            }
        }

        ksort($byDate);
        $this->stdout("Заказы по датам:\n", Console::FG_CYAN);
        foreach ($byDate as $day => $cnt) {
            $this->stdout("  {$day}: {$cnt}\n");
        }

        if ($latestDateTime !== null) {
            $this->stdout("Дата и время самого свежего заказа: {$latestDateTime}\n", Console::FG_GREEN);
        }
    }

    /**
     * Сохранение заказов в wb_order через upsert.
     *
     * @return array [int $savedCount, int $errorCount]
     */
    private function processData($db, array $data, int $companyId, string $dateFrom, ?string $dateTo): array
    {
        $count = 0;
        $errors = 0;

        foreach ($data as $item) {
            // Клиентская фильтрация по верхней границе диапазона (API её не поддерживает).
            if ($dateTo) {
                $itemDate = substr((string)($item['date'] ?? ''), 0, 10);
                if ($itemDate === '' || $itemDate > $dateTo) {
                    continue;
                }
            }

            // Обязательные колонки в wb_order: NOT NULL без DEFAULT.
            // Если WB вдруг не прислал одно из этих полей — лучше явно
            // пропустить запись с понятным сообщением, чем словить
            // малопонятную ошибку целостности БД на INSERT/UPSERT.
            $requiredFields = ['gNumber' => 'g_number', 'date' => 'date', 'lastChangeDate' => 'last_change_date', 'srid' => 'srid'];
            $missing = [];
            foreach ($requiredFields as $apiField => $column) {
                if (empty($item[$apiField])) {
                    $missing[] = $apiField;
                }
            }
            if ($missing) {
                $errors++;
                $this->stderr("Пропущена запись: отсутствуют обязательные поля (" . implode(', ', $missing) . "). Данные: " . json_encode($item) . "\n", Console::FG_RED);
                continue;
            }

            // cancel_date в БД NOT NULL, но семантически "нет даты отмены"
            // для неотменённого заказа — это NULL, а не значение из API
            // (WB может присылать пустую строку/дату-заглушку). Колонку
            // нужно сделать nullable в БД:
            // ALTER TABLE wb_order MODIFY `cancel_date` DATETIME NULL DEFAULT NULL;
            $isCancel = !empty($item['isCancel']);
            $cancelDate = $isCancel ? ($item['cancelDate'] ?? null) : null;
            if ($cancelDate === '' ) {
                $cancelDate = null;
            }

            try {
                $db->createCommand()->upsert('wb_order', [
                    'company_id'        => $companyId,
                    'g_number'          => (string)($item['gNumber'] ?? ''),
                    'date'              => $item['date'] ?? null,
                    'last_change_date'  => $item['lastChangeDate'] ?? null,
                    'cancel_date'       => $cancelDate,
                    'supplier_article'  => $item['supplierArticle'] ?? null,
                    'tech_size'         => $item['techSize'] ?? null,
                    'barcode'           => $item['barcode'] ?? null,
                    'total_price'       => $item['totalPrice'] ?? null,
                    'discount_percent'  => $item['discountPercent'] ?? null,
                    'warehouse_name'    => $item['warehouseName'] ?? null,
                    'warehouse_type'    => $item['warehouseType'] ?? null,
                    'country_name'      => $item['countryName'] ?? null,
                    'oblast_okrug_name' => $item['oblastOkrugName'] ?? null,
                    'region_name'       => $item['regionName'] ?? null,
                    'sale_id'           => $item['saleID'] ?? null,
                    'odid'              => $item['odid'] ?? null,
                    'for_pay'           => $item['forPay'] ?? null,
                    'order_type'        => $item['orderType'] ?? null,
                    'income_id'         => $item['incomeID'] ?? null,
                    'spp'               => $item['spp'] ?? null,
                    'finished_price'    => $item['finishedPrice'] ?? null,
                    'price_with_disc'   => $item['priceWithDisc'] ?? null,
                    'nm_id'             => $item['nmId'] ?? null,
                    'subject'           => $item['subject'] ?? null,
                    'category'          => $item['category'] ?? null,
                    'brand'             => $item['brand'] ?? null,
                    'is_supply'         => $item['isSupply'] ?? 0,
                    'is_realization'    => $item['isRealization'] ?? 0,
                    'is_cancel'         => $item['isCancel'] ?? 0,
                    'sticker'           => $item['sticker'] ?? null,
                    'srid'              => $item['srid'] ?? null,
                ], true)->execute();

                $count++;
            } catch (\Throwable $e) {
                // Одна проблемная запись (например, конфликт уникального индекса
                // или нарушение NOT NULL) больше не должна останавливать весь процесс.
                $errors++;
                $srid = $item['srid'] ?? 'unknown';
                $this->stderr("Ошибка сохранения srid {$srid}: " . $e->getMessage() . "\n", Console::FG_RED);
            }
        }

        return [$count, $errors];
    }

    /**
     * Сохранение заказов, полученных через /api/analytics/v1/order-feed.
     *
     * ВАЖНО: перед использованием в БД нужны новые колонки (order-feed отдаёт
     * поля, которых нет в текущей структуре wb_order) — выполните вручную:
     *
     *   ALTER TABLE `wb_order`
     *     ADD COLUMN `chrt_id` BIGINT NULL DEFAULT NULL
     *       COMMENT 'chrtId из order-feed (идентификатор размера)' AFTER `nm_id`,
     *     ADD COLUMN `status` VARCHAR(50) NULL DEFAULT NULL
     *       COMMENT 'status из order-feed (cancel, waiting, sold и т.п.)' AFTER `is_cancel`,
     *     ADD COLUMN `cancel_type` VARCHAR(50) NULL DEFAULT NULL
     *       COMMENT 'cancelType из order-feed' AFTER `status`,
     *     ADD COLUMN `is_mp` TINYINT(1) NULL DEFAULT NULL
     *       COMMENT 'isMp из order-feed' AFTER `cancel_type`,
     *     ADD COLUMN `is_b2b` TINYINT(1) NULL DEFAULT NULL
     *       COMMENT 'isB2b из order-feed' AFTER `is_mp`,
     *     ADD COLUMN `destination_city` VARCHAR(255) NULL DEFAULT NULL
     *       COMMENT 'destinationCity из order-feed (город/нас. пункт назначения)' AFTER `region_name`;
     *
     * warehouse_type/warehouse_name уже есть в таблице — колонку добавлять не нужно,
     * order-feed отдаёт их одной строкой (warehouseName), которую разбираем сами:
     * если строка начинается с "Склад продавца" — в warehouse_type пишем
     * "Склад продавца", а в warehouse_name — остаток строки (напр. "СЦ Смоленск 2").
     * Иначе в warehouse_type пишем "Склад WB", а в warehouse_name — строку целиком
     * (напр. "Рязань (Тюшевское)"). См. parseWarehouseName().
     *
     * МАППИНГ (проверен на реальном ответе order-feed от 2026-08-09 через
     * `wb-orders/test-order-feed` — см. пример ниже):
     *   - srid, nmId, chrtId, status, isMp, isB2b — ПОДТВЕРЖДЕНО, приходят как есть.
     *   - cancelType — в тестовом ответе отсутствовал (заказы были не отменены,
     *     status="created") — это ожидаемо, поле появляется только у отменённых
     *     заказов; код обрабатывает его отсутствие как null.
     *   - date              = createdAt
     *   - last_change_date  = updatedAt
     *   - is_cancel         = (status === 'cancel')
     *   - cancel_date       = updatedAt, если is_cancel (точной даты отмены API не отдаёт)
     *   - warehouse_name / warehouse_type — ПОДТВЕРЖДЕНО, но требуют разбора строки
     *                          warehouseName (см. parseWarehouseName()): если начинается
     *                          с "Склад продавца" — type="Склад продавца", name=остаток;
     *                          иначе — type="Склад WB", name=строка целиком.
     *   - destination_city  = destinationCity — ПОДТВЕРЖДЕНО, отдельная колонка
     *                          (город/нас. пункт назначения, напр. "Горячий Ключ").
     *                          Обновляется и при повторной загрузке существующего
     *                          по srid заказа (в отличие от остальных описательных
     *                          полей ниже).
     *   - oblast_okrug_name = destinationDistrict — ПОДТВЕРЖДЕНО (макрорегион назначения,
     *                          напр. "Южный и Северо-Кавказский", "Приволжский").
     *                          Раньше это (по ошибке) писали в region_name — теперь
     *                          region_name этим методом вообще не заполняется (нет
     *                          прямого аналога в order-feed).
     *   - price_with_disc   = sellerPrice (цена продавца, т.е. цена со скидкой продавца).
     *   - g_number: NOT NULL в БД, но order-feed его не отдаёт вовсе — подставляем
     *               srid как заглушку, но ТОЛЬКО при первой вставке.
     * Поля subject/category/brand/barcode/tech_size/supplier_article/spp/
     * discount_percent/total_price/finished_price/order_type/sale_id/
     * income_id/odid/sticker/is_supply/is_realization/country_name/
     * region_name этим методом не отдаются вовсе — не трогаем их.
     * Поле warehouseRegion (регион склада продавца) в ответе присутствует,
     * но под него нет подходящей колонки в wb_order — сейчас не сохраняется.
     *
     * ПОВЕДЕНИЕ ПРИ ПОВТОРЕ (заказ уже есть в БД по srid):
     * Обновляются ТОЛЬКО статус и новые поля этого метода —
     * last_change_date, status, cancel_type, is_cancel, cancel_date,
     * is_mp, is_b2b, chrt_id, destination_city, company_id. Описательные/
     * позиционные поля (date, nm_id, warehouse_type, warehouse_name,
     * oblast_okrug_name, price_with_disc, g_number) на апдейте НЕ трогаются,
     * чтобы не затирать более полные данные, ранее сохранённые через
     * supplier/orders.
     * При первой вставке (записи с таким srid ещё нет) сохраняются все
     * поля целиком.
     *
     * @return array [int $savedCount, int $errorCount]
     */
    private function processOrderFeedData($db, array $orders, int $companyId): array
    {
        $count = 0;
        $errors = 0;
        $isFirstRow = true; // печатаем реальный SQL только для первой записи — для наглядной проверки

        foreach ($orders as $item) {
            $srid = $item['srid'] ?? null;
            $createdAt = $item['createdAt'] ?? null;
            $updatedAt = $item['updatedAt'] ?? null;

            if (empty($srid) || empty($createdAt) || empty($updatedAt)) {
                $errors++;
                $missing = array_filter([
                    empty($srid) ? 'srid' : null,
                    empty($createdAt) ? 'createdAt' : null,
                    empty($updatedAt) ? 'updatedAt' : null,
                ]);
                $this->stderr("Пропущена запись order-feed: отсутствуют обязательные поля (" . implode(', ', $missing) . "). Данные: " . json_encode($item) . "\n", Console::FG_RED);
                continue;
            }

            $isCancel = ($item['status'] ?? null) === 'cancel';

            // Статус и "новые" поля — обновляются и на insert, и на update
            // существующей по srid записи.
            $statusFields = [
                'company_id'       => $companyId,
                'srid'             => (string)$srid,
                'last_change_date' => $this->toMysqlDateTime($updatedAt),
                'status'           => $item['status'] ?? null,
                'cancel_type'      => $item['cancelType'] ?? null,
                'is_cancel'        => $isCancel ? 1 : 0,
                'cancel_date'      => $isCancel ? $this->toMysqlDateTime($updatedAt) : null,
                'is_mp'            => array_key_exists('isMp', $item) ? (int)$item['isMp'] : null,
                'is_b2b'           => array_key_exists('isB2b', $item) ? (int)$item['isB2b'] : null,
                'chrt_id'          => $item['chrtId'] ?? null,
                'destination_city' => isset($item['destinationCity']) ? trim((string)$item['destinationCity']) : null,
            ];

            // Разбор строки warehouseName на тип склада и его название
            // (order-feed отдаёт их одной строкой) — см. parseWarehouseName().
            $rawWarehouseName = isset($item['warehouseName']) ? trim((string)$item['warehouseName']) : '';
            [$warehouseType, $warehouseName] = $this->parseWarehouseName($rawWarehouseName);

            // Описательные/позиционные поля — пишутся только при первой
            // вставке записи, на апдейт существующей по srid записи не влияют.
            $insertOnlyFields = [
                'date'              => $this->toMysqlDateTime($createdAt),
                'nm_id'             => $item['nmId'] ?? null,
                'warehouse_type'    => $warehouseType,
                'warehouse_name'    => $warehouseName,
                'oblast_okrug_name' => isset($item['destinationDistrict']) ? trim((string)$item['destinationDistrict']) : null,
                'price_with_disc'   => $item['sellerPrice'] ?? null,
                // g_number обязателен (NOT NULL), но этим методом не отдаётся —
                // на insert подставляем srid, чтобы не ловить ошибку целостности.
                'g_number'          => (string)$srid,
            ];

            try {
                $this->upsertOrderBySrid($db, (string)$srid, $statusFields, $insertOnlyFields, $isFirstRow);
                $isFirstRow = false;
                $count++;
            } catch (\Throwable $e) {
                $errors++;
                $this->stderr("Ошибка сохранения srid {$srid} (order-feed): " . $e->getMessage() . "\n", Console::FG_RED);
            }
        }

        return [$count, $errors];
    }

    /**
     * Upsert заказа в wb_order по уникальному ключу srid.
     *
     * Если запись с таким srid уже есть — обновляются ТОЛЬКО колонки,
     * переданные в $fields (плюс $insertOnlyFields не участвует в апдейте
     * вовсе), остальные ранее сохранённые значения не затираются.
     * Если записи нет — вставляется новая строка из $fields + $insertOnlyFields.
     *
     * @param array $fields          Колонки, которые нужно и на insert, и на update.
     * @param array $insertOnlyFields Колонки, нужные только для insert
     *                                (например, обязательные NOT NULL поля-заглушки,
     *                                которые не должны перезаписывать существующие значения).
     * @param bool  $debugSql        Если true — печатает в консоль реальный SQL перед выполнением
     *                                (для проверки, что в ON DUPLICATE KEY UPDATE попали только
     *                                нужные колонки).
     */
    private function upsertOrderBySrid($db, string $srid, array $fields, array $insertOnlyFields = [], bool $debugSql = false): void
    {
        $insertColumns = $fields + $insertOnlyFields;

        // Явно передаём $fields вторым (updateColumns) аргументом: при
        // конфликте по уникальному индексу idx_srid (srid) в ON DUPLICATE
        // KEY UPDATE попадут только колонки из $fields. $insertOnlyFields
        // используются лишь при первой вставке и на апдейт не влияют —
        // ранее сохранённые значения этих колонок не затираются.
        $command = $db->createCommand()->upsert('wb_order', $insertColumns, $fields);

        if ($debugSql) {
            $this->stdout("--- SQL upsert (для srid={$srid}, для проверки состава ON DUPLICATE KEY UPDATE) ---\n" . $command->getRawSql() . "\n", Console::FG_YELLOW);
        }

        $command->execute();
    }

    /**
     * Разбор строки warehouseName из order-feed на тип склада и его название.
     *
     * Если строка начинается с "Склад продавца" — возвращаем
     * ['Склад продавца', <остаток строки>], например:
     *   "Склад продавца СЦ Смоленск 2 " -> ['Склад продавца', 'СЦ Смоленск 2']
     * Иначе считаем это складом WB и возвращаем ['Склад WB', <строка целиком>]:
     *   "Рязань (Тюшевское)" -> ['Склад WB', 'Рязань (Тюшевское)']
     *
     * @return array{0: ?string, 1: ?string} [warehouseType, warehouseName]
     */
    private function parseWarehouseName(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [null, null];
        }

        $prefix = 'Склад продавца';
        if (mb_stripos($raw, $prefix) === 0) {
            $rest = trim(mb_substr($raw, mb_strlen($prefix)));
            return ['Склад продавца', $rest !== '' ? $rest : null];
        }

        return ['Склад WB', $raw];
    }

    /**
     * Преобразование ISO 8601 даты/времени (в т.ч. с offset, напр. +03:00)
     * в формат DATETIME для MySQL, без конвертации в другой часовой пояс.
     */
    private function toMysqlDateTime(string $isoDateTime): ?string
    {
        try {
            return (new \DateTime($isoDateTime))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}