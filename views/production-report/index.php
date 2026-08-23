<?php

/** @var yii\web\View $this */
/** @var yii\data\ArrayDataProvider $dataProvider */
/** @var string $period */
/** @var bool $showCompanyColumn */

use yii\helpers\Html;
use kartik\grid\GridView;
use kartik\icons\Icon;

Icon::map($this);

$this->title = 'Отчёт по производственному планированию';
$this->params['breadcrumbs'][] = $this->title;

$statusLabels = [
    'danger' => 'В печать',
    'warn' => 'Скоро',
    'ok' => 'Норма',
    'unknown' => 'Нет данных',
];
$statusClasses = [
    'danger' => 'report-stamp report-stamp-danger',
    'warn' => 'report-stamp report-stamp-warn',
    'ok' => 'report-stamp report-stamp-ok',
    'unknown' => 'report-stamp report-stamp-unknown',
];

if (!function_exists('fmtNum')) {
    function fmtNum($v, $digits = 0)
    {
        if ($v === null) {
            return '—';
        }
        return number_format((float)$v, $digits, ',', ' ');
    }
}

// подпись периода для заголовка панели, напр. "июль 2026"
$periodMonths = [
    1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
    7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
];
[$periodYear, $periodMonthNum] = array_map('intval', explode('-', $period));
$periodLabel = ($periodMonths[$periodMonthNum] ?? '') . ' ' . $periodYear;
?>
<div class="report-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card report-topbar mb-3">
        <div class="card-body">
            <?= Html::beginForm(['index'], 'get', ['class' => 'report-period-form']) ?>
                <label for="report-period" class="report-period-label">Период (месяц):</label>
                <input type="month" id="report-period" name="period" value="<?= Html::encode($period) ?>" class="form-control form-control-sm">
                <button type="submit" class="btn btn-primary btn-sm">Показать</button>
                <span class="text-muted report-period-note">
                    Остаток на начало периода берётся из ближайшего снапшота на эту дату или раньше.
                </span>
            <?= Html::endForm() ?>
        </div>
    </div>

    <?php
/*
    $columns = [
        [
            'label' => 'Товар',
            'format' => 'raw',
            'value' => function ($row) {
                return '<div class="report-item-vendor">' . Html::encode($row['vendor_code']) . ' <span class="text-muted">/ ' . $row['nm_id'] . '</span></div>'
                    . '<div class="report-item-title">' . Html::encode(mb_strimwidth($row['title'], 0, 45, '...')) . '</div>';
            },
        ],
    ];
*/
    $columns = [
        [
            'label' => 'Товар',
            'format' => 'raw',
            // 1. Задаем ширину колонки (например, 350px или больше, если нужно)
            'headerOptions' => ['style' => 'width: 350px; min-width: 250px;'], 
            'value' => function ($row) {
                // 2. Увеличиваем лимит символов (например, до 100) или можно вообще убрать mb_strimwidth
                $title = mb_strimwidth($row['title'], 0, 100, '...'); 
                
                return '<div class="report-item-vendor">' . Html::encode($row['vendor_code']) . ' <span class="text-muted">/ ' . $row['nm_id'] . '</span></div>'
                    . '<div class="report-item-title">' . Html::encode($title) . '</div>';
            },
        ],
    ];

    if ($showCompanyColumn) {
        $columns[] = [
            'label' => 'Компания',
            'value' => function ($row) { return $row['company_label']; },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center;'],
        ];
    }

    $columns = array_merge($columns, [
        [
            'label' => 'SML<br><small class="report-th-sub">нач. периода</small>',
            'encodeLabel' => false,
            'value' => function ($row) { return fmtNum($row['smolensk_start']); },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center; width:90px;'],
        ],
        [
            'label' => 'WB<br><small class="report-th-sub">нач. периода</small>',
            'encodeLabel' => false,
            'value' => function ($row) { return fmtNum($row['wb_start']); },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center; width:80px;'],
        ],
        [
            'label' => 'ИЗМ<br><small class="report-th-sub">Смоленск</small>',
            'encodeLabel' => false,
            'value' => function ($row) { return fmtNum($row['smolensk_movements']); },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center; width:95px;'],
        ],
        [
            'label' => 'Заказы<br><small class="report-th-sub">с начала периода</small>',
            'encodeLabel' => false,
            'value' => function ($row) { return fmtNum($row['orders_since_period']); },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center; width:100px;'],
        ],
        [
            'label' => 'Остаток<br><small class="report-th-sub">на сегодня</small>',
            'encodeLabel' => false,
            'format' => 'raw',
            'value' => function ($row) {
                return '<strong>' . fmtNum($row['total_today']) . '</strong>';
            },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center; width:90px;'],
            'pageSummary' => true,
            'pageSummaryFunc' => GridView::F_SUM,
            'pageSummaryOptions' => ['style' => 'text-align:center; font-weight:700;'],
        ],
        [
            'label' => 'Заказы<br><small class="report-th-sub">за 30 дн.</small>',
            'encodeLabel' => false,
            'value' => function ($row) { return fmtNum($row['orders_last_30']); },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center; width:85px;'],
        ],
        [
            'label' => 'Среднее<br><small class="report-th-sub">в день</small>',
            'encodeLabel' => false,
            'value' => function ($row) { return fmtNum($row['avg_daily'], 1); },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center; width:80px;'],
        ],
        [
            'label' => 'Дней<br><small class="report-th-sub">до конца</small>',
            'encodeLabel' => false,
            'value' => function ($row) { return $row['days_left'] !== null ? fmtNum($row['days_left']) : '—'; },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center; width:80px;'],
        ],
        [
            'label' => 'Закончится',
            'value' => function ($row) { return $row['eta_date'] ?? '—'; },
            'contentOptions' => ['style' => 'text-align:center; white-space:nowrap;'],
            'headerOptions' => ['style' => 'text-align:center; width:95px;'],
        ],
        [
            'label' => 'Точка<br><small class="report-th-sub">заказа, дн.</small>',
            'encodeLabel' => false,
            'value' => function ($row) { return $row['reorder_point_days']; },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center; width:80px;', 'title' => 'Произв. + логистика Смоленск + логистика WB + буфер'],
        ],
        [
            'label' => 'К<br><small class="report-th-sub">производству</small>',
            'encodeLabel' => false,
            'format' => 'raw',
            'value' => function ($row) {
                return $row['recommended_qty'] > 0
                    ? '<strong>' . fmtNum($row['recommended_qty']) . '</strong>'
                    : '<span class="text-muted">—</span>';
            },
            'contentOptions' => ['style' => 'text-align:center;'],
            'headerOptions' => ['style' => 'text-align:center; width:95px;'],
            'pageSummary' => true,
            'pageSummaryFunc' => GridView::F_SUM,
            'pageSummaryOptions' => ['style' => 'text-align:center; font-weight:700;'],
        ],
        [
            'label' => 'Статус',
            'format' => 'raw',
            'value' => function ($row) use ($statusLabels, $statusClasses) {
                return '<span class="' . $statusClasses[$row['status']] . '">' . $statusLabels[$row['status']] . '</span>';
            },
            'contentOptions' => ['style' => 'text-align:center; white-space:nowrap;'],
            'headerOptions' => ['style' => 'text-align:center;'],
        ],
    ]);
    ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'pjax' => false,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,
        'showPageSummary' => true,
        'showFooter' => false,
        'toggleData' => false,
        'tableOptions' => ['class' => 'report-table'],
        'columns' => $columns,
        'emptyText' => 'В плане пока нет товаров - добавьте их на странице "Список товаров для производственного планирования".',
        'export' => [
            'showConfirmAlert' => false,
            'target' => GridView::TARGET_BLANK,
        ],
        'exportConfig' => [
            GridView::EXCEL => ['label' => 'Сохранить в Excel'],
        ],
        'toolbar' => [
            '{export}',
        ],
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Производственный план — остатки и потребность (' . $periodLabel . ')',
            'headingOptions' => ['class' => 'card-header text-white bg-wb-blue-header'],
            'footer' => false,
            'after' => false,
        ],
        'containerOptions' => [
            'class' => 'no-border-class',
        ],
    ]) ?>

</div>

<style>
.report-topbar { border: none; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); overflow: hidden; }
.report-period-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.report-period-label { font-size: 13px; font-weight: 600; color: #495057; margin: 0; }
.report-period-form input[type=month] { width: 160px; }
.report-period-note { font-size: 12px; margin-left: 8px; }

.report-table { font-size: 12.5px; }
.report-table th { font-size: 10.5px; text-transform: uppercase; color: #495057; vertical-align: middle; line-height: 1.3; }
.report-th-sub { display: block; font-size: 9px; text-transform: none; color: #adb5bd; font-weight: 400; letter-spacing: 0; }
.report-table td { vertical-align: middle; }
.report-item-vendor { font-weight: 600; font-size: 12.5px; }
.report-item-title { font-size: 11.5px; color: #868e96; }

.report-stamp {
    display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .02em;
    text-transform: uppercase; padding: 4px 10px; border-radius: 4px; border: 1.5px solid;
}
.report-stamp-ok { color: #2f6e44; border-color: #2f6e44; background: #e4efe6; }
.report-stamp-warn { color: #b8720e; border-color: #b8720e; background: #f5e9d3; }
.report-stamp-danger { color: #a6362b; border-color: #a6362b; background: #f3deda; }
.report-stamp-unknown { color: #868e96; border-color: #ced4da; background: #f1f3f5; }

/* Уменьшаем отступы во всех ячейках таблицы */
.report-table td, 
.report-table th {
    padding-left: 4px !important;
    padding-right: 4px !important;
}

/* Первая колонка (Товар) забирает всё оставшееся свободное пространство */
.report-table th:first-child,
.report-table td:first-child {
    width: auto !important;
    min-width: 300px; /* Чтобы название товара не сжималось слишком сильно */
}

/* Все остальные колонки сжимаются строго под размер своего контента */
.report-table th:not(:first-child),
.report-table td:not(:first-child) {
    width: 1% !important;
    white-space: nowrap; /* Предотвращает некрасивый перенос цифр и дат */
}
</style>