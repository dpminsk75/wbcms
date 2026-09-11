<?php
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\grid\GridView;
use kartik\icons\Icon;
Icon::map($this);
/* @var $AdvProvider yii\data\ArrayDataProvider */
/* @var $dateFrom string */
/* @var $dateTo string */
?>

<?php
$statusMap = [
    -1 => ['label' => 'Удалена', 'class' => 'label-default'],
    4  => ['label' => 'Готова к запуску', 'class' => 'label-info'],
    7  => ['label' => 'Завершена', 'class' => 'label-primary'],
    8  => ['label' => 'Отклонена', 'class' => 'label-danger'],
    9  => ['label' => 'Активна', 'class' => 'label-success'],
    11 => ['label' => 'Пауза', 'class' => 'label-warning'],
];

    $allStats = $AdvProvider->allModels;
    $totalViews = array_sum(array_column($allStats, 'views'));
    $totalClicks = array_sum(array_column($allStats, 'clicks'));
    $totalSum = array_sum(array_column($allStats, 'sum'));
    $totalOrders = array_sum(array_column($allStats, 'orders'));
    $totalCanceled = array_sum(array_column($allStats, 'canceled'));
    $totalOC = $totalOrders - $totalCanceled;

    $totalCtr = $totalViews > 0 ? number_format(($totalClicks / $totalViews) * 100, 2) . '%' : '0.00%';
    $totalCPM = $totalOrders > 0 ? number_format(($totalSum / $totalViews * 1000),2) : '';
    $totalCPC = $totalOrders > 0 ? number_format(($totalSum / $totalClicks),2) : '';
    $totalCPO = $totalOC > 0 ? number_format(($totalSum / $totalOC),2) : '';
?>
<?php
$columns = [
            [
                'attribute' => 'name',
                'label' => 'Компания',
                'format' => 'raw', 
                'headerOptions'  => ['style' => 'width:200px; text-align: center;'],
                'contentOptions' => ['style' => 'width:200px; white-space: nowrap; align-content: center; text-align: left;'],
                'value' => function($model) {
                    if (!$model['name']) {
                        return null;
                    }
                    // Генерируем ссылку
                    return Html::a(
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
                    'headerOptions'  => ['style' => 'width:50px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:50px; white-space: nowrap; align-content: center; text-align: center;'],
                    'hAlign' => 'right',
                    'format' => 'raw',
                    'value' => function($model) {
                        // Просто вызываем метод из модели
                        return \app\models\WbCampaign::renderStatusLabel($model['status']);
                    }
                ],
                [
                    'attribute' => 'views',
                    'label' => 'Показы',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true, // Просто сумма
                ],
                [
                    'attribute' => 'clicks',
                    'label' => 'Клики',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true, // Просто сумма
                ],
                [
                    'attribute' => 'atbs',
                    'label' => 'Корзины',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true, // Просто сумма
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
                    'attribute' => 'canceled',
                    'label' => 'Отмена',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'format' => ['decimal', 0],
                    'hAlign' => 'right',
                    'pageSummary' => true,
                ],

                [
                    'attribute' => 'ctr',
                    'label' => 'CTR, %',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'value' => function ($model) {
                        return $model['views'] > 0 ? round(($model['clicks']  / $model['views']) * 100, 2) : '';
        //                return number_format($model['ctr'], 2) . '%';
                    },
                    'hAlign' => 'right',
                    'pageSummary' => $totalCtr, // Используем заранее вычисленное значение
                ],
                [
                    'attribute' => 'cr',
                    'label' => 'CR, %',
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
                    'value' => function ($model) {
                        return $model['clicks'] > 0 ? round(($model['atbs']  / $model['clicks']) * 100, 2) : '';
        //                return number_format($model['cr'], 2) . '%';
                    },
                    'hAlign' => 'right',
                    'pageSummary' => $totalCtr, // Используем заранее вычисленное значение
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
                    'label' => 'CPM, ₽', 
                    'format' => ['decimal', 2],
                    'pageSummary' => $totalCPM, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model['views'] > 0 ? round(($model['sum']  / $model['views'])*1000, 2) : '';
                    }
                ],
                [
                    'label' => 'CPC, ₽', 
                    'format' => ['decimal', 2],
                    'pageSummary' => $totalCPC, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model['clicks'] > 0 ? round(($model['sum']  / $model['clicks']), 2) : '';
                    }
                ],
                [
                    'label' => 'CPO ₽', 
                    'format' => ['decimal', 2],
                    'pageSummary' => $totalCPO, 'pageSummaryOptions' => ['class' => 'text-right'],
                    'headerOptions'  => ['style' => 'width:100px; text-align: center;'],
                    'contentOptions' => ['style' => 'width:100px; white-space: nowrap; align-content: center; text-align: right;'],
//                    'pageSummary' => GridView::F_AVG, 
                    'value' => function($model) {
                        return $model['orders'] > 0 ? round(($model['sum']  / $model['orders'] ), 2) : '';
                    }
                ],
    ];
?>

<div class="mobile-hide-block">
<div class="grid_advstat grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_65 expandable-container">
<?php
echo GridView::widget([
    'dataProvider' => $AdvProvider,
        'export' => false, 
        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'resizableColumns' => true,
        'hover' => true,
        'krajeeDialogSettings' => ['overrideYiiConfirm' => false],
        'showPageSummary' => false,
        'showFooter' => false,

        'toggleData' => false,
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
//            'after' => '<div class="float-right">{pager}</div>',
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],

    'columns' => $columns,
]);
?>
</div>
<div class="expand-btn-wrapper">
    <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
</div>
</div>
