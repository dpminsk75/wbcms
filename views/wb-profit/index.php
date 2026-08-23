<?php
use kartik\grid\GridView;


use kartik\icons\Icon;
Icon::map($this); 

$this->title = 'Маржинальный учет';

/* @var $this yii\web\View */
/* @var $MonthlyFinanceProvider yii\data\ArrayDataProvider */
?>

<?= $this->render('_monthly_profit_grid', ['dataProvider' => $MonthlyProfitProvider]) ?>

<?php
$columnsSKU = [
/*
            [
                'attribute' => 'nm_id',
                'label' => 'Месяц',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; width: 90px;'],
                'contentOptions' => ['style' => 'text-align: center; font-weight: bold; color: #2c3e50; vertical-align: middle;'],
                'pageSummary' => 'Итого',
                'pageSummaryOptions' => ['style' => 'text-align: center; font-weight: bold;']
            ],
*/
            [
                'attribute' => 'nm_id',
                'label' => 'Артикул WB', // Поменяли "Месяц" на актуальное название
                'format' => 'raw',       // Позволяет выводить HTML-код (ссылку)
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; width: 110px;'],
                'contentOptions' => ['style' => 'text-align: center; font-weight: bold; vertical-align: middle;'],
                'value' => function ($model) {
                    $nmId = $model['nm_id'] ?? '';
                    $title = !empty($model['title']) ? htmlspecialchars($model['title'], ENT_QUOTES) : '';
                    $vendorCode = !empty($model['vendorCode']) ? htmlspecialchars($model['vendorCode'], ENT_QUOTES) : '';
                    
                    // Формируем текст для всплывающей подсказки (title) при наведении
                    $tooltip = "{$title}";
                    if (!empty($vendorCode)) {
                        $tooltip .= " &#10;Артикул: {$vendorCode}"; // &#10; — это перенос строки внутри title
                    }
                    
                    // Строим ссылку с параметром фильтрации
                    return "<a href=\"/wb/detail?DPFilterForm[nm_id]={$nmId}\" title=\"{$tooltip}\" style=\"color: #2980b9; text-decoration: none;\">{$nmId}</a>";
                },
                'pageSummary' => 'Итого',
                'pageSummaryOptions' => ['style' => 'text-align: center; font-weight: bold;']
            ],
            [
                'attribute' => 'title',
                'label' => 'Название товара',
/*
                'headerOptions' => ['class' => 'kv-export-only'],
                'contentOptions' => ['class' => 'kv-export-only'],
                'pageSummaryOptions' => ['class' => 'kv-export-only'],
*/
                'headerOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],
                'contentOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],
                'pageSummaryOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],
                'value' => function ($model) {
                    return $model['title'] ?? '';
                },
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
                    'label' => 'Выручка',
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
                    'contentOptions' => [
                        'style' => 'vertical-align: middle; font-weight: bold; color: #27ae60; background-color: #f4fbf7;'
                    ],
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
                'contentOptions' => ['style' => 'vertical-align: middle; font-weight: bold; background-color: #fefde7;'],
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
                'contentOptions' => [
                    'style' => 'vertical-align: middle; font-weight: bold; color: #196f3d; background-color: #eaf2f8;'
                ],
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
                        $class = $model['net_profit'] < 0 ? 'text-danger' : 'text-success';
                        return ['class' => $class, 'style' => 'font-weight: bold; font-style: italic; background-color: #fafafa;'];
                    },
                ],

            [
                'attribute' => 'cost_per_item',
                'label' => 'Себ-ть/шт',
/*
                'headerOptions' => ['class' => 'kv-export-only'],
                'contentOptions' => ['class' => 'kv-export-only'],
                'pageSummaryOptions' => ['class' => 'kv-export-only'],
*/
                'headerOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],
                'contentOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],
                'pageSummaryOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],
                'format' => ['decimal', 2],
                'hAlign' => 'right',
            ],
                [
                    'attribute' => 'clear_per_item',
                    'label' => 'Маржа/шт',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'contentOptions' => function($model) {
                        $class = $model['clean_margin'] < 0 ? 'text-danger' : 'text-success';
                        return ['class' => $class, 'style' => 'font-weight: bold; font-style: italic; background-color: #fafafa;'];
                    },
                ],

            ];
?>
<div class="row grid_wbstat" style="margin-bottom: 25px;">
<?php
    echo GridView::widget([
        'dataProvider' => $top20SKUProvider,
//        'export' => false, 
        'pjax' => false,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,
        'showPageSummary' => true, // Включаем строку "Итого"
        'showFooter' => false,
        'toggleData' => false,
        'export' => [
            'showConfirmAlert' => false,
            'target' => GridView::TARGET_BLANK
        ],
        'exportConfig' => [
            GridView::EXCEL => ['label' => 'Сохранить в Excel'],
        ],
        'toolbar' => [
            '{export}', // Выведет только аккуратную кнопку экспорта
        ],
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Топ 20 товаров за последние 60 дней',
            'headingOptions' => ['class' => 'card-header text-white bg-wb-green-deep-header'],
            'footer' => false,
            'after' => false,
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],
        'columns' => $columnsSKU,

        ]);
?>
</div>
<div class="row grid_wbstat" style="margin-bottom: 25px;">
<?php
    echo GridView::widget([
        'dataProvider' => $last20SKUProvider,
//        'export' => false, 
        'pjax' => false,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,
        'showPageSummary' => true, // Включаем строку "Итого"
        'showFooter' => false,
        'toggleData' => false,
        'export' => [
            'showConfirmAlert' => false,
            'target' => GridView::TARGET_BLANK
        ],
        'exportConfig' => [
            GridView::EXCEL => ['label' => 'Сохранить в Excel'],
        ],
        'toolbar' => [
            '{export}', // Выведет только аккуратную кнопку экспорта
        ],
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Худшие 20 товаров за последние 60 дней',
            'headingOptions' => ['class' => 'card-header text-white bg-wb-danger-header'],
            'footer' => false,
            'after' => false,
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],
        'columns' => $columnsSKU,

        ]);

    ?>
</div>

<style>
    #w0 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* уменьшаем кнопки в панеле  */
    #w0 .kv-panel-before { padding: 2px;}
    #w0-togdata-page, #w4-togdata-all {padding: 2px 5px;; font-size: 11px;}
    #w0-togdata-page .svg-inline--fa.fa-w-14 {width: 10px;}
    #w0-togdata-all .svg-inline--fa.fa-w-14 {width: 10px;}
/* размер заголовка  */
    #w0 .card-header { font-size: 11px; }
    #w0 .card-header h5 { font-size: 13px; }
</style>
<style>
    #w2 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* уменьшаем кнопки в панеле  */
    #w2 .kv-panel-before { padding: 2px;}
    #w2-togdata-page, #w4-togdata-all {padding: 2px 5px;; font-size: 11px;}
    #w2-togdata-page .svg-inline--fa.fa-w-14 {width: 10px;}
    #w2-togdata-all .svg-inline--fa.fa-w-14 {width: 10px;}
/* размер заголовка  */
    #w2 .card-header { font-size: 11px; }
    #w2 .card-header h5 { font-size: 13px; }
</style>
<style>
    #w4 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* уменьшаем кнопки в панеле  */
    #w4 .kv-panel-before { padding: 2px;}
    #w4-togdata-page, #w4-togdata-all {padding: 2px 5px;; font-size: 11px;}
    #w4-togdata-page .svg-inline--fa.fa-w-14 {width: 10px;}
    #w4-togdata-all .svg-inline--fa.fa-w-14 {width: 10px;}
/* размер заголовка  */
    #w4 .card-header { font-size: 11px; }
    #w4 .card-header h5 { font-size: 13px; }
</style>
<style>
    #w6 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* уменьшаем кнопки в панеле  */
    #w6 .kv-panel-before { padding: 2px;}
    #w6-togdata-page, #w4-togdata-all {padding: 2px 5px;; font-size: 11px;}
    #w6-togdata-page .svg-inline--fa.fa-w-14 {width: 10px;}
    #w6-togdata-all .svg-inline--fa.fa-w-14 {width: 10px;}
/* размер заголовка  */
    #w6 .card-header { font-size: 11px; }
    #w6 .card-header h5 { font-size: 13px; }
</style>
<style>
    #w8 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* уменьшаем кнопки в панеле  */
    #w8 .kv-panel-before { padding: 2px;}
    #w8-togdata-page, #w4-togdata-all {padding: 2px 5px;; font-size: 11px;}
    #w8-togdata-page .svg-inline--fa.fa-w-14 {width: 10px;}
    #w8-togdata-all .svg-inline--fa.fa-w-14 {width: 10px;}
/* размер заголовка  */
    #w8 .card-header { font-size: 11px; }
    #w8 .card-header h5 { font-size: 13px; }
</style>
<style>
    .grid_wbstat .table {  font-size: 12px; table-layout: fixed; width: 100%; overflow-x: auto; overflow-y: hidden; display: block; border-collapse: collapse;}
    .grid_wbstat .table td, .grid_wbstat .table th {  padding: 4px 4px !important; }
    .grid_wbstat .table th {text-align: center;}
    .grid_wbstat input {  font-size: 12px;  }
    .grid_wbstat .input-group-text {padding: 4px;}
    .grid_wbstat {margin-bottom: 20px;}
    .grid_wbstat a {text-decoration: none; };
</style> 