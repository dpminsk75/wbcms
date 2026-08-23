<?php
use yii\helpers\Json;
?>
<?php
$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', [
    'depends' => [\app\assets\ChartAsset::class]
]);
?>

<script>
var AppChart;

am5.ready(function() {
    var root = am5.Root.new("stacked_column_div");
    root.setThemes([am5themes_Animated.new(root)]);
    root.locale = am5locales_ru_RU;

    root.numberFormatter.setAll({
      numberFormat: "#,###.##", // Формат: разделитель тысяч, точка для дробей
      numericSymbols: []        // Отключает сокращения типа "k", "M" (если они не нужны)
    });

    var data = <?= $statsJson ?>;
    var appTypes = [32, 64, 1];
/*
    var appNames = {
        "1":  "desktop_windows",
        "32": "android",
        "64": "apple"
    };
*/    
    var appNames = {
        "1":  "💻",
        "32": "🤖",
        "64": "🍎"
    };

    AppChart = root.container.children.push(am5xy.XYChart.new(root, {
        panX: true,
        panY: true,
        wheelX: "panX",
        wheelY: "zoomX",
        pinchZoomX: true,
        layout: root.verticalLayout
    }));

    // --- НАСТРОЙКА ОСИ X (как ты прислал) ---
    var xAxis = AppChart.xAxes.push(am5xy.DateAxis.new(root, {
        baseInterval: { timeUnit: "day", count: 1 },
        groupData: true, 
        groupCount: 500,
        groupIntervals: [
            { timeUnit: "day", count: 1 },
            { timeUnit: "week", count: 1 },
            { timeUnit: "month", count: 1 }
        ],
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
        },
        renderer: am5xy.AxisRendererX.new(root, { minGridDistance: 50 })
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

    var yAxisLeft  = AppChart.yAxes.push(am5xy.ValueAxis.new(root, { 
        extraMax: 0.2,
        style: {  fontSize: '10px' },
        min: 0,
        renderer: am5xy.AxisRendererY.new(root, {}) 
    }));

    var yAxisRight = AppChart.yAxes.push(am5xy.ValueAxis.new(root, {
        extraMax: 0.1,
        style: {  fontSize: '10px' },
        min: 0,
//        syncWithAxis: yAxisLeft,
        renderer: am5xy.AxisRendererY.new(root, { opposite: true })
    }));
/*
    yAxisRight.children.moveValue(am5.Label.new(root, {
        text: "Показы (шт)",
        rotation: 270,
        y: am5.p50,
        centerX: am5.p50,
        fill: am5.color(0xf965cf),
        fontSize: "10px"
    }), yAxisRight.children.length);
*/

    yAxisLeft.get("renderer").labels.template.setAll({
      fontSize: "11px"
    });

    // Уменьшаем шрифт для количественной оси
    yAxisRight.get("renderer").labels.template.setAll({
      fontSize: "11px"
    });

    yAxisLeft.get("renderer").labels.template.setAll({ fill: am5.color(0x660ec8) });
    yAxisRight.get("renderer").labels.template.setAll({ fill: am5.color(0xf965cf) });

    yAxisLeft.get("renderer").grid.template.setAll({ visible: false });


/*
        { label: "Клики",    key: "clicks",    axis: yAxisLeft,  color: "#5067de" },
        { label: "Сумма",    key: "sum_price", axis: yAxisRight, color: "#f96666" }
        { label: "Кор-ны",   key: "atbs",      axis: yAxisLeft, color: "#f96666" } 
        atbs
*/
    var groups = [
        { label: "Заказы",   key: "orders",    axis: yAxisLeft,  color: "#660ec8" },
        { label: "Кор-ны",   key: "atbs",      axis: yAxisLeft,  color: "#f96666" },
        { label: "Показы",   key: "views",     axis: yAxisRight, color: "#f965cf" },
    ];

    groups.forEach(function(group) {
        appTypes.forEach(function(type, index) {

            var iconName = appNames[type] || "help";

            var series = AppChart.series.push(am5xy.ColumnSeries.new(root, {
                name: appNames[type] +" "+ group.label,
                xAxis: xAxis,
                yAxis: group.axis,
                valueYField: group.key + "_" + type,
                valueXField: "date",
                clustered: true,
                stacked: (index !== 0), // Базовая серия + stacked для остальных
                stackId: group.key,
                
                // Твои обязательные параметры для агрегации:
                calculateAggregates: true, 
                valueYGroupedField: group.key + "_" + type,
                baseInterval: xAxis.get("baseInterval"),
                valueYGrouped: "sum" 
            }));

            series.data.processor = am5.DataProcessor.new(root, {
                dateFields: ["date"],
                dateFormat: "yyyy-MM-dd",
                numericFields: [group.key + "_" + type]
            });

            var color = am5.color(group.color);
            series.set("fill", am5.Color.lighten(color, index * 0.2));
            series.set("stroke", am5.Color.lighten(color, index * 0.2));

            series.columns.template.setAll({
                width: am5.percent(90),
                tooltipText: "{name}: [bold]{valueY}[/]", 
                strokeOpacity: 0,
                fillOpacity: 0.9
            });

            // Добавим эффект при наведении (столбик становится ярче)
            series.columns.template.states.create("hover", {
                fillOpacity: 1,
                strokeOpacity: 1,
                strokeWidth: 2
            });

            // Если это "Сумма", можно добавить форматирование валюты
            if (group.key === "sum_price") {
                series.columns.template.set("tooltipText", "{name}: [bold]{valueY.formatNumber('#,###.00')}[/] ₽");
            }
/*
            series.set("tooltip", am5.Tooltip.new(root, {
              labelText: "{name}: {valueY}" 
            }));
            series.columns.template.set("tooltipText", group.label + " (" + (type == 1 ? "Desktop" : type == 32 ? "Android" : "iOS") + "): [bold]{valueY}[/]");
*/

/*
            series.set("tooltip", am5.Tooltip.new(root, {
              pointerOrientation: "horizontal",
              labelText: "{name}: {valueY} ({valueYPercentTotal.formatNumber('#.#')}% )"
            }));
*/


    var tooltip = am5.Tooltip.new(root, {
        getFillFromSprite: true,
        autoTextColor: false
    });

    // Назначаем текст через label, а не через labelText тултипа

    tooltip.label.set("text", "{name}: [bold]{valueY}[/] ({percent}%)");

    tooltip.label.setAll({
        fontSize: "12px",
        fontWeight: "400",  
//        fill: am5.color(0xffffff) // Цвет текста (если нужно)
    });

    series.set("tooltip", tooltip);

// 2. Вешаем адаптер ПРЯМО НА LABEL тултипа
    tooltip.label.adapters.add("text", function(text, target) {
        var di = target.dataItem;
        if (di) {
            var value = di.get("valueYWorking") || di.get("valueY") || 0;
            var index = series.dataItems.indexOf(di);
            var totalSum = 0;
            
            // Берем ID текущего стека (например, "orders")
            var currentStackId = series.get("stackId");

            AppChart.series.each(function(s) {
                // СУММИРУЕМ ТОЛЬКО СЕРИИ ИЗ ЭТОГО ЖЕ СТОЛБИКА
                if (s.get("stackId") === currentStackId) {
                    var otherDi = s.dataItems[index];
                    if (otherDi) {
                        totalSum += (otherDi.get("valueYWorking") || otherDi.get("valueY") || 0);
                    }
                }
            });

            var percent = (totalSum > 0) ? (value / totalSum * 100).toFixed(1) : 0;
            var name = di.get("name") || series.get("name") || "";

            var formattedValue = root.numberFormatter.format(value, "#,###");
            return name + ": [bold]" + formattedValue + "[/] (" + percent + "%)";
        }
        return text;
    });


    series.bullets.push(function() {
        var label = am5.Label.new(root, {
            text: "{percent}%",
//            fill: am5.color(0xffffff), // Белый текст
            fill: color,
            centerX: am5.p50,
            centerY: am5.p50,
            populateText: true,
            fontSize: "11px",
            fontWeight: "bold",

                    paddingTop: 2, paddingBottom: 2, paddingLeft: 5, paddingRight: 5,
                    background: am5.RoundedRectangle.new(root, {
                        fill: am5.color(0xffffff),
                        stroke: color,
                        strokeWidth: 1.5,
                        cornerRadiusTL: 5, cornerRadiusTR: 5, cornerRadiusBL: 5, cornerRadiusBR: 5
                    })
        });

    // Тот же самый адаптер, что и для тултипа
    label.adapters.add("text", function(text, target) {
        var di = target.dataItem;
        if (di) {
            // 1. Получаем само число (значение колонки)
            var value = di.get("valueYWorking") || di.get("valueY") || 0;
            
            // 2. Считаем сумму для процентов (как делали раньше)
            var index = series.dataItems.indexOf(di);
            var totalSum = 0;
            var currentYAxis = series.get("yAxis");

            AppChart.series.each(function(s) {
                if (s.get("yAxis") === currentYAxis && s.get("stackId") === series.get("stackId")) {
                    var otherDi = s.dataItems[index];
                    if (otherDi) {
                        totalSum += (otherDi.get("valueYWorking") || otherDi.get("valueY") || 0);
                    }
                }
            });

            var percentRaw = (totalSum > 0) ? (value / totalSum * 100) : 0;

            // --- ФИЛЬТРЫ СКРЫТИЯ ---
            // Скрываем, если значение меньше 2 (физически не влезет текст)
            // ИЛИ если доля меньше 5% (визуальный шум)
            if (value < 2 || percentRaw < 5) {
                return ""; 
            }

            return percentRaw.toFixed(1) + "%";
        }
        return text;
    });

    return am5.Bullet.new(root, {
        locationY: 0.5, // Размещаем по центру сегмента колонки
        sprite: label
    });
});


            series.data.setAll(data);
            series.appear();
        });
    });

/*
    window.setStackedColumnInterval = function(unit) {
        if (unit === 'day') {
            xAxis.set("groupInterval", { timeUnit: "day", count: 1 });
        } else {
            xAxis.set("groupInterval", { timeUnit: unit, count: 1 });
        }

        // Принудительно обновляем серии, чтобы они увидели смену интервала
        AppChart.series.each(function(series) {
            series.markDirtyValues();
        });

        // Визуальное обновление кнопок
        const buttons = document.querySelectorAll('.btn-group .btn');
        buttons.forEach(btn => {
            btn.classList.remove('active');
            let attr = btn.getAttribute('onclick');
            if (attr && attr.includes("'" + unit + "'")) {
                btn.classList.add('active');
            }
        });
    };
*/

/**
 * Установка интервала для столбчатой диаграммы (Stacked Column)
 */
window.setStackedColumnInterval = function(unit, element) {
    if (typeof xAxis !== 'undefined') {
        xAxis.set("groupInterval", { timeUnit: unit, count: 1 });
    }

    if (typeof AppChart !== 'undefined' && AppChart.series) {
        AppChart.series.each(series => series.markDirtyValues());
    }
    const container = element 
        ? element.closest('.btn-group') 
        : document.getElementById('stacked_column_buttons');

    if (container) {
        const buttons = container.querySelectorAll('[data-unit]');
        buttons.forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-unit') === unit);
        });
    } else {
        console.warn("Контейнер #stacked_buttons не найден");
    }
};



    // Настройка курсора
    var cursor = AppChart.set("cursor", am5xy.XYCursor.new(root, {
        behavior: "panX", // zoomX
        wheelX: "zoomX",
        xAxis: xAxis
    }));

    // Чтобы тултип не прилипал к курсору, а был над столбиком
    cursor.lineY.set("visible", false); 
    cursor.lineX.set("visible", true);

/*
    var legend = chart.children.push(am5.Legend.new(root, { centerX: am5.p50, x: am5.p50 }));

    legend.labels.template.setAll({
        useHTML: true,
        useRawText: false,
        populateText: true
    });

    legend.data.setAll(chart.series.values);
*/

    var initialUnit = "day";
    if (data.length > 8) {
        initialUnit = "month";
    } else if (data.length > 14) {
        initialUnit = "week";
    }
    window.setStackedColumnInterval(initialUnit);

});

</script>