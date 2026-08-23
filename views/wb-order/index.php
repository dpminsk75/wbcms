<?php 

use kartik\grid\GridView;
use yii\helpers\Html;

use kartik\icons\Icon;
Icon::map($this); 

$this->title = 'Список заказов WB';

$this->title = 'Список заказов';
$this->params['breadcrumbs'][] = 'Данные';
$this->params['breadcrumbs'][] = 'Заказы';
$this->params['breadcrumbs'][] = 'Список заказов';

?>
<style>
    .wb-order-index .table {  font-size: 12px; table-layout: fixed; width: 100%; overflow-x: auto; overflow-y: hidden;
        display: block; border-collapse: collapse;}
    .wb-order-index .table td, .wb-order-index .table th {  padding: 4px 8px !important; }
    .wb-order-index input {  font-size: 12px;  }
    .wb-order-index .input-group-text {padding: 4px;}
    .svg-inline--fa.fa-w-14, .svg-inline--fa.fa-w-11 { width: 10px; }
    .wb-order-index #wbordersearch-date {padding: 2px;}
    input.form-control { padding: 2px; text-align: center;}
    #w0-filters td { padding: 2px 3px !important; }
    .form-select { padding: 2px 3px !important; font-size: 12px; text-align: center; background-position: right 5px center;}
</style>

<div class="wb-order-index">
    <h1><?= Html::encode($this->title) ?></h1>

<?php

$QFButton = [];
if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin') {
    $QFButton[0] = Html::a('<i class="fas fa-sync-alt"></i>Дневник-шпаргалка', ['/wb-order/index?WbOrderSearch[nm_id]=526443466'],  ['class' => 'btn btn-panel'] );
    $QFButton[1] = Html::a('<i class="fas fa-sync-alt"></i>Календарь', ['/wb-order/index?WbOrderSearch[nm_id]=135462932'],  ['class' => 'btn btn-panel'] );
    
}

$PanelButtons = "";
foreach ($QFButton as $str) {
    $PanelButtons .= $str;
}

/*
        'heading' => 'Заголовок таблицы',
        'footer' => 'Футер таблицы',
        'headingOptions' => ['class' => 'custom-heading-class'], // Свои классы для шапки
        'footerOptions' => ['class' => 'custom-footer-class'],   // Свои классы для футера
        'before' => '<em>Любой HTML перед таблицей</em>',
        'after' => '<em>Любой HTML после таблицы</em>',
*/
?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,
        
        'export' => [
            'showConfirmAlert' => false,
            'target' => GridView::TARGET_BLANK
        ],
        'exportConfig' => [
            GridView::EXCEL => ['label' => 'Сохранить в Excel'],
        ],

        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Заказы WB',
            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            'before' => $PanelButtons, //  . ' ' . $densityLinks. ' ' . $unusedFilter
            'footer' => false,
            'after' => false,

        ],
        'showPageSummary' => true,

        'containerOptions' => [
            'class' => 'no-border-class' 
        ],
        'columns' => [
//            ['class' => 'yii\grid\SerialColumn'],
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
                'attribute' => 'nm_id',
                'label' => 'Арт WB',
                'format' => 'raw', 
                'headerOptions'  => ['style' => 'width:90px'],
                'contentOptions' => ['style' => 'width:90px; white-space: nowrap; align-content: center; text-align: center;'],

                'value' => function($model) {
                    if (!$model->nm_id) {
                        return null;
                    }
                    // Генерируем ссылку
                    return Html::a(
                        (string)$model->nm_id, 
                        "https://www.wildberries.ru/catalog/" . $model->nm_id . "/detail.aspx", 
                        [
                            'target' => '_blank',
                            'data-pjax' => '0', 
                            'style' => 'text-decoration: underline;'
                        ]
                    );
                },
            ],
            [
                'attribute' => 'supplier_article',
                'headerOptions'  => ['style' => 'width:180px'],
                'contentOptions' => ['style' => 'width:180px; white-space: nowrap; align-content: center; text-align: center;'],
            ],
/*
            [
                'attribute' => 'subject',
                'headerOptions'  => ['style' => 'width:80px'],
                'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: left;'],
            ],
            [
                'attribute' => 'brand',
                'headerOptions'  => ['style' => 'width:180px'],
                'contentOptions' => ['style' => 'width:180px; white-space: wrap; align-content: center; text-align: left;'],
            ],
*/
            [
                    'attribute' => 'cardTitle',
                    'label' => 'Товар / Детали заказа',
                    'headerOptions'  => ['style' => 'width:330px'],
                    'contentOptions' => ['style' => 'min-width:330px; white-space: wrap; '],
                    'format' => 'raw',
                    'value' => function($model) {
                        // Верхний уровень: Название товара
                        $title = Html::tag('div', $model->card->title ?? '—', [
                            'style' => 'font-weight: bold; font-size: 13px; margin-bottom: 8px; color: #2c3e50;'
                        ]);

                        // Нижний уровень: Цены и склад в ряд
                        $details = Html::tag('div', 
/*
                            "Цена в карт: <b>{$model->total_price} ₽</b> | " .
                            "Цена со ск: <b>{$model->price_with_disc} ₽</b> | " .
*/
                            "<b>{$model->subject}</b> | <b>{$model->brand}</b> </br> ".
                            "Склад: <b>{$model->warehouse_name} ({$model->warehouse_type})</b>",
                            ['style' => 'color: #666; font-size: 11px;']
                        );

                        return $title . $details;
                    },
            ],
            [
                'attribute' => 'total_price',
                'label' => 'Цена в карт.',
                'headerOptions'  => ['style' => 'width:80px'],
                'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
            ],
            [
                'attribute' => 'discount_percent',
                'label' => 'Скидка',
                'headerOptions'  => ['style' => 'width:80px'],
                'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
            ],
            [
                'attribute' => 'price_with_disc',
                'label' => 'Цена со ск.',
                'headerOptions'  => ['style' => 'width:80px'],
                'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
            ],
            [
                'attribute' => 'spp',
                'label' => 'СПП',
                'headerOptions'  => ['style' => 'width:80px'],
                'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
            ],            [
                'attribute' => 'finished_price',
                'label' => 'Цена',
                'headerOptions'  => ['style' => 'width:80px'],
                'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
            ],
            [
                'attribute' => 'country_name',
                'label' => 'Страна / регион',
                    'headerOptions'  => ['style' => 'width:200px'],
                    'contentOptions' => ['style' => 'min-width:200px; white-space: wrap; align-content: center; text-align: left;'],
                    'format' => 'raw',
                    'value' => function($model) {
                        return Html::tag('div', ($model->country_name ?? '—').', '.($model->oblast_okrug_name ?? '—').', '.($model->region_name ?? '—') , [
                            'style' => 'font-size: 12px; margin-bottom: 8px; color: #2c3e50; align-content: center; text-align: left;'
                        ]);
                    },

            ],
            [
                'attribute' => 'is_cancel',
                'headerOptions'  => ['style' => 'width:100px'],
                'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: center;'],
                'label' => 'Статус',
                'value' => function($model) {
                    return $model->is_cancel ? 'Отмена' : 'Ок';
                },
                'filter' => [0 => 'Ок', 1 => 'Отмена'],
            ],
//            'srid',
//            'g_number',
            
        ],
    ]); ?>
<style>
#w0 .border-primary {
    border-color: var(--bs-border-color-translucent) !important;
}
</style>
</div>