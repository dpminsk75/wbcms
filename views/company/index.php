<?php
use yii\bootstrap5\Html;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\CompanySearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Компании';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="company-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Создать', ['create'], ['class' => 'btn btn-success']) ?>
    </div>
    <div class="table-responsive">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            'id',
            'name',
            'abbreviation',
            'inn',
            [
                'attribute' => 'is_active',
                'value' => fn($m) => $m->is_active ? 'Да' : 'Нет',
                'filter' => [1 => 'Да', 0 => 'Нет'],
            ],
            'fbs_deduct_enabled:boolean',
            'fbs_deduct_test:boolean',
            'updated_at:datetime',
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view} {update} {delete}',
            ],
        ],
    ]) ?>
    </div>
</div>
