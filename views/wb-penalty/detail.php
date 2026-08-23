<?php
use kartik\grid\GridView;
use app\components\UniversalFilterWidget; 

$this->title = 'Детализация логистики';
?>

<div class="row mb-4">
    <div class="col-12">
        <?= UniversalFilterWidget::widget([
            'action'        => ['detail'],
            'attribute'     => 'nmID', 
            'initValueText' => $initValueText, // Теперь виджет покажет выбранный товар
            'defaultDateFrom' => $dateFrom,
            'defaultDateTo'   => $dateTo,
            'ajaxUrl'       => \yii\helpers\Url::to(['/helper/search-cards']), 
        ]) ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel'  => $searchModel,
            'pjax'         => true,
            'panel'        => ['type' => GridView::TYPE_DANGER, 'heading' => 'Детализация записей'],
            'columns'      => [
//                ['attribute' => 'rr_dt', 'label' => 'Дата', 'format' => ['datetime', 'php:d.m.Y H:i']],

                [
                    'attribute' => 'rr_dt',
                    'label' => 'Дата',
                    // Используем формат 'date' вместо 'datetime'
                    'format' => ['date', 'php:d.m.Y'], 
                    'headerOptions' => ['style' => 'width:120px;'], // Опционально: сужаем колонку
                ],

                // Разделили артикул и название, чтобы не ломать фильтрацию
                ['attribute' => 'nm_id', 'label' => 'Артикул'],
                ['attribute' => 'card_name', 'label' => 'Товар'],

                [
                    'attribute' => 'office_name',
                    'label'     => 'Склад',
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter'     => $filterLists['offices'] ?? [],
                    'filterWidgetOptions' => [
                        'pluginOptions' => ['allowClear' => true],
                        'options'       => ['placeholder' => 'Все']
                    ],
                ],
                [
                    'attribute' => 'bonus_type_name',
                    'label'     => 'Причина',
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter'     => $filterLists['reasons'] ?? [],
                    'filterWidgetOptions' => [
                        'pluginOptions' => ['allowClear' => true],
                        'options'       => ['placeholder' => 'Все']
                    ],
                ],

                [
                    'attribute' => 'delivery_rub', 
                    'label'     => 'Сумма', 
                    'format'    => ['decimal', 2], 
                    'pageSummary' => true
                ],
            ],
            'showPageSummary' => true,
        ]); ?>
    </div>
</div>