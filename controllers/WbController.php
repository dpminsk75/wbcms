<?php
namespace app\controllers;

use yii\helpers\Json;
use yii\helpers\ArrayHelper;

use yii\web\Controller;
use Yii;

use yii\db\Query;
use yii\db\Expression;
use yii\data\ArrayDataProvider;

use yii\filters\AccessControl;
use yii\httpclient\Client;
use app\models\WbCard;
use app\models\WbCardSearch;

/**
 * Web-контроллер для работы с карточками Wildberries.
 */
class WbController extends Controller
{
    /**
     * Универсальный поиск для всех AJAX-виджетов
     */
    public function actionAjaxSearch($type, $q = null)
    {
        // Устанавливаем формат ответа JSON, который ожидает Select2 
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        // Определяем, по какой модели искать, в зависимости от параметра type
        $map = [
            'cards'   => \app\models\WbCard::class,
            'phrases' => \app\models\WbPhrasesDirectory::class,
        ];

        // Если тип неизвестен, возвращаем пустой результат
        if (!isset($map[$type])) {
            return ['results' => []];
        }

        $modelClass = $map[$type];

        // Вызываем searchForWidget модели: он вернет массив вида [['id' => ..., 'text' => ...], ...]
        return [
            'results' => $modelClass::searchForWidget($q)
        ];
    }


    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['index', 'cards', 'sync-cards'],
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['sync-cards'],
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            $user = Yii::$app->user;
                            return !$user->isGuest && $user->identity->username === 'admin';
                        },
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Стартовая страница раздела (действия с WB).
     *
     * @return string
     */
    public function actionIndex(): string
    {
        return $this->render('index');
    }

    /**
     * Просмотр таблицы карточек WB со строкой фильтрации.
     *
     * @return string
     */
    public function actionCards(): string
    {
        $searchModel = new WbCardSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        // Списки для Select2 в фильтрах (уникальные значения)
        $brandList = WbCard::find()
            ->select('brand')
            ->distinct()
            ->where(['not', ['brand' => null]])
            ->andWhere(['<>', 'brand', ''])
            ->orderBy('brand')
            ->indexBy('brand')
            ->column();
        $subjectNameList = WbCard::find()
            ->select('subjectName')
            ->distinct()
            ->where(['not', ['subjectName' => null]])
            ->andWhere(['<>', 'subjectName', ''])
            ->orderBy('subjectName')
            ->indexBy('subjectName')
            ->column();

        $condensed = (bool) Yii::$app->request->get('condensed', false);

        return $this->render('cards', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'brandList' => $brandList,
            'subjectNameList' => $subjectNameList,
            'condensed' => $condensed,
        ]);
    }

    /**
     * Запуск синхронизации карточек WB через web.
     *
     * @return string
     */
    public function actionSyncCards(): string
    {
        set_time_limit(0);

        $token = Yii::$app->params['wbApiTokenContent'] ?? null;
        $totalFetched = 0;
        $errors = [];
        $requestUrl = null;

        if (!$token) {
            $errors[] = "Параметр 'wbApiTokenContent' не задан в params.php";
            return $this->render('sync-cards-result', [
                'totalFetched' => $totalFetched,
                'errors' => $errors,
                'token' => $token,
                'requestUrl' => $requestUrl,
            ]);
        }

        $client = new Client([
            'baseUrl' => 'https://content-api.wildberries.ru',
        ]);

        // Полный URL для отображения в вебе
        $requestUrl = $client->baseUrl . '/content/v2/get/cards/list';

        $limit = 100;
        $cursorUpdatedAt = null;
        $cursorNmID = null;

        while (true) {
            $body = [
                'settings' => [
                    'sort' => [
                        'ascending' => true,
                    ],
                    'filter' => [
                        'withPhoto' => -1,
                    ],
                    'cursor' => [
                        'limit' => $limit,
                    ],
                ],
            ];

            if ($cursorUpdatedAt !== null && $cursorNmID !== null) {
                $body['settings']['cursor']['updatedAt'] = $cursorUpdatedAt;
                $body['settings']['cursor']['nmID'] = $cursorNmID;
            }

            $request = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('/content/v2/get/cards/list')
                ->setHeaders([
                    'Content-Type'  => 'application/json',
                    'Authorization' => $token,
                ])
                ->setFormat(Client::FORMAT_JSON)
                ->setData($body);

            $response = $request->send();

            if (!$response->isOk) {
                $errors[] = "WB API error: HTTP {$response->statusCode}";
                break;
            }

            $data = $response->data;
            $cards = $data['cards'] ?? [];
            $cursor = $data['cursor'] ?? null;

            $count = count($cards);
            $totalFetched += $count;

            if ($count === 0) {
                break;
            }

            foreach ($cards as $card) {
                if (!$this->saveCard($card)) {
                    $errors[] = "Ошибка сохранения nmID=" . ($card['nmID'] ?? 'null');
                }
            }

            if ($cursor === null || ($cursor['total'] ?? 0) < $limit || $count < $limit) {
                break;
            }

            $cursorUpdatedAt = $cursor['updatedAt'] ?? null;
            $cursorNmID = $cursor['nmID'] ?? null;

            if ($cursorUpdatedAt === null || $cursorNmID === null) {
                break;
            }
        }

        return $this->render('sync-cards-result', [
            'totalFetched' => $totalFetched,
            'errors' => $errors,
            'token' => $token,
            'requestUrl' => $requestUrl,
        ]);
    }

    /**
     * Сохранение / обновление одной карточки WB.
     *
     * @param array $card
     * @return bool
     */
/*
    protected function saveCard(array $card): bool
    {
        $model = WbCard::findOne($card['nmID'] ?? null) ?? new WbCard();

        $model->nmID        = $card['nmID'] ?? null;
        $model->imtID       = $card['imtID'] ?? null;
        $model->nmUUID      = $card['nmUUID'] ?? null;
        $model->subjectID   = $card['subjectID'] ?? null;
        $model->subjectName = $card['subjectName'] ?? null;
        $model->vendorCode  = $card['vendorCode'] ?? null;
        $model->brand       = $card['brand'] ?? null;
        $model->title       = $card['title'] ?? null;
        $model->description = $card['description'] ?? null;

        return $model->save();
    }
*/

/**
 * Сохранение / обновление одной карточки WB (Upsert).
 *
 * @param array $card
 * @return bool
 */
    protected function saveCard(array $card): bool
    {
        if (empty($card['nmID'])) {
            return false;
        }

        // Подготавливаем данные
        // Извлекаем только ссылки из массива photos (в API это объекты с url)
        $photoUrls = [];
        if (!empty($card['photos'])) {
            foreach ($card['photos'] as $photo) {
                $photoUrls[] = $photo['big'] ?? $photo['c246x328'] ?? null;
            }
        }

        $columns = [
            'nmID'        => $card['nmID'],
            'imtID'       => $card['imtID'] ?? null,
            'nmUUID'      => $card['nmUUID'] ?? null,
            'subjectID'   => $card['subjectID'] ?? null,
            'subjectName' => $card['subjectName'] ?? null,
            'vendorCode'  => $card['vendorCode'] ?? null,
            'brand'       => $card['brand'] ?? null,
            'title'       => $card['title'] ?? null,
            'description' => $card['description'] ?? null,
            // Новые поля
            'photos'      => Json::encode(array_filter($photoUrls)),
            'video'       => $card['video'] ?? null,

            'dimensions'         => isset($card['dimensions']) ? Json::encode($card['dimensions']) : null,
            'characteristics'    => isset($card['characteristics']) ? Json::encode($card['characteristics']) : null,
            'sizes'              => isset($card['sizes']) ? Json::encode($card['sizes']) : null,
            'tags'               => isset($card['tags']) ? Json::encode($card['tags']) : null,
        ];

        try {
            // Выполняем UPSERT на уровне базы данных
            // Первым аргументом идет имя таблицы (wbcards по вашей инструкции)
            Yii::$app->db->createCommand()->upsert('wbcards', $columns, [
                // Список полей, которые нужно обновить, если nmID уже существует
                'imtID'       => $columns['imtID'],
                'nmUUID'      => $columns['nmUUID'],
                'subjectID'   => $columns['subjectID'],
                'subjectName' => $columns['subjectName'],
                'vendorCode'  => $columns['vendorCode'],
                'brand'       => $columns['brand'],
                'title'       => $columns['title'],
                'description' => $columns['description'],
                
                'photos'      => $columns['photos'],
                'video'       => $columns['video'],

                'dimensions'       => $columns['dimensions'],
                'characteristics'  => $columns['characteristics'],
                'sizes'            => $columns['sizes'],
                'tags'             => $columns['tags'],
            ])->execute();

            return true;
        } catch (\Exception $e) {
            Yii::error("Ошибка Upsert для nmID {$card['nmID']}: " . $e->getMessage());
            return false;
        }
    }

    public function actionDetail($dateFrom = null) : string
    {
            
//        $params = \app\components\getDPWidget::getParams(70);

        $defaultDays = 70; // ЕДИНОЕ место, где задаётся глубина периода по умолчанию

        $filterParams = Yii::$app->request->get('DPFilterForm', []);
        if (empty($filterParams['date_from'])) {
            $ts = strtotime("-{$defaultDays} days");
            $filterParams['date_from'] = date('Y-m-d', strtotime('monday this week', $ts));

            $queryParams = Yii::$app->request->getQueryParams();
            $queryParams['DPFilterForm'] = $filterParams;
            Yii::$app->request->setQueryParams($queryParams);
        }

        $params = \app\components\getDPWidget::getParams($defaultDays);

        $card = [];
        $AdvProvider = [];

        $LastOrdersProvider = [];
        $LastOrdersChartData = [];
        $LastSalesProvider = [];
        $LastSalesChartData = [];
        $OrdersStats = [];
        $OrderFunnel = [];
        $PivotDataProvider = [];
        $pivotDates = [];
        $ChartformattedData = [];

        $WeeklyFinanceProvider = new \yii\data\ArrayDataProvider(['allModels' => []]); 
        $phraseDataProvider = new \yii\data\ArrayDataProvider(['allModels' => []]); 
        $uniqueDates = [];

        $date_from = $params['date_from'] . ' 00:00:00';
        $date_to   = $params['date_to']   . ' 23:59:59';
//        $date_to14 = date($date_to, strtotime('-14 days'));
        $date_to14 = date('Y-m-d', strtotime($date_to.' -14 days')). ' 23:59:59';;
        

        if ($params['nm_id']) {
            $card = \app\models\WbCard::findOne(['nmID' => $params['nm_id']]);

            $nmId = $params['nm_id'];



            $advQuery = (new \yii\db\Query())
                ->select([
                    'c.campaign_id',
                    'c.name',
                    'adv' => new Expression('CASE WHEN i.id IS NULL THEN 0 ELSE 1 END'),
                    'c.status',
                    'orders'    => 'SUM(n.orders)',
                    'sum'       => 'SUM(n.sum)',
                    'sum_price' => 'SUM(n.sum_price)'
                ])
                ->from(['n' => 'wb_campaign_stats_nms'])
                ->innerJoin(['s' => 'wb_campaign_stats'], 'n.parent_id = s.id')
                ->innerJoin(['c' => 'wb_campaign'], 'c.campaign_id = s.campaign_id')
                // Используем LEFT JOIN с проверкой по nmID, как в ваших настройках
                ->leftJoin(['i' => 'wb_campaign_item'], 'i.campaign_id = s.campaign_id AND n.nm_id = i.nm_id')
                ->where(['n.nm_id' => $nmId])
                ->andWhere(['between', 's.date', $date_from, $date_to])
                ->andWhere(['>', 'n.orders', 0])
                ->groupBy(['c.campaign_id', 'c.name', 'adv', 'c.status'])
                ->orderBy([
                    'c.status' => SORT_ASC,
                    'orders' => SORT_DESC
                ]);

                $advData = $advQuery->all();

//                    ->where(['>', 'date', '2026-01-01'])
//                    ->andWhere(['nm_id' => 526443466])

                $LastOrdersQuery = (new Query())
                    ->select([
                        'nm_id',
                        'odate' => new Expression('DATE([[date]])'),
                        'tp'    => 'AVG([[total_price]])',
                        'dsc'   => 'AVG([[discount_percent]])',
                        'apwd'  => 'AVG([[price_with_disc]])',

                        'spp'   => 'AVG([[spp]])',
                        'finished_price' => 'AVG([[finished_price]])',

                        'cnt'   => 'SUM([[is_realization]])',
                        'sum'   => new Expression("SUM(CASE WHEN [[is_cancel]] = 0 THEN [[finished_price]] ELSE 0 END)"),
 //                        'sum'   => 'SUM([[finished_price]])',
                        'cns'   => 'SUM([[is_cancel]])',
                    ])
                    ->from('wb_order')
                    ->where(['nm_id' => $nmId])
                    ->andWhere(['between', 'date', $date_from, $date_to])
                    ->groupBy(['nm_id', 'odate'])
                    ->orderBy(['odate' => SORT_DESC]); 
                    

            $LastOrdersData = $LastOrdersQuery->all();
            $LastOrdersChartQuery = clone $LastOrdersQuery; 
            $LastOrdersChartData = $LastOrdersChartQuery->orderBy(['odate' => SORT_ASC])->all();

//                    ->where(['>', 'date', '2026-01-01'])
//                    ->andWhere(['nmId' => 526443466])

                $LastSalesQuery = (new Query())
                    ->select([
                        'nm_id' => 'nmId',
                        'odate' => new Expression('DATE([[date]])'),
                        'tp'   => 'AVG([[totalPrice]])',
                        'dsc'  => 'AVG([[discountPercent]])',
                        'apwd' => 'AVG([[priceWithDisc]])',
                        'spp'  => 'AVG([[spp]])',
                        'finished_price' => 'AVG([[finishedPrice]])',
                        'forPay' => 'AVG([[forPay]])',
                        'cnt'   => 'SUM([[isRealization]])',
                        'sum'   => 'SUM([[finishedPrice]])',
                        'sFP'   => 'SUM([[forPay]])',
                    ])
                    ->from('wb_sales')
                    ->where(['nmId' => $nmId])
                    ->andWhere(['between', 'date', $date_from, $date_to])
                    ->groupBy(['nmId', 'odate'])
                    ->orderBy(['odate' => SORT_DESC]);

            $LastSalesData = $LastSalesQuery->all();
            $LastSalesChartQuery = clone $LastSalesQuery; 
            $LastSalesChartData = $LastSalesChartQuery->orderBy(['odate' => SORT_ASC])->all();


                $OrdersStats = (new Query())
                    ->select([
                        'nm_id',
                        'alls'   => 'SUM(o.is_realization)',
                        'sLO'    => 'SUM(o.finished_price)',
                        'cancel' => 'SUM(o.is_cancel)',
                        // Используем Expression для сложных CASE WHEN
                        'notb'   => new Expression('SUM(CASE WHEN s.saleID IS NULL THEN 1 ELSE 0 END)'),
                        'bought' => new Expression('SUM(CASE WHEN s.saleID IS NULL THEN 0 ELSE 1 END)'),
                        'sum'    => 'SUM(s.finishedPrice)',
                        'sFP'    => 'SUM(s.forPay)',
                    ])
                    ->from('wb_order o')
                    ->leftJoin('wb_sales s', 'o.srid = s.srid')
                    ->where(['o.nm_id' => $nmId])
                    ->andWhere(['between', 'o.date', $date_from, $date_to14])
                    ->groupBy('o.nm_id')
                    ->one();

                /*
                 * Воронка заказов за период (карточки "Заказы / Выкупленные / В доставке /
                 * Отменённые / Возвраты / Процент выкупа"), как в кабинете WB.
                 *
                 * Логика сопоставления заказа (wb_order) и продажи (wb_sales) — по srid:
                 *  - saleID вида "S..." в wb_sales = продажа (выкуп);
                 *  - saleID вида "R..." в wb_sales = возврат товара покупателем;
                 *  - если для заказа нет строки в wb_sales и он не отменён — считаем,
                 *    что он ещё "в доставке" (WB просто не прислал финальный статус).
                 *
                 * ВАЖНО: если в вашей базе продажи/возвраты различаются иначе (не по
                 * префиксу saleID), поправьте условие в bought_qty/bought_sum ниже.
                 */
                $OrderFunnel = (new Query())
                    ->select([
                        'total_qty'    => 'COUNT(*)',
                        'total_sum'    => 'SUM(o.finished_price)',

                        'bought_qty'   => new Expression("SUM(CASE WHEN s.saleID IS NOT NULL AND s.saleID NOT LIKE 'R%' THEN 1 ELSE 0 END)"),
                        'bought_sum'   => new Expression("SUM(CASE WHEN s.saleID IS NOT NULL AND s.saleID NOT LIKE 'R%' THEN s.finishedPrice ELSE 0 END)"),

                        'cancel_qty'   => new Expression('SUM(CASE WHEN o.is_cancel = 1 THEN 1 ELSE 0 END)'),
                        'cancel_sum'   => new Expression('SUM(CASE WHEN o.is_cancel = 1 THEN o.finished_price ELSE 0 END)'),

                        'delivery_qty' => new Expression('SUM(CASE WHEN o.is_cancel = 0 AND s.saleID IS NULL THEN 1 ELSE 0 END)'),
                        'delivery_sum' => new Expression('SUM(CASE WHEN o.is_cancel = 0 AND s.saleID IS NULL THEN o.finished_price ELSE 0 END)'),
                    ])
                    ->from(['o' => 'wb_order'])
                    ->leftJoin(['s' => 'wb_sales'], 's.srid = o.srid')
                    ->where(['o.nm_id' => $nmId])
                    ->andWhere(['between', 'o.date', $date_from, $date_to])
                    ->one();

                $returnsRow = (new Query())
                    ->select([
                        'returns_qty' => 'COUNT(*)',
                        'returns_sum' => 'SUM(s.finishedPrice)',
                    ])
                    ->from(['s' => 'wb_sales'])
                    ->innerJoin(['o' => 'wb_order'], 'o.srid = s.srid')
                    ->where(['o.nm_id' => $nmId])
                    ->andWhere(['between', 'o.date', $date_from, $date_to])
                    ->andWhere(new Expression("s.saleID LIKE 'R%'"))
                    ->one();

                $OrderFunnel = $OrderFunnel ?: [];
                $OrderFunnel['returns_qty'] = $returnsRow['returns_qty'] ?? 0;
                $OrderFunnel['returns_sum'] = $returnsRow['returns_sum'] ?? 0;

                $totalQty    = (int)($OrderFunnel['total_qty'] ?? 0);
                $boughtQty   = (int)($OrderFunnel['bought_qty'] ?? 0);
                $cancelQty   = (int)($OrderFunnel['cancel_qty'] ?? 0);
                $deliveryQty = (int)($OrderFunnel['delivery_qty'] ?? 0);
                $returnsQty  = (int)($OrderFunnel['returns_qty'] ?? 0);

                // Процент выкупа считаем классически: выкупленные / (выкупленные + отменённые),
                // не учитывая ещё не завершённые ("в доставке") заказы.
                $OrderFunnel['buyout_percent'] = ($boughtQty + $cancelQty) > 0
                    ? round($boughtQty / ($boughtQty + $cancelQty) * 100, 2)
                    : 0;

                $OrderFunnel['bought_percent']   = $totalQty > 0 ? round($boughtQty   / $totalQty * 100, 2) : 0;
                $OrderFunnel['delivery_percent'] = $totalQty > 0 ? round($deliveryQty / $totalQty * 100, 2) : 0;
                $OrderFunnel['cancel_percent']   = $totalQty > 0 ? round($cancelQty   / $totalQty * 100, 2) : 0;
                $OrderFunnel['returns_percent']  = $totalQty > 0 ? round($returnsQty  / $totalQty * 100, 2) : 0;


/* собираем аналитику по продажам через detail_by_period*/

        $weeklyFinanceData = (new Query())
            ->select([
                'sdate'             => new Expression("DATE_SUB(sdate, INTERVAL WEEKDAY(sdate) DAY)"),
                'qnt'               => new Expression("SUM(qnt)"),
                'amount'            => new Expression("SUM(amount)"),
                'return'            => new Expression("SUM(`return`)"),
                'commission'        => new Expression("SUM(commission)"),
                'f_acquiring_fee'   => new Expression("SUM(f_acquiring_fee)"),
                'f_acceptance'      => new Expression("SUM(f_acceptance)"),
                'f_delivery'        => new Expression("SUM(f_delivery)"),
                'f_storage_fee'     => new Expression("SUM(f_storage_fee)"),
                'f_penalty'         => new Expression("SUM(f_penalty)"),
                'f_deduction'       => new Expression("SUM(f_deduction)"),
                'f_otziv'           => new Expression("COALESCE(SUM(ff_otziv), 0)"),
//                'f_otziv' => new Expression("COALESCE(SUM(ff_otziv), 0) + COALESCE(SUM(f_otziv), 0)"),
                'f_adv'             => new Expression("COALESCE(SUM(ff_adv), 0)"),
                'f_cashback'        => new Expression("SUM(f_cashback)"),

                // net_profit с защитой от NULL в ff_otziv / ff_adv
                'net_profit'        => new Expression(
                    "SUM(net_profit) - COALESCE(SUM(ff_otziv), 0) - COALESCE(SUM(ff_adv), 0)"
                ),

                // НДС и себестоимость
                'total_nds'         => new Expression("COALESCE(SUM(f_nds), 0)"),
                'total_cost'        => new Expression("COALESCE(SUM(f_cost_price), 0)"),

                // Прибыль до налогов
                'profit_before_tax' => new Expression(
                    "SUM(net_profit) - COALESCE(SUM(f_nds), 0) - COALESCE(SUM(f_cost_price), 0) - COALESCE(SUM(ff_otziv), 0) - COALESCE(SUM(ff_adv), 0)"
                ),

                // Налог 7%
                'tax_amount'        => new Expression(
                    "GREATEST(0, SUM(net_profit) - COALESCE(SUM(f_nds), 0) - COALESCE(SUM(f_cost_price), 0) - COALESCE(SUM(ff_otziv), 0) - COALESCE(SUM(ff_adv), 0)) * 0.07"
                ),

                // Маржа (чистая)
                'clean_margin'      => new Expression(
                    "(SUM(net_profit) - COALESCE(SUM(f_nds), 0) - COALESCE(SUM(f_cost_price), 0) - COALESCE(SUM(ff_otziv), 0) - COALESCE(SUM(ff_adv), 0))
                     - (GREATEST(0, SUM(net_profit) - COALESCE(SUM(f_nds), 0) - COALESCE(SUM(f_cost_price), 0) - COALESCE(SUM(ff_otziv), 0) - COALESCE(SUM(ff_adv), 0)) * 0.07)"
                ),
/*
                'amount_per_item'   => new Expression("SUM(amount) / NULLIF(SUM(qnt), 0)"),
                'profit_per_item'   => new Expression("SUM(net_profit) / NULLIF(SUM(qnt), 0)"),
                'clear_per_item'    => new Expression(
                    "((SUM(net_profit) - COALESCE(SUM(f_nds), 0) - COALESCE(SUM(f_cost_price), 0) - COALESCE(SUM(ff_otziv), 0) - COALESCE(SUM(ff_adv), 0))
                     - (GREATEST(0, SUM(net_profit) - COALESCE(SUM(f_nds), 0) - COALESCE(SUM(f_cost_price), 0) - COALESCE(SUM(ff_otziv), 0) - COALESCE(SUM(ff_adv), 0)) * 0.07))
                     / NULLIF(SUM(qnt), 0)"
                ),
*/
                'amount_per_item'   => new Expression("COALESCE(SUM(amount) / NULLIF(SUM(qnt), 0), 0)"),
                'profit_per_item'   => new Expression("COALESCE(SUM(net_profit) / NULLIF(SUM(qnt), 0), 0)"),
                'clear_per_item'    => new Expression(
                    "COALESCE(
                        ((SUM(net_profit) - COALESCE(SUM(f_nds), 0) - COALESCE(SUM(f_cost_price), 0) - COALESCE(SUM(ff_otziv), 0) - COALESCE(SUM(ff_adv), 0))
                         - (GREATEST(0, SUM(net_profit) - COALESCE(SUM(f_nds), 0) - COALESCE(SUM(f_cost_price), 0) - COALESCE(SUM(ff_otziv), 0) - COALESCE(SUM(ff_adv), 0)) * 0.07))
                        / NULLIF(SUM(qnt), 0),
                    0)"
                ),

            ])
            ->from('agg_daily_summary')
            ->where(['nm_id' => $nmId])
            ->andWhere(['between', 'sdate', date('Y-m-d', strtotime($date_from)), date('Y-m-d', strtotime($date_to))])
            ->groupBy(new Expression("DATE_SUB(sdate, INTERVAL WEEKDAY(sdate) DAY)"))
            ->orderBy(['sdate' => SORT_DESC])
            ->all();

        $WeeklyFinanceProvider = new ArrayDataProvider([
            'allModels' => $weeklyFinanceData,
            'pagination' => false,
        ]);

/*
            'cnt' => 'COUNT(quantity)',
            'sum_price' => 'SUM(retail_price)',
            'sum_amount' => 'SUM(retail_amount)',
            'sum_pay' => 'SUM(ppvz_for_pay)',
*/

    $pivotQuery = (new Query())
        ->select([
            'dd' => "DATE_FORMAT(sale_dt, '%Y-%m')", 
            'nm_id',

            'retail_price'          => 'SUM(COALESCE(retail_price, 0))',
            'retail_amount'         => 'SUM(COALESCE(retail_amount, 0))',
            'commission_percent'    => 'AVG(COALESCE(commission_percent, 0))',
            'ppvz_spp_prc'          => 'AVG(COALESCE(ppvz_spp_prc, 0))',
            'ppvz_sales_commission' => 'SUM(COALESCE(ppvz_sales_commission, 0))',
            'ppvz_reward'           => 'SUM(COALESCE(ppvz_reward, 0))',
            'acquiring_fee'         => 'SUM(COALESCE(acquiring_fee, 0))',
            'ppvz_vw'               => 'SUM(COALESCE(ppvz_vw, 0))+SUM(COALESCE(ppvz_vw_nds, 0))',
            'delivery_rub'          => 'SUM(COALESCE(delivery_rub, 0))',
            'rebill_logistic_cost'  => 'SUM(COALESCE(rebill_logistic_cost, 0))',
            'ppvz_for_pay'          => 'SUM(COALESCE(ppvz_for_pay, 0))',
            'retail_sum'            => 'SUM(retail_amount)',
            'for_pay'               => 'SUM(ppvz_for_pay)',
            'delivery'              => 'SUM(delivery_amount)',
            'aretail_amount'        => 'AVG(COALESCE(retail_amount, 0))',
            'afor_pay'              => 'AVG(ppvz_for_pay)',

            'rows_count'   => 'COUNT(*)',
            'sales_count'  => 'SUM(CASE WHEN doc_type_name = "Продажа" THEN 1 ELSE 0 END)',
            'return_count' => 'SUM(CASE WHEN doc_type_name = "Возврат" THEN 1 ELSE 0 END)',

        ])
        ->from('detail_by_period')
        ->where([
            'nm_id' => $nmId,
            'doc_type_name' => 'Продажа',
            'supplier_oper_name' => 'Продажа'
        ])
        ->andWhere(['between', 'sale_dt', $date_from, $date_to])
        ->groupBy(['dd', 'nm_id'])
        ->orderBy(['dd' => SORT_ASC]);

    $rawRecords = $pivotQuery->all();
    $pivotDates = array_unique(ArrayHelper::getColumn($rawRecords, 'dd'));

    $metrics = [
                'sales_count'           => 'Кол-во, шт',   
                'retail_price'          => 'Сумма розн., руб',     
                'retail_amount'         => 'Сумма продажи, руб',   
                'commission_percent'    => 'Комиссия WB, %',       
                'ppvz_spp_prc'          => 'Скидка ПП, %',         
                'ppvz_sales_commission' => 'Итог. скидка, руб',    
                'ppvz_reward'           => 'Услуги ППВЗ, руб',     
                'acquiring_fee'         => 'Эквайринг, руб',       
                'ppvz_vw'               => 'Возн ВБ, руб', 
                'ppvz_for_pay'          => 'К перечислению, руб',  

                'aretail_amount'        => 'Сумма прд, руб/шт',   
                'afor_pay'              => 'К переч., руб/шт',  

    ];

    $avgMetricsKeys = [
        'commission_percent', 
        'ppvz_spp_prc', 
        'aretail_amount', 
        'afor_pay'
    ];
/*
                'rows_count'            => 'Кол-во записей',       
                'delivery_rub'          => 'Логистика, руб',       
                'rebill_logistic_cost'  => 'Возвраты, руб',        
*/

    $pivotedData = [];

    foreach ($metrics as $key => $label) {
        $row = [
            'metric_label' => $label,
            'metric_key'   => $key, 
        ];
        $sumForTotal = 0;
        $countForTotal = 0; 

        foreach ($pivotDates as $date) {
            $value = 0;
            foreach ($rawRecords as $record) {
                if ($record['dd'] === $date) {
                    $value = (float)$record[$key];
                    break;
                }
            }
            $row[$date] = $value;
            $sumForTotal += $value; // Накапливаем сумму для итогов
            
            // Если значение не нулевое, учитываем этот месяц в расчете среднего
            // (Или просто считаем все месяцы, зависит от вашей бизнес-логики)
            if ($value != 0) {
                $countForTotal++;
            }
        }

        if (in_array($key, $avgMetricsKeys)) {
            // Если это среднее значение, делим сумму на количество периодов
            $row['total'] = ($countForTotal > 0) ? ($sumForTotal / $countForTotal) : 0;
        } else {
            // Для всех остальных (суммовых) — просто сумма
            $row['total'] = $sumForTotal;
        }

        $pivotedData[] = $row;
    }

    $PivotDataProvider = new ArrayDataProvider([
        'allModels' => $pivotedData,
        'pagination' => false, 
    ]);

/* end */
/* сделаем график серий по годам */
    $ChartQuery = (new Query())
        ->select([
            // Ось X: порядковый номер месяца (1-12) или формат "01-01" для точности
            'month' => "DATE_FORMAT(sale_dt, '%m')", 
            // Идентификатор серии: год (2024, 2025...)
            'year'  => "DATE_FORMAT(sale_dt, '%Y')",
            'sales_amount' => 'sum(retail_price_withdisc_rub)',
            'sales_retail'  => 'sum(ppvz_for_pay)',
            'sales_count' => 'COUNT(*)', // Так как в where уже фильтр по "Продажа"
        ])
        ->from('detail_by_period')
        ->where([
            'nm_id' => $nmId,
            'doc_type_name' => 'Продажа',
            'supplier_oper_name' => 'Продажа'
        ])
        ->andWhere(['between', 'sale_dt', $date_from, $date_to])
        ->groupBy(['year', 'month']) // Группируем по году и месяцу
        ->orderBy(['year' => SORT_ASC, 'month' => SORT_ASC]);

    $ChartRows = $ChartQuery->all();


    $ChartformattedData = [];
    foreach ($ChartRows as $row) {
        $ChartformattedData[$row['year']][] = [
            // Создаем фиктивную дату: 2000 год, месяц из БД, 1-е число
            'date' => strtotime("2000-{$row['month']}-01") * 1000,
            'value' => (int)$row['sales_count'],
            'amount' => (int)$row['sales_amount'],
            'retail' => (int)$row['sales_retail']

        ];
    }

/* end */

            $AdvProvider = new \yii\data\ArrayDataProvider([
                'allModels' => $advData,
                'sort' => [
                    'attributes' => ['adv','name','orders',],
                    'defaultOrder' => [
                        'adv' => SORT_DESC, 
                        'orders' => SORT_DESC
                    ],
                ],
                'pagination' => [
                    'pageSize' => 10,
                ],
            ]);

/*
                'sort' => [
                    'attributes' => ['odate'], 
                ],

*/
            $LastOrdersProvider = new \yii\data\ArrayDataProvider([
                'allModels' => $LastOrdersData,
                'pagination' => [
                    'pageSize' => 30, 
                ],
            ]); 

/*
                'sort' => [
                    'attributes' => ['odate'],
                    'defaultOrder' => ['odate' => SORT_DESC],
                ],
*/

            $LastSalesProvider = new \yii\data\ArrayDataProvider([
                'allModels' => $LastSalesData,
                'pagination' => [
                    'pageSize' => 30, 
                ],
            ]);


// поисковые фразы
    $service = new \app\components\WbSearchService();
    $statsData = $service->getCardPhrasesMatrix($params['nm_id'], $params['date_from'], $params['date_to']);

    $phraseDataProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $statsData['models'],
        'pagination' => false,
/*
        'sort' => [
            'attributes' => ['phrase', 'avg_freq', 'total_clicks', 'total_orders'],
            'defaultOrder' => [
                'total_clicks' => SORT_DESC, 
                'avg_freq' => SORT_DESC,      
                'total_orders' => SORT_DESC,  
            ],
        ],
*/
    'sort' => [
        'attributes' => [
                'phrase' => [
                    'asc' => ['phrase' => SORT_ASC],
                    'desc' => ['phrase' => SORT_DESC],
                    'default' => SORT_ASC,
                ],
                'avg_freq' => [
                    'asc' => ['avg_freq' => SORT_ASC],
                    'desc' => ['avg_freq' => SORT_DESC],
                    'default' => SORT_DESC,
                ],
                'total_clicks' => [
                    'asc' => ['total_clicks' => SORT_ASC],
                    'desc' => ['total_clicks' => SORT_DESC],
                    'default' => SORT_DESC,
                ],
                'total_orders' => [
                    'asc' => ['total_orders' => SORT_ASC],
                    'desc' => ['total_orders' => SORT_DESC],
                    'default' => SORT_DESC,
                ],
            ],

        'defaultOrder' => [
            'total_orders' => SORT_DESC,  
        ],

    ],

    ]);
    $uniqueDates = $statsData['uniqueDates'];


        } // if ($params['nm_id'])  




        return $this->render('detail', [
            'AdvProvider' => $AdvProvider,
            'LastOrdersProvider' => $LastOrdersProvider,
            'LastOrdersChartData' => $LastOrdersChartData,
            'LastSalesProvider' => $LastSalesProvider,
            'LastSalesChartData' => $LastSalesChartData,
            'OrdersStats' => $OrdersStats,
            'OrderFunnel' => $OrderFunnel,
            'PivotDataProvider' => $PivotDataProvider,
            'pivotDates' => $pivotDates,
            'ChartformattedData' => $ChartformattedData,
            'WeeklyFinanceProvider' => $WeeklyFinanceProvider,

            'phraseDataProvider' => $phraseDataProvider,
            'uniqueDates' => $uniqueDates,
            
            'date_from' => $date_from,
            'date_to' => $date_to,
            'date_to14' => $date_to14,
            'dateFromWidget' => $dateFrom,
            'card' => $card,
        ]);

    } //actionDetail

}

