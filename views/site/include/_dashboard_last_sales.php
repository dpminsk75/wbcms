<?php
use yii\helpers\Html;
use kartik\grid\GridView;
/* @var $LastSalesProvider yii\data\ArrayDataProvider */
?>
<?php
    $allStatsLS = $LastSalesProvider->allModels;

    $totalLS_CNT   = array_sum(array_column($allStatsLS, 'cnt'));
    $totalLS_PWD   = array_sum(array_column($allStatsLS, 'pwd'));
    $totalLS_FP    = array_sum(array_column($allStatsLS, 'fp'));
    $totalLS_FPay  = array_sum(array_column($allStatsLS, 'forpay'));
?>
<?php
$columnsLS = [
            [
                    'attribute' => 'cardTitle',
                    'label' => 'Товар / Артикул',
                    'headerOptions'  => ['style' => 'width:350px'],
                    'contentOptions' => ['style' => 'min-width:350px; white-space: wrap; '],
                    'format' => 'raw',
                    'value' => function($model) {
                        // Верхний уровень: Название товара
                        $title = Html::tag('div', $model['title'] ?? '—', [
                            'style' => 'font-weight: bold; font-size: 12px; margin-bottom: 2px; color: #2c3e50;'
                        ]);
                        $details = Html::tag('div', 
                            "Артикул: <b>{$model['vendorCode']}</b> Арт WB: ". 
//                            http://31.130.204.146/wb-order/index?WbOrderSearch[nm_id]=50308273
                            Html::a((string)$model['nm_id'], "/wb/detail?DPFilterForm[nm_id]=".$model['nm_id'], ['title' => 'Перейти в карточку', 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ])

                            ,
                            ['style' => 'color: #666; font-size: 11px;']
                        );

                        return $title . $details;
                    },
                    'pageSummary' => function($model) use ($totalLS_CNT, $totalLS_PWD, $totalLS_FP, $totalLS_FPay)  {
                        return "Итого <b>$totalLS_CNT</b> пр | Σ в РЦ: <b>" . number_format($totalLS_PWD, 1, ',', ' ') . "</b> | Σ продажи: <b>".number_format($totalLS_FP, 1, ',', ' '). "</b> | WB нам: <b>".number_format($totalLS_FPay, 1, ',', ' ')."</b>";
                    }, 'pageSummaryOptions' => ['class' => 'text-right fw-normal', 'colspan' => 2, 'style' => 'font-weight: 400 !important;'],
            ],

            [
                'attribute' => 'cnt',
                'label' => 'Кол-во',
                'hAlign' => 'right',
//                'format' => ['decimal', 0],
                'headerOptions'  => ['style' => 'width:90px'],
                'contentOptions' => ['align-content: center; text-align: right; width:90px;'],
//                'pageSummary' => true, 
                'format' => 'raw',
                'pageSummary' => false,
                'value' => function($model) {
                    $title = Html::tag('div',$model['cnt'] ?? "—", ['style' => 'font-weight: bold; font-size: 12px; margin-bottom: 2px; text-align: center;']);
                    $details = Html::tag('div',($model['cnt_3'] ?? "—") . " | ".($model['cnt_2'] ?? "—") . " | ". ($model['cnt_1'] ?? "—") . " | ". ($model['cnt_0'] ?? "—"), ['style' => 'font-size: 12px; margin-bottom: 2px; color: #2c3e50; text-align: center;']);
//                    return $title . $details;
                    return Html::a((string)$title . $details, "/wb-sales/index?WbSalesSearch[nmId]=".$model['nm_id'], [ 'title' => 'Перейти к продажам', 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ]);

                }


            ],
            [
                'attribute' => 'pwd',
                'label' => 'Σ в РЦ',
                'hAlign' => 'right',
                'format' => ['decimal', 1],
                'contentOptions' => ['align-content: center; text-align: right;'],
                'pageSummary' => true, 
            ],
            [
                'attribute' => 'fp',
                'label' => 'Σ к опл',
                'hAlign' => 'right',
                'format' => ['decimal', 0],
                'contentOptions' => ['align-content: center; text-align: right;'],
                'pageSummary' => true, 
            ],
            [
                'attribute' => 'forpay',
                'label' => 'Σ WB нам',
                'hAlign' => 'right',
                'format' => ['decimal', 0],
                'contentOptions' => ['align-content: center; text-align: right;'],
                'pageSummary' => true, 
            ],
            [
                'attribute' => 'apwd',
                'label' => 'Цена',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['align-content: center; text-align: right;'],
            ],
            [
                'attribute' => 'aspp',
                'label' => 'СПП',
                'hAlign' => 'right',
                'format' => ['decimal', 1],
                'contentOptions' => ['align-content: center; text-align: right;'],
//                'pageSummary' => true, 
//                'pageSummaryFunc' => GridView::F_AVG,
            ],
            [
                'attribute' => 'afp',
                'label' => 'Цена пр.',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['align-content: center; text-align: right;'],
            ],
            [
                'attribute' => 'aforpay',
                'label' => 'К опл',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['align-content: center; text-align: right; style' => 'font-weight:bold;'],
            ],


    ];
?>
<div class="mobile-hide-block">
<div class="grid_lastsales grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_65 expandable-container">
<?php
echo GridView::widget([
    'dataProvider' => $LastSalesProvider,
        'export' => false, 
        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'resizableColumns' => true,
        'hover' => true,
        'krajeeDialogSettings' => ['overrideYiiConfirm' => false],

        'showPageSummary' => true,
        'pageSummaryPosition' => GridView::POS_TOP, 
        'showFooter' => false,

        'toggleData' => false,
//        'layout' => "{summary}\n{items}\n{pager}",
//        'pager' => [
//            'options' => ['class' => 'pagination'],
//            'maxButtonCount' => 5,
//        ],

        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Продажи (c '.Yii::$app->formatter->asDate($dateFrom, 'd MMM y').')',
            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            'after' => false,
            'footer' => false,
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],

    'columns' => $columnsLS,
]);
?>
</div>
    <div class="expand-btn-wrapper">
        <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
    </div>
</div>
