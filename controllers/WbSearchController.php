<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\WbSrReportItemPhrases;
use yii\helpers\ArrayHelper;

class WbSearchController extends Controller
{
 
/* php yii wb-parser/search-report-range 2025-12-10 2025-12-31 */

public function actionCard()
{
    $params = \app\components\getDPWidget::getParams(30);
    $dateFrom = $params['date_from'];
    $dateTo = $params['date_to'];
    $nmId = $params['nm_id'] ?? null;

    $models = [];
    $uniqueDates = [];
    $cardInfo = null;

    if ($nmId) {
        $cardInfo = (new \yii\db\Query())->from('wbcards')->where(['nmID' => $nmId])->one();

        $data = WbSrReportItemPhrases::find()
            ->select([
                'phrase', 
                'date', 
                'avg_position', 
                'clicks',
                'orders', //
                'week_frequency',
                'SUM(clicks) OVER (PARTITION BY phrase) as total_clicks',
                'SUM(orders) OVER (PARTITION BY phrase) as total_orders', // Агрегируем заказы
                'AVG(week_frequency) OVER (PARTITION BY phrase) as avg_week_freq'
            ])
            ->where(['nmID' => $nmId])
            ->andWhere(['between', 'date', $dateFrom, $dateTo])
            ->orderBy(['avg_week_freq' => SORT_DESC, 'date' => SORT_ASC])
            ->asArray()
            ->all();

        $matrix = [];
        $phraseStats = []; 

        foreach ($data as $row) {
            // Сохраняем и позицию, и количество заказов для каждой даты
            $matrix[$row['phrase']][$row['date']] = [
                'pos' => (int)$row['avg_position'],
                'orders' => (int)$row['orders'] //
            ];
            $phraseStats[$row['phrase']] = [
                'clicks' => (int)$row['total_clicks'],
                'orders' => (int)$row['total_orders'],
                'freq' => (int)$row['avg_week_freq']
            ];
            $uniqueDates[$row['date']] = true;
        }

        $uniqueDates = array_keys($uniqueDates);
        sort($uniqueDates);

        foreach ($matrix as $phrase => $dates) {
            $row = [
                'phrase' => $phrase,
                'avg_freq' => $phraseStats[$phrase]['freq'] ?? 0,
                'total_clicks' => $phraseStats[$phrase]['clicks'] ?? 0,
                'total_orders' => $phraseStats[$phrase]['orders'] ?? 0,
            ];
            foreach ($uniqueDates as $date) {
                $row[$date] = $dates[$date] ?? null;
            }
            $models[] = $row;
        }
    }

    $dataProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $models,
        'pagination' => false,
        'sort' => [
            'attributes' => ['phrase', 'avg_freq', 'total_clicks', 'total_orders'],
            'defaultOrder' => ['total_clicks' => SORT_DESC], 
        ],
    ]);

    return $this->render('card', [
        'dataProvider' => $dataProvider,
        'uniqueDates' => $uniqueDates,
        'cardInfo' => $cardInfo,
        'dateFrom' => $dateFrom,
        'nmId' => $nmId,
    ]);
}

/*
public function actionPhrase($phrase_id = null)
{
    $params = \app\components\getDPWidget::getParams(70);
    $dateFrom = $params['date_from'];
    $dateTo = $params['date_to'];

    $phrase_id = $phrase_id ?: (Yii::$app->request->get('phrase_id') ?: null);
    $phrase = \app\models\WbSrReportItemPhrases::findOne($phrase_id);
//    $phrase = WbSrReportItemPhrases::getItem($phrase_id);
    $phrase = $phrase ?: (Yii::$app->request->get('phrase') ?: null);

    // Список для Select2 с группировкой
    $phrasesList = \app\models\WbSrReportItemPhrases::find()
        ->select(['phrase', 'id'])
        ->where(['>', 'week_frequency', 0])
        ->groupBy(['phrase', 'id'])
        ->orderBy(['MAX(week_frequency)' => SORT_DESC])
        ->limit(1000)
//        ->indexBy('id')
        ->asArray()
        ->all();
//    $phrasesMap = array_combine($phrasesList, $phrasesList);
    $phrasesMap = ArrayHelper::map($phrasesList, 'id', 'phrase');

    $models = [];
    $uniqueDates = [];

    if ($phrase) {
        $data = \app\models\WbSrReportItemPhrases::find()
            ->alias('p')
            ->select([
                'p.nmID', 'c.title', 'p.date', 'p.avg_position', 'p.clicks', 'p.orders', 
                'SUM(p.clicks) OVER (PARTITION BY p.nmID) as total_clicks',
                'SUM(p.orders) OVER (PARTITION BY p.nmID) as total_orders',
                'AVG(p.week_frequency) OVER (PARTITION BY p.nmID) as avg_week_freq'
            ])
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = p.nmID')
            ->where(['p.phrase' => $phrase])
            ->andWhere(['between', 'p.date', $dateFrom, $dateTo])
            ->asArray()->all();

        $matrix = [];
        $cardStats = [];
        foreach ($data as $row) {
            $matrix[$row['nmID']][$row['date']] = ['pos' => $row['avg_position'], 'orders' => $row['orders']];
            $cardStats[$row['nmID']] = [
                'title' => $row['title'] ?: $row['nmID'],
                 'freq' => (int)$row['avg_week_freq'],
                'clicks' => $row['total_clicks'],
                'orders' => $row['total_orders']
            ];
            $uniqueDates[$row['date']] = true;
        }
        
        $uniqueDates = array_keys($uniqueDates);
        sort($uniqueDates);

        foreach ($matrix as $nmId => $dates) {
            $row = [
                'nmID' => $nmId,
                'avg_freq' => $cardStats[$nmId]['freq'] ?? 0,
                'title' => $cardStats[$nmId]['title'],
                'total_clicks' => $cardStats[$nmId]['clicks'],
                'total_orders' => $cardStats[$nmId]['orders'],
            ];
            foreach ($uniqueDates as $date) { $row[$date] = $dates[$date] ?? null; }
            $models[] = $row;
        }
    }

//    $dataProvider = new \yii\data\ArrayDataProvider(['allModels' => $models, 'pagination' => false]);
    
    $dataProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $models,
        'pagination' => false,
        'sort' => [
            'attributes' => ['nmID', 'title', 'avg_freq', 'total_clicks', 'total_orders'],
            'defaultOrder' => ['total_clicks' => SORT_DESC], 
        ],
    ]);


    return $this->render('phrase', [
        'dataProvider' => $dataProvider,
        'uniqueDates' => $uniqueDates,
        'phrase' => $phrase,
        'dateFrom' => $dateFrom,
        'phrasesMap' => $phrasesMap,
    ]);
}

*/
public function actionCannibalization()
{
    $params = \app\components\getDPWidget::getParams(70);
    $dateFrom = $params['date_from'];
    $dateTo = $params['date_to'];

    // SQL-запрос для поиска фраз, где более 1 артикула
    $data = \app\models\WbSrReportItemPhrases::find()
        ->alias('p')
        ->select([
            'p.phrase',
            'COUNT(DISTINCT p.nmID) as cards_count', // Сколько разных артикулов
            'GROUP_CONCAT(DISTINCT p.nmID) as nm_ids', // Список ID через запятую
            'SUM(p.clicks) as total_clicks',
            'SUM(p.orders) as total_orders',
            'AVG(p.week_frequency) as avg_freq'
        ])
        ->where(['between', 'p.date', $dateFrom, $dateTo])
        ->groupBy('p.phrase')
        ->having(['>', 'COUNT(DISTINCT p.nmID)', 1]) // Оставляем только те, где > 1 товара
        ->orderBy(['total_orders' => SORT_DESC])
        ->asArray()
        ->all();

    // Добавляем названия товаров для каждого ID
    foreach ($data as &$row) {
        $ids = explode(',', $row['nm_ids']);
        $cards = (new \yii\db\Query())
            ->select(['nmID', 'title'])
            ->from('wbcards')
            ->where(['nmID' => $ids])
            ->all();
        $row['cards_info'] = $cards;
    }

    $dataProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $data,
        'pagination' => false,
        'sort' => [
            'attributes' => ['phrase', 'cards_count', 'total_clicks', 'total_orders', 'avg_freq'],
        ],
    ]);

    return $this->render('cannibalization', [
        'dataProvider' => $dataProvider,
        'dateFrom' => $dateFrom,
    ]);
}



public function actionLostVisibility()
{
    $dropThreshold = 20; // Порог падения
    $topLimit = 30;      // Нас интересуют только те, кто был в ТОП-30
    
    $today = date('Y-m-d');
    $threeDaysAgo = date('Y-m-d', strtotime('-3 days'));
    $fourDaysAgo = date('Y-m-d', strtotime('-4 days'));
    $tenDaysAgo = date('Y-m-d', strtotime('-10 days'));

    $query = (new \yii\db\Query())
        ->select([
            'p.nmID',
            'c.title',
            'p.phrase',
            // Базовое среднее (за 7 дней) с учетом штрафа 999
            'ROUND((
                SUM(CASE WHEN p.date BETWEEN :baseStart AND :baseEnd THEN p.avg_position ELSE 0 END) + 
                (7 - COUNT(CASE WHEN p.date BETWEEN :baseStart AND :baseEnd THEN 1 END)) * 999
            ) / 7, 1) as avg_base',
            
            // Текущее среднее (за 3 дня)
            'ROUND((
                SUM(CASE WHEN p.date BETWEEN :curStart AND :curEnd THEN p.avg_position ELSE 0 END) + 
                (3 - COUNT(CASE WHEN p.date BETWEEN :curStart AND :curEnd THEN 1 END)) * 10
            ) / 3, 1) as avg_current',
            
            'MAX(p.week_frequency) as week_frequency',
            'SUM(p.orders) as total_orders_10d'
        ])
        ->from(['p' => 'wb_sr_report_item_phrases'])
        ->leftJoin(['c' => 'wbcards'], 'c.nmID = p.nmID')
        ->where(['between', 'p.date', $tenDaysAgo, $today])
        ->groupBy(['p.nmID', 'p.phrase'])
        ->addParams([
            ':baseStart' => $tenDaysAgo,
            ':baseEnd'   => $fourDaysAgo,
            ':curStart'  => $threeDaysAgo,
            ':curEnd'    => $today
        ]);

    $rawData = $query->all();
    $models = [];

    foreach ($rawData as $row) {
        $base = (float)$row['avg_base'];
        $current = (float)$row['avg_current'];

        // КЛЮЧЕВОЕ УСЛОВИЕ: берем только те фразы, по которым мы были в ТОП-30
        if ($base > $topLimit) continue;

        $diff = $current - $base;

        // Фильтруем по порогу падения
        if ($diff >= $dropThreshold) {
            $row['diff'] = round($diff, 1);
            $models[] = $row;
        }
    }

    // Сортировка по заказам: сначала те, по кому падение бьет больнее всего
    usort($models, function($a, $b) {
        return $b['total_orders_10d'] <=> $a['total_orders_10d'];
    });

    return $this->render('lost-visibility', [
        'dataProvider' => new \yii\data\ArrayDataProvider(['allModels' => $models, 'pagination' => false]),
        'dropThreshold' => $dropThreshold,
        'topLimit' => $topLimit
    ]);
}



public function actionPhrase($phrase_id = null)
{
   
    $params = \app\components\UniversalFilterWidget::getParams('phrase_id', 70);
    $dateFrom  = $params['date_from'];
    $dateTo    = $params['date_to'];
    $phrase_id = $params['id'];

    $chartData = [];
    $top5Info = [];
    $models = [];
    $uniqueDates = [];
    $phraseModel = null;
    $phraseText = null;

    if ($phrase_id) {
        // Ищем текст фразы в новом справочнике
        $phraseModel = \app\models\WbPhrasesDirectory::findOne($phrase_id);
        if ($phraseModel) {
            $phraseText = $phraseModel->phrase;
        }
    }


//    echo '1 - '.$phraseText.'<br/>';

    // Если ID не пришел или не найден, пробуем взять текст напрямую (для совместимости)
    if (!$phraseText) {
        $phraseText = Yii::$app->request->get('phrase') ?: null;
    }

//    echo '2 - '.$phraseText.'<br/>';


    // 2. Получаем список для Select2 из новой таблицы
    // Теперь это работает мгновенно, так как в таблице только уникальные фразы
    $phrasesMap = \yii\helpers\ArrayHelper::map(
        \app\models\WbPhrasesDirectory::find()
            ->select(['id', 'phrase', 'max_frequency'])
            ->where(['>', 'max_frequency', 5]) // Берем только "живые" фразы
            ->orderBy(['max_frequency' => SORT_DESC])
            ->limit(1000)
            ->asArray()
            ->all(), 
        'id', 
        function($model) {
            return $model['phrase'] . " ({$model['max_frequency']})";
        }
    );

    // 3. Основной запрос к данным (используем найденный текст фразы)
    if ($phraseText) {
        $data = \app\models\WbSrReportItemPhrases::find()
            ->alias('p')
            ->select([
                'p.nmID', 
                'c.title', 
                'p.date', 
                'p.avg_position', 
                'p.clicks', 
                'p.orders', 
                'SUM(p.clicks) OVER (PARTITION BY p.nmID) as total_clicks',
                'SUM(p.orders) OVER (PARTITION BY p.nmID) as total_orders',
                'AVG(p.week_frequency) OVER (PARTITION BY p.nmID) as avg_week_freq'
            ])
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = p.nmID')
            ->where(['p.phrase' => $phraseText]) // Поиск в основной таблице по тексту
            ->andWhere(['between', 'p.date', $dateFrom, $dateTo])
            ->asArray()
            ->all();

        $matrix = [];
        $cardStats = [];
        foreach ($data as $row) {
            $matrix[$row['nmID']][$row['date']] = [
                'pos' => $row['avg_position'], 
                'orders' => $row['orders']
            ];
            $cardStats[$row['nmID']] = [
                'title' => $row['title'] ?: $row['nmID'],
                'freq' => (int)$row['avg_week_freq'],
                'clicks' => $row['total_clicks'],
                'orders' => $row['total_orders']
            ];
            $uniqueDates[$row['date']] = true;
        }
        
        $uniqueDates = array_keys($uniqueDates);
        sort($uniqueDates);

        foreach ($matrix as $nmId => $dates) {
            $row = [
                'nmID' => $nmId,
                'avg_freq' => $cardStats[$nmId]['freq'] ?? 0,
                'title' => $cardStats[$nmId]['title'],
                'total_clicks' => $cardStats[$nmId]['clicks'],
                'total_orders' => $cardStats[$nmId]['orders'],
            ];
            foreach ($uniqueDates as $date) { 
                $row[$date] = $dates[$date] ?? null; 
            }
            $models[] = $row;
        }
    }

    $dataProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $models,
        'pagination' => false,
        'sort' => [
            'attributes' => ['nmID', 'title', 'avg_freq', 'total_clicks', 'total_orders'],
            'defaultOrder' => ['total_clicks' => SORT_DESC], 
        ],
    ]);

/* график */
    if ($phraseModel) {
        // 2. Получаем все данные по этой фразе за период
        $data = \app\models\WbSrReportItemPhrases::find()
            ->alias('p')
            ->select([
                'p.nmID', 'c.title', 'p.date', 'p.avg_position', 'p.clicks', 'p.orders', 
                'p.week_frequency',
                'SUM(p.clicks) OVER (PARTITION BY p.nmID) as total_clicks',
            ])
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = p.nmID')
            ->where(['p.phrase' => $phraseModel->phrase])
            ->andWhere(['between', 'p.date', $dateFrom, $dateTo])
            ->asArray()->all();

        $matrix = [];
        $cardNames = [];
        $rawFreqMap = [];
        $totalClicksPerCard = [];

        foreach ($data as $row) {
            $d = $row['date'];
            $nm = $row['nmID'];
            
            $matrix[$nm][$d] = (int)$row['clicks'];
            $cardNames[$nm] = $row['title'] ?: $nm;
            $totalClicksPerCard[$nm] = (int)$row['total_clicks'];
            
            // Собираем частотность (максимум за день, если вдруг дубли)
            $f = (int)$row['week_frequency'];
            if (!isset($rawFreqMap[$d]) || $f > $rawFreqMap[$d]) {
                $rawFreqMap[$d] = $f;
            }
        }

        // 3. Отбираем ТОП-5 карточек по суммарным кликам
        arsort($totalClicksPerCard);
        $top5NmIds = array_slice(array_keys($totalClicksPerCard), 0, 5);
        foreach ($top5NmIds as $id) {
            $top5Info[$id] = $cardNames[$id];
        }

        // 4. Генерируем непрерывный период дат для графика
        $period = new \DatePeriod(
            new \DateTime($dateFrom),
            new \DateInterval('P1D'),
            (new \DateTime($dateTo))->modify('+1 day')
        );

        $lastKnownFreq = 0;
        $daysSinceLastUpdate = 0;
        foreach ($period as $dt) {
            $d = $dt->format('Y-m-d');
/*
            // Логика Forward Fill для частотности
            if (isset($rawFreqMap[$d]) && $rawFreqMap[$d] > 0) {
                $lastKnownFreq = $rawFreqMap[$d];
            }
*/
        if (isset($rawFreqMap[$d]) && $rawFreqMap[$d] > 0) {
            // Данные есть: обновляем значение и сбрасываем счетчик
            $lastKnownFreq = $rawFreqMap[$d];
            $daysSinceLastUpdate = 0;
        } else {
            // Данных нет: увеличиваем счетчик "простоя"
            $daysSinceLastUpdate++;
            
            // Если данных нет 7 дней или больше — сбрасываем в 0
            if ($daysSinceLastUpdate >= 7) {
                $lastKnownFreq = 0;
            }
        }


            $point = [
                'date' => $dt->getTimestamp() * 1000, // Миллисекунды для JS
                'frequency' => $lastKnownFreq
            ];


            // Добавляем клики по каждой из ТОП-5 карточек
            foreach ($top5NmIds as $id) {
                $point['card_' . $id] = $matrix[$id][$d] ?? 0;
            }
            $chartData[] = $point;
        }
    }



    return $this->render('phrase', [
        'dataProvider' => $dataProvider,
        'uniqueDates' => $uniqueDates,
        'phrase' => $phraseText, // Передаем текст для заголовков в view
        'phrase_id' => $phrase_id,
        'dateFrom' => $dateFrom,
        'phrasesMap' => $phrasesMap,

        'chartData' => $chartData,
        'top5Info' => $top5Info,
    ]);
}

    public function actionAjaxSearch($type, $q = null)
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $map = [
            'cards'   => \app\models\WbCard::class,
            'phrases' => \app\models\WbPhrasesDirectory::class,
        ];

        if (!isset($map[$type])) return ['results' => []];

        $modelClass = $map[$type];
        return ['results' => $modelClass::searchForWidget($q)];
    }



public function actionTrend()
{
    $days = 90;
    $params = \app\components\UniversalFilterWidget::getParams('phrase_text', $days);
    $dateFrom = $params['date_from'];
    $dateTo   = $params['date_to'];
    $phrase_id = '';
    $phraseText = $params['id'] ?? null;

    if ($phraseText) {
        if (is_numeric($phraseText)) {
            // Если всё же пришел чистый ID (выбрали из подсказок)
            $phrase_id = $phraseText;
            $phraseModel = \app\models\WbPhrasesDirectory::findOne($phrase_id);
            if ($phraseModel) {
                $phraseText = $phraseModel->phrase;
            }
        } else {
            // Если пришел текст "лунный" — ищем все фразы, где он есть
        }
    }
    $query = (new \yii\db\Query())
        ->select([
            'pd.id as phrase_id',
            'p.phrase',
            'DATE_FORMAT(p.date, "%x-%v") as week_key',
            'MAX(p.week_frequency) as freq', 
            'SUM(p.clicks) as clicks',
            'SUM(p.orders) as orders'
        ])
        ->from(['p' => 'wb_sr_report_item_phrases'])
        ->leftJoin(['pd' => 'wb_phrases_directory'], 'pd.phrase = p.phrase') // Приклеиваем справочник
        ->where(['between', 'p.date', $dateFrom, $dateTo])
        ->groupBy(['pd.id', 'p.phrase', 'week_key'])
        ->having(['>=', 'MAX(p.week_frequency)', 5]);

/*
// В actionTrend
    $query = (new \yii\db\Query())
    ->select([
        'pd.id as phrase_id', // Берем ID из справочника
        's.phrase',           // Текст из статистики
        's.week_key',
        'AVG(s.week_frequency) as freq',
        'SUM(s.clicks) as clicks',
        'SUM(s.orders) as orders'
    ])
    ->from(['s' => 'wb_sr_report_item_phrases'])
    ->leftJoin(['pd' => 'wb_phrases_directory'], 'pd.phrase = s.phrase') // Приклеиваем справочник
    ->where(['between', 's.date', $dateFrom, $dateTo])
    ->groupBy(['pd.id', 's.phrase', 's.week_key']); // Группируем, чтобы ID пробросился
*/

    if ($phraseText) {
        if (is_numeric($phraseText)) {
            // Если всё же пришел чистый ID (выбрали из подсказок)
            $query->andWhere(['phrase_id' => $phraseText]);
        } else {
            // Если пришел текст "лунный" — ищем все фразы, где он есть
            $query->andWhere(['like', 'p.phrase', $phraseText]);
        }
    }

    $rawData = $query->all();
    $phraseStats = [];
    $allWeeks = [];

        foreach ($rawData as $row) {
            $p = $row['phrase'];
            $wk = 'w_' . $row['week_key'];
            $allWeeks[$wk] = true;
            $freq = (int)$row['freq']; // Сохраняем во временную переменную

            if (!isset($phraseStats[$p])) {
                $phraseStats[$p] = [
                    'phrase' => $p,
                    'phrase_id' => $row['phrase_id'],
                    'total_clicks' => 0,
                    'total_orders' => 0,
                    'sum_freq' => 0,
                    'count_weeks_with_data' => 0 // Считаем только недели с данными
                ];
            }

            $phraseStats[$p][$wk] = $freq;
            $phraseStats[$p]['total_clicks'] += (int)$row['clicks'];
            $phraseStats[$p]['total_orders'] += (int)$row['orders'];
            
            // Считаем сумму и количество недель только если частотность > 0
            if ($freq > 0) {
                $phraseStats[$p]['sum_freq'] += $freq;
                $phraseStats[$p]['count_weeks_with_data']++;
            }
        }

        $models = [];
        foreach ($phraseStats as $p => $data) {
            // Делим на количество реальных записей, а не на длину отчета
            $count = $data['count_weeks_with_data'] ?: 1; 
            $data['avg_freq'] = round($data['sum_freq'] / $count, 0);
            
            $data['conversion'] = $data['total_clicks'] > 0 
                ? round(($data['total_orders'] / $data['total_clicks']) * 100, 2) 
                : 0;

            foreach (array_keys($allWeeks) as $wk) { 
                if (!isset($data[$wk])) $data[$wk] = 0; 
            }
            $models[] = $data;
        }



    usort($models, fn($a, $b) => $b['avg_freq'] <=> $a['avg_freq']);
    $topPhrases = array_slice($models, 0, 9);

    $chartData = [];
    $sortedWeeks = array_keys($allWeeks);
    sort($sortedWeeks);
    foreach ($sortedWeeks as $wk) {
        $point = ['week' => substr($wk, 2)];
        foreach ($topPhrases as $index => $tp) {
            $point['val_' . $index] = $tp[$wk] ?? 0;
        }
        $chartData[] = $point;
    }

    return $this->render('trend', [
        'dataProvider' => new \yii\data\ArrayDataProvider([
            'allModels' => $models, 'pagination' => ['pageSize' => 50],
            'sort' => ['attributes' => ['phrase', 'total_clicks', 'total_orders', 'avg_freq', 'conversion']],
        ]),
        'chartData' => $chartData,
        'topPhrases' => $topPhrases,
        'phraseId' => $phraseText,
        'dateFrom'     => $dateFrom, 
        'dateTo'       => $dateTo,   
        'defaultDays' => $days,
    ]);
}



}