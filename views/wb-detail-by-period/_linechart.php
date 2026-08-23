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

    var Linechart = rootTime.container.children.push(am5xy.XYChart.new(rootTime, {
        panX: true,
        panY: true,
        wheelX: "panX",
        wheelY: "zoomX",
        pinchZoomX: true,
        layout: rootTime.verticalLayout
    }));

    // Ось X (Категории: недели)
    var xAxis = Linechart.xAxes.push(am5xy.CategoryAxis.new(rootTime, {
        categoryField: "week_key",
        renderer: am5xy.AxisRendererX.new(rootTime, {
            minGridDistance: 15, // Чем меньше число, тем плотнее сетка (выводит все метки)
            minorGridEnabled: true
        }),
        tooltip: am5.Tooltip.new(rootTime, {})
    }));
    xAxis.data.setAll(data);


    xAxis.get("renderer").labels.template.setAll({
      rotation: -45,     // Угол наклона в градусах
      fontSize: "12px",  // Размер шрифта
      centerY: am5.p50,
      centerX: am5.p100,
      paddingRight: 15   // Отступ, чтобы текст не прилипал
    });

    var yAxisMoney = Linechart.yAxes.push(am5xy.ValueAxis.new(rootTime, {
        extraMax: 0.1,
        style: {
            fontSize: '10px'
        },
        renderer: am5xy.AxisRendererY.new(rootTime, {
            strokeOpacity: 0.1 // Сделает линию оси мягче
        })
    }));
    yAxisMoney.children.moveValue(am5.Label.new(rootTime, {
        text: "Кол-во (шт)",
        rotation: -90,
        y: am5.p50,
        centerX: am5.p50,
        fontSize: "11px",
    }), 0);

    var yAxisQty = Linechart.yAxes.push(am5xy.ValueAxis.new(rootTime, {
        extraMax: 0.1,
        syncWithAxis: yAxisMoney,
        renderer: am5xy.AxisRendererY.new(rootTime, { opposite: true, strokeOpacity: 0.1 })
    }));
    yAxisQty.children.moveValue(am5.Label.new(rootTime, {
        text: "Цена продажи (₽)",
        rotation: 270,
        y: am5.p50,
        centerX: am5.p50,
        fontSize: "11px"
    }), yAxisQty.children.length);

    yAxisMoney.get("renderer").labels.template.setAll({
      fontSize: "10px"
    });

    // Уменьшаем шрифт для количественной оси
    yAxisQty.get("renderer").labels.template.setAll({
      fontSize: "10px"
    });

    // Чтобы сетка от двух осей не перемешивалась, можно отключить её у правой
    yAxisMoney.get("renderer").grid.template.setAll({ visible: false });

    // Функция создания линий
    function createSeries(name, field, targetAxis, color, isSpline = false) {
        var series = Linechart.series.push(am5xy.SmoothedXLineSeries.new(rootTime, {
            name: name,
            xAxis: xAxis,
            yAxis: targetAxis, // Привязываем к переданной оси
            valueYField: field,
            tension: isSpline ? 0.5 : 1, 
            categoryXField: "week_key",
            tooltip: am5.Tooltip.new(rootTime, {
                labelText: "{name}: {valueY.formatNumber('#.0')}"
            })
        }));

        if (color) {
            series.set("stroke", color);
            series.set("fill", color);
        }

        series.bullets.push(function() {
            return am5.Bullet.new(rootTime, {
                sprite: am5.Circle.new(rootTime, {
                    radius: 4,
                    fill: series.get("fill")
                })
            });
        });

        series.data.setAll(data);
        series.appear();
        return series;
    }

    // Добавляем линии (поля из вашего SQL select)
    // retail_amount идет на ПРАВУЮ ось
//    createSeries("Выручка (Retail)", "retail_amount", yAxisRight, am5.color(0xe97659));
//    createSeries("К перечислению", "ppvz_for_pay", yAxisRight, am5.color(0x6794dc));

    // Остальные на ЛЕВУЮ ось
//    createSeries("Кол-во продаж", "sales_count", yAxisLeft, am5.color(0x67b7dc));
//    createSeries("К перечислению", "ppvz_for_pay", yAxisLeft, am5.color(0x6794dc));

    // Выплаты на ЛЕВУЮ ось
//    createSeries("К перечислению", "ppvz_for_pay",  yAxisQty, am5.color(0x660ec8),true);
    createSeries("Цена продажи",   "retail_amount", yAxisQty, am5.color(0x660ec8),true);

    // Продажи на ПРАВУЮ ось
    createSeries("Кол-во продаж", "sales_count", yAxisMoney, am5.color(0xf965cf));


    // Добавим легенду, чтобы не путаться
    var legend = Linechart.children.push(am5.Legend.new(rootTime, {
        centerX: am5.p50,
        x: am5.p50,
        marginTop: 15,
        marginBottom: 15
    }));
    legend.data.setAll(Linechart.series.values);

    Linechart.set("cursor", am5xy.XYCursor.new(rootTime, {}));
    Linechart.appear(1000, 100);

});
</script>