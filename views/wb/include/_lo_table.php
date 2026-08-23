<?php // expandable-container
use yii\helpers\Html;
use kartik\grid\GridView;
use kartik\select2\Select2;

	$allStatsLO  = $LastOrdersProvider->allModels;
    $totalLO_SUM = array_sum(array_column($allStatsLO, 'sum'));
    $totalLO_CNT = array_sum(array_column($allStatsLO, 'cnt'));
    $totalLO_CNS = array_sum(array_column($allStatsLO, 'cns'));


$columns = [
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view}',
                'urlCreator' => function ($action, $model, $key, $index) {
                    if ($action === 'view') {
                        return \yii\helpers\Url::to([
                            'wb-order/index', 
                            'WbOrderSearch[nm_id]' => $model['nm_id'], 
                            'WbOrderSearch[date]' => $model['odate']
                        ]);
                    }
                }
            ],
			[
                'attribute' => 'odate',
                'label' => 'Дата',
                'format' => ['datetime', 'php:d.m.Y'],
//                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => function($model) { return 'Итого';
                 }, 'pageSummaryOptions' => ['class' => 'text-right'],
            ],
            [
                'attribute' => 'cnt',
                'label' => 'Кол-во',
                'hAlign' => 'right',
                'format' => ['decimal', 0],
                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => number_format($totalLO_CNT, 0, ',', ' '),
            ],
            [
                'attribute' => 'cns',
                'label' => 'Отмена',
                'hAlign' => 'right',
                'format' => ['decimal', 0],
                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => number_format($totalLO_CNS, 0, ',', ' '),
            ],
            [
                'attribute' => 'sum',
                'label' => 'Сумма, ₽',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => number_format($totalLO_SUM, 1, ',', ' '),
            ],

            [
                'attribute' => 'tp',
                'label' => 'Цена Рзн, ₽',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
            ],
            [
                'attribute' => 'dsc',
                'label' => 'Скидка, %',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
            ],
            [
                'attribute' => 'apwd',
                'label' => 'Цена со ск, ₽',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
            ],
            [
                'attribute' => 'spp',
                'label' => 'СПП, %',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
            ],
            [
                'attribute' => 'finished_price',
                'label' => 'Цена зкз, ₽',
                'headerOptions'  => ['style' => 'width:80px'],
                'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
            ],
];
?>
<?php
echo GridView::widget([
    'dataProvider' => $LastOrdersProvider,
        'containerOptions' => ['class' => 'custom-compact-grid'],
            'export' => [
                'showConfirmAlert' => false,
                'target' => GridView::TARGET_BLANK
            ],
            'exportConfig' => [
                GridView::EXCEL => ['label' => 'Сохранить в Excel'],
            ],
        'toggleData' => false,
        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,

        'showPageSummary' => true,
	    'pageSummaryPosition' => GridView::POS_TOP, 
// Если POS_TOP не сработал, используем layout:
//    'layout' => "{summary}\n{items}\n{pageSummary}\n{pager}", 

        'showFooter' => false,



        'toggleData' => true,
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Заказы (c '.Yii::$app->formatter->asDate($dateFrom, 'd MMM y').')',
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