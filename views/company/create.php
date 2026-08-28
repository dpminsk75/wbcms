<?php
/** @var yii\web\View $this */
/** @var app\models\Company $model */
$this->title = 'Новая компания';
$this->params['breadcrumbs'][] = ['label' => 'Компании', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="company-create">
    <h1><?= $this->title ?></h1>
    <?= $this->render('_form', compact('model')) ?>
</div>
