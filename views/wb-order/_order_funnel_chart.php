<?php
/**
 * Виджет воронки заказов: график AmCharts + прогресс-бар + цифровые карточки.
 * Вынесен отдельно чтобы не загромождать feed-aggregated.php
 *
 * @var yii\web\View $this
 * @var array $funnelStats [
 *   total_cnt, total_sum,
 *   bought_cnt, bought_sum, bought_pct,
 *   delivery_cnt, delivery_sum, delivery_pct,
 *   cancel_cnt, cancel_sum, cancel_pct,
 *   returns_cnt, returns_sum, returns_pct,
 *   buyout_pct
 * ]
 * @var array $chartData [['date'=>'Y-m-d','bought_cnt'=>int,'delivery_cnt'=>int,'cancel_cnt'=>int,'returns_cnt'=>int,...], ...]
 */

use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\View;
use app\assets\ChartAsset;

ChartAsset::register($this);
$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', ['depends' => [ChartAsset::class]]);
$fmtMoney = function ($v) {
    return number_format((float)$v, 0, ',', ' ') . ' ₽';
};
$fmtPct = function ($v) {
    return number_format((float)$v, 2, ',', ' ') . ' %';
};
$hasData = !empty($funnelStats) && ($funnelStats['total_cnt'] ?? 0) > 0;
?>

<style>
.order-funnel-chart-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:16px 18px; margin-bottom:16px; }
.order-funnel-progress { display:flex; height:25px; border-radius:7px; overflow:hidden; margin:10px 0 18px; background: transparent; }
.order-funnel-progress > div { height:100%; }
.order-funnel-progress > div:first-child { border-radius: 7px 0 0 7px; }
.order-funnel-progress > div:last-child { border-radius: 0 7px 7px 0; }
.order-funnel-progress > div:only-child { border-radius: 7px; }
.order-funnel-legend { display:flex; flex-wrap:wrap; gap:18px 24px; font-size:13px; color:#555; margin-bottom:12px; }
.order-funnel-legend span.dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; vertical-align:middle; }
.order-funnel-cards .of-label .dot { display:inline-block; width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.order-funnel-cards { display:flex; flex-wrap:wrap; gap:12px; }
.order-funnel-cards .of-card { flex:1 1 160px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px; min-width:140px; }
.order-funnel-cards .of-card .of-label { font-size:12px; color:#8a8f98; margin-bottom:6px; display:flex; align-items:center; gap:6px; }
.order-funnel-cards .of-card .of-value { font-size:20px; font-weight:700; color:#1f2937; white-space:nowrap; }
.order-funnel-cards .of-card .of-sub { font-size:12px; color:#6b7280; margin-top:2px; }
.order-funnel-cards .of-card.of-buyout { background:#f9fafb; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; }
.order-funnel-cards .of-card.of-buyout .of-value { font-size:26px; font-weight:800; }
@media (max-width: 767px) {
    .order-funnel-cards .of-card { flex:1 1 45%; }
    #orderFunnelChartDiv { height:220px !important; }
}
</style>

<div class="order-funnel-chart-card">
    <div style="font-size:13px; color:#6b7280; margin-bottom:8px;">Распределение статуса заказов</div>
    <div id="orderFunnelChartDiv" style="width:100%; height:300px;"></div>
</div>

<?php if ($hasData): ?>
<div class="order-funnel-progress" title="Доли по количеству заказов">
    <?php if ($funnelStats['bought_pct'] > 0): ?>
        <div style="width: <?= $funnelStats['bought_pct'] ?>%; background:#5B9CD6;" title="Выкупленные <?= $fmtPct($funnelStats['bought_pct']) ?>"></div>
    <?php endif; ?>
    <?php if ($funnelStats['delivery_pct'] > 0): ?>
        <div style="width: <?= $funnelStats['delivery_pct'] ?>%; background:#9AA0A8;" title="В доставке <?= $fmtPct($funnelStats['delivery_pct']) ?>"></div>
    <?php endif; ?>
    <?php if ($funnelStats['cancel_pct'] > 0): ?>
        <div style="width: <?= $funnelStats['cancel_pct'] ?>%; background:#F5A623;" title="Отменённые <?= $fmtPct($funnelStats['cancel_pct']) ?>"></div>
    <?php endif; ?>
    <?php if ($funnelStats['returns_pct'] > 0): ?>
        <div style="width: <?= max(0.6, $funnelStats['returns_pct']) ?>%; background:#E74C3C;" title="Возвраты <?= $fmtPct($funnelStats['returns_pct']) ?>"></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="order-funnel-cards">
    <div class="of-card">
        <div class="of-label">Заказы <span title="Всего заказов за период" style="cursor:help; color:#aaa;">?</span></div>
        <div class="of-value"><?= $fmtMoney($funnelStats['total_sum'] ?? 0) ?></div>
        <div class="of-sub"><?= number_format((int)($funnelStats['total_cnt'] ?? 0), 0, ',', ' ') ?> шт.</div>
    </div>
    <div class="of-card">
        <div class="of-label"><span class="dot" style="background:#5B9CD6;"></span> Выкупленные <span title="s.saleID NOT LIKE 'R%' и не отменён" style="cursor:help; color:#aaa;">?</span></div>
        <div class="of-value"><?= $fmtMoney($funnelStats['bought_sum'] ?? 0) ?></div>
        <div class="of-sub"><?= $fmtPct($funnelStats['bought_pct'] ?? 0) ?></div>
        <div class="of-sub"><?= number_format((int)($funnelStats['bought_cnt'] ?? 0), 0, ',', ' ') ?> шт.</div>
    </div>
    <div class="of-card">
        <div class="of-label"><span class="dot" style="background:#9AA0A8;"></span> В доставке <span title="is_cancel=0 и srid без продажи" style="cursor:help; color:#aaa;">?</span></div>
        <div class="of-value"><?= $fmtMoney($funnelStats['delivery_sum'] ?? 0) ?></div>
        <div class="of-sub"><?= $fmtPct($funnelStats['delivery_pct'] ?? 0) ?></div>
        <div class="of-sub"><?= number_format((int)($funnelStats['delivery_cnt'] ?? 0), 0, ',', ' ') ?> шт.</div>
    </div>
    <div class="of-card">
        <div class="of-label"><span class="dot" style="background:#F5A623;"></span> Отменённые <span title="is_cancel=1" style="cursor:help; color:#aaa;">?</span></div>
        <div class="of-value"><?= $fmtMoney($funnelStats['cancel_sum'] ?? 0) ?></div>
        <div class="of-sub"><?= $fmtPct($funnelStats['cancel_pct'] ?? 0) ?></div>
        <div class="of-sub"><?= number_format((int)($funnelStats['cancel_cnt'] ?? 0), 0, ',', ' ') ?> шт.</div>
    </div>
    <div class="of-card">
        <div class="of-label"><span class="dot" style="background:#E74C3C;"></span> Возвраты <span title="s.saleID LIKE 'R%'" style="cursor:help; color:#aaa;">?</span></div>
        <div class="of-value"><?= $fmtMoney($funnelStats['returns_sum'] ?? 0) ?></div>
        <div class="of-sub"><?= $fmtPct($funnelStats['returns_pct'] ?? 0) ?></div>
        <div class="of-sub"><?= number_format((int)($funnelStats['returns_cnt'] ?? 0), 0, ',', ' ') ?> шт.</div>
    </div>
    <div class="of-card of-buyout">
        <div class="of-label" style="justify-content:center;">Процент выкупа</div>
        <div class="of-value"><?= $fmtPct($funnelStats['buyout_pct'] ?? 0) ?></div>
    </div>
</div>

<?php
$chartJson = Json::encode($chartData);
$js = <<<JS
am5.ready(function() {
    var el = document.getElementById("orderFunnelChartDiv");
    if (!el) return;
    var root = am5.Root.new("orderFunnelChartDiv");
    if (typeof am5locales_ru_RU !== "undefined") root.locale = am5locales_ru_RU;
    root.numberFormatter.set("numberFormat", "#,###");
    root.setThemes([am5themes_Animated.new(root)]);

    var chart = root.container.children.push(am5xy.XYChart.new(root, {
        panX: false, panY: false, wheelX: "none", wheelY: "none", layout: root.verticalLayout
    }));

    var xRenderer = am5xy.AxisRendererX.new(root, { minGridDistance: 40 });
    xRenderer.labels.template.setAll({ fontSize: 11, rotation: -0, centerY: am5.p50 });
    xRenderer.grid.template.setAll({ visible: false });

    var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
        baseInterval: { timeUnit: "day", count: 1 },
        renderer: xRenderer,
        tooltip: am5.Tooltip.new(root, {})
    }));
    xAxis.get("dateFormats")["day"] = "d MMM";
    xAxis.get("periodChangeDateFormats")["day"] = "d MMM";

    var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        renderer: am5xy.AxisRendererY.new(root, {}),
        extraMax: 0.05
    }));
    yAxis.get("renderer").labels.template.setAll({ fontSize: 11 });

    var raw = $chartJson;
    var chartData = raw.map(function(r){
        return {
            date: new Date(r.date).getTime(),
            bought: r.bought_cnt,
            delivery: r.delivery_cnt,
            cancel: r.cancel_cnt,
            returns: r.returns_cnt
        };
    });

    function makeSeries(name, field, color) {
        var s = chart.series.push(am5xy.ColumnSeries.new(root, {
            name: name,
            xAxis: xAxis, yAxis: yAxis,
            valueYField: field, valueXField: "date",
            stacked: true,
            tooltip: am5.Tooltip.new(root, { labelText: name + ": {valueY}" })
        }));
        s.columns.template.setAll({ fill: am5.Color.fromString(color), strokeOpacity: 0, width: am5.percent(85) });
        s.data.setAll(chartData);
        return s;
    }

    makeSeries("Выкупленные", "bought", "#5B9CD6");
    makeSeries("В доставке", "delivery", "#9AA0A8");
    makeSeries("Отменённые", "cancel", "#F5A623");
    makeSeries("Возвраты", "returns", "#E74C3C");

    chart.set("cursor", am5xy.XYCursor.new(root, { behavior: "none" }));

    var legend = chart.children.push(am5.Legend.new(root, {
        centerX: am5.p50, x: am5.p50, layout: am5.GridLayout.new(root, { maxColumns: 4, fixedWidthGrid: true })
    }));
    legend.labels.template.setAll({ fontSize: 11 });
    legend.markers.template.setAll({ width: 10, height: 10 });
    legend.data.setAll(chart.series.values);

    chart.appear(800, 100);
});
JS;
$this->registerJs($js, View::POS_END);
?>
