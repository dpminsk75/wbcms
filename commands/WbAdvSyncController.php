<?php
namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\ArrayHelper;
use app\models\WbCampaign;
use app\models\WbCampaignItem;
use app\models\WbCampaignStats;

class WbAdvSyncController extends Controller
{
    private $client;
    private $token;

    public function init()
    {
        parent::init();
//        $this->token = Yii::$app->params['wbApiTokenContent'];
        $this->client = new \yii\httpclient\Client([
            'baseUrl' => 'https://advert-api.wildberries.ru',
            'requestConfig' => ['format' => \yii\httpclient\Client::FORMAT_JSON],
        ]);
    }
/*
public function actionIndex()
    {
        echo "1. Получение данных через /promotion/count...\n";
        
        $response = $this->client->get('/adv/v1/promotion/count', [], [
            'Authorization' => $this->token
        ])->send();

        if (!$response->isOk) {
            echo "Ошибка API. Код: " . $response->getStatusCode() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $allIds = [];

        // Разбираем структуру: adverts -> [ {type, status, advert_list}, ... ]
        if (isset($response->data['adverts']) && is_array($response->data['adverts'])) {
            foreach ($response->data['adverts'] as $group) {
                $type = $group['type'] ?? 0;
                $status = $group['status'] ?? 0;
                
                if (isset($group['advert_list']) && is_array($group['advert_list'])) {
                    foreach ($group['advert_list'] as $item) {
                        $advId = $item['advertId'];
                        $changeTime = $item['changeTime'];
                        
                        $allIds[] = $advId;

                        // Сохраняем/обновляем базовую инфу
                        $this->registerShortCampaign($advId, $type, $status, $changeTime);
                    }
                }
            }
        }

        echo "Итого обработано: " . count($allIds) . " кампаний.\n";

        if (!empty($allIds)) {
            $campaignIds = \app\models\WbCampaign::find()
                ->select('campaign_id')
                ->where(['>=', 'change_time', '2026-01-01 00:00:00'])
                ->orderBy(['change_time' => SORT_DESC])
                ->column();

            echo "\n2. Обогащение данными через /v2/adverts...\n";
            $this->syncCampaignDetails($campaignIds);

            echo "\n3. Загрузка статистики...\n";
            $this->syncStats($campaignIds);
        }

        return ExitCode::OK;
    }
*/

public function actionIndex()
    {
        $companies = (new \yii\db\Query())
            ->select(['id', 'name', 'api_key'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all();

        if (empty($companies)) {
            echo "Нет активных компаний.\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $this->token = $company['api_key'] ?? null;

            if (!$this->token) {
                echo "Пропуск {$company['name']} - нет токена.\n";
                continue;
            }

            echo "\n=== КОМПАНИЯ: {$company['name']} ===\n";
            echo "1. Получение данных через /promotion/count...\n";
            
            $response = $this->client->get('/adv/v1/promotion/count', [], [
                'Authorization' => $this->token
            ])->send();

            if (!$response->isOk) {
                echo "Ошибка API. Код: " . $response->getStatusCode() . "\n";
                continue;
            }

            $allIds = [];

            if (isset($response->data['adverts']) && is_array($response->data['adverts'])) {
                foreach ($response->data['adverts'] as $group) {
                    $type = $group['type'] ?? 0;
                    $status = $group['status'] ?? 0;
                    
                    if (isset($group['advert_list']) && is_array($group['advert_list'])) {
                        foreach ($group['advert_list'] as $item) {
                            $advId = $item['advertId'];
                            $changeTime = $item['changeTime'];
                            
                            $allIds[] = $advId;
                            $this->registerShortCampaign($advId, $type, $status, $changeTime, $companyId);
                        }
                    }
                }
            }

            echo "Итого обработано: " . count($allIds) . " кампаний.\n";

            if (!empty($allIds)) {
                $campaignIds = \app\models\WbCampaign::find()
                    ->select('campaign_id')
                    ->where(['company_id' => $companyId])
                    ->andWhere(['>=', 'change_time', '2026-01-01 00:00:00'])
                    ->orderBy(['change_time' => SORT_DESC])
                    ->column();

                echo "\n2. Обогащение данными через /v2/adverts...\n";
                $this->syncCampaignDetails($campaignIds, $companyId);

                echo "\n3. Загрузка статистики...\n";
                $this->syncStats($campaignIds, $companyId);
            }
        }

        return ExitCode::OK;
    }

/**
 * Синхронизация поисковых запросов
 * Запуск: php yii wb-adv-sync/queries 2026-01-01 2026-01-07
 */
/*
    public function actionQueries($from = null, $to = null)
    {
        // Если даты не переданы, берем вчерашний день (самый надежный для v0)
        $dateFrom = $from ?: date('Y-m-d', strtotime('-1 day'));
        $dateTo = $to ?: $dateFrom;

        echo "Запуск синхронизации поисковых запросов за период: $dateFrom - $dateTo\n";

        // Получаем те же активные кампании, что и в основном экшене
        $campaignIds = \app\models\WbCampaign::find()
            ->select('campaign_id')
            ->where(['>=', 'change_time', '2026-01-01 00:00:00'])
            ->column();

        if (empty($campaignIds)) {
            echo "Нет подходящих кампаний.\n";
            return ExitCode::OK;
        }

        // Вызываем нашу функцию
        $this->syncQueries($campaignIds, $dateFrom, $dateTo);

        echo "Синхронизация запросов завершена.\n";
        return ExitCode::OK;
    }
*/
    public function actionQueries($from = null, $to = null)
    {
        $dateFrom = $from ?: date('Y-m-d', strtotime('-1 day'));
        $dateTo = $to ?: $dateFrom;

        echo "Запуск синхронизации поисковых запросов за период: $dateFrom - $dateTo\n";

        $companies = (new \yii\db\Query())->select(['id', 'name', 'api_key'])->from('companies')->where(['is_active' => 1])->all();

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $this->token = $company['api_key'] ?? null;
            if (!$this->token) continue;

            echo "\n=== КОМПАНИЯ: {$company['name']} ===\n";

            $campaignIds = \app\models\WbCampaign::find()
                ->select('campaign_id')
                ->where(['company_id' => $companyId])
                ->andWhere(['>=', 'change_time', '2026-01-01 00:00:00'])
                ->column();

            if (empty($campaignIds)) {
                echo "Нет подходящих кампаний.\n";
                continue;
            }

            $this->syncQueries($campaignIds, $dateFrom, $dateTo, $companyId);
        }

        echo "Синхронизация запросов завершена.\n";
        return ExitCode::OK;
    }

    /**
     * Отдельная загрузка статистики без похода в /promotion/count и /v2/adverts.
     * Список кампаний берётся из БД (как и в actionIndex).
     * Запуск: php yii wb-adv-sync/stats 2026-07-24 2026-07-25
     * Без параметров - последние 3 дня (как в actionIndex).
     */
    public function actionStats($from = null, $to = null)
    {
        $beginDate = $from ?: date('Y-m-d', strtotime('-3 days'));
        $endDate = $to ?: date('Y-m-d');

        echo "Запуск загрузки статистики за период: $beginDate - $endDate\n";

        $companies = (new \yii\db\Query())->select(['id', 'name', 'api_key'])->from('companies')->where(['is_active' => 1])->all();

        if (empty($companies)) {
            echo "Нет активных компаний.\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($companies as $company) {
            $companyId = $company['id'];
            $this->token = $company['api_key'] ?? null;
            if (!$this->token) {
                echo "Пропуск {$company['name']} - нет токена.\n";
                continue;
            }

            echo "\n=== КОМПАНИЯ: {$company['name']} ===\n";

            $campaignIds = \app\models\WbCampaign::find()
                ->select('campaign_id')
                ->where(['company_id' => $companyId])
                ->andWhere(['>=', 'change_time', '2026-01-01 00:00:00'])
                ->orderBy(['change_time' => SORT_DESC])
                ->column();

            if (empty($campaignIds)) {
                echo "Нет подходящих кампаний.\n";
                continue;
            }

            $this->syncStats($campaignIds, $companyId, $beginDate, $endDate);
        }

        echo "Загрузка статистики завершена.\n";
        return ExitCode::OK;
    }

    /**
     * Сохраняет 4 базовых поля: type, status, advertId и changeTime
     */
/*
    protected function registerShortCampaign($id, $type, $status, $changeTime)
    {
        $model = \app\models\WbCampaign::findOne(['campaign_id' => $id]) 
                 ?: new \app\models\WbCampaign();
        
        $model->campaign_id = $id;
        $model->type = $type;
        $model->status = $status;
        // WB присылает дату в ISO 8601 (например, 2024-05-20T10:00:00Z)
        $model->change_time = date('Y-m-d H:i:s', strtotime($changeTime));
        
        if ($model->isNewRecord) {
            $model->name = 'New Campaign ' . $id; // Временное имя
        }

        if (!$model->save()) {
            echo "Ошибка регистрации ID $id: " . json_encode($model->getErrors()) . "\n";
        }
    }

    protected function registerCampaigns($ids)
    {
        foreach ($ids as $id) {
            $model = \app\models\WbCampaign::findOne(['campaign_id' => $id]) 
                     ?: new \app\models\WbCampaign();
            
            if ($model->isNewRecord) {
                $model->campaign_id = $id;
                $model->name = 'Загрузка...'; // Временное имя
                $model->type = 0;             // Тип обновится в следующем шаге
                $model->status = 0;
                $model->save(false); // Сохраняем без валидации для скорости
            }
        }
    }
*/

    protected function registerShortCampaign($id, $type, $status, $changeTime, $companyId)
    {
        $model = \app\models\WbCampaign::findOne(['campaign_id' => $id, 'company_id' => $companyId]) 
                 ?: new \app\models\WbCampaign();
        
        $model->campaign_id = $id;
        $model->company_id = $companyId;
        $model->type = $type;
        $model->status = $status;
        $model->change_time = date('Y-m-d H:i:s', strtotime($changeTime));
        
        if ($model->isNewRecord) {
            $model->name = 'New Campaign ' . $id;
        }

        if (!$model->save()) {
            echo "Ошибка регистрации ID $id: " . json_encode($model->getErrors()) . "\n";
        }
    }

    protected function registerCampaigns($ids, $companyId)
    {
        foreach ($ids as $id) {
            $model = \app\models\WbCampaign::findOne(['campaign_id' => $id, 'company_id' => $companyId]) 
                     ?: new \app\models\WbCampaign();
            
            if ($model->isNewRecord) {
                $model->campaign_id = $id;
                $model->company_id = $companyId;
                $model->name = 'Загрузка...';
                $model->type = 0;
                $model->status = 0;
                $model->save(false);
            }
        }
    }
/*
protected function syncCampaignDetails($ids)
{
    $chunks = array_chunk($ids, 50);

    foreach ($chunks as $chunk) {
        $response = $this->client->get('/api/advert/v2/adverts', ['id' => $chunk], [
            'Authorization' => $this->token,
        ])->send();

        if (!$response->isOk) {
            echo "Ошибка v2/adverts: " . $response->getStatusCode() . "\n";
            continue;
        }

        $adverts = $response->data['adverts'] ?? [];

        foreach ($adverts as $adData) {
            $advId = $adData['id'] ?? null;
            if (!$advId) continue;

            // Используем твое название модели WbCampaign
            $model = \app\models\WbCampaign::findOne(['campaign_id' => $advId]);
            if (!$model) {
                $model = new \app\models\WbCampaign();
                $model->campaign_id = $advId;
            }

            // Название кампании из settings -> name
            $model->name = $adData['settings']['name'] ?? 'Без названия';
            
            if ($model->save()) {
                echo "Кампания {$advId} обновлена: {$model->name}\n";
                // Передаем данные для обновления вложенных товаров
                $this->updateCampaignItems($advId, $adData);
            } else {
                echo "Ошибка сохранения WbCampaign {$advId}: " . json_encode($model->getErrors()) . "\n";
            }
        }
        usleep(200000);
    }
}


protected function updateCampaignItems($campaignId, $adData)
{
    if (empty($adData['nm_settings']) || !is_array($adData['nm_settings'])) {
        return;
    }

    $count = 0;
    foreach ($adData['nm_settings'] as $nmItem) {
        $nmId = $nmItem['nm_id'] ?? null;
        
        if (!$nmId) continue;

        // Используем твое название модели WbCampaignItems
        $item = \app\models\WbCampaignItems::findOne([
            'campaign_id' => $campaignId, 
            'nm_id' => $nmId
        ]) ?: new \app\models\WbCampaignItems();

        $item->campaign_id = $campaignId;
        $item->nm_id = (int)$nmId;
        
        // Берем категорию товара как временное имя
        if (isset($nmItem['subject']['name'])) {
            $item->name = $nmItem['subject']['name'];
        }

        if ($item->save()) {
            $count++;
        } else {
            echo "    [!] Ошибка WbCampaignItems для NM $nmId: " . json_encode($item->getErrors()) . "\n";
        }
    }
    echo "  -- Товаров в базе: $count\n";
}
*/

    protected function syncCampaignDetails($ids, $companyId)
    {
        $chunks = array_chunk($ids, 50);

        foreach ($chunks as $chunk) {
            $response = $this->client->get('/api/advert/v2/adverts', ['id' => $chunk], [
                'Authorization' => $this->token,
            ])->send();

            if (!$response->isOk) continue;

            $adverts = $response->data['adverts'] ?? [];

            foreach ($adverts as $adData) {
                $advId = $adData['id'] ?? null;
                if (!$advId) continue;

                $model = \app\models\WbCampaign::findOne(['campaign_id' => $advId, 'company_id' => $companyId]);
                if (!$model) {
                    $model = new \app\models\WbCampaign();
                    $model->campaign_id = $advId;
                    $model->company_id = $companyId;
                }

                $model->name = $adData['settings']['name'] ?? 'Без названия';
                
                if ($model->save()) {
                    echo "Кампания {$advId} обновлена: {$model->name}\n";
                    $this->updateCampaignItems($advId, $adData, $companyId);
                }
            }
            usleep(200000);
        }
    }

    protected function updateCampaignItems($campaignId, $adData, $companyId)
    {
        if (empty($adData['nm_settings']) || !is_array($adData['nm_settings'])) return;

        $count = 0;
        foreach ($adData['nm_settings'] as $nmItem) {
            $nmId = $nmItem['nm_id'] ?? null;
            if (!$nmId) continue;

            $item = \app\models\WbCampaignItems::findOne([
                'campaign_id' => $campaignId, 
                'nm_id' => $nmId,
                'company_id' => $companyId
            ]) ?: new \app\models\WbCampaignItems();

            $item->campaign_id = $campaignId;
            $item->company_id = $companyId;
            $item->nm_id = (int)$nmId;
            
            if (isset($nmItem['subject']['name'])) {
                $item->name = $nmItem['subject']['name'];
            }

            if ($item->save()) $count++;
        }
        echo "  -- Товаров в базе: $count\n";
    }

// var_dump($response->data);

//protected function syncStats($ids)
protected function syncStats($ids, $companyId, $beginDate = null, $endDate = null)
{
    $beginDate = $beginDate ?: date('Y-m-d', strtotime('-3 days'));
    $endDate = $endDate ?: date('Y-m-d');

//    $beginDate= date('Y-m-d', strtotime('-30 days'));
//    $endDate= date('Y-m-d', strtotime('-10 days'));

    echo "Загрузка статистики v3 (Ids: " . count($ids) . ")... \n";
    echo " Период: $beginDate - $endDate\n";

    $chunks = array_chunk($ids, 50);
    $totalChunks = count($chunks);

    foreach ($chunks as $index => $chunk) {
        $idsString = implode(',', $chunk);
        
        // Формируем URL вручную, чтобы контролировать каждый символ
        // WB иногда плохо переваривает url-encoded запятые (%2C)
        $url = "/adv/v3/fullstats?ids={$idsString}&beginDate={$beginDate}&endDate={$endDate}";

        echo $url."\n";

        $response = $this->client->get($url, [], [
            'Authorization' => $this->token,
            'Accept' => 'application/json',
        ])->send();

        echo "[" . ($index + 1) . "/$totalChunks] Статус: " . $response->getStatusCode() . "\n";

        if (!$response->isOk) {
            echo "Ошибка: " . ($response->data['detail'] ?? json_encode($response->data)) . "\n";
            // Если всё еще пишет 'ids is required', значит WB ждет массив ?ids=1&ids=2
            if (strpos(json_encode($response->data), 'ids') !== false) {
                 echo "Пробую альтернативный формат (массив ids)... \n";
                 $response = $this->client->get('/adv/v3/fullstats', [
                     'ids' => $chunk, // HttpClient сделает ids=1&ids=2
                     'beginDate' => $beginDate,
                     'endDate' => $endDate
                 ], ['Authorization' => $this->token])->send();
                 echo "Новый статус: " . $response->getStatusCode() . "\n";
            }
        }

//        var_dump($response->data);

        if ($response->isOk && !empty($response->data)) {
//            $this->parseAndSaveStats($response->data);
            $this->parseAndSaveStats($response->data, $companyId);
        }

//        sleep(21);

        if ($index + 1 < $totalChunks) {
            echo "Пауза 21 сек...\n";
            sleep(21);
        }
    }
}


/*
protected function parseAndSaveStats($data)
{
    $db = Yii::$app->db;
    $savedApps = 0;
    $savedNms = 0;

    foreach ($data as $campaignStat) {
        $cId = $campaignStat['advertId'] ?? null;
        if (!$cId || empty($campaignStat['days'])) continue;

        foreach ($campaignStat['days'] as $day) {
            $date = date('Y-m-d', strtotime($day['date']));

            if (!empty($day['apps'])) {
                foreach ($day['apps'] as $app) {
                    $appType = (string)($app['appType'] ?? '0');

                    // 1. Сохраняем общую статистику по приложению (родитель)
                    // Важно: так как у вас UNIQUE KEY (campaign_id, date, nm_id, app_type),
                    // а в apps верхнего уровня нет конкретного nm_id, мы возьмем nmId из первого элемента nms
                    // или поставим 0, если nms пуст.
                    $firstNmId = !empty($app['nms']) ? $app['nms'][0]['nmId'] : 0;

                    $appAttributes = [
                        'campaign_id' => $cId,
                        'date'        => $date,
//                        'nm_id'       => $firstNmId, 
                        'nm_id'       => 0, 
                        'app_type'    => $appType,
                        'atbs'        => (int)($app['atbs'] ?? 0),
                        'canceled'    => (int)($app['canceled'] ?? 0),
                        'clicks'      => (int)($app['clicks'] ?? 0),
                        'cpc'         => (int)($app['cpc'] ?? 0),
                        'cr'          => (int)($app['cr'] ?? 0),
                        'ctr'         => (int)($app['ctr'] ?? 0),

                        'orders'      => (int)($app['orders'] ?? 0),
                        'shks'        => (int)($app['shks'] ?? 0),
                        'sum'         => (float)($app['sum'] ?? 0),
                        'sum_price'   => (float)($app['sum_price'] ?? 0),
                        'views'       => (int)($app['views'] ?? 0),
                    ];

                    $db->createCommand()->upsert('wb_campaign_stats', $appAttributes, [
                        'atbs'     => new \yii\db\Expression('VALUES(atbs)'),
                        'canceled' => new \yii\db\Expression('VALUES(canceled)'),
                        'clicks'   => new \yii\db\Expression('VALUES(clicks)'),
                        'cpc'      => new \yii\db\Expression('VALUES(cpc)'),
                        'cr'       => new \yii\db\Expression('VALUES(cr)'),
                        'ctr'      => new \yii\db\Expression('VALUES(ctr)'),

                        'orders'    => new \yii\db\Expression('VALUES(orders)'),
                        'shks'      => new \yii\db\Expression('VALUES(shks)'),
                        'sum'       => new \yii\db\Expression('VALUES(sum)'),
                        'sum_price' => new \yii\db\Expression('VALUES(sum_price)'),
                        'views'     => new \yii\db\Expression('VALUES(views)'),
                    ])->execute();

                    // Получаем ID родительской записи для связи
                    $parentId = $db->createCommand("
                        SELECT id FROM wb_campaign_stats 
                        WHERE campaign_id = :c AND date = :d AND app_type = :a
                    ", [
                        ':c' => $cId, 
                        ':d' => $date, 
                        ':a' => $appType
                    ])->queryScalar();

                    if (!$parentId) continue;
                    $savedApps++;

                    // 2. Сохраняем детализацию по товарам (дочерняя таблица)
                    if (!empty($app['nms'])) {
                        foreach ($app['nms'] as $nm) {
                            $nmId = $nm['nmId'] ?? null;
                            if (!$nmId) continue;

                            $nmAttributes = [
                                'parent_id' => $parentId,
                                'nm_id'     => (int)$nmId,
                                'name'      => (string)($nm['name'] ?? ''),
                                'views'     => (int)($nm['views'] ?? 0),
                                'clicks'    => (int)($nm['clicks'] ?? 0),
                                'atbs'      => (int)($nm['atbs'] ?? 0),
                                'orders'    => (int)($nm['orders'] ?? 0),
                                'shks'      => (int)($nm['shks'] ?? 0),
                                'sum'       => (float)($nm['sum'] ?? 0),
                                'sum_price' => (float)($nm['sum_price'] ?? 0),
                                'canceled'  => (int)($nm['canceled'] ?? 0),
                            ];

                            $db->createCommand()->upsert('wb_campaign_stats_nms', $nmAttributes, [
                                'name'      => new \yii\db\Expression('VALUES(name)'),
                                'views'     => new \yii\db\Expression('VALUES(views)'),
                                'clicks'    => new \yii\db\Expression('VALUES(clicks)'),
                                'atbs'      => new \yii\db\Expression('VALUES(atbs)'),
                                'orders'    => new \yii\db\Expression('VALUES(orders)'),
                                'shks'      => new \yii\db\Expression('VALUES(shks)'),
                                'sum'       => new \yii\db\Expression('VALUES(sum)'),
                                'sum_price' => new \yii\db\Expression('VALUES(sum_price)'),
                                'canceled'  => new \yii\db\Expression('VALUES(canceled)'),
                            ])->execute();
                            
                            $savedNms++;
                        }
                    }
                }
            }
        }
        echo "Кампания $cId: сохранена.\n";
    }
    echo "--- Готово! Записей в родительской: $savedApps, Детальных (NMS): $savedNms ---\n";
}
*/
/*
    protected function parseAndSaveStats($data, $companyId)
    {
        $db = Yii::$app->db;
        $savedApps = 0;
        $savedNms = 0;

        foreach ($data as $campaignStat) {
            $cId = $campaignStat['advertId'] ?? null;
            if (!$cId || empty($campaignStat['days'])) continue;

            foreach ($campaignStat['days'] as $day) {
                $date = date('Y-m-d', strtotime($day['date']));

                if (!empty($day['apps'])) {
                    foreach ($day['apps'] as $app) {
                        $appType = (string)($app['appType'] ?? '0');

                        $appAttributes = [
                            'campaign_id' => $cId,
                            'company_id'  => $companyId, // <--- Добавили
                            'date'        => $date,
                            'nm_id'       => 0, 
                            'app_type'    => $appType,
                            'atbs'        => (int)($app['atbs'] ?? 0),
                            'canceled'    => (int)($app['canceled'] ?? 0),
                            'clicks'      => (int)($app['clicks'] ?? 0),
                            'cpc'         => (int)($app['cpc'] ?? 0),
                            'cr'          => (int)($app['cr'] ?? 0),
                            'ctr'         => (int)($app['ctr'] ?? 0),
                            'orders'      => (int)($app['orders'] ?? 0),
                            'shks'        => (int)($app['shks'] ?? 0),
                            'sum'         => (float)($app['sum'] ?? 0),
                            'sum_price'   => (float)($app['sum_price'] ?? 0),
                            'views'       => (int)($app['views'] ?? 0),
                        ];

                        $db->createCommand()->upsert('wb_campaign_stats', $appAttributes, [
                            'atbs'     => new \yii\db\Expression('VALUES(atbs)'),
                            'canceled' => new \yii\db\Expression('VALUES(canceled)'),
                            'clicks'   => new \yii\db\Expression('VALUES(clicks)'),
                            'cpc'      => new \yii\db\Expression('VALUES(cpc)'),
                            'cr'       => new \yii\db\Expression('VALUES(cr)'),
                            'ctr'      => new \yii\db\Expression('VALUES(ctr)'),
                            'orders'   => new \yii\db\Expression('VALUES(orders)'),
                            'shks'     => new \yii\db\Expression('VALUES(shks)'),
                            'sum'      => new \yii\db\Expression('VALUES(sum)'),
                            'sum_price'=> new \yii\db\Expression('VALUES(sum_price)'),
                            'views'    => new \yii\db\Expression('VALUES(views)'),
                        ])->execute();

                        // Ищем с учетом company_id
                        $parentId = $db->createCommand("
                            SELECT id FROM wb_campaign_stats 
                            WHERE campaign_id = :c AND date = :d AND app_type = :a AND company_id = :comp
                        ", [
                            ':c' => $cId, 
                            ':d' => $date, 
                            ':a' => $appType,
                            ':comp' => $companyId
                        ])->queryScalar();

                        if (!$parentId) continue;
                        $savedApps++;

                        // 2. Детализация по товарам (оставляем как есть, parent_id достаточно)
                        if (!empty($app['nms'])) {
                            foreach ($app['nms'] as $nm) {
                                $nmId = $nm['nmId'] ?? null;
                                if (!$nmId) continue;

                                $nmAttributes = [
                                    'parent_id' => $parentId,
                                    'nm_id'     => (int)$nmId,
                                    'name'      => (string)($nm['name'] ?? ''),
                                    'views'     => (int)($nm['views'] ?? 0),
                                    'clicks'    => (int)($nm['clicks'] ?? 0),
                                    'atbs'      => (int)($nm['atbs'] ?? 0),
                                    'orders'    => (int)($nm['orders'] ?? 0),
                                    'shks'      => (int)($nm['shks'] ?? 0),
                                    'sum'       => (float)($nm['sum'] ?? 0),
                                    'sum_price' => (float)($nm['sum_price'] ?? 0),
                                    'canceled'  => (int)($nm['canceled'] ?? 0),
                                ];

                                $db->createCommand()->upsert('wb_campaign_stats_nms', $nmAttributes, [
                                    'name'      => new \yii\db\Expression('VALUES(name)'),
                                    'views'     => new \yii\db\Expression('VALUES(views)'),
                                    'clicks'    => new \yii\db\Expression('VALUES(clicks)'),
                                    'atbs'      => new \yii\db\Expression('VALUES(atbs)'),
                                    'orders'    => new \yii\db\Expression('VALUES(orders)'),
                                    'shks'      => new \yii\db\Expression('VALUES(shks)'),
                                    'sum'       => new \yii\db\Expression('VALUES(sum)'),
                                    'sum_price' => new \yii\db\Expression('VALUES(sum_price)'),
                                    'canceled'  => new \yii\db\Expression('VALUES(canceled)'),
                                ])->execute();
                                
                                $savedNms++;
                            }
                        }
                    }
                }
            }
        }
    }
*/
protected function parseAndSaveStats($data, $companyId)
{
    $db = Yii::$app->db;
    $savedApps = 0;
    $savedNms = 0;

    foreach ($data as $campaignStat) {
        $cId = $campaignStat['advertId'] ?? null;
        if (!$cId || empty($campaignStat['days'])) continue;

        foreach ($campaignStat['days'] as $day) {
            $date = date('Y-m-d', strtotime($day['date']));

            if (!empty($day['apps'])) {
                foreach ($day['apps'] as $app) {
                    $appType = (string)($app['appType'] ?? '0');

                    // Сохраняем родительскую запись
                    $appAttributes = [
                        'campaign_id' => $cId,
                        'company_id'  => $companyId,
                        'date'        => $date,
                        'nm_id'       => 0, 
                        'app_type'    => $appType,
                        'views'       => (int)($app['views'] ?? 0),
                        'clicks'      => (int)($app['clicks'] ?? 0),
                        'ctr'         => (float)($app['ctr'] ?? 0),
                        'cpc'         => (float)($app['cpc'] ?? 0),
                        'cr'          => (float)($app['cr'] ?? 0),
                        'atbs'        => (int)($app['atbs'] ?? 0),
                        'orders'      => (int)($app['orders'] ?? 0),
                        'canceled'    => (int)($app['canceled'] ?? 0),
                        'shks'        => (int)($app['shks'] ?? 0),
                        'sum'         => (float)($app['sum'] ?? 0),
                        'sum_price'   => (float)($app['sum_price'] ?? 0),
                    ];

                    // Используем INSERT ... ON DUPLICATE KEY UPDATE
                    $columns = array_keys($appAttributes);

                    // Именованные плейсхолдеры вместо "?": так yii\db\Command
                    // сможет корректно вызвать PDOStatement::bindValue() по имени,
                    // а не по числовому индексу (который PDO требует начинать с 1).
                    $params = [];
                    $placeholders = [];
                    foreach ($appAttributes as $key => $value) {
                        $ph = ':' . $key;
                        $placeholders[] = $ph;
                        $params[$ph] = $value;
                    }
                    $placeholders = implode(',', $placeholders);

                    $updateFields = [];
                    foreach ($appAttributes as $key => $value) {
                        if (!in_array($key, ['campaign_id', 'company_id', 'date', 'app_type', 'nm_id'])) {
                            $updateFields[] = "`$key` = VALUES(`$key`)";
                        }
                    }
                    $updateSql = implode(', ', $updateFields);

                    $sql = "INSERT INTO wb_campaign_stats (" . implode(',', array_map(function($col) { 
                        return "`$col`"; 
                    }, $columns)) . ") 
                            VALUES ($placeholders) 
                            ON DUPLICATE KEY UPDATE $updateSql";

                    $db->createCommand($sql, $params)->execute();

                    // Получаем parent_id
                    $parentId = $db->createCommand("
                        SELECT id FROM wb_campaign_stats 
                        WHERE campaign_id = :c 
                          AND date = :d 
                          AND app_type = :a 
                          AND company_id = :comp
                    ", [
                        ':c' => $cId, 
                        ':d' => $date, 
                        ':a' => $appType,
                        ':comp' => $companyId
                    ])->queryScalar();

                    if (!$parentId) continue;
                    $savedApps++;

                    // Сохраняем товары
                    if (!empty($app['nms'])) {
                        foreach ($app['nms'] as $nm) {
                            $nmId = $nm['nmId'] ?? null;
                            if (!$nmId) continue;

                            $nmAttributes = [
                                'company_id' => $companyId,
                                'parent_id'  => $parentId,
                                'nm_id'      => (int)$nmId,
                                'name'       => (string)($nm['name'] ?? ''),
                                'views'      => (int)($nm['views'] ?? 0),
                                'clicks'     => (int)($nm['clicks'] ?? 0),
                                'atbs'       => (int)($nm['atbs'] ?? 0),
                                'orders'     => (int)($nm['orders'] ?? 0),
                                'shks'       => (int)($nm['shks'] ?? 0),
                                'sum'        => (float)($nm['sum'] ?? 0),
                                'sum_price'  => (float)($nm['sum_price'] ?? 0),
                                'canceled'   => (int)($nm['canceled'] ?? 0),
                            ];

                            // Аналогично для дочерней таблицы
                            $columnsNms = array_keys($nmAttributes);

                            // Именованные плейсхолдеры вместо "?" (см. фикс выше по той же причине)
                            $paramsNms = [];
                            $placeholdersNms = [];
                            foreach ($nmAttributes as $key => $value) {
                                $ph = ':' . $key;
                                $placeholdersNms[] = $ph;
                                $paramsNms[$ph] = $value;
                            }
                            $placeholdersNms = implode(',', $placeholdersNms);

                            $updateFieldsNms = [];
                            foreach ($nmAttributes as $key => $value) {
                                if (!in_array($key, ['company_id', 'parent_id', 'nm_id'])) {
                                    $updateFieldsNms[] = "`$key` = VALUES(`$key`)";
                                }
                            }
                            $updateSqlNms = implode(', ', $updateFieldsNms);

                            $sqlNms = "INSERT INTO wb_campaign_stats_nms (" . implode(',', array_map(function($col) { 
                                return "`$col`"; 
                            }, $columnsNms)) . ") 
                                    VALUES ($placeholdersNms) 
                                    ON DUPLICATE KEY UPDATE $updateSqlNms";

                            $db->createCommand($sqlNms, $paramsNms)->execute();
                            $savedNms++;
                        }
                    }
                }
            }
        }
    }
    
    echo "--- Готово! Родительских: $savedApps, Детальных: $savedNms ---\n";
}

    
//protected function syncQueries($campaignIds, $dateFrom, $dateTo)
protected function syncQueries($campaignIds, $dateFrom, $dateTo, $companyId)
{
    echo "Загрузка поисковых запросов батчами по 100...\n";

    $period = new \DatePeriod(
        new \DateTime($dateFrom),
        new \DateInterval('P1D'),
        (new \DateTime($dateTo))->modify('+1 day')
    );

    foreach ($period as $dt) {
        $currentDate = $dt->format("Y-m-d");
        echo "\n>>> ДАТА: $currentDate\n";

        // Собираем активные товары (таблица wb_campaign)
/*
        $activeItems = \app\models\WbCampaignItems::find()
            ->alias('i')
            ->select(['i.campaign_id', 'i.nm_id'])
            ->innerJoin(['c' => 'wb_campaign'], 'c.campaign_id = i.campaign_id')
            ->where(['in', 'i.campaign_id', $campaignIds])
            ->andWhere(['>', 'c.change_time', '2025-12-01 00:00:00'])
            ->asArray()
            ->all();
*/
        $activeItems = \app\models\WbCampaignItems::find()
            ->alias('i')
            ->select(['i.campaign_id', 'i.nm_id'])
            ->innerJoin(['c' => 'wb_campaign'], 'c.campaign_id = i.campaign_id AND c.company_id = :cid')
            ->addParams([':cid' => $companyId]) // <--- Прокидываем компанию
            ->where(['in', 'i.campaign_id', $campaignIds])
            ->andWhere(['>', 'c.change_time', '2025-12-01 00:00:00'])
            ->asArray()
            ->all();

        if (empty($activeItems)) {
            echo "Нет активных товаров за эту дату.\n";
            continue;
        }

        $itemsPayload = [];
        foreach ($activeItems as $item) {
            $itemsPayload[] = [
                'advert_id' => (int)$item['campaign_id'],
                'nm_id' => (int)$item['nm_id']
            ];
        }

        $chunks = array_chunk($itemsPayload, 100);
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $startTime = microtime(true);
            echo "[" . date('H:i:s') . "] Чанк " . ($index + 1) . "/$totalChunks отправка...";

            $payload = [
                'from' => $currentDate,
                'to' => $currentDate,
                'items' => $chunk
            ];

            $response = $this->client->post('/adv/v0/normquery/stats', 
                json_encode($payload),
                [
                    'Authorization' => $this->token,
                    'Content-Type' => 'application/json',
                ]
            )->send();

            if ($response->isOk && !empty($response->data)) {
//                $this->parseQueryBatch($response->data, $currentDate);
                $this->parseQueryBatch($response->data, $currentDate, $companyId);
            } else {
                echo " | Ошибка API: " . $response->getStatusCode() . " " . $response->content;
            }

            // ПАУЗА ДОЛЖНА БЫТЬ ЗДЕСЬ - БЕЗ УСЛОВИЙ
            // Чтобы между датами тоже было 6 секунд
            echo " | Пауза 6 сек...";
            for ($i = 6; $i > 0; $i--) {
                echo " $i..";
                if (php_sapi_name() === 'cli') {
                    @ob_flush();
                    flush();
                }
                sleep(1);
            }
            echo " OK\n";

        }
    }
}

/*
protected function parseQueryBatch($data, $currentDate)
{
    $db = Yii::$app->db;
    $totalSaved = 0;
    
    $items = $data['stats'] ?? [];
    
    if (empty($items)) {
        echo " [!] В ответе пустой stats.\n";
        return;
    }

    foreach ($items as $advertItem) {
        $cId = $advertItem['advert_id'] ?? null;
        $nmId = $advertItem['nm_id'] ?? null;
        $queries = $advertItem['stats'] ?? [];

        if (!$cId || !$nmId || empty($queries)) {
            continue;
        }

        echo "\n    * Camp: $cId, NM: $nmId (Фраз: " . count($queries) . ")";

        foreach ($queries as $qStat) {
            $queryText = $qStat['norm_query'] ?? null;
            if (!$queryText) continue;

            $views = (int)($qStat['views'] ?? 0);
            $clicks = (int)($qStat['clicks'] ?? 0);
            
            // Считаем CTR для сохранения
            $ctr = $views > 0 ? round(($clicks / $views) * 100, 2) : 0;

            $attributes = [
                'campaign_id' => (int)$cId,
                'nm_id'       => (int)$nmId,
                'date'        => $currentDate,
                'query'       => (string)$queryText,
                'views'       => $views,
                'clicks'      => $clicks,
                'ctr'         => $ctr,
                'sum'         => (float)($qStat['spend'] ?? 0),
                'atbs'        => (int)($qStat['atbs'] ?? 0),
                'orders'      => (int)($qStat['orders'] ?? 0),
                'shks'        => (int)($qStat['shks'] ?? 0),
            ];

            // Выполняем быстрый UPSERT
            $result = $db->createCommand()->upsert('wb_campaign_query', $attributes, [
                'views'  => new \yii\db\Expression('VALUES(views)'),
                'clicks' => new \yii\db\Expression('VALUES(clicks)'),
                'ctr'    => new \yii\db\Expression('VALUES(ctr)'),
                'sum'    => new \yii\db\Expression('VALUES(sum)'),
                'atbs'   => new \yii\db\Expression('VALUES(atbs)'),
                'orders' => new \yii\db\Expression('VALUES(orders)'),
                'shks'   => new \yii\db\Expression('VALUES(shks)'),
            ])->execute();

            if ($result) {
                $totalSaved++;
            }
        }
    }
    echo "\n  >>> Итог чанка: обработано $totalSaved фраз\n";
}
*/

protected function parseQueryBatch($data, $currentDate, $companyId)
    {
        $db = Yii::$app->db;
        $totalSaved = 0;
        
        $items = $data['stats'] ?? [];
        
        if (empty($items)) return;

        foreach ($items as $advertItem) {
            $cId = $advertItem['advert_id'] ?? null;
            $nmId = $advertItem['nm_id'] ?? null;
            $queries = $advertItem['stats'] ?? [];

            if (!$cId || !$nmId || empty($queries)) continue;

            foreach ($queries as $qStat) {
                $queryText = $qStat['norm_query'] ?? null;
                if (!$queryText) continue;

                $views = (int)($qStat['views'] ?? 0);
                $clicks = (int)($qStat['clicks'] ?? 0);
                $ctr = $views > 0 ? round(($clicks / $views) * 100, 2) : 0;

                $attributes = [
                    'campaign_id' => (int)$cId,
                    'company_id'  => $companyId, // <--- Добавили
                    'nm_id'       => (int)$nmId,
                    'date'        => $currentDate,
                    'query'       => (string)$queryText,
                    'views'       => $views,
                    'clicks'      => $clicks,
                    'ctr'         => $ctr,
                    'sum'         => (float)($qStat['spend'] ?? 0),
                    'atbs'        => (int)($qStat['atbs'] ?? 0),
                    'orders'      => (int)($qStat['orders'] ?? 0),
                    'shks'        => (int)($qStat['shks'] ?? 0),
                ];

                $result = $db->createCommand()->upsert('wb_campaign_query', $attributes, [
                    'views'  => new \yii\db\Expression('VALUES(views)'),
                    'clicks' => new \yii\db\Expression('VALUES(clicks)'),
                    'ctr'    => new \yii\db\Expression('VALUES(ctr)'),
                    'sum'    => new \yii\db\Expression('VALUES(sum)'),
                    'atbs'   => new \yii\db\Expression('VALUES(atbs)'),
                    'orders' => new \yii\db\Expression('VALUES(orders)'),
                    'shks'   => new \yii\db\Expression('VALUES(shks)'),
                ])->execute();

                if ($result) $totalSaved++;
            }
        }
    }





}