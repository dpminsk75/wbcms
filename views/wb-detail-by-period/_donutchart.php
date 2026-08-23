<?php
use yii\helpers\Json;
?>

<script>
am5.ready(function() {
    var rootDonut     = am5.Root.new("donut_div");

    rootDonut.setThemes([am5themes_Animated.new(rootDonut)]);

// --- ГРАФИК "БАРАНКА" ---
    var donutChart = rootDonut.container.children.push(am5percent.PieChart.new(rootDonut, {
        innerRadius: am5.percent(50),
        layout: rootDonut.verticalLabels
    }));

    donutChart.set("radius", am5.percent(65));

    var donutSeries = donutChart.series.push(am5percent.PieSeries.new(rootDonut, {
        name: "Регионы",
        valueField: "sales_count",
        categoryField: "region",
        alignLabels: true
    }));

    donutSeries.labels.template.setAll({
        text: "{category}: {value} ({valuePercentTotal.formatNumber('0.0')}% )",
        fontSize: 11,
        tooltipY: 0
    });

    donutSeries.labels.template.setAll({
      maxWidth: 120,
      oversizedBehavior: "wrap", 
      // oversizedBehavior: "truncate" // Или добавится многоточие
    });

    donutSeries.labels.template.setAll({
        interactive: false // Чтобы подписи не перехватывали клики
    });

    donutSeries.ticks.template.setAll({
        interactive: false // Чтобы линии-выноски не мешали
    });

    donutSeries.setAll({ // поднимаем чуток повыше бублик
        y: -50, 
//        centerY: am5.p0 
        verticalCenter: am5.p0 
    });

    donutSeries.set("maskContent", false);

    donutSeries.data.setAll(<?= $regionJson ?>);

});
</script>
