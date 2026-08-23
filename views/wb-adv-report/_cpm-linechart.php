<?php
use yii\helpers\Json;
?>
<script>
var CPMLinechart;

am5.ready(function() {

    var rootTime      = am5.Root.new("cpmtimeline_div");
    rootTime.locale = am5locales_ru_RU;
    rootTime.setThemes([am5themes_Animated.new(rootTime)]);

    // --- ЛИНЕЙНЫЙ ГРАФИК ---
    var data = <?= $timelineJson ?>;

    CPMLinechart = rootTime.container.children.push(am5xy.XYChart.new(rootTime, {
        panX: true,
        panY: true,
        wheelX: "panX",
        wheelY: "zoomX",
        pinchZoomX: true,
        layout: rootTime.verticalLayout
    }));

    var xAxisCPM = CPMLinechart.xAxes.push(am5xy.DateAxis.new(rootTime, {
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

    xAxisCPM.get("renderer").labels.template.setAll({
        location: 0.5, // Центрируем метку по дню
        multiLocation: 0.5
    });

    // Настройка внешнего вида меток (ваша ротация)
    xAxisCPM.get("renderer").labels.template.setAll({
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

// _yAxisQty
// yAxisMoney
    var yAxisQty = CPMLinechart.yAxes.push(am5xy.ValueAxis.new(rootTime, {
        extraMax: 0.1,
        style: {
            fontSize: '10px'
        },
        min: 0,
        renderer: am5xy.AxisRendererY.new(rootTime, {
            strokeOpacity: 0.1 // Сделает линию оси мягче
        })
    }));
    yAxisQty.children.moveValue(am5.Label.new(rootTime, {
        text: "Показатель (%)",
        rotation: -90,
        y: am5.p50,
        centerX: am5.p50,
        fontSize: "12px",
    }), 0);

    var yAxisMoney = CPMLinechart.yAxes.push(am5xy.ValueAxis.new(rootTime, {
        extraMax: 0.1,
//        syncWithAxis: yAxisQty,
        min: 0,
        renderer: am5xy.AxisRendererY.new(rootTime, { opposite: true, strokeOpacity: 0.1 })
    }));
    yAxisMoney.children.moveValue(am5.Label.new(rootTime, {
        text: "Сумма (₽)",
        rotation: 270,
        y: am5.p50,
        centerX: am5.p50,
        fontSize: "11px"
    }), yAxisQty.children.length);

    yAxisQty.get("renderer").labels.template.setAll({
      fontSize: "11px"
    });

    // Уменьшаем шрифт для количественной оси
    yAxisMoney.get("renderer").labels.template.setAll({
      fontSize: "11px"
    });

    // Чтобы сетка от двух осей не перемешивалась, можно отключить её у правой
    yAxisQty.get("renderer").grid.template.setAll({ visible: false });

    yAxisQty.get("renderer").labels.template.setAll({ fill: am5.color(0x2196f3) });
    yAxisMoney.get("renderer").labels.template.setAll({ fill: am5.color(0xf44336) });

window.setGraphIntervalCPM = function(unit, element) {
    if (typeof xAxisCPM !== 'undefined') {
        xAxisCPM.set("groupInterval", { timeUnit: unit, count: 1 });
    }

    const container = element 
        ? element.closest('.btn-group') 
        : document.getElementById('cpmtimeline_buttons');

    if (!container) {
        console.warn("Контейнер #timeline_buttons не найден");
        return;
    }

    const buttons = container.querySelectorAll('[data-unit]');
    buttons.forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-unit') === unit);
    });
};

function createSeries(name, field, targetAxis, color, isSpline = false, sWidth = 1, showLabels = false) {

// Possible values for any of the aggregate-function settings are: "open", "close", "low", "high", "average", "sum", "extreme".
// https://www.amcharts.com/docs/v5/charts/xy-chart/axes/date-axis/#Custom_aggregation_functions
    var currentGroupRule = (field === "sum") ? "sum" : "average";
    const baseWidth = Number(sWidth) || 2;
    
    var series = CPMLinechart.series.push(am5xy.SmoothedXLineSeries.new(rootTime, { // SmoothedXLineSeries LineSeries
        name: name,
        xAxis: xAxisCPM,
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
        strokeWidth: baseWidth,
        stroke: color,
        interactive: true
    });

    series.strokes.template.states.create("hover", {
        fillOpacity: 1,
        strokeOpacity: 1,
        strokeWidth: baseWidth + 2
    });

    series.set("trackAppearance", true);

    series.bullets.push(function() {
        return am5.Bullet.new(rootTime, {
            sprite: am5.Circle.new(rootTime, {
                radius: 4,
                fill: series.get("fill")
            })
        });
    });

    let tooltip = am5.Tooltip.new(rootTime, {
        labelText: "{valueX.formatDate()}[/]\n{name}: [bold]{valueY.formatNumber('#,###.0')}[/]"
    });
    tooltip.label.setAll({
        fontSize: "12px",
        fill: am5.color(0x000000)
    });
    series.set("tooltip", tooltip);


    if (showLabels) {
        series.bullets.push(function() {
            return am5.Bullet.new(rootTime, {
                locationY: 1,
                sprite: am5.Label.new(rootTime, {
                    text: "{valueY.formatNumber('#,###.#')}",
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

    var initialUnit = "week";
    if (data.length > 28) {
        initialUnit = "month";
    } else if (data.length > 14) {
        initialUnit = "week";
    }

    window.setGraphIntervalCPM(initialUnit);

    // Продажи на ЛЕВУЮ ось
    createSeries("CPM, ₽", "CPM", yAxisQty, am5.color(0x2196f3), true, 1, true); // 0xf965cf
    createSeries("CPO, ₽", "CPO", yAxisQty, am5.color(0xc767dc), true, 1, true); // 0x660ec8
//    createSeries("Заказы", "orders", yAxisQty, am5.color(0x5067de), false, 1, true);

    createSeries("Расходы",   "sum", yAxisMoney, am5.color(0xf44336), true, 3, true); // f44336 0xf96666



    // Добавим легенду, чтобы не путаться
    var legend = CPMLinechart.children.push(am5.Legend.new(rootTime, {
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

    legend.data.setAll(CPMLinechart.series.values);

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


    CPMLinechart.set("cursor", am5xy.XYCursor.new(rootTime, {
        behavior: "panX", // zoomX
        wheelX: "zoomX",
        xAxis: xAxisCPM
    }));

    CPMLinechart.appear(1000, 100);


    function toggle(index) {
      var series = chart.series.getIndex(index);
      toggleSeries(series);
    }

    function toggleSeries(series) {
      if (series.get("visible")) {
        series.hide();
      }
      else {
        series.show();
      }
    }

});




</script>