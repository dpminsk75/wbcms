<?php

/** @var yii\web\View $this */
/** @var \yii\data\ArrayDataProvider $dataProvider */

use kartik\builder\TabularForm;
use kartik\form\ActiveForm;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\web\View;

$this->title = 'Типы продуктов';
$this->params['breadcrumbs'][] = 'Справочники';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="product-type-index">
    <?php $form = ActiveForm::begin([
        'id' => 'product-type-form',
        'enableClientValidation' => false, // Отключаем проверку в браузере
        'enableAjaxValidation' => false,   // Отключаем Ajax-проверку
        'options' => ['novalidate' => 'novalidate'], // Добавляем, чтобы браузер не блокировал пустые поля
    ]); ?>

    <?= TabularForm::widget([
        'form' => $form,
        'dataProvider' => $dataProvider,
        'actionColumn' => false,
        'checkboxColumn' => false,
        'attributes' => [
            'id' => [
                'type' => TabularForm::INPUT_HIDDEN,
                'columnOptions' => ['hidden' => true],
            ],
            'name' => [
                'label' => 'Название',
                'type' => TabularForm::INPUT_TEXT,
                'columnOptions' => [
                    'headerOptions' => ['style' => 'min-width:200px;'],
                    'contentOptions' => ['style' => 'min-width:200px;'],
                ],

            ],
            'description' => [
                'label' => 'Описание',
                'type' => TabularForm::INPUT_TEXTAREA,
                'options' => ['rows' => 2],
                'columnOptions' => [
                    'headerOptions' => ['style' => 'min-width:300px;'],
                    'contentOptions' => ['style' => 'min-width:300px;'],
                ],

            ],
            '_delete' => [
                'label' => 'Удалить',
                'type' => TabularForm::INPUT_CHECKBOX,
                'options' => ['class' => 'delete-checkbox'],
                'columnOptions' => [
                    'headerOptions' => ['style' => 'width:90px; text-align:center;'],
                    'contentOptions' => ['style' => 'width:90px; text-align:center;'],
                ],

            ],
/*
            'created_at' => [
                'label' => 'Создано',
                'type' => TabularForm::INPUT_STATIC,
                'format' => ['datetime', 'php:d.m.Y H:i'],
                'columnOptions' => ['style' => 'width:170px; white-space:nowrap;'],
            ],
*/
        ],
        'gridSettings' => [
            'condensed' => true,
            'floatHeader' => true,
            'panel' => [
                'heading' => '<i class="fas fa-layer-group"></i> Типы продуктов',
                'type' => GridView::TYPE_PRIMARY,
                'before' => Html::submitButton('Сохранить', ['class' => 'btn btn-success']),
                'after' => false,
            ],
        ],
    ]); ?>

    <?php ActiveForm::end(); ?>
</div>

<?php
$js = <<<JS
var \$form = $('#product-type-form');

// Используем ТОЛЬКО beforeSubmit. Это событие Yii2, которое срабатывает перед отправкой.
\$form.off('beforeSubmit').on('beforeSubmit', function () {
    var checkedCount = $('.delete-checkbox:checked').length;
    
    if (checkedCount > 0) {
        if (!confirm('Вы отметили для удаления записей: ' + checkedCount + '. Продолжить?')) {
            return false; // Отмена отправки
        }
    }
    return true; // Разрешение отправки
});

// УДАЛИТЕ ИЛИ ЗАКОММЕНТИРУЙТЕ БЛОК \$form.on('submit', ...), который был здесь раньше!
JS;

$this->registerJs($js, \yii\web\View::POS_READY);
?>