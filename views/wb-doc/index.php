<?php
use yii\helpers\Html;
use kartik\grid\GridView;
use yii\helpers\Url;

/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string|null $type */
$this->title = 'Документы склада';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wb-doc-index">
    <h1><?= Html::encode($this->title) ?></h1>
    <p>
        <?= Html::a('Приход', ['create','type'=>'RECEIPT'], ['class'=>'btn btn-success']) ?>
        <?= Html::a('Расход/Списание', ['create','type'=>'EXPENSE'], ['class'=>'btn btn-warning']) ?>
        <?= Html::a('Перемещение', ['create','type'=>'TRANSFER'], ['class'=>'btn btn-primary']) ?>
        <?= Html::a('Инвентаризация', ['create','type'=>'INVENTORY'], ['class'=>'btn btn-info']) ?>
        <?= Html::a('Корректировка', ['create','type'=>'ADJUSTMENT'], ['class'=>'btn btn-danger']) ?>
        <?= Html::a('Все', ['index'], ['class'=>'btn btn-default']) ?>
        <?= Html::a('Приходы', ['index','type'=>'RECEIPT'], ['class'=>'btn btn-default']) ?>
        <?= Html::a('Расходы', ['index','type'=>'EXPENSE'], ['class'=>'btn btn-default']) ?>
        <?= Html::a('Перемещения', ['index','type'=>'TRANSFER'], ['class'=>'btn btn-default']) ?>
        <?= Html::a('Инвентаризации', ['index','type'=>'INVENTORY'], ['class'=>'btn btn-default']) ?>
        <?= Html::a('Корректировки', ['index','type'=>'ADJUSTMENT'], ['class'=>'btn btn-default']) ?>
    </p>
    <?= GridView::widget([
        'dataProvider'=>$dataProvider,
        'pjax'=>false,
        'bordered'=>true,'striped'=>true,'condensed'=>true,'hover'=>true,'responsive'=>true,
        'panel'=>['type'=>GridView::TYPE_PRIMARY,'heading'=>'Документы','before'=>false,'after'=>false],
        'toolbar'=>['{export}','{toggleData}'],
        'export'=>['showConfirmAlert'=>false,'target'=>GridView::TARGET_BLANK],
        'exportConfig'=>[GridView::EXCEL=>['label'=>'Сохранить в Excel']],
        'columns'=>[
            'id',
            [
                'attribute'=>'type',
                'value'=>function($m){
                    $map=['RECEIPT'=>'Приход','EXPENSE'=>'Расход','TRANSFER'=>'Перемещение','INVENTORY'=>'Инвентаризация','ADJUSTMENT'=>'Корректировка'];
                    return $map[$m->type] ?? $m->type;
                },
            ],
            'status',
            [
                'attribute'=>'warehouseId',
                'label'=>'Склад / Откуда→Куда',
                'value'=>function($m){
                    if($m->type==='TRANSFER' && $m->to_warehouseId) return $m->warehouseId.' → '.$m->to_warehouseId;
                    return $m->warehouseId;
                }
            ],
            'date',
            'comment',
            [
                'class'=>'kartik\grid\ActionColumn',
                'template'=>'{view} {update} {delete}',
                'visibleButtons'=>[
                    'update'=>fn($m)=> in_array($m->status,['draft','canceled'],true),
                    'delete'=>fn($m)=> in_array($m->status,['draft','canceled'],true),
                ],
                'buttons'=>[
                    'view'=>fn($u,$m)=>Html::a('Открыть',['view','id'=>$m->id],['class'=>'btn btn-xs btn-primary']),
                    'update'=>fn($u,$m)=>Html::a('Ред.',['update','id'=>$m->id],['class'=>'btn btn-xs btn-warning']),
                    'delete'=>fn($u,$m)=>Html::a('Удал.',['delete','id'=>$m->id],['class'=>'btn btn-xs btn-danger','data-method'=>'post','data-confirm'=>'Удалить черновик #'.$m->id.'?']),
                ],
            ],
        ],
    ]) ?>
</div>
