<?php

/** @var yii\web\View $this */
/** @var app\models\Tag $model */
/** @var app\models\WbCardSearch $wbSearchModel */
/** @var yii\data\ActiveDataProvider $wbDataProvider */
/** @var app\models\WbCard[] $selectedCards */

use yii\helpers\Html;

$this->title = 'Редактирование тега: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Теги', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="tag-create">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('include/_form', [
        'model' => $model,
        'wbSearchModel' => $wbSearchModel,
        'wbDataProvider' => $wbDataProvider,
        'selectedCards' => $selectedCards ?? [],
    ]) ?>
</div>