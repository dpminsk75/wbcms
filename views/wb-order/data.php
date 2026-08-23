<?php
ini_set('memory_limit', '256M');

use fedemotta\datatables\DataTables;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\date\DatePicker;

/** @var yii\web\View $this */
/** @var app\models\WbOrderSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Заказы WB';

$this->registerCss("
    .wb-filter-panel { background: #f4f7f6; padding: 15px; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 20px; }
    .wb-order-index table.dataTable { font-size: 12px; }
    .wb-order-index table.dataTable td, .wb-order-index table.dataTable th { padding: 5px 8px !important; }
    .wb-filter-panel .form-group { margin-bottom: 10px; }
    .wb-filter-panel label { font-size: 11px; text-transform: uppercase; color: #777; font-weight: bold; }
    .btn-filter-group { margin-top: 24px; }
");
?>

<div class="wb-order-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="wb-filter-panel">
        <?php $form = ActiveForm::begin([
            'action' => ['data'],
            'method' => 'get',
            'id' => 'wb-orders-search-form',
            'options' => ['class' => 'row'],
        ]); ?>

        <div class="col-md-2">
            <?= $form->field($searchModel, 'date')->widget(DatePicker::class, [
                'pluginOptions' => [
                    'format' => 'yyyy-mm-dd',
                    'autoclose' => true,
                    'todayHighlight' => true,
                ],
                'options' => ['placeholder' => 'Выберите дату...', 'class' => 'input-sm'],
            ])->label('Дата заказа') ?>
        </div>

        <div class="col-md-2">
            <?= $form->field($searchModel, 'nm_id')->textInput(['placeholder' => 'Артикул WB', 'class' => 'form-control input-sm'])->label('NM ID (Артикул)') ?>
        </div>

        <div class="col-md-2">
            <?= $form->field($searchModel, 'brand')->textInput(['placeholder' => 'Бренд', 'class' => 'form-control input-sm'])->label('Бренд') ?>
        </div>

        <div class="col-md-3">
            <?= $form->field($searchModel, 'cardTitle')->textInput(['placeholder' => 'Название товара...', 'class' => 'form-control input-sm'])->label('Название') ?>
        </div>

        <div class="col-md-3 btn-filter-group">
            <?= Html::submitButton('Применить', ['class' => 'btn btn-primary btn-sm']) ?>
            <?= Html::a('Сбросить', ['index'], ['class' => 'btn btn-default btn-sm']) ?>
        </div>
        
        <div class="clearfix"></div>

        <div class="col-md-2">
            <?= $form->field($searchModel, 'category')->textInput(['placeholder' => 'Категория', 'class' => 'form-control input-sm'])->label('Категория') ?>
        </div>
        
        <div class="col-md-2">
            <?= $form->field($searchModel, 'supplier_article')->textInput(['placeholder' => 'Ваш артикул', 'class' => 'form-control input-sm'])->label('Артикул поставщика') ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>

    <?= DataTables::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'clientOptions' => [
//            'ajax' => [
//                'url' => yii\helpers\Url::to(['data']), // Ссылка на экспорт данных
//                ],
            'language' => [
                'url' => '//cdn.datatables.net/plug-ins/1.10.25/i18n/Russian.json',
                ],
            'pageLength' => 100,
            'lengthMenu' => [50, 100, 200],
            'ordering' => 'true',
            'dom' => 'lrtip', 
        ],
        'columns' => [
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view}',
                'buttonOptions' => ['class' => 'btn btn-xs btn-default'],
            ],
            [
                'attribute' => 'date',
                'value' => function($model) {
                    return date('d.m.Y H:i', strtotime($model['date'])); // ->date
                },
            ],
            [
                'attribute' => 'nm_id',
                'format' => 'raw',
                'value' => function($model) {
                    return $model->nm_id ? Html::a((string)$model->nm_id, 
                        "https://www.wildberries.ru/catalog/{$model->nm_id}/detail.aspx", 
                        ['target' => '_blank', 'style' => 'text-decoration: underline; font-weight: bold;']) : null;
                },
            ],
            [
                'attribute' => 'cardTitle',
                'label' => 'Название',
                'value' => function($model) {
                    return $model->card->title ?? '<span class="text-muted">—</span>';
                },
                'format' => 'raw',
            ],
            'supplier_article',
            'brand',
            [
                'attribute' => 'finished_price',
                'contentOptions' => ['style' => 'text-align:right; font-weight:bold'],
                'format' => ['decimal', 0],
            ],
            [
                'attribute' => 'is_cancel',
                'label' => 'Статус',
                'value' => function($model) {
                    return $model->is_cancel ? 'Отмена' : 'Ок';
                },
                'contentOptions' => function($model) {
                    return ['style' => 'font-weight:bold;', 'class' => $model->is_cancel ? 'text-danger' : 'text-success'];
                }
            ],
            'warehouse_name',
            'g_number',
        ],
    ]); ?>
</div>