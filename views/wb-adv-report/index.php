<?php
use app\assets\AppAsset;
AppAsset::register($this);

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;

use yii\widgets\ActiveForm;

use kartik\grid\GridView;
use kartik\select2\Select2;
use kartik\date\DatePicker;
use kartik\daterange\DateRangePicker;
use kartik\icons\Icon;
Icon::map($this); 

$this->registerCssFile('https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined', [
    'depends' => [\app\assets\AppAsset::class],
]);

$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', [
    'depends' => [\app\assets\ChartAsset::class]
]);

$this->title = 'Аналитика рекламы WB';
?>
<?php
// Массив статусов с цветами Bootstrap
$statusMap = [
    -1 => ['label' => 'Удалена', 'class' => 'label-default'],
    4  => ['label' => 'Готова к запуску', 'class' => 'label-info'],
    7  => ['label' => 'Завершена', 'class' => 'label-primary'],
    8  => ['label' => 'Отклонена', 'class' => 'label-danger'],
    9  => ['label' => 'Активна', 'class' => 'label-success'],
    11 => ['label' => 'Пауза', 'class' => 'label-warning'],
];

// Массив типов
$typeMap = [
    4 => 'Каталог',
    5 => 'Карточка товара',
    6 => 'Поиск',
    7 => 'Рекомендации',
    8 => 'Автоматическая (old)',
    9 => 'Поиск + Каталог / Авто',
];
?>

<div class="wb-adv-report-index_head">
<?php if ($campaign): ?>
    <?= \app\components\PageHeaderWidget::widget(['title' => 'Компания: '.$campaign['name'],'nmId' => $campaign['campaign_id'] ]) ?>
<?php else: ?>
    <h1><?= Html::encode($this->title) ?></h1>
<?php endif; ?>

    <div class="row mb-3">
        <div class="col-md-6 card shadow p-3">
        <?php $form = ActiveForm::begin([
            'action' => ['index'],
            'method' => 'get',
            'options' => ['data-pjax' => true]
        ]); ?>

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Выберите кампанию</label>
                        <?= Select2::widget([
                            'name' => 'id',
                            'value' => $id,
                            'data' => $campaignList,
                            'options' => ['placeholder' => 'Поиск по ID или названию...'],
                            'pluginOptions' => [
                                'allowClear' => true
                            ],
                        ]); ?>
                    </div>
                </div>
            </div>


            <div class="row" style="display: flex;align-content: flex-end;flex-direction: row; align-items: flex-end;">
                <div class="form-group form__input-dates col-md-6" style="margin-bottom: 8px;">
                    <label class="control-label">Период с / по</label>
                    <?= DatePicker::widget([
                        'name' => 'date_from',
                        'value' => $dateFrom,
                        'name2' => 'date_to',
                        'value2' => $dateTo,
                        'type' => DatePicker::TYPE_RANGE,
                        'separator' => ' | ',
                        'options' => [
                            'placeholder' => 'Дата начала',
                            'style' => 'height: 38px;',
                            'id' => 'date_from',
                        ],
                        'options2' => [
                            'placeholder' => 'Дата окончания',
                            'style' => 'height: 38px;',
                            'id' => 'date_to',
                        ],
                        'pluginOptions' => [
                            'autoclose' => true,
                            'format' => 'yyyy-mm-dd',
                            'orientation' => 'bottom auto',
                            'todayHighlight' => true
                        ]
                    ]) ?>
                </div>
                <div class="btn-group col-md-6" style="height: 38px; margin-bottom: 8px;">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('quarter')" title="Минус квартал">-Q</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('year')" title="Минус год">-Y</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('last_year')" title="Прошлый год">LY</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('today')" title="По сегодня">TD</button>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <?= Html::submitButton('Показать данные', ['class' => 'btn btn-primary btn-block']) ?>
                </div>
            </div>
        <?php ActiveForm::end(); ?>
        </div> 

        <div class="col-md-6 ms-0 me-0 p-0">
        <?php if ($campaign): ?>
            <div class="card shadow p-3 ms-3 me-0">
                    <div class="panel panel-info">
                        <div class="panel-heading">
                                Кампания: <b><?= Html::encode($campaign->name) ?></b> ID: <a href="https://cmp.wildberries.ru/campaigns/edit/<?= $campaign->campaign_id ?>" target="_blank"><b><?= $campaign->campaign_id ?></b></a>
                        </div>
                        <div class="panel-body font_11px grey">
                                Статус: <b>
                                    <?php 
                                    $currStatus = $statusMap[$campaign->status] ?? ['label' => 'Неизвестно', 'class' => 'label-default'];
                                    echo Html::tag('span', $currStatus['label'], ['class' => 'label ' . $currStatus['class']]);
                                    ?> </b>
                                Тип: <b><?= $typeMap[$campaign->type] ?? "Код {$campaign->type}" ?> </b>
                                Бюджет: <b><?= number_format($campaign->daily_budget, 0, '.', ' ') ?> ₽ </b>
                                Изменена: <b><?= Yii::$app->formatter->asDatetime($campaign->change_time, 'short') ?></b>
                        </div>
                    </div>
                    <div class="alert alert-default font_13px" style="background: #f1f1f1; border-left: 5px solid #337ab7; margin: 10px 0px 0px;">
                        <strong>Товары в кампании</strong>
                        <div style="margin-top: 10px;"><ul>
                            <?php foreach ($items as $item): ?>
                                <li><a href="/wb-get-sales-funnel/wbcard?nmId=<?=Html::encode($item['nm_id'])?>" target="_blank"><?= Html::encode($item['nm_id']) ?></a> | <?= Html::encode($item['card_name'] ?: 'Без названия') ?> | <?= Html::encode($item['vendorCode']) ?> </li>
                            <?php endforeach; ?>
                        </ul></div>
                    </div>
            </div>
        <?php else: ?>
            <div class="row m-3 alert alert-warning">
                Выберите кампанию и период, чтобы увидеть аналитику.
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>


<div class="row mb-3">
<?php if ($campaign): ?>
    <div class="col-md-7 m-0 p-0 div-chart" id="LinechartDiv">
        <div class="card m-0 p-0 panel panel-default"> 
            <div class="panel-heading d-flex align-items-center p-3" style="position: relative; justify-content: center; min-height: 40px;">
                <span class="mx-auto"><b>Расходы и показатели компании</b></span>

                <div class="btn-group btn-group-sm mb-2"  role="group" id="timeline_buttons">
                    <button type="button" class="btn btn-outline-secondary" onclick="setGraphIntervalTL('day', this)"         data-unit="day" title='По дням'>D</button>
                    <button type="button" class="btn btn-outline-secondary active" onclick="setGraphIntervalTL('week', this)" data-unit="week" title='По неделям'>W</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setGraphIntervalTL('month', this)"       data-unit="month" title='По месяцам'>M</button>
                    &nbsp 
                    <button type="button" class="btn btn-secondary active" onclick="toggleLabels(Linechart, this); return false;" title='Подписи'><i class="bi bi-tag"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleWidth(this); return false;" title="Изменить ширину" data-bs-toggle="tooltip"><i class="fas fa-arrows-alt-h"></i></button>
                </div>
            </div>
            <div class="panel-body">
                <div id="timeline_div" style="width: 100%; height: 400px;"></div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php if (!empty($ChartAppStats)): ?>
    <div class="col-md-5 m-0 p-0 div-chart">
        <div class="card ms-3 me-0 p-0 panel panel-default">
            <div class="panel-heading d-flex align-items-center p-3" style="position: relative; justify-content: center; min-height: 40px;">
                <span class="mx-auto"><b>Устр-ва: заказы, корзины, показы</b></span>
                <div class="btn-group  btn-group-sm mb-2" role="group" id="stacked_column_buttons">
                    <button type="button" class="btn btn-outline-secondary sc-btn" onclick="setStackedColumnInterval('day', this)"   data-unit="day" title='По дням'>D</button>
                    <button type="button" class="btn btn-outline-secondary sc-btn" onclick="setStackedColumnInterval('week', this)"  data-unit="week" title='По неделям'>W</button>
                    <button type="button" class="btn btn-outline-secondary sc-btn" onclick="setStackedColumnInterval('month', this)" data-unit="month" title='По месяцам'>M</button>
                    &nbsp 
                    <button type="button" class="btn btn-secondary active"  onclick="toggleLabels(AppChart, this); return false;" title='Подписи'><i class="bi bi-tag"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleWidth(this); return false;" title="Изменить ширину" data-bs-toggle="tooltip"><i class="fas fa-arrows-alt-h"></i></button>
                </div>
            </div>
            <div class="panel-body">
                <div id="stacked_column_div" style="width: 100%; height: 400px;"></div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>


<div class="row mb-3">
<?php if ($campaign): ?>
    <div class="col-md-6 m-0 p-0 div-chart" id="CPMLinechartDiv">
        <div class="card m-0 p-0 panel panel-default">
            <div class="panel-heading d-flex align-items-center p-3" style="position: relative; justify-content: center; min-height: 40px;">
                <span class="mx-auto"><b>Показатели: CPM, CPO</b></span>

                <div class="btn-group btn-group-sm mb-2"  role="group" id="cpmtimeline_buttons">
                    <button type="button" class="btn btn-outline-secondary" onclick="setGraphIntervalCPM('day', this)"         data-unit="day" title='По дням'>D</button>
                    <button type="button" class="btn btn-outline-secondary active" onclick="setGraphIntervalCPM('week', this)" data-unit="week" title='По неделям'>W</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setGraphIntervalCPM('month', this)"       data-unit="month" title='По месяцам'>M</button>
                    &nbsp 
                    <button type="button" class="btn btn-secondary active" onclick="toggleLabels(CPMLinechart, this); return false;" title='Подписи'><i class="bi bi-tag"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleWidth(this); return false;" title="Изменить ширину" data-bs-toggle="tooltip"><i class="fas fa-arrows-alt-h"></i></button>
                </div>
            </div>
            <div class="panel-body">
                <div id="cpmtimeline_div" style="width: 100%; height: 400px;"></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 m-0 p-0 div-chart" id="CPMLinechartDiv">
        <div class="card ms-3 me-0 p-0 panel panel-default">
            <div class="panel-heading d-flex align-items-center p-3" style="position: relative; justify-content: center; min-height: 40px;">
                <span class="mx-auto"><b>Показатели: CTR, CR</b></span>

                <div class="btn-group btn-group-sm mb-2"  role="group" id="ctrtimeline_buttons">
                    <button type="button" class="btn btn-outline-secondary" onclick="setGraphIntervalCTR('day', this)"         data-unit="day" title='По дням'>D</button>
                    <button type="button" class="btn btn-outline-secondary active" onclick="setGraphIntervalCTR('week', this)" data-unit="week" title='По неделям'>W</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setGraphIntervalCTR('month', this)"       data-unit="month" title='По месяцам'>M</button>
                    &nbsp 
                    <button type="button" class="btn btn-secondary active" onclick="toggleLabels(CTRLinechart, this); return false;" title='Подписи'><i class="bi bi-tag"></i></button>
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleWidth(this); return false;" title="Изменить ширину" data-bs-toggle="tooltip"><i class="fas fa-arrows-alt-h"></i></button>
                </div>
            </div>
            <div class="panel-body">
                <div id="ctrtimeline_div" style="width: 100%; height: 400px;"></div>
            </div>
        </div>
    </div>

<?php endif; ?>
</div>

<?php if ($statsProvider): ?> 
    <?php if ($statsProvider->totalCount > 0): ?>
    <?php
        // Считаем итоги для всей выборки $stats
        $allStats = $statsProvider->allModels;
        $totalViews  = array_sum(array_column($allStats, 'views'));
        $totalClicks = array_sum(array_column($allStats, 'clicks'));
        $totalATBs   = array_sum(array_column($allStats, 'atbs'));

        $totalSum    = array_sum(array_column($allStats, 'sum'));
        $totalOrders = array_sum(array_column($allStats, 'orders'));
        $totalCanceled = array_sum(array_column($allStats, 'canceled'));
        $totalOC = $totalOrders - $totalCanceled;

        $totalCtr = $totalViews  > 0 ? number_format(($totalClicks / $totalViews)  * 100, 2) . '%' : '0.00%';
        $totalCR  = $totalClicks > 0 ? number_format(($totalATBs   / $totalClicks) * 100, 2) . '%' : '0.00%'; // $model['atbs']  / $model['clicks']

        $totalCPM = $totalOrders > 0 ? number_format(($totalSum / $totalViews * 1000),2) : '';
        $totalCPC = $totalOrders > 0 ? number_format(($totalSum / $totalClicks),2) : '';
        $totalCPO = $totalOC > 0 ? number_format(($totalSum / $totalOC),2) : '';
    ?>
    <?php
    $column_date =[
         [
            'attribute' => 'date',
            'label' => 'Дата',
            'pageSummary' => 'ИТОГО:',
                'format' => ['datetime', 'php:d.m.Y'],
                'headerOptions'  => ['style' => 'width:130px; text-align: center;'],
                'contentOptions' => ['style' => 'width:130px; white-space: nowrap; align-content: center; text-align: center;'],

        ]];
    $columns = [
            [
                'attribute' => 'nm_id',
                'label' => 'Арт WB',
                'format' => 'raw', 
                'headerOptions'  => ['style' => 'width:90px; text-align: center;'],
                'contentOptions' => ['style' => 'width:90px; white-space: nowrap; align-content: center; text-align: center;'],
                'value' => function($model) {
                    if (!$model['nm_id']) {
                        return null;
                    }
                    // Генерируем ссылку
                    return Html::a(
                        (string)$model['nm_id'], 
//                        "https://www.wildberries.ru/catalog/" . $model['nm_id'] . "/detail.aspx", 
                        "/wb/detail?nm_id=". $model['nm_id'],
                        [
                            'target' => '_blank',
                            'data-pjax' => '0', 
                            'style' => 'text-decoration: none;'
                        ]
                    );
                },

            ],
            [
                    'attribute' => 'cardTitle',
                    'label' => 'Товар / Артикул',
                    'headerOptions'  => ['style' => 'width:420px'],
                    'contentOptions' => ['style' => 'min-width:420px; white-space: wrap; '],
                    'format' => 'raw',
                    'value' => function($model) {
                        // Верхний уровень: Название товара
                        $title = Html::tag('div', $model['title'] ?? '—', [
                            'style' => 'font-weight: bold; font-size: 13px; margin-bottom: 8px; color: #2c3e50;'
                        ]);
                        $details = Html::tag('div', 
                            "Артикул: <b>{$model['vendorCode']}</b>" ,
                            ['style' => 'color: #666; font-size: 11px;']
                        );

                        return $title . $details;
                    },
            ],
                [
                    'attribute' => 'views',
                    'label' => 'Показы',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true, // Просто сумма
                ],
                [
                    'attribute' => 'clicks',
                    'label' => 'Клики',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true, // Просто сумма
                ],
                [
                    'attribute' => 'atbs',
                    'label' => 'Корзины',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true, // Просто сумма
                ],
                [
                    'attribute' => 'orders',
                    'label' => 'Заказы',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'canceled',
                    'label' => 'Отмена',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                ],

                [
                    'attribute' => 'ctr',
                    'label' => 'CTR, %',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'value' => function ($model) {
                        return $model['views'] > 0 ? round(($model['clicks']  / $model['views']) * 100, 2) : '';
        //                return number_format($model['ctr'], 2) . '%';
                    },
                    'hAlign' => 'right',
                    'pageSummary' => $totalCtr, // Используем заранее вычисленное значение
                ],
                [
                    'attribute' => 'cr',
                    'label' => 'CR, %',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'value' => function ($model) {
                        return $model['clicks'] > 0 ? round(($model['atbs']  / $model['clicks']) * 100, 2) : '';
        //                return number_format($model['cr'], 2) . '%';
                    },
                    'hAlign' => 'right',
                    'pageSummary' => $totalCR, // Используем заранее вычисленное значение
                ],
                [
                    'attribute' => 'sum',
                    'label' => 'Затраты, ₽',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],

                ],
                [
                    'label' => 'CPM, ₽', 
                    'format' => ['decimal', 2],
                    'pageSummary' => $totalCPM, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model['views'] > 0 ? round(($model['sum']  / $model['views'])*1000, 2) : '';
                    }
                ],
                [
                    'label' => 'CPC, ₽', 
                    'format' => ['decimal', 2],
                    'pageSummary' => $totalCPC, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model['clicks'] > 0 ? round(($model['sum']  / $model['clicks']), 2) : '';
                    }
                ],
                [
                    'label' => 'CPO ₽', 
                    'format' => ['decimal', 2],
                    'pageSummary' => $totalCPO, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model['orders'] > 0 ? round(($model['sum']  / $model['orders'] ), 2) : '';
                    }
                ],
    ];

    $columnsAG = [
            [
                'attribute' => 'nm_id',
                'label' => 'Арт WB',
                'format' => 'raw', 
                'headerOptions'  => ['style' => 'width:90px; text-align: center;'],
                'contentOptions' => ['style' => 'width:90px; white-space: nowrap; align-content: center; text-align: center;'],
                'value' => function($model) {
                    if (!$model['nm_id']) {
                        return null;
                    }
                    // Генерируем ссылку
                    return Html::a(
                        (string)$model['nm_id'], 
//                        "https://www.wildberries.ru/catalog/" . $model['nm_id'] . "/detail.aspx", 
                        "/wb/detail?nm_id=". $model['nm_id'],
                        [
                            'target' => '_blank',
                            'data-pjax' => '0', 
                            'style' => 'text-decoration: none;'
                        ]
                    );
                },

            ],
            [
                    'attribute' => 'cardTitle',
                    'label' => 'Товар / Артикул',
                    'headerOptions'  => ['style' => 'width:420px'],
                    'contentOptions' => ['style' => 'min-width:420px; white-space: wrap; '],
                    'format' => 'raw',
                    'value' => function($model) {
                        // Верхний уровень: Название товара
                        $title = Html::tag('div', $model['title'] ?? '—', [
                            'style' => 'font-weight: bold; font-size: 13px; margin-bottom: 8px; color: #2c3e50;'
                        ]);
                        $details = Html::tag('div', 
                            "Артикул: <b>{$model['vendorCode']}</b>" ,
                            ['style' => 'color: #666; font-size: 11px;']
                        );

                        return $title . $details;
                    },
            ],

                [
                    'attribute' => 'atbs',
                    'label' => 'Корзины',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true, // Просто сумма
                ],
                [
                    'attribute' => 'orders',
                    'label' => 'Заказы',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'canceled',
                    'label' => 'Отмена',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                ],

                [
                    'attribute' => 'sum_price',
                    'label' => 'Сумма, ₽', 
                    'format' => ['decimal', 2],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'pageSummary' => true, 'pageSummaryOptions' => ['class' => 'text-right'],
//                    'pageSummary' => GridView::F_AVG, 
                ],
    ];
    ?>
    <hr>
    <div class="row mb-3 custom-compact-grid">
        <div class="wb-adv-report-index wb-adv-report-index__short rounded ">
        <?php
        echo GridView::widget([
            'dataProvider' => $ShortStatsProvider,
                'export' => false, 
                'toggleData' => false,
                'pjax' => true,
                'bordered' => true,
                'striped' => true,
                'condensed' => true,
                'responsive' => true,
                'hover' => true,
                'showPageSummary' => true,
                'showFooter' => false,
                'panel' => [
                    'type' => GridView::TYPE_PRIMARY,
                    'heading' => 'Сводные показатели',
                    'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                    'footer' => false,
                ],
                'containerOptions' => [
                    'class' => 'no-border-class' 
                ],

            'columns' => $columns,
        ]);
        ?>
        </div>

        <div class="wb-adv-report-index wb-adv-report-index__another expandable-container rounded ">
        <?php
        echo GridView::widget([
            'dataProvider' => $AnotherGoodsProvider,
                'export' => false, 
                'toggleData' => true,
                'pjax' => true,
                'bordered' => true,
                'striped' => true,
                'condensed' => true,
                'responsive' => true,
                'hover' => true,
                'showPageSummary' => true,
                'pageSummaryPosition' => GridView::POS_TOP, 
                'showFooter' => false,
                'panel' => [
                    'type' => GridView::TYPE_PRIMARY,
                    'heading' => 'Другие товары в заказах',
                    'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                    'footer' => false,
                ],
                'containerOptions' => [
                    'class' => 'no-border-class' 
                ],

            'columns' => $columnsAG,
        ]);
        ?>
        </div>

        <div class="expand-btn-wrapper">
            <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
        </div>
    </div>

    <div class="row custom-compact-grid ">
        <div class="wb-adv-report-index">
        <?php
            $result = array_merge($column_date, $columns);
            echo GridView::widget([
                'dataProvider' => $statsProvider,
                    'pjax' => true,
                    'bordered' => true,
                    'striped' => true,
                    'condensed' => true,
                    'responsive' => true,
                    'hover' => true,
                    'showPageSummary' => true,
                    'panel' => [
                        'type' => GridView::TYPE_PRIMARY,
                        'heading' => 'Общая статистика по дням',
                        'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                        'footer' => false,
                    ],
                    'containerOptions' => [
                        'class' => 'no-border-class' 
                    ],

                'columns' => $result,
            ]);
        ?>
        </div>
    </div>

    <?php endif; ?>
<?php endif; ?>


<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>

<?php if (!empty($ChartStats)) {
    $timelineJson = json_encode($ChartStats, JSON_NUMERIC_CHECK);
    echo $this->render('_linechart', [
        'timelineJson' => $timelineJson,
    ]);
}
?>
<?php if (!empty($ChartAppStats)) {
//    $statsJson = json_encode($ChartAppStats, JSON_NUMERIC_CHECK);
    $stats = $ChartAppStats;

    $flatData = [];
    $types = [32, 64, 1]; 
    $metrics = ['clicks', 'orders', 'atbs', 'views', 'sum_price'];

    foreach ($stats as $row) {
        $date = $row['date'];
        $type = $row['app_type'];

        // 1. Если даты еще нет в массиве, создаем шаблон со всеми нулями
        if (!isset($flatData[$date])) {
            $flatData[$date] = ['date' => $date];
            foreach ($types as $t) {
                foreach ($metrics as $metric) {
                    $flatData[$date]["{$metric}_{$t}"] = 0;
                }
            }
        }

        // 2. Записываем реальные данные поверх нулей
        $flatData[$date]["clicks_{$type}"] = (int)$row['clicks'];
        $flatData[$date]["orders_{$type}"] = (int)$row['orders'];
        $flatData[$date]["atbs_{$type}"]   = (int)$row['atbs'];
        $flatData[$date]["views_{$type}"]  = (int)$row['views'];
        $flatData[$date]["sum_price_{$type}"] = (float)$row['sum_price'];
    }

    $statsJson = Json::encode(array_values($flatData));

    echo $this->render('_appchart', [
        'statsJson' => $statsJson,
    ]);
}
?>



<style>
    #w5 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* уменьшаем кнопки в панеле  */
    #w5 .kv-panel-before { padding: 2px;}
    #w5-togdata-page {padding: 2px 5px;; font-size: 11px; margin: 0px 5px;}
    #w5-togdata-page .svg-inline--fa.fa-w-14 {width: 10px;}
    #w5-togdata-all  {padding: 2px 5px;; font-size: 11px; margin: 0px 5px;}
    #w5-togdata-all .svg-inline--fa.fa-w-14 {width: 10px;}
/* размер заголовка  */
    #w5 .card-header { font-size: 11px; }
    #w5 .card-header h5 { font-size: 13px; }
    #w6-button {padding: 2px 5px;; font-size: 11px; margin: 0px 5px;}
</style>
<style>
    #w7 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* уменьшаем кнопки в панеле  */
    #w7 .kv-panel-before { padding: 2px;}
    #w7-togdata-page {padding: 2px 5px;; font-size: 11px; margin: 0px 5px;}
    #w7-togdata-page .svg-inline--fa.fa-w-14 {width: 10px;}
    #w7-togdata-all  {padding: 2px 5px;; font-size: 11px; margin: 0px 5px;}
    #w7-togdata-all .svg-inline--fa.fa-w-14 {width: 10px;}
/* размер заголовка  */
    #w7 .card-header { font-size: 11px; }
    #w7 .card-header h5 { font-size: 13px; }
    #w8-button {padding: 2px 5px;; font-size: 11px; margin: 0px 5px;}
</style>


<style>
    .wb-adv-report-index .table {  font-size: 12px; table-layout: fixed; width: 100%; overflow-x: auto; overflow-y: hidden;
        display: block; border-collapse: collapse;}
    .wb-adv-report-index .table td, .wb-order-index .table th {  padding: 4px 8px !important; }
    .wb-adv-report-index input {  font-size: 12px;  }
    .wb-adv-report-index .input-group-text {padding: 4px;}
    .svg-inline--fa.fa-w-14, .svg-inline--fa.fa-w-11 { width: 10px; }
    .wb-order-index #wbordersearch-date {padding: 2px;}
    input.form-control { padding: 2px; text-align: center;}
    #w0-filters td { padding: 2px 3px !important; }
    .form-select { padding: 2px 3px !important; font-size: 12px; text-align: center; background-position: right 5px center;}
    .wb-adv-report-index {margin-bottom: 20px;}
    .form__input-dates {    display: flex; flex-direction: column; justify-content: flex-start; flex-wrap: nowrap;}

    .kv-align-right { white-space: nowrap; align-content: center; text-align: right;}
    #main a {text-decoration: none };
</style>

<style>
#w3 .border-primary, #w5 .border-primary {
    border-color: var(--bs-border-color-translucent) !important;
}
</style>





<?php if (!empty($ChartStats)) {
    $CPMtimelineJson = json_encode($ChartStats, JSON_NUMERIC_CHECK);
    echo $this->render('_cpm-linechart', [
        'timelineJson' => $CPMtimelineJson,
    ]);

    $CTRtimelineJson = json_encode($ChartStats, JSON_NUMERIC_CHECK);
    echo $this->render('_ctr-linechart', [
        'timelineJson' => $CTRtimelineJson,
    ]);
}

?>