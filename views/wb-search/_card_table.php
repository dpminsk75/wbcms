<?php
use yii\helpers\Html;
use kartik\grid\GridView;

/** @var $dataProvider yii\data\ArrayDataProvider */
/** @var $uniqueDates array */

// 1. Формируем базовые статичные колонки
$gridColumns = [
    [
        'attribute' => 'phrase',
        'label' => 'Поисковый запрос',
        'format' => 'raw',
        'contentOptions' => ['style' => 'width:300px; white-space:normal;', 'class' => 'text-muted kv-sticky-column'],
        'headerOptions' =>  ['class' => 'kv-sticky-column'],
        'value' => function($model) {
            $url = 'https://www.wildberries.ru/catalog/0/search.aspx?search=' . urlencode($model['phrase']);
            return Html::a($model['phrase'], $url, ['data-pjax' => 0, 'target' => '_blank']);
        }
    ],
    [
        'attribute' => 'avg_freq',
        'label' => 'Ср. част',
        'format' => 'integer',
        'hAlign' => 'center',
        'width' => '80px',
        'headerOptions' => ['title' => 'Средняя частотность за неделю (week_frequency)'],
        'contentOptions' => ['class' => 'text-secondary', 'style' => 'background-color: #fcfcfc;'],
    ],
    [
        'attribute' => 'total_clicks',
        'label' => 'Клики',
        'format' => ['decimal', 0],
        'hAlign' => 'center',
        'width' => '90px',
        'contentOptions' => ['style' => 'background-color: #fcfcfc;', 'class' => 'text-primary'],
    ],
    [
        'attribute' => 'total_orders',
        'label' => 'Заказы',
        'format' => ['decimal', 0],
        'hAlign' => 'center',
        'width' => '70px',
        'contentOptions' => ['style' => 'background-color: #f8f9ff;', 'class' => 'text-primary'],
    ],
];

// 2. Динамически добавляем колонки дат
foreach ($uniqueDates as $date) {
    $gridColumns[] = [
        'attribute' => $date,
        'label' => date('d.m', strtotime($date)),
        'format' => 'raw',
        'hAlign' => 'center',
        'headerOptions' => ['class' => 'text-center small'],
        'contentOptions' => function($model) use ($date) {
            $data = $model[$date] ?? null;
            $pos = $data['pos'] ?? 0;
            $orders = $data['orders'] ?? 0;
            
            $classes = [];
            if ($pos > 0 && $pos <= 10) {
                $classes[] = 'pos-top-10';
            } elseif ($pos > 10 && $pos <= 50) {
                $classes[] = 'pos-top-50';
            }
            
            if ($orders > 0) {
                $classes[] = 'has-orders';
            }
            
            return [
                'class' => implode(' ', $classes), 
                'title' => $orders > 0 ? "Заказов: $orders" : ""
            ];
        },
        'value' => function($model) use ($date) {
            $data = $model[$date] ?? null;
            $pos = $data['pos'] ?? 0;
            return $pos ?: '';
        }
    ];
}
?>

<div id="grid-scroll" style="max-height: 70vh; position: relative;">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns'      => $gridColumns,
        'pjax'         => true,
        'responsive'   => false,
        'bordered'     => true,
        'condensed'    => true,
        'hover'        => true,
        'containerOptions' => ['style' => 'max-height: 70vh; overflow: auto;'],
        'floatHeader'  => true,
        'rowOptions' => function ($model, $key, $index, $grid) {
            return ['class' => 'clickable-row', 'style' => 'cursor: pointer;'];
        },
        'panel' => [
            'type'    => GridView::TYPE_DEFAULT,
            'heading' => '<i class="fas fa-chart-line"></i> Динамика позиций',
            'headingOptions' => ['class' => 'card-header text-white bg-wb-blue-header'],
            'before'  => false, 'after'   => false, 'footer' => false,
        ],
    ]) ?>
</div>

<?php 
// Подключаем JS скрипт выделения строк. 
// Благодаря registerJs, код зарегистрируется один раз, даже если виджет будет вызван повторно.
$js = <<<JS
$(document).off('click', '.clickable-row').on('click', '.clickable-row', function(e) {
    var table = $(this).closest('table');
    table.find('tr').removeClass('selected-row');
    $(this).addClass('selected-row');
});
JS;
$this->registerJs($js);
?>

<style>
    /* Все стили таблицы инкапсулируем здесь, чтобы они «путешествовали» вместе с ней */
    .table-bordered td, .table-bordered th { border: 1px solid #f1f1f1 !important; }
    .panel-default > .panel-heading { background-color: #fff; border-bottom: 1px solid #eee; }
    .custom-compact-grid .table, .kv-grid-container { overflow: auto !important; }

    #grid-scroll .kv-sticky-column {
        position: sticky !important;
        left: 0 !important;
        background-color: white !important;
        z-index: 5 !important;
        border-right: 2px solid #dee2e6 !important;
    }

    #grid-scroll th.kv-sticky-column { z-index: 10 !important; top: 0; }
    .kv-float-header { z-index: 10 !important; }

    .pos-top-10 { background-color: #ebfbee !important; color: #2b8a3e !important; font-weight: 500; }
    .pos-top-50 { background-color: #fff9db !important; color: #856404 !important; }
    .has-orders { font-weight: 900 !important; box-shadow: inset 0 0 0 1px rgba(0, 123, 255, 0.1); }

    .table > tbody > tr.selected-row > td, 
    .table > tbody > tr.selected-row > th { background-color: #ffb7f8 !important; color: #000 !important; }
    .table > tbody > tr.selected-row > td.pos-top-10 { background-color: #f37be7 !important; }
    .table > tbody > tr.selected-row > td.pos-top-50 { background-color: #f897ee !important; }


#grid-scroll .table {
    table-layout: auto !important;
    width: auto !important;
    min-width: 100% !important;
    display: table !important;
    overflow: visible !important;
}
</style>

