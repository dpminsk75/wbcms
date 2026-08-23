<?php
use kartik\grid\GridView;
use kartik\select2\Select2; // Импортируем виджет
use yii\helpers\Html;
use yii\widgets\Pjax;
use yii\web\YiiAsset;
?>
<?php Pjax::begin(); ?> 
<?= GridView::widget([
        'dataProvider' => $dataProvider,

        'pjax' => true,

'pjaxSettings' => [
    'options' => [
        'id' => 'pjax-container-id', // Явно задаем ID
        'enablePushState' => false,   // Часто помогает избежать проблем с URL
    ],
],

        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,
        'showPageSummary' => true,

        'panel' => [
            'heading' => 'Воронка - Переход / Корзина / Заказ WB',
            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
        ],
        'containerOptions' => [
            'class' => 'no-border-class funnel_table' 
        ],

            'columns' => [
                [ 'attribute' => 'date', 'label' => 'Дата', 
                    'format' => ['datetime', 'php:d.m.Y'],
                    'headerOptions'  => ['style' => 'width:130px; align-content: center; text-align: center;'],
                    'contentOptions' => ['style' => 'width:130px; white-space: nowrap; align-content: center; text-align: center;'],
                ],
                [ 'attribute' => 'nmId', 'label' => 'Артикул WB', 'format' => 'raw',
                    'headerOptions'  => ['style' => 'width:90px'],
                    'contentOptions' => ['style' => 'width:90px; white-space: nowrap; align-content: center; text-align: center;'],

                'value' => function($model) {
                    if (!$model->nmId) {
                        return null;
                    }
                    // Генерируем ссылку
                    return Html::a(
                        (string)$model->nmId, 
                        "/wb-order/index?WbOrderSearch[nm_id]=" . $model->nmId . "&WbOrderSearch[date]=". $model->date , 
                        [
                            'target' => '_blank',
                            'data-pjax' => '0', 
                            'style' => 'text-decoration: none;'
                        ]
                    );
                },


            ],
                [ 'attribute' => 'openCount',  'label' => 'Переходов',  
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'hAlign' => 'right',
                    'pageSummary' => true, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'format' => ['decimal', 0],
                ],
                [ 'attribute' => 'cartCount',  'label' => 'Корзин',  
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'hAlign' => 'right',
                    'pageSummary' => true, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'format' => ['decimal', 0],
                ],
                [
                    'label' => 'CR, %', 
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model->openCount > 0 ? round(($model->cartCount / $model->openCount) * 100, 2) . '%' : '-';
                    }
                ],
                [ 'attribute' => 'orderCount', 'label' => 'Заказов', 
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'hAlign' => 'right',
                    'pageSummary' => true, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'format' => ['decimal', 0],
                ],
                [
                    'label' => 'CR в заказ, %', 
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model->cartCount > 0 ? round(($model->orderCount / $model->cartCount) * 100, 2) . '%' : '-';
                    }
                ],
                [
                    'attribute' => 'orderSum', 'label' => 'Сумма заказов', 
                    'headerOptions'  => ['style' => 'width:80px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                    'hAlign' => 'right',
                    'pageSummary' => true, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'format' => ['decimal', 2],
                ],

                [
                    'label' => 'Ср цена, руб', 
                    'headerOptions'  => ['style' => 'width:80px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                    'hAlign' => 'right',
                    'pageSummary' => function ($summary, $data, $widget) {
                        // $data — это уже массив всех 'orderSum' на этой странице
                        if (empty($data)) {
                            return 0;
                        }
                        return round(array_sum($data) / count($data),2); // Ваше среднее
                    },
                    'format' => ['decimal', 2],
                    'value' => function($model) {
                        return $model->orderCount > 0 ? round(($model->orderSum / $model->orderCount), 2) : 0;
                    }
                ],

                [ 'attribute' => 'buyoutCount', 'label' => 'Выкупили', 
                    'pageSummary' => true, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:80px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                ],

                [
                    'label' => 'SR, %', 
//                    'pageSummary' => GridView::F_AVG, 
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],

                    'value' => function($model) {
                        return $model->orderCount > 0 ? round(($model->buyoutCount / $model->orderCount) * 100, 2) . '%' : '-';
                    }
                ],

                [
                    'attribute' => 'buyoutSum', 'label' => 'Сумма выкупов', 
                    'pageSummary' => true, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:80px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 2],
                ],
            ],
        ]); 
        ?>
<?php Pjax::end(); ?> 
        