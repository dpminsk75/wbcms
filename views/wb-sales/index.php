<?php

use kartik\grid\GridView;
use yii\helpers\Html;

use kartik\icons\Icon;
Icon::map($this); 

/* @var $this yii\web\View */
/* @var $searchModel common\models\WbSalesSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */


$this->title = 'Список продаж WB';

$this->title = 'Список продаж';
$this->params['breadcrumbs'][] = 'Данные';
$this->params['breadcrumbs'][] = 'Заказы';
$this->params['breadcrumbs'][] = 'Список продаж';

$this->registerCss("
    .wb-sales-index .table {
        font-size: 12px;
    }
    /* Дополнительно уменьшим отступы в ячейках, чтобы таблица была еще компактнее */
    .wb-sales-index .table td, .wb-order-index .table th {
        padding: 4px 8px !important;
    }
");


$QFButton = [];
if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin') {
    $QFButton[0] = Html::a('<i class="fas fa-sync-alt"></i>Дневник-шпаргалка', ['/wb-sales/index?WbSalesSearch[nmId]=526443466'],  ['class' => 'btn btn-panel'] );
    $QFButton[1] = Html::a('<i class="fas fa-sync-alt"></i>Календарь', ['/wb-sales/index?WbSalesSearch[nmId]=135462932'],  ['class' => 'btn btn-panel'] );
    
}

$PanelButtons = "";
foreach ($QFButton as $str) {
    $PanelButtons .= $str;
}


?>

<div class="wb-sales-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Продажи на WB',
            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            'before' => $PanelButtons, //  . ' ' . $densityLinks. ' ' . $unusedFilter
        ],
        'showPageSummary' => true,

        'columns' => [
            ['class' => 'yii\grid\ActionColumn', 'template' => '{view}',
                    'headerOptions'  => ['style' => 'width:30px'],
                    'contentOptions' => ['style' => 'width:30px; white-space: nowrap; align-content: center; text-align: center;'],
            ],

            [
                'attribute' => 'date',
                'label' => 'Дата',
                'format' => ['datetime', 'php:d.m.Y H:i'],
                'headerOptions'  => ['style' => 'width:130px'],
                'contentOptions' => ['style' => 'width:130px; white-space: nowrap; align-content: center; text-align: center;'],

                'filterType' => GridView::FILTER_DATE,
                'filterWidgetOptions' => [
                    'pluginOptions' => [
                        'format' => 'yyyy-mm-dd', // Формат, который понимает база данных и SearchModel
                        'autoclose' => true,
                        'todayHighlight' => true,
                        'convertFormat' => true, // Позволяет корректно обрабатывать значения
                    ],
                    'options' => [
                        'placeholder' => 'Выбрать...',
                        'autocomplete' => 'off', // Чтобы браузер не мешал своими подсказками
                    ]
                ],
            ],

            [
                'attribute' => 'nmId',
                'label' => 'Арт WB',
                'format' => 'raw', 
                'headerOptions'  => ['style' => 'width:90px'],
                'contentOptions' => ['style' => 'width:90px; white-space: nowrap; align-content: center; text-align: center;'],

                'value' => function($model) {
                    if (!$model->nmId) {
                        return null;
                    }
                    // Генерируем ссылку
                    return Html::a(
                        (string)$model->nmId, 
                        "https://www.wildberries.ru/catalog/" . $model->nmId . "/detail.aspx", 
                        [
                            'target' => '_blank',
                            'data-pjax' => '0', 
                            'style' => 'text-decoration: underline;'
                        ]
                    );
                },
            ],
            [
                'attribute' => 'supplierArticle',
                'headerOptions'  => ['style' => 'width:180px'],
                'contentOptions' => ['style' => 'width:180px; white-space: nowrap; align-content: center; text-align: center;'],
            ],

            [
                    'attribute' => 'cardTitle',
                    'label' => 'Товар / Детали заказа',
                    'headerOptions'  => ['style' => 'width:350px'],
                    'contentOptions' => ['style' => 'min-width:350px; white-space: wrap; '],
                    'format' => 'raw',
                    'value' => function($model) {
                        // Верхний уровень: Название товара
                        $title = Html::tag('div', $model->card->title ?? '—', [
                            'style' => 'font-weight: bold; font-size: 13px; margin-bottom: 8px; color: #2c3e50;'
                        ]);

                        // Нижний уровень: Цены и склад в ряд
                        $details = Html::tag('div', 
                            "Цена в карт: <b>{$model->totalPrice} ₽</b> | " .
                            "Своя скидка: <b>{$model->discountPercent} %</b> | " .
                            "Цена со ск: <b>{$model->priceWithDisc} ₽</b>" ,
                            ['style' => 'color: #666; font-size: 11px;']
                        );

                        $footer = Html::tag('div', 
                            "Склад: <b>{$model->warehouseName} ({$model->warehouseType})</b>",
                            ['style' => 'color: #666; font-size: 11px;']
                        );


                        return $title . $details. $footer;
                    },
            ],

            [
                'attribute' => 'totalPrice',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => true, // Можно включить итог по странице
            ],
            [
                'attribute' => 'discountPercent',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => true, // Можно включить итог по странице
            ],
            [
                'attribute' => 'priceWithDisc',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => true, // Можно включить итог по странице
            ],
            [
                'attribute' => 'spp',
                'width' => '100px',
                'hAlign' => 'center',
                'value' => function($model) {
                    return $model->spp . '%';
                },
            ],

            [
                'attribute' => 'finishedPrice',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => true, // Можно включить итог по странице
            ],

            [
                'attribute' => 'forPay',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'pageSummary' => true, // Можно включить итог по странице
            ],

            [
                    'attribute' => 'countryName',
                    'label' => 'Регион покупки',
                    'headerOptions'  => ['style' => 'width:250px'],
                    'contentOptions' => ['style' => 'min-width:250px; white-space: wrap; '],
                    'format' => 'raw',
                    'value' => function($model) {
                        $details = Html::tag('div', 
                            "{$model->countryName}, " .
                            "{$model->oblastOkrugName}, ".
                            "<b>{$model->regionName}</b> " ,
                            ['style' => 'color: #666; font-size: 11px;']
                        );

                        return $details;
                    },
            ],
/*
            'srid',
            [
                'class' => 'kartik\grid\ActionColumn',
                'template' => '{view}',
                'noWrap' => true,
            ],
*/
        ],
    ]); ?>


<style>
#w0 .border-primary {
    border-color: var(--bs-border-color-translucent) !important;
}
</style>

<style>
    .wb-sales-index .table {  font-size: 12px; table-layout: fixed; width: 100%; overflow-x: auto; overflow-y: hidden;
        display: block; border-collapse: collapse;}
    .wb-sales-index .table td, .wb-order-index .table th {  padding: 4px 8px !important; }
    .wb-sales-index input {  font-size: 12px;  }
    .wb-sales-index .input-group-text {padding: 4px;}
    .svg-inline--fa.fa-w-14, .svg-inline--fa.fa-w-11 { width: 10px; }
    .wb-sales-index #wbordersearch-date {padding: 2px;}
    input.form-control { padding: 2px; text-align: center;}
    #w0-filters td { padding: 2px 3px !important; }
    .form-select { padding: 2px 3px !important; font-size: 12px; text-align: center; background-position: right 5px center;}
</style>
</div>