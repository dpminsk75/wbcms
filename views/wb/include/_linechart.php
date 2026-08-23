<?php
use yii\helpers\Json;
?>

<script>
am5.ready(function() {

    var rootTime      = am5.Root.new("timeline_div");
    rootTime.locale = am5locales_ru_RU;
    rootTime.setThemes([am5themes_Animated.new(rootTime)]);

    // --- ЛИНЕЙНЫЙ ГРАФИК ---
    var data = <?= $timelineJson ?>;

    Linechart = rootTime.container.children.push(am5xy.XYChart.new(rootTime, {
        panX: true,
        panY: true,
        layout: rootTime.verticalLayout
    }));
//    Linechart.set("height", 350);
/*
        wheelX: "panX",
        wheelY: "zoomX",
        pinchZoomX: true,
*/

    var xAxis = Linechart.xAxes.push(am5xy.DateAxis.new(rootTime, {
        maxDeviation: 0.2,
        baseInterval: { timeUnit: "day", count: 1 }, 
        firstDayOfWeek: 1,
        renderer: am5xy.AxisRendererX.new(rootTime, {
            minGridDistance: 15,
            minorGridEnabled: true
        }),
        tooltip: am5.Tooltip.new(rootTime, {}),
        groupData: true, // ВКЛЮЧАЕМ группировку
        groupIntervals: [
            { timeUnit: "day", count: 1 },
            { timeUnit: "week", count: 1 },
            { timeUnit: "month", count: 1 }
        ],
        groupCount: 500,

        gridIntervals: [
          { timeUnit: "day", count: 1 }, 
          { timeUnit: "day", count: 2 }, 
          { timeUnit: "day", count: 5 }, 
          { timeUnit: "week", count: 1 },
          { timeUnit: "month", count: 1 }
        ],

        dateFormats: {
            day: "yyyy-MM-dd",
            week: "yyyy-ww",
            month: "yyyy-MM"
        },
        periodChangeDateFormats: {
            day: "yyyy-MM-dd",
            week: "yyyy-ww",
            month: "yyyy-MM"
        }

    }));

    xAxis.get("renderer").labels.template.setAll({
        location: 0.5, // Центрируем метку по дню
        multiLocation: 0.5
    });

    // Настройка внешнего вида меток (ваша ротация)
    xAxis.get("renderer").labels.template.setAll({
        rotation: -45,
        fontSize: "12px",
        centerY: am5.p50,
        centerX: am5.p100,
        paddingRight: 15
    });

    // ВАЖНО: Если данные - строки, amCharts должен знать, как их парсить
    rootTime.dateFormatter.setAll({
      dateFormat: "yyyy-MM-dd",
      dateFields: ["odate"]
    });



    var yAxisMoney = Linechart.yAxes.push(am5xy.ValueAxis.new(rootTime, {
        extraMax: 0.25,
        style: {
            fontSize: '10px'
        },
        min: 0,
        renderer: am5xy.AxisRendererY.new(rootTime, {
            strokeOpacity: 0.1 // Сделает линию оси мягче
        })
    }));
    yAxisMoney.children.moveValue(am5.Label.new(rootTime, {
        text: "Цена в заказе (₽)",
        rotation: -90,
        y: am5.p50,
        centerX: am5.p50,
        fontSize: "11px",
    }), 0);

    var yAxisQty = Linechart.yAxes.push(am5xy.ValueAxis.new(rootTime, {
        extraMax: 0.5,
        strictMinMax: false,
        min: 0,
        renderer: am5xy.AxisRendererY.new(rootTime, { opposite: true, strokeOpacity: 0.1 })
    }));
    yAxisQty.children.moveValue(am5.Label.new(rootTime, {
        text: "Кол-во (шт)",
        rotation: 270,
        y: am5.p50,
        centerX: am5.p50,
        fontSize: "11px"
    }), yAxisQty.children.length);

    yAxisMoney.get("renderer").labels.template.setAll({
      fontSize: "11px"
    });

    // Уменьшаем шрифт для количественной оси
    yAxisQty.get("renderer").labels.template.setAll({
      fontSize: "11px"
    });

    // Чтобы сетка от двух осей не перемешивалась, можно отключить её у правой
    yAxisMoney.get("renderer").grid.template.setAll({ visible: false });

    yAxisQty.get("renderer").labels.template.setAll({ fill: am5.color(0x660ec8) });
    yAxisMoney.get("renderer").labels.template.setAll({ fill: am5.color(0xf965cf) });


    window.setGraphInterval = function(unit) {
        if (unit === 'day') {
            xAxis.set("groupInterval", { timeUnit: "day", count: 1 });
        } else {
            xAxis.set("groupInterval", { timeUnit: unit, count: 1 });
        }
        const buttons = document.querySelectorAll('.btn-group .btn');
        
        buttons.forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes("'" + unit + "'")) {
                btn.classList.add('active');
            }
        });
    };

function createColumnSeries(name, field, targetAxis, color, showLabels = false) {

    var currentGroupRule = (field === "cnt") ? "sum" : "average";

    var series = Linechart.series.push(am5xy.ColumnSeries.new(rootTime, {
        name: name,
        xAxis: xAxis,
        yAxis: targetAxis,
        valueYField: field,
        valueXField: "odate",
        valueYGroupedField: field,
        calculateAggregates: true,
        valueYGrouped: currentGroupRule,
        baseInterval: { timeUnit: "day", count: 1 },
        tooltip: am5.Tooltip.new(rootTime, {
            labelText: "{valueX.formatDate()}[/]\n{name}: [bold]{valueY.formatNumber('#.0')}[/]"
        })
    }));

    series.data.processor = am5.DataProcessor.new(rootTime, {
        dateFields: ["odate"],
        dateFormat: "yyyy-MM-dd"
    });

    // Полупрозрачные столбики, чтобы не спорили с линиями цены за внимание
    series.columns.template.setAll({
        fill: color,
        stroke: color,
        fillOpacity: 0.3,
        strokeOpacity: 0.5,
        strokeWidth: 1,
        cornerRadiusTL: 4,
        cornerRadiusTR: 4,
        width: am5.percent(55)
    });

    if (showLabels) {
        var bulletTemplate = series.bullets.push(function() {
            return am5.Bullet.new(rootTime, {
                locationY: 0, // привязка к нулевой линии (оси X), а не к вершине столбика
                sprite: am5.Label.new(rootTime, {
                    text: "{valueY.formatNumber('#.#')}",
                    fill: color,
                    fontWeight: "bold",
                    fontSize: "12px",
                    centerX: am5.p50,
                    centerY: am5.p100, // якорь снизу лейбла -> сам лейбл рисуется выше точки
                    dy: -6,
                    populateText: true,
                    paddingTop: 2, paddingBottom: 2, paddingLeft: 5, paddingRight: 5,
                    background: am5.RoundedRectangle.new(rootTime, {
                        fill: am5.color(0xffffff),
                        stroke: color,
                        strokeWidth: 1.5,
                        cornerRadiusTL: 5, cornerRadiusTR: 5, cornerRadiusBL: 5, cornerRadiusBR: 5
                    })
                })
            });
        });
        series.set("labelBullet", bulletTemplate);
    }

    var processedData = JSON.parse(JSON.stringify(data)).map(item => {
        item[field] = Number(item[field]);
        return item;
    });
    series.data.setAll(processedData);
    series.appear();
    return series;
}

function createSeries(name, field, targetAxis, color, isSpline = false, sWidth = 1, showLabels = false) {

// Possible values for any of the aggregate-function settings are: "open", "close", "low", "high", "average", "sum", "extreme".
// https://www.amcharts.com/docs/v5/charts/xy-chart/axes/date-axis/#Custom_aggregation_functions
    var currentGroupRule = (field === "cnt") ? "sum" : "average";
    
    var series = Linechart.series.push(am5xy.SmoothedXLineSeries.new(rootTime, { // SmoothedXLineSeries LineSeries
        name: name,
        xAxis: xAxis,
        yAxis: targetAxis,
        valueYField: field,
        valueXField: "odate", 
        valueYGroupedField: field, 
        calculateAggregates: true,
        valueYGrouped: currentGroupRule, 
        baseInterval: { timeUnit: "day", count: 1 }, 

        tension: isSpline ? 0.5 : 1, 
        tooltip: am5.Tooltip.new(rootTime, {
//            labelText: "{name}: {valueY.formatNumber('#.0')}"
            labelText: "{valueX.formatDate()}[/]\n{name}: [bold]{valueY.formatNumber('#.0')}[/]"
        })
    }));

    // ИЗМЕНЕНИЕ 2: Учим серию превращать строки "2026-01-25" в объекты даты
    series.data.processor = am5.DataProcessor.new(rootTime, {
        dateFields: ["odate"],
        dateFormat: "yyyy-MM-dd"
    });

    if (color) {
        series.set("stroke", color);
        series.set("fill", color);
    }

    series.strokes.template.setAll({
        strokeWidth: sWidth,
        stroke: color
    });

    series.bullets.push(function() {
        return am5.Bullet.new(rootTime, {
            sprite: am5.Circle.new(rootTime, {
                radius: 4,
                fill: series.get("fill")
            })
        });
    });


    if (showLabels) {
        var bulletTemplate = series.bullets.push(function() {
            return am5.Bullet.new(rootTime, {
                locationY: 1,
                sprite: am5.Label.new(rootTime, {
                    text: "{valueY.formatNumber('#.#')}",
                    fill: color,
                    fontWeight: "bold",
                    fontSize: "12px",
                    centerX: am5.p50,
                    centerY: am5.p100,
                    dy: -12,
                    populateText: true,
                    paddingTop: 2, paddingBottom: 2, paddingLeft: 5, paddingRight: 5,
                    background: am5.RoundedRectangle.new(rootTime, {
                        fill: am5.color(0xffffff),
                        stroke: color,
                        strokeWidth: 1.5,
                        cornerRadiusTL: 5, cornerRadiusTR: 5, cornerRadiusBL: 5, cornerRadiusBR: 5
                    })
                })
            });
        });
        series.set("labelBullet", bulletTemplate);
    }

    var processedData = JSON.parse(JSON.stringify(data)).map(item => {
        item[field] = Number(item[field]);
        return item;
    });
    series.data.setAll(processedData);

//    series.data.setAll(data);
    series.appear();
    return series;
}

    var initialUnit = "day";
    if (data.length > 50) {
        initialUnit = "month";
    } else if (data.length > 14) {
        initialUnit = "week";
    }

    window.setGraphInterval(initialUnit);

    createColumnSeries("Кол-во", "cnt", yAxisQty, am5.color(0x660ec8), true);
    createSeries("Цена, ₽", "finished_price", yAxisMoney, am5.color(0xf965cf), true, 2, true);
    createSeries("Цена со скд, ₽", "apwd", yAxisMoney, am5.color(0x2ca02c), true, 2, true);

    // Добавим легенду, чтобы не путаться
    var legend = Linechart.children.push(am5.Legend.new(rootTime, {
        centerX: am5.p50,
        x: am5.p50,
        marginTop: 15,
        marginBottom: 15,
        layout: rootTime.horizontalLayout // Явно задаем горизонтальный лейаут
    }));

    legend.itemContainers.template.setAll({
        toggleKey: "active",
        cursorOverStyle: "pointer",
        interactive: true,
        paddingLeft: 15,
        paddingRight: 15,
        paddingTop: 5,
        paddingBottom: 5
    });

    legend.data.setAll(Linechart.series.values);

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


    Linechart.set("cursor", am5xy.XYCursor.new(rootTime, {
        behavior: "zoomX",
        xAxis: xAxis
    }));

    Linechart.appear(1000, 100);




});

</script>