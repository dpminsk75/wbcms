<?php

use yii\helpers\Html;
use kartik\dynagrid\DynaGrid;
use kartik\grid\GridView;

use kartik\select2\Select2;
use kartik\icons\Icon;
Icon::map($this); 


/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var app\models\ProductSearch $searchModel */
/** @var array $productTypesList */
/** @var array $brandsList */

$this->title = 'Товары';
$this->params['breadcrumbs'][] = 'Справочники';
$this->params['breadcrumbs'][] = $this->title;

$columns = [
    // 1. Наименование (ID и SerialColumn удалены по вашей просьбе)
    [
        'attribute' => 'name',
        'label' => 'Название', 

        'format' => 'raw',
        'value' => function($model) {
            return "<b>" . Html::encode($model->name) . "</b>";
        },
        'vAlign' => GridView::ALIGN_MIDDLE,

        'headerOptions' =>  ['style' => 'font-size: 11px; width: 400px;'], 
        'contentOptions' => ['style' => 'font-size: 12px; width: 400px;'], 
        'filterInputOptions' => [
            'style' => 'font-size: 11px; height: 25px; padding: 2px 5px;', 
            'class' => 'form-control', 
        ],
    ],
    // 2. Тип продукта с верным списком фильтрации
    [
        'attribute' => 'product_type_id',
        'value' => 'productType.name',

        'label' => 'Тип',
        'headerOptions' =>  ['style' => 'font-size: 11px; width: 200px;'], 
        'contentOptions' => ['style' => 'font-size: 11px; width: 200px;'], 

        'filterType' => Select2::class,
        'filterWidgetOptions' => [
            'data' => $productTypesList,
            'options' => ['placeholder' => 'Все типы', 'multiple' => false, 'style' => 'font-size: 10px;'],
            'pluginOptions' => ['allowClear' => true,  'containerCss' => ['font-size' => '10px', 'height' => '25px', 'padding' => '5px'], 'dropdownCss' => ['font-size' => '10px'],],
        ],

/*
        'filterType' => GridView::FILTER_SELECT2,
        'filter' => $productTypesList, // Список из контроллера
        'filterWidgetOptions' => [
            'pluginOptions' => ['allowClear' => true],
        ],
        'filterInputOptions' => ['placeholder' => 'Все'],
*/
        'vAlign' => GridView::ALIGN_MIDDLE,
    ],
    // 3. Бренд с верным списком фильтрации
    [
        'attribute' => 'brand_id',
        'value' => 'brand.name',

        'label' => 'Бренд', 
        'headerOptions' =>  ['style' => 'font-size: 11px; width: 200px;'], 
        'contentOptions' => ['style' => 'font-size: 11px; width: 200px;'], 

        'filterType' => Select2::class,
        'filterWidgetOptions' => [
            'data' => $brandsList,
            'options' => ['placeholder' => 'Все бренды', 'multiple' => false, 'style' => 'font-size: 10px;'],
            'pluginOptions' => ['allowClear' => true,  'containerCss' => ['font-size' => '10px', 'height' => '25px', 'padding' => '5px'], 'dropdownCss' => ['font-size' => '10px'],],
        ],

/*
        'filterType' => GridView::FILTER_SELECT2,
        'filter' => $brandsList, // Список из контроллера
        'filterWidgetOptions' => [
            'pluginOptions' => ['allowClear' => true],
        ],
        'filterInputOptions' => ['placeholder' => 'Все'],
*/
        'vAlign' => GridView::ALIGN_MIDDLE,
    ],
    // 4. Себестоимость
    [
        'attribute' => 'cost',
        'label' => 'Себест.',

        'headerOptions' =>  ['style' => 'font-size: 11px; width: 100px;'], 
        'contentOptions' => ['style' => 'font-size: 11px; width: 100px;'], 

        'format' => ['decimal', 2],
        'hAlign' => GridView::ALIGN_RIGHT,
        'vAlign' => GridView::ALIGN_MIDDLE,
        'width' => '100px',
        'filter' => false,
    ],
    // 5. Вес
    [
        'attribute' => 'weight',
        'label' => 'Вес (кг)',

        'headerOptions' =>  ['style' => 'font-size: 11px; width: 100px;'], 
        'contentOptions' => ['style' => 'font-size: 11px; width: 100px;'], 

        'format' => ['decimal', 3],
        'hAlign' => GridView::ALIGN_RIGHT,
        'vAlign' => GridView::ALIGN_MIDDLE,
        'width' => '100px',
        'filter' => false,
    ],
    // 6. Привязанные nmID 

// 6. Привязанные nmID (Бейджи)
    [
        'attribute' => 'nmIdFilter',
        'label' => 'Карточки (Арт WB)',
        'headerOptions' => ['style' => 'font-size: 11px; width: 400px;'], 
        'contentOptions' => ['style' => 'font-size: 12px; width: 400px;'], 
        'format' => 'raw',
        'value' => function ($model) {
            /** @var \app\models\Product $model */
            // Получаем все записи из связующей таблицы для этого товара
            $links = \app\models\ProductWbCard::find()
                ->where(['product_id' => $model->id])
                ->with('wbCard') // Жадная загрузка данных карточки
                ->all();

            if (empty($links)) return '<span class="text-muted small">нет</span>';
            
            $items = [];
            foreach ($links as $link) {
                $card = $link->wbCard;
                if (!$card) continue;

                // Определяем класс цвета: если type == 1, то синий (primary), иначе серый (secondary)
                $badgeClass = ($link->type == 1) ? 'badge-primary badge-blue' : 'badge-secondary badge-grey';
                
                $tooltip = "Артикул: " . Html::encode($card->vendorCode) . " | " . Html::encode($card->title);
                
                $items[] = Html::tag('span', Html::encode($card->nmID), [
                    'class' => 'badge ' . $badgeClass,
                    'title' => $tooltip,
                    'style' => 'cursor: help; margin-right: 2px;',
                ]);
            }
            return implode(' ', $items);
        },
        'filterInputOptions' => [
            'placeholder' => 'Поиск по ID...',
            'style' => 'font-size: 11px; height: 25px; padding: 2px 5px;', 
            'class' => 'form-control',
        ],
        'vAlign' => GridView::ALIGN_MIDDLE,
    ],
    // 7. КОЛОНКА ДЕЙСТВИЙ (Исправлено)
    [
        'class' => 'kartik\grid\ActionColumn',
        'dropdown' => false,
        'template' => '{update} {delete}',
        'vAlign' => GridView::ALIGN_MIDDLE,
        'width' => '100px',
        'headerOptions' =>  ['style' => 'font-size: 11px; width: 100px;'], 

        'order' => DynaGrid::ORDER_FIX_RIGHT, // Всегда справа
        'buttons' => [
            'update' => function($url, $model) {
                return Html::a('<i class="fas fa-pencil-alt"></i>', ['update', 'id' => $model->id], [
                    'class' => 'btn btn-xs btn-outline-primary btn_30px',
                    'title' => 'Редактировать',
                    'data-pjax' => '0',
                ]);
            },
            'delete' => function($url, $model) {
                return Html::a('<i class="fas fa-trash"></i>', ['delete', 'id' => $model->id], [
                    'class' => 'btn btn-xs btn-outline-danger btn_30px',
                    'title' => 'Удалить',
                    'data-method' => 'post',
                    'data-confirm' => 'Вы точно хотите удалить этот товар?',
                ]);
            },
        ],
    ],
];

echo DynaGrid::widget([
    'columns' => $columns,
    'theme' => 'panel-default',
    'gridOptions' => [
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'pjax' => true,
        'panel' => [
            'heading' => '<i class="fas fa-boxes"></i> ' . Html::encode($this->title),
            'before' => Html::a('<i class="fas fa-plus"></i> Добавить товар', ['create'], ['class' => 'btn btn-success']),
            'after' => Html::a('<i class="fas fa-redo"></i> Сбросить фильтры', ['index'], ['class' => 'btn btn-info']),
        ],
        'toolbar' =>  [
            ['content' => '{dynagridFilter}{dynagridSort}{dynagrid}'],
            '{export}',
            '{toggleData}',
        ]
    ],
    'options' => ['id' => 'dynagrid-product-list'] // Изменил ID, чтобы сбросить старые настройки колонок
]);