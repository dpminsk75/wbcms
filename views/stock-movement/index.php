<?php

use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var app\models\search\StockMovementSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Движения остатков (Смоленск)';
?>
<div class="stock-movement-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('+ Добавить вручную', ['create'], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Импорт из Excel', ['import'], ['class' => 'btn btn-default']) ?>
        <?= Html::a('Остатки на начало периода', ['stock-snapshot/index'], ['class' => 'btn btn-default']) ?>
    </p>

    <?php Pjax::begin(); ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['attribute' => 'movement_date', 'label' => 'Дата'],
            [
                'attribute' => 'vendorCodeOrTitle',
                'label' => 'Товар (артикул / nmID / название)',
                'value' => function ($model) {
                    return $model->wbCard
                        ? $model->wbCard->vendorCode . ' / ' . $model->wbCard->nmID . ' — ' . $model->wbCard->title
                        : '—';
                },
            ],
            [
                'attribute' => 'type',
                'label' => 'Тип',
                'value' => function ($model) { return $model->typeLabel; },
                'filter' => \app\models\StockMovement::typeLabels(),
            ],
            [
                'attribute' => 'qty',
                'label' => 'Кол-во',
                'contentOptions' => function ($model) {
                    return ['class' => $model->qty < 0 ? 'text-danger' : 'text-success'];
                },
            ],
            'comment',
            [
                'attribute' => 'source',
                'label' => 'Источник',
                'value' => function ($model) {
                    return $model->source === 'excel_import' ? 'Импорт из Excel' : 'Вручную';
                },
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{update} {delete}',
            ],
        ],
    ]); ?>
    <?php Pjax::end(); ?>

</div>
