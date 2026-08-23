<?php
use kartik\grid\GridView;
use yii\helpers\Html;

$this->title = 'Аналитика запасов и оборачиваемости';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wb-stock-analytics">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= GridView::widget([
        'dataProvider' => $shortageProvider,
        'pjax' => true,
        'panel' => [
            'type' => GridView::TYPE_DANGER,
            'heading' => '<i class="glyphicon glyphicon-fire"></i> СКОРО ЗАКОНЧИТСЯ: Остаток менее чем на 7 дней',
        ],
        'columns' => [
            ['class' => 'kartik\grid\SerialColumn'],
            'brand',
            [
                'attribute' => 'nm_id',
                'label' => 'nmID',
                'format' => 'raw',
                'value' => function($model) {
                    return Html::a($model['nm_id'], "https://www.wildberries.ru/catalog/" . $model['nm_id'] . "/detail.aspx", ['target' => '_blank']);
                }
            ],
            ['attribute' => 'card_name', 'label' => 'Наименование'],
            [
                'attribute' => 'current_stock',
                'label' => 'Остаток',
                'contentOptions' => ['class' => 'text-center font-weight-bold'],
            ],
            [
                'attribute' => 'daily_speed',
                'label' => 'Скорость (ед/день)',
                'format' => ['decimal', 2],
            ],
            [
                'attribute' => 'days_left',
                'label' => 'Дней до 0',
                'contentOptions' => ['class' => 'text-danger', 'style' => 'font-weight:bold'],
            ],
        ],
    ]); ?>

    <?= GridView::widget([
        'dataProvider' => $outOfStockProvider,
        'pjax' => true,
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => '<i class="glyphicon glyphicon-alert"></i> OUT-OF-STOCK: Товары кончились, но есть спрос',
        ],
        'columns' => [
            ['class' => 'kartik\grid\SerialColumn'],
            'brand',
            'nm_id',
            'card_name',
            [
                'attribute' => 'daily_speed',
                'label' => 'Потенциал продаж (ед/день)',
                'format' => ['decimal', 2],
                'contentOptions' => ['class' => 'text-primary', 'style' => 'font-weight:bold'],
            ],
            [
                'attribute' => 'sales_14_days',
                'label' => 'Продано за 14 дней',
            ],
        ],
    ]); ?>

    <?= GridView::widget([
        'dataProvider' => $excessProvider,
        'pjax' => true,
        'panel' => [
            'type' => GridView::TYPE_WARNING,
            'heading' => '<i class="glyphicon glyphicon-hourglass"></i> ИЗЛИШКИ И НЕЛИКВИДЫ: Запас более 60 дней',
        ],
        'columns' => [
            ['class' => 'kartik\grid\SerialColumn'],
            'brand',
            'nm_id',
            'card_name',
            [
                'attribute' => 'current_stock',
                'label' => 'Остаток',
            ],
            [
                'attribute' => 'days_left',
                'label' => 'Хватит на (дней)',
                'value' => function($model) {
                    return $model['days_left'] >= 999 ? 'Нет продаж' : $model['days_left'];
                },
                'contentOptions' => ['class' => 'text-muted'],
            ],
        ],
    ]); ?>

</div>