<?php
/** @var yii\web\View $this */
/** @var app\models\Company $model */
$this->title = 'Редактировать: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Компании', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Редактировать';
?>
<div class="company-update">
    <h1><?= $this->title ?></h1>
    <?= $this->render('_form', compact('model')) ?>
</div>
