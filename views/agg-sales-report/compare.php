<?php

use kartik\grid\GridView;
use yii\helpers\Json;
use yii\helpers\Html;
use kartik\icons\Icon;

Icon::map($this); 

$this->registerCssFile('https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined', [
    'depends' => [\app\assets\AppAsset::class],
]);

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ArrayDataProvider */
/* @var $lastMonthProvider yii\data\ArrayDataProvider */
/* @var $currentMonthProvider yii\data\ArrayDataProvider */
/* @var $chartData array */
/* @var $lastMonthName string */
/* @var $currentMonthName string */

$this->title = 'Сравнение продаж по годам: 2024 - 2026';
$this->params['breadcrumbs'][] = $this->title;



$percentRender = function($model, $key, $index, $column) {
    if (!is_array($model) || !isset($model[$column->attribute])) {
        return ''; // Возвращаем пустоту, если ключ отсутствует (например, в строке итогов)
    }
    $val = $model[$column->attribute];
    if ($val == 0) return '0%';
    return ($val > 0 ? '+' : '') . number_format($val, 1, '.', ' ') . '%';
};

$percentContentOptions = function($model, $key, $index, $column) {
    if (!is_array($model) || !isset($model[$column->attribute])) {
        return ['style' => 'text-align: right;'];
    }
    $val = $model[$column->attribute];
    return ['class' => ($val >= 0 ? 'text-success' : 'text-danger') . ' font-weight-bold', 'style' => 'text-align: right;'];
};
?>
<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

<?php
$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', [
    'depends' => [\app\assets\ChartAsset::class]
]);
?>

<div class="agg-orders-report-compare">

    <h1 class="mb-4"><?= Html::encode($this->title) ?></h1>

    <div class="card card-outline card-info mb-5 shadow-sm">
        <div class="card-header bg-wb-blue-header text-white"> <!-- bg-info -->
            <h3 class="card-title font-weight-bold m-0"><i class="fas fa-calendar-check"></i> ПРОШЛЫЙ МЕСЯЦ: <?= Html::encode($prevMonthName) ?> (Данные в целом за месяц)</h3>
        </div>
        <div class="card-body p-3">
            <?= GridView::widget([
                'dataProvider' => $pmSummaryProvider,
                'summary' => false,
                'beforeHeader' => [[
                    'columns' => [
                        ['content' => 'Период аналитики', 'options' => ['class' => 'text-center bg-light font-weight-bold']],
                        ['content' => $pmYears[0] . ' Год', 'options' => ['colspan' => 2, 'class' => 'text-center table-secondary']],
                        ['content' => $pmYears[1] . ' Год', 'options' => ['colspan' => 3, 'class' => 'text-center table-warning']],
                        ['content' => $pmYears[2] . ' Год', 'options' => ['colspan' => 3, 'class' => 'text-center table-success']],
                    ],
                ]],
                'columns' => [
                    ['attribute' => 'period_name', 'label' => 'Временной отрезок', 'contentOptions' => ['class' => 'font-weight-bold']],
                    ['attribute' => 'qty_0', 'label' => 'Кол-во', 'format' => 'integer', 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'amt_0', 'label' => 'Сумма', 'format' => ['decimal', 0], 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'qty_1', 'label' => 'Кол-во', 'format' => 'integer', 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'amt_1', 'label' => 'Сумма', 'format' => ['decimal', 0], 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'change_1', 'label' => 'Изм. %', 'format' => 'raw', 'value' => $percentRender, 'contentOptions' => $percentContentOptions],
                    ['attribute' => 'qty_2', 'label' => 'Кол-во', 'format' => 'integer', 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'amt_2', 'label' => 'Сумма', 'format' => ['decimal', 0], 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'change_2', 'label' => 'Изм. %', 'format' => 'raw', 'value' => $percentRender, 'contentOptions' => $percentContentOptions],
                ]
            ]); ?>
        </div>
    </div>


    <div class="card card-outline card-success mb-5 shadow-sm">
        <div class="card-header bg-wb-orange-header text-white"> <!-- bg-success -->
            <h3 class="card-title font-weight-bold m-0"><i class="fas fa-bolt"></i> ТЕКУЩИЙ МЕСЯЦ: <?= Html::encode($currMonthName) ?> (Срез темпа продаж по вчерашнее число)</h3>
        </div>
        <div class="card-body p-3">
            <?= GridView::widget([
                'dataProvider' => $cmSummaryProvider,
                'summary' => false,
                'beforeHeader' => [[
                    'columns' => [
                        ['content' => 'Период аналитики', 'options' => ['class' => 'text-center bg-light font-weight-bold']],
                        ['content' => $years[0] . ' Год', 'options' => ['colspan' => 2, 'class' => 'text-center table-secondary']],
                        ['content' => $years[1] . ' Год', 'options' => ['colspan' => 3, 'class' => 'text-center table-warning']],
                        ['content' => $years[2] . ' Год', 'options' => ['colspan' => 3, 'class' => 'text-center table-success']],
                    ],
                ]],
                'columns' => [
                    ['attribute' => 'period_name', 'label' => 'Временной отрезок', 'contentOptions' => ['class' => 'font-weight-bold']],
                    ['attribute' => 'qty_0', 'label' => 'Кол-во', 'format' => 'integer', 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'amt_0', 'label' => 'Сумма', 'format' => ['decimal', 0], 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'qty_1', 'label' => 'Кол-во', 'format' => 'integer', 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'amt_1', 'label' => 'Сумма', 'format' => ['decimal', 0], 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'change_1', 'label' => 'Изм. %', 'format' => 'raw', 'value' => $percentRender, 'contentOptions' => $percentContentOptions],
                    ['attribute' => 'qty_2', 'label' => 'Кол-во', 'format' => 'integer', 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'amt_2', 'label' => 'Сумма', 'format' => ['decimal', 0], 'hAlign' => GridView::ALIGN_RIGHT],
                    ['attribute' => 'change_2', 'label' => 'Изм. %', 'format' => 'raw', 'value' => $percentRender, 'contentOptions' => $percentContentOptions],
                ]
            ]); ?>
        </div>
    </div>




    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-wb-green-vibrant-header text-white font-weight-bold"> <!-- bg-dark -->
                    <i class="fas fa-chart-bar"></i> Динамика суммы продаж по месяцам
                </div>
                <div class="card-body">
                    <div id="chartdiv" style="width: 100%; height: 450px; background-color: #f9f9f9;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-5">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'responsive' => true,
            'hover' => true,
            'pjax' => true,
            'panel' => [
                'type' => GridView::TYPE_DEFAULT,
                'heading' => '<i class="fas fa-table"></i> Таблица сравнения по месяцам',
                'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            ],
            'footerRowOptions' => ['style' => 'text-align: right; font-weight: bold;'], 

            'beforeHeader' => [
                [
                    'columns' => [
                        ['content' => 'Месяцы', 'options' => ['colspan' => 1, 'class' => 'text-center bg-light']],
                        ['content' => '2024 Год', 'options' => ['colspan' => 2, 'class' => 'text-center kv-align-middle table-info']],
                        ['content' => '2025 Год', 'options' => ['colspan' => 3, 'class' => 'text-center kv-align-middle table-warning']],
                        ['content' => '2026 Год', 'options' => ['colspan' => 3, 'class' => 'text-center kv-align-middle table-success']],
                    ],
                ]
            ],
            'columns' => [
                [
                    'attribute' => 'month_name',
                    'label' => 'Отчетный период',
                    'headerOptions' => ['class' => 'kv-align-bottom'],
                    'contentOptions' => ['class' => 'font-weight-bold'],
                ],
                ['attribute' => 'qty_2024'   , 'label' => 'Кол-во', 'hAlign' => 'right', 'format' => ['integer'],    'pageSummary' => true, 'contentOptions' => ['style' => 'text-align: right;'], 'footerOptions' => ['style' => 'text-align: right;'] ],
                ['attribute' => 'amount_2024', 'label' => 'Сумма',  'hAlign' => 'right', 'format' => ['decimal', 0], 'pageSummary' => true, 'contentOptions' => ['style' => 'text-align: right;'], 'footerOptions' => ['style' => 'text-align: right;'] ],
                
                ['attribute' => 'qty_2025'   , 'label' => 'Кол-во', 'hAlign' => 'right', 'format' => ['integer'],    'pageSummary' => true, 'contentOptions' => ['style' => 'text-align: right;'] ],
                ['attribute' => 'amount_2025', 'label' => 'Сумма',  'hAlign' => 'right', 'format' => ['decimal', 0], 'pageSummary' => true, 'contentOptions' => ['style' => 'text-align: right;'] ],
                ['attribute' => 'for_pay_2025', 'label' => 'К оплате',  'hAlign' => 'right', 'format' => ['decimal', 0], 'pageSummary' => true, 'contentOptions' => ['text-align: right;'] ],
                
                ['attribute' => 'qty_2026'   , 'label' => 'Кол-во', 'hAlign' => 'right', 'format' => ['integer'],    'pageSummary' => true, 'contentOptions' => ['style' => 'text-align: right;'] ],
                ['attribute' => 'amount_2026',  'label' => 'Сумма продаж',  'hAlign' => 'right', 'format' => ['decimal', 0], 'pageSummary' => true, 'contentOptions' => ['text-align: right;'] ],
                ['attribute' => 'for_pay_2026', 'label' => 'К оплате',  'hAlign' => 'right', 'format' => ['decimal', 0], 'pageSummary' => true, 'contentOptions' => ['text-align: right;'] ],
            ],
            'showPageSummary' => true,

        ]); ?>
    </div>

    <div class="mb-5">
        <?= GridView::widget([
            'dataProvider' => $lastMonthProvider,
            'responsive' => true,
            'hover' => true,
            'pjax' => true,
            'panel' => [
                'type' => GridView::TYPE_PRIMARY,
                'heading' => '<i class="fas fa-history"></i> ТОП товаров за прошлый месяц (' . Html::encode($lastMonthName) . ') по годам (Сортировка по сумме за 2026 г.)',
            ],
            'beforeHeader' => [
                [
                    'columns' => [
                        ['content' => 'Информация о товаре', 'options' => ['colspan' => 2, 'class' => 'text-center bg-light font-weight-bold']],
                        ['content' => '2024 Год', 'options' => ['colspan' => 2, 'class' => 'text-center kv-align-middle table-info']],
                        ['content' => '2025 Год', 'options' => ['colspan' => 3, 'class' => 'text-center kv-align-middle table-warning']],
                        ['content' => '2026 Год', 'options' => ['colspan' => 3, 'class' => 'text-center kv-align-middle table-success']],
                    ],
                ]
            ],
            'columns' => [
                [
                    'attribute' => 'nmID',
                    'label' => 'SKU (nmID)',
                    'contentOptions' => ['class' => 'font-weight-bold'],
                ],
                [
                    'attribute' => 'title',
                    'label' => 'Название товара',
                ],
                // 2024
                ['attribute' => 'qty_2024',    'label' => 'Кол-во','hAlign' => 'right', 'format' => ['integer'], 'pageSummary' => true],
                ['attribute' => 'amount_2024', 'label' => 'Сумма', 'hAlign' => 'right', 'format' => ['decimal', 0], 'pageSummary' => true],
                // 2025
                ['attribute' => 'qty_2025',    'label' => 'Кол-во','hAlign' => 'right', 'format' => ['integer'], 'pageSummary' => true],
                ['attribute' => 'amount_2025', 'label' => 'Сумма', 'hAlign' => 'right', 'format' => ['decimal', 0], 'pageSummary' => true],
                ['attribute' => 'avg_price_2025', 'label' => 'Ср. цена',  'hAlign' => 'right','format' => ['decimal', 2], 'pageSummary' => true],
                // 2026
                ['attribute' => 'qty_2026',    'label' => 'Кол-во','hAlign' => 'right', 'format' => ['integer'], 'pageSummary' => true],
                ['attribute' => 'amount_2026', 'label' => 'Сумма', 'hAlign' => 'right', 'format' => ['decimal', 0], 'pageSummary' => true],
                ['attribute' => 'avg_price_2026', 'label' => 'Ср. цена',  'hAlign' => 'right','format' => ['decimal', 2], 'pageSummary' => true],
            ],
            'showPageSummary' => true,
        ]); ?>
    </div>

    <div class="mb-5">
        <?= GridView::widget([
            'dataProvider' => $currentMonthProvider,
            'responsive' => true,
            'hover' => true,
            'pjax' => true,
            'panel' => [
                'type' => GridView::TYPE_SUCCESS,
                'heading' => '<i class="fas fa-calendar-alt"></i> ТОП товаров за текущий месяц (' . Html::encode($currentMonthName) . ') по годам (Сортировка по сумме за 2026 г.)',
            ],
            'beforeHeader' => [
                [
                    'columns' => [
                        ['content' => 'Информация о товаре', 'options' => ['colspan' => 2, 'class' => 'text-center bg-light font-weight-bold']],
                        ['content' => '2024 Год', 'options' => ['colspan' => 2, 'class' => 'text-center kv-align-middle table-info']],
                        ['content' => '2025 Год', 'options' => ['colspan' => 3, 'class' => 'text-center kv-align-middle table-warning']],
                        ['content' => '2026 Год', 'options' => ['colspan' => 3, 'class' => 'text-center kv-align-middle table-success']],
                    ],
                ]
            ],
            'columns' => [
                [
                    'attribute' => 'nmID',
                    'label' => 'SKU (nmID)',
                    'contentOptions' => ['class' => 'font-weight-bold'],
                ],
                [
                    'attribute' => 'title',
                    'label' => 'Название товара',
                ],
                // 2024
                ['attribute' => 'qty_2024',    'label' => 'Кол-во', 'hAlign' => 'right','format' => ['integer'], 'pageSummary' => true],
                ['attribute' => 'amount_2024', 'label' => 'Сумма',  'hAlign' => 'right','format' => ['decimal', 0], 'pageSummary' => true],
                // 2025
                ['attribute' => 'qty_2025',    'label' => 'Кол-во', 'hAlign' => 'right','format' => ['integer'], 'pageSummary' => true],
                ['attribute' => 'amount_2025', 'label' => 'Сумма',  'hAlign' => 'right','format' => ['decimal', 0], 'pageSummary' => true],
                ['attribute' => 'avg_price_2025', 'label' => 'Ср. цена',  'hAlign' => 'right','format' => ['decimal', 2], 'pageSummary' => true],
                // 2026
                ['attribute' => 'qty_2026',    'label' => 'Кол-во', 'hAlign' => 'right','format' => ['integer'], 'pageSummary' => true],
                ['attribute' => 'amount_2026', 'label' => 'Сумма',  'hAlign' => 'right','format' => ['decimal', 0], 'pageSummary' => true],
                ['attribute' => 'avg_price_2026', 'label' => 'Ср. цена',  'hAlign' => 'right','format' => ['decimal', 2], 'pageSummary' => true],
            ],
            'showPageSummary' => true,
        ]); ?>
    </div>

</div>

<script>
am5.ready(function() {
    var root = am5.Root.new("chartdiv");
    root.setThemes([am5themes_Animated.new(root)]);

    // Установка русской локали для графика
    root.locale = am5locales_ru_RU;

    var chart = root.container.children.push(am5xy.XYChart.new(root, {
        panX: false, 
        panY: false, 
        wheelX: "panX", 
        wheelY: "zoomX", 
        layout: root.verticalLayout
    }));

    var data = <?= Json::encode($chartData) ?>;

    var xRenderer = am5xy.AxisRendererX.new(root, { minGridDistance: 30 });
    var xAxis = chart.xAxes.push(am5xy.CategoryAxis.new(root, {
        categoryField: "month_name",
        renderer: xRenderer,
        tooltip: am5.Tooltip.new(root, {})
    }));
    xAxis.data.setAll(data);

    var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        renderer: am5xy.AxisRendererY.new(root, {})
    }));

    function makeSeries(name, fieldName) {
        var series = chart.series.push(am5xy.ColumnSeries.new(root, {
            name: name,
            xAxis: xAxis,
            yAxis: yAxis,
            valueYField: fieldName,
            categoryXField: "month_name",
            tooltip: am5.Tooltip.new(root, {
                labelText: "{name}: [bold]{valueY}[/]"
            })
        }));

        series.columns.template.setAll({
            tooltipY: 0,
            cornerRadiusTL: 5,
            cornerRadiusTR: 5
        });

        series.data.setAll(data);
        series.appear();
    }

    makeSeries("2024 год", "amount_2024");
    makeSeries("2025 год", "amount_2025");
    makeSeries("2026 год", "amount_2026");

    // Легенда графика с правильным сжатием (по 3 элемента в строке)
    var legend = root.container.children.push(am5.Legend.new(root, { 
        centerX: am5.p50, 
        x: am5.p50, 
        marginTop: 15, 
        layout: root.gridLayout, 
        useDefaultMarker: true, 
        layout: am5.GridLayout.new(root, { maxColumns: 3, fixedWidthGrid: true }) 
    }));
    
    legend.data.setAll(chart.series.values);
    chart.appear(1000, 100);
}); 
</script>

<style>
.card-header h3.card-title {
    font-size: 15px;
}
.svg-inline--fa.fa-w-14, .svg-inline--fa.fa-w-10, .svg-inline--fa.fa-w-16 {
    width: 17px;
    padding-right: 5px;
}

.bg-wb-danger-header {
    background: linear-gradient(
        97.26deg, 
        #ff4d4d 0.49%,  /* Яркий кораллово-красный (аналог лилового) */
        #f73b4c 14.88%, 
        #ef2a5b 29.27%, 
        #e31c6a 43.14%, 
        #d50e7a 57.02%, /* Здесь уходим в мадженту (аналог перехода в фиолетовый) */
        #c30089 70.89%, 
        #ae0099 84.76%, 
        #9900a8 99.15%  /* Финиш в глубокий пурпур */
    ), 
    linear-gradient(#0000000d, #0000000d) !important;
    border: none !important;
}

.bg-wb-orange-header {
    background: linear-gradient(
        97.26deg, 
        #ff4500 0.49%,  /* Насыщенный красно-оранжевый */
        #ff5e00 14.88%, 
        #ff7700 29.27%, 
        #ff8f00 43.14%, 
        #ffa600 57.02%, /* Переход в чистый оранжевый */
        #ffbc00 70.89%, 
        #ffd000 84.76%, 
        #ffe500 99.15%  /* Финиш в теплое золото */
    ), 
    linear-gradient(#0000000d, #0000000d) !important;
    border: none !important;
}

.bg-wb-blue-header {
    background: linear-gradient(
        97.26deg, 
        #002fa7 0.49%,  /* Глубокий ультрамарин (международный синий) */
        #0046c7 14.88%, 
        #005ce6 29.27%, 
        #0072ff 43.14%, 
        #008cff 57.02%, /* Переход в яркий синий */
        #00a4ff 70.89%, 
        #00bcff 84.76%, 
        #00d2ff 99.15%  /* Финиш в сочную лазурь / циан */
    ), 
    linear-gradient(#0000000d, #0000000d) !important;
    border: none !important;
}

.bg-wb-green-deep-header {
    background: linear-gradient(
        97.26deg, 
        #062e1a 0.49%,  /* Темная хвоя / глубокий лесной */
        #093e24 14.88%, 
        #0d4f30 29.27%, 
        #11613c 43.14%, 
        #147349 57.02%, /* Переход в насыщенный изумруд */
        #188556 70.89%, 
        #1c9864 84.76%, 
        #20ab72 99.15%  /* Финиш в приглушенный мятно-зеленый */
    ), 
    linear-gradient(#0000000d, #0000000d) !important;
    border: none !important;
}


.bg-wb-green-vibrant-header {
    background: linear-gradient(
        97.26deg, 
        #0b3c36 0.49%,  /* Темный сине-зеленый (база для контраста) */
        #0f4f45 14.88%, 
        #136254 29.27%, 
        #177664 43.14%, 
        #1b8b75 57.02%, /* Плавный выход в зелень */
        #1fa086 70.89%, 
        #24b698 84.76%, 
        #28ccaa 99.15%  /* Финиш в плотную морскую волну */
    ), 
    linear-gradient(#0000000d, #0000000d) !important;
    border: none !important;
}
</style>