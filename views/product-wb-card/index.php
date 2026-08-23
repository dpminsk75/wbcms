<?php

use yii\helpers\Html;
use kartik\dynagrid\DynaGrid;
use kartik\grid\GridView;

use kartik\select2\Select2;
use kartik\icons\Icon;
Icon::map($this); 


/** @var yii\web\View $this */
/** @var app\models\ProductWbCardSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Составные товары (Комплекты)';
$this->params['breadcrumbs'][] = 'Справочники';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="product-wb-card-index">

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'pjax' => true,
        'striped' => false, // Отключаем полоски для лучшего вида группировки
        'hover' => true,
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => '<i class="fas fa-link"></i> ' . Html::encode($this->title),
            'before' => Html::a('<i class="fas fa-plus"></i> Создать новый комплект', ['create'], ['class' => 'btn btn-success']),
        ],
        'columns' => [
            // Колонки считаются с нуля:
            // 0: SerialColumn
            ['class' => 'kartik\grid\SerialColumn'],

            // 1: Название карточки (Главная колонка для группировки)
            [
                'attribute' => 'wb_nm_id',
                'label' => 'Составная карточка',
                'value' => function($model) {
                    return "<b>" . ($model->wbCard->title ?? 'Без названия') . "</b>";
                },
                'format' => 'raw',
                'group' => true,
                'groupedRow' => false,
                'headerOptions' => ['style' => 'font-size: 12px; width: 300px;'], 
                'contentOptions' => ['style' => 'font-size: 13px; width: 300px;'], 
            ],

            // 2: nmID (привязан к группе в колонке 1)
            [
                'attribute' => 'wb_nm_id',
                'label' => 'Арт WB',
                'group' => true,
                'subGroupOf' => 1,
                'headerOptions' => ['style' => 'font-size: 12px; width: 150px;'], 
                'contentOptions' => ['style' => 'font-size: 13px; width: 150px;'], 
            ],

            // 3: Артикул (привязан к группе в колонке 1)
            [
                'label' => 'Артикул',
                'value' => 'wbCard.vendorCode',
                'group' => true,
                'subGroupOf' => 1,
                'headerOptions' => ['style' => 'font-size: 12px; width: 200px;'], 
                'contentOptions' => ['style' => 'font-size: 13px; width: 200px;'], 
            ],

            // 4: Товар (уникален для каждой строки, не группируется)
            [
                'attribute' => 'productName',
                'label' => 'Товар',
                'value' => 'product.name',
                'headerOptions' => ['style' => 'font-size: 12px; width: 300px;'], 
                'contentOptions' => ['style' => 'font-size: 13px; width: 300px;'], 
            ],

            // 5: Кол-во
            [
                'attribute' => 'q',
                'label' => 'Кол-во',
                'width' => '100px',
                'hAlign' => 'center',
                'headerOptions' => ['style' => 'font-size: 12px; width: 50px;'], 
                'contentOptions' => ['style' => 'font-size: 13px; width: 50px;'], 
            ],

            // 6: Процент
            [
                'attribute' => 'p',
                'label' => '% в цене',
                'value' => function($model) {
                    return $model->p . ' %';
                },
                'width' => '100px',
                'hAlign' => 'center',
                'headerOptions' => ['style' => 'font-size: 12px; width: 50px;'], 
                'contentOptions' => ['style' => 'font-size: 13px; width: 50px;'], 
            ],

            // 7: Действия (используем обычный массив вместо ActionColumn для поддержки группировки)
            [
                'label' => 'Действия',
                'format' => 'raw',
                'width' => '120px',
                'value' => function ($model) {
                    $buttons = Html::a('<i class="fas fa-pencil-alt"></i>', ['update', 'id' => $model->id], [
                        'class' => 'btn btn-xs btn-outline-primary',
                        'title' => 'Редактировать группу',
                        'data-pjax' => '0',
                        'style' => 'margin-right: 5px;'
                    ]);
                    
                    $buttons .= Html::a('<i class="fas fa-trash"></i>', ['delete', 'id' => $model->id], [
                        'class' => 'btn btn-xs btn-outline-danger',
                        'title' => 'Удалить',
                        'data-method' => 'post',
                        'data-confirm' => 'Вы уверены, что хотите удалить эту запись?',
                    ]);
                    
                    return $buttons;
                },
                'group' => true,
                'subGroupOf' => 1, // Кнопка будет одна на всю группу карточки
                'vAlign' => GridView::ALIGN_MIDDLE,
                'hAlign' => GridView::ALIGN_CENTER,
            ],
        ],
    ]); ?>

</div>