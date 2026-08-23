<?php
/** @var yii\web\View $this */
/** @var app\models\WbReplyRule $model */

$this->title = 'Редактирование правила: ' . $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Автоответы', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Редактирование';
?>
<div class="wb-reply-rule-update">
    <h1><?= \yii\helpers\Html::encode($this->title) ?></h1>
    <?= $this->render('_form', [
        'model' => $model,
        'greetings' => $greetings,
        'bodies' => $bodies,
        'signoffs' => $signoffs,
        'selectedBrands' => $selectedBrands,     
        'selectedProducts' => $selectedProducts, 
    ]) ?>
</div>