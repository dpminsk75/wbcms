<?php
use yii\helpers\Json;
/* @var $todayStats array — результат SiteController::buildPeriodStats() для периода 'today',
   структура: ['period'=>'today','orders'=>[...],'sales'=>[...]] */

$tabsConfig = [
    'orders' => 'Заказы',
    'sales'  => 'Продажи',
];

$periodLabels = [
    'today'         => 'За сегодня',
    'yesterday'     => 'За вчера',
    'week_to_date'  => 'С начала недели',
    'last_week'     => 'За прошлую неделю',
    'month_to_date' => 'За месяц',
    'last_month'    => 'За прошлый месяц',
];
?>

<div class="card shadow-sm border-0 mb-4 today-stats-widget">
    <div class="card-body p-3">

        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap tsw-header">
            <div class="today-stats-tabs">
                <?php foreach ($tabsConfig as $key => $label): ?>
                    <button type="button"
                            class="tsw-tab<?= $key === 'orders' ? ' active' : '' ?>"
                            data-target="<?= $key ?>">
                        <?= $label ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="d-flex align-items-center" style="gap: 14px;">
                <div class="text-muted small" id="tswUpdatedAt">Обновлено в <?= date('H:i') ?></div>

                <div class="tsw-period-dropdown">
                    <button type="button" class="tsw-period-btn" id="tswPeriodBtn">
                        <span id="tswPeriodLabel"><?= $periodLabels['today'] ?></span>
                        <span class="tsw-period-caret">&#9662;</span>
                    </button>
                    <div class="tsw-period-menu" id="tswPeriodMenu">
                        <?php foreach ($periodLabels as $key => $label): ?>
                            <button type="button" class="tsw-period-item<?= $key === 'today' ? ' active' : '' ?>" data-period="<?= $key ?>"><?= $label ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach ($tabsConfig as $key => $label): ?>
        <div class="tsw-pane<?= $key === 'orders' ? '' : ' d-none' ?>" data-pane="<?= $key ?>">
            <div class="row g-4 mb-2 tsw-kpi-row"></div>
            <div class="text-muted small mb-1 tsw-axis-caption" style="display:none;"></div>
            <div class="tsw-chart-wrap" style="position: relative;">
                <div id="tsw-chart-<?= $key ?>" style="width: 100%; height: 260px;"></div>
                <div class="tsw-loader" data-loader-for="<?= $key ?>">
                    <div class="tsw-spinner"></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</div>

<style>
.today-stats-tabs .tsw-tab {
    border: none;
    background: transparent;
    font-size: 14px;
    font-weight: 600;
    color: #6E6A80;
    padding: 6px 4px;
    margin-right: 22px;
    border-bottom: 2px solid transparent;
    cursor: pointer;
}
.today-stats-tabs .tsw-tab.active {
    color: #1D1B2A;
    border-bottom-color: #4A3A8C;
}

.tsw-period-dropdown {
    position: relative;
}
.tsw-period-btn {
    border: 1px solid #E2E0EC;
    background: #fff;
    color: #1D1B2A;
    font-size: 13px;
    font-weight: 600;
    padding: 7px 12px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}
.tsw-period-btn:hover {
    background: #F7F6FB;
}
.tsw-period-caret {
    font-size: 10px;
    color: #8A859E;
}
.tsw-period-menu {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    background: #fff;
    border: 1px solid #E2E0EC;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,.10), 0 4px 10px -2px rgba(0,0,0,.05);
    padding: 6px;
    min-width: 200px;
    z-index: 20;
}
.tsw-period-menu.open {
    display: block;
}
.tsw-period-item {
    display: block;
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
    padding: 8px 10px;
    font-size: 13.5px;
    color: #1D1B2A;
    border-radius: 7px;
    cursor: pointer;
}
.tsw-period-item:hover {
    background: #F2EFFB;
}
.tsw-period-item.active {
    color: #4A3A8C;
    font-weight: 700;
    background: #F2EFFB;
}

.tsw-loader {
    display: none;
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,.7);
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: 8px;
}
.tsw-loader.active {
    display: flex;
}
.tsw-spinner {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 3px solid #E2E0EC;
    border-top-color: #4A3A8C;
    animation: tsw-spin .7s linear infinite;
}
@keyframes tsw-spin {
    to { transform: rotate(360deg); }
}
</style>

<script>
(function () {
    var PERIOD_LABELS = <?= Json::encode($periodLabels) ?>;
//    var COLORS = ['#4A3A8C', '#E85E8C', '#2ec4b6'];
    var COLORS = ['#4B61EC', '#F042B7', '#3AC1C7'];
    var DASHED = [false, false, true];

    var cache = {};                       // period -> {period, orders, sales}
    var amRoots = { orders: null, sales: null }; // текущие корни amCharts, для dispose перед пересборкой
    var activePeriod = 'today';

    cache.today = {
        period: 'today',
        orders: <?= Json::encode($todayStats['orders']) ?>,
        sales:  <?= Json::encode($todayStats['sales']) ?>
    };

    // ---------- вкладки Заказы/Продажи ----------
    document.querySelectorAll('.today-stats-tabs .tsw-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.today-stats-tabs .tsw-tab').forEach(function (b) {
                b.classList.remove('active');
            });
            this.classList.add('active');

            var target = this.dataset.target;
            document.querySelectorAll('.tsw-pane').forEach(function (p) {
                p.classList.toggle('d-none', p.dataset.pane !== target);
            });

            window.dispatchEvent(new Event('resize'));
        });
    });

    // ---------- дропдаун периодов ----------
    var periodBtn = document.getElementById('tswPeriodBtn');
    var periodMenu = document.getElementById('tswPeriodMenu');
    var periodLabelEl = document.getElementById('tswPeriodLabel');

    periodBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        periodMenu.classList.toggle('open');
    });
    document.addEventListener('click', function () {
        periodMenu.classList.remove('open');
    });

    periodMenu.querySelectorAll('.tsw-period-item').forEach(function (item) {
        item.addEventListener('click', function () {
            var period = this.dataset.period;
            periodMenu.classList.remove('open');
            if (period === activePeriod) return;

            periodMenu.querySelectorAll('.tsw-period-item').forEach(function (i) {
                i.classList.toggle('active', i.dataset.period === period);
            });
            periodLabelEl.textContent = PERIOD_LABELS[period];
            activePeriod = period;

            loadPeriod(period);
        });
    });

    // ---------- загрузка периода (с кэшем и лоадером) ----------
    function setLoading(isLoading) {
        document.querySelectorAll('.tsw-loader').forEach(function (el) {
            el.classList.toggle('active', isLoading);
        });
    }

    function loadPeriod(period) {
        if (cache[period]) {
            renderPayload(cache[period]);
            return;
        }

        setLoading(true);
        fetch('/site/today-stats-data?period=' + encodeURIComponent(period))
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                cache[period] = data;
                renderPayload(data);
            })
            .catch(function (e) {
                console.error('[today-stats-widget] ошибка загрузки периода "' + period + '":', e);
            })
            .finally(function () {
                setLoading(false);
                document.getElementById('tswUpdatedAt').textContent =
                    'Обновлено в ' + new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
            });
    }

    // ---------- форматирование чисел (как PHP number_format(v, d, ',', ' ')) ----------
    function fmtNum(v, decimals) {
        decimals = decimals || 0;
        var n = Number(v || 0);
        var parts = n.toFixed(decimals).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return parts.join(',');
    }

    function deltaLineHtml(currentVal, otherVal, decimals, suffix, label, isPp) {
        var diff = (currentVal || 0) - (otherVal || 0);
        var up = diff >= 0;
        var color = up ? '#1E9E7C' : '#E0525C';
        var arrow = up ? '&#8593;' : '&#8595;';
        var text = fmtNum(Math.abs(diff), decimals) + (isPp ? ' п.п.' : suffix);
        return '<div class="small mt-1"><span style="color:' + color + ';">' + arrow + ' ' + text + '</span> — ' + label + '</div>';
    }

    // ---------- KPI-блок (Количество / Сумма / СПП) ----------
    function renderKpi(pane, seriesMeta, totals) {
        var mainKey = seriesMeta[0].key;
        var main = totals[mainKey];
        var others = seriesMeta.slice(1);

        var cntHtml = '<div class="text-muted small">Количество</div><div class="h3 font-weight-bold mt-1">' + fmtNum(main.cnt) + '</div>';
        others.forEach(function (o) {
            cntHtml += deltaLineHtml(main.cnt, totals[o.key].cnt, 0, '', o.name, false);
        });

        var sumHtml = '<div class="text-muted small">Сумма</div><div class="h3 font-weight-bold mt-1">' + fmtNum(main.sum, 2) + ' ₽</div>';
        others.forEach(function (o) {
            sumHtml += deltaLineHtml(main.sum, totals[o.key].sum, 2, ' ₽', o.name, false);
        });

        var sppHtml = '<div class="text-muted small">СПП (среднее)</div><div class="h3 font-weight-bold mt-1">' + fmtNum(main.spp, 1) + '%</div>';
        others.forEach(function (o) {
            sppHtml += deltaLineHtml(main.spp, totals[o.key].spp, 1, '', o.name, true);
        });

        // Блок абсолютных цифр по "соседнему" периоду сравнения (первый в
        // others — для "Вчера" это "Позавчера", для "С начала недели" —
        // "Прошлая неделя" и т.п.). Остальные строки показывают только
        // дельту, а тут — реальные цифры того периода целиком.
        var prevHtml = '';
        if (others.length) {
            var prev = others[0];
            var prevTotals = totals[prev.key];
            prevHtml =
                '<div class="text-muted small">' + prev.name + '</div>' +
                '<div class="small mt-1 text-end"><b>' + fmtNum(prevTotals.cnt) + '</b> - заказов</div>' +
                '<div class="small mt-1 text-end"><b>' + fmtNum(prevTotals.sum, 2) + '</b> ₽ - на сумму</div>' +
                '<div class="small mt-1 text-end"><b>' + fmtNum(prevTotals.spp, 1) + '</b> % - СПП</div>';
        }
        var kpiRow = pane.querySelector('.tsw-kpi-row');
        kpiRow.innerHTML =
            '<div class="col-6 col-md-3">' + cntHtml + '</div>' +
            '<div class="col-6 col-md-3">' + sumHtml + '</div>' +
            '<div class="col-6 col-md-3">' + sppHtml + '</div>' +
            '<div class="col-6 col-md-3 border-primary border rounded-2">' + prevHtml + '</div>';
        }

    // ---------- построение графика (2 или 3 линии, часы или дни) ----------
    function buildChart(elementId, granularity, categories, seriesMeta, seriesData) {
        var root = am5.Root.new(elementId);
        root.locale = am5locales_ru_RU;
        root.numberFormatter.set("numberFormat", "#,###.##");
        root.setThemes([am5themes_Animated.new(root)]);
        root.container.set("layout", root.verticalLayout);

        var chart = root.container.children.push(am5xy.XYChart.new(root, {
            panX: false,
            panY: false,
            wheelX: "none",
            wheelY: "none",
            paddingLeft: 0
        }));

        var xAxis = chart.xAxes.push(am5xy.CategoryAxis.new(root, {
            categoryField: "category",
            renderer: am5xy.AxisRendererX.new(root, { minGridDistance: 20 })
        }));
        xAxis.data.setAll(categories.map(function (c) { return { category: c }; }));

        if (categories.length > 12) {
            xAxis.get("renderer").labels.template.setAll({
                rotation: -45,
                centerY: am5.p50,
                centerX: am5.p100
            });
        }

        var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
            min: 0,        // ось всегда начинается с нуля, а не с авто-минимума по данным
            extraMax: 0.1, // 10% воздуха сверху, чтобы пик графика не упирался в границу
            renderer: am5xy.AxisRendererY.new(root, {})
        }));

        // --- СПП: правая ось + колонки (только для дневных периодов —
        // недели/месяцы; на часовых today/yesterday не добавляем, чтобы не
        // перегружать и без того плотный график) ---
        var sppAxis = null;
        if (granularity === 'day') {
            sppAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
                min: 0,
                extraMax: 0.3, // колонки СПП держим невысокими, чтобы не спорили с линиями
                renderer: am5xy.AxisRendererY.new(root, { opposite: true })
            }));
            sppAxis.get("renderer").labels.template.adapters.add("text", function (text) {
                return text + '%';
            });
            sppAxis.children.unshift(am5.Label.new(root, {
                text: "СПП, %",
                rotation: -90,
                y: am5.p50,
                centerX: am5.p50,
                fontSize: 11,
                fill: am5.color(0x8A859E)
            }));

            seriesMeta.forEach(function (meta, idx) {
                var color = COLORS[idx] || '#999999';
                var sppColumns = chart.series.push(am5xy.ColumnSeries.new(root, {
                    name: meta.name + ' — СПП',
                    xAxis: xAxis,
                    yAxis: sppAxis,
                    valueYField: "spp",
                    categoryXField: "category",
                    clustered: true,
                    fill: am5.color(color),
                    stroke: am5.color(color)
                }));
                sppColumns.columns.template.setAll({
                    width: am5.percent(60),
                    fillOpacity: 0.28,
                    strokeOpacity: 0,
                    cornerRadiusTL: 3,
                    cornerRadiusTR: 3
                });
                sppColumns.data.setAll(seriesData[meta.key].map(function (d) {
                    return { category: d.category, spp: d.spp };
                }));
            });
        }

        var seriesByKey = {};

        seriesMeta.forEach(function (meta, idx) {
            var color = COLORS[idx] || '#999999';
            var isMain = idx === 0;

            var series = chart.series.push(am5xy.LineSeries.new(root, {
                name: meta.name,
                xAxis: xAxis,
                yAxis: yAxis,
                valueYField: "sum",
                categoryXField: "category",
                connect: false, // разрывы вместо просадки в 0, если у серии нет данных на категорию (короткий месяц)
                stroke: am5.color(color),
                fill: am5.color(color)
            }));

            var strokeSettings = { strokeWidth: isMain ? 3 : 2 };
            if (DASHED[idx]) {
                strokeSettings.strokeDasharray = [4, 4];
            }
            series.strokes.template.setAll(strokeSettings);

            if (isMain) {
                series.fills.template.setAll({ fillOpacity: 0.12, visible: true });
            } else {
                series.fills.template.setAll({ visible: false });
            }

            series.bullets.push(function () {
                var circle = am5.Circle.new(root, {
                    radius: 5,
                    fill: am5.color(0xffffff),
                    stroke: am5.color(color),
                    strokeWidth: isMain ? 3 : 2,
                    opacity: 0
                });
                return am5.Bullet.new(root, { sprite: circle });
            });

            series.data.setAll(seriesData[meta.key].map(function (d) {
                return { category: d.category, sum: d.sum, date: d.date, cnt: d.cnt, spp: d.spp };
            }));

            seriesByKey[meta.key] = { series: series, name: meta.name, color: color };
        });


        // главная серия (первая в seriesMeta — "Сегодня"/текущий период)
        // должна рисоваться ПОВЕРХ остальных линий, а не под ними — иначе
        // при пересечении её загораживают "Вчера"/"Неделю назад"
        chart.series.moveValue(seriesByKey[seriesMeta[0].key].series, chart.series.length - 1);

        // --- курсор + невидимая колонка-подложка на каждую категорию ---
        var cursor = chart.set("cursor", am5xy.XYCursor.new(root, {
            behavior: "none",
            xAxis: xAxis
        }));
        cursor.lineY.set("forceHidden", true);

        cursor.events.on("cursordisappeared", function () {
            seriesMeta.forEach(function (m) {
                var info = seriesByKey[m.key];
                info.series.dataItems.forEach(function (di) {
                    if (di.bullets) {
                        di.bullets.forEach(function (b) {
                            var sprite = b.get("sprite");
                            if (sprite) sprite.set("opacity", 0);
                        });
                    }
                });
            });
        });

        var hiddenYAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
            min: 0,
            max: 1,
            renderer: am5xy.AxisRendererY.new(root, { opacity: 0 })
        }));
        hiddenYAxis.get("renderer").labels.template.set("forceHidden", true);
        hiddenYAxis.get("renderer").grid.template.set("forceHidden", true);

        var hoverSeries = chart.series.push(am5xy.ColumnSeries.new(root, {
            xAxis: xAxis,
            yAxis: hiddenYAxis,
            valueYField: "one",
            categoryXField: "category",
            clustered: false // иначе amCharts сгруппирует её с колонками СПП и сузит на всю ширину категории
        }));
        hoverSeries.columns.template.setAll({
            fillOpacity: 0,
            strokeOpacity: 0,
            width: am5.percent(100)
        });
        hoverSeries.data.setAll(categories.map(function (c) {
            return { category: c, one: 1 };
        }));

        // --- объединённый тултип ---
        var combinedTooltip = am5.Tooltip.new(root, {
            pointerOrientation: "horizontal"
        });

        var bg = combinedTooltip.get("background");
        bg.adapters.add("fillOpacity", function () { return 0; });
        bg.adapters.add("strokeOpacity", function () { return 0; });
        bg.adapters.add("pointerBaseWidth", function () { return 0; });
        bg.adapters.add("pointerLength", function () { return 0; });

        combinedTooltip.label.setAll({
            paddingTop: 0, paddingBottom: 0, paddingLeft: 0, paddingRight: 0
        });

        combinedTooltip.label.adapters.add("html", function (html, target) {
            var dataItem = target.dataItem;
            if (!dataItem) return html;
            var cat = dataItem.get("categoryX");
            if (!cat) return html;

            seriesMeta.forEach(function (m) {
                var info = seriesByKey[m.key];
                info.series.dataItems.forEach(function (di) {
                    var isCurrent = di.get("categoryX") === cat;
                    if (di.bullets) {
                        di.bullets.forEach(function (b) {
                            var sprite = b.get("sprite");
                            if (sprite) sprite.set("opacity", isCurrent ? 1 : 0);
                        });
                    }
                });
            });

            function valueAt(key) {
                var s = seriesByKey[key].series;
                var di = s.dataItems.find(function (d) { return d.get("categoryX") === cat; });
                if (!di) return { value: null, date: null, cnt: null, spp: null };
                return {
                    value: di.get("valueY"),
                    date: di.dataContext ? di.dataContext.date : null,
                    cnt: di.dataContext ? di.dataContext.cnt : null,
                    spp: di.dataContext ? di.dataContext.spp : null
                };
            }

            var rows = seriesMeta.map(function (m) {
                var info = seriesByKey[m.key];
                var found = valueAt(m.key);
                if (found.value === null || found.value === undefined) return ''; // нет данных на эту категорию — пропускаем строку

                var subtitle = found.date || cat;
                if (granularity === 'hour' && found.date) {
                    subtitle = found.date + '&nbsp;&nbsp;' + cat + ':00';
                }

                var metaBits = [];
                if (found.cnt !== null && found.cnt !== undefined) {
                    metaBits.push(found.cnt + ' зак.');
                }
                if (found.spp !== null && found.spp !== undefined) {
                    metaBits.push('СПП ' + root.numberFormatter.format(found.spp, "#,###.#") + '%');
                }
                var metaLine = metaBits.length
                    ? '<div style="color:#8A859E; font-size:11px; margin-top:2px;">' + metaBits.join(' · ') + '</div>'
                    : '';

                return (
                    '<div style="display:flex; align-items:center; justify-content:space-between; gap:24px; padding:6px 0;">' +
                        '<div style="display:flex; align-items:center; gap:10px;">' +
                            '<span style="width:10px; height:10px; border-radius:50%; background:' + info.color + '; display:inline-block; flex-shrink:0;"></span>' +
                            '<div>' +
                                '<div style="font-weight:600; font-size:13px; color:#1D1B2A; line-height:1.2;">' + info.name + '</div>' +
                                '<div style="color:#8A859E; font-size:11px; margin-top:2px;">' + subtitle + '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div style="text-align:right; margin-left:12px;">' +
                            '<div style="font-weight:700; font-size:14px; color:#1D1B2A; white-space:nowrap;">' +
                                root.numberFormatter.format(found.value, "#,###.##") + '&nbsp;₽' +
                            '</div>' +
                            metaLine +
                        '</div>' +
                    '</div>'
                );
            }).join('');

            return (
                '<div style="background:#ffffff; border:1px solid #E2E0EC; border-radius:18px; padding:12px 16px; ' +
                'box-shadow:0 10px 25px -5px rgba(0,0,0,.08), 0 4px 10px -2px rgba(0,0,0,.04); ' +
                'font-family:Inter,-apple-system,BlinkMacSystemFont,sans-serif; min-width:240px;">' +
                    rows +
                '</div>'
            );
        });

        hoverSeries.set("tooltip", combinedTooltip);

        // --- легенда ---
        var legend = root.container.children.push(am5.Legend.new(root, {
            centerX: am5.p50,
            x: am5.p50,
            marginTop: 12,
            useDefaultMarker: true
        }));
        legend.markers.template.setAll({ width: 12, height: 12 });
        legend.markerRectangles.template.setAll({
            cornerRadiusTL: 6, cornerRadiusTR: 6, cornerRadiusBL: 6, cornerRadiusBR: 6
        });
        legend.markerRectangles.template.adapters.add("fill", function (fill, target) {
            var dataItem = target.dataItem;
            if (dataItem && dataItem.dataContext) {
                var color = dataItem.dataContext.get("stroke");
                if (color) return color;
            }
            return fill;
        });
        legend.data.setAll(seriesMeta.map(function (m) { return seriesByKey[m.key].series; }));

        // легенда — сосед chart, а не потомок, поэтому её появление (и цвет
        // маркеров при первом рендере) иногда не подхватывается сразу;
        // короткий polling-фолбэк форсирует итоговое состояние.
        var forceUpdateInterval = setInterval(function () {
            var allDone = true;
            legend.dataItems.forEach(function (dataItem) {
                var marker = dataItem.get("marker");
                if (marker) {
                    var rect = marker.children.getIndex(0);
                    if (rect) {
                        var color = dataItem.dataContext.get("stroke");
                        if (color && rect.get("fill") !== color) rect.set("fill", color);
                        if (rect.get("fillOpacity") !== 1) {
                            rect.set("fillOpacity", 1);
                            allDone = false;
                        }
                    }
                }
            });
            if (allDone) clearInterval(forceUpdateInterval);
        }, 50);
        setTimeout(function () { clearInterval(forceUpdateInterval); }, 3000);

        chart.appear(800, 100);
        legend.appear(800, 100);

        return root;
    }

    function disposeChart(tabKey) {
        if (amRoots[tabKey]) {
            amRoots[tabKey].dispose();
            amRoots[tabKey] = null;
        }
    }

    // ---------- отрисовка всего пейлоада (обе вкладки: orders + sales) ----------
    function renderPayload(payload) {
        ['orders', 'sales'].forEach(function (tabKey) {
            var data = payload[tabKey];
            var pane = document.querySelector('.tsw-pane[data-pane="' + tabKey + '"]');

            renderKpi(pane, data.seriesMeta, data.totals);

            var captionEl = pane.querySelector('.tsw-axis-caption');
            if (data.axisCaption) {
                captionEl.textContent = data.axisCaption;
                captionEl.style.display = 'block';
            } else {
                captionEl.style.display = 'none';
            }

            disposeChart(tabKey);
            try {
                amRoots[tabKey] = buildChart(
                    'tsw-chart-' + tabKey,
                    data.granularity,
                    data.categories,
                    data.seriesMeta,
                    data.series
                );
            } catch (e) {
                console.error('[today-stats-widget] ошибка построения графика "' + tabKey + '":', e);
            }
        });
    }

    am5.ready(function () {
        console.log('[today-stats-widget] проверка поля date, первая точка серии "a" (orders):',
            cache.today.orders.series.a[0]);
        renderPayload(cache.today);
    });
})();
</script>
<style>
    .tsw-axis-caption {
        text-align: end;
        color: blue !important;
        margin-bottom: -8px !important;
    }
</style>