<?php
use app\assets\AppAsset;
AppAsset::register($this);

use yii\helpers\Html;
use app\components\WbImageHelper;
use kartik\grid\GridView;
use kartik\select2\Select2;

use app\models\WbStocks;
use yii\data\ArrayDataProvider;

use kartik\icons\Icon;
Icon::map($this); 

$nmId = $card['nmID'] ?? null;

$dateFrom = $dateFromWidget ?: date('Y-m-d', strtotime($date_from));
$dateTo   = date('Y-m-d', strtotime($date_to));

$title = 'О карточке';
//$this->title = 'Карточка: ' . ($card->title ? $card->title : 'Выберите артикул');
$this->title = 'Карточка: ' . (isset($card['title']) && $card['title'] ? $card['title'] : 'Выберите артикул');


$this->params['breadcrumbs'][] = ['label' => 'Данные', 'url' => ['index']];
$this->params['breadcrumbs'][] = $title;

$myButtons = \app\components\AdminQuickButtons::getButtons();
?>
<div class="wb-detail-report">
<?php if ($card): ?> 
    <?= \app\components\PageHeaderWidget::widget(['title' => $card['title'],'nmId' => $card['nmID'] ]) ?>
<?php else: ?>
    <h1><?= Html::encode($this->title) ?></h1>
<?php endif; ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <?= \app\components\getDPWidget::widget([
                    'action' => ['/wb/detail'], 
                    'quickButtons' => $myButtons, 
                    'defaultDateFrom' => $dateFrom,]) 
            ?>
<?php if ($AdvProvider): ?> 
    <?php if ($AdvProvider->totalCount > 0): ?>
        <div class="grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_65 mb-3" style="margin-top: 10px;">
        <?php
            echo $this->render('include/_advtable', [
                'AdvProvider' => $AdvProvider, 'dateFrom' => $dateFrom,
            ]);
        ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info" style="margin-top: 10px;" >Нет данных о рекламных компаниях.</div>
    <?php endif; ?>
<?php endif; ?>

<?php /* остатки */
    $totals = WbStocks::getTotalStats($nmId);

    // Провайдер для таблицы остатков
    $stocksProvider = new ArrayDataProvider([
        'allModels' => WbStocks::getWarehouseStocks($nmId),
        'pagination' => false, // обычно складов не так много
    ]);

    // Провайдер для логистики (в пути)
    $inWayProvider = new ArrayDataProvider([
        'allModels' => WbStocks::getInWayStocks($nmId),
        'pagination' => false,
    ]);
?>
<?php if ($stocksProvider): ?> 
    <div class="row custom-compact-grid">
    <div class="col-md-6">
        <div class="grid_wbstat grid_no_kv-panel-before"> <?php /*expandable-container*/?>
            <?php echo GridView::widget([
                'dataProvider' => $stocksProvider,

                'containerOptions' => ['class' => 'custom-compact-grid'],
                'export' => false, 
                'toggleData' => false,
                'pjax' => true,
                'bordered' => true,
                'striped' => true,
                'condensed' => true,
                'responsive' => true,
                'hover' => true,
                'showPageSummary' => true,
                'pageSummaryPosition' => GridView::POS_TOP, 

                'layout' => "{summary}\n{items}",
                'showFooter' => false,
//                'panel' => false,

                'summary' => false, // Отключаем текст "Showing 1-X of Y"
                'columns' => [
                    [
                        'attribute' => 'warehouse_name',
                        'label' => 'Склад',
                        'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; min-width: 200px;'],
                    ],
                    [
                        'attribute' => 'quantity',
                        'label' => 'Доступно (шт)',
                        'format' => ['decimal', 0],
                        'pageSummary' => true, 
                        'contentOptions' => ['class' => 'text-right'],
                        'headerOptions' => ['class' => 'text-right'],
                        'pageSummaryOptions' => ['class' => 'text-right'],
                    ],
                ],
                'panel' => [
//                    'type' => GridView::TYPE_INFO,
                    'type' => GridView::TYPE_PRIMARY,
                    'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                    'heading' => 'На складах',
                    'footer' => false, // отключает card-footer 
                    'after' => false,
                ],
            ]);
            ?>
        </div>
<?php /*
        <div class="expand-btn-wrapper" style="margin-bottom: 5px;">
            <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
        </div>
*/?>
    </div>

    <div class="col-md-6">
        <div class="grid_wbstat grid_no_kv-panel-before"> <?php /* expandable-container */ ?>
            <?php echo GridView::widget([
                'dataProvider' => $inWayProvider,

                'containerOptions' => ['class' => 'custom-compact-grid'],
                'export' => false, 
                'toggleData' => false,
                'pjax' => true,
                'bordered' => true,
                'striped' => true,
                'condensed' => true,
                'responsive' => true,
                'hover' => true,
                'showPageSummary' => true,
                'pageSummaryPosition' => GridView::POS_TOP, 
                'showFooter' => false,
//                'panel' => false,

                'showPageSummary' => true,
                'summary' => false,
                'columns' => [
                    [
                        'attribute' => 'warehouse_name',
                        'label' => 'Склад отправки',
                    ],
                    [
                        'attribute' => 'in_way_to_client',
                        'label' => 'К клиенту',
                        'pageSummary' => true,
                        'contentOptions' => ['class' => 'text-right'],
                        'pageSummaryOptions' => ['class' => 'text-right'],
                    ],
                    [
                        'attribute' => 'in_way_from_client',
                        'label' => 'От клиента',
                        'pageSummary' => true,
                        'contentOptions' => ['class' => 'text-right'],
                        'pageSummaryOptions' => ['class' => 'text-right'],
                    ],
                ],
                'panel' => [
//                    'type' => GridView::TYPE_WARNING,
                    'type' => GridView::TYPE_PRIMARY,
                    'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                    'heading' => 'В пути',
                    'footer' => false, // отключает card-footer 
                    'after' => false,
                ],
            ]);
            ?>               
        </div>
<?php /*
        <div class="expand-btn-wrapper" style="margin-bottom: 5px;">
            <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
        </div>
*/?>
    </div>
    </div>
<?php endif; ?>

            <?php /* Платное хранение — агрегат по calcType за период */ ?>
            <?php if (isset($paidStorageProvider) && $paidStorageProvider->totalCount > 0): ?>
                <div class="grid_wbstat grid_no_kv-panel-before expandable-container paid-storage" style="margin-top: 10px;">
                    <?php echo GridView::widget([
                        'dataProvider' => $paidStorageProvider,
                        'containerOptions' => ['class' => 'custom-compact-grid'],
                        'export' => false,
                        'toggleData' => false,
                        'pjax' => false,
                        'bordered' => true,
                        'striped' => true,
                        'condensed' => true,
                        'responsive' => true,
                        'hover' => true,
                        'showPageSummary' => true,
                        'pageSummaryPosition' => GridView::POS_TOP,
                        'layout' => "{items}",
                        'showFooter' => false,
                        'summary' => false,
                        'panel' => [
                            'type' => GridView::TYPE_PRIMARY,
                            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                            'heading' => 'Платное хранение за период (' . Html::encode(date('d.m.Y', strtotime($date_from)) . ' — ' . date('d.m.Y', strtotime($date_to))) . ')',
                            'footer' => false,
                            'after' => false,
                        ],
                        'columns' => [
                            [
                                'attribute' => 'calcType',
                                'label' => 'Тип расчёта',
                                'headerOptions' => ['style' => 'text-align: left; min-width: 240px;'],
                                'contentOptions' => ['style' => 'text-align: left; font-size: 12px; white-space: nowrap;'],
                            ],
                            [
                                'attribute' => 'days_cnt',
                                'label' => 'Дней',
                                'format' => ['decimal', 0],
                                'hAlign' => 'right',
                                'headerOptions' => ['style' => 'text-align: center; width: 60px;'],
                                'contentOptions' => ['class' => 'text-right'],
                                'pageSummary' => false,
                            ],
                            [
                                'attribute' => 'total_units',
                                'label' => 'Ед. × дни',
                                'format' => ['decimal', 0],
                                'hAlign' => 'right',
                                'headerOptions' => ['style' => 'text-align: center; width: 90px;'],
                                'contentOptions' => ['class' => 'text-right'],
                                'pageSummary' => true,
                                'pageSummaryOptions' => ['class' => 'text-right fw-bold'],
                            ],
                            [
                                'attribute' => 'avg_volume',
                                'label' => 'Объём',
                                'format' => ['decimal', 2],
                                'hAlign' => 'right',
                                'headerOptions' => ['style' => 'text-align: center; width: 70px;'],
                                'contentOptions' => ['class' => 'text-right', 'style' => 'color: #5d6d7e;'],
                                'value' => function($model) { $v = $model['avg_volume'] ?? null; return $v !== null && (float)$v > 0 ? $v : null; },
                            ],
                            [
                                'attribute' => 'total_price',
                                'label' => 'Сумма ₽',
                                'format' => ['decimal', 2],
                                'hAlign' => 'right',
                                'headerOptions' => ['style' => 'text-align: center; width: 110px;'],
                                'contentOptions' => function($model) {
                                    $isNeg = ($model['total_price'] ?? 0) < 0;
                                    return ['class' => 'text-right ', 'style' => $isNeg ? 'color: #c0392b;' : 'color: #2c3e50;'];
                                },
                                'pageSummary' => true,
                                'pageSummaryOptions' => ['class' => 'text-right fw-bold', 'style' => 'color: #2c3e50; background: #f8f9fa;'],
                            ],
                            [
                                'attribute' => 'price_per_unit',
                                'label' => '₽/ед.',
                                'hAlign' => 'right',
                                'format' => 'raw',
                                'headerOptions' => ['style' => 'text-align: center; width: 80px;'],
                                'contentOptions' => ['class' => 'text-right', 'style' => 'color: #7f8c8d; font-style: italic;'],
                                'value' => function($model) {
                                    if ($model['price_per_unit'] === null || $model['total_units'] == 0) return '<span style="color:#bbb;">—</span>';
                                    return number_format((float)$model['price_per_unit'], 2, ',', ' ');
                                },
                            ],
                        ],
                    ]); ?>
                </div>
            <div class="expand-btn-wrapper" style="margin-bottom: 5px;">
                <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
            </div>
            <?php else: ?>
                <?php if ($card): ?>
                <div class="alert alert-light border" style="margin-top: 10px; font-size: 12px;">Нет данных о платном хранении за период.</div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
        <div class="col-md-6">
            <?php if ($card): ?> 
            <div class="div_bordered">
                <div class="wb_preview_title col-md-12">
                    <div class="panel-heading">Товар: <b><?= Html::encode($card->title) ?></b></div>
                    <div class="row" style=" margin-top: 5px; margin-bottom:15px">
                        <div class="panel-body font_11px grey card_characteristics col-md-6"> 
                            <dl>
                            <div class="dl_item"><dt class="card_characteristics__dt">Арт. WB:</dt><dd class="card_characteristics__dd"><a href="https://www.wildberries.ru/catalog/<?= Html::encode($card->nmID) ?>/detail.aspx?targetUrl=EX" target="_blank"><b><?= Html::encode($card->nmID) ?></b></a> </dd></div>
                            <div class="dl_item"><dt class="card_characteristics__dt">Арт.:   </dt><dd class="card_characteristics__dd"><b><?= Html::encode($card->vendorCode) ?></b> </dd></div>
                            <div class="dl_item"><dt class="card_characteristics__dt">Бренд:  </dt><dd class="card_characteristics__dd"><b><?= Html::encode($card->brand) ?></b></dd></div>
                            <div class="dl_item"><dt class="card_characteristics__dt">Размер: </dt><dd class="card_characteristics__dd"><?= Html::encode($card->getDimensions(' x ')) ?></dd></div>
                            </dl>
                        </div>
                        <div class="panel-body font_11px grey panel_stats col-md-6"> 
                            <?php if ($OrdersStats): ?> 
                            <?php 
                            $notb = ($OrdersStats['cancel'] > 0) ? $OrdersStats['notb'] - $OrdersStats['cancel'] : 0; 
                            $percent = ($OrdersStats['cancel'] > 0) ? 100 - round(($OrdersStats['cancel'] / $OrdersStats['alls']) * 100, 1) : 0;
                            $colorClass = ($percent > 90) ? 'bg-success' : (($percent > 80) ? 'bg-warning' : 'bg-danger');

                            ?> 
                            <center>с <b><?= date('Y-m-d', strtotime($date_from)) ?></b> по <b><?= date('Y-m-d', strtotime($date_to14)) ?></b></center>
                            <div class="orders_total">
                                <div>Всего заказов <b><?= $OrdersStats['alls'] ?></b></div><div>На сумму <b><?= number_format($OrdersStats['sLO'], 2, ',', ' ') ?></b></div>
                                <div>Отказов  <b><?= $OrdersStats['cancel'] ?></b></div><div>Процент выкупа <span class="<?= str_replace('bg-', 'text-', $colorClass) ?> fw-bold"><?= $percent ?>%</span>
                                </div>
                                <div>Выкупили <b><?= $OrdersStats['bought'] ?></b></div><div> На сумму <b><?= number_format($OrdersStats['sum'], 2, ',', ' ') ?></b></div>
                                <div> Не выкупили <b><?= $notb ?></b></div><div>К оплате <b><?= number_format($OrdersStats['sFP'], 2, ',', ' ') ?></b></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>


                </div>
                <div class="row">
                    <div style="height: 200px;" class="wb_preview_img col-md-12">
                        <?= $card->renderGallery(); ?>
                    </div>
                </div>
                <div class="row expandable-container card-descr">
                    <div class="card_characteristics col-md-6">
                        <?= $card->renderCharacteristics(); ?>
                    </div>
                    <div class="card_description col-md-6">
                        <p><b>Описание</b></p>
                        <p><?= $card->description; ?></p>
                    </div>
                </div>
                <div class="expand-btn-wrapper" style="margin-bottom: 5px;">
                    <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
                </div>

            </div>
            <?php endif; ?>
        </div>
    </div>

<?php if (!empty($OrderFunnel)): ?>
    <div class="row mb-3">
        <div class="col-md-12">
            <?= $this->render('include/_order_funnel', ['OrderFunnel' => $OrderFunnel]) ?>
        </div>
    </div>
<?php endif; ?>


<?php /* if ($PivotDataProvider): ?> 
<div class="row mb-3">
    <div class="col-md-12 grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_100">

<?php
    $columns = [
        [
            'attribute' => 'metric_label',
            'label' => 'Показатель',
            'contentOptions' => ['style' => 'font-weight: bold; background: #f4f4f4;'],
        ],
        [
            'attribute' => 'total',
            'label' => 'ИТОГО',
            'format' => ['decimal', 2],
            'contentOptions' => [
                'style' => 'font-weight: bold; color: #2c3e50; background: #f2e7c3; text-align: right;'
            ],
            'headerOptions' => ['style' => 'text-align: center; background: #f2e7c3;'],
        ],
    ];

    // Добавляем по колонке для каждой даты
    foreach ($pivotDates as $date) {
        $columns[] = [
            'attribute' => $date,
            'label' => $date,
            'format' => ['decimal', 2], // Опционально: форматирование чисел
            'contentOptions' => ['style' => 'text-align: right;'],
            'headerOptions'  => ['style' => 'text-align: center;'],
            'value' => function($model) use ($date) {
                return $model[$date] ?? 0;
            }
        ];
    }

    echo GridView::widget([
        'dataProvider' => $PivotDataProvider,
            'containerOptions' => ['class' => 'custom-compact-grid'],
            'export' => [
                'showConfirmAlert' => false,
                'target' => GridView::TARGET_BLANK
            ],
            'exportConfig' => [
                GridView::EXCEL => ['label' => 'Сохранить в Excel'],
            ],
            'pjax' => true,
            'bordered' => true,
            'striped' => true,
            'condensed' => true,
            'responsive' => true,
            'hover' => true,

            'showPageSummary' => false,
            'showFooter' => false,

            'toggleData' => true,
            'panel' => [
                'type' => GridView::TYPE_PRIMARY,
                'heading' => 'Детализация продаж (по месяцам)',
                'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                'footer' => false,
                 'after' => false,
            ],
            'containerOptions' => [
                'class' => 'no-border-class' 
            ],

            'rowOptions' => function ($model, $key, $index, $grid) {
                $highlightKeys = ['aretail_amount', 'afor_pay'];
                
                if (in_array($model['metric_key'], $highlightKeys)) {
                    return ['class' => 'per_unit'];
                }
                return [];
            },

        'columns' => $columns,
    ]);


?>
    </div>
</div>
<?php endif; */ /*вывод PivotDataProvider end */  ?>

<div class="row mb-3">
    <div class="col-md-6">
<?php if ($LastOrdersProvider): ?> 
    <?php if ($LastOrdersProvider->totalCount > 0): ?>

        <div class="col-md-12 grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_100 expandable-container" style="margin-top: 10px;">
            <?php
                echo $this->render('include/_lo_table.php', [
                    'LastOrdersProvider' => $LastOrdersProvider, 'dateFrom' => $dateFrom,
                ]);
            ?>
        </div>
        <div class="expand-btn-wrapper" style="margin-bottom: 5px;">
            <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
        </div>

    <?php else: ?>
        <div class="alert alert-info" style="margin-top: 10px;" >Нет данных о заказах.</div>
    <?php endif; ?>
<?php endif; ?>
    </div>
    <div class="col-md-6">
<?php if ($LastSalesProvider): ?> 
    <?php if ($LastSalesProvider->totalCount > 0): ?>
        <div class="col-md-12 grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_100 expandable-container" style="margin-top: 10px;">
            <?php
                echo $this->render('include/_ls_table.php', [
                    'LastSalesProvider' => $LastSalesProvider, 'dateFrom' => $dateFrom,
                ]);
            ?>
        </div>
        <div class="expand-btn-wrapper" style="margin-bottom: 5px;">
            <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
        </div>
    <?php else: ?>
        <div class="alert alert-info" style="margin-top: 10px;" >Нет данных о продажах.</div>
    <?php endif; ?>
<?php endif; ?>
</div>
</div>


<div class="row mb-3">
    <div class="col-md-6 div-chart" id="LinechartDiv">
<?php if ($LastOrdersProvider): ?> 
    <?php if ($LastOrdersProvider->totalCount > 0): ?>
        <div class="panel panel-default div_bordered">
            <div class="panel-heading d-flex align-items-center" style="position: relative; justify-content: center; min-height: 40px;">
                <span class="mx-auto" style="text-align: center;"><b>Заказы:</b> Цена и Кол-во</span>
                <div class="btn-group btn-group-sm mb-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="setGraphInterval('day')"  title='По дням'>D</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setGraphInterval('week')" title='По неделям'>W</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setGraphInterval('month')" title='По месяцам'>M</button>
                    &nbsp 
                    <button type="button" class="btn btn-secondary active" onclick="toggleLabels(Linechart, this); return false;" title='Подписи'><i class="bi bi-tag"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleWidth(this); return false;" title="Изменить ширину" data-bs-toggle="tooltip"><i class="fas fa-arrows-alt-h"></i></button>

                </div>
            </div>
            <div class="panel-body">

                <div id="timeline_div" style="width: 100%; height: 400px;"></div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
    </div>

    <div class="col-md-6 div-chart" id="YearchartDiv">
<?php if ($ChartformattedData): ?> 
        <div class="panel panel-default div_bordered">
            <div class="panel-heading d-flex align-items-center" style="position: relative; justify-content: center; min-height: 40px;">
                <span class="mx-auto" style="text-align: center;"><b>Продажи по годам</b></span>
                <div class="btn-group btn-group-sm mb-2">
                    <button type="button" class="btn btn-secondary active" onclick="toggleLabels(YearLineChart, this); return false;" title='Подписи'><i class="bi bi-tag"></i></button>
                </div>
            </div>
            <div class="panel-body">
                <div id="yearline_div" style="width: 100%; height: 400px;"></div>
            </div>
        </div>
<?php endif; ?>
    </div>

<?php if ($card): ?> 

    <div class="row grid_wbstat" style="margin: 20px auto;">
        <?php
        echo GridView::widget([
            'dataProvider' => $WeeklyFinanceProvider,
            'export' => false, 
            'pjax' => false,
            'bordered' => true,
            'striped' => true,
            'condensed' => true,
            'responsive' => true,
            'hover' => true,
            'showPageSummary' => true, // Включаем строку "Итого"
            'showFooter' => false,
            'toggleData' => false,
            'panel' => [
                'type' => GridView::TYPE_PRIMARY,
                'heading' => 'Аналитика продаж по неделям',
                'headingOptions' => ['class' => 'card-header text-white bg-wb-green-deep-header'],
                'footer' => false,
                'after'  => false,
            ],
            'containerOptions' => [
                'class' => 'no-border-class' 
            ],
            'columns' => [
                [
                    'attribute' => 'sdate',
                    'label' => 'Год-Неделя',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; width: 90px;'],
                    'contentOptions' => ['style' => 'text-align: center; font-weight: bold; color: #2c3e50; vertical-align: middle;'],
                    'pageSummary' => 'Итого',
                    'pageSummaryOptions' => ['style' => 'text-align: center; font-weight: bold;']
                ],
                [
                    'attribute' => 'qnt',
                    'label' => 'Кол-во',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle;'],
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'amount',
                    'label' => 'Продажи',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; font-weight: bold;'],
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'commission',
                    'label' => 'Ком. WB',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                    'pageSummary' => true,
                ],
/*
                [
                    'attribute' => 'f_retail_amount',
                    'label' => 'К перечислению',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; '],
                    'pageSummary' => true,
                ],
*/
                [
                    'attribute' => 'f_acquiring_fee',
                    'label' => 'Эквайринг',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                    'pageSummary' => true,
                ],
/*
                [
                    'attribute' => 'f_acceptance',
                    'label' => 'Приемка',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                    'pageSummary' => true,
                ],
*/
                [
                    'attribute' => 'f_delivery',
                    'label' => 'Логистика',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b; font-weight: 500;'],
                    'pageSummary' => true,
                ],
/*
                [
                    'attribute' => 'f_storage_fee',
                    'label' => 'Хранение',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b; font-weight: 500;'],
                    'pageSummary' => true,
                ],
*/
                [
                    'attribute' => 'f_penalty',
                    'label' => 'Штрафы',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                    'pageSummary' => true,
                ],
/*
                [
                    'attribute' => 'f_deduction',
                    'label' => 'Удержания',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                    'pageSummary' => true,
                ],
*/
                [
                    'attribute' => 'f_otziv',
                    'label' => 'Отзывы',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #16a085;'],
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'f_adv',
                    'label' => 'Реклама',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #2980b9;'],
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'f_cashback',
                    'label' => 'Кэшбек',
//                    'format' => ['decimal', 2],
                    'format' => 'integer',
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'net_profit',
                    'label' => 'Общий итог',
//                    'format' => ['decimal', 2],
                    'format' => 'integer',
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; background-color: #e8f8f5; color: #111;'],
/*
                    'contentOptions' => [
                        'style' => 'vertical-align: middle; font-weight: bold; color: #27ae60; background-color: #f4fbf7;'
                    ],
*/
                    'contentOptions' => function($model) {
                        return $model['net_profit'] < 0 
                            ? ['class' => 'table-danger text-danger fw-bold'] 
                            : ['class' => 'table-success text-success fw-bold', 'style' => 'font-weight: bold;'];
                    },


                    'pageSummary' => true, // Автоматически посчитает сумму итогов за все месяцы
                    'pageSummaryOptions' => ['style' => 'text-align: right; font-weight: bold; color: #27ae60; background-color: #f4fbf7;']
                ],


            [
                'attribute' => 'total_nds',
                'label' => 'НДС',
                'format' => 'integer',
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; color: #111;'],
                'contentOptions' => ['style' => 'vertical-align: middle; color: #333;'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'total_cost',
                'label' => 'Себ-ть',
                'format' => 'integer',
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; color: #111;'],
                'contentOptions' => ['style' => 'vertical-align: middle; color: #333;'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'profit_before_tax',
                'label' => 'Прибыль',
                'format' => 'integer',
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; background-color: #fcf3cf; color: #111;'],
//                'contentOptions' => ['style' => 'vertical-align: middle; font-weight: bold; background-color: #fefde7;'],

                'contentOptions' => function($model) {
                    return $model['profit_before_tax'] < 0 
                        ? ['style' => 'vertical-align: middle; font-weight: bold; background-color: #fefde7;', 'class' => 'text-danger'] 
                        : ['style' => 'vertical-align: middle; font-weight: bold; background-color: #fefde7;'];
                },

                'pageSummary' => true,
                'pageSummaryOptions' => ['style' => 'text-align: right; font-weight: bold; background-color: #fefde7;']
            ],
            [
                'attribute' => 'tax_amount',
                'label' => 'Налог (7%)',
                'format' => 'integer',
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; color: #d35400;'],
                'contentOptions' => ['style' => 'vertical-align: middle; color: #e67e22;'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'clean_margin',
                'label' => 'Маржа',
                'format' => 'integer',
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; background-color: #d4efdf; color: #111; font-weight: bold;'],
/*
                'contentOptions' => [
                    'style' => 'vertical-align: middle; font-weight: bold; color: #196f3d; background-color: #eaf2f8;'
                ],
*/
                'contentOptions' => function($model) {
                    return $model['clean_margin'] < 0 
                        ? ['class' => 'table-danger text-danger fw-bold'] 
                        : ['class' => 'table-success text-success fw-bold', 'style' => 'font-weight: bold;'];
                },
                
                'pageSummary' => true,
                'pageSummaryOptions' => ['style' => 'text-align: right; font-weight: bold; color: #196f3d; background-color: #eaf2f8;']
            ],

                [
                    'attribute' => 'amount_per_item',
                    'label' => 'Продажа/шт',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'contentOptions' => ['style' => 'font-style: italic; background-color: #fafafa;'],
/*
                    // Считаем среднюю цену за весь период в подвале
                    'pageSummary' => function ($summary, $data, $widget) {
                        $totalQnt = array_sum(array_column($widget->dataProvider->allModels, 'qnt'));
                        $totalAmount = array_sum(array_column($widget->dataProvider->allModels, 'amount'));
                        return $totalQnt > 0 ? number_format($totalAmount / $totalQnt, 2, '.', ' ') : '0.00';
                    },
*/
                ],


                [
                    'attribute' => 'profit_per_item',
                    'label' => 'Итог/шт',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'contentOptions' => function($model) {
                        $class = $model['profit_per_item'] < 0 ? 'text-danger' : 'text-success';
                        return ['class' => $class, 'style' => 'font-weight: bold; font-style: italic; background-color: #fafafa;'];
                    },
                ],

                [
                    'attribute' => 'clear_per_item',
                    'label' => 'Маржа/шт',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'contentOptions' => function($model) {
                        $class = $model['clear_per_item'] < 0 ? 'text-danger' : 'text-success';
                        return ['class' => $class, 'style' => 'font-weight: bold; font-style: italic; background-color: #fafafa;'];
                    },
                ],

            ],
        ]);
        ?>
        </div>

    <div class="row product-detail-phrases grid_wbstat" style="margin: 0px auto 30px;">
            <h3>Анализ поисковых фраз</h3>
            <?= $this->render('/wb-search/_card_table', [
                'dataProvider' => $phraseDataProvider, // DataProvider, сформированный в вашем новом контроллере через WbSearchService
                'uniqueDates' => $uniqueDates,
            ]) ?>
    </div>
<?php endif; ?>

</div>



<style>
    .panel-heading .mx-auto { width: 60%;}
    .wb_preview_img img { width: 23%;}
    .wb_preview_img {display: flex; flex-direction: row; flex-wrap: nowrap; justify-content: space-around; align-items: center; }
    .wb_preview_img {margin-bottom: 20px;}
    .wb_preview_title a {text-decoration: none;}
    .wb-detail-report h1 {font-size: 30px;}
    .card_description { font-size: 11px; }

.card_characteristics dl { font-size: 11px;  align-items: start;}
.card_characteristics .dl_item {display: grid; grid-template-columns: 1fr 2fr;  gap: 5px; border-bottom: 1px dashed #999;}
.card_characteristics__dt {grid-column: 1; width: 100px; font-weight: bold; color: #555;}
.card_characteristics__dd {grid-column: 2; padding-bottom: 2px; margin-bottom: 0px;}
</style>

<?php
$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', [
    'depends' => [\app\assets\ChartAsset::class]
]);
?>
<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

<?php if (!empty($LastOrdersChartData)) {
    $LastOrdersJson = json_encode($LastOrdersChartData, JSON_NUMERIC_CHECK);
    echo $this->render('include/_linechart', [
        'timelineJson' => $LastOrdersJson,
    ]);
}
?>

<script>
var YearLineChart;
am5.ready(function() {

    // Создаем корневой элемент
    var YearLineRoot = am5.Root.new("yearline_div");
    YearLineRoot.locale = am5locales_ru_RU; 
    YearLineRoot.setThemes([am5themes_Animated.new(YearLineRoot)]);

    var chartData = <?= json_encode($ChartformattedData) ?>;

    // Создаем сам график
    YearLineChart = YearLineRoot.container.children.push(am5xy.XYChart.new(YearLineRoot, {
        panX: true,
        panY: true,
        layout: YearLineRoot.verticalLayout
    }));
    YearLineChart.set("height", 350);
/*
        wheelX: "panX",
        wheelY: "zoomX",
        pinchZoomX: true,
*/

    var xAxis = YearLineChart.xAxes.push(am5xy.DateAxis.new(YearLineRoot, {
        maxDeviation: 0.2,
        baseInterval: { timeUnit: "month", count: 1 },
        renderer: am5xy.AxisRendererX.new(YearLineRoot, {
            minGridDistance: 30
        }),
        tooltip: am5.Tooltip.new(YearLineRoot, {}),

        // 1. Убираем год из всплывающей подсказки на оси
        tooltipDateFormat: "MMMM", 

        // 2. Настраиваем форматы для самой оси
        dateFormats: {
            "month": "MMM" 
        },
        periodChangeDateFormats: {
            "month": "MMM" 
        }
    }));

    xAxis.get("renderer").labels.template.setAll({
        location: 0.5, // Центрируем метку по дню
        multiLocation: 0.5
    });

    xAxis.get("renderer").labels.template.setAll({
        rotation: -45, // ротация меток
        fontSize: "12px",
        centerY: am5.p50,
        centerX: am5.p100,
        paddingRight: 15
    });

    // Настройка оси Y (Продажи)
    var yAxis = YearLineChart.yAxes.push(am5xy.ValueAxis.new(YearLineRoot, {
       extraMax: 0.1,
        style: {
            fontSize: '10px'
        },
        min: 0,
        renderer: am5xy.AxisRendererY.new(YearLineRoot, {})
    }));

    var yearColors = {
        "2024": am5.color(0xB287F8), //6610f2), 
        "2025": am5.color(0xf965cf), 
        "2026": am5.color(0x007bff)     
    };

    // ЦИКЛ СОЗДАНИЯ СЕРИЙ ПО ГОДАМ
    Object.keys(chartData).forEach(function(year) {

        var color = yearColors[year] || YearLineChart.get("colors").getIndex(0);
        
        var series = YearLineChart.series.push(am5xy.LineSeries.new(YearLineRoot, {
            name: year,
            xAxis: xAxis,
            yAxis: yAxis,
            valueYField: "value",
            valueXField: "date",
            // Назначаем цвет из нашей карты (или берем стандартный, если года нет в списке)
            stroke: yearColors[year] || YearLineChart.get("colors").getIndex(0),
            fill:   yearColors[year] || YearLineChart.get("colors").getIndex(0),
            tooltip: am5.Tooltip.new(YearLineRoot, {
                labelText: "{name}: {valueY}"
            })
        }));

        series.fills.template.setAll({ 
            fillOpacity: 0, // Устанавливаем в 0, чтобы убрать заливку
            visible: false  // Для верности отключаем видимость
        });

        series.strokes.template.setAll({ strokeWidth: 2 });

        // Добавляем точки на график
        series.bullets.push(function() {
            return am5.Bullet.new(YearLineRoot, {
                sprite: am5.Circle.new(YearLineRoot, {
                    radius: 4,
                    fill: series.get("fill")
                })
            });
        });



        var bulletTemplate = series.bullets.push(function() {
            return am5.Bullet.new(YearLineRoot, {
                locationY: 1,
                sprite: am5.Label.new(YearLineRoot, {
                    text: "{valueY.formatNumber('#.#')}",
                    fill: color,
                    fontWeight: "bold",
                    fontSize: "12px",
                    centerX: am5.p50,
                    centerY: am5.p100,
                    dy: -12,
                    populateText: true,
                    paddingTop: 2, paddingBottom: 2, paddingLeft: 5, paddingRight: 5,
                    background: am5.RoundedRectangle.new(YearLineRoot, {
                        fill: am5.color(0xffffff),
                        stroke: color,
                        strokeWidth: 1.5,
                        cornerRadiusTL: 5, cornerRadiusTR: 5, cornerRadiusBL: 5, cornerRadiusBR: 5
                    })
                })
            });
        });
        series.set("labelBullet", bulletTemplate);



        // Загружаем данные года в серию
        series.data.setAll(chartData[year]);
        
        // Плавное появление
        series.appear(1000);
    });

    // 1. Убеждаемся, что график занимает доступное место, оставляя пространство под легенду
//    YearLineChart.set("height", am5.percent(90)); 
YearLineRoot.container.set("layout", am5.VerticalLayout.new(YearLineRoot, {}));
/*
    // 3. Создаем легенду
    var legend = YearLineRoot.container.children.push(am5.Legend.new(YearLineRoot, {
      centerX: am5.p50,
      x: am5.p50,
      paddingTop: 15,
      paddingBottom: 15,
      layout: YearLineRoot.horizontalLayout // Явно задаем горизонтальный лейаут
    }));
*/
/*
    var legend = YearLineRoot.container.children.push(am5.Legend.new(YearLineRoot, {
      x: am5.p50,      // Позиция: 50% от ширины родителя
      centerX: am5.p50, // Точка привязки самой легенды: её центр
      
      paddingTop: 15,
      paddingBottom: 15,
      layout: YearLineRoot.horizontalLayout 
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
*/

// 1. Создаем контейнер-обертку
var legendContainer = YearLineRoot.container.children.push(am5.Container.new(YearLineRoot, {
  width: am5.percent(100),
  layout: am5.GridLayout.new(YearLineRoot, {}),
  centerX: am5.p50,
  x: am5.p50
}));

// 2. Вставляем легенду в этот контейнер
var legend = legendContainer.children.push(am5.Legend.new(YearLineRoot, {
  centerX: am5.p50,
  x: am5.p50,
  layout: YearLineRoot.horizontalLayout
}));



    // 4. Передаем данные серий (ВАЖНО: делать это строго после того, как все серии созданы в цикле)
    legend.data.setAll(YearLineChart.series.values);

    YearLineChart.set("cursor", am5xy.XYCursor.new(YearLineRoot, {
        behavior: "zoomX",
        xAxis: xAxis
    }));

/*
    // Добавляем курсор (перекрестие)
    var cursor = YearLineChart.set("cursor", am5xy.XYCursor.new(YearLineRoot, {
        behavior: "zoomX"
    }));
    cursor.lineY.set("visible", false);
    
*/
    YearLineChart.appear(1000, 100);

}); // end am5.ready()



</script>





<style>
.orders_total {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
}
.orders_total div {width: 45%}
.orders_total  div:nth-child(even) {
    align-self: flex-end; 
    text-align: right;    
}
.panel_stats {
    background: #aed7ff;
    padding: 5px 10px;
    border: 1px solid rgb(74 128 180);
    border-radius: 10px;
}

.table-hover > tbody > tr.per_unit > td {
    --bs-table-accent-bg: transparent !important; 
    background-color: #fff9c4 !important;
    box-shadow: none !important; 
}
</style>
<style>
    #w19 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* уменьшаем кнопки в панеле  */
    #w19 .kv-panel-before { padding: 2px;}
    #w19-togdata-page, #w4-togdata-all {padding: 2px 5px;; font-size: 11px;}
    #w19-togdata-page .svg-inline--fa.fa-w-14 {width: 10px;}
    #w19-togdata-all .svg-inline--fa.fa-w-14 {width: 10px;}
/* размер заголовка  */
    #w19 .card-header { font-size: 11px; }
    #w19 .card-header h5 { font-size: 13px; }

    .grid_wbstat .table {  font-size: 12px; table-layout: fixed; width: 100%; overflow-x: auto; overflow-y: hidden; display: block; border-collapse: collapse;}
    .grid_wbstat .table td, .grid_wbstat .table th {  padding: 4px 4px !important; }
    .grid_wbstat .table th {text-align: center;}

    .paid-storage.expandable-container { max-height: 150px; }
    .paid-storage.expandable-container.is-expanded { max-height: 50000px;}
    .card-descr.expandable-container { max-height: 450px; }
    .card-descr.expandable-container.is-expanded { max-height: 50000px;}

</style>


