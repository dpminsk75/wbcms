<?php
use yii\grid\GridView;
use yii\helpers\Html;

$this->title = 'Детализация отчета';

// Получаем список всех колонок таблицы, кроме технических
$columns = Yii::$app->db->getTableSchema('detail_by_period')->columnNames;
$gridColumns = [];

foreach ($columns as $column) {
    if (in_array($column, ['id', 'created_at'])) continue;
    $gridColumns[] = [
        'attribute' => $column,
        'contentOptions' => ['style' => 'min-width: 120px; font-size: 11px; white-space: nowrap;'],
    ];
}
?>

<div class="report-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div style="overflow-x: auto;">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => $gridColumns,
            'tableOptions' => ['class' => 'table table-striped table-bordered'],
        ]); ?>
    </div>
</div>