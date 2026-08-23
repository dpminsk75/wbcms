<?php

use kartik\grid\GridView;
use yii\helpers\Html;


use kartik\icons\Icon;
Icon::map($this); 

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ArrayDataProvider */
/* @var $dateFrom string */
/* @var $dateTo string */
/* @var $sortBy string */
/* @var $limit int */

$this->title = "ТОП-{$limit} товаров за период";  
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="wb-profit-top-products ">

    <div class="card card-default mb-4 shadow-sm">
        <div class="card-body">
            <?= Html::beginForm(['top-products'], 'get', ['class' => 'row g-3 align-items-end']) ?>
            
            <div class="col-md-2">
                <?= Html::label('Дата начала', 'dateFrom', ['class' => 'form-label', 'style' => 'font-weight: 500;']) ?>
                <?= Html::input('date', 'dateFrom', $dateFrom, ['class' => 'form-control']) ?>
            </div>
            
            <div class="col-md-2">
                <?= Html::label('Дата окончания', 'dateTo', ['class' => 'form-label', 'style' => 'font-weight: 500;']) ?>
                <?= Html::input('date', 'dateTo', $dateTo, ['class' => 'form-control']) ?>
            </div>
            
            <div class="col-md-3">
                <?= Html::label('Сортировать по', 'sortBy', ['class' => 'form-label', 'style' => 'font-weight: 500;']) ?>
                <?= Html::dropDownList('sortBy', $sortBy, [
                    'qnt' => 'Количеству продаж (шт)',
                    'amount' => 'Выручке (руб)',
                    'net_profit' => 'По итогу от WB (руб)',
                    'clean_margin' => 'Марже после налогов (руб)'

                ], ['class' => 'form-control form-select']) ?>
            </div>
            
            <div class="col-md-2">
                <?= Html::label('Показать товаров', 'limit', ['class' => 'form-label', 'style' => 'font-weight: 500;']) ?>
                <?= Html::dropDownList('limit', $limit, [
                    20 => 'ТОП-20',
                    50 => 'ТОП-50',
                    200 => 'ТОП-200',
                    500 => 'ТОП-500',
                    1000 => 'ТОП-1000'
                ], ['class' => 'form-control form-select']) ?>
            </div>
            
            <div class="col-md-3 d-grid">
                <?= Html::submitButton('<i class="fas fa-filter"></i> Применить фильтр', ['class' => 'btn btn-primary']) ?>
            </div>

            <?= Html::endForm() ?>
        </div>
    </div>
<div class="row grid_wbstat" style="margin-bottom: 25px;">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'containerOptions' => ['class' => 'custom-compact-grid'],

        'showFooter' => false,
        'toggleData' => false,

        'export' => [
            'showConfirmAlert' => false,
            'target' => GridView::TARGET_BLANK
        ],
        'exportConfig' => [
            GridView::EXCEL => ['label' => 'Сохранить в Excel'],
        ],

        'showPageSummary' => true, //text-white bg-wb-green-deep-header
        'striped' => true,
        'hover' => true,
        'responsive' => true,
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => '<i class="fas fa-crown"></i> ' . Html::encode($this->title),
            'headingOptions' => ['class' => 'card-header text-white bg-wb-green-deep-header'],
            'before' => Html::encode("Период анализа: c " . date('d.m.Y', strtotime($dateFrom)) . " по " . date('d.m.Y', strtotime($dateTo))),
            'footer' => false,
        ],
        'columns' => [
/*
            [
                'class' => 'kartik\grid\SerialColumn',
                'width' => '40px',
            ],
*/
/*
            [
                'attribute' => 'nm_id',
                'label' => 'nmID',
                'format' => 'raw',
                'value' => function($model) {
                    return Html::a($model['nm_id'], ['wb/detail', 'nm_id' => $model['nm_id']], [
                        'target' => '_blank', 
                        'data-pjax' => '0', 
                        'style' => 'font-weight: bold; text-decoration: none;'
                    ]);
                },
                'pageSummary' => 'Итого по ТОПу:',
            ],
*/
            [
                'attribute' => 'nm_id',
                'label' => 'Артикул WB', // Поменяли "Месяц" на актуальное название
                'format' => 'raw',       // Позволяет выводить HTML-код (ссылку)
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
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
                'attribute' => 'vendorCode',
                'label' => 'Артикул',

                'headerOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],
                'contentOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],
                'pageSummaryOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],

                'value' => function($model) {
                    return $model['vendorCode'] ?: '-';
                }
            ],
/*
            [
                'attribute' => 'title',
                'label' => 'Наименование товара',
                'options' => ['style' => 'max-width: 250px; white-space: normal;'],
                'value' => function($model) {
                    return $model['title'] ?: '—';
                }
            ],
*/
[
    'attribute' => 'title',
    'label' => 'Наименование товара',
    'options' => ['style' => 'max-width: 250px; white-space: normal;'],
    'value' => function($model) {
        if (empty($model['title'])) {
            return '—';
        }
        // Ищет двоеточие или запятую, после которых сразу идет буква или цифра,
        // и вставляет между ними пробел
        return preg_replace('/([:,.])(?=[^\s])/u', '$1 ', $model['title']);
    }
],

            [
                'attribute' => 'brand',
                'label' => 'Бренд',

                'headerOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],
                'contentOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],
                'pageSummaryOptions' => ['style' => 'display: none !important;', 'class' => 'kv-export-only'],

                'value' => function($model) {
                    return $model['brand'] ?: '—';
                }
            ],
            [
                'attribute' => 'qnt',
                'label' => 'Кол-во',
                'format' => ['decimal', 0],
                'hAlign' => 'center',
                'pageSummary' => true,
            ],
            [
                'attribute' => 'amount',
                'label' => 'Выручка',
                'format' => ['decimal', 0],
                'hAlign' => 'right',
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
                [
                    'attribute' => 'f_acquiring_fee',
                    'label' => 'Экв.',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'f_delivery',
                    'label' => 'Лог-ка',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b; font-weight: 500;'],
                    'pageSummary' => true,
                ],
/*
                [
                    'attribute' => 'f_storage_fee',
                    'label' => 'Хран.',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b; font-weight: 500;'],
                    'pageSummary' => true,
                ],
*/
/*
                [
                    'attribute' => 'f_penalty',
                    'label' => 'Штрафы',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                    'pageSummary' => true,
                ],

                [
                    'attribute' => 'f_otziv',
                    'label' => 'Отзывы',
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle; color: #16a085;'],
                    'pageSummary' => true,
                ],
*/
                [
                    'attribute' => 'f_penalty',
                    'label' => 'Штрафы',
                    'format' => 'raw', // Переключаем формат на RAW для вывода ссылки
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle;'],
                    'value' => function ($model) use ($dateFrom, $dateTo) {
                        if (empty($model['f_penalty'])) {
                            return '0';
                        }
                        
                        $formattedValue = Yii::$app->formatter->asDecimal($model['f_penalty'], 0);
                        $nmId = $model['nm_id'] ?? null;

                        return Html::a($formattedValue, '#', [
                            'class' => 'trigger-detail-popup',
                            'style' => 'color: #c0392b; font-weight: bold; text-decoration: underline;',
                            'data-from' => $dateFrom,
                            'data-to' => $dateTo,
                            'data-nmid' => $nmId,
                            'data-type' => 'shf', // Наш тип для штрафов
                        ]);
                    },
                    'pageSummary' => true,
                ],
                [
                    'attribute' => 'f_otziv',
                    'label' => 'Отзывы',
                    'format' => 'raw', // Переключаем формат на RAW для вывода ссылки
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'vertical-align: middle;'],
                    'value' => function ($model) use ($dateFrom, $dateTo) {
                        if (empty($model['f_otziv'])) {
                            return '0';
                        }
                        
                        $formattedValue = Yii::$app->formatter->asDecimal($model['f_otziv'], 0);
                        $nmId = $model['nm_id'] ?? null;

                        return Html::a($formattedValue, '#', [
                            'class' => 'trigger-feedback-popup',
                            'style' => 'color: #16a085; font-weight: bold; text-decoration: underline;',
                            'data-from' => $dateFrom,
                            'data-to' => $dateTo,
                            'data-nmid' => $nmId,
                        ]);
                    },
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
                'label' => 'Итого',
                'format' => ['decimal', 0],
                'hAlign' => 'right',
                'pageSummary' => true,

                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; background-color: #e8f8f5; color: #111;'],

                'contentOptions' => function($model) {
                    return $model['net_profit'] < 0 
                        ? ['class' => 'table-danger text-danger fw-bold'] 
                        : ['class' => 'table-success text-success fw-bold', 'style' => 'font-weight: bold;'];
                },
                'pageSummaryOptions' => ['style' => 'font-weight: bold;']
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
/*
                'contentOptions' => ['style' => 'vertical-align: middle; font-weight: bold; background-color: #fefde7;'],
*/
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
                    'label' => 'Цена',
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

        ],
    ]); ?>

</div></div>

<style>
    #w0 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* уменьшаем кнопки в панеле  */
    #w0 .kv-panel-before { padding: 5px 10px;}
    #w0-togdata-page, #w0-togdata-all {padding: 2px 5px;; font-size: 11px;}
    #w0-togdata-page .svg-inline--fa.fa-w-14 {width: 10px;}
    #w0-togdata-all .svg-inline--fa.fa-w-14 {width: 10px;}
/* размер заголовка  */
    #w0 .card-header { font-size: 11px; }
    #w0 .card-header h5 { font-size: 13px; }
</style>
<style>
.custom-compact-grid .table td {
    padding: 3px 2px !important;
}
</style>
<style>
main > .container-xxl {
    max-width: 100%;
}
</style>