<?php
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\grid\GridView;
use kartik\date\DatePicker;
use kartik\select2\Select2;

use kartik\icons\Icon;
Icon::map($this); 

$this->title = 'Невыкупленные товары (Unclaimed)';
$this->params['breadcrumbs'][] = $this->title;

$percentOptions = [
    '5' => '5%', '10' => '10%', '20' => '20%', '30' => '30%', 
    '40' => '40%', '50' => '50%', '60' => '60%', '70' => '70%'
];

$orderOptions = array_combine([1, 5, 10, 20, 50, 100], [1, 5, 10, 20, 50, 100]);
?>

<div class="unclaimed-orders-index">
<div class="row">
    <h1><?= Html::encode($this->title) ?></h1>


<div class="panel panel-default col-md-4 card shadow" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9;">
        <?= Html::beginForm(['index'], 'get', ['class' => 'form-inline']) ?>
        
        <div class="form-group" style="margin-right: 10px;">
            <label>Период:</label>
            <?= DatePicker::widget([
                'name' => 'date_from',
                'value' => $params['date_from'],
                'type' => DatePicker::TYPE_RANGE,
                'name2' => 'date_to',
                'value2' => $params['date_to'],
                'pluginOptions' => [
                    'autoclose' => true,
                    'format' => 'yyyy-mm-dd'
                ]
            ]); ?>
        </div>
        <div class="row">
            <div class="form-group col-md-6">
                <label>Порог отмен %:</label>
                <?= Select2::widget([
                    'name' => 'percent',
                    'value' => $params['percent'],
                    'data' => $percentOptions,
                    'options' => ['placeholder' => 'Выберите %'],
                    'pluginOptions' => ['allowClear' => false],
                ]); ?>
            </div>

            <div class="form-group col-md-6">
                <label>Мин. заказов:</label>
                <?= Select2::widget([
                    'name' => 'min_orders',
                    'value' => $params['min_orders'],
                    'data' => $orderOptions,
                    'options' => ['placeholder' => 'Кол-во'],
                    'pluginOptions' => ['allowClear' => false],
                ]); ?>
            </div>
        </div>
        <?= Html::submitButton('Применить', ['class' => 'btn btn-primary', 'style' => 'margin-top: 25px;']) ?>
        <?= Html::a('Сброс', ['index'], ['class' => 'btn btn-default', 'style' => 'margin-top: 25px;']) ?>

        <?= Html::endForm() ?>
    </div>
</div>
<div class="row grid_advstat grid_wbstat ">
    <div class="custom-compact-grid">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'export' => [], 
        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,

        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Невыкупленные товары (c '.Yii::$app->formatter->asDate($params['date_from'], 'd MMM y').')',
            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
            'footer' => false,
//            'after' => '<div class="float-right">{pager}</div>',
        ],
        'containerOptions' => [
            'class' => 'no-border-class' 
        ],


//        'tableOptions' => ['class' => 'table table-striped table-bordered'],
        'columns' => [
            [
                'attribute' => 'nm_id',
                'label' => 'Артикул WB',
                'format' => 'raw',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: center;'],

                'value' => function($model) {
//                    return Html::a($model['nm_id'], "https://www.wildberries.ru/catalog/{$model['nm_id']}/detail.aspx", ['target' => '_blank']);
                    return Html::a($model['nm_id'], "/wb/detail?nm_id={$model['nm_id']}", ['target' => '_blank']);
                }
            ],
            [
                'attribute' => 'card_name',
                'label' => 'Название товара',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: left;'],
            ],
            [
                'attribute' => 'vendorCode',
                'label' => 'Артикул продавца',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: left;'],
            ],
            [
                'attribute' => 'alls',
                'label' => 'Заказов, шт',
                'format' => ['decimal', 0],
                'hAlign' => 'right',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: right;'],
            ],
            [
                'attribute' => 'cancel',
                'label' => 'Отмен, шт',
                'contentOptions' => ['style' => 'color: #d9534f; font-weight: bold;'],
                'format' => ['decimal', 0],
                'hAlign' => 'right',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: right;'],
            ],
            [
                'attribute' => 'rate',
                'label' => '% Отмен',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'value' => function($model) {
                    return number_format($model['rate'] * 100, 1) . '%';
                },
                'contentOptions' => function($model) {
                    // Если процент отмен совсем дикий (например, > 50%), подкрасим ячейку
                    return $model['rate'] > 0.3 ? ['class' => 'danger', 'style' => 'text-align: right;'] : ['style' => 'text-align: right;'];
                }
            ],
            [
                'attribute' => 'bought',
                'label' => 'Выкупили, шт',
                'format' => ['decimal', 0],
                'hAlign' => 'right',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: right;'],
            ],
            [
                'attribute' => 'sum_price',
                'label' => 'Сумма выкупа, ₽',
                'format' => ['decimal', 2],
                'hAlign' => 'right',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: right;'],
            ],
        ],
    ]); ?>
    </div>
    </div>
</div>

<style>
#w3 .border-primary { border-color: var(--bs-border-color-translucent) !important; }
#w3 .btn-group { margin: 0px 5px; }
.custom-compact-grid #w3 .table { font-size: 14px; }
.custom-compact-grid #w3 .table td, .grid_wbstat #w3 .table th {  padding: 6px 4px !important; }
#w3 .danger {color: #cd1c1c;}
</style>