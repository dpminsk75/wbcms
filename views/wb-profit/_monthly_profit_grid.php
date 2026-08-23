<?php
use kartik\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ArrayDataProvider */
?>

<div class="row grid_wbstat" style="margin-bottom: 25px;">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'pjax' => false,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,
        'showPageSummary' => true,
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
            '{export}',
        ],
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Финансовая аналитика (с 2025 года)',
            'headingOptions' => ['class' => 'card-header text-white bg-wb-blue-header'],
            'footer' => false,
            'after' => false,
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],
        'columns' => [
            [
                'attribute' => 'month',
                'label' => 'Месяц',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; width: 90px;'],
                'contentOptions' => ['style' => 'text-align: center; font-weight: bold; color: #2c3e50; vertical-align: middle;'],
                'pageSummary' => 'Итого',
                'pageSummaryOptions' => ['style' => 'text-align: center; font-weight: bold;']
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
                'label' => 'Продажи',
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
            [
                'attribute' => 'f_acquiring_fee',
                'label' => 'Эквайринг',
                'format' => ['decimal', 0],
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'f_acceptance',
                'label' => 'Приемка',
                'format' => ['decimal', 0],
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'f_delivery',
                'label' => 'Логистика',
                'format' => ['decimal', 0],
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b; font-weight: 500;'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'f_storage_fee',
                'label' => 'Хранение',
                'format' => ['decimal', 0],
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b; font-weight: 500;'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'f_penalty',
                'label' => 'Штрафы',
                'format' => 'raw',
                'hAlign' => 'right',
                'value' => function ($model) {
                    if (empty($model['f_penalty'])) return '0.00';
                    $dateFrom = $model['month'] . '-01'; 
                    $dateTo = date('Y-m-t', strtotime($dateFrom));
                    $nmId = $model['nmID'] ?? $model['nm_id'] ?? null;
                    $formattedValue = Yii::$app->formatter->asDecimal($model['f_penalty'], 0);

                    return \yii\helpers\Html::a($formattedValue, '#', [
                        'class' => 'trigger-detail-popup',
                        'style' => 'color: #c0392b; text-decoration: underline;',
                        'data-from' => $dateFrom,
                        'data-to' => $dateTo,
                        'data-nmid' => $nmId,
                        'data-type' => 'shf',
                    ]);
                }
            ],
            [
                'attribute' => 'f_deduction',
                'label' => 'Удержания',
                'format' => 'raw',
                'hAlign' => 'right',
                'value' => function ($model) {
                    if (empty($model['f_deduction'])) return '0.00';
                    $dateFrom = $model['month'] . '-01'; 
                    $dateTo = date('Y-m-t', strtotime($dateFrom));
                    $nmId = $model['nmID'] ?? $model['nm_id'] ?? null;
                    $formattedValue = Yii::$app->formatter->asDecimal($model['f_deduction'], 0);

                    return \yii\helpers\Html::a($formattedValue, '#', [
                        'class' => 'trigger-detail-popup',
                        'style' => 'color: #c0392b; text-decoration: underline;',
                        'data-from' => $dateFrom,
                        'data-to' => $dateTo,
                        'data-nmid' => $nmId,
                        'data-type' => 'udr',
                    ]);
                }
            ],
            [
                'attribute' => 'f_otziv',
                'label' => 'Отзывы',
                'format' => 'raw',
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                'contentOptions' => ['style' => 'vertical-align: middle; color: #16a085;'],
                'pageSummary' => true,
                'value' => function ($model) {
                    if (empty($model['f_otziv'])) return 0;
                    $dateFrom = $model['month'] . '-01'; 
                    $dateTo = date('Y-m-t', strtotime($dateFrom));
                    $nmId = $model['nmID'] ?? $model['nm_id'] ?? null;
                    $formattedValue = Yii::$app->formatter->asDecimal($model['f_otziv'], 0);

                    return \yii\helpers\Html::a($formattedValue, '#', [
                        'class' => 'trigger-feedback-popup',
                        'style' => 'color: #16a085; font-weight: bold; text-decoration: underline;',
                        'data-from' => $dateFrom,
                        'data-to' => $dateTo,
                        'data-nmid' => $nmId,
                    ]);
                }
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
                'format' => 'integer',
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                'contentOptions' => ['style' => 'vertical-align: middle; color: #c0392b;'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'net_profit',
                'label' => 'Общий итог',
                'format' => 'integer',
                'hAlign' => 'right',
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; background-color: #e8f8f5; color: #111;'],
                'contentOptions' => [
                    'style' => 'vertical-align: middle; font-weight: bold; color: #27ae60; background-color: #f4fbf7;'
                ],
                'pageSummary' => true,
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
        ],
    ]); ?>
</div>