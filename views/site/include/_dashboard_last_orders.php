<?php
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\grid\GridView;
use kartik\icons\Icon;
Icon::map($this);
/* @var $LastOrdersProvider yii\data\ArrayDataProvider */
/* @var $dateFrom string */
?>


<?php
    $allStatsLO = $LastOrdersProvider->allModels;

    $totalLO_PWD = array_sum(array_column($allStatsLO, 'pwd'));
    $totalLO_CNT = array_sum(array_column($allStatsLO, 'cnt'));
    $totalLO_FP  = array_sum(array_column($allStatsLO, 'fp'));

?>
<?php
/*
            [
                'attribute' => 'nm_id',
                'label' => 'Арт WB',
                'format' => 'raw', 
                'headerOptions'  => ['style' => 'width:90px; text-align: center;'],
                'contentOptions' => ['style' => 'width:90px; white-space: nowrap; align-content: center; text-align: center;'],
                'value' => function($model) {
                    if (!$model['nm_id']) {
                        return null;
                    }
                    // Генерируем ссылку
                    return Html::a((string)$model['nm_id'], "https://www.wildberries.ru/catalog/" . $model['nm_id'] . "/detail.aspx", [ 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ]);
                },
            ],
*/

$columnsLO = [
            [
                    'attribute' => 'cardTitle',
                    'label' => 'Товар / Артикул',
//                    'headerOptions'  => ['style' => 'width:420px'],
//                    'contentOptions' => ['style' => 'min-width:420px; white-space: wrap; '],
                    'headerOptions'  => ['class' => 'mobile-column-420'],
                    'contentOptions' => ['class' => 'mobile-column-420'],
                    'format' => 'raw',
/*
                    'value' => function($model) {
                        // Верхний уровень: Название товара
                        $title = Html::tag('div', $model['title'] ?? '—', [
                            'style' => 'font-weight: bold; font-size: 12px; margin-bottom: 2px; color: #2c3e50;'
                        ]);
                        $details = Html::tag('div', 
                            "Артикул: <b>{$model['vendorCode']}</b> Арт WB: ". 
                            Html::a((string)$model['nm_id'], "/wb/detail?DPFilterForm[nm_id]=".$model['nm_id'], ['title' => 'Перейти в карточку', 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ])
//                            http://31.130.204.146/wb-order/index?WbOrderSearch[nm_id]=50308273
                            ,
                            ['style' => 'color: #666; font-size: 11px;']
                        );

                        return $title . $details;
                    },
*/

                        'value' => function($model) {

// 1. Заголовок
    $title = Html::tag('div', $model['title'] ?? '—', [
        'class' => 'cart-item-title'
    ]);

    // 2. Артикул seller
    $vendor = Html::tag('span', "Артикул: <b>{$model['vendorCode']}</b>", [
        'class' => 'd-block d-md-inline-block me-md-2 mr-md-2',
        'style' => 'white-space: nowrap;'
    ]);

    // 3. Ссылка WB
    $wbLink = Html::a((string)$model['nm_id'], "/wb/detail?DPFilterForm[nm_id]=".$model['nm_id'], [
        'title' => 'Перейти в карточку',
        'target' => '_blank',
        'data-pjax' => '0',
        'style' => 'text-decoration: none;'
    ]);

    // 4. Арт WB
    $wb = Html::tag('span', "Арт WB: {$wbLink}", [
        'class' => 'd-block d-md-inline-block',
        'style' => 'white-space: nowrap;'
    ]);

    // 5. Блок артикулов
    $details = Html::tag('div', $vendor . $wb, [
        'class' => 'cart-item-details'
    ]);

    return $title . $details;


                        },  
/*
                    'pageSummary' => function($cnt, $pwd, $fp) use ($totalLO_CNT, $totalLO_PWD, $totalLO_FP)  {
                        return "Итого <b>$totalLO_CNT</b> зак | Сумма в РЦ: <b>" . number_format($totalLO_PWD, 1, ',', ' ') . "</b> | Заказы: <b>".number_format($totalLO_FP, 1, ',', ' ')."</b>";
                    }, 'pageSummaryOptions' => ['class' => 'text-right'],
*/

'pageSummary' => function($cnt, $pwd, $fp) use ($totalLO_CNT, $totalLO_PWD, $totalLO_FP) {
    // 1. Формируем текстовые части
    $p1 = "Итого <b>$totalLO_CNT</b> зак";
    $p2 = "Сумма в РЦ: <b>" . number_format($totalLO_PWD, 1, ',', ' ') . "</b>";
    $p3 = "Заказы: <b>" . number_format($totalLO_FP, 1, ',', ' ') . "</b>";

    // 2. Оборачиваем каждую часть в адаптивный span с запретом разрыва внутри фразы
    $part1 = Html::tag('span', $p1, [
        'class' => 'd-block d-md-inline-block',
        'style' => 'white-space: nowrap;'
    ]);
    
    $part2 = Html::tag('span', $p2, [
        'class' => 'd-block d-md-inline-block',
        'style' => 'white-space: nowrap;'
    ]);
    
    $part3 = Html::tag('span', $p3, [
        'class' => 'd-block d-md-inline-block',
        'style' => 'white-space: nowrap;'
    ]);

    // 3. Разделитель |, который виден ТОЛЬКО на десктопе (от 768px)
    $sep = Html::tag('span', ' | ', [
        'class' => 'd-none d-md-inline'
    ]);

    return $part1 . $sep . $part2 . $sep . $part3;
}, 'pageSummaryOptions' => ['class' => 'text-right'],


            ],

            [
                'attribute' => 'cnt',
                'label' => 'Кол-во',
                'hAlign' => 'right',
                'contentOptions' => ['align-content: center; text-align: center;'],
                'format' => 'raw',

                'value' => function($model) {
                    $title = Html::tag('div',$model['cnt'] ?? "—", ['class' => 'cart-item-cnt-total', 'style' => 'font-weight: bold; text-align: center;']);
                    $details = Html::tag('div',($model['cnt_3'] ?? "—") . " | ".($model['cnt_2'] ?? "—") . " | ". ($model['cnt_1'] ?? "—") . " | ". ($model['cnt_0'] ?? "—"), ['style' => 'font-size: 12px; margin-bottom: 2px; color: #2c3e50; text-align: center;']);
//                    return Html::a((string)$title . $details, "/wb-order/index?WbOrderSearch[nm_id]=".$model['nm_id'], [ 'title' => 'Перейти в заказы', 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ]);

                return Html::a((string)$title . $details, Url::to([
                    '/wb-order/feed',
                    'DPFilterForm' => [
                        'nm_id'     => $model['nm_id'],
                        'date_from' => date('Y-m-d', strtotime('-3 days')),
                        'date_to'   => date('Y-m-d'),
                    ],
                ]), [
                    'title'     => 'Перейти в заказы',
                    'target'    => '_blank',
                    'data-pjax' => '0',
                    'style'     => 'text-decoration: none;',
                ]);

                }
            ],
            [
                'attribute' => 'pwd',
                'label' => 'Σ в РЦ',
                'hAlign' => 'right',
                'format' => ['decimal', 1],
                'headerOptions' => ['class' => 'mobile-hide-col'],
                'contentOptions' => ['align-content: center; text-align: right;', 'class' => 'mobile-hide-col'],
                'pageSummary' => true, 
            ],
            [
                'attribute' => 'fp',
                'label' => 'Σ к опл',
                'hAlign' => 'right',
                'format' => ['decimal', 0],
                'headerOptions' => ['class' => 'mobile-hide-col'],
                'contentOptions' => ['align-content: center; text-align: right;', 'class' => 'mobile-hide-col'],
                'pageSummary' => true, 
            ],
            [
                'attribute' => 'apwd',
                'label' => 'Цена',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'headerOptions' => ['class' => 'mobile-hide-col'],
                'contentOptions' => ['align-content: center; text-align: right;', 'class' => 'mobile-hide-col'],
            ],
            [
                'attribute' => 'aspp',
                'label' => 'СПП, %',
                'hAlign' => 'right',
                'format' => ['decimal', 1],
                'headerOptions' => ['class' => 'mobile-hide-col'],
                'contentOptions' => ['align-content: center; text-align: right;', 'class' => 'mobile-hide-col'],
//                'pageSummary' => true, 
//                'pageSummaryFunc' => GridView::F_AVG,
            ],
            [
                'attribute' => 'afp',
                'label' => 'Цена пр.',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['align-content: center; text-align: right; style' => 'font-weight:bold;'],
            ],


    ];
?>
<div class="grid_lastorders grid_wbstat grid_no_kv-panel-before grid_no_kv__summary_65 expandable-container">
<?php
echo GridView::widget([
    'dataProvider' => $LastOrdersProvider,
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
//            'heading' => 'Заказы (c '.Yii::$app->formatter->asDate($dateFrom, 'd MMM y').')',

            'heading' => Html::a(
//                'Заказы (c ' . Yii::$app->formatter->asDate($dateFrom, 'd MMM y') . ')',
                'Заказы (c ' . Yii::$app->formatter->asDate($dateFrom, 'd MMM y') . ') <i class="bi bi-eye-fill me-1 lh-1" style="transform: none;"></i>',
                Url::to([
                    '/wb-order/feed',
                    'DPFilterForm' => [
                        'date_from' => $dateFrom,
                        'date_to'   => date('Y-m-d'),
                    ],
                ]),
                [
                    'class' => 'text-white', // Сохраняет белый цвет текста на фоне bg-wb
                    'style' => 'text-decoration: none;', // Добавляет подчеркивание, чтобы было видно, что это ссылка
                    'target' => '_blank',
                    'data-pjax' => '0',
                ]
            ) . ' ' . Html::a(
                '<i class="bi bi-graph-up me-1" style="transform: none;"></i> Топ',
                Url::to([
                    '/wb-order/feed-aggregated',
                    'DPFilterForm' => [
                        'date_from' => $dateFrom,
                        'date_to'   => date('Y-m-d'),
                    ],
                ]),
                [
                    'class' => 'text-white ms-2', // Маленький отступ слева
                    'style' => 'text-decoration: underline; font-weight: 500;',
                    'target' => '_blank',
                    'data-pjax' => '0',
                ]
            ),

            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            'footer' => false,
            'after' => false,
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],

    'columns' => $columnsLO,
]);
?>
</div>
<div class="expand-btn-wrapper">
    <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
</div>
