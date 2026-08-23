<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */

/** @var app\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

//$this->title = 'Login';
/*
$this->title = 'Авторизация';
$this->params['breadcrumbs'][] = $this->title;
*/

/*    <h1><?= Html::encode($this->title) ?></h1> */
?>
<div class="site-login vh-100 d-flex align-items-center justify-content-center">
    <div class="row">
        <div class="col-lg-12 card shadow p-4">
    <p>Введите ваш логин и пароль:</p>

            <?php $form = ActiveForm::begin([
                'id' => 'login-form',
                'fieldConfig' => [
                    'template' => "{label}\n{input}\n{error}",
                    'labelOptions' => ['class' => 'col-md-12 col-form-label mr-lg-3'],
                    'inputOptions' => ['class' => 'col-md-12 form-control'],
                    'errorOptions' => ['class' => 'col-md-12 invalid-feedback'],
                ],
            ]); ?>


            <?= $form->field($model, 'username')->textInput(['autofocus' => true])->label('Логин') ?>
            <?= $form->field($model, 'password')->passwordInput()->label('Пароль') ?>
            <?= $form->field($model, 'rememberMe')->checkbox([
                'template' => "<div class=\"custom-control custom-checkbox\">{input} {label}</div>\n<div class=\"col-lg-8\">{error}</div>",
            ])->label('Запомнить меня') ?>


            <div class="form-group">
                <div>
                    <?= Html::submitButton('Login', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
                </div>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<style>
#login-form {margin-bottom: 0px;}
</style>
