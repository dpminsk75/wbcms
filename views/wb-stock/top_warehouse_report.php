<?php

use yii\helpers\Html;
use kartik\grid\GridView;

use kartik\icons\Icon;
Icon::map($this); 

/** @var yii\web\View $this */
/** @var yii\data\ArrayDataProvider $dataProvider */
/** @var array $warehouses */
/** @var int $days */
/** @var int $limit */
/** @var int $minStock */

$this->title = 'ТОП товаров в разрезе складов';
$this->params['breadcrumbs'][] = $this->title;

// Безопасная функция очистки названий складов от скобок и дефисов
$cleanNameFn = function($name) {
    if (empty($name)) return '';
    $pos = preg_split('/[\(\-]/u', $name, 2);
    return trim($pos[0]);
};

// 1. Базовые колонки
$gridColumns = [
/*
    [
        'attribute' => 'nmID',
        'label' => 'Номенклатура WB',
        'width' => '120px',
        'vAlign' => 'middle',
    ],
*/
            [
                'attribute' => 'nmID',
                'label' => 'Артикул WB', // Поменяли "Месяц" на актуальное название
                'format' => 'raw',       // Позволяет выводить HTML-код (ссылку)
                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle;'],
                'contentOptions' => ['style' => 'text-align: center; font-weight: bold; vertical-align: middle;'],
                'value' => function ($model) {
                    $nmId = $model['nmID'] ?? '';
                    $title = !empty($model['title']) ? htmlspecialchars($model['title'], ENT_QUOTES) : '';
                    $vendorCode = !empty($model['vendorCode']) ? htmlspecialchars($model['vendorCode'], ENT_QUOTES) : '';
                    
                    // Формируем текст для всплывающей подсказки (title) при наведении
                    $tooltip = "{$title}";
                    if (!empty($vendorCode)) {
                        $tooltip .= " &#10;Артикул: {$vendorCode}"; // &#10; — это перенос строки внутри title
                    }
                    
                    // Строим ссылку с параметром фильтрации
                    return "<a href=\"/wb/detail?DPFilterForm[nm_id]={$nmId}\" title=\"{$tooltip}\" style=\"color: #2980b9; text-decoration: none;\">{$nmId}</a>";
                },
                'pageSummary' => 'Итого',
                'pageSummaryOptions' => ['style' => 'text-align: center; font-weight: bold;']
            ],
    [
        'attribute' => 'title',
        'label' => 'Наименование товара',
        'vAlign' => 'middle',
    ],

    [
        'attribute' => 'total_stock',
        'label' => 'Остаток',
        'vAlign' => 'middle',
        'pageSummary' => true,
        'format' => ['integer'],
        'contentOptions' => ['class' => 'table-info text-center font-weight-bold'],
    ],
];
/*
// 2. Генерация динамических колонок для каждого склада
foreach ($warehouses as $wh) {
    $gridColumns[] = [
        'attribute' => "wh_{$wh}_stock",
        'label' => 'Ост.',
        'value' => function($model) use ($wh) {
            $val = $model["wh_{$wh}_stock"] ?? 0;
            return $val > 0 ? $val : ''; 
        },
        'format' => ['integer'],
        'pageSummary' => true,
        'contentOptions' => function($model) use ($wh) {
            $val = $model["wh_{$wh}_stock"] ?? 0;
            return ['class' => 'text-center table-success'];
        }
    ];
    $gridColumns[] = [
        'attribute' => "wh_{$wh}_sales",
        'label' => 'Прод.',
        'value' => function($model) use ($wh) {
            $val = $model["wh_{$wh}_sales"] ?? 0;
            return $val > 0 ? $val : ''; 
        },
        'format' => ['integer'],
        'pageSummary' => true,
        'contentOptions' => function($model) use ($wh) {
            $val = $model["wh_{$wh}_sales"] ?? 0;
            return ['class' => 'text-center '];
        }
    ];
}
*/
/*
// 2. Генерация динамических колонок для каждого склада
foreach ($warehouses as $wh) {
    $gridColumns[] = [
        'attribute' => "wh_{$wh}_stock",
        'label' => 'Ост.',
        'value' => function($model) use ($wh) {
            $val = $model["wh_{$wh}_stock"] ?? 0;
            return $val > 0 ? $val : ''; 
        },
        'format' => ['integer'],
        'pageSummary' => true,
        'contentOptions' => function($model) use ($wh) {
            return ['class' => 'text-center table-success'];
        }
    ];
    $gridColumns[] = [
        'attribute' => "wh_{$wh}_sales",
        'label' => 'Прод.',
        'value' => function($model) use ($wh, $minStock) {
            // Проверяем текущий остаток по этому складу в модели
            $stockVal = $model["wh_{$wh}_stock"] ?? 0;
            
            // Если выбран фильтр minStock и остаток на складе не проходит этот фильтр,
            // то продажи для данного склада обнуляем/скрываем
            if ($minStock > 0 && $stockVal <= $minStock) {
                return '';
            }
            
            $val = $model["wh_{$wh}_sales"] ?? 0;
            return $val > 0 ? $val : ''; 
        },
        'format' => ['integer'],
        'pageSummary' => true,
        'contentOptions' => function($model) use ($wh, $minStock) {
            $stockVal = $model["wh_{$wh}_stock"] ?? 0;
            
            // Если остаток не проходит фильтр, убираем стили/подсветку ячейки продаж
            if ($minStock > 0 && $stockVal <= $minStock) {
                return ['class' => 'text-center text-muted'];
            }
            return ['class' => 'text-center'];
        }
    ];
}
*/

// 2. Генерация динамических колонок для каждого склада
foreach ($warehouses as $wh) {
    $gridColumns[] = [
        'attribute' => "wh_{$wh}_stock",
        'label' => 'Ост.',
        'format' => 'raw', // Используем raw, чтобы пустая строка передавалась корректно без приведения к 0
        'value' => function($model) use ($wh, $minStock) {
            $stockVal = $model["wh_{$wh}_stock"] ?? 0;
            $salesVal = $model["wh_{$wh}_sales"] ?? 0;

            // Если режим критичных остатков, скрываем те, где остаток в норме (Ост >= Прод)
            if ($minStock === 'critical' && ($stockVal == 0 || $stockVal >= $salesVal)) {
                return '';
            }
            // Для обычного числового фильтра minStock
            if ($minStock !== 'critical' && (int)$minStock > 0 && $stockVal <= (int)$minStock) {
                return '';
            }

            // Если остаток равен 0 — возвращаем пустоту, чтобы не засорять вид
            return $stockVal > 0 ? $stockVal : ''; 
        },
        'pageSummary' => true,
        'contentOptions' => function($model) use ($wh, $minStock) {
            $stockVal = $model["wh_{$wh}_stock"] ?? 0;
            $salesVal = $model["wh_{$wh}_sales"] ?? 0;

            // Если скрыто фильтрами или остаток равен 0
            if ($stockVal == 0) {
                return ['class' => 'text-center text-muted'];
            }
            if ($minStock === 'critical' && $stockVal >= $salesVal) {
                return ['class' => 'text-center text-muted'];
            }
            if ($minStock !== 'critical' && (int)$minStock > 0 && $stockVal <= (int)$minStock) {
                return ['class' => 'text-center text-muted'];
            }

            // РЕАЛЬНО ОПАСНЫЕ (Остаток > 0 и продаж строго больше, чем осталось)
            if ($salesVal > $stockVal) {
                return ['class' => 'text-center table-danger font-weight-bold'];
            }

            // Обычные стандартные остатки
            return ['class' => 'text-center table-success'];
        }
    ];

    $gridColumns[] = [
        'attribute' => "wh_{$wh}_sales",
        'label' => 'Прод.',
        'format' => 'raw', // Меняем формат, чтобы избавиться от скрытых нулей
        'value' => function($model) use ($wh, $minStock) {
            $stockVal = $model["wh_{$wh}_stock"] ?? 0;
            $salesVal = $model["wh_{$wh}_sales"] ?? 0;

            // Скрываем продажи по фильтрам дефицита
            if ($minStock === 'critical' && ($stockVal == 0 || $stockVal >= $salesVal)) {
                return '';
            }
            if ($minStock !== 'critical' && (int)$minStock > 0 && $stockVal <= (int)$minStock) {
                return '';
            }

            // Если продаж нет (0) — возвращаем пустоту
            return $salesVal > 0 ? $salesVal : ''; 
        },
        'pageSummary' => true,
        'contentOptions' => function($model) use ($wh, $minStock) {
            $stockVal = $model["wh_{$wh}_stock"] ?? 0;
            $salesVal = $model["wh_{$wh}_sales"] ?? 0;

            // Если скрыто фильтрами или продаж нет
            if ($salesVal == 0) {
                return ['class' => 'text-center'];
            }
            if ($minStock === 'critical' && ($stockVal == 0 || $stockVal >= $salesVal)) {
                return ['class' => 'text-center'];
            }
            if ($minStock !== 'critical' && (int)$minStock > 0 && $stockVal <= (int)$minStock) {
                return ['class' => 'text-center'];
            }

            // Если по этому складу зафиксирован дефицит товара (Прод > Ост)
            if ($stockVal > 0 && $salesVal > $stockVal) {
                return ['class' => 'text-center table-warning'];
            }

            return ['class' => 'text-center'];
        }
    ];
}


// 3. Формирование многоуровневого заголовка верхнего уровня с очищенными именами
$beforeHeaderRow = [
    'columns' => [
        ['content' => 'Товарная информация', 'options' => ['colspan' => 3, 'class' => 'text-center bg-light font-weight-bold']],
    ],
    'options' => [] 
];

foreach ($warehouses as $wh) {
    $beforeHeaderRow['columns'][] = [
        'content' => $cleanNameFn($wh), // Обрезаем скобки и дефисы только при выводе названия склада
        'options' => ['colspan' => 2, 'class' => 'text-center font-weight-bold table-active']
    ];
}
?>

<div class="wb-stock-top-warehouse-report">

    <h1><?= Html::encode($this->title) ?></h1>

<div class="card my-3">
    <div class="card-body">
        <?= Html::beginForm(['top-warehouse-report'], 'get') ?>
            <div class="row align-items-end">
                
                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="font-weight-bold mb-2 text-nowrap" for="limit">Показать товаров</label>
                    <?= Html::dropDownList('limit', $limit, [
                        20 => 'ТОП-20',
                        50 => 'ТОП-50',
                        100 => 'ТОП-100',
                        500 => 'ТОП-500'
                    ], ['class' => 'form-control', 'id' => 'limit']) ?>
                </div>

                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="font-weight-bold mb-2 text-nowrap" for="days">Период продаж</label>
                    <?= Html::dropDownList('days', $days, [
                        7 => '7 дней',
                        14 => '14 дней',
                        30 => '30 дней',
                        60 => '60 дней'
                    ], ['class' => 'form-control', 'id' => 'days']) ?>
                </div>

                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="font-weight-bold mb-2 text-nowrap" for="minStock">Текущее наличие</label>
                    <?= Html::dropDownList('minStock', $minStock, [
                        0 => 'Выдать все склады',
                        1 => 'Только склады с остатком > 1 шт.',
                        5 => 'Только склады с остатком > 5 шт.',
                        'critical' => 'Только критичные остатки (Ост. < Прод.)' 
                    ], ['class' => 'form-control', 'id' => 'minStock']) ?>
                </div>

                <div class="col-md-3 text-right">
                    <?= Html::submitButton('<i class="fas fa-filter mr-2"></i> Применить фильтр', ['class' => 'btn btn-primary btn-block px-4']) ?>
                </div>

            </div>
        <?= Html::endForm() ?>
    </div>
</div>

<div class="row grid_wbstat" style="margin-bottom: 25px;">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'containerOptions' => ['class' => 'custom-compact-grid', 'style' => 'overflow: auto; max-height: 600px;'],

    'floatHeader' => true, // Фиксируем шапку при вертикальной прокрутке
    'floatHeaderOptions' => ['scrollingTop' => '0'],

        'showFooter' => false,
        'toggleData' => false,

        'columns' => $gridColumns,
        'beforeHeader' => [
            $beforeHeaderRow
        ],
        'responsive' => true,
        'hover' => true,
        'showPageSummary' => true,
        'showFooter' => false,
        'toggleData' => false,

        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => '<i class="fas fa-cubes"></i> Матрица остатков и продаж на складах WB (Ост. — Остаток, Прод. — Продажи за период)',

            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            'footer' => false,
            'after' => false,
        ],
/*
        'export' => [
            'fontAwesome' => true,
            'label' => 'Экспорт',
        ],
        'exportConfig' => [
            GridView::EXCEL => [
                'label' => 'Excel',
                'icon' => 'file-excel-o',
                'iconOptions' => ['class' => 'text-success'],
                'showHeader' => true,
                'showPageSummary' => true,
                'showFooter' => true,
                'config' => [
                    'worksheet' => 'Товары по складам',
                    'cssFile' => ''
                ]
            ],
        ],
*/
        'export' => [
            'showConfirmAlert' => false,
            'target' => GridView::TARGET_BLANK
        ],
        'exportConfig' => [
            GridView::EXCEL => ['label' => 'Сохранить в Excel'],
        ],
    ]); ?>

</div></div>

<style>
    #w0 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
/* уменьшаем кнопки в панеле  */
    #w0 .kv-panel-before { padding: 5px 10px;}
    #w0-togdata-page, #w0-togdata-all {padding: 2px 5px;; font-size: 11px;}
    #w0-togdata-page .svg-inline--fa.fa-w-14 {width: 10px;}
    #w0-togdata-all .svg-inline--fa.fa-w-14 {width: 10px;}
/* размер заголовка  */
    #w0 .card-header { font-size: 11px; }
    #w0 .card-header h5 { font-size: 13px; }
</style>
<style>
.custom-compact-grid .table td {
    padding: 3px 2px !important;
}
</style>
<style>
main > .container-xxl {
    max-width: 100%;
}
</style>