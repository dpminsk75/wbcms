<?php
/** @var yii\web\View $this */
$this->title = 'Создание правила автоответа';
$this->params['breadcrumbs'][] = ['label' => 'Автоответы', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wb-reply-rule-create">
    <h1><?= \yii\helpers\Html::encode($this->title) ?></h1>
    <?= $this->render('_form', [
        'model' => $model,
        'greetings' => $greetings,
        'bodies' => $bodies,
        'signoffs' => $signoffs,
    ]) ?>
</div>