<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use app\components\getDPWidget;

use kartik\icons\Icon;
Icon::map($this);

/** @var yii\web\View $this */
/** @var app\models\DPFilterForm $filterModel */
/** @var app\models\WbOrderFeedSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Лента заказов';
?>
<h1><?= Html::encode($this->title) ?></h1>
<div class="row"><div class="col-md-6 mb-3">
<?= getDPWidget::widget([
    'action' => ['wb-order/feed'],
    // По умолчанию — сегодня (а не -15 дней, как в других разделах);
    // фильтр по карточке (nm_id) по умолчанию не установлен.
    'defaultDateFrom' => date('Y-m-d'),
    'defaultDateTo' => date('Y-m-d'),
]) ?>
</div></div>

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

<?php
// Резолвер статуса → [текст, css-класс пилюли]. Без префиксов "FBS:"/"FBW:" —
// как в собственном отчёте WB, просто человекочитаемый статус с фоном.
$resolveStatus = function ($row) {
    $isFbs = ($row['warehouse_type'] ?? null) === 'Склад продавца';

    if ($isFbs) {
        $supplierStatus = $row['fbs_supplier_status'] ?? null;
        $wbStatus = $row['fbs_wb_status'] ?? null;

        // Заказ ещё не попал ни в одну поставку — самый ранний статус,
        // проверяем раньше остальных условий (supplier_status/wb_status
        // в этот момент могут быть ещё не заполнены).
        if (empty($row['fbs_supply_id'])) {
            return ['Новый', 'st-blue'];
        }

        if ($wbStatus === 'waiting') {
            switch ($supplierStatus) {
                case 'new':
                    return ['Новый', 'st-blue'];
                case 'confirm':
                    return ['Сборка', 'st-blue'];
                case 'complete':
                    return ['В пути', 'st-lightgreen'];
                case 'cancel':
                    return ['Отменён', 'st-lightred'];
            }
        }

        switch ($wbStatus) {
            case 'ready_for_pickup':
                return ['На ПВЗ', 'st-lightgreen'];
            case 'sold':
                return ['Выкуплен', 'st-green'];
            case 'canceled':
                return ['Отменён', 'st-lightred'];
            case 'canceled_by_client':
                return ['Отмена клиентом', 'st-lightred'];
            case 'defect':
                return ['Брак', 'st-darkred'];
            case 'sorted':
                return ['В пути', 'st-lightgreen'];
        }

        if (!$supplierStatus && !$wbStatus) {
            return ['Нет данных', 'st-unknown'];
        }

        // Комбинация вне описанного списка — показываем как есть, чтобы
        // было видно в проде, что появился новый вариант статуса.
        return [trim($supplierStatus . ' / ' . $wbStatus, ' /'), 'st-unknown'];
    }

    // FBW — по имеющимся флагам/датам самого заказа.
    if (!empty($row['is_cancel'])) {
        return ['Отменён', 'st-lightred'];
    }
    if (!empty($row['sale_date'])) {
        return ['Выкуплен', 'st-green'];
    }
    return ['В пути', 'st-lightgreen'];
};

$columns = [

    // ---- Карточка товара (фото + название + категория/бренд + артикулы) ----
    [
        'label' => 'Заказ',
        'format' => 'raw',
        'filter' => '',
        'headerOptions' => ['style' => 'width:280px;'],
        'contentOptions' => ['style' => 'width:280px;'],
        'value' => function ($row) {
            // ВАЖНО: в некоторых записях wbcards.photos оказывается
            // задвойным JSON-кодированием (значение хранится как JSON-
            // строка, содержащая JSON-текст, а не как нативный JSON-массив —
            // похоже на баг в команде синка карточек, где в JSON-колонку
            // передаётся уже json_encode()-нутая строка, а Yii2 кодирует её
            // ещё раз). Поэтому декодируем с подстраховкой на второй проход.
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

/*
            $breadcrumb = Html::tag('div', Html::encode($row['card_subject_name']) . ' • ' . Html::encode($row['card_brand']), [
                'class' => 'cart-item-details',
            ]);
*/
            $eyeIcon = '<a href="/wb-order/view?id='.$row['id'].'" class="icon-link icon-link-hover" target="_blank"><i class="bi bi-eye-fill me-1 lh-1" style="transform: none;"></i></a>'; 
            $content = $eyeIcon . Html::encode($row['card_subject_name']) . ' • ' . Html::encode($row['card_brand']);
            $breadcrumb = Html::tag('div', $content, [
                'class' => 'cart-item-details d-flex align-items-center', // добавили флексы для идеального выравнивания по центру
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

    // ---- Даты ----
    [
        'label' => 'Дата заказа',
        'format' => 'raw',
        'filter' => '',
        'value' => function ($row) {
            if (empty($row['date'])) {
                return '—';
            }
            $ts = strtotime($row['date']);
            return '<div style="font-weight:600;">' . date('d.m.Y', $ts) . '</div>'
                . '<div style="font-size:11px; color:#888;">' . date('H:i', $ts) . '</div>';
        },
    ],
    [
        'label' => 'Обновлен',
        'format' => 'raw',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col'],
        'value' => function ($row) {
            // sale_date — дата фактической реализации (выкупа). Если она
            // проставлена, показываем именно её, независимо от fbs/fbw —
            // это самый достоверный "текущий статус" из возможных.
            if (!empty($row['sale_date'])) {
                $dt = $row['sale_date'];
            } else {
                $isFbs = ($row['warehouse_type'] ?? null) === 'Склад продавца';
                $dt = $isFbs
                    ? ($row['fbs_status_changed_at'] ?? $row['last_change_date'] ?? null)
                    : ($row['last_change_date'] ?? null);
            }
            if (empty($dt)) {
                return '—';
            }
            $ts = strtotime($dt);
            return '<div style="font-weight:600;">' . date('d.m.Y', $ts) . '</div>'
                . '<div style="font-size:11px; color:#888;">' . date('H:i', $ts) . '</div>';
        },
    ],

    // ---- Статус (fbs / fbw) ----
    [
        'attribute' => 'status',
        'label' => 'Статус',
        'format' => 'raw',
        'filter' => \app\models\WbOrderFeedSearch::getStatusOptions(),
        'filterInputOptions' => ['class' => 'form-control', 'prompt' => 'Все'],
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'width:100px;'],
        'value' => function ($row) use ($resolveStatus) {
            [$label, $cls] = $resolveStatus($row);

            $isFbs = ($row['warehouse_type'] ?? null) === 'Склад продавца';
            $fbsLabel = $isFbs ? 'FBS' : 'FBO';
            $fbsClass = $isFbs ? 'badge-fbs st-orange' : 'badge-fbo st-darkorange'; // Классы для кастомизации цвета (опционально)
            $fbsBadge = Html::tag('span', $fbsLabel, [
                'class' => 'status-badge ' . $fbsClass . ' me-1' // me-1 сделает аккуратный отступ справа до следующего бейджа
            ]);

            $mainBadge = Html::tag('span', Html::encode($label), ['class' => 'status-badge ' . $cls]);

            // Для отменённых заказов, если известна причина отмены
            // (cancel_type из order-feed) — показываем её мелкой подписью
            // под бейджем статуса.
            $cancelReason = '';
            if (in_array($label, ['Отменён', 'Отмена клиентом'], true) && !empty($row['cancel_type'])) {
                $cancelReason = '<br/><span style="font-size:10px; color:#999;">' . Html::encode($row['cancel_type']) . '</span>';
            }

            return $fbsBadge ."<br/>". $mainBadge . $cancelReason;
/*
            return Html::tag('span', Html::encode($label), ['class' => 'status-badge ' . $cls]);
*/
        },
    ],

    // ---- Откуда / Куда ----
    [
        'attribute' => 'warehouse_name',
        'label' => 'Откуда',
        'format' => 'raw',
        'filter' => $searchModel->getWarehouseOptions(),
        'filterInputOptions' => ['class' => 'form-control', 'prompt' => 'Все'],
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'width:100px;'],
        'value' => function ($row) {
            $name = Html::encode($row['warehouse_name'] ?? '—');
            $subParts = array_filter([$row['warehouse_type'] ?? null]); //$row['oblast_okrug_name'] ?? null,
            $sub = Html::encode(implode(' • ', $subParts));
            return '<div style="font-weight:600;">' . $name . '</div>'
                . '<div style="font-size:11px; color:#888;">' . $sub . '</div>';
        },
    ],
    [
        'attribute' => 'region_name',
        'label' => 'Куда',
        'format' => 'raw',
        'filter' => $searchModel->getRegionOptions(),
        'filterInputOptions' => ['class' => 'form-control', 'prompt' => 'Все'],
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'width:100px;'],
        'value' => function ($row) {
            $region = trim((string)($row['region_name'] ?? ''));
            $city = trim((string)($row['destination_city'] ?? ''));

            if ($region !== '' && $city !== '') {
                return '<div style="font-weight:600;">' . Html::encode($region) . '</div>'
                    . '<div style="font-size:11px; color:#888;">' . Html::encode($city) . '</div>';
            }
            if ($region !== '') {
                return '<div style="font-weight:600;">' . Html::encode($region) . '</div>';
            }
            if ($city !== '') {
                return '<div style="font-weight:600;">' . Html::encode($city) . '</div>';
            }
            return '<span class="text-muted">—</span>';
        },
    ],

    // ---- Цены ----
    [
        'attribute' => 'total_price',
        'label' => 'Цена в кар-ке',
        'format' => ['decimal', 2],
        'hAlign' => 'right',
        'filter' => '', // пусто, не '—' — иначе видно заглушку emptyCell в строке фильтров
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;width:50px;'],
    ],
    [
        'attribute' => 'discount_percent',
        'label' => 'Скидка, %',
        'hAlign' => 'right',
        'filter' => '', // пусто, не '—' — иначе видно заглушку emptyCell в строке фильтров
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;'],
    ],
    [
        'attribute' => 'price_with_disc',
        'label' => 'Цена со скидкой',
        'format' => ['decimal', 2],
        'hAlign' => 'right',
        'filter' => '', // пусто, не '—' — иначе видно заглушку emptyCell в строке фильтров
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;width:50px; font-weight:bold; '],
    ],
    [
        'attribute' => 'spp',
        'label' => 'СПП',
        'format' => ['decimal', 2],
        'hAlign' => 'right',
        'filter' => '', // пусто, не '—' — иначе видно заглушку emptyCell в строке фильтров
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;'],
    ],
    [
        'attribute' => 'finished_price',
        'label' => 'Цена продажи',
        'format' => ['decimal', 2],
        'hAlign' => 'right',
        'filter' => '', // пусто, не '—' — иначе видно заглушку emptyCell в строке фильтров
        'contentOptions' => ['style' => 'text-align:right; width:50px;'],
    ],

    // ---- Финансы (факты из wb_order) ----
    [
        'label' => 'Комиссия',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right; white-space: nowrap;'],
        'value' => function ($row) {
            if (($row['commission_fee'] ?? null) !== null) {
                return Html::encode($row['commission_fee']) . ' ₽<br>'
                    . '<span style="font-size:12px; color:#666;">' . Html::encode($row['commission_percent'] ?? '') . '%</span>';
            }

            // Факта нет. Прогноз показываем ТОЛЬКО для ещё "живых" заказов
            // (не выкуплен, но и не отменён) — для отменённых прогноз не
            // имеет смысла, честно показываем прочерк.
            $isAlive = empty($row['sale_date']) && empty($row['is_cancel']);
            if ($isAlive && ($row['forecast_commission_pct'] ?? null) !== null) {
                $ratio = (float)$row['forecast_commission_pct'];
                $base = (float)($row['price_with_disc'] ?? $row['finished_price'] ?? 0);
                $estFee = round($base * $ratio, 2);
                $estPct = round($ratio * 100, 1);
                return '<i style="color:#888;">' . Html::encode($estFee) . ' ₽</i><br>'
                    . '<span style="font-size:12px; color:#aaa;"><i>' . Html::encode($estPct) . '%</i></span>';
            }

            return '<span class="text-muted">—</span>';
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
            if (($row['acquiring_fee'] ?? null) !== null) {
                return Html::encode($row['acquiring_fee']) . ' ₽<br>'
                    . '<span style="font-size:12px; color:#666;">' . Html::encode($row['acquiring_percent'] ?? '') . '%</span>';
            }

            $isAlive = empty($row['sale_date']) && empty($row['is_cancel']);
            if ($isAlive && ($row['forecast_acquiring_pct'] ?? null) !== null) {
                $ratio = (float)$row['forecast_acquiring_pct'];
                $base = (float)($row['price_with_disc'] ?? $row['finished_price'] ?? 0);
                $estFee = round($base * $ratio, 2);
                $estPct = round($ratio * 100, 1);
                return '<i style="color:#888;">' . Html::encode($estFee) . ' ₽</i><br>'
                    . '<span style="font-size:12px; color:#aaa;"><i>' . Html::encode($estPct) . '%</i></span>';
            }

            return '<span class="text-muted">—</span>';
        },
    ],
    [
        'label' => 'Кешбэк',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;'],
        'value' => function ($row) {
            // Кешбэка нет в detail_by_period_forecast — прогнозировать
            // нечего. Если заказ уже реально обработан (facts_updated_at
            // проставлен — приходил хотя бы один факт по этому srid) и
            // кешбэка не случилось, это подтверждённый 0. Если заказ ещё не
            // обработан — честный прочерк, а не выдуманный ноль.
            if (!empty($row['facts_updated_at'])) {
                $val = (float)($row['cashback_amount'] ?? 0);
                if ($val > 0) { return Html::encode(number_format($val, 2, ',', ' ')) . ' ₽'; }
            }
            return '<span class="text-muted">—</span>';
        },
    ],

    // ---- Логистика (факты из wb_order) ----
    [
        'label' => 'Логистика',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right; white-space: nowrap;'],
        'value' => function ($row) {
            if (($row['delivery_rub'] ?? null) !== null) {
                
                return Html::encode($row['delivery_rub']) . ' ₽' . ((($row['return_rub'] ?? null) !== null) ? ' <br /> ' . Html::encode($row['return_rub']) . ' ₽' : '');
            }
            $isAlive = empty($row['sale_date']) && empty($row['is_cancel']);
            if ($isAlive && ($row['forecast_delivery_rub'] ?? null) !== null) {
                return '<i style="color:#888;">' . Html::encode(round((float)$row['forecast_delivery_rub'], 2)) . ' ₽</i>';
            }
            return '<span class="text-muted">—</span>';
        },
    ],
/*
    [
        'attribute' => 'delivery_method',
        'label' => 'Тип',
        'format' => 'raw',
        'filter' => '', // пусто, не '—' — иначе видно заглушку emptyCell в строке фильтров
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col'],
        'value' => function ($row) {
            // Текстовое поле, прогноза быть не может — только факт или прочерк.
            return !empty($row['delivery_method'])
                ? Html::encode(trim(preg_replace('/,\s*\(.*?\)/', '', $row['delivery_method'])))
                : '<span class="text-muted">—</span>';
        },
    ],
    [
        'label' => 'Возврат',
        'format' => 'raw',
        'hAlign' => 'right',
        'filter' => '',
        'headerOptions' => ['class' => 'mobile-hide-col'],
        'contentOptions' => ['class' => 'mobile-hide-col', 'style' => 'text-align:right;'],
        'value' => function ($row) {
            // Прогноза тут нет и не будет, пока в detail_by_period_forecast не
            // появится отдельное поле для возвратной логистики (сейчас там
            // один общий sum_delivery_rub без разделения на "туда"/"обратно") —
            // честно показываем факт или прочерк, без фабрикации цифр.
            return (($row['return_rub'] ?? null) !== null)
                ? Html::encode($row['return_rub']) . ' ₽'
                : '<span class="text-muted">—</span>';
        },
    ],
*/
];
?>
<div class="row grid_orderfeed grid_wbstat grid_no_kv-panel-before">
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'export' => false,
    'pjax' => true,
    'bordered' => true,
    'striped' => true,
    'condensed' => true,
    'responsive' => true,
    'hover' => true,
    'panel' => [
        'type' => GridView::TYPE_PRIMARY,
        'heading' => 'Заказы (' . Yii::$app->formatter->asDate($filterModel->date_from, 'd MMM y')
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
/*    text-transform: uppercase;
    letter-spacing: .03em; */
    margin-bottom: 8px;
}
.grid_orderfeed_summary .summary-value {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
/*    line-height: 1.2; */
    white-space: nowrap;
}
.grid_orderfeed_summary .summary-split {
    display: flex;
    gap: 16px;
    margin-top: 4px;
}
.grid_orderfeed_summary .summary-split-item {
    font-size: 20px;
/*    font-weight: 700; */
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

/* Везде, кроме колонки "Товар" (она первая, стили выше уже её задают
   отдельно), — единый уменьшенный размер шрифта. Только на самой <td>,
   БЕЗ универсального селектора по потомкам — иначе перебивает инлайновые
   font-size:11px у вложенных строк (время под датой, тип склада под
   названием) и вся визуальная иерархия "крупно/мелко" схлопывается. */
.grid_orderfeed td:not(:first-child) {
    font-size: 12px !important;
    vertical-align: middle;
}
.grid_orderfeed td:nth-child(2), .grid_orderfeed td:nth-child(3), .grid_orderfeed td:nth-child(4)
{
    text-align: center;
}

/* Заголовки — мельче, обычное начертание (не жирное), перенос по словам
   вместо nowrap, чтобы длинные подписи ("Логистика возврата" и т.п.) не
   растягивали таблицу по ширине, а переносились в 2 строки. */
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

/* Статус — пилюля с фоном, без текста "FBS"/"FBW", как в отчёте WB. */
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