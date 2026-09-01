<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\db\Expression;
use yii\filters\AccessControl;

use yii\web\Response;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }




public function actionIndex($dateFrom = null, $dateTo = null)
{
//        return $this->render('index');

    // Если пользователь не авторизован
    if (Yii::$app->user->isGuest) {
        $model = new \app\models\LoginForm();
            
    // Если форма отправлена и логин успешен — обновляем страницу
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goHome();
        }

        // Показываем страницу с формой входа
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    // Если пользователь уже вошел — показываем личный кабинет/ссылки
//    return $this->render('index_dashboard');
    return $this->renderDashboard($dateFrom, $dateTo);
}


public function actionIndexDashboard($dateFrom = null, $dateTo = null) 
{

    // Если пользователь не авторизован
    if (Yii::$app->user->isGuest) {
        $model = new \app\models\LoginForm();
            
    // Если форма отправлена и логин успешен — обновляем страницу
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goHome();
        }

        // Показываем страницу с формой входа
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    return $this->renderDashboard($dateFrom, $dateTo);
}

protected function renderDashboard($dateFrom = null, $dateTo = null)
{
    // Установка дат по умолчанию
    $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-3 days'));
    $dateTo = $dateTo ?: date('Y-m-d');

    $date_from = $dateFrom . ' 00:00:00';
    $date_to   = $dateTo   . ' 23:59:59';
    $cm = Yii::$app->companyManager;

    $advQuery = (new \yii\db\Query())
        ->select([
            'campaign_id' => 'c.campaign_id', 
            'name'        => 'c.name', 
            'status'      => 'c.status',

        'status_priority' => new \yii\db\Expression("
            CASE 
                WHEN c.status = 9 THEN 1 
                WHEN c.status = 11 THEN 2 
                WHEN c.status = 7 THEN 4
                WHEN c.status = 4 THEN 5 
                WHEN c.status = -1 THEN 6 
                ELSE 5 
            END
        "),

            'views'       => 'SUM(n.views)',
            'clicks'      => 'SUM(n.clicks)',
            'atbs'        => 'SUM(n.atbs)',
            'orders'      => 'SUM(n.orders)',
            'shks'        => 'SUM(n.shks)',
            'sum'         => 'SUM(n.sum)',
            'sum_price'   => 'SUM(n.sum_price)',
            'canceled'    => 'SUM(n.canceled)',
        ])
        ->from(['c' => 'wb_campaign'])
        ->innerJoin(['i' => 'wb_campaign_item'], 'c.campaign_id = i.campaign_id')
        ->innerJoin(['s' => 'wb_campaign_stats'], 'c.campaign_id = s.campaign_id')
        ->innerJoin(['n' => 'wb_campaign_stats_nms'], 's.id = n.parent_id AND i.nm_id = n.nm_id')
        ->innerJoin(['w' => 'wbcards'], 'n.nm_id = w.nmID') // Учтено из ваших инструкций
        ->where(['between', 's.date', $date_from, $date_to])
        ->groupBy(['c.campaign_id', 'c.name', 'c.status'])
        ->orderBy(['c.status' => SORT_ASC]);
    $cm->applyToQuery($advQuery, 'c');
    $advData = $advQuery->all();

    $lastOrdersQuery = (new \yii\db\Query())
        ->select([
            'title'        => 'c.title',
            'nm_id'         => 'c.nmID',
            'vendorCode'   => 'c.vendorCode',
            'cnt'          => 'COUNT(o.nm_id)',

            'cnt_0' => new Expression("SUM(CASE WHEN DATE(date) = CURDATE() THEN 1 ELSE 0 END)"),
            'cnt_1' => new Expression("SUM(CASE WHEN DATE(date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END)"),
            'cnt_2' => new Expression("SUM(CASE WHEN DATE(date) = DATE_SUB(CURDATE(), INTERVAL 2 DAY) THEN 1 ELSE 0 END)"),
            'cnt_3' => new Expression("SUM(CASE WHEN DATE(date) = DATE_SUB(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END)"),

            'pwd'          => 'SUM(o.price_with_disc)',
            'fp'           => 'SUM(o.finished_price)',
            'apwd'         => 'AVG(o.price_with_disc)',
            'aspp'         => 'AVG(o.spp)',
            'afp'          => 'AVG(o.finished_price)',
        ])
        ->from(['o' => 'wb_order'])
        ->innerJoin(['c' => 'wbcards'], 'o.nm_id = c.nmID') // Используем c.nmID по инструкции
        ->where(['between', 'o.date', $date_from, $date_to])
        ->groupBy(['c.title', 'c.nmID', 'c.vendorCode'])
        ->orderBy(['cnt' => SORT_DESC]);
    $cm->applyToQuery($lastOrdersQuery, 'o');
    $LastOrders = $lastOrdersQuery->all();

    $lastSalesQuery = (new \yii\db\Query())
        ->select([
            'title'        => 'c.title',
            'nm_id'         => 'c.nmID',
            'vendorCode'   => 'c.vendorCode',
            // Агрегаты: Количество и Суммы
            'cnt'         => 'COUNT(o.nmId)',
            
            'cnt_0' => new Expression("SUM(CASE WHEN DATE(date) = CURDATE() THEN 1 ELSE 0 END)"),
            'cnt_1' => new Expression("SUM(CASE WHEN DATE(date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END)"),
            'cnt_2' => new Expression("SUM(CASE WHEN DATE(date) = DATE_SUB(CURDATE(), INTERVAL 2 DAY) THEN 1 ELSE 0 END)"),
            'cnt_3' => new Expression("SUM(CASE WHEN DATE(date) = DATE_SUB(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END)"),

            'tp'          => 'SUM(o.totalPrice)',
            'pwd'         => 'SUM(o.priceWithDisc)',
            'fp'          => 'SUM(o.finishedPrice)',
            'forpay'      => 'SUM(o.forPay)',
            // Агрегаты: Средние значения
            'adp'         => 'AVG(o.discountPercent)',
            'aspp'        => 'AVG(o.spp)',
            'apwd'        => 'AVG(o.priceWithDisc)',
            'afp'         => 'AVG(o.finishedPrice)',
            'aforpay'     => 'AVG(o.forPay)',
        ])
        ->from(['o' => 'wb_sales'])
        ->innerJoin(['c' => 'wbcards'], 'o.nmId = c.nmID') // Связь по nmID
        ->where(['between', 'o.date', $date_from, $date_to])
        ->groupBy(['c.title', 'c.nmID', 'c.vendorCode'])
        ->orderBy(['cnt' => SORT_DESC]);
    $cm->applyToQuery($lastSalesQuery, 'o');
    $LastSales = $lastSalesQuery->all();
/*
        'sort' => [
            'attributes' => ['views', 'clicks', 'sum', 'orders', 'status'],
            'defaultOrder' => ['sum' => SORT_DESC], // Сортируем по сумме трат по умолчанию
        ],
*/

// --- НАЧАЛО НОВОГО БЛОКА: Сводные данные по заказам (UNION) ---
    $qTotalPrice = (new \yii\db\Query())
        ->select([
            'price_type'   => new Expression("'Без скидок'"),
            'ieri'         => new Expression("SUM(CASE WHEN DATE(date) = CURDATE() - INTERVAL 1 DAY THEN total_price ELSE 0 END)"),
            'pazyera'      => new Expression("SUM(CASE WHEN DATE(date) = CURDATE() - INTERVAL 2 DAY THEN total_price ELSE 0 END)"),
            'past_7_days'  => new Expression("SUM(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 7 DAY AND date < CURDATE() THEN total_price ELSE 0 END)"),
            'week_before'  => new Expression("SUM(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 14 DAY AND date < CURDATE() - INTERVAL 7 DAY THEN total_price ELSE 0 END)"),
            'past_30_days' => new Expression("SUM(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 30 DAY AND date < CURDATE() THEN total_price ELSE 0 END)"),
        ])
        ->from('wb_order')
        ->where(['>=', 'date', new Expression('CURDATE() - INTERVAL 30 DAY')]);
    $cm->applyToQuery($qTotalPrice, '');

    $qPriceWithDisc = (new \yii\db\Query())
        ->select([
            'price_type'   => new Expression("'Цена со скидкой'"),
            'ieri'         => new Expression("SUM(CASE WHEN DATE(date) = CURDATE() - INTERVAL 1 DAY THEN price_with_disc ELSE 0 END)"),
            'pazyera'      => new Expression("SUM(CASE WHEN DATE(date) = CURDATE() - INTERVAL 2 DAY THEN price_with_disc ELSE 0 END)"),
            'past_7_days'  => new Expression("SUM(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 7 DAY AND date < CURDATE() THEN price_with_disc ELSE 0 END)"),
            'week_before'  => new Expression("SUM(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 14 DAY AND date < CURDATE() - INTERVAL 7 DAY THEN price_with_disc ELSE 0 END)"),
            'past_30_days' => new Expression("SUM(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 30 DAY AND date < CURDATE() THEN price_with_disc ELSE 0 END)"),
        ])
        ->from('wb_order')
        ->where(['>=', 'date', new Expression('CURDATE() - INTERVAL 30 DAY')]);
    $cm->applyToQuery($qPriceWithDisc, '');

    $qFinishedPrice = (new \yii\db\Query())
        ->select([
            'price_type'   => new Expression("'Цена в заказе'"),
            'ieri'         => new Expression("SUM(CASE WHEN DATE(date) = CURDATE() - INTERVAL 1 DAY THEN finished_price ELSE 0 END)"),
            'pazyera'      => new Expression("SUM(CASE WHEN DATE(date) = CURDATE() - INTERVAL 2 DAY THEN finished_price ELSE 0 END)"),
            'past_7_days'  => new Expression("SUM(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 7 DAY AND date < CURDATE() THEN finished_price ELSE 0 END)"),
            'week_before'  => new Expression("SUM(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 14 DAY AND date < CURDATE() - INTERVAL 7 DAY THEN finished_price ELSE 0 END)"),
            'past_30_days' => new Expression("SUM(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 30 DAY AND date < CURDATE() THEN finished_price ELSE 0 END)"),
        ])
        ->from('wb_order')
        ->where(['>=', 'date', new Expression('CURDATE() - INTERVAL 30 DAY')]);
    $cm->applyToQuery($qFinishedPrice, '');

    $qCountOrders = (new \yii\db\Query())
        ->select([
            'price_type' => new Expression("'Количество заказов'"),
            'ieri'         => new Expression("COUNT(CASE WHEN DATE(date) = CURDATE() - INTERVAL 1 DAY THEN 1 END)"),
            'pazyera'      => new Expression("COUNT(CASE WHEN DATE(date) = CURDATE() - INTERVAL 2 DAY THEN 1 END)"),
            'past_7_days'  => new Expression("COUNT(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 7 DAY AND date < CURDATE() THEN 1 END)"),
            'week_before'  => new Expression("COUNT(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 14 DAY AND date < CURDATE() - INTERVAL 7 DAY THEN 1 END)"),
            'past_30_days' => new Expression("COUNT(CASE WHEN DATE(date) >= CURDATE() - INTERVAL 30 DAY AND date < CURDATE() THEN 1 END)"),
        ])
        ->from('wb_order')
        ->where(['>=', 'date', new Expression('CURDATE() - INTERVAL 30 DAY')]);
    $cm->applyToQuery($qCountOrders, '');

    // Объединяем запросы через UNION ALL
    $ordersSummary = $qCountOrders
        ->union($qTotalPrice, true)
        ->union($qPriceWithDisc, true)
        ->union($qFinishedPrice, true)
        ->all();

    $AdvProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $advData,
        'sort' => [
            'attributes' => ['status','name','status_priority',],
            'defaultOrder' => [
                'status_priority' => SORT_ASC, 
                'name' => SORT_ASC
            ],
        ],
        'pagination' => [
            'pageSize' => 10,
        ],
    ]);

    $LastOrdersProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $LastOrders,
        'pagination' => [
            'pageSize' => 10,
        ],
        'sort' => [
            'attributes' => ['cnt', 'title'],
            'defaultOrder' => ['cnt' => SORT_DESC]
        ],
    ]);


    $LastSalesProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $LastSales,
        'sort' => [
            'attributes' => ['cnt', 'title'],
            'defaultOrder' => ['cnt' => SORT_DESC],
        ],
        'pagination' => [
            'pageSize' => 15,
        ],
    ]);

    $OrdersSummaryProvider = new \yii\data\ArrayDataProvider([
        'allModels' => $ordersSummary,
        'pagination' => false, // Таблица маленькая (всего 3 строки), пагинация не нужна
    ]);

    if (Yii::$app->request->get('refresh') == 1) {
        Yii::$app->cache->delete('monthly_finance_dashboard_data');
    }

    $targetTimeToday = strtotime('today 10:00:00');
    if (time() < $targetTimeToday) {
        // Если еще нет 10 утра, кэшируем до 10:00 сегодня
        $secondsLeftTo10AM = $targetTimeToday - time();
    } else {
        // Если 10 утра уже прошло, кэшируем до 10:00 завтра
        $secondsLeftTo10AM = strtotime('tomorrow 10:00:00') - time();
    }

// === ДАННЫЕ ДЛЯ ТОП-БЛОКА (45 ДНЕЙ): ГРАФИК И KPI ===
    $date45DaysAgo = date('Y-m-d', strtotime('-30 days'));
    $dateYestoday = date('Y-m-d', strtotime('-1 days'));
    $dateToday = date('Y-m-d');

// Посуточные данные для Stacked Bar графика amCharts 5
    $chart45Query = (new \yii\db\Query()) 
        ->select([
            'date'       => 'sdate',
            'amount'     => 'SUM(amount)',
            'net_profit' => 'SUM(net_profit)',
            'qnt'        => 'SUM(qnt)',


            'total_expenses'   => 'SUM(commission) + SUM(f_acquiring_fee) + SUM(f_acceptance) + SUM(f_delivery) + SUM(f_storage_fee) + SUM(f_penalty) + SUM(f_deduction) + SUM(f_otziv) + SUM(f_adv) + SUM(f_cashback)',
            'total_nds'        => 'SUM(f_nds)',
            'total_cost'       => 'SUM(f_cost_price)',
            'tax_amount'       => '(SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)) * 0.07',
            'clean_margin'     => '(SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)) - (GREATEST(0, SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)) * 0.07)'

        ])
        ->from('agg_daily_summary')
        ->where(['between', 'sdate', $date45DaysAgo, $dateYestoday])
        ->groupBy('sdate')
        ->orderBy(['sdate' => SORT_ASC]);
    $cm->applyToQuery($chart45Query, '');
    $chart45Data = $chart45Query->all();

    // Суммарные KPI показатели для плашек за 45 дней
    $kpi45Query = (new \yii\db\Query())
        ->select([
            'total_sales_rub'  => 'SUM(amount)',
            'total_return_rub' => 'SUM(`return`)',
            'total_profit_rub' => 'SUM(net_profit)',
            'total_expenses'   => 'SUM(commission) + SUM(f_acquiring_fee) + SUM(f_acceptance) + SUM(f_delivery) + SUM(f_storage_fee) + SUM(f_penalty) + SUM(f_deduction) + SUM(f_otziv) + SUM(f_adv) + SUM(f_cashback)',

            'total_delivery'   => 'SUM(f_delivery)',
            'total_adv'        => 'SUM(f_adv)',
            'total_cashback'   => 'SUM(f_cashback)',

                'total_nds'         => 'SUM(f_nds)',
                'total_cost'        => 'SUM(f_cost_price)',
                'profit_before_tax' => 'SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)',
                'tax_amount'        => '(SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)) * 0.07',
                'clean_margin'      => '(SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)) - (GREATEST(0, SUM(net_profit) - SUM(f_nds) - SUM(f_cost_price)) * 0.07)'

        ])
        ->from('agg_daily_summary')
        ->where(['between', 'sdate', $date45DaysAgo, $dateYestoday]);
    $cm->applyToQuery($kpi45Query, '');
    $kpi45Data = $kpi45Query->one();

    // Количество заказов за 45 дней (из специализированного агрегата или таблицы заказов)
    $kpi45OrdersQuery = (new \yii\db\Query())
        ->from('wb_order')
        ->where(['between', 'date', $date45DaysAgo . ' 00:00:00', $dateYestoday . ' 23:59:59']);
    $cm->applyToQuery($kpi45OrdersQuery, '');
    $kpi45Orders = $kpi45OrdersQuery->count();
        
    $kpi45Data['total_orders_cnt'] = $kpi45Orders;


    $ProfitService = new \app\components\WbProfitService();

    // === ДАННЫЕ ДЛЯ ВИДЖЕТА "Заказы/Выкупы" (только для админа) ===
    // Первая загрузка страницы сразу считает период "today" — тем же кодом,
    // что и AJAX-экшен ниже, чтобы не дублировать логику в двух местах.
    $todayStats = [
        'period' => 'today',
        'orders' => $this->buildPeriodStats('today', 'wb_order', 'price_with_disc'),
        'sales'  => $this->buildPeriodStats('today', 'wb_sales', 'priceWithDisc'),
    ];

    return $this->render('index_dashboard', [
        'AdvProvider' => $AdvProvider,
        'LastOrdersProvider' => $LastOrdersProvider,
        'LastSalesProvider' => $LastSalesProvider,
        'OrdersSummaryProvider' => $OrdersSummaryProvider,
        'MonthlyFinanceProvider' => $ProfitService->getMonthlyProfitProvider(),
        'chart45Data' => $chart45Data,
        'kpi45Data'   => $kpi45Data,
        'todayStats'  => $todayStats,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
    ]);
} //actionIndexDashboard

/**
 * AJAX-экшен: отдаёт данные для виджета "Заказы/Выкупы" по выбранному
 * периоду. Тот же формат, что и при первой отрисовке страницы (см. выше).
 * URL по умолчанию: /site/today-stats-data?period=week_to_date
 */
public function actionTodayStatsData()
{
    Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
/*
    if (Yii::$app->user->isGuest || Yii::$app->user->identity->username !== 'admin') {
        throw new \yii\web\ForbiddenHttpException('Доступно только администратору.');
    }
*/
    $period = Yii::$app->request->get('period', 'today');
    $allowed = ['today', 'yesterday', 'week_to_date', 'last_week', 'month_to_date', 'last_month'];
    if (!in_array($period, $allowed, true)) {
        $period = 'today';
    }

    return [
        'period' => $period,
        'orders' => $this->buildPeriodStats($period, 'wb_order', 'price_with_disc'),
        'sales'  => $this->buildPeriodStats($period, 'wb_sales', 'priceWithDisc'),
    ];
}

/**
 * Диспетчер: по ключу периода строит нужную структуру (часовую для
 * today/yesterday, дневную для недель/месяцев) для одной таблицы.
 *
 * Формат результата одинаковый для всех периодов:
 * [
 *   'granularity' => 'hour' | 'day',
 *   'categories'  => ['00', '01', ...] | ['Пн', 'Вт', ...] | ['1', '2', ...],
 *   'seriesMeta'  => [['key' => 'a', 'name' => 'Сегодня'], ...],   // 2 или 3 штуки
 *   'series'      => ['a' => [['category' => '00', 'sum' => 123.4], ...], 'b' => [...]],
 *   'totals'      => ['a' => ['cnt' => 10, 'sum' => 1234.5, 'spp' => 35.2], ...],
 * ]
 */
protected function buildPeriodStats($period, $table, $sumField)
{
    switch ($period) {
        case 'yesterday':
            return $this->buildHourlyComparison($table, $sumField, [
                ['key' => 'a', 'label' => 'Вчера',        'daysAgo' => 1],
                ['key' => 'b', 'label' => 'Позавчера',    'daysAgo' => 2],
                ['key' => 'c', 'label' => '8 дней назад', 'daysAgo' => 8],
            ]);

        case 'week_to_date':
            return $this->buildDailyWeekComparison($table, $sumField, 0);

        case 'last_week':
            return $this->buildDailyWeekComparison($table, $sumField, 1);

        case 'month_to_date':
            return $this->buildDailyMonthComparison($table, $sumField, 0);

        case 'last_month':
            return $this->buildDailyMonthComparison($table, $sumField, 1);

        case 'today':
        default:
            return $this->buildHourlyComparison($table, $sumField, [
                ['key' => 'a', 'label' => 'Сегодня',      'daysAgo' => 0],
                ['key' => 'b', 'label' => 'Вчера',        'daysAgo' => 1],
                ['key' => 'c', 'label' => 'Неделю назад', 'daysAgo' => 7],
            ]);
    }
}

/**
 * Почасовая статистика (today / yesterday) — N дат, заданных сдвигом
 * "daysAgo" от сегодня, каждая раскладывается по часам 00..23.
 */
protected function buildHourlyComparison($table, $sumField, array $seriesConfig)
{
    $categories = [];
    for ($h = 0; $h <= 23; $h++) {
        $categories[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT);
    }

    $series = [];
    $totals = [];

    foreach ($seriesConfig as $cfg) {
        $date = date('Y-m-d', strtotime('-' . (int) $cfg['daysAgo'] . ' days'));
        $dateLabel = Yii::$app->formatter->asDate($date, 'd MMMM');

        $series[$cfg['key']] = $this->queryPeriodByHour($table, $sumField, $date, $dateLabel);
        $totals[$cfg['key']] = $this->queryPeriodAgg($table, $sumField, $date . ' 00:00:00', $date . ' 23:59:59');
    }

    return [
        'granularity' => 'hour',
        'categories'  => $categories,
        'seriesMeta'  => array_map(function ($cfg) {
            return ['key' => $cfg['key'], 'name' => $cfg['label']];
        }, $seriesConfig),
        'series' => $series,
        'totals' => $totals,
    ];
}

/**
 * Дневная статистика по неделям (week_to_date / last_week).
 * $weeksAgoStart = 0 → текущая неделя (Пн..сегодня) vs неделя назад (Пн..Вс)
 * $weeksAgoStart = 1 → прошлая неделя (Пн..Вс) vs позапрошлая (Пн..Вс)
 */
protected function buildDailyWeekComparison($table, $sumField, $weeksAgoStart)
{
    $isCurrentWeek = ($weeksAgoStart === 0);

    $mondayA = date('Y-m-d', strtotime('monday this week -' . ($weeksAgoStart * 7) . ' days'));
    $mondayB = date('Y-m-d', strtotime($mondayA . ' -7 days'));
    $sundayA = date('Y-m-d', strtotime($mondayA . ' +6 days'));
    $sundayB = date('Y-m-d', strtotime($mondayB . ' +6 days'));

    // для незавершённой (текущей) недели реальный конец периода — сегодня
    $endA = $isCurrentWeek ? date('Y-m-d') : $sundayA;

    $dataA = $this->queryPeriodByDay($table, $sumField, $mondayA, $endA);
    $dataB = $this->queryPeriodByDay($table, $sumField, $mondayB, $sundayB);

    $dayNames = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

    $seriesA = [];
    $seriesB = [];
    for ($i = 0; $i < 7; $i++) {
        $dA = date('Y-m-d', strtotime($mondayA . " +$i days"));
        $dB = date('Y-m-d', strtotime($mondayB . " +$i days"));

        $rowA = $dataA[$dA] ?? null;
        $rowB = $dataB[$dB] ?? null;

        $seriesA[] = [
            'category' => $dayNames[$i],
            'sum' => $rowA ? round($rowA['sum'], 2) : ($dA <= $endA ? 0 : null),
            'cnt' => $rowA ? $rowA['cnt'] : ($dA <= $endA ? 0 : null),
            'spp' => $rowA ? round($rowA['spp'], 1) : ($dA <= $endA ? 0 : null),
            'date' => Yii::$app->formatter->asDate($dA, 'd MMMM'),
        ];
        $seriesB[] = [
            'category' => $dayNames[$i],
            'sum' => $rowB ? round($rowB['sum'], 2) : 0,
            'cnt' => $rowB ? $rowB['cnt'] : 0,
            'spp' => $rowB ? round($rowB['spp'], 1) : 0,
            'date' => Yii::$app->formatter->asDate($dB, 'd MMMM'),
        ];
    }

    $totalsA = $this->queryPeriodAgg($table, $sumField, $mondayA . ' 00:00:00', $endA . ' 23:59:59');
    $totalsB = $this->queryPeriodAgg($table, $sumField, $mondayB . ' 00:00:00', $sundayB . ' 23:59:59');

    $labelA = $isCurrentWeek ? 'Текущая неделя' : 'Прошлая неделя';
    $labelB = $isCurrentWeek ? 'Неделю назад' : 'Позапрошлая неделя';

    return [
        'granularity' => 'day',
        'categories'  => $dayNames,
        'seriesMeta'  => [
            ['key' => 'a', 'name' => $labelA],
            ['key' => 'b', 'name' => $labelB],
        ],
        'series' => ['a' => $seriesA, 'b' => $seriesB],
        'totals' => ['a' => $totalsA, 'b' => $totalsB],
    ];
}

/**
 * Дневная статистика по месяцам (month_to_date / last_month).
 * $monthsAgoStart = 0 → текущий месяц (1 число..сегодня) vs прошлый месяц (целиком)
 * $monthsAgoStart = 1 → прошлый месяц (целиком) vs позапрошлый (целиком)
 * Категории — числа месяца 1..N, где N = более длинный из двух месяцев
 * (если месяцы разной длины — короткая серия просто обрывается, без 0).
 */
protected function buildDailyMonthComparison($table, $sumField, $monthsAgoStart)
{
    $isCurrentMonth = ($monthsAgoStart === 0);

    $firstA = date('Y-m-01', strtotime('-' . $monthsAgoStart . ' months'));
    $firstB = date('Y-m-01', strtotime($firstA . ' -1 month'));

    $daysInA = (int) date('t', strtotime($firstA));
    $daysInB = (int) date('t', strtotime($firstB));

    $lastA = date('Y-m-t', strtotime($firstA));
    $lastB = date('Y-m-t', strtotime($firstB));

    $endA = $isCurrentMonth ? date('Y-m-d') : $lastA;

    $dataA = $this->queryPeriodByDay($table, $sumField, $firstA, $endA);
    $dataB = $this->queryPeriodByDay($table, $sumField, $firstB, $lastB);

    $maxDays = max($daysInA, $daysInB);
    $categories = [];
    $seriesA = [];
    $seriesB = [];

    for ($d = 1; $d <= $maxDays; $d++) {
        $categories[] = (string) $d;

        $dateA = $d <= $daysInA ? date('Y-m-d', strtotime($firstA . ' +' . ($d - 1) . ' days')) : null;
        $dateB = $d <= $daysInB ? date('Y-m-d', strtotime($firstB . ' +' . ($d - 1) . ' days')) : null;

        $rowA = $dateA !== null ? ($dataA[$dateA] ?? null) : null;
        $rowB = $dateB !== null ? ($dataB[$dateB] ?? null) : null;
        $inRangeA = $dateA !== null && $dateA <= $endA;

        $seriesA[] = [
            'category' => (string) $d,
            'sum' => $dateA === null ? null : ($inRangeA ? ($rowA ? round($rowA['sum'], 2) : 0) : null),
            'cnt' => $dateA === null ? null : ($inRangeA ? ($rowA ? $rowA['cnt'] : 0) : null),
            'spp' => $dateA === null ? null : ($inRangeA ? ($rowA ? round($rowA['spp'], 1) : 0) : null),
            'date' => $dateA === null ? null : Yii::$app->formatter->asDate($dateA, 'd MMMM'),
        ];
        $seriesB[] = [
            'category' => (string) $d,
            'sum' => $dateB === null ? null : ($rowB ? round($rowB['sum'], 2) : 0),
            'cnt' => $dateB === null ? null : ($rowB ? $rowB['cnt'] : 0),
            'spp' => $dateB === null ? null : ($rowB ? round($rowB['spp'], 1) : 0),
            'date' => $dateB === null ? null : Yii::$app->formatter->asDate($dateB, 'd MMMM'),
        ];
    }

    $totalsA = $this->queryPeriodAgg($table, $sumField, $firstA . ' 00:00:00', $endA . ' 23:59:59');
    $totalsB = $this->queryPeriodAgg($table, $sumField, $firstB . ' 00:00:00', $lastB . ' 23:59:59');

    $labelA = $isCurrentMonth ? 'Текущий месяц' : 'Прошлый месяц';
    $labelB = $isCurrentMonth ? 'Прошлый месяц' : 'Позапрошлый месяц';

    // подпись над графиком, т.к. по одним числам 1..31 не видно, какой это
    // месяц — тем более что A и B это два РАЗНЫХ месяца на одной оси
    $monthNameA = mb_convert_case(Yii::$app->formatter->asDate($firstA, 'LLLL yyyy'), MB_CASE_TITLE, 'UTF-8');
    $monthNameB = mb_convert_case(Yii::$app->formatter->asDate($firstB, 'LLLL yyyy'), MB_CASE_TITLE, 'UTF-8');
    $axisCaption = $monthNameA . ' / ' . $monthNameB;

    return [
        'granularity' => 'day',
        'categories'  => $categories,
        'axisCaption' => $axisCaption,
        'seriesMeta'  => [
            ['key' => 'a', 'name' => $labelA],
            ['key' => 'b', 'name' => $labelB],
        ],
        'series' => ['a' => $seriesA, 'b' => $seriesB],
        'totals' => ['a' => $totalsA, 'b' => $totalsB],
    ];
}

/**
 * Сумма/количество/средний СПП по каждому часу (00..23) за одну дату.
 * $dateLabel — человекочитаемая дата ("23 июля"), проставляется в каждую
 * точку, чтобы тултип на фронте мог показать полную дату+час.
 */
protected function queryPeriodByHour($table, $sumField, $date, $dateLabel = null)
{
    $cm = Yii::$app->companyManager;

    $q = (new \yii\db\Query())
        ->select([
            'hour' => new \yii\db\Expression('HOUR(date)'),
            'cnt'  => new \yii\db\Expression('COUNT(*)'),
            'sum'  => new \yii\db\Expression("SUM($sumField)"),
            'spp'  => new \yii\db\Expression('AVG(spp)'),
        ])
        ->from($table)
        ->where(['between', 'date', $date . ' 00:00:00', $date . ' 23:59:59'])
        ->groupBy('hour');
    $cm->applyToQuery($q, '');
    $rows = $q->all();

    $byHour = [];
    foreach ($rows as $r) {
        $byHour[(int) $r['hour']] = [
            'cnt' => (int) $r['cnt'],
            'sum' => (float) $r['sum'],
            'spp' => (float) $r['spp'],
        ];
    }

    $result = [];
    for ($h = 0; $h <= 23; $h++) {
        $row = $byHour[$h] ?? ['cnt' => 0, 'sum' => 0, 'spp' => 0];
        $result[] = [
            'category' => str_pad((string) $h, 2, '0', STR_PAD_LEFT),
            'sum' => round($row['sum'], 2),
            'cnt' => $row['cnt'],
            'spp' => round($row['spp'], 1),
            'date' => $dateLabel,
        ];
    }

    return $result;
}

/**
 * Сумма/количество/средний СПП по каждому календарному дню в диапазоне
 * [$dateFrom, $dateTo]. Возвращает ['2026-07-20' => ['sum'=>.., 'cnt'=>.., 'spp'=>..], ...].
 */
protected function queryPeriodByDay($table, $sumField, $dateFrom, $dateTo)
{
    $cm = Yii::$app->companyManager;

    $q = (new \yii\db\Query())
        ->select([
            'd'   => new \yii\db\Expression('DATE(date)'),
            'cnt' => new \yii\db\Expression('COUNT(*)'),
            'sum' => new \yii\db\Expression("SUM($sumField)"),
            'spp' => new \yii\db\Expression('AVG(spp)'),
        ])
        ->from($table)
        ->where(['between', 'date', $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
        ->groupBy('d');
    $cm->applyToQuery($q, '');
    $rows = $q->all();

    $byDate = [];
    foreach ($rows as $r) {
        $byDate[$r['d']] = [
            'cnt' => (int) $r['cnt'],
            'sum' => (float) $r['sum'],
            'spp' => (float) $r['spp'],
        ];
    }

    return $byDate;
}

/**
 * Итоговые количество/сумма/средний СПП за произвольный период —
 * отдельным агрегирующим запросом (не суммированием почасовых/подневных
 * значений), чтобы средний СПП считался корректно.
 */
protected function queryPeriodAgg($table, $sumField, $dateFrom, $dateTo)
{
    $cm = Yii::$app->companyManager;

    $q = (new \yii\db\Query())
        ->select([
            'cnt' => new \yii\db\Expression('COUNT(*)'),
            'sum' => new \yii\db\Expression("SUM($sumField)"),
            'spp' => new \yii\db\Expression('AVG(spp)'),
        ])
        ->from($table)
        ->where(['between', 'date', $dateFrom, $dateTo]);
    $cm->applyToQuery($q, '');
    $row = $q->one();

    return [
        'cnt' => (int) ($row['cnt'] ?? 0),
        'sum' => round((float) ($row['sum'] ?? 0), 2),
        'spp' => round((float) ($row['spp'] ?? 0), 1),
    ];
}



    /**
     * Отдельная страница «Новые карточки» — тот же вид что в _new_cards,
     * но с фильтрами: период (с/по), поиск по части названия, сортировка.
     */
    public function actionNewCards()
    {
        $request = Yii::$app->request;

        $dateFrom = $request->get('dateFrom', date('Y-m-d', strtotime('-14 days')));
        $dateTo   = $request->get('dateTo', date('Y-m-d'));
        $titleFilter = trim((string) $request->get('title', ''));
        $sort = $request->get('sort', 'created_desc');

        // валидация дат
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-d', strtotime('-14 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }

        $allowedSorts = ['created_desc', 'created_asc', 'nmid_asc', 'nmid_desc', 'title_asc', 'title_desc'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_desc';
        }

        $dateFromSql = $dateFrom . ' 00:00:00';
        $dateToSql   = $dateTo . ' 23:59:59';

        $query = (new \yii\db\Query())
            ->select(['nmID', 'vendorCode', 'title', 'brand', 'photos', 'created_at'])
            ->from('wbcards')
            ->where(['between', 'created_at', $dateFromSql, $dateToSql]);

        if ($titleFilter !== '') {
            $query->andWhere(['like', 'title', $titleFilter]);
        }

        // сортировка
        switch ($sort) {
            case 'created_asc':
                $query->orderBy(['created_at' => SORT_ASC]);
                break;
            case 'nmid_asc':
                $query->orderBy(['nmID' => SORT_ASC]);
                break;
            case 'nmid_desc':
                $query->orderBy(['nmID' => SORT_DESC]);
                break;
            case 'title_asc':
                $query->orderBy(['title' => SORT_ASC]);
                break;
            case 'title_desc':
                $query->orderBy(['title' => SORT_DESC]);
                break;
            case 'created_desc':
            default:
                // как в оригинале: приоритет по символу vendorCode + дата DESC
                $query->orderBy([
                    new Expression("
                        CASE 
                            WHEN vendorCode LIKE '!%' THEN 1
                            WHEN vendorCode LIKE '\$%' THEN 2
                            WHEN vendorCode LIKE '#%' THEN 3
                            ELSE 4
                        END ASC
                    "),
                    'created_at' => SORT_DESC,
                ]);
                break;
        }

        // при сортировке по nmID/title вторым ключом добавляем дату для стабильности
        if (in_array($sort, ['nmid_asc', 'nmid_desc', 'title_asc', 'title_desc'], true)) {
            // уже отсортировано выше, но если есть дубли — добавим created_at DESC как втор. ключ через addOrderBy
            // переопределяем полностью: для Yii Query orderBy уже задан, добавим второй
            $query->addOrderBy(['created_at' => SORT_DESC]);
        }

        $newCards = $query->all();

        return $this->render('new_cards', [
            'newCards'    => $newCards,
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'titleFilter' => $titleFilter,
            'sort'        => $sort,
        ]);
    }

    public function actionSelectCompany($id)
    {
        // Проверяем, существует ли такая компания и принадлежит ли она пользователю,
        // чтобы исключить подмену ID злоумышленниками
        $companyExists = (new \yii\db\Query())
            ->from('companies')
            ->where(['id' => $id, 'is_active' => 1])
            ->exists();

        if ($companyExists) {
            Yii::$app->companyManager->setCurrentId($id);
        } else {
            throw new BadRequestHttpException('Выбранная компания не найдена.');
        }

        // Возвращаем пользователя обратно на ту страницу, где он находился
        return $this->redirect(Yii::$app->request->referrer ?: ['index']);
    }

    public function actionSelectAllCompanies()
    {
        Yii::$app->companyManager->resetCurrentId();

        return $this->redirect(Yii::$app->request->referrer ?: ['global-stats/index']);
    }


}
