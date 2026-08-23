<?php // expandable-container
use yii\helpers\Html;
use kartik\grid\GridView;
use kartik\select2\Select2;



$columns = [
            [
                'attribute' => 'name',
                'label' => 'Компания',
                'format' => 'raw', 
                'headerOptions'  => ['style' => 'width:350px; text-align: center;'],
                'contentOptions' => ['style' => 'width:350px; white-space: nowrap; align-content: center; text-align: left;'],
                'value' => function($model) {

                $icon = $model['adv'] == 1 
                    ? '<i class="bi bi-arrow-right-circle text-danger" title="Реклама"></i> ' 
                    : '<i class="bi bi-arrow-repeat text-muted" title="Органика"></i> ';

                    // Генерируем ссылку
                    return $icon.' '.Html::a(
                        (string)$model['name'], 
                        "/wb-adv-report/index?id=" . $model['campaign_id'], 
//                        /wb-adv-report/index?id=33656377&dateFrom=2026-02-04&dateTo=2026-02-18
                        [
                            'target' => '_blank',
                            'data-pjax' => '0', 
                            'style' => 'text-decoration: none;'
                        ]
                    );
                },

            ],
                [
                    'attribute' => 'status',
                    'label' => 'Ст',
                    'headerOptions'  => ['style' => 'width:80px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: center;'],
                    'hAlign' => 'right',
                    'format' => 'raw',
                    'value' => function($model) {
                        // Просто вызываем метод из модели
                        return \app\models\WbCampaign::renderStatusLabel($model['status']);
                    }
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
                    'attribute' => 'sum_price',
                    'label' => 'Σ зак, ₽',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],

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
                    'label' => 'CPO ₽', 
                    'format' => ['decimal', 2],
//                    'pageSummary' => $totalCPO, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model['orders'] > 0 ? round(($model['sum']  / $model['orders'] ), 2) : '';
                    }
                ],

];
?>
<?php
echo GridView::widget([
    'dataProvider' => $AdvProvider,
        'containerOptions' => ['class' => 'custom-compact-grid'],
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

        'toggleData' => true,
//        'layout' => "{summary}\n{items}\n{pager}",
//        'pager' => [
//            'options' => ['class' => 'pagination'],
//            'maxButtonCount' => 5,
//        ],

        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Реклама (c '.Yii::$app->formatter->asDate($dateFrom, 'd MMM y').')',
            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            'footer' => false,
            'after' => false,
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],

    'columns' => $columns,
]);
?>
