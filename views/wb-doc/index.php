<?php
use yii\helpers\Html;
use kartik\icons\Icon;
use yii\bootstrap5\BootstrapIconAsset;
use kartik\grid\GridView;
use yii\helpers\Url;

Icon::map($this);
BootstrapIconAsset::register($this);

/** @var yii\data\ActiveDataProvider $dataProvider */

/** @var string|null $type */
$this->title = 'Документы склада';
$this->params['breadcrumbs'][] = $this->title;
$whCache = [];
$whName = function($id) use (&$whCache) {
    if(!$id) return '—';
    if(isset($whCache[$id])) return $whCache[$id];
    $w = \app\models\OurWarehouse::findOne((int)$id);
    $v = $w ? $w->name : '#'.$id;
    $whCache[$id] = $v;
    return $v;
};
?>
<div class="wb-doc-index">
    <h1><?= Html::encode($this->title) ?></h1>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header py-2"><i class="fas fa-plus me-1"></i> <b>Создать</b></div>
                <div class="card-body d-flex gap-2 flex-wrap">
                    <?= Html::a('Приход', ['create','type'=>'RECEIPT'], ['class'=>'btn btn-success btn-sm py-2 px-3']) ?>
                    <?= Html::a('Расход/Списание', ['create','type'=>'EXPENSE'], ['class'=>'btn btn-warning btn-sm py-2 px-3']) ?>
                    <?= Html::a('Перемещение', ['create','type'=>'TRANSFER'], ['class'=>'btn btn-primary btn-sm py-2 px-3']) ?>
                    <?= Html::a('Инвентаризация', ['create','type'=>'INVENTORY'], ['class'=>'btn btn-info btn-sm py-2 px-3']) ?>
                    <?= Html::a('Корректировка', ['create','type'=>'ADJUSTMENT'], ['class'=>'btn btn-danger btn-sm py-2 px-3']) ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header py-2"><i class="fas fa-filter me-1"></i> <b>Фильтр</b></div>
                <div class="card-body d-flex gap-2 flex-wrap">
                    <?php $isAll = empty($type); ?>
                    <?= Html::a('Все', ['index'], ['class'=>'btn btn-sm py-2 px-3 '.($isAll?'btn-primary':'btn-outline-secondary')]) ?>
                    <?= Html::a('Приходы', ['index','type'=>'RECEIPT'], ['class'=>'btn btn-sm py-2 px-3 '.($type==='RECEIPT'?'btn-success':'btn-outline-secondary')]) ?>
                    <?= Html::a('Расходы', ['index','type'=>'EXPENSE'], ['class'=>'btn btn-sm py-2 px-3 '.($type==='EXPENSE'?'btn-warning':'btn-outline-secondary')]) ?>
                    <?= Html::a('Перемещения', ['index','type'=>'TRANSFER'], ['class'=>'btn btn-sm py-2 px-3 '.($type==='TRANSFER'?'btn-primary':'btn-outline-secondary')]) ?>
                    <?= Html::a('Инвентаризации', ['index','type'=>'INVENTORY'], ['class'=>'btn btn-sm py-2 px-3 '.($type==='INVENTORY'?'btn-info':'btn-outline-secondary')]) ?>
                    <?= Html::a('Корректировки', ['index','type'=>'ADJUSTMENT'], ['class'=>'btn btn-sm py-2 px-3 '.($type==='ADJUSTMENT'?'btn-danger':'btn-outline-secondary')]) ?>
                </div>
            </div>
        </div>
    </div>
    <?= GridView::widget([
        'dataProvider'=>$dataProvider,
        'pjax'=>false,
        'bordered'=>true,'striped'=>true,'condensed'=>true,'hover'=>true,'responsive'=>true,
        'panel'=>['type'=>GridView::TYPE_PRIMARY,'heading'=>'Документы','before'=>false,'after'=>false],
        'export' => [
            'showConfirmAlert' => false,
            'target' => GridView::TARGET_BLANK,
            'batchSize' => 1000,
        ],
        'exportConfig' => [
            GridView::EXCEL => ['label' => 'Сохранить в Excel'],
        ],
        'columns'=>[
            ['attribute'=>'id','label'=>'ID','contentOptions'=>['style'=>'width:60px;text-align:center']],
            ['attribute'=>'date','label'=>'Дата','format'=>['datetime','php:d.m.Y H:i'],'contentOptions'=>['style'=>'white-space:nowrap;width:140px']],
            [
                'attribute'=>'type',
                'label'=>'Тип',
                'value'=>function($m){
                    $map=['RECEIPT'=>'Приход','EXPENSE'=>'Расход','TRANSFER'=>'Перемещение','INVENTORY'=>'Инвентаризация','ADJUSTMENT'=>'Корректировка'];
                    return $map[$m->type] ?? $m->type;
                },
            ],
            ['attribute'=>'status','label'=>'Статус','contentOptions'=>['style'=>'width:90px']],
            [
                'label'=>'Склад',
                'value'=>function($m) use ($whName){
                    if($m->type==='TRANSFER' && $m->to_warehouseId) return $whName($m->warehouseId).' → '.$whName($m->to_warehouseId);
                    return $whName($m->warehouseId);
                }
            ],
            ['attribute'=>'comment','label'=>'Комментарий','contentOptions'=>['style'=>'max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis']],
            [
                'header'=>'Действия',
                'class'=>'kartik\grid\ActionColumn',
                'template'=>'{view} {update} {delete}',
                'visibleButtons'=>[
                    'update'=>fn($m)=> in_array($m->status,['draft','canceled'],true),
                    'delete'=>fn($m)=> in_array($m->status,['draft','canceled'],true),
                ],
                'buttons'=>[
                    'view'=>fn($u,$m)=>Html::a('Открыть',['view','id'=>$m->id],['class'=>'btn btn-sm py-1 px-2 btn-primary']),
                    'update'=>fn($u,$m)=>Html::a('Ред.',['update','id'=>$m->id],['class'=>'btn btn-sm py-1 px-2 btn-warning']),
                    'delete'=>fn($u,$m)=>Html::a('Удал.',['delete','id'=>$m->id],['class'=>'btn btn-sm py-1 px-2 btn-danger','data-method'=>'post','data-confirm'=>'Удалить черновик #'.$m->id.'?']),
                ],
            ],
        ],
    ]) ?>
</div>
