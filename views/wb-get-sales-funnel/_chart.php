<?php
use yii\helpers\Json;
?>
<?php if (!empty($chartData)): ?>
    <hr>

    <div class="chart-controls" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #eee; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <strong style="display: block; margin-bottom: 10px; color: #555;">Управление сериями:</strong>
        
        <div class="checkbox-group">
            <label class="custom-chk-container" style="color: #f965cf;">
                <input type="checkbox" id="chk-openCount" checked>
                <span class="checkmark" style="--chk-color: #f965cf;"></span>
                Переходы в карточку
            </label>
            
            <label class="custom-chk-container" style="color: #660ec8;">
                <input type="checkbox" id="chk-cartCount" checked>
                <span class="checkmark" style="--chk-color: #660ec8;"></span>
                Добавление в корзин
            </label>
            
            <label class="custom-chk-container" style="color: #5067de;">
                <input type="checkbox" id="chk-orderCount" checked>
                <span class="checkmark" style="--chk-color: #5067de;"></span>
                Заказы
            </label>
        </div>
    </div>

    <div id="chartdiv" style="width: 100%; height: 450px; background-color: #f9f9f9; margin-bottom: 30px; border-radius: 8px; border: 1px solid #ddd;"></div>
<?php
$jsData = json_encode($chartData);

$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', [
    'depends' => [\app\assets\ChartAsset::class]
]);

$this->registerJs(<<<JS
    am5.ready(function() {
        var root = am5.Root.new("chartdiv");
        root.locale = am5locales_ru_RU;
        root.setThemes([am5themes_Animated.new(root)]);

        var chart = root.container.children.push(am5xy.XYChart.new(root, {
            panX: true, panY: true, wheelX: "panX", wheelY: "zoomX", pinchZoomX: true,
            layout: root.verticalLayout
        }));

        var legend = chart.children.push(am5.Legend.new(root, {
            centerX: am5.p50, x: am5.p50, marginTop: 15, marginBottom: 15
        }));

        var xAxis = chart.xAxes.push(am5xy.DateAxis.new(root, {
            maxDeviation: 0.1,
            baseInterval: { timeUnit: "day", count: 1 },
            renderer: am5xy.AxisRendererX.new(root, { 
                minGridDistance: 50,    
                cellStartLocation: 0.2,     
                cellEndLocation: 0.8
            }),
            tooltip: am5.Tooltip.new(root, {})
        }));

        // --- ЛЕВАЯ ОСЬ Y (для корзин и заказов) --- 
        var yAxisLeft = chart.yAxes.push(am5xy.ValueAxis.new(root, {
            renderer: am5xy.AxisRendererY.new(root, {}),
            min: 0,
            extraMin: 0,
            tooltip: am5.Tooltip.new(root, {}),
            strictMinMax: true,
            extraMax: 0.2 
        }));

        // --- ПРАВАЯ ОСЬ Y (для переходов) ---
        var yAxisRight = chart.yAxes.push(am5xy.ValueAxis.new(root, {
            renderer: am5xy.AxisRendererY.new(root, {
                opposite: true, // Размещаем справа
            }),
            tooltip: am5.Tooltip.new(root, {}),
            min: 0,
            extraMax: 0.1
        }));

        // Чтобы сетка от двух осей не перемешивалась, можно отключить её у правой
        yAxisRight.get("renderer").grid.template.setAll({ visible: false });

        yAxisLeft.children.moveValue(am5.Label.new(root, {
            text: "Заказы, корзины (шт)",
            rotation: 270,
            y: am5.p50,
            centerX: am5.p50,
            fontSize: "12px",
            fill: am5.color(0x5067de)
        }), 0);

        yAxisRight.children.moveValue(am5.Label.new(root, {
            text: "Переходы (шт)",
            rotation: -90,
            y: am5.p50,
            x: am5.p100,
            centerX: am5.p50,
            fontSize: "12px",
            fill: am5.color(0xf44336)
        }), 0);

        xAxis.get("renderer").labels.template.setAll({
            location: 0.5,        // Центрируем метку внутри дня
            multiLocation: 0.5,
            centerX: am5.p50     // Выравниваем текст по центру точки привязки
        });

        xAxis.get("renderer").grid.template.setAll({
            location: 0.5,        // Смещаем вертикальную линию сетки в центр дня (к точке)
            multiLocation: 0.5
        });

        xAxis.set("dateFormats", {
            "day": "dd MMM"
        });
        xAxis.set("periodChangeDateFormats", {
            "day": "dd MMM"
        });


        yAxisLeft.get("renderer").labels.template.setAll({ fill: am5.color(0x5067de) });
        yAxisRight.get("renderer").labels.template.setAll({ fill: am5.color(0xf44336) });


        var seriesMap = {};

        function createSeries(id, name, field, color, targetYAxis, isSpline = false, showLabels = false, sWidth = 2) {
            var series = chart.series.push(am5xy.SmoothedXLineSeries.new(root, {
                name: name,
                xAxis: xAxis,
                yAxis: targetYAxis, // Привязываем к переданной оси
                valueYField: field,
                valueXField: "date",
                stroke: color,
                strokeWidth: sWidth,
                tension: isSpline ? 0.5 : 1, 
                tooltip: am5.Tooltip.new(root, { 
                    labelText: "{name}: {valueY}",
                    pointerOrientation: "horizontal" 
                })
            }));

            // Рисуем точку (кружок)
            series.bullets.push(function() {
                return am5.Bullet.new(root, {
                    sprite: am5.Circle.new(root, { radius: 4, fill: color })
                });
            });

            // Красивая выноска со значением (как в примере)
            if (showLabels) {
                series.bullets.push(function() {
                    return am5.Bullet.new(root, {
                        locationY: 1,
                        sprite: am5.Label.new(root, {
                            text: "{valueY}",
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
            }

            series.fills.template.setAll({ fillOpacity: 0.1, visible: true });
            series.data.setAll($jsData);
            series.appear(1000);
            legend.data.push(series);
            seriesMap[id] = series;
        }

        // Создаем серии с привязкой к разным осям
        
        // "Корзины" и "Заказы" — на правую ось
        createSeries("cartCount", "Корзины", "cartCount", am5.color(0x660ec8), yAxisLeft);
        createSeries("orderCount", "Заказы", "orderCount", am5.color(0x5067de), yAxisLeft, false, true);

        // "Переходы" — на левую ось
        createSeries("openCount", "Переходы", "openCount", am5.color(0xf44336), yAxisRight, true, true);

        // Обработчики чекбоксов (если вы их добавили)
        ["openCount", "cartCount", "orderCount"].forEach(function(id) {
            var chk = document.getElementById("chk-" + id);
            if (chk) {
                chk.addEventListener("change", function() {
                    this.checked ? seriesMap[id].show() : seriesMap[id].hide();
                });
            }
        });

        chart.set("cursor", am5xy.XYCursor.new(root, { behavior: "zoomX" }));
    });
JS
, \yii\web\View::POS_READY);
?>
<?php endif; ?>