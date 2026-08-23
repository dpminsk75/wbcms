<?php

use kartik\grid\GridView;
use yii\helpers\Json;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ArrayDataProvider */
/* @var $chartData array */

$this->title = 'Сравнение заказов по годам: 2024 - 2026';
$this->params['breadcrumbs'][] = $this->title;

// Подключаем ресурсы amCharts 5
$this->registerJsFile('https://cdn.amcharts.com/lib/5/index.js');
$this->registerJsFile('https://cdn.amcharts.com/lib/5/xy.js');
$this->registerJsFile('https://cdn.amcharts.com/lib/5/themes/Animated.js');
?>

<div class="agg-orders-report-compare">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    Динамика суммы заказов по месяцам
                </div>
                <div class="card-body">
                    <!-- Контейнер для графика -->
                    <div id="chartdiv" style="width: 100%; height: 450px; background-color: #f9f9f9;"></div>
                </div>
            </div>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'responsive' => true,
        'hover' => true,
        'pjax' => true,
        'panel' => [
            'type' => GridView::TYPE_DEFAULT,
            'heading' => '<i class="fas fa-table"></i> Таблица сравнения',
        ],
        // Исправляем ошибку вложенности через вторую строку заголовка
        'beforeHeader' => [
            [
                'columns' => [
                    ['content' => 'Месяцы', 'options' => ['colspan' => 1, 'class' => 'text-center bg-light']],
                    ['content' => '2024 Год', 'options' => ['colspan' => 2, 'class' => 'text-center kv-align-middle table-info']],
                    ['content' => '2025 Год', 'options' => ['colspan' => 2, 'class' => 'text-center kv-align-middle table-warning']],
                    ['content' => '2026 Год', 'options' => ['colspan' => 2, 'class' => 'text-center kv-align-middle table-success']],
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
            // 2024
            [
                'attribute' => 'qty_2024',
                'label' => 'Кол-во',
                'format' => ['integer'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'amount_2024',
                'label' => 'Сумма',
                'format' => ['decimal', 0],
                'pageSummary' => true,
            ],
            // 2025
            [
                'attribute' => 'qty_2025',
                'label' => 'Кол-во',
                'format' => ['integer'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'amount_2025',
                'label' => 'Сумма',
                'format' => ['decimal', 0],
                'pageSummary' => true,
            ],
            // 2026
            [
                'attribute' => 'qty_2026',
                'label' => 'Кол-во',
                'format' => ['integer'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'amount_2026',
                'label' => 'Сумма',
                'format' => ['decimal', 0],
                'pageSummary' => true,
            ],
        ],
        'showPageSummary' => true,
    ]); ?>

</div>

<script>
// Ожидаем готовности amCharts
am5.ready(function() {
    
    // Создаем корневой элемент
    var root = am5.Root.new("chartdiv");

    // Устанавливаем тему
    root.setThemes([am5themes_Animated.new(root)]);

    // Создаем график
    var chart = root.container.children.push(am5xy.XYChart.new(root, {
        panX: false,
        panY: false,
        wheelX: "panX",
        wheelY: "zoomX",
        layout: root.verticalLayout
    }));

    // Передаем данные из PHP
    var data = <?= Json::encode($chartData) ?>;

    // Осевые настройки
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

    // Функция создания серий (столбцов)
    function makeSeries(name, fieldName, color) {
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

    // Выводим суммы по годам
    makeSeries("2024 год", "amount_2024");
    makeSeries("2025 год", "amount_2025");
    makeSeries("2026 год", "amount_2026");

    // Добавляем легенду
    var legend = chart.children.push(am5.Legend.new(root, {
        centerX: am5.p50,
        x: am5.p50
    }));
    legend.data.setAll(chart.series.values);

    // Анимация появления
    chart.appear(1000, 100);

}); 
</script>