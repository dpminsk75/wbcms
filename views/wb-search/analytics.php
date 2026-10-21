<?php
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use app\components\UniversalFilterWidget;

$this->title = 'Частотность поисковых запросов';
?>
<div class="search-analytics-report">
    <div class="row mb-3">
        <h2 class="mb-4" style="font-weight: 400;"><?= Html::encode($this->title) ?></h2>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
<?php
echo UniversalFilterWidget::widget([
    'attribute' => 'phrase_text',
    'label' => 'Поиск по запросам',
    'defaultDays' => 30,
    'ajaxUrl' => Url::to(['wb-search/ajax-search', 'type' => 'phrases']),
    'initValueText' => $phraseText ?? '',
    'pluginOptions' => [
        'tags' => true,
        'allowClear' => true,
        'minimumInputLength' => 2,
        'createTag' => new \yii\web\JsExpression("function (params) {
            return {
                id: params.term,
                text: params.term,
                isNew: true
            };
        }"),
    ]
]);
?>
        </div>
    </div>
<?php
$gridColumns = [
    ['class' => 'kartik\grid\SerialColumn'],

    [
        'attribute' => 'phrase',
        'label' => 'Поисковый запрос',
        'format' => 'raw',
        'value' => function($model) use ($dateFrom, $dateTo) {
            $url = Url::to([
                '/wb-search/phrase',
                'DPFilterForm' => [
                    'phrase_id' => null,
                    'phrase' => $model['phrase'],
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                ],
            ]);
            return Html::a(Html::encode($model['phrase']), $url, [
                'data-pjax' => '0',
                'target' => '_blank',
            ]);
        },
        'contentOptions' => ['style' => 'min-width: 280px; white-space: normal;'],
    ],

    ['attribute' => 'total', 'label' => 'Запросов', 'format' => ['decimal', 0], 'hAlign' => 'right'],

    [
        'attribute' => 'delta_total',
        'label' => 'Динамика',
        'format' => 'raw',
        'hAlign' => 'right',
        'value' => function($model) {
            if (!empty($model['is_new'])) return '<span style="color:#8a2be0; font-weight:600;">new</span>';
            $d = (int)$model['delta_total'];
            if ($d > 0) return '<span style="color:#1e9e7c; font-weight:600;">&uarr; ' . number_format($d, 0, '.', ' ') . '</span>';
            if ($d < 0) return '<span style="color:#e0525c;">&darr; ' . number_format(-$d, 0, '.', ' ') . '</span>';
            return '0';
        },
        'contentOptions' => function($model) {
            if (!empty($model['is_new'])) return [];
            $d = (int)$model['delta_total'];
            if ($d > 0) return ['style' => 'color:#1e9e7c; font-weight:600; white-space:nowrap;'];
            if ($d < 0) return ['style' => 'color:#e0525c; white-space:nowrap;'];
            return [];
        },
    ],

    [
        'attribute' => 'freq',
        'label' => 'В день',
        'format' => 'raw',
        'hAlign' => 'right',
        'value' => function($model) {
            $out = number_format((int)$model['freq'], 0, '.', ' ');
            if (!empty($model['is_new'])) {
                $out .= '<br><small style="color:#8a2be0; font-weight:600;">new</small>';
            } else {
                $d = (int)$model['delta'];
                if ($d > 0) $out .= '<br><small style="color:#1e9e7c;">&uarr; ' . number_format($d, 0, '.', ' ') . '</small>';
                elseif ($d < 0) $out .= '<br><small style="color:#e0525c;">&darr; ' . number_format(-$d, 0, '.', ' ') . '</small>';
            }
            return $out;
        },
    ],

    ['attribute' => 'goods', 'label' => 'Товаров', 'format' => ['decimal', 0], 'hAlign' => 'center'],
    ['attribute' => 'avg_pos', 'label' => 'Ср. позиция', 'hAlign' => 'center',
        'value' => fn($model) => $model['avg_pos'] === null ? '&mdash;' : $model['avg_pos']],
    ['attribute' => 'best_pos', 'label' => 'Лучшая', 'hAlign' => 'center',
        'value' => fn($model) => $model['best_pos'] === null ? '&mdash;' : $model['best_pos']],
    ['attribute' => 'clicks', 'label' => 'Клики', 'format' => ['decimal', 0], 'hAlign' => 'right'],
    ['attribute' => 'orders', 'label' => 'Заказы', 'format' => ['decimal', 0], 'hAlign' => 'right'],
];
?>

<div class="row custom-compact-grid mb-3 mt-3">
    <div class="col-md-12">
        <div id="grid-scroll" style="position: relative;">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => $gridColumns,
            'pjax' => true,
            'responsive'    => false,
            'bordered'      => true,
            'condensed'     => true,
            'floatHeader'   => true,
            'panel' => [
                'type' => 'default',
                'heading' => 'Частотность запросов с ' . Yii::$app->formatter->asDate($dateFrom, 'dd.MM.yyyy')
                    . ' по ' . Yii::$app->formatter->asDate($dateTo, 'dd.MM.yyyy')
                    . ' в сравнении с ' . Yii::$app->formatter->asDate($prevFrom, 'dd.MM.yyyy')
                    . ' по ' . Yii::$app->formatter->asDate($prevTo, 'dd.MM.yyyy'),
            ],
            'containerOptions' => ['style' => 'max-height: 70vh; overflow: auto;'],
        ]); ?>
        </div>
    </div>
</div>
</div>
