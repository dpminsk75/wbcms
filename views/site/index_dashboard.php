<?php
use app\assets\AppAsset;
AppAsset::register($this);

use yii\helpers\Html;
use yii\helpers\Url;
use kartik\grid\GridView;
use yii\widgets\Menu;

use kartik\icons\Icon;
Icon::map($this); 


$this->title = 'Управление товарами и карточками';

$isFbsOnly = !Yii::$app->user->isGuest && Yii::$app->user->can('manageFbsStocks') && !Yii::$app->user->can('viewReports') && !Yii::$app->user->can('admin') && !Yii::$app->user->can('viewOrders');


    $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-3 days'));
    $dateTo = $dateTo ?: date('Y-m-d');

/*             <h1>Добро пожаловать, <?= Yii::$app->user->identity->username ?>!</h1>*/
?>
<?php
$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', [
    'depends' => [\app\assets\ChartAsset::class]
]);
?>
<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

<div class="site-index">
    <div class="row" style="margin-bottom: 20px;">
        <div class="nav_div col-md-2">
            <div class="list-group">
            <?php 
                echo Menu::widget([
                    'options' => ['class' => 'side-menu-list'],
                    'items' => \app\components\MenuHelper::getMenuItems('side'), // Без иконок
                ]);
            ?>
            </div>
            <div class="sidebar_img">
                <img src="/_icons/logo_300px.png" alt="Товары для WB" style="vertical-align: middle;">
            </div>
        </div>
        <div class="dash_div col-md-10">

<?php if (!$isFbsOnly): ?>
<div class="mobile-hide-block">
<?php 
// Выводим блок с новыми карточками и виджет "Сегодня" только для администратора
if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin') {
    echo $this->render('_new_cards');
//    echo $this->render('_today_stats_widget', ['todayStats' => $todayStats]);
}
?>

<?= 
    $this->render('_dashboard_top_metrics', [
                'chart45Data' => $chart45Data,
                'kpi45Data'   => $kpi45Data,
            ]) 
?>
</div>
<?php endif; ?>


<?php
$statusMap = [
    -1 => ['label' => 'Удалена', 'class' => 'label-default'],
    4  => ['label' => 'Готова к запуску', 'class' => 'label-info'],
    7  => ['label' => 'Завершена', 'class' => 'label-primary'],
    8  => ['label' => 'Отклонена', 'class' => 'label-danger'],
    9  => ['label' => 'Активна', 'class' => 'label-success'],
    11 => ['label' => 'Пауза', 'class' => 'label-warning'],
];

    $allStats = $AdvProvider->allModels;
    $totalViews = array_sum(array_column($allStats, 'views'));
    $totalClicks = array_sum(array_column($allStats, 'clicks'));
    $totalSum = array_sum(array_column($allStats, 'sum'));
    $totalOrders = array_sum(array_column($allStats, 'orders'));
    $totalCanceled = array_sum(array_column($allStats, 'canceled'));
    $totalOC = $totalOrders - $totalCanceled;

    $totalCtr = $totalViews > 0 ? number_format(($totalClicks / $totalViews) * 100, 2) . '%' : '0.00%';
    $totalCPM = $totalOrders > 0 ? number_format(($totalSum / $totalViews * 1000),2) : '';
    $totalCPC = $totalOrders > 0 ? number_format(($totalSum / $totalClicks),2) : '';
    $totalCPO = $totalOC > 0 ? number_format(($totalSum / $totalOC),2) : '';
?>
<?php
$columns = [
            [
                'attribute' => 'name',
                'label' => 'Компания',
                'format' => 'raw', 
                'headerOptions'  => ['style' => 'width:200px; text-align: center;'],
                'contentOptions' => ['style' => 'width:200px; white-space: nowrap; align-content: center; text-align: left;'],
                'value' => function($model) {
                    if (!$model['name']) {
                        return null;
                    }
                    // Генерируем ссылку
                    return Html::a(
                        (string)$model['name'], 
                        "/wb-adv-report/index?id=" . $model['campaign_id'], 
//                        /wb-adv-report/index?id=33656377&dateFrom=2026-02-04&dateTo=2026-02-18
                        [
                            'target' => '_blank',
                            'data-pjax' => '0', 
                            'style' => 'text-decoration: none;'
                        ]
                    );
                },

            ],
                [
                    'attribute' => 'status',
                    'label' => 'Ст',
                    'headerOptions'  => ['style' => 'width:50px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:50px; white-space: nowrap; align-content: center; text-align: center;'],
                    'hAlign' => 'right',
                    'format' => 'raw',
                    'value' => function($model) {
                        // Просто вызываем метод из модели
                        return \app\models\WbCampaign::renderStatusLabel($model['status']);
                    }
                ],
                [
                    'attribute' => 'views',
                    'label' => 'Показы',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true, // Просто сумма
                ],
                [
                    'attribute' => 'clicks',
                    'label' => 'Клики',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true, // Просто сумма
                ],
                [
                    'attribute' => 'atbs',
                    'label' => 'Корзины',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true, // Просто сумма
                ],
                [
                    'attribute' => 'orders',
                    'label' => 'Заказы',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'canceled',
                    'label' => 'Отмена',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                ],

                [
                    'attribute' => 'ctr',
                    'label' => 'CTR, %',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'value' => function ($model) {
                        return $model['views'] > 0 ? round(($model['clicks']  / $model['views']) * 100, 2) : '';
        //                return number_format($model['ctr'], 2) . '%';
                    },
                    'hAlign' => 'right',
                    'pageSummary' => $totalCtr, // Используем заранее вычисленное значение
                ],
                [
                    'attribute' => 'cr',
                    'label' => 'CR, %',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'value' => function ($model) {
                        return $model['clicks'] > 0 ? round(($model['atbs']  / $model['clicks']) * 100, 2) : '';
        //                return number_format($model['cr'], 2) . '%';
                    },
                    'hAlign' => 'right',
                    'pageSummary' => $totalCtr, // Используем заранее вычисленное значение
                ],
                [
                    'attribute' => 'sum',
                    'label' => 'Затраты, ₽',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],

                ],
                [
                    'label' => 'CPM, ₽', 
                    'format' => ['decimal', 2],
                    'pageSummary' => $totalCPM, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model['views'] > 0 ? round(($model['sum']  / $model['views'])*1000, 2) : '';
                    }
                ],
                [
                    'label' => 'CPC, ₽', 
                    'format' => ['decimal', 2],
                    'pageSummary' => $totalCPC, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model['clicks'] > 0 ? round(($model['sum']  / $model['clicks']), 2) : '';
                    }
                ],
                [
                    'label' => 'CPO ₽', 
                    'format' => ['decimal', 2],
                    'pageSummary' => $totalCPO, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model['orders'] > 0 ? round(($model['sum']  / $model['orders'] ), 2) : '';
                    }
                ],
    ];
?>

<?php if (!$isFbsOnly): ?>
<div class="mobile-hide-block">
<div class="row grid_advstat grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_65 expandable-container">
<?php
echo GridView::widget([
    'dataProvider' => $AdvProvider,
        'export' => false, 
        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,
        'showPageSummary' => false,
        'showFooter' => false,

        'toggleData' => true,
//        'layout' => "{summary}\n{items}\n{pager}",
//        'pager' => [
//            'options' => ['class' => 'pagination'],
//            'maxButtonCount' => 5,
//        ],

        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Реклама (c '.Yii::$app->formatter->asDate($dateFrom, 'd MMM y').')',
            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            'footer' => false,
            'after' => false,
//            'after' => '<div class="float-right">{pager}</div>',
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],

    'columns' => $columns,
]);
?>
</div>
<div class="expand-btn-wrapper">
    <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
</div>
</div>
<?php endif; ?>


        <div class="mobile-hide-block">
        <div class="row grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_65" style="margin-bottom: 25px;">
        <?php
        echo GridView::widget([
            'dataProvider' => $OrdersSummaryProvider,
            'summary' => false,
            'export' => false, 
            'pjax' => false,
            'bordered' => true,
            'striped' => true,
            'condensed' => true,
            'responsive' => true,
            'hover' => true,
            'showPageSummary' => false,
            'showFooter' => false,
            'toggleData' => false,
            'panel' => [
                'type' => GridView::TYPE_PRIMARY,
                'heading' => 'Сумма заказов за последние 30 дней',
                'headingOptions' => ['class' => 'card-header text-white bg-wb-blue-header'],
                'before' => false,
                'after' => false,
                'footer' => false,
//                'footer' => false,
            ],
            'containerOptions' => [
                'class' => 'no-border-class' 
            ],
            'columns' => [
                [
                    'attribute' => 'price_type',
                    'label' => 'Показатель',
                    'format' => 'raw',
                    'headerOptions' => ['style' => 'text-align: left; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'text-align: left; color: #2c3e50; vertical-align: middle; width:200px;'],
                ],
                [
                    'attribute' => 'ieri',
                    'label' => 'Вчера',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'width: 140px; text-align: center;'],
                    'contentOptions' => ['style' => 'vertical-align: middle;'],
                    'value' => function($model) { return $model['ieri'] ?? 0; }
                ],
                [
                    'attribute' => 'pazyera',
                    'label' => 'Позавчера',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'width: 140px; text-align: center;'],
                    'contentOptions' => ['style' => 'vertical-align: middle;'],
                    'value' => function($model) { return $model['pazyera'] ?? 0; }
                ],
                [
                    'attribute' => 'past_7_days',
                    'label' => 'Прошедшие 7 дней',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'width: 150px; text-align: center;'],
                    'contentOptions' => ['style' => 'vertical-align: middle;'],
                    'value' => function($model) { return $model['past_7_days'] ?? 0; }
                ],
                [
                    'attribute' => 'week_before',
                    'label' => 'Предыдущая неделя',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'width: 150px; text-align: center;'],
                    'contentOptions' => ['style' => 'color: #666; vertical-align: middle;'],
                    'value' => function($model) { return $model['week_before'] ?? 0; }
                ],
                [
                    'attribute' => 'past_30_days',
                    'label' => 'Прошедшие 30 дней',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'width: 150px; text-align: center;'],
                    'contentOptions' => ['style' => 'color: #2c3e50; vertical-align: middle;'],
                    'value' => function($model) { return $model['past_30_days'] ?? 0; }
                ],
            ],
        ]);
        ?>
        </div>
        </div>

<?php if (!$isFbsOnly): ?>
<?php
//if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin') {
    echo $this->render('_today_stats_widget', ['todayStats' => $todayStats]);
//}
?>



<?php
    $allStatsLO = $LastOrdersProvider->allModels;

    $totalLO_PWD = array_sum(array_column($allStatsLO, 'pwd'));
    $totalLO_CNT = array_sum(array_column($allStatsLO, 'cnt'));
    $totalLO_FP  = array_sum(array_column($allStatsLO, 'fp'));

?>
<?php
/*
            [
                'attribute' => 'nm_id',
                'label' => 'Арт WB',
                'format' => 'raw', 
                'headerOptions'  => ['style' => 'width:90px; text-align: center;'],
                'contentOptions' => ['style' => 'width:90px; white-space: nowrap; align-content: center; text-align: center;'],
                'value' => function($model) {
                    if (!$model['nm_id']) {
                        return null;
                    }
                    // Генерируем ссылку
                    return Html::a((string)$model['nm_id'], "https://www.wildberries.ru/catalog/" . $model['nm_id'] . "/detail.aspx", [ 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ]);
                },
            ],
*/

$columnsLO = [
            [
                    'attribute' => 'cardTitle',
                    'label' => 'Товар / Артикул',
//                    'headerOptions'  => ['style' => 'width:420px'],
//                    'contentOptions' => ['style' => 'min-width:420px; white-space: wrap; '],
                    'headerOptions'  => ['class' => 'mobile-column-420'],
                    'contentOptions' => ['class' => 'mobile-column-420'],
                    'format' => 'raw',
/*
                    'value' => function($model) {
                        // Верхний уровень: Название товара
                        $title = Html::tag('div', $model['title'] ?? '—', [
                            'style' => 'font-weight: bold; font-size: 12px; margin-bottom: 2px; color: #2c3e50;'
                        ]);
                        $details = Html::tag('div', 
                            "Артикул: <b>{$model['vendorCode']}</b> Арт WB: ". 
                            Html::a((string)$model['nm_id'], "/wb/detail?DPFilterForm[nm_id]=".$model['nm_id'], ['title' => 'Перейти в карточку', 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ])
//                            http://31.130.204.146/wb-order/index?WbOrderSearch[nm_id]=50308273
                            ,
                            ['style' => 'color: #666; font-size: 11px;']
                        );

                        return $title . $details;
                    },
*/

                        'value' => function($model) {

// 1. Заголовок
    $title = Html::tag('div', $model['title'] ?? '—', [
        'class' => 'cart-item-title'
    ]);

    // 2. Артикул seller
    $vendor = Html::tag('span', "Артикул: <b>{$model['vendorCode']}</b>", [
        'class' => 'd-block d-md-inline-block me-md-2 mr-md-2',
        'style' => 'white-space: nowrap;'
    ]);

    // 3. Ссылка WB
    $wbLink = Html::a((string)$model['nm_id'], "/wb/detail?DPFilterForm[nm_id]=".$model['nm_id'], [
        'title' => 'Перейти в карточку',
        'target' => '_blank',
        'data-pjax' => '0',
        'style' => 'text-decoration: none;'
    ]);

    // 4. Арт WB
    $wb = Html::tag('span', "Арт WB: {$wbLink}", [
        'class' => 'd-block d-md-inline-block',
        'style' => 'white-space: nowrap;'
    ]);

    // 5. Блок артикулов
    $details = Html::tag('div', $vendor . $wb, [
        'class' => 'cart-item-details'
    ]);

    return $title . $details;


                        },  
/*
                    'pageSummary' => function($cnt, $pwd, $fp) use ($totalLO_CNT, $totalLO_PWD, $totalLO_FP)  {
                        return "Итого <b>$totalLO_CNT</b> зак | Сумма в РЦ: <b>" . number_format($totalLO_PWD, 1, ',', ' ') . "</b> | Заказы: <b>".number_format($totalLO_FP, 1, ',', ' ')."</b>";
                    }, 'pageSummaryOptions' => ['class' => 'text-right'],
*/

'pageSummary' => function($cnt, $pwd, $fp) use ($totalLO_CNT, $totalLO_PWD, $totalLO_FP) {
    // 1. Формируем текстовые части
    $p1 = "Итого <b>$totalLO_CNT</b> зак";
    $p2 = "Сумма в РЦ: <b>" . number_format($totalLO_PWD, 1, ',', ' ') . "</b>";
    $p3 = "Заказы: <b>" . number_format($totalLO_FP, 1, ',', ' ') . "</b>";

    // 2. Оборачиваем каждую часть в адаптивный span с запретом разрыва внутри фразы
    $part1 = Html::tag('span', $p1, [
        'class' => 'd-block d-md-inline-block',
        'style' => 'white-space: nowrap;'
    ]);
    
    $part2 = Html::tag('span', $p2, [
        'class' => 'd-block d-md-inline-block',
        'style' => 'white-space: nowrap;'
    ]);
    
    $part3 = Html::tag('span', $p3, [
        'class' => 'd-block d-md-inline-block',
        'style' => 'white-space: nowrap;'
    ]);

    // 3. Разделитель |, который виден ТОЛЬКО на десктопе (от 768px)
    $sep = Html::tag('span', ' | ', [
        'class' => 'd-none d-md-inline'
    ]);

    return $part1 . $sep . $part2 . $sep . $part3;
}, 'pageSummaryOptions' => ['class' => 'text-right'],


            ],

            [
                'attribute' => 'cnt',
                'label' => 'Кол-во',
                'hAlign' => 'right',
                'contentOptions' => ['align-content: center; text-align: center;'],
                'format' => 'raw',

                'value' => function($model) {
                    $title = Html::tag('div',$model['cnt'] ?? "—", ['class' => 'cart-item-cnt-total', 'style' => 'font-weight: bold; text-align: center;']);
                    $details = Html::tag('div',($model['cnt_3'] ?? "—") . " | ".($model['cnt_2'] ?? "—") . " | ". ($model['cnt_1'] ?? "—") . " | ". ($model['cnt_0'] ?? "—"), ['style' => 'font-size: 12px; margin-bottom: 2px; color: #2c3e50; text-align: center;']);
//                    return Html::a((string)$title . $details, "/wb-order/index?WbOrderSearch[nm_id]=".$model['nm_id'], [ 'title' => 'Перейти в заказы', 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ]);

                return Html::a((string)$title . $details, Url::to([
                    '/wb-order/feed',
                    'DPFilterForm' => [
                        'nm_id'     => $model['nm_id'],
                        'date_from' => date('Y-m-d', strtotime('-3 days')),
                        'date_to'   => date('Y-m-d'),
                    ],
                ]), [
                    'title'     => 'Перейти в заказы',
                    'target'    => '_blank',
                    'data-pjax' => '0',
                    'style'     => 'text-decoration: none;',
                ]);

                }
            ],
            [
                'attribute' => 'pwd',
                'label' => 'Σ в РЦ',
                'hAlign' => 'right',
                'format' => ['decimal', 1],
                'headerOptions' => ['class' => 'mobile-hide-col'],
                'contentOptions' => ['align-content: center; text-align: right;', 'class' => 'mobile-hide-col'],
                'pageSummary' => true, 
            ],
            [
                'attribute' => 'fp',
                'label' => 'Σ к опл',
                'hAlign' => 'right',
                'format' => ['decimal', 0],
                'headerOptions' => ['class' => 'mobile-hide-col'],
                'contentOptions' => ['align-content: center; text-align: right;', 'class' => 'mobile-hide-col'],
                'pageSummary' => true, 
            ],
            [
                'attribute' => 'apwd',
                'label' => 'Цена',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'headerOptions' => ['class' => 'mobile-hide-col'],
                'contentOptions' => ['align-content: center; text-align: right;', 'class' => 'mobile-hide-col'],
            ],
            [
                'attribute' => 'aspp',
                'label' => 'СПП, %',
                'hAlign' => 'right',
                'format' => ['decimal', 1],
                'headerOptions' => ['class' => 'mobile-hide-col'],
                'contentOptions' => ['align-content: center; text-align: right;', 'class' => 'mobile-hide-col'],
//                'pageSummary' => true, 
//                'pageSummaryFunc' => GridView::F_AVG,
            ],
            [
                'attribute' => 'afp',
                'label' => 'Цена пр.',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['align-content: center; text-align: right; style' => 'font-weight:bold;'],
            ],


    ];
?>
<div class="row grid_lastorders grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_65 expandable-container">
<?php
echo GridView::widget([
    'dataProvider' => $LastOrdersProvider,
        'export' => false, 
        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,

        'showPageSummary' => true,
        'pageSummaryPosition' => GridView::POS_TOP, 
        'showFooter' => false,

        'toggleData' => true,
//        'layout' => "{summary}\n{items}\n{pager}",
//        'pager' => [
//            'options' => ['class' => 'pagination'],
//            'maxButtonCount' => 5,
//        ],

        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
//            'heading' => 'Заказы (c '.Yii::$app->formatter->asDate($dateFrom, 'd MMM y').')',

            'heading' => Html::a(
//                'Заказы (c ' . Yii::$app->formatter->asDate($dateFrom, 'd MMM y') . ')',
                'Заказы (c ' . Yii::$app->formatter->asDate($dateFrom, 'd MMM y') . ') <i class="bi bi-eye-fill me-1 lh-1" style="transform: none;"></i>',
                Url::to([
                    '/wb-order/feed',
                    'DPFilterForm' => [
                        'date_from' => $dateFrom,
                        'date_to'   => date('Y-m-d'),
                    ],
                ]),
                [
                    'class' => 'text-white', // Сохраняет белый цвет текста на фоне bg-wb
                    'style' => 'text-decoration: none;', // Добавляет подчеркивание, чтобы было видно, что это ссылка
                    'target' => '_blank',
                    'data-pjax' => '0',
                ]
            ) . ' ' . Html::a(
                '<i class="bi bi-graph-up me-1" style="transform: none;"></i> Топ',
                Url::to([
                    '/wb-order/feed-aggregated',
                    'DPFilterForm' => [
                        'date_from' => $dateFrom,
                        'date_to'   => date('Y-m-d'),
                    ],
                ]),
                [
                    'class' => 'text-white ms-2', // Маленький отступ слева
                    'style' => 'text-decoration: underline; font-weight: 500;',
                    'target' => '_blank',
                    'data-pjax' => '0',
                ]
            ),

            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            'footer' => false,
            'after' => false,
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],

    'columns' => $columnsLO,
]);
?>
</div>
<div class="expand-btn-wrapper">
    <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
</div>

<?php
    $allStatsLS = $LastSalesProvider->allModels;

    $totalLS_CNT   = array_sum(array_column($allStatsLS, 'cnt'));
    $totalLS_PWD   = array_sum(array_column($allStatsLS, 'pwd'));
    $totalLS_FP    = array_sum(array_column($allStatsLS, 'fp'));
    $totalLS_FPay  = array_sum(array_column($allStatsLS, 'forpay'));
?>
<?php
$columnsLS = [
            [
                    'attribute' => 'cardTitle',
                    'label' => 'Товар / Артикул',
                    'headerOptions'  => ['style' => 'width:350px'],
                    'contentOptions' => ['style' => 'min-width:350px; white-space: wrap; '],
                    'format' => 'raw',
                    'value' => function($model) {
                        // Верхний уровень: Название товара
                        $title = Html::tag('div', $model['title'] ?? '—', [
                            'style' => 'font-weight: bold; font-size: 12px; margin-bottom: 2px; color: #2c3e50;'
                        ]);
                        $details = Html::tag('div', 
                            "Артикул: <b>{$model['vendorCode']}</b> Арт WB: ". 
//                            http://31.130.204.146/wb-order/index?WbOrderSearch[nm_id]=50308273
                            Html::a((string)$model['nm_id'], "/wb/detail?DPFilterForm[nm_id]=".$model['nm_id'], ['title' => 'Перейти в карточку', 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ])

                            ,
                            ['style' => 'color: #666; font-size: 11px;']
                        );

                        return $title . $details;
                    },
                    'pageSummary' => function($model) use ($totalLS_CNT, $totalLS_PWD, $totalLS_FP, $totalLS_FPay)  {
                        return "Итого <b>$totalLS_CNT</b> пр | Σ в РЦ: <b>" . number_format($totalLS_PWD, 1, ',', ' ') . "</b> | Σ продажи: <b>".number_format($totalLS_FP, 1, ',', ' '). "</b> | WB нам: <b>".number_format($totalLS_FPay, 1, ',', ' ')."</b>";
                    }, 'pageSummaryOptions' => ['class' => 'text-right fw-normal', 'colspan' => 2, 'style' => 'font-weight: 400 !important;'],
            ],

            [
                'attribute' => 'cnt',
                'label' => 'Кол-во',
                'hAlign' => 'right',
//                'format' => ['decimal', 0],
                'headerOptions'  => ['style' => 'width:90px'],
                'contentOptions' => ['align-content: center; text-align: right; width:90px;'],
//                'pageSummary' => true, 
                'format' => 'raw',
                'pageSummary' => false,
                'value' => function($model) {
                    $title = Html::tag('div',$model['cnt'] ?? "—", ['style' => 'font-weight: bold; font-size: 12px; margin-bottom: 2px; text-align: center;']);
                    $details = Html::tag('div',($model['cnt_3'] ?? "—") . " | ".($model['cnt_2'] ?? "—") . " | ". ($model['cnt_1'] ?? "—") . " | ". ($model['cnt_0'] ?? "—"), ['style' => 'font-size: 12px; margin-bottom: 2px; color: #2c3e50; text-align: center;']);
//                    return $title . $details;
                    return Html::a((string)$title . $details, "/wb-sales/index?WbSalesSearch[nmId]=".$model['nm_id'], [ 'title' => 'Перейти к продажам', 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ]);

                }


            ],
            [
                'attribute' => 'pwd',
                'label' => 'Σ в РЦ',
                'hAlign' => 'right',
                'format' => ['decimal', 1],
                'contentOptions' => ['align-content: center; text-align: right;'],
                'pageSummary' => true, 
            ],
            [
                'attribute' => 'fp',
                'label' => 'Σ к опл',
                'hAlign' => 'right',
                'format' => ['decimal', 0],
                'contentOptions' => ['align-content: center; text-align: right;'],
                'pageSummary' => true, 
            ],
            [
                'attribute' => 'forpay',
                'label' => 'Σ WB нам',
                'hAlign' => 'right',
                'format' => ['decimal', 0],
                'contentOptions' => ['align-content: center; text-align: right;'],
                'pageSummary' => true, 
            ],
            [
                'attribute' => 'apwd',
                'label' => 'Цена',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['align-content: center; text-align: right;'],
            ],
            [
                'attribute' => 'aspp',
                'label' => 'СПП',
                'hAlign' => 'right',
                'format' => ['decimal', 1],
                'contentOptions' => ['align-content: center; text-align: right;'],
//                'pageSummary' => true, 
//                'pageSummaryFunc' => GridView::F_AVG,
            ],
            [
                'attribute' => 'afp',
                'label' => 'Цена пр.',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['align-content: center; text-align: right;'],
            ],
            [
                'attribute' => 'aforpay',
                'label' => 'К опл',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['align-content: center; text-align: right; style' => 'font-weight:bold;'],
            ],


    ];
?>
<div class="mobile-hide-block">
<div class="row grid_lastsales grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_65 expandable-container">
<?php
echo GridView::widget([
    'dataProvider' => $LastSalesProvider,
        'export' => false, 
        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,

        'showPageSummary' => true,
        'pageSummaryPosition' => GridView::POS_TOP, 
        'showFooter' => false,

        'toggleData' => true,
//        'layout' => "{summary}\n{items}\n{pager}",
//        'pager' => [
//            'options' => ['class' => 'pagination'],
//            'maxButtonCount' => 5,
//        ],

        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Продажи (c '.Yii::$app->formatter->asDate($dateFrom, 'd MMM y').')',
            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            'after' => false,
            'footer' => false,
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],

    'columns' => $columnsLS,
]);
?>
</div>
    <div class="expand-btn-wrapper">
        <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
    </div>
</div>

    </div>
    </div>

<div class="mobile-hide-block">
<div class="row grid_wbstat mb-5 grid_no_kv-panel-before grid_no_kv__summary_25" >
    <?= $this->render('/wb-profit/_monthly_profit_grid', ['dataProvider' => $MonthlyFinanceProvider]) ?>
</div>
</div>
<?php endif; ?>

</div> 

<style>
    .grid_wbstat .table {  font-size: 12px; table-layout: fixed; width: 100%; overflow-x: auto; overflow-y: hidden; display: block; border-collapse: collapse;}
    .grid_wbstat .table td, .grid_wbstat .table th {  padding: 4px 4px !important; }
    .grid_wbstat .table th {text-align: center;}
    .grid_wbstat input {  font-size: 12px;  }
    .grid_wbstat .input-group-text {padding: 4px;}
    .grid_wbstat {margin-bottom: 20px;}
    .grid_wbstat a {text-decoration: none; };
</style> 
<style>
    #w0 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* размер заголовка  */
    #w0 .card-header { font-size: 11px; }
    #w0 .card-header h5 { font-size: 13px; }
</style>
<style>
    #w2 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* размер заголовка  */
    #w2 .card-header { font-size: 11px; }
    #w2 .card-header h5 { font-size: 13px; }
</style>
<style>
    #w4 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* размер заголовка  */
    #w4 .card-header { font-size: 11px; }
    #w4 .card-header h5 { font-size: 13px; }
</style>
<style>
    #w6 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* размер заголовка  */
    #w6 .card-header { font-size: 11px; }
    #w6 .card-header h5 { font-size: 13px; }
</style>
<style>
    #w8 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* размер заголовка  */
    #w8 .card-header { font-size: 11px; }
    #w8 .card-header h5 { font-size: 13px; }
</style>

<?php
/*
    #w0 div.border-primary { border-color: var(--bs-border-color-translucent) !important; }

    .svg-inline--fa.fa-w-14, .svg-inline--fa.fa-w-11 { width: 10px; }
    .wb-order-index #wbordersearch-date {padding: 2px;}
    input.form-control { padding: 2px; text-align: center;}
    #w0-filters td { padding: 2px 3px !important; }
    .form-select { padding: 2px 3px !important; font-size: 12px; text-align: center; background-position: right 5px center;}
    .form__input-dates {    display: flex; flex-direction: column; justify-content: flex-start; flex-wrap: nowrap;}
    .kv-align-right { white-space: nowrap; align-content: center; text-align: right;}
    #main a {text-decoration: none };

*/
?>

<style>
    .expandable-container {
        max-height: 200px;
        overflow: hidden;
        position: relative;
        transition: max-height 0.5s ease-in-out;
        margin-bottom: 10px;
    }

    .expandable-container::after {
        content: "";
        position: absolute;
        bottom: 0; left: 0; width: 100%; height: 50px;
        background: linear-gradient(transparent, white);
        pointer-events: none;
        transition: opacity 0.3s;
    }

    /* Класс для развернутого состояния */
    .expandable-container.is-expanded {
        max-height: 50000px; /* С запасом для больших таблиц */
    }
    
    .expandable-container.is-expanded::after {
        opacity: 0;
    }

    .expand-btn-wrapper {
        text-align: center;
        margin-bottom: 30px; /* Отступ между блоками */
    }
</style>

<style>
    .expandable-container.is-expanded {
        max-height: 50000px !important;
    }
</style>
<style>
    .expandable-container {
        max-height: 250px !important; /* Фиксируем начальную высоту */
        overflow: hidden !important;
        position: relative;
        transition: max-height 0.5s ease-in-out;
        margin-bottom: 10px;
        display: block; /* Убедимся, что это не инлайновый элемент */
    }

    .expandable-container.is-expanded {
        max-height: 20000px !important; /* Увеличиваем до максимума */
    }

    /* Убираем градиент, когда развернуто */
    .expandable-container.is-expanded::after {
        display: none !important;
    }
</style>
<style>
.kv-align-right {vertical-align: middle;}
.kv-page-summary-container tr:first-child td:first-child { font-weight: 400; text-align: center; }
</style>

<style>
/* Упрощённая таблица "Заказы" на мобильных: оставляем только Товар/Артикул, Кол-во, Цена пр. */
@media (max-width: 767px) {
    .grid_lastorders .mobile-hide-col {
        display: none !important;
    }

    /* Локальное боковое меню дашборда дублирует общий гамбургер из layout —
       на мобильных прячем его, чтобы оно не перекрывало контент. */
    .nav_div {
        display: none !important;
    }
    .dash_div {
        width: 100% !important;
        flex: 0 0 100%;
        max-width: 100%;
    }

    /* На мобильных оставляем только блок "Заказы" — остальные блоки дашборда скрываем */
    .mobile-hide-block {
        display: none !important;
    }

    /* Виджет "Заказы/Продажи" (_today_stats_widget) оставляем на мобильных —
       вкладки, период и KPI видны, но сам график (amCharts) прячем */
    .today-stats-widget .tsw-chart-wrap,
    .today-stats-widget .tsw-axis-caption {
        display: none !important;
    }

    /* На мобильных прячем "Обновлено в ..." — лишняя информация, занимает место */
    .today-stats-widget #tswUpdatedAt {
        display: none !important;
    }
    /* Вкладки "Заказы/Продажи" + кнопка периода — в один ряд, без переноса */
    .today-stats-widget .tsw-header {
        flex-wrap: nowrap !important;
        gap: 10px;
    }
    .today-stats-widget .today-stats-tabs {
        display: flex;
        flex-shrink: 0;
    }
    .today-stats-widget .today-stats-tabs .tsw-tab {
        margin-right: 12px;
        padding: 6px 2px;
        font-size: 13px;
        white-space: nowrap;
    }
    .today-stats-widget .tsw-period-btn {
        padding: 6px 10px;
        font-size: 12px;
        white-space: nowrap;
    }

    /* JS-рендер KPI ставит col-6 col-md-3 (2 колонки в ряд на мобильных) —
       переопределяем на одну колонку в ряд, чтобы не было визуальной каши */
    .today-stats-widget .tsw-kpi-row > [class*="col-"] {
        flex: 0 0 100% !important;
        max-width: 100% !important;
        margin-bottom: 18px;
    }
    .today-stats-widget .tsw-kpi-row > [class*="col-"]:last-child {
        margin-bottom: 0;
    }
}

/* Мобильная версия (по умолчанию) */
.mobile-column-420 {
    width: 100%;
    min-width: auto; /* Сбрасываем жесткое ограничение на мобильных */
    white-space: normal; /* text-wrap: wrap в старых браузерах заменяется на normal */
}

/* Десктопная версия (для экранов шире 992px) */
@media (min-width: 992px) {
    th.mobile-column-420, td.mobile-column-420 {
        width: 420px !important;
        min-width: 420px !important;
        max-width: 420px !important; /* Ограничивает максимальное расширение */
        box-sizing: border-box;
    }
}

    .cart-item-title {
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 2px;
        font-size: 17px; /* Размер для мобильных */
    }
    .cart-item-details {
        color: #666;
        font-size: 13px; /* Размер для мобильных */
    }
    .cart-item-cnt-total {
        font-size: 17px; 
        margin-bottom: 5px; 
    }
    .text-right {
        font-size: 16px !important;
    }



/* Возвращаем компактный размер на десктопе (от 768px) */
@media (min-width: 768px) {
    .cart-item-title { font-size: 12px; }
    .cart-item-details { font-size: 11px; }
    .cart-item-cnt-total {
        font-size: 12px; 
        margin-bottom: 2px; 
        }
    .text-right { font-size: 12px !important; }
}
@media (max-width: 767px) {
    /* Скрываем все ячейки футера, кроме первой */
    .kv-page-summary td:not(:first-child) {
        display: none !important;
    }
}


/* Базовые стили (Десктоп: 250px) */
.expandable-container {
    max-height: 250px !important;
    overflow: hidden !important;
    position: relative;
    transition: max-height 0.5s ease-in-out;
    margin-bottom: 10px;
    display: block;
}

/* Мобильная версия (до 768px): базовая высота 850px */
@media (max-width: 767px) {
    .expandable-container {
        max-height: 850px !important;
    }
}

/* Развернутое состояние (работает и на десктопе, и на мобильном) */
.expandable-container.is-expanded {
    max-height: 20000px !important;
}
</style>