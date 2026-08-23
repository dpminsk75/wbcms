<?php
use yii\helpers\Html;

$this->title = 'Создать новый комплект';
$this->params['breadcrumbs'][] = ['label' => 'Составные товары', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="product-wb-card-create">
    <div class="card">
        <div class="card-body">
            <h1><?= Html::encode($this->title) ?></h1>
            <?= $this->render('_form', ['model' => $model]) ?>
        </div>
    </div>
</div>