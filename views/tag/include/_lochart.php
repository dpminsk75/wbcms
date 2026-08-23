<?php
/** @var array $chartData */
?>

<script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

<script>
(function() {
    var chartData = <?= json_encode($chartData) ?>;
    if (!chartData || chartData.length === 0) return;

    am5.ready(function() {
        var root = am5.Root.new("chartdiv");
        root.setThemes([am5themes_Animated.new(root)]);

        var chart = root.container.children.push(am5xy.XYChart.new(root, {
            panX: true, panY: true, wheelX: "panX", wheelY: "none",
            layout: root.verticalLayout
        }));

        var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
            maxDeviation: 0.1,
            baseInterval: { timeUnit: "day", count: 1 },
            firstDayOfWeek: 1,
            renderer: am5xy.AxisRendererX.new(root, { minGridDistance: 50 })
        }));

        xAxis.get("renderer").labels.template.setAll({
            location: 0.5, // Центрируем метку по дню
            multiLocation: 0.5,
            rotation: -45,
            fontSize: "12px",
            centerY: am5.p50,
            centerX: am5.p100,
            paddingRight: 15
        });

        var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
            style: { fontSize: '11px' },
            extraMax: 0.1,
            min: 0,
            renderer: am5xy.AxisRendererY.new(root, {})
        }));

        yAxis.get("renderer").labels.template.setAll({
            fontSize: "12px"
        });


        yAxis.children.moveValue(am5.Label.new(root, {
            text: "Кол-во (шт)",
            rotation: -90,
            y: am5.p50,
            centerX: am5.p50,
            fontSize: "12px",
        }), 0);

        // Считаем сумму количества для сортировки (лидеры вниз)
        var countTotals = {};
        chartData.forEach(function(item) {
            Object.keys(item).forEach(function(key) {
                if (key.indexOf('value_') === 0) {
                    var id = key.replace('value_', '');
                    countTotals[id] = (countTotals[id] || 0) + item[key];
                }
            });
        });

        var sortedIds = Object.keys(countTotals).sort((a, b) => countTotals[b] - countTotals[a]);

        // --- ИЗМЕНЕНИЯ ЗДЕСЬ ---
        // Определяем палитру оптимистичных пастельных цветов
/*
        var pastelColors = [
            am5.color(0xFFB3BA), // Нежно-розовый
            am5.color(0xBAE1FF), // Небесно-голубой
            am5.color(0xBAFFC9), // Мятно-зеленый
            am5.color(0xFFFFBA), // Светло-желтый
            am5.color(0xE0BBE4), // Лавандовый
            am5.color(0xFFDFBA)  // Персиковый
        ];
*/
/*
var pastelColors = [
    am5.color(0xfac858), // Теплый желтый
    am5.color(0xee6666), // Мягкий красный/коралловый
    am5.color(0x91cc75), // Салатовый
    am5.color(0x73c0de), // Голубой (другой оттенок)
    am5.color(0x3ba272), // Зеленый
    am5.color(0xfc8452), // Оранжевый
    am5.color(0x9a60b4), // Пурпурный
    am5.color(0xea7ccc)  // Розовый
];
*/
/*
var pastelColors = [
    am5.color(0xFF6B6B), // Коралловый
    am5.color(0x4ECDC4), // Бирюзовый
    am5.color(0xFFE66D), // Солнечный
    am5.color(0x1A535C), // Темный морс (для контраста снизу)
    am5.color(0xFF9F1C), // Оранжевый
    am5.color(0x70C1B3), // Мятный
    am5.color(0x247BA0), // Океан
    am5.color(0xF25F5C)  // Розовый
];
*/
/*        
var pastelColors = [
    am5.color(0x76D7C4), // Мягкая бирюза
    am5.color(0x85C1E9), // Небесно-голубой
    am5.color(0xF7DC6F), // Солнечно-желтый
    am5.color(0xBB8FCE), // Приглушенная лаванда
    am5.color(0x82E0AA), // Светло-зеленый
    am5.color(0xF8C471), // Мягкий песочный
    am5.color(0xD7BDE2), // Светло-сиреневый
    am5.color(0xAED6F1)  // Бледно-голубой
];
*/

var pastelColors = [
//    am5.color(0xf7fcf5), 
    am5.color(0x005a32),
    am5.color(0x238b45), 
    am5.color(0x41ab5d), 
    am5.color(0x74c476), 

    am5.color(0xa1d99b), 

    am5.color(0xc7e9c0), 
    am5.color(0xe5f5e0) 
];

// Зеленые тона - #f7fcf5, #e5f5e0, #c7e9c0, #a1d99b, #74c476, #41ab5d, #238b45, #005a32


        sortedIds.forEach(function(id, index) { // Добавили index в параметры callback-функции
            // --- ИЗМЕНЕНИЯ ЗДЕСЬ ---
            // Выбираем цвет из палитры по индексу (с зацикливанием)
            var seriesColor = pastelColors[index % pastelColors.length];
            // ----------------------

            var series = chart.series.push(am5xy.LineSeries.new(root, {
                name: "Арт: " + id,
                xAxis: xAxis,
                yAxis: yAxis,
                valueYField: "value_" + id, // Это cnt из контроллера
                valueXField: "date",
                stacked: true,
                stroke: seriesColor, // --- ИЗМЕНЕНИЯ ЗДЕСЬ --- Устанавливаем цвет линии
                fill: seriesColor,
                curveFactory: d3.curveMonotoneX, // Сглаживание как на скрине
                tooltip: am5.Tooltip.new(root, {
                    labelText: "{name}: [bold]{valueY}[/] шт. ({sum_" + id + "} руб.)"
                })
            }));

            // --- ИЗМЕНЕНИЯ ЗДЕСЬ ---
            // Устанавливаем цвет заливки и делаем её более прозрачной для пастельного эффекта
            series.fills.template.setAll({ 
                visible: true, 
                fillOpacity: 0.7, // Уменьшили прозрачность для мягкости
                fill: seriesColor 
            });
            // ----------------------
            
//            series.strokes.template.setAll({ strokeWidth: 2 });
            series.strokes.template.setAll({ 
                strokeWidth: 1,
//                stroke: seriesColor // Явно передаем цвет линии
            });

            series.data.setAll(chartData);
            series.appear();
        });

/* итоговая линия */
    var color = am5.color(0x005a32);

    var totalSeries = chart.series.push(am5xy.SmoothedXLineSeries.new(root, {
        name: "Итого заказов",
        xAxis: xAxis,
        yAxis: yAxis,
        valueYField: "total_cnt",
        valueXField: "date",
        stroke: color, 
        strokeWidth: 2,
//        tension: 0.5,
        curveFactory: d3.curveMonotoneX,
        tooltip: am5.Tooltip.new(root, {
            labelText: "Итого: [bold]{valueY}[/] шт.",
            fillOpacity: 0.7, // Уменьшили прозрачность для мягкости
            fill: color
        })
    }));

    totalSeries.bullets.push(function(root, series, dataItem) {
        // ВАЖНО: Если баббл пытается отрисоваться в легенде, 
        // у него нет поля total_cnt. В таком случае просто ничего не рисуем!
        if (!dataItem.dataContext || typeof dataItem.dataContext.total_cnt === "undefined") {
            return;
        }

        return am5.Bullet.new(root, {
            locationY: 1,
            sprite: am5.Label.new(root, {
                text: "{total_cnt}",
                fill: color,
                fontWeight: "bold",
                fontSize: "12px",
                centerX: am5.p50,
                centerY: am5.p100,
                dy: -12,
                populateText: true,
                paddingTop: 2, paddingBottom: 2, paddingLeft: 5, paddingRight: 5,
                background: am5.RoundedRectangle.new(root, {
                    fill: am5.color(0xffffff),
                    stroke: color,
                    strokeWidth: 1.5,
                    cornerRadiusTL: 5, cornerRadiusTR: 5, cornerRadiusBL: 5, cornerRadiusBR: 5
                })
            })
        });
    });

    totalSeries.data.setAll(chartData);
    totalSeries.appear();
/* end итого */


//    chart.set("cursor", am5xy.XYCursor.new(root, { behavior: "zoomX" }));
    chart.set("cursor", am5xy.XYCursor.new(root, {
        behavior: "panX", // zoomX
        wheelX: "none",
        xAxis: xAxis
    }));


    var legend = chart.children.push(am5.Legend.new(root, { 
        centerX: am5.p50, 
        x: am5.p50 
    }));

    legend.labels.template.setAll({
        fontSize: 12, // Укажите нужный размер в пикселях
        fontWeight: "400"
    });

    legend.data.setAll(chart.series.values);

    legend.itemContainers.template.events.on("pointerover", function(e) {
      var itemContainer = e.target;
      var series = itemContainer.dataItem.dataContext;
      series.hover();
    });
    legend.itemContainers.template.events.on("pointerout", function(e) {
      var itemContainer = e.target;
      var series = itemContainer.dataItem.dataContext;
      series.unhover();
    });


        chart.appear(1000, 100);
    });
})();
</script>