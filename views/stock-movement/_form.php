<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use yii\web\JqueryAsset;
use app\models\WbCard;
use app\models\StockMovement;

/** @var app\models\StockMovement $model */

$this->title = $model->isNewRecord ? 'Добавить движение' : 'Редактировать движение #' . $model->id;

// текущее значение (для режима редактирования) - чтобы select2 сразу показал выбранный товар,
// не заставляя пользователя искать его заново
$initialLabel = '';
if ($model->nm_id) {
    $card = WbCard::findOne($model->nm_id);
    if ($card) {
        $initialLabel = $card->vendorCode . ' / nmID ' . $card->nmID . ' — ' . $card->title;
    }
}

$this->registerCssFile('https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css');
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js', [
    'depends' => [JqueryAsset::class],
]);

$searchUrl = Url::to(['stock-movement/search-cards']);
$this->registerJs(<<<JS
$('#stockmovement-nm_id').select2({
    placeholder: 'Начните вводить артикул, nmID или название товара',
    allowClear: true,
    minimumInputLength: 1,
    width: '100%',
    ajax: {
        url: '{$searchUrl}',
        dataType: 'json',
        delay: 250,
        data: function (params) { return { q: params.term }; },
        processResults: function (data) { return { results: data }; }
    }
});
JS
);
?>
<div class="stock-movement-form">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(); ?>

    <div class="form-group field-stockmovement-nm_id">
        <?= Html::activeLabel($model, 'nm_id', ['label' => 'Товар (nmID)']) ?>
        <select id="stockmovement-nm_id" name="StockMovement[nm_id]" class="form-control">
            <?php if ($model->nm_id): ?>
                <option value="<?= $model->nm_id ?>" selected><?= Html::encode($initialLabel) ?></option>
            <?php endif; ?>
        </select>
        <?= Html::error($model, 'nm_id', ['class' => 'help-block']) ?>
    </div>

    <?= $form->field($model, 'type')->dropDownList(StockMovement::typeLabels()) ?>

    <?= $form->field($model, 'qty')->textInput(['type' => 'number'])
        ->hint('Для "Корректировка" и "Списание" можно указывать отрицательное число.') ?>

    <?= $form->field($model, 'movement_date')->input('date') ?>

    <?= $form->field($model, 'comment')->textInput(['maxlength' => 255]) ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
