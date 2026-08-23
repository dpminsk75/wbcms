<?php
use yii\helpers\Html;
use kartik\grid\GridView;

$this->title = 'Упущенная видимость';
?>

<div class="lost-visibility-report">
    <h2 class="mb-4" style="font-weight: 400;"><?= Html::encode($this->title) ?></h2>

    <div class="alert alert-danger border-0 shadow-sm bg-white">
        <div class="d-flex align-items-center">
            <div class="display-6 me-3 text-danger"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <strong>Внимание:</strong> Ниже представлены фразы, по которым ваши товары значительно просели в выдаче. 
                Критерий: падение более чем на <strong><?= $dropThreshold ?></strong> позиций относительно среднего за 7 дней.
            </div>
        </div>
    </div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'containerOptions' => ['style' => 'height: 70vh; overflow: auto;'],
    'responsive' => false,
    'columns' => [
        [
            'attribute' => 'nmID',
            'format' => 'raw',
            'value' => function($model) {
                return Html::a($model['nmID'], ['/wb-search/card', 'nmId' => $model['nmID']], ['data-pjax' => 0]);
            }
        ],
        [
            'attribute' => 'title',
            'label' => 'Товар',
            'contentOptions' => ['style' => 'width:200px; white-space:normal;', 'class' => 'small text-muted'],
        ],
        [
            'attribute' => 'phrase',
            'label' => 'Поисковый запрос',
            'format' => 'raw',
            'value' => function($model) {
                return Html::a($model['phrase'], ['/wb-search/phrase', 'phrase' => $model['phrase']], ['data-pjax' => 0, 'class' => 'fw-bold']);
            }
        ],
        [
            'attribute' => 'avg_base',
            'label' => 'Среднее (база 7д)',
            'hAlign' => 'center',
            'contentOptions' => ['class' => 'bg-light'],
        ],
        [
            'attribute' => 'avg_current',
            'label' => 'Среднее (тек. 3д)',
            'hAlign' => 'center',
            'contentOptions' => function($model) {
                return ['style' => 'font-weight:bold; color:' . ($model['avg_current'] <= 0 ? 'red' : 'inherit')];
            },
            'value' => function($model) {
                return $model['avg_current'] > 0 ? $model['avg_current'] : 'Вылет';
            }
        ],
        [
            'attribute' => 'diff',
            'label' => 'Падение',
            'hAlign' => 'center',
            'format' => 'raw',
            'value' => function($model) {
                return '<span class="text-danger"><i class="fas fa-arrow-down"></i> ' . $model['diff'] . '</span>';
            }
        ],
        [
            'attribute' => 'total_orders_period',
            'label' => 'Заказы (10д)',
            'hAlign' => 'center',
            'contentOptions' => ['class' => 'fw-bold text-primary'],
        ],
    ],
    'panel' => [
        'type' => GridView::TYPE_DANGER,
        'heading' => 'Анализ падения видимости: Сравнение окон 7 дней vs 3 дня',
    ],
]); ?>
</div>