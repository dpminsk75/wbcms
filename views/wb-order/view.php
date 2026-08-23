<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\WbOrder $model */

$this->title = 'Детали заказа: ' . $model->g_number;
$this->params['breadcrumbs'][] = ['label' => 'Заказы WB', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Также применим уменьшенный шрифт, чтобы карточка была компактной
$this->registerCss("
    .wb-order-view table {
        font-size: 13px;
    }
    .wb-order-view th {
        width: 30%;
    }
");
?>
<div class="wb-order-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Назад к списку', ['index'], ['class' => 'btn btn-default']) ?>
    </p>

    <div class="panel panel-default">
        <div class="panel-heading">
            <b>Информация из API Wildberries</b>
        </div>
        <?= DetailView::widget([
            'model' => $model,
            'attributes' => [
                'id',
                'srid',
                'g_number',
                [
                    'attribute' => 'date',
                    'format' => ['datetime', 'php:d.m.Y H:i:s'],
                ],
                [
                    'attribute' => 'last_change_date',
                    'format' => ['datetime', 'php:d.m.Y H:i:s'],
                ],
                'supplier_article',
                'nm_id',
                'barcode',
                'tech_size',
                [
                    'attribute' => 'total_price',
                    'value' => $model->total_price . ' руб.',
                ],
                'discount_percent',
                'price_with_disc',
                'finished_price',
                'for_pay',
                'spp',
                'warehouse_name',
                'country_name',
                'oblast_okrug_name',
                'region_name',
                'income_id',
                'sale_id',
                'odid',
                'subject',
                'category',
                'brand',
                [
                    'attribute' => 'is_cancel',
                    'format' => 'raw',
                    'value' => $model->is_cancel 
                        ? '<span class="label label-danger">Отмена (Storno)</span>' 
                        : '<span class="label label-success">Ок</span>',
                ],
                'order_type',
                'sticker',
                [
                    'attribute' => 'created_at',
                    'label' => 'Дата загрузки в БД',
                    'format' => ['datetime', 'php:d.m.Y H:i:s'],
                ],
            ],
        ]) ?>
    </div>

</div>