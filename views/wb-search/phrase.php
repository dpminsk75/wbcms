<?php
use yii\helpers\Html;
use kartik\grid\GridView;
use kartik\select2\Select2;
use yii\helpers\Json;

/** @var $this yii\web\View */
/** @var $phrase string */
/** @var $dataProvider yii\data\ArrayDataProvider */
/** @var $uniqueDates array */
/** @var $dateFrom string */
/** @var $phrasesMap array */

$this->title = 'Анализ фразы: ' . ($phrase ?: 'выберите запрос');
$currentPhraseId = Yii::$app->request->get('DPFilterForm')['phrase_id'] ?? null;
//$phrase = '';
?>

<div class="search-phrase-report">
    <div class="row mb-3">
        <h2 class="mb-4" style="font-weight: 400;"><?= Html::encode($this->title) ?></h2>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
<?php /*
            <?= \app\components\UniversalFilterWidget::widget([
                'attribute' => 'phrase_id',      // СКАЗАЛИ ВИДЖЕТУ: "Работай с полем phrase"
                'label' => 'Поисковая фраза',
                'data' => $phrasesMap,        // Твой список фраз
                'action' => ['/wb-search/phrase'],  // Твой URL
                'pluginOptions' => [
                    'minimumInputLength' => 2,
                ],
            ]) ?>
*/?>
        <?= \app\components\UniversalFilterWidget::widget([
            'attribute' => 'phrase_id',
            'label'     => 'Поисковая фраза',             // Вернули ваше название
            'action'    => ['/wb-search/phrase'],         // Вернули ваш URL обработки формы
            'ajaxUrl'   => \yii\helpers\Url::to(['wb/ajax-search', 'type' => 'phrases']),
            'initValueText' => $currentPhraseId ? \app\models\WbPhrasesDirectory::getAjaxText($currentPhraseId) : '',
            'pluginOptions' => [
                'minimumInputLength' => 2,                // Снова устанавливаем 2 символа вместо 3
            ],
        ]) ?>

        </div>
    </div>

<div class="row custom-compact-grid mb-3">
    <div class="col-md-12">


    <?php if ($phrase_id && $dataProvider->totalCount > 0): ?>
        <?php
        $gridColumns = [
            [
                'attribute' => 'nmID',
                'label' => 'Арт WB',
                'width' => '100px',
                'format' => 'raw',
                'contentOptions' => ['class' => 'text-muted small'],
                'value' => function($model) {
                    return Html::a($model['nmID'], ['wb/detail', 'DPFilterForm' => ['nm_id' => $model['nmID']]], ['data-pjax' => 0]);
                }
            ],
            [
                'attribute' => 'title',
                'label' => 'Товар / Позиция',
                'format' => 'raw',
                'width' => '300px',
                'contentOptions' => ['style' => 'min-width:300px; white-space:normal;', 'class' => ''],
//                'contentOptions' => ['style' => 'width:300px; white-space:normal;', 'class' => 'text-muted'],

                'value' => function($model) {
                    return Html::a($model['title'], ['/wb-search/card', 'DPFilterForm' => ['nm_id' => $model['nmID']]], ['data-pjax' => 0]);
                }
            ],
/*
    [
        'attribute' => 'avg_freq',
        'label' => 'Ср. част', // Твое название
        'format' => 'integer',
        'hAlign' => 'center',
        'width' => '80px',
        'headerOptions' => ['title' => 'Средняя частотность за неделю (week_frequency)'],
        'contentOptions' => ['class' => 'text-secondary', 'style' => 'background-color: #fcfcfc;'],
    ],
*/
            [
                'attribute' => 'total_clicks',
                'label' => 'Клики',
                'hAlign' => 'center',
                'width' => '80px',
                'contentOptions' => ['style' => 'background-color: #fcfcfc;', 'class' => 'small'],
            ],
            [
                'attribute' => 'total_orders',
                'label' => 'Заказы',
                'hAlign' => 'center',
                'width' => '80px',
                'contentOptions' => ['style' => 'background-color: #f8f9ff; font-weight:bold;', 'class' => 'small text-primary'],
            ],
        ];

        foreach ($uniqueDates as $date) {
            $gridColumns[] = [
                'attribute' => $date,
                'label' => date('d.m', strtotime($date)),
                'format' => 'raw',
                'hAlign' => 'center',
                'headerOptions' => ['class' => 'text-center small'],
                'contentOptions' => function($model) use ($date) {
                    $data = $model[$date] ?? null;
                    $pos = $data['pos'] ?? 0;
                    $orders = $data['orders'] ?? 0;
                    $style = [];
                    
                    if ($pos > 0 && $pos <= 10) {
                        $style['background-color'] = '#ebfbee';
                        $style['color'] = '#2b8a3e';
                    } elseif ($pos > 10 && $pos <= 50) {
                        $style['background-color'] = '#fff9db';
                    }
                    
                    if ($orders > 0) {
                        $style['font-weight'] = '900';
                        $style['text-decoration'] = 'underline';
                    }
                    return ['style' => $style, 'title' => $orders > 0 ? "Заказов: $orders" : ""];
                },
                'value' => function($model) use ($date) {
                    $data = $model[$date] ?? null;
                    return ($data['pos'] ?? 0) ?: '';
                }
            ];
        }
    ?>

    <div id="grid-scroll" style="max-height: 70vh; position: relative; " > <!-- overflow: auto; -->
            <?=  GridView::widget([
                'dataProvider' => $dataProvider,
                'columns' => $gridColumns,

                'pjax'          => true,
                'responsive'    => false,
                'bordered'      => true,
                'condensed'     => true,

                'hover'         => true,
                'containerOptions' => ['style' => 'max-height: 70vh; overflow: auto;'], // Фикс скролла

                'floatHeader'   => true,
                'rowOptions' => function ($model, $key, $index, $grid) {
                    return ['class' => 'clickable-row', 'style' => 'cursor: pointer;'];
                },

                'panel' => [
                    'type' => GridView::TYPE_DEFAULT,
                    'heading' => '<i class="fas fa-list"></i> Товары по запросу: ' . Html::encode($phrase),
                    'before' => false, 'after' => false,
                ],
            ]);
            ?>
    </div>

</div></div>
    <?php elseif ($phrase): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            По фразе <strong>"<?= Html::encode($phrase) ?>"</strong> данных не найдено.
        </div>
    <?php endif; ?>
</div>

<style>
    /* Липкая колонка для названия товара */
    .search-phrase-report [data-col-seq="1"] {
        position: sticky !important;
        left: 0;
        background-color: white !important;
        z-index: 100;
        border-right: 2px solid #dee2e6 !important;
    }
    .search-phrase-report thead th[data-col-seq="1"] { z-index: 110; }
    
    /* Стилизация скролла */
.kv-grid-container::-webkit-scrollbar { width: 10px; height: 10px; }
.kv-grid-container::-webkit-scrollbar-track { background: #f8f9fa; }
.kv-grid-container::-webkit-scrollbar-thumb { background: #ccc; border-radius: 5px; }
body {overflow-x: hidden;}

.table > tbody > tr.selected-row > td, 
.table > tbody > tr.selected-row > th {
    background-color: #ffb7f8 !important;
/*    --bs-table-accent-bg: #ffb7f8 !important; */ /* Для Bootstrap 5 (сброс переменных) */
    color: #000 !important; /* На всякий случай, если текст станет нечитаемым */
}


#grid-scroll .kv-sticky-column {
    position: sticky !important;
    left: 0 !important;
    background-color: white !important;
    z-index: 5 !important; /* Для ячеек тела */
    border-right: 2px solid #dee2e6 !important;
}

#grid-scroll th.kv-sticky-column {
    z-index: 10 !important; /* Заголовок должен быть выше */
    top: 0; /* Если используется floatHeader, это может помочь */
}


.table > tbody > tr.selected-row > td.pos-top-10 {  background-color: #f37be7 !important; }
.table > tbody > tr.selected-row > td.pos-top-50 {  background-color: #f897ee !important; }

.custom-compact-grid .table, .kv-grid-container {overflow: auto !important;}
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


<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
<script src="https://d3js.org/d3.v7.min.js"></script>

<?php //var_dump($chartData); ?>

<?php if ($phrase_id && !empty($chartData)): ?>
<div class="card border-0 shadow-sm mb-4 bg-light">
    <div class="card-body">
        <h5 class="card-title">Динамика ТОП-5 карточек (по кликам) и частотности запроса</h5>
        <div id="chartdiv" style="width: 100%; height: 400px;"></div>
    </div>
</div>
<?php /*
<script>
am5.ready(function() {
    var root = am5.Root.new("chartdiv");
    root.setThemes([am5themes_Animated.new(root)]);

    var chart = root.container.children.push(am5xy.XYChart.new(root, {
        paddingBottom: 30,
        layout: root.verticalLayout,
        panX: true,
        panY: true,
        wheelX: "panX",
        wheelY: "zoomX",
        pinchZoomX: true
    }));

    var cursor = chart.set("cursor", am5xy.XYCursor.new(root, {
        behavior: "none"
    }));
    cursor.lineY.set("visible", false);

    // Данные из PHP
    var data = <?= json_encode($chartData) ?>;
    var top5Info = <?= json_encode($top5Info) ?>;

    // --- ОСЬ X (ДАТЫ) ---
    var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
        maxDeviation: 0.2,
        baseInterval: { timeUnit: "day", count: 1 },
        renderer: am5xy.AxisRendererX.new(root, {}),
        tooltip: am5.Tooltip.new(root, {}),
        // Включаем встроенную группировку amCharts (по дням, неделям, месяцам)
        groupData: true,
        groupIntervals: [
            { timeUnit: "day", count: 1 },
            { timeUnit: "week", count: 1 },
            { timeUnit: "month", count: 1 }
        ]
    }));

    // --- ОСИ Y ---
    // Левая ось (Клики)
    var yAxisClicks = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        renderer: am5xy.AxisRendererY.new(root, { pan: "zoom" })
    }));
    // Правая ось (Частотность запроса)
    var yAxisFreq = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        renderer: am5xy.AxisRendererY.new(root, { opposite: true })
    }));

    // --- ГРАФИК ЧАСТОТНОСТИ ---
    var freqSeries = chart.series.push(am5xy.LineSeries.new(root, {
        name: "Частотность (WB)",
        xAxis: xAxis,
        yAxis: yAxisFreq, // Привязываем к правой оси
        valueYField: "frequency",
        valueXField: "date",
        tooltip: am5.Tooltip.new(root, { labelText: "{name}: {valueY}" })
    }));
    // Делаем линию частотности пунктирной и потолще, чтобы отличалась
    freqSeries.strokes.template.setAll({ strokeWidth: 3, strokeDasharray: [4, 4], stroke: am5.color(0x555555) });
    freqSeries.data.setAll(data);

    // --- ГРАФИКИ ТОП-5 КАРТОЧЕК ---
    for (var key in top5Info) {
        var series = chart.series.push(am5xy.LineSeries.new(root, {
            name: top5Info[key],
            xAxis: xAxis,
            yAxis: yAxisClicks, // Привязываем к левой оси
            valueYField: key,
            valueXField: "date",
            tooltip: am5.Tooltip.new(root, { labelText: "Клики: {valueY}" })
        }));
        series.strokes.template.setAll({ strokeWidth: 2 });
        // Сглаживание линий (опционально)
        series.set("curveFactory", d3.curveMonotoneX);
        series.data.setAll(data);
    }

    // Скроллбар
    chart.set("scrollbarX", am5.Scrollbar.new(root, { orientation: "horizontal" }));

// 1. Создаем контейнер для легенды ПОД графиком
// Мы добавляем его в root.container, где уже лежит chart
var legend = chart.children.push(am5.Legend.new(root, {
  centerX: am5.p50,
  x: am5.p50,
  layout: am5.GridLayout.new(root, {
    maxColumns: 3, // Опционально: сколько колонок в ряду
    fixedWidthGrid: true
  })
}));

// Важный момент: чтобы легенда не "прилипала" к оси X
legend.set("marginTop", 30);


// 4. Передаем все серии (и частотность, и карточки) в легенду
legend.data.setAll(chart.series.values);


    chart.appear(1000, 100);
});
</script>
*/

/*
<script>
am5.ready(function() {

    // --- ДАННЫЕ ИЗ PHP ---
    var data = <?= Json::encode($chartData) ?>;
    var top5 = <?= Json::encode($top5Info) ?>;
    var phraseName = "<?= \yii\helpers\Html::encode($phrase) ?>"; // Имя запроса

    var seriesColors = [
        am5.color(0x0d6efd), // Ярко-синий
        am5.color(0x198754), // Зеленый
        am5.color(0xd63384), // Розовый/Пурпурный
        am5.color(0x0dcaf0), // Голубой
        am5.color(0x6610f2)  // Фиолетовый
    ];

    var root = am5.Root.new("chartdiv");
    root.setThemes([am5themes_Animated.new(root)]);

    // Устанавливаем ВЕРТИКАЛЬНЫЙ лейаут для главного контейнера
    // Это заставит легенду встать ПОД график, если мы добавим её второй
    root.container.set("layout", root.verticalLayout);

    var chart = root.container.children.push(am5xy.XYChart.new(root, {
        panX: true,
        panY: false,
        wheelX: "panX",
        wheelY: "zoomX",
        paddingBottom: 20
    }));

    // --- ОСИ ---
    var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
        baseInterval: { timeUnit: "day", count: 1 },
        renderer: am5xy.AxisRendererX.new(root, {}),
        tooltip: am5.Tooltip.new(root, {}),
        groupData: true // Включаем автоматическую группировку (недели/месяцы)
    }));

    var yAxisClicks = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        renderer: am5xy.AxisRendererY.new(root, {})
    }));

    var yAxisFreq = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        renderer: am5xy.AxisRendererY.new(root, { opposite: true })
    }));


    // --- СЕРИЯ: ЧАСТОТНОСТЬ (Правая ось, пунктир) ---
    var freqSeries = chart.series.push(am5xy.LineSeries.new(root, {
        name: phraseName,
        xAxis: xAxis,
        yAxis: yAxisFreq,
        valueYField: "frequency",
        valueXField: "date",
        stroke: am5.color(0xffb74d), // Светло-оранжевый
        fill: am5.color(0xffb74d),   // Цвет заливки
        tooltip: am5.Tooltip.new(root, { labelText: "{name}: {valueY}" })
    }));

// Настраиваем заливку (Area Chart)
    freqSeries.fills.template.setAll({
        fillOpacity: 0.2,
        visible: true
    });

    // Настраиваем саму линию (пунктир)
    freqSeries.strokes.template.setAll({ 
        strokeWidth: 2, 
        strokeDasharray: [5, 5] 
    });

//    freqSeries.strokes.template.setAll({ strokeWidth: 2, strokeDasharray: [5, 5] });
    freqSeries.data.setAll(data);

    // --- СЕРИИ: ТОП-5 КАРТОЧЕК (Левая ось, сплошные) ---
    var colorIndex = 0;
    Object.keys(top5).forEach(function(nmId) {
        var color = seriesColors[colorIndex % seriesColors.length]; // Зацикливаем, если вдруг карточек больше 5
        var series = chart.series.push(am5xy.LineSeries.new(root, {
            name: top5[nmId],
            xAxis: xAxis,
            yAxis: yAxisClicks,
            valueYField: "card_" + nmId,
            stroke: color,
            valueXField: "date",
            tooltip: am5.Tooltip.new(root, { labelText: "{name}: {valueY} кликов" })
        }));

        series.get("tooltip").get("background").setAll({
            fill: color,
            fillOpacity: 0.8
        });

        series.strokes.template.setAll({ strokeWidth: 2 });
        // Плавные линии
        series.set("curveFactory", d3.curveMonotoneX);
        series.data.setAll(data);
        colorIndex++;
    });

    // Добавляем курсор
    chart.set("cursor", am5xy.XYCursor.new(root, { behavior: "zoomX" }));

    // --- ЛЕГЕНДА (Добавляем её ПОСЛЕ графика в root.container) ---
    var legend = root.container.children.push(am5.Legend.new(root, {
        centerX: am5.p50,
        x: am5.p50,
        marginTop: 20,
        layout: root.gridLayout
    }));

    legend.data.setAll(chart.series.values);

    // Анимация появления
    chart.appear(1000, 100);
});
</script>
*/
?>
<script>
am5.ready(function() {
    // 1. Функция обрезки текста
    function truncate(str, n) {
        if (!str) return "Запрос";
        return (str.length > n) ? str.slice(0, n).trim() + "..." : str;
    }

    // 2. Инициализация Root
    var root = am5.Root.new("chartdiv");
    root.setThemes([am5themes_Animated.new(root)]);

    // Вертикальный лейаут ОБЯЗАТЕЛЕН, чтобы легенда упала ВНИЗ
    root.container.set("layout", root.verticalLayout);

    // 3. Создание графика
    var chart = root.container.children.push(am5xy.XYChart.new(root, {
        panX: true,
        panY: false,
        wheelX: "panX",
        wheelY: "zoomX",
        paddingBottom: 20,
        layout: root.verticalLayout
    }));

    // Данные из PHP
    var data = <?= \yii\helpers\Json::encode($chartData) ?>;
    var top5 = <?= \yii\helpers\Json::encode($top5Info) ?>;
    var rawPhrase = <?= \yii\helpers\Json::encode($phrase) ?>;

    // --- ОСИ ---
    var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
        baseInterval: { timeUnit: "day", count: 1 },
        renderer: am5xy.AxisRendererX.new(root, {}),
        tooltip: am5.Tooltip.new(root, {})
    }));

    var yAxisClicks = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        renderer: am5xy.AxisRendererY.new(root, {})
    }));

    var yAxisFreq = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        renderer: am5xy.AxisRendererY.new(root, { opposite: true })
    }));

    // --- СЕРИЯ 1: ПОИСКОВАЯ ФРАЗА (Оранжевая заливка, задний план) ---
    var freqSeries = chart.series.push(am5xy.LineSeries.new(root, {
        name: rawPhrase,
        xAxis: xAxis,
        yAxis: yAxisFreq,
        valueYField: "frequency",
        valueXField: "date",
        stroke: am5.color(0xffb74d),
        fill: am5.color(0xffb74d), // Задаем fill, чтобы цвет был в легенде
        tooltip: am5.Tooltip.new(root, { labelText: "{name}: {valueY}" })
    }));

    freqSeries.fills.template.setAll({ fillOpacity: 0.15, visible: true });
//    freqSeries.strokes.template.setAll({ strokeWidth: 2, strokeDasharray: [5, 5] });
//    freqSeries.set("legendMarkerDasharray", [3, 3]);
    freqSeries.data.setAll(data);

    // --- СЕРИИ: КАРТОЧКИ ---
    var seriesColors = [
        am5.color(0x0d6efd), am5.color(0x198754), am5.color(0xd63384),
        am5.color(0x0dcaf0), am5.color(0x6610f2)
    ];

    var colorIndex = 0;
    Object.keys(top5).forEach(function(nmId) {
        var color = seriesColors[colorIndex % seriesColors.length];
        var shortTitle = truncate(top5[nmId], 27);

        var series = chart.series.push(am5xy.SmoothedXLineSeries.new(root, {
            name: shortTitle,
            xAxis: xAxis,
            yAxis: yAxisClicks,
            valueYField: "card_" + nmId,
            valueXField: "date",
            valueYGroupedField: "card_" + nmId, 
            calculateAggregates: true,
            valueYGrouped: "sum", 
            baseInterval: { timeUnit: "day", count: 1 }, 

            stroke: color,
            fill: color, // Важно для легенды
            tooltip: am5.Tooltip.new(root, {
//                getFillFromSprite: false,
                fontSize: "11px",
                labelText: "{name}[/]: {valueY}"
            })
        }));

        if (color) {
            series.set("stroke", color);
            series.set("fill", color);
        }
        // Цвет тултипа под цвет линии
        series.strokes.template.setAll({ strokeWidth: 2.5, stroke: color, interactive: true });
        series.set("trackAppearance", true);

        series.strokes.template.states.create("hover", {
            fillOpacity: 1,
            strokeOpacity: 1,
            strokeWidth: 3
        });

        // Маленькие точки (делают легенду "понятной", рисуя цветной круг)
/*
        series.bullets.push(function() {
            return am5.Bullet.new(root, {
                sprite: am5.Circle.new(root, { radius: 3, fill: color })
            });
        });
*/

        series.data.setAll(data);
        colorIndex++;
    });


// 4. ЛЕГЕНДА
    var legend = root.container.children.push(am5.Legend.new(root, {
        centerX: am5.p50,
        x: am5.p50,
        marginTop: 15,
        layout: root.gridLayout,
        useDefaultMarker: true,
        layout: am5.GridLayout.new(root, {
            maxColumns: 3,       // максимум 3 элемента в строке
            fixedWidthGrid: true // чтобы колонки были одинаковой ширины (по желанию)
        })
    }));

    // --- НАСТРОЙКА ШАБЛОНОВ (До заполнения данными) ---

    // 1. Увеличиваем контейнер для маркера, чтобы линия была длиннее
    legend.markers.template.setAll({ 
        width: 10, 
        height: 1, 
        centerY: am5.p50,
        verticalAlign: "middle"
    });

    legend.markerRectangles.template.setAll({
        fillOpacity: 0,      // без заливки, только линия
        strokeOpacity: 1,
        strokeWidth: 2,
        height: 0,           // либо 1–2, чтобы видно было
        centerY: am5.p50
    });
    // 3. Чтобы линии не были прозрачными (копируется с графиков без заливки)
    legend.itemContainers.template.setAll({
        toggleKey: "active",
        cursorOverStyle: "pointer",
        interactive: true,
        paddingLeft: 10,
        paddingRight: 10,
        paddingTop: 5,
        paddingBottom: 5,
        verticalAlign: "middle"
    });

    // --- СПЕЦИФИКА СЕРИЙ ---

    // Принудительно ставим пунктир для частотности (ДО отрисовки легенды)
    freqSeries.set("legendMarkerDasharray", [3, 3]);

    // Для всех остальных серий (карточек) убеждаемся, что в легенде они будут сплошными
    chart.series.each(function(series) {
        if (series !== freqSeries) {
            series.set("legendMarkerDasharray", []);
        }
    });

    // Интерактивность
    legend.itemContainers.template.events.on("pointerover", function(e) {
        var series = e.target.dataItem.dataContext;
        series.hover();
    });
    legend.itemContainers.template.events.on("pointerout", function(e) {
        var series = e.target.dataItem.dataContext;
        series.unhover();
    });

    legend.labels.template.setAll({ fontSize: 13, fontWeight: "400" });

    // --- ФИНАЛЬНЫЙ ШАГ ---
    // Передаем данные только когда все настройки шаблонов завершены
    legend.data.setAll(chart.series.values);

    chart.set("cursor", am5xy.XYCursor.new(root, { behavior: "zoomX" }));
    chart.appear(1000, 100);


});
</script>

<?php endif; ?>