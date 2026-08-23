<?php
use yii\helpers\Html;
use kartik\grid\GridView;

$this->title = 'Каннибализация запросов';
?>

<div class="cannibalization-report">
    <h2 class="mb-4" style="font-weight: 400;"><?= Html::encode($this->title) ?></h2>

    <div class="card border-0 shadow-sm mb-4 bg-light">
        <div class="card-body">
            <?= \app\components\getDPWidget::widget([
                'action' => ['/wb-search/cannibalization'],
                'defaultDateFrom' => $dateFrom,
            ]) ?>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'responsive' => false,
        'pjax' => true,
        'columns' => [
            [
                'attribute' => 'phrase',
                'label' => 'Поисковый запрос',
                'format' => 'raw',
                'value' => function($model) {
                    return Html::a($model['phrase'], ['/wb-search/phrase', 'phrase' => $model['phrase']], ['data-pjax' => 0, 'class' => 'fw-bold']);
                }
            ],
            [
                'attribute' => 'avg_freq',
                'label' => 'Ср. част',
                'format' => ['decimal', 0],
                'width' => '100px',
                'hAlign' => 'center',
            ],
            [
                'attribute' => 'cards_count',
                'label' => 'Кол-во артикулов',
                'hAlign' => 'center',
                'width' => '150px',
                'contentOptions' => ['class' => 'text-danger fw-bold'],
            ],
            [
                'label' => 'Ваши товары в выдаче',
                'format' => 'raw',
                'value' => function($model) {
                    $items = [];
                    foreach ($model['cards_info'] as $card) {
                        $items[] = Html::a($card['nmID'], ['/wb-search/card', 'nmId' => $card['nmID']], [
                            'class' => 'badge bg-light text-dark border',
                            'title' => $card['title'],
                            'data-pjax' => 0
                        ]);
                    }
                    return implode(' ', $items);
                }
            ],
            [
                'attribute' => 'total_clicks',
                'label' => 'Всего кликов',
                'hAlign' => 'center',
                'width' => '120px',
            ],
            [
                'attribute' => 'total_orders',
                'label' => 'Всего заказов',
                'hAlign' => 'center',
                'width' => '120px',
                'contentOptions' => ['class' => 'text-primary fw-bold'],
            ],
        ],
        'panel' => [
            'type' => GridView::TYPE_DEFAULT,
            'heading' => 'Список фраз, по которым ранжируются 2 и более ваших товара',
            'before' => '<div class="text-muted small">Эти запросы "размывают" продажи между вашими карточками. Возможно, стоит оставить рекламу только для одной.</div>',
        ],
        'containerOptions' => ['style' => 'height: 70vh; overflow: auto;'], 
    ]); ?>
</div>