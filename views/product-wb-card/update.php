<?php
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\ProductWbCard $model */
/** @var app\models\ProductWbCard[] $existingItems */

$this->title = 'Редактирование комплекта: ' . $model->wb_nm_id;
$this->params['breadcrumbs'][] = ['label' => 'Составные товары', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Редактирование';
?>
<div class="product-wb-card-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'existingItems' => $existingItems,
    ]) ?>

</div>