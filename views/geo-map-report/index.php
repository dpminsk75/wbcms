<?php
use yii\helpers\Html;
use yii\helpers\Json;
use app\components\getDPWidget;
use app\components\GeoHelper;

use kartik\grid\GridView;

$this->title = 'География продаж (по финансовым документам)';
$processed = GeoHelper::prepareChartData($mapData);
$chartData = $processed['chart'] ?? [];
$debugData = $processed['debug'] ?? [];

$columnsCo = [];

$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.3.15/proj4.js');
$this->registerJsFile('/js/highmaps.js', ['depends' => [\yii\web\JqueryAsset::class]]);
// Подгружаем ваш файл
$this->registerJsFile('/js/ru-all.js', ['depends' => [\yii\web\JqueryAsset::class]]);

$params = \app\components\getDPWidget::getParams(70);
$nmId = $params['nm_id'];

$myButtons = [];
if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin') {
    $myButtons[] = Html::a('<i class="fas fa-sync-alt"></i> Дневник',       ['/geo-map-report/index', 'DPFilterForm' => ['nm_id' => 526443466]], ['class' => 'btn btn-panel']);
    $myButtons[] = Html::a('<i class="fas fa-calendar-alt"></i> Календарь', ['/geo-map-report/index', 'DPFilterForm' => ['nm_id' => 135462932]], ['class' => 'btn btn-panel']);
}

?>

<div class="geo-report">
    <div class="row mb-3">
        <div class="col-md-6">
                <?= getDPWidget::widget(['action' => ['index'], 'quickButtons' => $myButtons, 'defaultDays' => 30]) ?>
        </div>
    </div>


<?php if (!$nmId): ?> 
        <div class="alert alert-secondary">
            Пожалуйста, выберите карточку в фильтре выше.
        </div>
    <?php else: ?>


    <div class="row mb-3">
        <div class="col-md-12 ">
            <div id="map-container" style="width: 100%; height: 550px; margin: 20px 0; border: 1px solid #ddd; background: #fff;"></div>
        </div>
    </div>

<?php if ($GridProvider): ?> 
    <?php if ($GridProvider->totalCount > 0): ?>
    <?php

    $columns = [
            [
                'attribute' => 'name',
                'label' => 'Регион',
                'format' => 'raw', 

            ],
                [
                    'attribute' => 'sales_count',
                    'label' => 'Кол-во',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'retail_price',
                    'label' => 'Сумма, ₽', 
                    'format' => ['decimal', 2],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'pageSummary' => true, 'pageSummaryOptions' => ['class' => 'text-right'],
//                    'pageSummary' => GridView::F_AVG, 
                ],
                [
                    'attribute' => 'retail_amount',
                    'label' => 'Цена со ск, ₽', 
                    'format' => ['decimal', 2],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'pageSummary' => true, 'pageSummaryOptions' => ['class' => 'text-right'],
//                    'pageSummary' => GridView::F_AVG, 
                ],
                [
                    'attribute' => 'ppvz_spp_prc',
                    'label' => 'СПП, %',
                    'hAlign' => 'right',
                    'format' => ['decimal', 2],
                ],
                [
                    'attribute' => 'for_pay',
                    'label' => 'К оплате, ₽',
                    'hAlign' => 'right',
                    'format' => ['decimal', 2],
                    'contentOptions' => ['style' => 'font-weight:bold'],
    //                'pageSummary' => number_format($totalLS_SFP, 1, ',', ' '),
                ],

    ];
    ?>

    <?php
    $columnsCo = [
            [
                'attribute' => 'country',
                'label' => 'Страна',
                'format' => 'raw', 

            ],
            [
                'attribute' => 'region',
                'label' => 'Регион',
                'format' => 'raw', 

            ],
                [
                    'attribute' => 'sales_count',
                    'label' => 'Кол-во',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'retail_amount',
                    'label' => 'Цена со ск, ₽', 
                    'format' => ['decimal', 2],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'pageSummary' => true, 'pageSummaryOptions' => ['class' => 'text-right'],
//                    'pageSummary' => GridView::F_AVG, 
                ],
                [
                    'attribute' => 'ppvz_spp_prc',
                    'label' => 'СПП, %',
                    'hAlign' => 'right',
                    'format' => ['decimal', 2],
                ],
                [
                    'attribute' => 'for_pay',
                    'label' => 'К оплате, ₽',
                    'hAlign' => 'right',
                    'format' => ['decimal', 2],
                    'contentOptions' => ['style' => 'font-weight:bold'],
                    'pageSummary' => true,
    //                'pageSummary' => number_format($totalLS_SFP, 1, ',', ' '),
                ],

    ];
    ?>

    <div class="row mb-3 custom-compact-grid">
        <div class="col-md-7 rounded ">
            <div class="row grid_advstat grid_wbstat expandable-container">
            <?php
            echo GridView::widget([
                'dataProvider' => $GridProvider,
                    'export' => false, 
                    'toggleData' => false,
                    'pjax' => true,
                    'bordered' => true,
                    'striped' => true,
                    'condensed' => true,
                    'responsive' => true,
                    'hover' => true,
                    'showPageSummary' => true,
                    'pageSummaryPosition' => GridView::POS_TOP, 
                    'showFooter' => false,
                    'panel' => [
                        'type' => GridView::TYPE_PRIMARY,
                        'heading' => 'По областям РФ',
                        'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                        'footer' => false,
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

        <div class="col-md-5 rounded ">
            <div class="row grid_advstat grid_wbstat expandable-container">
            <?php
            echo GridView::widget([
                'dataProvider' => $CountryProvider,
                    'export' => false, 
                    'toggleData' => false,
                    'pjax' => true,
                    'bordered' => true,
                    'striped' => true,
                    'condensed' => true,
                    'responsive' => true,
                    'hover' => true,
                    'showPageSummary' => true,
                    'pageSummaryPosition' => GridView::POS_TOP, 
                    'showFooter' => false,
                    'panel' => [
                        'type' => GridView::TYPE_PRIMARY,
                        'heading' => 'Другие страны',
                        'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                        'footer' => false,
                    ],
                    'containerOptions' => [
                        'class' => 'no-border-class' 
                    ],

                'columns' => $columnsCo,
            ]);
            ?>
            </div>
            <div class="expand-btn-wrapper">
                <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
            </div>
        </div>

    </div>


    <?php endif; ?>
<?php endif; ?>
</div>

<?php
// Важно: переводим в JSON
$jsData = Json::encode($chartData);

// 1. Находим максимум (чтобы не было деления на 0, минимум 1)
$maxValue = count($chartData) > 0 ? max(array_column($chartData, 'value')) : 0;
$maxValue = $maxValue > 0 ? $maxValue : 100;

// 2. Генерируем динамические диапазоны (шаг 20%)
$steps = [
    ['from' => 1, 'to' => round($maxValue * 0.02),                           'color' => '#f7fcf5', 'label' => '1 - ' . round($maxValue * 0.02)],
    ['from' => round($maxValue * 0.02) + 1, 'to' => round($maxValue * 0.05), 'color' => '#e5f5e0', 'label' => (round($maxValue * 0.02) + 1) . ' - ' . round($maxValue * 0.05)],
    ['from' => round($maxValue * 0.05) + 1, 'to' => round($maxValue * 0.1),  'color' => '#c7e9c0', 'label' => (round($maxValue * 0.05) + 1) . ' - ' . round($maxValue * 0.1)],
    ['from' => round($maxValue * 0.1) + 1,  'to' => round($maxValue * 0.2),  'color' => '#a1d99b', 'label' => (round($maxValue * 0.1) + 1)  . ' - ' . round($maxValue * 0.2)],
    ['from' => round($maxValue * 0.2) + 1,  'to' => round($maxValue * 0.25), 'color' => '#74c476', 'label' => (round($maxValue * 0.2) + 1)  . ' - ' . round($maxValue * 0.25)],
    ['from' => round($maxValue * 0.25) + 1, 'to' => round($maxValue * 0.4),  'color' => '#41ab5d', 'label' => (round($maxValue * 0.25) + 1) . ' - ' . round($maxValue * 0.4)],
    ['from' => round($maxValue * 0.4) + 1,  'to' => round($maxValue * 0.75), 'color' => '#238b45', 'label' => (round($maxValue * 0.4) + 1)  . ' - ' . round($maxValue * 0.75)],
    ['from' => round($maxValue * 0.75) + 1, 'to' => null,                    'color' => '#005a32', 'label' => (round($maxValue * 0.75) + 1) . ' +'],
];


// Зеленые тона - #f7fcf5, #e5f5e0, #c7e9c0, #a1d99b, #74c476, #41ab5d, #238b45, #005a32


// Передаем это в JS
$jsSteps = json_encode($steps);

$this->registerJs(<<<JS
    (function() {
        var dataFromServer = $jsData;
        var steps = $jsSteps;

        var dataClasses = [{ to: 0, color: '#f8f8f8', name: 'Нет продаж' }];
            steps.forEach(function(s) {
                var item = { from: s.from, color: s.color, name: s.label };
                if (s.to) item.to = s.to;
                dataClasses.push(item);
        });

        function renderMap() {
            if (typeof Highcharts === 'undefined' || !Highcharts.maps["countries/ru/ru-all"]) {
                setTimeout(renderMap, 100);
                return;
            }

            // Отладочная информация в консоль
            console.log('Данные для карты (Raw):', dataFromServer);

            Highcharts.setOptions({
                lang: {
                    thousandsSep: ' '
                }
            });

            Highcharts.mapChart('map-container', {
                chart: { 
                    map: 'countries/ru/ru-all', 
                    borderWidth: 1,
                    borderColor: '#eee'
                },
                title: { text: null },

                // Возвращаем кнопки + и -
                mapNavigation: {
                    enabled: true,
                    buttonOptions: {
                        verticalAlign: 'bottom'
                    }
                },

                // сейчас зеленый
                colorAxis: {
                    dataClasses: dataClasses
                },
/*
                colorAxis: {
                    min: 0,
                    minColor: '#f8f8f8', // Цвет для нуля
                    maxColor: '#005a32', // Цвет для максимума (ваш темно-зеленый)
                    // Тип шкалы 'logarithmic' помогает увидеть разницу, 
                    // если между лидером и остальными огромный разрыв
                    type: 'linear', 
                    stops: [
                        [0, '#f8f8f8'],
                        [0.1, '#c7e9c0'], // 10% от макс.
                        [0.2, '#a1d99b'], // 20%
                        [0.4, '#74c476'], // 40%
                        [0.6, '#41ab5d'], // 40%
                        [0.8, '#238b45'], // 80%
                        [1,   '#005a32']  // 100%
                    ]
                },
*/

/*
                colorAxis: {
                    dataClasses: [
                        { to: 0, color: '#f8f8f8', name: 'Нет продаж' },
                        { from: 1,   to: 5,   color: '#e5f5e0', name: '1-5' },
                        { from: 6,   to: 9,   color: '#c7e9c0', name: '6-9' },
                        { from: 10,  to: 19,  color: '#a1d99b', name: '10-19' },
                        { from: 20,  to: 49,  color: '#74c476', name: '20-49' },
                        { from: 50,  to: 99,  color: '#41ab5d', name: '50-99' },
                        { from: 100, to: 299, color: '#238b45', name: '100-299' },
                        { from: 301,          color: '#005a32', name: '300+' }
                    ]
                },
*/
/*
Синие тона -  #f7fbff, #deebf7, #c6dbef, #9ecae1, #6baed6, #4292c6, #2171b5, #084594

                    dataClasses: [
                        { to: 0, color: '#f8f8f8', name: 'Нет продаж' },
                        { from: 1,   to: 5,   color: '#deebf7', name: '1-5' },
                        { from: 6,   to: 9,   color: '#c6dbef', name: '6-9' },
                        { from: 10,  to: 19,  color: '#9ecae1', name: '10-19' },
                        { from: 20,  to: 49,  color: '#6baed6', name: '20-49' },
                        { from: 50,  to: 99,  color: '#4292c6', name: '50-99' },
                        { from: 100, to: 299, color: '#2171b5', name: '100-299' },
                        { from: 301,          color: '#084594', name: '300+' }
                    ]

Серо-синие тона - #f0f4f8, #d9e2ec, #bcccdc, #9fb3c8, #829ab1, #627d98, #486581, #334e68
Зеленые тона - #f7fcf5, #e5f5e0, #c7e9c0, #a1d99b, #74c476, #41ab5d, #238b45, #005a32
Оранжевые - #fff5eb, #fee6ce, #fdd0a2, #fdae6b, #fd8d3c, #f16913, #d94801, #8c2d04

                    dataClasses: [
                        { to: 0, color: '#f8f8f8', name: 'Нет продаж' },
                        { from: 1,   to: 5,   color: '#fee6ce', name: '1-5' },
                        { from: 6,   to: 9,   color: '#fdd0a2', name: '6-9' },
                        { from: 10,  to: 19,  color: '#fdae6b', name: '10-19' },
                        { from: 20,  to: 49,  color: '#fd8d3c', name: '20-49' },
                        { from: 50,  to: 99,  color: '#f16913', name: '50-99' },
                        { from: 100, to: 299, color: '#d94801', name: '100-299' },
                        { from: 301,          color: '#8c2d04', name: '300+' }
                    ]

                    dataClasses: [
                        { to: 0, color: '#f8f8f8', name: 'Нет заказов' },
                        { from: 1,   to: 10,  color: '#e6f2ff', name: '1-50' },
                        { from: 11,  to: 50,  color: '#b3d7ff', name: '11-50' },
                        { from: 51,  to: 100, color: '#66afff', name: '51-100' },
                        { from: 101, to: 300, color: '#007bff', name: '100-300' },
                        { from: 301,          color: '#003366', name: '300+' }
                    ]
*/
                legend: {
                    title: { text: 'Купили:' },
                    align: 'left',
                    verticalAlign: 'top',
                    floating: true,
                    layout: 'vertical',
                    valueDecimals: 0,
                    backgroundColor: 'rgba(255,255,255,0.85)',
                    symbolRadius: 0,
                    itemStyle: { fontSize: '10px' }
                },

                series: [{
                    data: dataFromServer,
                    joinBy: 'hc-key',
                    name: 'Купили',
                    allAreas: true, // Рисуем все контуры
                    states: {
                        hover: { color: '#ffc107' }
                    },
                    dataLabels: {
                        enabled: true,
                        format: '{point.name}',
                        style: {
                            fontSize: '9px',
                            fontWeight: 'normal',
                            textOutline: 'none'
                        }
                    },
                    tooltip: {
                        headerFormat: '',
//                        useHTML: true,
//                        style: { textAlign: 'center' },
//                        pointFormat: '<b>{point.name}</b><br>Заказов: <b>{point.value}</b>'
                        pointFormat: '{point.name}:<br><b>{point.value}</b> шт. | <b>{point.retail_sum:,.0f}</b> ₽'
                    }
                }]
            });
        }

        $(document).ready(renderMap);
    })();
JS
, \yii\web\View::POS_END);

?>

<?php endif; ?>