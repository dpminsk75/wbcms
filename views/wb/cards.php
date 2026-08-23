<?php

/** @var yii\web\View $this */
/** @var \app\models\WbCardSearch $searchModel */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var array $brandList */
/** @var array $subjectNameList */
/** @var bool $condensed */

use Yii;
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\grid\GridView;
use kartik\dynagrid\DynaGrid;
use kartik\select2\Select2;
use kartik\icons\Icon;

Icon::map($this); 

$this->title = 'Карточки Толока Инвест на Wildberries';
$this->params['breadcrumbs'][] = 'Справочники';
$this->params['breadcrumbs'][] = 'Карточки WB';

$syncButton = '';
if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin') {
    $syncButton = Html::a(
        '<i class="fas fa-sync-alt"></i> Синхронизировать с WB',
        ['wb/sync-cards'],
        ['class' => 'btn btn-primary']
    );
}

// Улучшенная форма фильтра
$unusedFilter = Html::beginForm(['wb/cards'], 'get', [
    'class' => 'form-inline d-inline-block', 
    'style' => 'margin-left:15px; vertical-align: middle;',
    'id' => 'unused-filter-form'
]);

// Добавляем ВСЕ текущие параметры поиска как скрытые поля, 
// чтобы при клике на чекбокс не сбрасывались фильтры в колонках
if (isset(Yii::$app->request->queryParams['WbCardSearch'])) {
    foreach (Yii::$app->request->queryParams['WbCardSearch'] as $key => $value) {
        if ($key !== 'onlyUnused') { // Чтобы не дублировать сам чекбокс
            $unusedFilter .= Html::hiddenInput("WbCardSearch[$key]", $value);
        }
    }
}

$unusedFilter .= Html::activeCheckbox($searchModel, 'onlyUnused', [
    'label' => 'Только свободные',
    'labelOptions' => ['style' => 'margin-bottom: 0; cursor: pointer;'],
    'onchange' => 'this.form.submit();',
    'uncheck' => 0, // Важно: отправляет 0, если галочка снята
]);

$unusedFilter .= Html::endForm();

/*
$unusedFilter = Html::beginForm(['index'], 'get', [
    'class' => 'form-inline d-inline-block', 
    'style' => 'margin-left:15px; vertical-align: middle;',
    'id' => 'unused-filter-form'
]) .
    // Передаем существующие параметры GET (чтобы не сбрасывать поиск по артикулу/бренду)
    Html::activeCheckbox($searchModel, 'onlyUnused', [
        'label' => '<b>Только свободные</b>',
        'labelOptions' => ['style' => 'margin-bottom: 0; cursor: pointer;'],
        'onchange' => 'this.form.submit();',
        'uncheck' => 0,
    ]) .
    // Скрытые поля для сохранения текущих фильтров DynaGrid при клике
    (isset(Yii::$app->request->queryParams['WbCardSearch']) ? 
        Html::hiddenInput('WbCardSearch[vendorCode]', Yii::$app->request->queryParams['WbCardSearch']['vendorCode'] ?? '') .
        Html::hiddenInput('WbCardSearch[nmID]', Yii::$app->request->queryParams['WbCardSearch']['nmID'] ?? '') 
        : '') .
Html::endForm();
*/


$densityLinks = $condensed
    ? Html::a('Обычный вид', Url::current(['condensed' => 0]), ['class' => 'btn btn-sm btn-outline-secondary'])
    : Html::a('Компактный вид', Url::current(['condensed' => 1]), ['class' => 'btn btn-sm btn-outline-secondary']);

echo DynaGrid::widget([
    'columns' => [ // Ключевой массив должен быть тут
//        ['class' => 'kartik\grid\SerialColumn'],
        [
            'attribute' => 'nmID',
            'label' => 'Арт WB', 
            'headerOptions' =>  ['style' => 'font-size: 11px; width: 100px;'], 
            'contentOptions' => ['style' => 'font-size: 11px; width: 100px; white-space: nowrap;'], 
            'filterInputOptions' => [
                'style' => 'font-size: 11px; height: 25px; padding: 2px 5px;', 
                'class' => 'form-control', 
            ],
        ],
        [
            'attribute' => 'vendorCode',
            'label' => 'Артикул', 
            'headerOptions' =>  ['style' => 'font-size: 11px; width: 250px;'], 
            'contentOptions' => ['style' => 'font-size: 11px; width: 250px; white-space: normal; word-break: break-all;'], 
            'filterInputOptions' => [
                'style' => 'font-size: 11px; height: 25px; padding: 2px 5px;', 
                'class' => 'form-control', 
            ],
        ],
        [
            'attribute' => 'brand',
            'label' => 'Бренд', 
            'headerOptions' =>  ['style' => 'font-size: 11px; width: 200px;'], 
            'contentOptions' => ['style' => 'font-size: 11px; width: 200px;'], 
/*
            'filterInputOptions' => [
                'style' => 'font-size: 11px; height: 25px; padding: 2px 5px;', 
                'class' => 'form-control', 
            ],
*/
            'filterType' => Select2::class,
            'filterWidgetOptions' => [
                'data' => $brandList,
                'options' => ['placeholder' => 'Все бренды', 'multiple' => false, 'style' => 'font-size: 10px;'],
                'pluginOptions' => ['allowClear' => true,  'containerCss' => ['font-size' => '10px', 'height' => '25px', 'padding' => '5px'], 'dropdownCss' => ['font-size' => '10px'],],
            ],
        ],
        [
            'attribute' => 'subjectName',
            'label' => 'Категория', 
            'headerOptions' =>  ['style' => 'font-size: 11px; width: 200px;'], 
            'contentOptions' => ['style' => 'font-size: 11px; width: 200px;'], 
/*
            'filterInputOptions' => [
                'style' => 'font-size: 11px; height: 25px; padding: 2px 5px;', 
                'class' => 'form-control', 
            ],
*/
            'filterType' => Select2::class,
            'filterWidgetOptions' => [
                'data' => $subjectNameList,
                'options' => ['placeholder' => 'Все категории', 'multiple' => false, 'style' => 'font-size: 10px;'],
                'pluginOptions' => ['allowClear' => true,  'containerCss' => ['font-size' => '10px', 'height' => '25px', 'padding' => '5px'], 'dropdownCss' => ['font-size' => '10px'],],
            ],

        ],
        [
            'attribute' => 'title',
            'label' => 'Название', 
            'headerOptions' =>  ['style' => 'font-size: 11px; width: 400px;'], 
            'contentOptions' => ['style' => 'font-size: 12px; width: 400px;'], 
            'filterInputOptions' => [
                'style' => 'font-size: 11px; height: 25px; padding: 2px 5px;', 
                'class' => 'form-control', 
            ],
        ],
        [
            'attribute' => 'description',
            'label' => 'Описание', 
            'headerOptions' =>  ['style' => 'font-size: 11px; width: 300px;'], 
            'contentOptions' => ['style' => 'font-size: 11px; max-width: 400px;'], 
            'filterInputOptions' => [
                'style' => 'font-size: 11px; height: 25px; padding: 2px 5px;', 
                'class' => 'form-control', 
            ],
            'format' => 'ntext',
            'value' => function ($model) {
                /** @var \app\models\WbCard $model */
                $text = (string)$model->description;
                $limit = 200;
                if (mb_strlen($text, 'UTF-8') > $limit) {
                    return mb_substr($text, 0, $limit, 'UTF-8') . '...';
                }
                return $text;
            },
        ],
/*
        [
            'attribute' => 'updated_at',
            'format' => ['date', 'php:Y-m-d'],
        ],
        ['class' => 'kartik\grid\ActionColumn'],
*/
    ],
    'storage' => DynaGrid::TYPE_SESSION,
    'theme' => 'panel-default',
    'userSpecific' => true,
    'showPersonalize' => true,
    'showFilter' => true,
    'showSort' => true,
    'allowPageSetting' => true,
    'allowThemeSetting' => false,
    'allowFilterSetting' => true,
        'gridOptions' => [
            'id' => 'wbcards-grid',
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
//            'columns' => $columns,
            'responsiveWrap' => false,
            'bootstrap' => true,
            'bordered' => true,
            'striped' => true,
            'condensed' => $condensed,
            'hover' => true,
            'resizableColumns' => true,
            'persistResize' => true,
            'resizeStorageKey' => 'wbcards-grid-resize',
            'toggleDataOptions' => [
                'minCount' => 500,
                'maxCount' => 10000,
                'confirmMsg' => 'Показать все записи ({totalCount})? Это может занять время.',
                'all' => [
                    'icon' => 'fas fa-expand',
                    'label' => 'Все',
                    'class' => 'btn btn-secondary',
                    'title' => 'Показать все записи',
                ],
            'page' => [
                'icon' => 'fas fa-compress',
                'label' => 'По странице',
                'class' => 'btn btn-secondary',
                'title' => 'Показать по страницам',
            ],
           'filterRowOptions' => [
                'style' => 'font-size: 10px; height: 25px; padding: 2px 5px;', 
                'class' => 'form-control',
            ],
        ],
        'export' => [
            'label' => 'Выгрузить',
            'showConfirmAlert' => false,
            'target' => GridView::TARGET_BLANK,
            'formats' => ['html', 'csv', 'xls'],
            'encoding' => 'UTF-8',
            'bom' => true,
        ],
        'exportConfig' => [
            GridView::HTML => ['label' => 'HTML', 'filename' => 'wb-cards'],
            GridView::CSV => ['label' => 'CSV', 'filename' => 'wb-cards'],
            GridView::EXCEL => ['label' => 'Excel', 'filename' => 'wb-cards'],
        ],
        'panel' => [
            'type' => GridView::TYPE_DEFAULT,
            'heading' => '<i class="fas fa-list"></i> Карточки WB',
            'before' => $syncButton . ' ' . $densityLinks. ' ' . $unusedFilter,
//            'headingOptions' => ['template' => '{title}'],
            'titleOptions' => ['template' => '{title}'],
        ],
        'toolbar' => [
             ['content' => '{dynagrid}'],
            '{toggleData}',
            '{export}',
        ],
        'itemLabelSingle' => 'карточка',
        'itemLabelPlural' => 'карточек',
        'itemLabelFew' => 'карточки',
        'itemLabelMany' => 'карточек',
    ],
    'options' => ['id' => 'wbcards-dynagrid'],
]);

?>