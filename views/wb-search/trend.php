<?php
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use app\components\UniversalFilterWidget;
/*
echo UniversalFilterWidget::widget([
    'attribute' => 'phrase_id',
    'label' => 'Корень фразы',
    'ajaxUrl' => Url::to(['wb-search/ajax-search', 'type' => 'phrases']),
    'initValueText' => $phraseId ? (\app\models\WbPhrasesDirectory::findOne($phraseId)->phrase ?? '') : '',
]);
*/
$this->title = 'Анализ фразы: ' . ($phraseId ?: 'выберите запрос');
$currentPhraseId = $phraseId ?? null;
?>
<div class="search-phrase-report">
    <div class="row mb-3">
        <h2 class="mb-4" style="font-weight: 400;"><?= Html::encode($this->title) ?></h2>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
<?php
echo UniversalFilterWidget::widget([
    'attribute' => 'phrase_text', // Используем новое поле
    'label' => 'Корень фразы',
    'defaultDays' => 90,
    'ajaxUrl' => Url::to(['wb-search/ajax-search', 'type' => 'phrases']),
    'initValueText' => $model->phrase_text ?? '', 
    'pluginOptions' => [
        'tags' => true, // Позволяет вводить свой текст
        'allowClear' => true,
        'minimumInputLength' => 2,
        'createTag' => new \yii\web\JsExpression("function (params) {
            return {
                id: params.term, // Теперь ID будет равен самому тексту
                text: params.term,
                isNew: true
            };
        }"),
    ]
]);
?>
        </div>
    </div>
<?php
$gridColumns = [
    ['class' => 'kartik\grid\SerialColumn'],
//    ['attribute' => 'phrase',       'label' => 'Фраза',   'format' => 'raw', 'contentOptions' => ['style' => 'min-width: 300px; white-space: normal;'],],

    [
        'attribute' => 'phrase',
        'label' => 'Фраза',
        'format' => 'raw',
        'value' => function($model) use ($dateFrom, $dateTo) {
            // Формируем ссылку на /wb-search/phrase
            $url = Url::to([
                '/wb-search/phrase', 
                'DPFilterForm' => [
                    'phrase_id' => $model['phrase_id'] ?? null,
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo
                ]
            ]);
            
            return Html::a($model['phrase'], $url, [
                'data-pjax' => '0', // Чтобы ссылка открывалась как обычная страница, а не внутри Pjax
                'target' => '_blank', // Открываем в новой вкладке, чтобы не терять таблицу трендов
//                'style' => 'font-weight: bold; color: #0d6efd;'
            ]);
        },
        'contentOptions' => ['style' => 'min-width: 300px; white-space: normal;'],
//        'class' => 'kv-sticky-column', // Не забудь добавить этот класс для липкой колонки
    ],

    ['attribute' => 'avg_freq',     'label' => 'Част',   'format' => ['decimal', 0], 'hAlign' => 'right', 'contentOptions' => ['style' => 'min-width: 50px;'],],
    ['attribute' => 'total_clicks', 'label' => 'Клики',  'format' => ['decimal', 0], 'hAlign' => 'right', 'contentOptions' => ['style' => 'min-width: 50px;'],],
    ['attribute' => 'total_orders', 'label' => 'Заказы', 'format' => ['decimal', 0], 'hAlign' => 'right', 'contentOptions' => ['style' => 'min-width: 50px;'],],
    ['attribute' => 'conversion',   'label' => 'CR %', 'hAlign' => 'center', 'contentOptions' => ['style' => 'min-width: 50px;'],
        'contentOptions' => function($model) {
            if ($model['conversion'] > 10) return ['style' => 'background-color: #d4edda; color: #155724; font-weight:bold;'];
            if ($model['total_orders'] == 0 && $model['total_clicks'] > 50) return ['style' => 'background-color: #f8d7da; color: #721c24;'];
            return [];
        }
    ],

];

if (!empty($dataProvider->allModels)) {
    $keys = array_keys($dataProvider->allModels[0]);
    sort($keys);
    foreach ($keys as $key) {
        if (strpos($key, 'w_') === 0) {
            $gridColumns[] = [
                'attribute' => $key,
                'header' => substr($key, 2),
                'format' => 'raw',
                'hAlign' => 'center',
                'headerOptions' => ['class' => 'text-center small'],
                'contentOptions' => function($model) use ($key) {
                    $val = (float)$model[$key];
                    $avg = (float)($model['avg_freq'] ?: 1);
                    $ratio = $val / $avg;
                    
                    $style = [];
                    if ($val > 0) {
                        // 10 ПЛАВНЫХ ГРАДАЦИЙ (Heatmap)
                        if ($ratio > 2.5) {
                            $style = ['background-color' => '#1862aa !important', 'color' => '#ffffff', ]; // 1862aa 
                        } elseif ($ratio > 1.95) {
                            $style = ['background-color' => '#2171b5 !important', 'color' => '#ffffff', ]; // Сильный рост (>160%)
                        } elseif ($ratio > 1.3) {
                            $style = ['background-color' => '#4292c6 !important', 'color' => '#ffffff']; // Хороший рост (>140%)
                        } elseif ($ratio > 1.1) {
                            $style = ['background-color' => '#6baed6 !important', 'color' => '#ffffff']; // Заметный рост (>120%)
                        } elseif ($ratio > 0.9) {
                            $style = ['background-color' => '#9ecae1 !important', 'color' => '#333']; 
                        } elseif ($ratio > 0.4) {
                            $style = ['background-color' => '#c6dbef !important', 'color' => '#333']; 
                        } elseif ($ratio > 0.2) {
                            $style = ['background-color' => '#cedff1 !important', 'color' => '#333']; 
                        } elseif ($ratio > 0.1) {
                            $style = ['background-color' => '#d6e5f4 !important', 'color' => '#555']; 
                        } else {
                            $style = ['background-color' => '#deebf7 !important', 'color' => '#555']; 
                        }
                    }
                    return ['style' => $style];
                },
                'value' => function($model) use ($key) {
                    $val = (float)$model[$key];
                    // Убираем нули для чистоты таблицы
                    return ($val > 0) ? number_format($val, 0, '.', ' ') : '';
                }
            ];
        }
    }
}
?>

<div class="row custom-compact-grid mb-3 mt-3">
    <div class="col-md-12">
        <div id="grid-scroll" style=" position: relative;" >
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => $gridColumns,
            'pjax' => true,

            'responsive'    => false,
            'bordered'      => true,
            'condensed'     => true,
            'floatHeader'   => true,

            'panel' => ['type' => 'default', 'heading' => 'Анализ вхождений'],
            'containerOptions' => ['style' => 'max-height: 70vh; overflow: auto;'],

            'rowOptions' => function ($model, $key, $index, $grid) {
                return ['class' => 'clickable-row', 'style' => 'cursor: pointer;'];
            },
        ]); ?>
        </div>
    </div>
</div>

<div class="card my-3 shadow-sm">
    <div class="card-body">
        <div id="chartdiv" style="width: 100%; height: 600px;"></div>
    </div>
</div>

<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

<script>


am5.ready(function() {
    var root = am5.Root.new("chartdiv");
    root.setThemes([am5themes_Animated.new(root)]);

    // Вертикальный лейаут для разделения графика и легенды
    root.container.set("layout", root.verticalLayout);

    var chart = root.container.children.push(am5xy.XYChart.new(root, {
        panX: true,
        panY: false,
        wheelX: "panX",
        wheelY: "zoomX",
        paddingBottom: 20
    }));

    // --- НАСТРОЙКА ЕДИНОГО ТУЛТИПА ---
    // Создаем один общий тултип на весь график
    var sharedTooltip = am5.Tooltip.new(root, {
        getFillFromSprite: false,
        autoTextColor: false,
        pointerOrientation: "horizontal",
        labelText: "{name}: {valueY}"
    });

    sharedTooltip.get("background").setAll({
        fill: am5.color(0x000000), // Черный фон
        fillOpacity: 0.8
    });
    sharedTooltip.label.setAll({ fill: am5.color(0xffffff) }); // Белый текст

    // Курсор с настройкой snapToSeries (прилипание)
    var cursor = chart.set("cursor", am5xy.XYCursor.new(root, {
        behavior: "zoomX",
        xAxis: xAxis
    }));
    cursor.lineY.set("visible", false);

    // --- ОСИ ---
    var xAxis = chart.xAxes.push(am5xy.CategoryAxis.new(root, {
        categoryField: "week",
        renderer: am5xy.AxisRendererX.new(root, { minGridDistance: 50 })
    }));
    xAxis.data.setAll(<?= \yii\helpers\Json::encode($chartData) ?>);

    var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        renderer: am5xy.AxisRendererY.new(root, {})
    }));

    // --- ЦВЕТА И СЕРИИ ---
    var colors = [
        am5.color(0x6794dc), am5.color(0x67b7dc), am5.color(0x67dc75),
        am5.color(0xdca867), am5.color(0xdc67ce), am5.color(0x095256),
        am5.color(0x087f8c), am5.color(0x5aaa95), am5.color(0x86a873)
    ];

    <?php foreach ($topPhrases as $index => $tp): ?>
    (function() {
        var series = chart.series.push(am5xy.LineSeries.new(root, {
            name: "<?= \yii\helpers\Html::encode($tp['phrase']) ?>",
            xAxis: xAxis,
            yAxis: yAxis,
            valueYField: "val_<?= $index ?>",
            categoryXField: "week",
            stroke: colors[<?= $index ?>],
            // Привязываем серию к общему механизму курсора
            tooltip: am5.Tooltip.new(root, {
                labelText: "{name}: [bold]{valueY}[/]",
                pointerOrientation: "horizontal"
            })
        }));

        // КЛЮЧЕВОЙ МОМЕНТ: Состояние HOVER
        series.strokes.template.setAll({ strokeWidth: 2, interactive: true });
        
        // Создаем состояние для выделения
        series.strokes.template.states.create("hover", {
            strokeWidth: 6, // Реально жирная линия
            strokeOpacity: 1
        });

        // Связываем появление тултипа с выделением линии
        series.on("tooltipDataItem", function(tooltipDataItem) {
            if (tooltipDataItem) {
                series.hover();
            } else {
                series.unhover();
            }
        });

        series.data.setAll(<?= \yii\helpers\Json::encode($chartData) ?>);
        series.appear();
    })();
    <?php endforeach; ?>

    // --- ТВОЯ ЛЕГЕНДА (3 В РЯД) ---
    var legend = root.container.children.push(am5.Legend.new(root, {
        centerX: am5.p50,
        x: am5.p50,
        marginTop: 15,
        useDefaultMarker: true,
        layout: am5.GridLayout.new(root, {
            maxColumns: 3,
            fixedWidthGrid: true
        })
    }));

    // Настройки маркеров из твоего кода
    legend.markers.template.setAll({ width: 40, height: 1, centerY: am5.p50 });
    legend.markerRectangles.template.setAll({ strokeWidth: 2, height: 0, centerY: am5.p50 });

    legend.itemContainers.template.events.on("pointerover", function(e) {
        var itemSeries = e.target.dataItem.dataContext;
        itemSeries.hover();
    });
    legend.itemContainers.template.events.on("pointerout", function(e) {
        var itemSeries = e.target.dataItem.dataContext;
        itemSeries.unhover();
    });

    legend.data.setAll(chart.series.values);
    chart.appear(1000, 100);
});
</script>

<style>
/* Липкая колонка с фразой */
.grid-view th:nth-child(2), 
.grid-view td:nth-child(2) {
    position: sticky;
    left: 0;
/*    z-index: 8; */
    box-shadow: 2px 0 5px rgba(0,0,0,0.05);
    background-color: inherit !important;
}

.grid-view th:nth-child(2) {
    background-color: #eee !important;
}

/*
.grid-view th:nth-child(3), .grid-view td:nth-child(3) { 
    z-index: 9; 
    position: relative;
}
*/


/* Тонкие линии таблицы */
.table-condensed > theater > tr > th, 
.table-condensed > tbody > tr > td {
    padding: 5px 8px !important;
    font-size: 12px;
}

.table-striped tbody tr:nth-of-type(odd) td:nth-child(2) {
    background-color: #f9f9f9 !important;  /* Цвет стандартной зебры Bootstrap */
}

.table-striped tbody tr:nth-of-type(even) td:nth-child(2) {
    background-color: #fff !important;  /* Цвет стандартной зебры Bootstrap */
}

/*
.table > tbody > tr.selected-row > td, 
.table > tbody > tr.selected-row > th {
    background-color: #ffb7f8 !important;
}
*/

.table tbody tr.selected-row td,
.table tbody tr.selected-row th,
.table-striped tbody tr.selected-row:nth-of-type(odd) td {
    background-color: #ffb7f8 !important;
}


/*
.table > tbody > tr.selected-row > td.pos-top-10 {  background-color: #f37be7 !important; }
.table > tbody > tr.selected-row > td.pos-top-50 {  background-color: #f897ee !important; }

.custom-compact-grid .table, .kv-grid-container {overflow: auto !important;}
*/

.kv-float-header {
    z-index: 7 !important;
}

</style>


<?php 
$js = <<<JS
$(document).on('click', '.clickable-row', function(e) {
    var table = $(this).closest('table');
    table.find('tr').removeClass('selected-row');
    $(this).addClass('selected-row');
});
JS;

$this->registerJs($js);
?>
