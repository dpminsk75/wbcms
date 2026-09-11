<?php
use kartik\grid\GridView;
/* @var $OrdersSummaryProvider yii\data\ArrayDataProvider */
?>
        <div class="mobile-hide-block">
        <div class="grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_65" style="margin-bottom: 25px;">
        <?php
        echo GridView::widget([
            'dataProvider' => $OrdersSummaryProvider,
            'summary' => false,
            'export' => false, 
            'pjax' => false,
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
            'panel' => [
                'type' => GridView::TYPE_PRIMARY,
                'heading' => 'Сумма заказов за последние 30 дней',
                'headingOptions' => ['class' => 'card-header text-white bg-wb-blue-header'],
                'before' => false,
                'after' => false,
                'footer' => false,
//                'footer' => false,
            ],
            'containerOptions' => [
                'class' => 'no-border-class' 
            ],
            'columns' => [
                [
                    'attribute' => 'price_type',
                    'label' => 'Показатель',
                    'format' => 'raw',
                    'headerOptions' => ['style' => 'text-align: left; vertical-align: middle;'],
                    'contentOptions' => ['style' => 'text-align: left; color: #2c3e50; vertical-align: middle; width:200px;'],
                ],
                [
                    'attribute' => 'ieri',
                    'label' => 'Вчера',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'width: 140px; text-align: center;'],
                    'contentOptions' => ['style' => 'vertical-align: middle;'],
                    'value' => function($model) { return $model['ieri'] ?? 0; }
                ],
                [
                    'attribute' => 'pazyera',
                    'label' => 'Позавчера',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'width: 140px; text-align: center;'],
                    'contentOptions' => ['style' => 'vertical-align: middle;'],
                    'value' => function($model) { return $model['pazyera'] ?? 0; }
                ],
                [
                    'attribute' => 'past_7_days',
                    'label' => 'Прошедшие 7 дней',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'width: 150px; text-align: center;'],
                    'contentOptions' => ['style' => 'vertical-align: middle;'],
                    'value' => function($model) { return $model['past_7_days'] ?? 0; }
                ],
                [
                    'attribute' => 'week_before',
                    'label' => 'Предыдущая неделя',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'width: 150px; text-align: center;'],
                    'contentOptions' => ['style' => 'color: #666; vertical-align: middle;'],
                    'value' => function($model) { return $model['week_before'] ?? 0; }
                ],
                [
                    'attribute' => 'past_30_days',
                    'label' => 'Прошедшие 30 дней',
                    'format' => ['decimal', 2],
                    'hAlign' => 'right',
                    'headerOptions' => ['style' => 'width: 150px; text-align: center;'],
                    'contentOptions' => ['style' => 'color: #2c3e50; vertical-align: middle;'],
                    'value' => function($model) { return $model['past_30_days'] ?? 0; }
                ],
            ],
        ]);
        ?>
        </div>
        </div>
