<?php
use yii\bootstrap5\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Company $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Компании', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="company-view">
    <h1><?= Html::encode($this->title) ?></h1>
    <p>
        <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Удалить (деактивировать)', ['delete', 'id' => $model->id], ['class' => 'btn btn-danger', 'data-method' => 'post', 'data-confirm' => 'Деактивировать?']) ?>
    </p>
    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'name',
            'abbreviation',
            'inn',
            ['attribute' => 'api_key', 'value' => $model->api_key ? mb_substr($model->api_key, 0, 20) . '...' : '(не задан)'],
            'is_active:boolean',
            'fbs_deduct_enabled:boolean',
            'fbs_deduct_test:boolean',
            'created_at:datetime',
            'updated_at:datetime',
        ],
    ]) ?>
</div>
