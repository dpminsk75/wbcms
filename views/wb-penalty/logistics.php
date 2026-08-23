<?php
use yii\widgets\Pjax;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\date\DatePicker;

use kartik\icons\Icon;
Icon::map($this); 

\kartik\select2\Select2Asset::register($this);

$this->title = 'Отчет по логистике';
?>

<div class="row mb-4">
    <div class="col-md-5 card shadow">
        <form id="date-filter-form" method="get" class="mb-0">
            <div class="row mb-3 p-3">
                    <label>Период отчета:</label>
                    <?= DatePicker::widget([
                        'name' => 'date_from',
                        'value' => $dateFrom,
                        'type' => DatePicker::TYPE_RANGE,
                        'name2' => 'date_to',
                        'value2' => $dateTo,
                        'separator' => '—',
                        'options' => [
                            'id' => 'date_from',
                            'placeholder' => 'Дата с',
                        ],
                        'options2' => [
                            'id' => 'date_to',
                            'placeholder' => 'Дата по',
                        ],
                        'pluginOptions' => [
                            'autoclose' => true,
                            'format' => 'yyyy-mm-dd'
                        ]
                    ]) ?>
            </div>
            <div class="row p-3" >
                <?= Html::submitButton('Обновить все', ['class' => 'btn btn-primary col-auto']) ?>
            </div>
        </form>
    </div>

    <div class="col-md-7 wb-penalty-logistics custom-compact-grid">
    <?= GridView::widget([
        'dataProvider' => $periodSummaryProvider,
        'panel' => [
            'type' => GridView::TYPE_PRIMARY, 
            'striped' => true,
            'condensed' => true,
            'showPageSummary' => true,
            'showFooter' => false,
             'footer' => false,
            'heading' => 'Итого за период',
            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
        ],
        'columns' => [
            ['attribute' => 'bonus_type_name', 'label' => 'Причина'],
            ['attribute' => 'items_count', 'label' => 'Кол-во'],
            ['attribute' => 'total_sum', 'label' => 'Сумма', 'format' => ['decimal', 2]],
        ],
    ]); ?>
    </div>
</div>

<div class="row mb-3">
    <div class="custom-compact-grid">
        <div class="grid_wbstat "> <!-- expandable-container -->
        <?= GridView::widget([
            'dataProvider' => $productSummaryProvider,
            'panel' => ['type' => GridView::TYPE_DEFAULT, 'heading' => 'Сводная по товарам'],
            'columns' => [
//                ['attribute' => 'nm_id',  'label' => 'Арт WB', ],
//                ['attribute' => 'card_name',  'label' => 'Название',],
                [
                    'attribute' => 'nm_id',
                    'label' => 'Арт WB',
                    'format' => 'raw',
                    'value' => function ($model) use ($dateFrom, $dateTo) {
                        $url = \yii\helpers\Url::to([
                            '/wb/detail',
                            'DPFilterForm[nm_id]'      => $model['nm_id'],
                            'DPFilterForm[date_from]' => $dateFrom, 
                            'DPFilterForm[date_to]'   => $dateTo,
                        ]);
                        // data-pjax="0" обязательно, чтобы переход был полноценным, а не пытался загрузиться внутри текущего pjax-блока
                        return \yii\helpers\Html::a($model['nm_id'], $url, ['data-pjax' => 0, 'target' => '_blank', 'class' => 'text-primary']);
                    }
                ],


                [
                    'attribute' => 'card_name',
                    'label' => 'Товар',
                    'format' => 'raw',
                    'value' => function ($model) use ($dateFrom, $dateTo) {
                        $url = \yii\helpers\Url::to([
                            '/wb-penalty/detail',
                            'DPFilterForm[nmID]'      => $model['nm_id'],
                            'DPFilterForm[date_from]' => $dateFrom,
                            'DPFilterForm[date_to]'   => $dateTo,
                        ]);
                        // data-pjax="0" обязательно, чтобы переход был полноценным, а не пытался загрузиться внутри текущего pjax-блока
                        return \yii\helpers\Html::a($model['card_name'], $url, ['data-pjax' => 0, 'target' => '_blank', 'class' => 'text-primary']);
                    }
                ],

                ['attribute' => 'items_count',          'label' => 'Кол-во',      'format' => ['decimal', 0],'hAlign' => 'right', 'pageSummary' => true,],
                ['attribute' => 'to_client_cancel',     'label' => 'Отмена (->)',  'format' => ['decimal', 2], 'hAlign' => 'right'],
                ['attribute' => 'from_client_cancel',   'label' => 'Отмена (<-)',  'format' => ['decimal', 2], 'hAlign' => 'right'],
                ['attribute' => 'from_client_return',   'label' => 'Возврат (<-)', 'format' => ['decimal', 2], 'hAlign' => 'right'],
                ['attribute' => 'defect_return',        'label' => 'Брак',        'format' => ['decimal', 2], 'hAlign' => 'right'],
                ['attribute' => 'other_sum',            'label' => 'Прочее',      'format' => ['decimal', 2], 'hAlign' => 'right'],
                ['attribute' => 'total_product_sum',    'label' => 'Итого',       'format' => ['decimal', 2], 'hAlign' => 'right'],
            ],
        ]); ?>
        </div>
<!--
        <div class="expand-btn-wrapper" style="margin-bottom: 5px;">
            <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
        </div>
--> 
    </div>
</div>

<?php /*
<div class="row mb-3">
    <div class="mb-3">
        <?= Html::button('Статистика по дням', ['class' => 'btn btn-info', 'onclick' => 'loadBlock("days")']) ?>
        <?= Html::button('Детализация', ['class' => 'btn btn-danger', 'onclick' => 'loadBlock("detailed")']) ?>
    </div>

    <?php Pjax::begin(['id' => 'pjax-days', 'enablePushState' => false, 'timeout' => 10000]); ?>
        <?php if ($target === 'days' && isset($daysDataProvider)): ?>
            <?= GridView::widget([
                'dataProvider' => $daysDataProvider,
                'panel' => ['type' => GridView::TYPE_INFO, 'heading' => 'Статистика по дням'],
                'columns' => [
                    ['attribute' => 'date_only', 'label' => 'Дата', 'group' => true],
                    ['attribute' => 'bonus_type_name', 'label' => 'Причина'],
                    ['attribute' => 'items_count', 'label' => 'Кол-во'],
                    ['attribute' => 'total_sum', 'label' => 'Сумма', 'format' => ['decimal', 2]],
                ],
            ]); ?>
        <?php else: ?>
            <div id="target-days"></div>
        <?php endif; ?>
    <?php Pjax::end(); ?>

    <?php Pjax::begin(['id' => 'pjax-detailed', 'enablePushState' => false, 'timeout' => 20000]); ?>
        <?php if ($target === 'detailed' && isset($detailedDataProvider)): ?>
            <?= GridView::widget([
                'dataProvider' => $detailedDataProvider,
                'filterModel' => $searchModel,
                'pjax' => false, // Выключаем внутренний, так как мы обернули снаружи
                'showPageSummary' => true,
                // Жизненно важно для фильтров, чтобы они не перезагружали всю страницу
                'filterUrl' => Url::to(['logistics', '_target' => 'detailed', 'date_from' => $dateFrom, 'date_to' => $dateTo]),
                'panel' => ['type' => GridView::TYPE_DANGER, 'heading' => 'Детализация'],
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],
                    [
                        'attribute' => 'rr_dt',
                        'format' => ['datetime', 'php:d.m.Y H:i'],
                        'filter' => false,
                    ],
                    [
                        'attribute' => 'nm_id',
                        'label' => 'Товар',
                        'filterType' => GridView::FILTER_SELECT2,
                        'filter' => $filterLists['products'],
                        'filterWidgetOptions' => [
                            'pluginOptions' => ['allowClear' => true, 'placeholder' => 'Выберите товар'],
                        ],
                        'value' => function($model) {
                            return $model->nm_id . ' (' . ($model->card_name ?: '...') . ')';
                        }
                    ],
                    [
                        'attribute' => 'bonus_type_name',
                        'filterType' => GridView::FILTER_SELECT2,
                        'filter' => $filterLists['reasons'],
                        'filterWidgetOptions' => [
                            'pluginOptions' => ['allowClear' => true, 'placeholder' => 'Причина'],
                        ],
                    ],
                    [
                        'attribute' => 'office_name',
                        'filterType' => GridView::FILTER_SELECT2,
                        'filter' => $filterLists['offices'],
                        'filterWidgetOptions' => [
                            'pluginOptions' => ['allowClear' => true, 'placeholder' => 'Склад'],
                        ],
                    ],
                    [
                        'attribute' => 'delivery_rub',
                        'format' => ['decimal', 2],
                        'hAlign' => 'right',
                        'pageSummary' => true,
                    ],
                ],
            ]); ?>
        <?php else: ?>
            <div id="target-detailed"></div>
        <?php endif; ?>
    <?php Pjax::end(); ?>
</div>

<?php
$urlDays = Url::to(['logistics', '_target' => 'days']);
$urlDetailed = Url::to(['logistics', '_target' => 'detailed']);

$this->registerJs(<<<JS
    window.loadBlock = function(type) {
        let dFrom = $('#date_from').val();
        let dTo = $('#date_to').val();
        
        let container = type === 'days' ? '#pjax-days' : '#pjax-detailed';
        let url = type === 'days' ? '{$urlDays}' : '{$urlDetailed}';
        
        url += '&date_from=' + dFrom + '&date_to=' + dTo;
        
        // Показываем лоадер только внутри целевого контейнера
        $(container).html('<div class="p-4 text-center">Загрузка...</div>');
        
        $.pjax({
            url: url, 
            container: container, 
            push: false, 
            replace: false, 
            timeout: 20000
        });
    }
JS
);
?>

*/ ?>

<?php /*
<div class="row mb-3">
    <div class="mb-3">
        <?= Html::button('Статистика по дням', ['class' => 'btn btn-info', 'onclick' => 'loadBlock("days")']) ?>
        <?= Html::button('Детализация', ['class' => 'btn btn-danger', 'onclick' => 'loadBlock("detailed")']) ?>
    </div>

    <div id="target-days" class="mb-4"></div>
    <div id="target-detailed"></div>
</div>
*/ ?>

<?php
$urlDays = Url::current(['_target' => 'days']);
$urlDetailed = Url::current(['_target' => 'detailed']);

$this->registerJs(<<<JS
/*
    $(document).on('pjax:complete', function() {
        // Ищем все select, которые должны быть select2, но еще не инициализированы
        $('.kv-select2').each(function() {
            if ($(this).data('select2')) {
                $(this).select2('destroy'); // Сначала убиваем старый, если есть
            }
            $(this).select2(); // Запускаем заново
        });
    });
*/
    window.loadBlock = function(type) {
        let url = type === 'days' ? '{$urlDays}' : '{$urlDetailed}';
        
        let targetDiv = $('#target-' + type);
        targetDiv.html('<div class="text-center p-4"><i class="fa fa-spinner fa-spin fa-2x"></i> Загрузка...</div>');
        
        // Обычный AJAX для первичной загрузки блока
        targetDiv.load(url);
    }
JS
);
?>
