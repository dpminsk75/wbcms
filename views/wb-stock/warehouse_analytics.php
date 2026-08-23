<?php
use app\assets\AppAsset;
AppAsset::register($this);

use kartik\grid\GridView;
use kartik\select2\Select2;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

use kartik\icons\Icon;
Icon::map($this); 

/** @var yii\web\View $this */
/** @var yii\data\ArrayDataProvider $dataProvider */
/** @var yii\base\DynamicModel $searchModel */

$this->title = 'Аналитика остатков';

// 1. Убираем "крутелочки" через CSS на корню
$this->registerCss("
    .kv-attribute-summary-container, .kv-field-range-indicator { display: none !important; }
    .grid-view-loading { background: none !important; }
    .grid-view-loading > * { opacity: 1 !important; }
");

$this->registerCss("
    .kv-field-range-indicator, 
    .kv-attribute-summary-container,
    .column-set-filter-indicator,
    .grid-view-loading { 
        display: none !important; 
        background: none !important;
    }
    /* Убираем прозрачность при загрузке, чтобы таблица не моргала */
    .grid-view-loading > * { opacity: 1 !important; }
");
?>

<div class="warehouse-analytics">
    <div class="row">
        <div class="panel panel-default">
            <div class="panel-body col-md-6 card shadow mb-3">
                <?php $form = ActiveForm::begin(['method' => 'get', 'action' => ['warehouse-analytics']]); ?>
                <div class="row card-body ">
                    <div class="col-md-12 mb-3 d-flex" style="flex-direction: row; align-items: center;"> Выводить "до дней"
                        <div class="btn-group btn-primary border ms-5">
                            <?php foreach ([7, 10, 14] as $d): ?>
                                <?= Html::a($d . ' дн.', ['warehouse-analytics', 'nmId' => $selectedNmId, 'days' => $d], [
                                    'class' => 'btn ' . ($currentThreshold == $d ? 'btn-primary' : 'btn-default')
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
<?php /*
                    <div class="col-md-12 mb-3">
                        <?= Select2::widget([
                            'name' => 'nmId',
                            'value' => $selectedNmId,
                            'data' => $allCards,
                            'options' => ['placeholder' => 'Быстрый выбор любого товара...'],
                            'pluginOptions' => ['allowClear' => true],
                        ]) ?>
                    </div>
*/ ?>
        <?php 
            // Используем $form->field для привычного отображения
            // Так как в экшне параметр называется $nmId, 
            // мы можем либо использовать DynamicModel, либо оставить старый вариант, 
            // но оформить его визуально как вы привыкли:
            echo $form->field($searchModel, 'filterNmId')->widget(Select2::classname(), [
                'data' => $allCards,
                'options' => [
                    'placeholder' => 'Выберите артикул...',
                    'id' => 'main-nm-select' // Уникальный ID для скриптов
                ],
                'pluginOptions' => [
                    'allowClear' => true, 
                    'width' => '100%'
                ],
            ])->label(false); 
        ?>

                    <div class="col-md-12">
                        <?= Html::submitButton('Применить', ['class' => 'btn btn-success']) ?>
                        <?= Html::a('Сброс', ['warehouse-analytics'], ['class' => 'btn btn-default']) ?>
                    </div>
                </div>
                <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>

 <div class="row custom-compact-grid grid_wbstat">
 <?= GridView::widget([
        'id' => 'warehouse-grid',
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'pjax' => true,
        'pjaxSettings' => ['options' => ['id' => 'warehouse-grid-pjax']],

        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,

//        'export' => true,
//        'toggleData' => false,
        'showPageSummary' => false,
        'showFooter' => false,

        'panel' => [
//            'type' => GridView::TYPE_DANGER, 
            'headingOptions' => ['class' => 'card-header text-white bg-wb-danger-header'],
            'heading' => 'Критические остатки',
            'footer' => false,
        ],
        'columns' => [
            ['class' => 'kartik\grid\SerialColumn'],
            [
                // Используем nmID согласно вашим правилам
                'attribute' => 'nmID', 
                'label' => 'Артикул WB',
                'headerOptions'  => ['style' => 'min-width: 100px; text-align: center; '],
                'contentOptions' => ['style' => 'min-width: 100px; white-space: nowrap; align-content: center; text-align: center;'],
                'filter' => Select2::widget([
                    'model' => $searchModel,
                    'attribute' => 'filterNmId',
                    'data' => $gridNmIds,
                    'options' => [
                            'placeholder' => 'ID...',
                            ],
                    'pluginOptions' => [
                        'allowClear' => true,
                        // Отключаем внутренние индикаторы Select2
                        'containerCssClass' => 'kv-select2-clean', 
                        'dropdownCssClass' => 'custom-compact-grid_select-dropdown', 
                    ],
                ]),
            ],
            [
                'attribute' => 'card_name',
                'label' => 'Товар',
                'filter' => Select2::widget([
                    'model' => $searchModel,
                    'attribute' => 'filterCardName',
                    'data' => $gridCardNames,
                    'options' => [
                            'placeholder' => 'Выберите товар...',
                        ],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'containerCssClass' => 'kv-select2-clean', 
                        'dropdownCssClass' => 'custom-compact-grid_select-dropdown', 
                    ],
                ]),
            ],
            // Остальные колонки: Склад, Остаток и т.д.
            [
                'attribute' => 'warehouse_name',
                'label' => 'Склад',
            ],
            [
                'attribute' => 'current_stock',
                'label' => 'На складе',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: right;'],
                'format' => ['decimal', 0],
            ],
            [
                'attribute' => 'week_orders',
                'header' => 'Заказов<br>в неделю',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: right;'],
                'format' => ['decimal', 0],
            ],
            [
                'attribute' => 'days_left',
                'label' => 'Дней до 0',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: right;'],
                'format' => ['decimal', 2],
                'hAlign' => 'right',
                'contentOptions' => function($model) use ($currentThreshold) {
                    return ['class' => ($model['days_left'] < $currentThreshold) ? 'text-danger font-weight-bold' : ''];
                },
            ],
        ],
    ]); ?>
</div>

</div>
<?php
// JS: Исправляем поведение PJAX
$js = <<<JS
// Принудительная отправка фильтра при выборе в Select2
$(document).on('change', '#warehouse-grid-pjax select', function() {
    $('#warehouse-grid').yiiGridView('applyFilter');
});

// После обновления PJAX убираем мусор
$(document).on('pjax:complete', function() {
    $('.kv-field-range-indicator').remove();
    $('.grid-view').removeClass('grid-view-loading');
});
JS;
$this->registerJs($js);
?>

<?php
// Обновленный скрипт: авто-фильтрация + ре-инициализация Select2 после Pjax
$js = <<<JS
// 1. Автоматическая отправка фильтра при изменении Select2
$(document).on('change', '#warehouse-grid-pjax select', function() {
    $('#warehouse-grid').yiiGridView('applyFilter');
});

// 2. Ре-инициализация Select2 после обновления Pjax
$(document).on('pjax:complete', '#warehouse-grid-pjax', function() {
    // Находим все select, которые должны быть Select2, и инициализируем их снова
    // Обычно Select2 от Kartik имеет определенные настройки в data-кратрибутах
    // Но самый надежный способ для виджетов Kartik - вызвать их стандартную инициализацию,
    // если она доступна, или просто пересоздать через плагин.
    
    $('#warehouse-grid-pjax select[data-krajee-select2]').each(function() {
        var el = $(this);
        if (el.data('select2')) {
            el.select2('destroy'); 
        }
        // Берем настройки из глобального объекта kvSelect2, который создает Kartik
        var id = el.attr('id');
        var settings = window[el.data('krajee-select2')] || {};
        $.when(el.select2(settings)).done(function() {
            // Исправляем фокус после выбора (опционально)
        });
    });
});
JS;
$this->registerJs($js);
?>

<style>
.kv-plugin-loading {display: none;}
.bg-wb-danger {
    background: linear-gradient(
        97.26deg, 
        #dc3545 0.49%, /* --bs-danger */
        #e22d48 14.88%, 
        #e7234a 29.27%, 
        #ed154d 43.14%, 
        #f20050 57.02%, 
        #d90048 70.89%, 
        #c10040 84.76%, 
        #a80038 99.15%
    ), 
    linear-gradient(#0000000d, #0000000d) !important;
}

.bg-wb-danger-header-dark {
    background: linear-gradient(
        97.26deg, 
        #dc3545 0%,      /* Старт: Базовый Danger */
        #c82333 10%,     /* Быстрый уход в темный к 10% */
        #b21f2d 25%, 
        #9d1b28 40%, 
        #881723 55%, 
        #73131e 70%, 
        #5e0f19 85%, 
        #4d0b13 100%     /* Финиш: Глубокий бордовый */
    ), 
    linear-gradient(#0000000d, #0000000d) !important;
    color: white;        /* Белый текст для контраста */
}

.bg-wb-danger-header {
    background: linear-gradient(
        97.26deg, 
        #ff4d4d 0.49%,  /* Яркий кораллово-красный (аналог лилового) */
        #f73b4c 14.88%, 
        #ef2a5b 29.27%, 
        #e31c6a 43.14%, 
        #d50e7a 57.02%, /* Здесь уходим в мадженту (аналог перехода в фиолетовый) */
        #c30089 70.89%, 
        #ae0099 84.76%, 
        #9900a8 99.15%  /* Финиш в глубокий пурпур */
    ), 
    linear-gradient(#0000000d, #0000000d) !important;
    border: none !important;
}

.custom-compact-grid_select-dropdown .select2-results li.select2-results__option {padding: 5px; font-size: 12px;}  
</style>
