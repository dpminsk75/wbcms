<?php
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var app\models\Company $model */
?>
<?php $form = ActiveForm::begin() ?>
<div class="row">
    <div class="col-md-6">
        <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
        <?= $form->field($model, 'abbreviation')->textInput(['maxlength' => true, 'placeholder' => 'Короткое имя для гридов']) ?>
        <?= $form->field($model, 'inn')->textInput(['maxlength' => true]) ?>
        <?= $form->field($model, 'api_key')->textarea(['rows' => 3, 'placeholder' => 'JWT WB, сохранится как есть']) ?>
    </div>
    <div class="col-md-6">
        <?= $form->field($model, 'is_active')->checkbox() ?>
        <?= $form->field($model, 'fbs_deduct_enabled')->checkbox() ?>
        <?= $form->field($model, 'fbs_deduct_test')->checkbox()->hint('1=сухое списание в лог, 0=реальное') ?>
    </div>
</div>
<div class="form-group">
    <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    <?= Html::a('Отмена', ['index'], ['class' => 'btn btn-secondary']) ?>
</div>
<?php ActiveForm::end() ?>
