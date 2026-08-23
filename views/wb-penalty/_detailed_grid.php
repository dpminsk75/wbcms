<?php
use kartik\grid\GridView;
use yii\helpers\Url;

echo GridView::widget([
    'id' => 'grid-detailed-inner',
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    // Эта строка заставляет фильтры работать внутри PJAX:
    'filterUrl' => Url::to(['/wb-penalty/logistics', '_target' => 'detailed', 'date_from' => $dateFrom, 'date_to' => $dateTo]),
    'pjax' => true,
    'pjaxSettings' => ['options' => ['id' => 'pjax-detailed', 'enablePushState' => false]],
    'panel' => ['type' => GridView::TYPE_DANGER, 'heading' => 'Детальные записи'],
    'columns' => [
        ['attribute' => 'rr_dt', 'label' => 'Дата', 'format' => ['datetime', 'php:d.m.Y H:i']],
        [
            'attribute' => 'nm_id',
            'label' => 'Товар',
            'filterType' => GridView::FILTER_SELECT2,
            'filter' => $filterLists['products'],
            'filterWidgetOptions' => [
                'pluginOptions' => ['allowClear' => true],
                'options' => ['placeholder' => 'Все'],
            ],
            'format' => 'raw',
            'value' => function($model) {
                return $model->nm_id . '<br><small class="text-muted">' . ($model->card_name ?? '') . '</small>';
            }
        ],
        [
            'attribute' => 'office_name',
            'label' => 'Склад',
            'filterType' => GridView::FILTER_SELECT2,
            'filter' => $filterLists['offices'],
            'filterWidgetOptions' => [
                'pluginOptions' => ['allowClear' => true],
                'options' => ['placeholder' => 'Все'],
            ],
        ],
        [
            'attribute' => 'bonus_type_name',
            'label' => 'Причина',
            'filterType' => GridView::FILTER_SELECT2,
            'filter' => $filterLists['reasons'],
            'filterWidgetOptions' => [
                'pluginOptions' => ['allowClear' => true],
                'options' => ['placeholder' => 'Все'],
            ],
        ],
        ['attribute' => 'delivery_rub', 'label' => 'Сумма', 'format' => ['decimal', 2], 'pageSummary' => true],
        'rid',
    ],
    'showPageSummary' => true,
]);