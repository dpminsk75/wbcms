<?php
use yii\helpers\Json;

/* @var $chart45Data array */
/* @var $kpi45Data array */
?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 bg-light text-dark h-100 p-3 text-center">
            <div class=" small font-weight-bold">Выручка (30 дн)</div>
            <div class="h3 font-weight-bold mt-2 text-primary"><?= number_format($kpi45Data['total_sales_rub'], 0, '.', ' ') ?> ₽</div>
            <div class="row justify-content-left g-2">
                <div class="col-auto small mt-1">Возвраты: <?= number_format($kpi45Data['total_return_rub'], 0, '.', ' ') ?> ₽</div>
                <div class="col-auto small mt-1">Заказы: <?= number_format($kpi45Data['total_orders_cnt'], 0, '.', ' ') ?> шт</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 bg-light text-dark h-100 p-3 text-center">
            <div class=" small font-weight-bold">Все расходы (30 дн)</div>
            <div class="h3 font-weight-bold mt-2 "><?= number_format($kpi45Data['total_expenses'], 0, '.', ' ') ?> ₽</div>
            <div class="row justify-content-left g-2">
                <div class="col-auto small   mt-1">Реклама: <?= number_format($kpi45Data['total_adv'], 0, '.', ' ')?> ₽</div>
                <div class="col-auto small   mt-1">Кешбэк: <?= number_format($kpi45Data['total_cashback'], 0, '.', ' ')?> ₽</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 h-100 p-3 text-center <?= $kpi45Data['total_profit_rub'] < 0 ? 'bg-danger text-white' : 'bg-light text-dark ' ?>">
            <div class="small font-weight-bold">Итого к оплате (30 дн)</div>
            <div class="h3 font-weight-bold mt-2 text-info"><?= number_format($kpi45Data['total_profit_rub'], 0, '.', ' ') ?> ₽</div>
            <div class="row justify-content-left g-2">
                <div class="col-auto small text-danger mt-1">НДС: <?= number_format($kpi45Data['total_nds'], 0, '.', ' ')?> ₽</div>
                <div class="col-auto small text-danger mt-1">Себ-сть: <?= number_format($kpi45Data['total_cost'], 0, '.', ' ')?> ₽</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card shadow-sm border-0 bg-light text-dark h-100 p-3 text-center">
            <div class=" small font-weight-bold">Маржа (30 дн)</div>
            <div class="h3 font-weight-bold mt-2 text-success"><?= number_format($kpi45Data['clean_margin'], 0, '.', ' ') ?> ₽</div>
        </div>
    </div>


</div>

<div class="card shadow-sm border-0 mb-4">
<?php /*
    <div class="card-header bg-white font-weight-bold"><i class="fas fa-chart-bar text-success"></i> Динамика доходов за последние 45 дней</div>
*/ ?>
    <div class="card-body">
        <div id="chartdiv45" style="width: 100%; height: 350px;"></div>
    </div>
</div>

<style>
@media (min-width: 768px) {
    .col-md-2-4 { float: left; width: 20%; }
}
</style>

<script>
am5.ready(function() {
    var root = am5.Root.new("chartdiv45");

    // Используем встроенную локализацию, которая у вас подключена
    root.locale = am5locales_ru_RU;
    root.numberFormatter.set("numberFormat", "#,###");

    root.setThemes([am5themes_Animated.new(root)]);

    // Вертикальный контейнер, чтобы легенда упала строго под график
    root.container.set("layout", root.verticalLayout);

    var chart = root.container.children.push(am5xy.XYChart.new(root, {
        panX: true,
        panY: false,
        wheelX: "none",
        wheelY: "none",
        pinchZoomX: true,
        paddingBottom: 10
    }));

    var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
        maxDeviation: 0.1,
        baseInterval: { timeUnit: "day", count: 1 },
        renderer: am5xy.AxisRendererX.new(root, { minGridDistance: 50 }),
//        tooltip: am5.Tooltip.new(root, {})
        tooltip: am5.Tooltip.new(root, {
            layer: 10 
        })
/*
        tooltip: am5.Tooltip.new(root, {
        dy: 25,
        animationDuration: 0  
    })
*/
    }));

    // Форматируем вывод дней на оси Х через локаль
    xAxis.get("dateFormats")["day"] = "d MMM";
    xAxis.get("periodChangeDateFormats")["day"] = "d MMM";

    var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        stacked: true, // Накопительный режим (одна серия на другую)
        renderer: am5xy.AxisRendererY.new(root, {})
    }));

    xAxis.get("renderer").labels.template.setAll({
      rotation: -45,       // Угол наклона в градусах
      centerY: am5.p50,    // Выравнивание по центру по вертикали
      centerX: am5.p100    // Привязка правого края метки к делению
    });

    // Уменьшаем шрифт для горизонтальной оси (X)
    xAxis.get("renderer").labels.template.setAll({
      fontSize: "12px" // Укажите нужный размер (например, 10px или 11px)
    });

    // Уменьшаем шрифт для вертикальной оси (Y)
    yAxis.get("renderer").labels.template.setAll({
      fontSize: "12px"
    });


/*
ярко-желтый цвет (12-0643 TCX): #FFEB60
цвет лютика (12-0752 TCX): #F3E05A
одуванчиковый цвет (13-0758 TCX): #FFD000
ярко-желтый цвет (13-0858 TCX): #FCD116
кибер желтый цвет (14-0760 TCX): #FFD300
цвет желтого спектра (14-0957 TCX): #F9A602
цвет золотого сплава (15-1062 TCX): #FF9E1B


Rectory Red (Farrow & Ball): #99434C (или #9F404B)
Currant Red (Benjamin Moore): #A03D41
Grenedier Red (Pratt & Lambert): #9F212B
Heartthrob (Sherwin-Williams): #A82E33
Cut Ruby (Valspar): #8A181F
Red Alert (Kelly-Moore): #A24151
Candy Apple Red (Behr): #A9202A
Red Licorice (Pittsburgh Paints): #A83E4C
*/
/*
    var seriesAmount = chart.series.push(am5xy.ColumnSeries.new(root, {
        name: "Выручка",
        stacked: true,
        xAxis: xAxis,
        yAxis: yAxis,
        valueYField: "amount",
        valueXField: "date"
    }));

    seriesAmount.columns.template.setAll({
        fill: am5.Color.fromString("#9CA3AD"), 
        strokeOpacity: 0,
        width: am5.percent(80)
    });

    var seriesProfit = chart.series.push(am5xy.ColumnSeries.new(root, {
        name: "К оплате",
        stacked: true,
        xAxis: xAxis,
        yAxis: yAxis,
        valueYField: "net_profit",
        valueXField: "date"
    }));

    seriesProfit.columns.template.setAll({
        fill: am5.Color.fromString("#45CD9B"), 
        strokeOpacity: 0,
        width: am5.percent(80)
    });
*/

    var seriesExpenses = chart.series.push(am5xy.ColumnSeries.new(root, {
        name: "Расходы",
        stacked: true,
        xAxis: xAxis,
        yAxis: yAxis,
        valueYField: "total_expenses",
        valueXField: "date"
    }));

    seriesExpenses.columns.template.setAll({
        fill: am5.Color.fromString("#b2c0d2"), // #9CA3AD
        strokeOpacity: 0,
        width: am5.percent(80)
    });

    var seriesVAT = chart.series.push(am5xy.ColumnSeries.new(root, {
        name: "НДС",
        stacked: true,
        xAxis: xAxis,
        yAxis: yAxis,
        valueYField: "total_nds",
        valueXField: "date"
    }));

    seriesVAT.columns.template.setAll({
        fill: am5.Color.fromString("#dcdcdc"), //#A83E4C
        strokeOpacity: 0,
        width: am5.percent(80)
    });


    var seriesCost = chart.series.push(am5xy.ColumnSeries.new(root, {
        name: "Себестоимость",
        stacked: true,
        xAxis: xAxis,
        yAxis: yAxis,
        valueYField: "total_cost",
        valueXField: "date"
    }));

    seriesCost.columns.template.setAll({    
        fill: am5.Color.fromString("#FF9E1B"), //#45CD9B
        strokeOpacity: 0,
        width: am5.percent(80)
    });

    var seriesTax = chart.series.push(am5xy.ColumnSeries.new(root, {
        name: "Налог на прибыль",
        stacked: true,
        xAxis: xAxis,
        yAxis: yAxis,
        valueYField: "tax_amount",
        valueXField: "date"
    }));

    seriesTax.columns.template.setAll({
        fill: am5.Color.fromString("#ff6b6b"), //#A03D41
        strokeOpacity: 0,
        width: am5.percent(80)
    });

    var seriesMargin = chart.series.push(am5xy.ColumnSeries.new(root, {
        name: "Маржа",
        stacked: true,
        xAxis: xAxis,
        yAxis: yAxis,
        valueYField: "clean_margin",
        valueXField: "date"
    }));

    seriesMargin.columns.template.setAll({
        fill: am5.Color.fromString("#2ec4b6"), //#FF9E1B
        strokeOpacity: 0,
        width: am5.percent(80)
    });

    // Чистые данные напрямую из PHP без пересчетов 
    var rawData = <?= \yii\helpers\Json::encode($chart45Data) ?>;
    var processedData = [];
    
    rawData.forEach(function(item) {
        processedData.push({
            date: new Date(item.date).getTime(),
            amount: Math.round(parseFloat(item.amount)) || 0,
            net_profit: Math.round(parseFloat(item.net_profit)) || 0,
            total_expenses: Math.round(parseFloat(item.total_expenses)) || 0,
            total_cost: Math.round(parseFloat(item.total_cost)) || 0,
            total_nds: Math.round(parseFloat(item.total_nds)) || 0,
            tax_amount: Math.round(parseFloat(item.tax_amount)) || 0,

//            tax_amount: Math.round(parseFloat( (item.net_profit - item.total_nds - item.total_cost) )) || 0,
            clean_margin: Math.round(parseFloat(item.clean_margin)) || 0
        });
    });

/*
    seriesAmount.data.setAll(processedData);
    seriesProfit.data.setAll(processedData);
        
        seriesAmount.set("tooltip", am5.Tooltip.new(root, {
          labelText: "Выручка: {valueY} ₽" // amCharts сам подставит значение из поля valueYField
        }));

        
        seriesProfit.set("tooltip", am5.Tooltip.new(root, {
          labelText: "К оплате: {valueY} ₽"
        }));
*/

    seriesExpenses.data.setAll(processedData);
    seriesCost.data.setAll(processedData);
    seriesVAT.data.setAll(processedData);    
    seriesMargin.data.setAll(processedData); 
    seriesTax.data.setAll(processedData); 

    // Добавляем курсор
    var cursor = chart.set("cursor", am5xy.XYCursor.new(root, { 
          behavior: "none",     // Отключает выделение/зум при перетаскивании мышью
          wheelX: "none",       // ОТКЛЮЧАЕТ зум по горизонтали колёсиком мыши
          wheelY: "none"        // ОТКЛЮЧАЕТ зум по вертикали колёсиком мыш
    }));


    cursor.lineY.set("visible", false);


seriesExpenses.set("tooltip", am5.Tooltip.new(root, {
    getHTML: true
}));

seriesExpenses.get("tooltip").label.adapters.add("text", function(text, target) {
    var dataItem = target.dataItem;
    if (dataItem && dataItem.dataContext) {
        var d = dataItem.dataContext;
        // Берём округленные значения и форматируем их через amCharts
        var fAmount = root.numberFormatter.format(d.amount);
        var fExpenses = root.numberFormatter.format(d.total_expenses);
        var fProfit = root.numberFormatter.format(d.net_profit);
        
        return "💰 [b]Выручка:[/b] " + fAmount + " ₽\n" +
               "📈 [b]Расходы: [/b]" + fExpenses + " ₽\n" +
               "💵 [b]К оплате:[/b] " + fProfit + " ₽";
    }
    return text;
});
/*
seriesExpenses.set("tooltip", am5.Tooltip.new(root, {
    getHTML: true,
    pointerOrientation: "vertical", 
    dy: -10,
    labelText: "Расходы" // Любой текст-заглушка, адаптер всё равно его полностью перепишет
}));

// 2. Наш рабочий адаптер для динамической подстановки данных
seriesExpenses.get("tooltip").label.adapters.add("text", function(text, target) {
    var dataItem = target.dataItem;
    if (dataItem && dataItem.dataContext) {
        var d = dataItem.dataContext;
        
        var fAmount = root.numberFormatter.format(d.amount);
        var fExpenses = root.numberFormatter.format(d.total_expenses);
        var fProfit = root.numberFormatter.format(d.net_profit);
        
        return "💰 [b]Выручка:[/b] " + fAmount + " ₽\n" +
               "📈 [b]Расходы: [/b]" + fExpenses + " ₽\n" +
               "💵 [b]К оплате:[/b] " + fProfit + " ₽";
    }
    return text;
});
*/
/*
        seriesExpenses.set("tooltip", am5.Tooltip.new(root, {
          labelText: "Расходы: {valueY.formatNumber()} ₽" 
        }));
*/
        seriesCost.set("tooltip", am5.Tooltip.new(root, {
          labelText: "Себестоимость: {valueY.formatNumber()} ₽"
        }));

        seriesVAT.set("tooltip", am5.Tooltip.new(root, {
          labelText: "Сумма НДС: {valueY.formatNumber()} ₽"
        }));

        seriesMargin.set("tooltip", am5.Tooltip.new(root, {
          labelText: "Маржа: {valueY.formatNumber()} ₽"
        }));

        seriesTax.set("tooltip", am5.Tooltip.new(root, {
          labelText: "Налог на прибыль: {valueY.formatNumber()} ₽"
        }));


    // Адаптируем данные под курсор (совмещенный tooltip)
    cursor.events.on("cursormoved", function() {
        var dataItem = xAxis.get("tooltip").dataItem;
        if (dataItem) {
            // amCharts автоматически подтянет правильные значения для текущей даты
        }
    });

    var legend = root.container.children.push(am5.Legend.new(root, {
        centerX: am5.p50,
        x: am5.p50,
        marginTop: -5,
        layout: am5.GridLayout.new(root, { maxColumns: 5, fixedWidthGrid: true })
    }));

// Настройка компактности: ужимаем отступы самих элементов легенды
legend.labels.template.setAll({
    paddingLeft: 4,
    paddingRight: 12, // Расстояние до следующего элемента
    fontSize: "13px"   // Можно слегка уменьшить шрифт, если элементов очень много
});

legend.markers.template.setAll({
    width: 12,
    height: 12,
    paddingRight: 4
});

legend.itemContainers.template.setAll({
    paddingTop: 0,
    paddingBottom: 0,
    marginLeft: 0,
    marginRight: 0
});

    legend.data.setAll(chart.series.values);

    chart.appear(1000, 100);
});
</script>