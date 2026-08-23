<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use app\models\WbSrReportSummary;
use app\models\WbSrReportGroups;
use app\models\WbSrReportItems;
use app\models\WbSrReportItemPhrases;

class WbParserController extends Controller
{
    private $_client;

    /** @var int */
    private $_companyId;

    public function init()
    {
        parent::init();
    }

    private function createClient(string $token): Client
    {
        return new Client([
            'base_uri' => 'https://seller-analytics-api.wildberries.ru',
            'timeout'  => 180.0,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    private function setCompanyContext(int $companyId, string $token): void
    {
        $this->_companyId = $companyId;
        Yii::$app->companyManager->setCurrentId($companyId);
        $this->_client = $this->createClient($token);
    }

    /**
     * @return int[]
     */
    private function getNmIdsForCompany(int $companyId, string $targetDate): array
    {
        return (new \yii\db\Query())
            ->select(['o.nm_id'])
            ->from(['o' => 'wb_order'])
            ->innerJoin(['c' => 'wbcards'], 'c.nmID = o.nm_id AND c.company_id = o.company_id')
            ->where(['>=', 'o.date', date('Y-m-d', strtotime($targetDate . ' -5 days'))])
            ->andWhere(['o.company_id' => $companyId])
            ->andWhere(['c.company_id' => $companyId])
            ->groupBy('o.nm_id')
            ->orderBy(['SUM(o.finished_price)' => SORT_DESC])
            ->column();
    }

/*
public function actionSearchReport($date = null)
    {
        $targetDate = $date ?: date('Y-m-d');
        $this->stdout("\n=== ЗАПУСК ПАРСИНГА [$targetDate] ===\n", \yii\helpers\Console::FG_CYAN);

        // Получаем 100 самых продаваемых товаров
        $allNmIds = (new \yii\db\Query())
            ->select(['nm_id'])
            ->from('wb_order')
            ->where(['>=', 'date', date('Y-m-d', strtotime('-30 days'))])
            ->groupBy('nm_id')
            ->orderBy(['SUM(finished_price)' => SORT_DESC])
            ->limit(100) 
            ->column();

        if (empty($allNmIds)) {
            $this->stdout("[-] Ошибка: в таблице wb_order не найдено заказов за последние 30 дней.\n", \yii\helpers\Console::FG_RED);
            return ExitCode::OK;
        }

        $this->stdout("[i] Всего артикулов к обработке: " . count($allNmIds) . "\n");

        // 1. Очистка и создание Сводки
        WbSrReportSummary::deleteAll(['period_start' => $targetDate]);
        $summary = new WbSrReportSummary([
            'period_start' => $targetDate,
            'period_end' => $targetDate,
            'currency' => 'RUB'
        ]);
        if (!$summary->save()) {
            $this->stdout("[-] Критическая ошибка создания Summary: " . Json::encode($summary->errors) . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $chunks = array_chunk($allNmIds, 50);

        // --- ШАГ 1: ОСНОВНОЙ ОТЧЕТ ---
        $this->stdout("\n--- ШАГ 1: СБОР ОСНОВНЫХ МЕТРИК (ЗАКАЗЫ, ПОЗИЦИИ) ---\n", \yii\helpers\Console::FG_YELLOW);
        foreach ($chunks as $idx => $chunk) {
            if ($idx > 0) {
                $this->stdout("--- Ожидание 22 сек (Rate Limit) ---\n", \yii\helpers\Console::FG_GREY);
                sleep(22); 
            }
            
            $num = $idx + 1;
            $this->stdout("[$num/" . count($chunks) . "] Обработка чанка артикулов:\n", \yii\helpers\Console::FG_CYAN);
            $this->stdout(implode(', ', $chunk) . "\n"); // Выводим список nmID в консоль
            
            $this->stdout(" -> Запрос к API... ");
            $apiData = $this->fetchMainReport($targetDate, $chunk);
            
            if ($apiData && isset($apiData['data'])) {
                $this->stdout("OK. Сохранение в БД... ");
//                $count = $this->saveMainReportData($apiData['data'], $summary);
                $count = $this->saveMainReportData($apiData['data'], $summary, $targetDate);
                $this->stdout("Готово (Добавлено товаров: $count)\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("ОШИБКА (Ответ пуст или некорректен)\n", \yii\helpers\Console::FG_RED);
            }
        }

        // --- ШАГ 2: ПОИСКОВЫЕ ФРАЗЫ ---
        $this->stdout("\n--- ШАГ 2: СБОР ПОИСКОВЫХ ФРАЗ (SEARCH TEXTS) ---\n", \yii\helpers\Console::FG_YELLOW);
        foreach ($chunks as $idx => $chunk) {
            if ($idx > 0) {
                $this->stdout("--- Ожидание 22 сек (Rate Limit) ---\n", \yii\helpers\Console::FG_GREY);
                sleep(22); 
            }
            
            $num = $idx + 1;
            $this->stdout("[$num/" . count($chunks) . "] Поиск фраз для артикулов:\n", \yii\helpers\Console::FG_CYAN);
            $this->stdout(implode(', ', $chunk) . "\n");
            
            $this->stdout(" -> Запрос к API... ");
            $apiData = $this->fetchPhraseData($targetDate, $chunk);

            if ($apiData && isset($apiData['data'])) {
                $this->stdout("OK. Сохранение в БД... ");
                $count = $this->savePhrases($apiData['data'], $targetDate, $summary->id);
                $this->stdout("Готово (Сохранено фраз: $count)\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("ОШИБКА\n", \yii\helpers\Console::FG_RED);
            }
        }

        $this->stdout("\n=== ПАРСИНГ ЗАВЕРШЕН. ПРОВЕРЯЙТЕ ТАБЛИЦУ wb_sr_report_item_phrases ===\n", \yii\helpers\Console::FG_CYAN);
        return ExitCode::OK;
    }
*/
    private function fetchMainReport($date, $nmIds)
    {
        $body = [
            'currentPeriod' => ['start' => $date, 'end' => $date],
            'pastPeriod'    => ['start' => date('Y-m-d', strtotime("$date -1 days")), 'end' => date('Y-m-d', strtotime("$date -1 days"))],
            'nmIds' => array_map('intval', $nmIds),
            'positionCluster' => 'all',
            'orderBy' => ['field' => 'avgPosition', 'mode' => 'asc'],
            'limit' => 1000,
            'offset' => 0
        ];
        return $this->postRequest('/api/v2/search-report/report', $body);
    }
/*
    private function fetchPhraseData($date, $nmIds)
    {
        // Текст запроса согласно твоему примеру
        $body = [
            'currentPeriod' => ['start' => $date, 'end' => $date],
            'pastPeriod'    => ['start' => date('Y-m-d', strtotime("$date -1 day")), 'end' => date('Y-m-d', strtotime("$date -1 day"))],
            'nmIds' => array_map('intval', $nmIds),
            'topOrderBy' => 'openToCart',
            'includeSubstitutedSKUs' => true,
            'includeSearchTexts' => false,
            'orderBy' => ['field' => 'avgPosition', 'mode' => 'asc'],
            'limit' => 30 // В фразах лимит обычно равен кол-ву nmIds
        ];
        return $this->postRequest('/api/v2/search-report/product/search-texts', $body);
    }
*/
    private function fetchPhraseData($date, $nmIds)
    {
        $body = [
            'currentPeriod' => ['start' => $date, 'end' => $date],
            'pastPeriod'    => ['start' => date('Y-m-d', strtotime("$date -1 day")), 'end' => date('Y-m-d', strtotime("$date -1 day"))],
            'nmIds' => array_map('intval', $nmIds),
            'topOrderBy' => 'openToCart',
            'includeSubstitutedSKUs' => true,
            'includeSearchTexts' => true,
            'orderBy' => ['field' => 'avgPosition', 'mode' => 'asc'],
            'limit' => 30 
        ];

        // ВЫВОДИМ ТО ЧТО ОТПРАВЛЯЕМ
        $this->stdout("\n--- ЗАПРОС К API ПОИСКОВЫХ ФРАЗ ---\n", \yii\helpers\Console::FG_CYAN);
//        $this->stdout("URL: /api/v2/search-report/product/search-texts\n");
//        $this->stdout("BODY: " . Json::encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        try {
            $response = $this->_client->post('/api/v2/search-report/product/search-texts', ['json' => $body]);

            
            // ВЫВОДИМ КОД ОТВЕТА
            $this->stdout("HTTP STATUS: " . $response->getStatusCode() . " " . $response->getReasonPhrase() . "\n", \yii\helpers\Console::FG_GREEN);
            
            $content = $response->getBody()->getContents();
            
            // ВЫВОДИМ ВЕСЬ СЫРОЙ ОТВЕТ
//            $this->stdout("RAW RESPONSE:\n" . $content . "\n", \yii\helpers\Console::FG_GREY);
//            $this->stdout("----------------------------------------\n");

            return Json::decode($content);
        } catch (RequestException $e) {
            $this->stdout("\n[!!!] ОШИБКА API [!!!]\n", \yii\helpers\Console::FG_RED);
            if ($e->hasResponse()) {
                $this->stdout("HTTP STATUS: " . $e->getResponse()->getStatusCode() . "\n");
                $this->stdout("ERROR BODY: " . $e->getResponse()->getBody()->getContents() . "\n");
            } else {
                $this->stdout("MESSAGE: " . $e->getMessage() . "\n");
            }
            return null;
        }
    }



    private function postRequest($url, $body)
    {
        try {
            $response = $this->_client->post($url, ['json' => $body]);
            return Json::decode($response->getBody()->getContents());
        } catch (RequestException $e) {
            $this->stdout("\n[!!!] ОШИБКА API [!!!]\n", \yii\helpers\Console::FG_RED);
            $this->stdout("URL: $url\nBODY: " . Json::encode($body) . "\n");
            if ($e->hasResponse()) {
                $this->stdout("ОТВЕТ: " . $e->getResponse()->getBody()->getContents() . "\n");
            }
            return null;
        }
    }
/*
    private function saveMainReportData($data, $summary) {
        $itemCount = 0;
        foreach ($data['groups'] as $gData) {
            $group = new WbSrReportGroups([
                'summary_id' => $summary->id,
                'subject_name' => $gData['subjectName'] ?? null,
                'metrics_json' => $gData['metrics'] ?? null
            ]);
            if ($group->save()) {
                foreach ($gData['items'] as $iData) {
                    $item = new WbSrReportItems([
                        'group_id' => $group->id,
                        'nmID' => $iData['nmId'],
                        'name' => $iData['name'] ?? null,
                        'vendor_code' => $iData['vendorCode'] ?? null,
                    ]);
                    if ($item->save()) $itemCount++;
                }
            }
        }
        return $itemCount;
    }
*/
/*
    private function savePhrases($data, $date, $summaryId) {
        $phraseCount = 0;
        foreach ($data as $itemData) {
            $dbItem = WbSrReportItems::find()->alias('i')
                ->innerJoin(['g' => 'wb_sr_report_groups'], 'g.id = i.group_id')
                ->where(['i.nmID' => $itemData['nmId'], 'g.summary_id' => $summaryId])
                ->one();

            if ($dbItem && !empty($itemData['searchTexts'])) {
                foreach ($itemData['searchTexts'] as $pData) {
                    $phrase = new WbSrReportItemPhrases([
                        'item_id' => $dbItem->id,
                        'phrase' => $pData['searchText'],
                        'avg_position' => $pData['avgPosition']['current'] ?? null,
                        'clicks' => $pData['openCard']['current'] ?? 0,
                        'orders' => $pData['orders']['current'] ?? 0,
                        'ctr' => $pData['openToCart']['current'] ?? null,
                    ]);
                    if ($phrase->save()) $phraseCount++;
                }
            }
        }
        return $phraseCount;
    }
*/
    /*
    private function savePhrases($data, $date, $summaryId) {
        $phraseCount = 0;
        
        // WB может вернуть данные в ключе 'items' или просто списком в 'data'
        $items = isset($data['items']) ? $data['items'] : $data;

        if (!is_array($items)) return 0;

        foreach ($items as $itemData) {
            // Проверяем наличие nmId (у WB бывает разный регистр nmId / nmID)
            $currentNmId = $itemData['nmId'] ?? ($itemData['nmID'] ?? null);
            
            if (!$currentNmId) continue;

            $dbItem = WbSrReportItems::find()->alias('i')
                ->innerJoin(['g' => 'wb_sr_report_groups'], 'g.id = i.group_id')
                ->where(['i.nmID' => $currentNmId, 'g.summary_id' => $summaryId])
                ->one();

            if ($dbItem && !empty($itemData['searchTexts'])) {
                foreach ($itemData['searchTexts'] as $pData) {
                    $phrase = new WbSrReportItemPhrases([
                        'item_id' => $dbItem->id, 
                        'phrase' => $pData['searchText'] ?? 'unknown',
                        'avg_position' => $pData['avgPosition']['current'] ?? null,
                        'clicks' => $pData['openCard']['current'] ?? 0,
                        'orders' => $pData['orders']['current'] ?? 0,
                        'ctr' => $pData['openToCart']['current'] ?? null,
                    ]);
                    if ($phrase->save()) {
                        $phraseCount++;
                    } else {
                        Yii::error("Ошибка сохранения фразы: " . Json::encode($phrase->errors));
                    }
                }
            }
        }
        return $phraseCount;
    }
*/
    /*
    private function savePhrases($apiResponse, $date, $summaryId) 
    {
        $phraseCount = 0;
        
        // Согласно твоему JSON, данные лежат в $apiResponse['items']
        $items = $apiResponse['items'] ?? [];

        if (empty($items)) {
            $this->stdout(" [!] Массив items пуст.\n", \yii\helpers\Console::FG_RED);
            return 0;
        }

        foreach ($items as $itemData) {
            $nmId = $itemData['nmId'] ?? null;
            $phraseText = $itemData['text'] ?? null;
            
            if (!$nmId || !$phraseText) continue;

            // Ищем товар, созданный на первом шаге
            // Помни: в твоей базе поле называется nmID (с большой D)
            $dbItem = WbSrReportItems::find()->alias('i')
                ->innerJoin(['g' => 'wb_sr_report_groups'], 'g.id = i.group_id')
                ->where(['i.nmID' => $nmId, 'g.summary_id' => $summaryId])
                ->one();

            if ($dbItem) {
                $phrase = new WbSrReportItemPhrases([
                    'item_id'      => $dbItem->id, 
                    'phrase'       => (string)$phraseText,
                    'avg_position' => $itemData['avgPosition']['current'] ?? null,
                    'clicks'       => $itemData['openCard']['current'] ?? 0,
                    'orders'       => $itemData['orders']['current'] ?? 0,
                    'ctr'          => $itemData['openToCart']['current'] ?? null,
                ]);
                
                if ($phrase->save()) {
                    $phraseCount++;
                } else {
                    $this->stdout("\n[!] Ошибка валидации для фразы '$phraseText': " . Json::encode($phrase->errors) . "\n");
                }
            }
        }
        return $phraseCount;
    }
    */
private function savePhrases($apiData, $date, $summaryId) 
{
    $phraseCount = 0;
    $items = $apiData['items'] ?? [];

    if (empty($items)) return 0;

    foreach ($items as $itemData) {
        $nmId = $itemData['nmId'] ?? null;
        $phraseText = $itemData['text'] ?? null;
        
        if (!$nmId || !$phraseText) continue;

        $dbItem = WbSrReportItems::find()->alias('i')
            ->innerJoin(['g' => 'wb_sr_report_groups'], 'g.id = i.group_id')
            ->where(['i.nmID' => $nmId, 'g.summary_id' => $summaryId])
            ->one();

        if ($dbItem) {
            $phrase = new WbSrReportItemPhrases();
            $phrase->company_id = $this->_companyId;
            $phrase->item_id = $dbItem->id;
            $phrase->date = $date;
            $phrase->nmID = $nmId;
            $phrase->phrase = (string)$phraseText;
            
            // Базовые метрики
            $phrase->avg_position = $itemData['avgPosition']['current'] ?? null;
            $phrase->clicks = $itemData['openCard']['current'] ?? 0;
            $phrase->orders = $itemData['orders']['current'] ?? 0;
            $phrase->ctr = $itemData['openToCart']['current'] ?? null;

            // Новые поля (п. 1-6)
            $phrase->is_card_rated = (int)($itemData['isCardRated'] ?? 0);
            $phrase->rating = isset($itemData['rating']) ? (float)$itemData['rating'] : null;
            $phrase->feedback_rating = isset($itemData['feedbackRating']) ? (float)$itemData['feedbackRating'] : null;
            $phrase->frequency_current = $itemData['frequency']['current'] ?? null;
            $phrase->median_position_current = $itemData['medianPosition']['current'] ?? null;
            $phrase->week_frequency = $itemData['weekFrequency'] ?? null;

            // JSON метрики (п. 7)
            $phrase->open_card_json = \yii\helpers\Json::encode($itemData['openCard'] ?? null);
            $phrase->add_to_cart_json = \yii\helpers\Json::encode($itemData['addToCart'] ?? null);
            $phrase->open_to_cart_json = \yii\helpers\Json::encode($itemData['openToCart'] ?? null);
            $phrase->orders_json = \yii\helpers\Json::encode($itemData['orders'] ?? null);
            $phrase->cart_to_order_json = \yii\helpers\Json::encode($itemData['cartToOrder'] ?? null);

            // Весь сырой JSON (п. 8)
            $phrase->raw_json = \yii\helpers\Json::encode($itemData);

            if ($phrase->save()) {
                $phraseCount++;
            } else {
                $this->stdout("\n[!] Ошибка записи: " . \yii\helpers\Json::encode($phrase->errors) . "\n");
            }
        }
    }
    return $phraseCount;
}

/*
private function saveMainReportData($apiData, $summary)
{
    $groups = $apiData['groups'] ?? [];
    $itemCount = 0;

    foreach ($groups as $gData) {
        $group = new WbSrReportGroups([
            'summary_id'   => $summary->id,
            'subject_id'   => $gData['subjectId'] ?? null,
            'subject_name' => $gData['subjectName'] ?? null,
            'brand_name'   => $gData['brandName'] ?? null,
            'tag_id'       => $gData['tagId'] ?? null,
            'tag_name'     => $gData['tagName'] ?? null,
            'metrics_json' => \yii\helpers\Json::encode($gData['metrics'] ?? null),
            'raw_json'     => \yii\helpers\Json::encode($gData),
        ]);

        if ($group->save()) {
            $items = $gData['items'] ?? [];
            foreach ($items as $iData) {
                $item = new WbSrReportItems();
                $item->group_id        = $group->id;
                $item->nmID            = $iData['nmId'] ?? null; // Твоё правило: nmID
                $item->vendor_code     = $iData['vendorCode'] ?? null;
                $item->name            = $iData['name'] ?? null;
                $item->brand_name      = $iData['brandName'] ?? null;
                $item->subject_name    = $iData['subjectName'] ?? null;
                $item->is_advertised   = (int)($iData['isAdvertised'] ?? 0);
                $item->is_card_rated   = (int)($iData['isCardRated'] ?? 0);
                $item->rating          = isset($iData['rating']) ? (float)$iData['rating'] : null;
                $item->feedback_rating = isset($iData['feedbackRating']) ? (float)$iData['feedbackRating'] : null;
                
                // Метрики (вытаскиваем current для плоских полей)
                $item->avg_position    = $iData['avgPosition']['current'] ?? null;
                $item->clicks          = $iData['openCard']['current'] ?? 0;
                $item->orders          = $iData['orders']['current'] ?? 0;
                $item->ctr             = $iData['openToCart']['current'] ?? null;

                // Складываем всё остальное в JSON как в структуре
                $item->price_json      = \yii\helpers\Json::encode($iData['price'] ?? null);
                $item->metrics_json    = \yii\helpers\Json::encode([
                    'avgPosition' => $iData['avgPosition'] ?? null,
                    'openCard'    => $iData['openCard'] ?? null,
                    'addToCart'   => $iData['addToCart'] ?? null,
                    'orders'      => $iData['orders'] ?? null,
                    'visibility'  => $iData['visibility'] ?? null,
                ]);
                $item->raw_json        = \yii\helpers\Json::encode($iData);

                if ($item->save()) {
                    $itemCount++;
                } else {
                    $this->stdout("\n[!] Ошибка Item (nmID: {$item->nmID}): " . \yii\helpers\Json::encode($item->errors) . "\n");
                }
            }
        }
    }
    return $itemCount;
}
*/



private function saveMainReportData($apiData, $summary, $targetDate)
{
    // 1. Сначала сохраняем Summary (обязательно вызываем save)
    $summary->total_products = $apiData['commonInfo']['totalProducts'] ?? 0;
    $summary->currency = $apiData['currency'] ?? 'RUB';
    $summary->company_id = $this->_companyId;
    $summary->supplier_rating_json = \yii\helpers\Json::encode($apiData['supplierRating'] ?? null);
    $summary->advertised_products_json = \yii\helpers\Json::encode($apiData['advertisedProducts'] ?? null);
    $summary->position_info_json = \yii\helpers\Json::encode($apiData['positionInfo'] ?? null);
    $summary->visibility_info_json = \yii\helpers\Json::encode($apiData['visibilityInfo'] ?? null);
    $summary->raw_json = \yii\helpers\Json::encode($apiData);
    
    if (!$summary->save()) {
        $this->stdout("[-] Ошибка Summary: " . \yii\helpers\Json::encode($summary->errors) . "\n", \yii\helpers\Console::FG_RED);
    }

    $groups = $apiData['groups'] ?? [];
    $itemCount = 0;

    $this->stdout("\n[v] Начинаю обработку групп (всего: " . count($groups) . ")\n", \yii\helpers\Console::FG_CYAN);

    foreach ($groups as $idx => $gData) {
        // ВЫВОД RAW В КОНСОЛЬ ДЛЯ ПРОВЕРКИ
//        $this->stdout("\n--- Группа #$idx Raw Data ---\n", \yii\helpers\Console::FG_YELLOW);
//        $this->stdout(\yii\helpers\Json::encode($gData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
//        $this->stdout("---------------------------\n");

        $group = new WbSrReportGroups();
        $group->company_id   = $this->_companyId;
        $group->summary_id   = $summary->id;
        $group->date         = $targetDate;
        $group->subject_id   = $gData['subjectId'] ?? null;
        $group->subject_name = (string)($gData['subjectName'] ?? '');
        $group->brand_name   = (string)($gData['brandName'] ?? '');
        $group->tag_id       = $gData['tagId'] ?? null;
        $group->tag_name     = (string)($gData['tagName'] ?? '');
        $group->metrics_json = \yii\helpers\Json::encode($gData['metrics'] ?? null);
        $group->raw_json     = \yii\helpers\Json::encode($gData); // Здесь будет только текущая группа

        if ($group->save()) {
            $items = $gData['items'] ?? [];
            foreach ($items as $iData) {
                $item = new WbSrReportItems();
                $item->company_id      = $this->_companyId;
                $item->group_id        = $group->id;
                $item->date            = $targetDate;
                $item->nmID            = $iData['nmId'] ?? null;
                $item->vendor_code     = $iData['vendorCode'] ?? null;
                $item->name            = $iData['name'] ?? null;
                $item->brand_name      = $iData['brandName'] ?? null;
                $item->subject_name    = $iData['subjectName'] ?? null;
                $item->is_advertised   = (int)($iData['isAdvertised'] ?? 0);
                $item->is_card_rated   = (int)($iData['isCardRated'] ?? 0);
                $item->rating          = isset($iData['rating']) ? (float)$iData['rating'] : null;
                $item->feedback_rating = isset($iData['feedbackRating']) ? (float)$iData['feedbackRating'] : null;
                
                // Метрики
                $item->avg_position    = $iData['avgPosition']['current'] ?? null;
                $item->clicks          = $iData['openCard']['current'] ?? 0;
                $item->orders          = $iData['orders']['current'] ?? 0;
                $item->ctr             = $iData['openToCart']['current'] ?? null;

                $item->price_json      = \yii\helpers\Json::encode($iData['price'] ?? null);
                // Сохраняем и метрики и raw для товара
                $item->metrics_json    = \yii\helpers\Json::encode($iData); 

                if ($item->save()) {
                    $itemCount++;
                } else {
                    $this->stdout("    [!] Ошибка Item (nmID {$item->nmID}): " . \yii\helpers\Json::encode($item->errors) . "\n");
                }
            }
        } else {
            $this->stdout("[-] Ошибка сохранения группы: " . \yii\helpers\Json::encode($group->errors) . "\n", \yii\helpers\Console::FG_RED);
        }
    }
    return $itemCount;
}


public function actionSearchReport($date = null)
{
    $targetDate = $date ?: date('Y-m-d', strtotime('-1 day'));
    $this->stdout("\n=== ЗАПУСК ОПТИМИЗИРОВАННОГО ПАРСИНГА [$targetDate] ===\n", \yii\helpers\Console::FG_CYAN);

    $companies = Yii::$app->companyManager->getActiveCompanies();
    if (empty($companies)) {
        $this->stderr("[-] Не найдено активных компаний в таблице companies.\n", \yii\helpers\Console::FG_RED);
        return ExitCode::UNSPECIFIED_ERROR;
    }

    foreach ($companies as $company) {
        $companyId = (int) $company['id'];
        $token = $company['api_key'] ?? null;

        if (!$token) {
            $this->stdout("[-] Пропуск компании '{$company['name']}' (ID: {$companyId}): отсутствует api_key.\n", \yii\helpers\Console::FG_YELLOW);
            continue;
        }

        $this->stdout("\n>>> КОМПАНИЯ: {$company['name']} (ID: {$companyId}) <<<\n", \yii\helpers\Console::FG_CYAN);
        $this->searchReportForCompany($targetDate, $companyId, $token);
    }

    $this->stdout("\n=== ПАРСИНГ ЗАВЕРШЕН ===\n", \yii\helpers\Console::FG_CYAN);

    $this->actionUpdateDirectory();

    return ExitCode::OK;
}

private function searchReportForCompany(string $targetDate, int $companyId, string $token): void
{
    $this->setCompanyContext($companyId, $token);

    $allNmIds = $this->getNmIdsForCompany($companyId, $targetDate);

    $countFound = count($allNmIds);
    $this->stdout("[i] Найдено артикулов: " . $countFound . "\n", \yii\helpers\Console::FG_CYAN);

    if ($countFound === 0) {
        $this->stdout("[-] Список пуст, парсинг для компании останавливается.\n", \yii\helpers\Console::FG_RED);
        return;
    }

    WbSrReportSummary::deleteAll(['period_start' => $targetDate, 'company_id' => $companyId]);
    $summary = new WbSrReportSummary([
        'company_id' => $companyId,
        'period_start' => $targetDate,
        'period_end' => $targetDate,
        'currency' => 'RUB',
    ]);
    $summary->save();

    $chunks = array_chunk($allNmIds, 150);
    $totalChunks = count($chunks);

    foreach ($chunks as $idx => $chunk) {
        $num = $idx + 1;
        $this->stdout("\n[$num/$totalChunks] ОБРАБОТКА ЧАНКА: " . implode(', ', $chunk) . "\n", \yii\helpers\Console::FG_YELLOW);

        $this->stdout(" -> Основной отчет... ");
        $mainData = $this->fetchMainReport($targetDate, $chunk);
        if ($mainData && isset($mainData['data'])) {
            $itemsSaved = $this->saveMainReportData($mainData['data'], $summary, $targetDate);
            $this->stdout("OK (Товаров: $itemsSaved) ");
        } else {
            $this->stdout("ОШИБКА ");
        }

        $s_chunks = array_chunk($chunk, 50);
        $s_totalChunks = count($s_chunks);
        foreach ($s_chunks as $sidx => $s_chunk) {
            $s_num = $sidx + 1;
            $this->stdout("\n[$s_num/$s_totalChunks] ОБРАБОТКА ЧАНКА Поиска: " . implode(', ', $s_chunk) . "\n", \yii\helpers\Console::FG_YELLOW);
            $this->stdout("| Поисковые фразы... ");
            $phraseData = $this->fetchPhraseData($targetDate, $s_chunk);
            if ($phraseData && isset($phraseData['data'])) {
                $phrasesSaved = $this->savePhrases($phraseData['data'], $targetDate, $summary->id);
                $this->stdout("OK (Фраз: $phrasesSaved)\n");
            } else {
                $this->stdout("ОШИБКА\n");
            }

            if ($s_num < $s_totalChunks) {
                $this->stdout("--- [$targetDate] Rate Limit: ожидание 20 сек ---\n", \yii\helpers\Console::FG_GREY);
                sleep(20);
            }
        }
        sleep(20);
    }
}


public function actionSearchReportRange($from, $to)
{
    $start = strtotime($from);
    $end = strtotime($to);

    if (!$start || !$end || $start > $end) {
        $this->stdout("[-] Ошибка: Неверный формат дат или начало позже конца.\n", \yii\helpers\Console::FG_RED);
        return ExitCode::DATAERR;
    }

    $current = $start;
    while ($current <= $end) {
        $targetDate = date('Y-m-d', $current);
        
        $this->stdout("\n" . str_repeat("=", 50) . "\n");
        $this->stdout(">>> ОБРАБОТКА ДАТЫ: $targetDate\n", \yii\helpers\Console::FG_YELLOW);
        $this->stdout(str_repeat("=", 50) . "\n");

        // Вызываем существующий метод для конкретной даты
        $this->actionSearchReport($targetDate);

        $current = strtotime('+1 day', $current);
        
        // Небольшая пауза между днями для надежности
        if ($current <= $end) {
            $this->stdout("\n[i] Переход к следующему дню...\n");
            sleep(5); 
        }
    }

    $this->stdout("\n[OK] Парсинг за период завершен!\n", \yii\helpers\Console::FG_GREEN);
    return ExitCode::OK;
}

public function actionUpdateDirectory()
{
    $db = Yii::$app->db;
    echo "Начинаю обновление справочника...\n";

    // Шаг 1: Добавляем новые уникальные фразы (используем вашу кодировку utf8mb4_0900_ai_ci)
    $sqlInsert = "INSERT IGNORE INTO wb_phrases_directory (phrase)
                  SELECT DISTINCT phrase 
                  FROM wb_sr_report_item_phrases 
                  WHERE phrase IS NOT NULL AND phrase != ''";
    
    $inserted = $db->createCommand($sqlInsert)->execute();
    echo "Добавлено новых фраз: $inserted\n";

    // Шаг 2: Обновляем max_frequency для всех фраз в справочнике
    // Используем JOIN для массового обновления
    echo "Обновляю данные по частотности...\n";
    $sqlUpdate = "UPDATE wb_phrases_directory d
                  JOIN (
                      SELECT 
                          phrase, 
                          MAX(week_frequency) as max_freq,
                          COUNT(DISTINCT nmID) as items_cnt,
                          SUM(orders) as orders_sum,
                          COUNT(DISTINCT date) as unique_dates_cnt 
                      FROM wb_sr_report_item_phrases
                      GROUP BY phrase
                  ) s ON d.phrase = s.phrase
                  SET 
                      d.max_frequency = s.max_freq,
                      d.items_count = s.items_cnt,
                      d.orders_count = s.orders_sum,
                      d.entries_count = s.unique_dates_cnt";
    
    $updated = $db->createCommand($sqlUpdate)->execute();
    echo "Частотность обновлена: $updated \n";
}

}