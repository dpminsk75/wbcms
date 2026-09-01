<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use app\components\getDPWidget;

use kartik\icons\Icon;
Icon::map($this);

/** @var yii\web\View $this */
/** @var app\models\DPFilterForm $filterModel */
/** @var app\models\WbOrderFeedAggregatedSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Сводка по товарам (заказы)';
?>
<?php
$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', [
    'depends' => [\app\assets\ChartAsset::class]
]);
?>
<h1><?= Html::encode($this->title) ?></h1>
<div class="row"><div class="col-md-6 mb-3">
<?= getDPWidget::widget([
    'action' => ['wb-order/feed-aggregated'],
    'defaultDateFrom' => date('Y-m-d', strtotime('-6 days')),
    'defaultDateTo' => date('Y-m-d'),
]) ?>
</div>
<div class="col-md-3 mb-3">
    <?php // Сортировка — отдельная мини-форма, чтобы сохранить текущие фильтры ?>
    <form method="get" action="/wb-order/feed-aggregated" class="d-flex align-items-end gap-2" style="height:100%;">
        <input type="hidden" name="DPFilterForm[nm_id]" value="<?= Html::encode($filterModel->nm_id) ?>">
        <input type="hidden" name="DPFilterForm[date_from]" value="<?= Html::encode($filterModel->date_from) ?>">
        <input type="hidden" name="DPFilterForm[date_to]" value="<?= Html::encode($filterModel->date_to) ?>">
        <div class="flex-grow-1">
            <label class="form-label mb-1" style="font-size:12px;">Сортировка</label>
            <?= Html::dropDownList('WbOrderFeedAggregatedSearch[sort_by]', $searchModel->sort_by, $searchModel::getSortOptions(), [
                'class' => 'form-control',
                'onchange' => 'this.form.submit()',
            ]) ?>
        </div>
        <noscript><button class="btn btn-primary">OK</button></noscript>
    </form>
</div>
</div>

<?php $stats = $searchModel->getSummaryStats(); ?>
<div class="grid_orderfeed_summary">
    <div class="summary-card">
        <div class="summary-label">Количество заказов</div>
        <div class="summary-value"><?= number_format($stats['count'], 0, ',', ' ') ?></div>
    </div>
    <div class="summary-card">
        <div class="summary-label">Из них</div>
        <div class="summary-split">
            <span class="summary-split-item">
                <b><?= number_format($stats['fbs_count'], 0, ',', ' ') ?></b>
                <span class="mini-badge fbs">FBS</span>
            </span>
            <span class="summary-split-item">
                <b><?= number_format($stats['fbo_count'], 0, ',', ' ') ?></b>
                <span class="mini-badge fbo">FBO</span>
            </span>
        </div>
    </div>
    <div class="summary-card">
        <div class="summary-label">Сумма</div>
        <div class="summary-value"><?= number_format($stats['sum'], 2, ',', ' ') ?> ₽</div>
    </div>
    <div class="summary-card">
        <div class="summary-label">СПП (среднее)</div>
        <div class="summary-value"><?= number_format($stats['avg_spp'], 1, ',', ' ') ?>%</div>
    </div>
</div>
<div class="row mb-3">
<?= $this->render('_order_funnel_chart', [
    'funnelStats' => $funnelStats ?? $searchModel->getFunnelStats(),
    'chartData' => $chartData ?? $searchModel->getDailyStatusChartData(),
]) ?>
</div>
<?php
$fmt = function ($value, $dec = 2, $suffix = '') {
    if ($value === null || $value === '') {
        return '<span class="text-muted">—</span>';
    }
    return Html::encode(number_format((float)$value, $dec, ',', ' ')) . $suffix;
};

$columns = [

    // ---- Карточка товара (фото + название + артикулы) ----
    [
        'label' => 'Товар',
        'format' => 'raw',
        'filter' => '',
        'headerOptions' => ['style' => 'width:280px;'],
        'contentOptions' => ['style' => 'width:280px;'],
        'value' => function ($row) {
            $photos = [];
            if (!empty($row['card_photos'])) {
                $decoded = json_decode($row['card_photos'], true);
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                if (is_array($decoded)) {
                    $photos = $decoded;
                }
            }
            $img = !empty($photos[0]) ? $photos[0] : '/images/no-photo.png';

            $photoTag = Html::img($img, [
                'style' => 'width:50px; height:66px; object-fit:cover; border-radius:4px; flex-shrink:0;',
                'alt' => '',
            ]);

            $title = Html::tag('div', Html::encode($row['card_title'] ?: '(нет карточки)'), [
                'class' => 'cart-item-title',
                'style' => 'white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 210px;',
                'title' => Html::encode($row['card_title'] ?? ''),
            ]);

            $breadcrumb = Html::tag('div', Html::encode($row['card_subject_name']) . ' • ' . Html::encode($row['card_brand']), [
                'class' => 'cart-item-details',
            ]);

            $vendor = Html::tag('div', Html::encode($row['card_vendor_code']), [
                'class' => 'cart-item-details',
            ]);

            $wbLink = Html::a('WB: ' . Html::encode($row['nm_id']), '/wb/detail?DPFilterForm[nm_id]=' . $row['nm_id'], [
                'title' => 'Перейти в карточку',
                'target' => '_blank',
                'data-pjax' => '0',
                'style' => 'text-decoration: none;',
            ]);
            $wb = Html::tag('div', $wbLink, ['class' => 'cart-item-details']);

            $textBlock = Html::tag('div', $title . $breadcrumb . $vendor . $wb, ['style' => 'min-width:0;']);

            return Html::tag('div', $photoTag . $textBlock, ['style' => 'display:flex; gap:10px; align-items:flex-start;']);
        },
    ],

    // ---- Количество ----
    [
        'attribute' => 'orders_cnt',
        'label' => 'Заказов',
        'hAlign' => 'right',
        'filter' => '',
        'contentOptions' => ['style' => 'text-align:right; font-weight:bold;'],
    ],
    [
        'attribute' => 'cancelled_cnt',
        'label' => 'Отмен',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;'],
        'value' => function ($row) {
            $cnt = (int)$row['cancelled_cnt'];
            return $cnt > 0
                ? '<span style="color:#c0392b;">' . $cnt . '</span>'
                : '<span class="text-muted">0</span>';
        },
    ],

    // ---- Цены ----
    [
        'attribute' => 'avg_total_price',
        'label' => 'Цена в кар-ке (ср.)',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;'],
        'value' => fn($row) => $fmt($row['avg_total_price']),
    ],
    [
        'attribute' => 'avg_discount',
        'label' => 'Скидка, % (ср.)',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;'],
        'value' => fn($row) => $fmt($row['avg_discount'], 1),
    ],
    [
        'attribute' => 'sum_price_with_disc',
        'label' => 'Общая сумма',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'contentOptions' => ['style' => 'text-align:right; font-weight:bold; white-space: nowrap;'],
        'value' => fn($row) => $fmt($row['sum_price_with_disc'], 2, ' ₽'),
    ],
    [
        'attribute' => 'avg_price_with_disc',
        'label' => 'Цена со скидкой (ср.)',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;'],
        'value' => fn($row) => $fmt($row['avg_price_with_disc']),
    ],
    [
        'attribute' => 'avg_spp',
        'label' => 'СПП (ср.)',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;'],
        'value' => fn($row) => $fmt($row['avg_spp'], 1, '%'),
    ],
    [
        'attribute' => 'sum_finished',
        'label' => 'Сумма продаж',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'contentOptions' => ['style' => 'text-align:right; white-space: nowrap;'],
        'value' => fn($row) => $fmt($row['sum_finished'], 2, ' ₽'),
    ],
    [
        'attribute' => 'avg_finished',
        'label' => 'Цена продажи (ср.)',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'contentOptions' => ['style' => 'text-align:right;'],
        'value' => fn($row) => $fmt($row['avg_finished']),
    ],

    // ---- Финансы (факты + прогнозы) — расчётные, курсивом ----
    [
        'label' => 'Комиссия',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right; white-space: nowrap;'],
        'value' => function ($row) {
            $sum = $row['sum_commission'];
            $pct = $row['avg_commission_pct'];
            if ($sum === null && $pct === null) return '<span class="text-muted">—</span>';
            $sumStr = $sum !== null ? number_format((float)$sum, 2, ',', ' ') . ' ₽' : '—';
            $pctStr = $pct !== null ? number_format((float)$pct, 1, ',', ' ') . '%' : '';
            return '<i style="color:#888;">' . Html::encode($sumStr) . '</i><br><span style="font-size:11px; color:#aaa;"><i>' . Html::encode($pctStr) . '</i></span>';
        },
    ],
    [
        'label' => 'Эквайринг',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right; white-space: nowrap;'],
        'value' => function ($row) {
            $sum = $row['sum_acquiring'];
            $pct = $row['avg_acquiring_pct'];
            if ($sum === null && $pct === null) return '<span class="text-muted">—</span>';
            $sumStr = $sum !== null ? number_format((float)$sum, 2, ',', ' ') . ' ₽' : '—';
            $pctStr = $pct !== null ? number_format((float)$pct, 1, ',', ' ') . '%' : '';
            return '<i style="color:#888;">' . Html::encode($sumStr) . '</i><br><span style="font-size:11px; color:#aaa;"><i>' . Html::encode($pctStr) . '</i></span>';
        },
    ],
    [
        'attribute' => 'sum_delivery',
        'label' => 'Логистика (сумма)',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'contentOptions' => ['style' => 'text-align:right; white-space: nowrap;'],
        'value' => function ($row) {
            $v = $row['sum_delivery'];
            if ($v === null || $v === '') return '<span class="text-muted">—</span>';
            return '<i style="color:#888;">' . Html::encode(number_format((float)$v, 2, ',', ' ')) . ' ₽</i>';
        },
    ],
    [
        'attribute' => 'avg_delivery',
        'label' => 'Логистика (ср.)',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right; white-space: nowrap;'],
        'value' => function ($row) {
            $v = $row['avg_delivery'];
            if ($v === null || $v === '') return '<span class="text-muted">—</span>';
            return '<i style="color:#888;">' . Html::encode(number_format((float)$v, 2, ',', ' ')) . ' ₽</i>';
        },
    ],
];
?>
<div class="row grid_orderfeed grid_wbstat grid_no_kv-panel-before">
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'export' => false,
    'pjax' => true,
    'bordered' => true,
    'striped' => true,
    'condensed' => true,
    'responsive' => true,
    'hover' => true,
    'panel' => [
        'type' => GridView::TYPE_PRIMARY,
        'heading' => 'Сводка по товарам (' . Yii::$app->formatter->asDate($filterModel->date_from, 'd MMM y')
            . ($filterModel->date_from !== $filterModel->date_to ? ' — ' . Yii::$app->formatter->asDate($filterModel->date_to, 'd MMM y') : '')
            . ')',
        'headingOptions' => ['class' => 'card-header text-white bg-wb'],
        'after' => false,
    ],
    'containerOptions' => ['class' => 'no-border-class'],
    'emptyCell' => '—',
    'columns' => $columns,
]) ?>
</div>

<style>
/* Сводка над таблицей (Количество/FBS-FBO/Сумма/СПП) */
.grid_orderfeed_summary {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin: 16px 0 24px;
}
.grid_orderfeed_summary .summary-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 16px 22px;
    min-width: 160px;
    flex: 1 1 160px;
    transition: box-shadow .15s ease, border-color .15s ease;
}
.grid_orderfeed_summary .summary-card:hover {
    border-color: #d1d5db;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}
.grid_orderfeed_summary .summary-label {
    font-size: 12px;
    color: #8a8f98;
    margin-bottom: 8px;
}
.grid_orderfeed_summary .summary-value {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
    white-space: nowrap;
}
.grid_orderfeed_summary .summary-split {
    display: flex;
    gap: 16px;
    margin-top: 4px;
}
.grid_orderfeed_summary .summary-split-item {
    font-size: 20px;
    color: #1f2937;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.grid_orderfeed_summary .mini-badge {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 8px;
    letter-spacing: .02em;
    margin-top: 4px;
}
.grid_orderfeed_summary .mini-badge.fbs { background: #fef3c7; color: #d97706; }
.grid_orderfeed_summary .mini-badge.fbo { background: #ffedd5; color: #ea580c; }

@media (max-width: 767px) {
    .grid_orderfeed_summary .summary-card {
        flex: 1 1 45%;
        min-width: 0;
    }
}

.grid_orderfeed .summary { margin-right: 70px; }
.grid_orderfeed .form-select {
    padding: 3px;
    font-size: 12px;
    width: 92%;
    margin: 0px auto;
}
.grid_orderfeed .cart-item-title {
    font-weight: bold;
    color: #2c3e50;
    font-size: 13px;
}
.grid_orderfeed .cart-item-details {
    color: #666;
    font-size: 11px;
}
.grid_orderfeed td:not(:first-child) {
    font-size: 12px !important;
    vertical-align: middle;
}
.grid_orderfeed td:nth-child(2), .grid_orderfeed td:nth-child(3), .grid_orderfeed td:nth-child(4)
{
    text-align: center;
}
.grid_orderfeed th {
    white-space: normal !important;
    word-break: break-word;
    font-weight: 500 !important;
    color: #444;
    text-align: center;
    vertical-align: middle;
}
.grid_orderfeed th:not(:first-child) {
    font-size: 11px !important;
}
.status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px !important;
    font-weight: 500;
    white-space: nowrap;
    margin-top: 5px;
}
.status-badge.st-darkorange { background: #ffedd5; color: #ea580c; }
.status-badge.st-orange     { background: #fef3c7; color: #d97706; }
.status-badge.st-blue       { background: #e3edff; color: #1a56db; }
.status-badge.st-lightgreen { background: #e3f9ec; color: #1e7e45; }
.status-badge.st-green      { background: #c9f2d8; color: #0f5132; font-weight: 600; }
.status-badge.st-lightred   { background: #fde2e2; color: #c0392b; }
.status-badge.st-darkred    { background: #8b1e1e; color: #fff; font-weight: 600; }
.status-badge.st-unknown    { background: #eee;    color: #888; }

@media (max-width: 767px) {
    .grid_orderfeed .mobile-hide-col {
        display: none !important;
    }
}
</style>
