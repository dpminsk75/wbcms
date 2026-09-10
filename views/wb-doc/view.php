<?php
use yii\helpers\Html;
use kartik\grid\GridView;

/** @var app\models\WbDoc $model */
/** @var array $itemsRaw */
/** @var yii\data\ArrayDataProvider $dataProvider */
$this->title = 'Документ #'.$model->id.' '.$model->type.' '.$model->status;
$this->params['breadcrumbs'][] = ['label'=>'Документы','url'=>['index']];
$this->params['breadcrumbs'][] = $this->title;

function whName($id){
    if(!$id) return '—';
    $w = \app\models\OurWarehouse::findOne((int)$id);
    return $w ? Html::encode($w->name.' (#'.$w->id.')') : Html::encode((string)$id);
}
$typeLabels = ['RECEIPT'=>'Приход','EXPENSE'=>'Расход','TRANSFER'=>'Перемещение','INVENTORY'=>'Инвентаризация','ADJUSTMENT'=>'Корректировка'];
$typeLabel = $typeLabels[$model->type] ?? $model->type;
$isInventory = $model->type === \app\models\WbDoc::TYPE_INVENTORY;
$isAdjustment = $model->type === \app\models\WbDoc::TYPE_ADJUSTMENT;
$isTransfer = $model->type === \app\models\WbDoc::TYPE_TRANSFER;
?>
<div class="wb-doc-view">
    <h1><?= Html::encode('Документ #'.$model->id.' '.$typeLabel.' — '.$model->status) ?></h1>
    <p>
        <?php if ($model->status==='draft'): ?>
            <?= Html::a('Провести', ['post','id'=>$model->id], ['class'=>'btn btn-success','data-method'=>'post','data-confirm'=>'Провести? Остатки изменятся']) ?>
            <?= Html::a('Редактировать', ['update','id'=>$model->id], ['class'=>'btn btn-warning']) ?>
            <?= Html::a('Удалить', ['delete','id'=>$model->id], ['class'=>'btn btn-danger','data-method'=>'post','data-confirm'=>'Удалить черновик?']) ?>
        <?php endif; ?>
        <?php if ($model->status==='posted'): ?>
            <?= Html::a('Отменить (сторно)', ['cancel','id'=>$model->id], ['class'=>'btn btn-warning','data-method'=>'post','data-confirm'=>'Отменить? Создаст сторно']) ?>
        <?php endif; ?>
        <?php if ($model->status==='canceled'): ?>
            <?= Html::a('Редактировать (вернет в черновик)', ['update','id'=>$model->id], ['class'=>'btn btn-warning']) ?>
            <?= Html::a('Удалить', ['delete','id'=>$model->id], ['class'=>'btn btn-danger','data-method'=>'post','data-confirm'=>'Удалить отмененный документ?']) ?>
        <?php endif; ?>
        <?php if($isInventory): ?>
            <?= Html::a('Печать акта', ['print','id'=>$model->id], ['class'=>'btn btn-info','target'=>'_blank']) ?>
        <?php endif; ?>
        <?= Html::button('<i class="fas fa-file-excel me-1"></i> Сохранить в Excel', ['class'=>'btn btn-success btn-sm','id'=>'export-view-btn']) ?>
        <?= Html::a('Назад', ['index'], ['class'=>'btn btn-default']) ?>
    </p>
    <table class="table table-bordered" style="max-width:700px">
        <tr><th>Тип</th><td><?= Html::encode($typeLabel) ?> (<?= Html::encode($model->type) ?>)</td></tr>
        <tr><th>Статус</th><td><?= Html::encode($model->status) ?></td></tr>
        <?php if($isTransfer): ?>
            <tr><th>Откуда</th><td><?= whName($model->warehouseId) ?></td></tr>
            <tr><th>Куда</th><td><?= whName($model->to_warehouseId) ?></td></tr>
        <?php else: ?>
            <tr><th>Склад</th><td><?= whName($model->warehouseId) ?></td></tr>
        <?php endif; ?>
        <tr><th>Дата</th><td><?= Html::encode($model->date) ?></td></tr>
        <tr><th>Комментарий</th><td><?= Html::encode($model->comment) ?></td></tr>
        <tr><th>Автор</th><td><?= Html::encode((string)$model->user_id) ?></td></tr>
    </table>

    <h3>Состав — <?= count($itemsRaw) ?> поз.</h3>

    <?php
    // колонки как в wb-fbs-virtual и как в заполняемом документе
    $cols = [];
    // Товар — красиво, но скрыт от экспорта
    $cols[] = [
        'label'=>'Товар',
        'format'=>'raw',
        'hiddenFromExport'=>true,
        'contentOptions'=>['style'=>'min-width:260px'],
        'value'=>function($m){
            $art = Html::encode($m['vendorCode'] ?? '—');
            $nm = Html::encode($m['nmID'] ?? $m['s_nmID'] ?? '');
            $title = Html::encode($m['title'] ?? '');
            return '<div style="font-weight:bold">'.$art.'</div><div style="font-size:11px;color:#666">'.$nm.' — '.$title.'</div>';
        }
    ];
    // для экспорта
    $cols[] = ['label'=>'Артикул продавца','attribute'=>'vendorCode','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['vendorCode'] ?? ''];
    $cols[] = ['label'=>'Наименование','attribute'=>'title','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['title'] ?? ''];
    $cols[] = ['label'=>'nmID','attribute'=>'nmID','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['nmID'] ?? $m['s_nmID'] ?? ''];
    // Баркод
    $cols[] = [
        'label'=>'Баркод',
        'attribute'=>'sku',
        'format'=>'raw',
        'contentOptions'=>['style'=>'font-family:monospace;white-space:nowrap;width:140px'],
        'value'=>fn($m)=> Html::encode($m['sku']),
    ];
    $cols[] = ['label'=>'chrtID','attribute'=>'chrtID','hidden'=>true,'hiddenFromExport'=>false];

    if($isInventory){
        $cols[] = ['label'=>'По учету','attribute'=>'qty_before','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;background:#f0f6ff;width:90px'],'value'=>fn($m)=> $m['qty_before'] ?? 0];
        $cols[] = ['label'=>'Факт','attribute'=>'qty_fact','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;background:#fff8e1;width:90px'],'value'=>function($m){
            $v = $m['qty_fact'] ?? $m['qty'] ?? '';
            return $v==='' ? '' : (int)$v;
        }];
        $cols[] = ['label'=>'Разница','format'=>'raw','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;width:90px'],'value'=>function($m){
            $fact = $m['qty_fact'] ?? $m['qty'] ?? 0;
            $before = $m['qty_before'] ?? 0;
            $d = (int)$fact - (int)$before;
            $c = $d===0?'#666':($d>0?'#0a0':'#c00');
            return '<span style="color:'.$c.';font-weight:bold">'.($d>0?'+':'').$d.'</span>';
        }];
        // для экспорта разница
        $cols[] = ['label'=>'Разница','attribute'=>'delta','hidden'=>true,'hiddenFromExport'=>false,'value'=>function($m){
            $fact = $m['qty_fact'] ?? $m['qty'] ?? 0;
            $before = $m['qty_before'] ?? 0;
            return (int)$fact - (int)$before;
        }];
    } elseif($isAdjustment){
        $cols[] = [
            'label'=>'Корректировка',
            'attribute'=>'qty',
            'format'=>'raw',
            'hAlign'=>'right',
            'contentOptions'=>function($m){ return ['style'=>'text-align:center;background:#fff3cd;width:110px;color:'.((int)$m['qty']>0?'#0a0':'#c00').';font-weight:bold']; },
            'value'=>function($m){ $v=(int)$m['qty']; return ($v>0?'+':'').$v; }
        ];
    } elseif($isTransfer){
        $cols[] = [
            'label'=>'Количество',
            'attribute'=>'qty',
            'hAlign'=>'right',
            'contentOptions'=>['style'=>'text-align:center;background:#fff8e1;width:110px'],
            'value'=>fn($m)=> (int)$m['qty']
        ];
    } else {
        // приход/расход
        $cols[] = [
            'label'=>'Количество',
            'attribute'=>'qty',
            'hAlign'=>'right',
            'contentOptions'=>['style'=>'text-align:center;background:#fff8e1;width:110px'],
            'value'=>fn($m)=> (int)$m['qty']
        ];
        $cols[] = ['label'=>'Цена','attribute'=>'price','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['price'] ?? ''];
    }
    ?>

    <div class="custom-compact-grid">
    <?= GridView::widget([
        'dataProvider'=>$dataProvider,
        'id'=>'view-spec-grid',
        'pjax'=>false,
        'bordered'=>true,'striped'=>true,'condensed'=>true,'hover'=>true,'responsive'=>true,'responsiveWrap'=>false,
        'panel'=>[
            'type'=>GridView::TYPE_PRIMARY,
            'heading'=>'Состав — '.count($itemsRaw).' поз.',
            'headingOptions'=>['class'=>'card-header text-white bg-wb'],
            'before'=>false,
            'after'=>false,
        ],
        'toolbar'=>['{export}'],
        'export'=>[
            'showConfirmAlert'=>false,
            'target'=>GridView::TARGET_BLANK,
        ],
        'exportConfig'=>[
            GridView::EXCEL=>['label'=>'Сохранить в Excel'],
        ],
        'columns'=>$cols,
    ]) ?>
    </div>
<script>
(function(){
  const btn=document.getElementById('export-view-btn');
  if(!btn) return;
  btn.addEventListener('click', function(e){
    e.preventDefault();
    // пробуем kartik экспорт, если есть
    const kBtn=document.querySelector('#view-spec-grid .export-excel');
    if(kBtn){ kBtn.click(); return; }
    const kDrop=document.querySelector('#view-spec-grid .dropdown-menu a');
    if(kDrop){ kDrop.click(); return; }
    // fallback — CSV из itemsRaw
    const items=<?= json_encode($itemsRaw, JSON_UNESCAPED_UNICODE) ?>;
    if(!items.length){ alert('Нет строк'); return; }
    let csv='\uFEFF';
    const isInv=<?= $isInventory?'true':'false' ?>, isAdj=<?= $isAdjustment?'true':'false' ?>;
    if(isInv) csv+='vendorCode;title;nmID;sku;По учету;Факт;Разница\n';
    else if(isAdj) csv+='vendorCode;title;nmID;sku;Корректировка\n';
    else csv+='vendorCode;title;nmID;sku;Количество\n';
    items.forEach(r=>{
      const vc=(r.vendorCode||'').replace(/;/g,',');
      const tl=(r.title||'').replace(/;/g,',');
      const nm=r.nmID||r.s_nmID||'';
      const sku=r.sku||'';
      if(isInv){
        const before=r.qty_before??0, fact=r.qty_fact??r.qty??0, delta=(parseInt(fact)-parseInt(before));
        csv+=`"${vc}";"${tl}";"${nm}";"${sku}";"${before}";"${fact}";"${delta}"\n`;
      } else {
        csv+=`"${vc}";"${tl}";"${nm}";"${sku}";"${r.qty}"\n`;
      }
    });
    const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
    const url=URL.createObjectURL(blob);
    const a=document.createElement('a'); a.href=url; a.download='doc_'+<?= $model->id ?>+'_spec.csv'; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
  });
})();
</script>

    <?php if($isInventory): ?>
        <?php
        $sumPlus=0; $sumMinus=0; $cntPlus=0; $cntMinus=0;
        foreach($itemsRaw as $it){
            $fact = $it['qty_fact'] ?? $it['qty'] ?? 0;
            $before = $it['qty_before'] ?? 0;
            $d = (int)$fact - (int)$before;
            if($d>0){ $sumPlus+=$d; $cntPlus++; } elseif($d<0){ $sumMinus+=$d; $cntMinus++; }
        }
        ?>
        <div class="alert alert-info" style="max-width:700px">
            Позиций: <?= count($itemsRaw) ?>, излишков: <?= $cntPlus ?> (+<?= $sumPlus ?>), недостач: <?= $cntMinus ?> (<?= $sumMinus ?>)
        </div>
    <?php elseif($isAdjustment): ?>
        <?php $sumPlus=0; $sumMinus=0; foreach($itemsRaw as $it){ if((int)$it['qty']>0) $sumPlus+=(int)$it['qty']; else $sumMinus+=(int)$it['qty']; } ?>
        <div class="alert alert-info" style="max-width:700px">
            Позиций: <?= count($itemsRaw) ?>, оприходовано: +<?= $sumPlus ?>, списано: <?= $sumMinus ?>
        </div>
    <?php endif; ?>
</div>
