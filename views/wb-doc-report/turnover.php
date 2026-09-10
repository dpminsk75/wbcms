<?php
use yii\helpers\Html;
use kartik\icons\Icon;
use yii\bootstrap5\BootstrapIconAsset;
use yii\helpers\Url;
use kartik\grid\GridView;

Icon::map($this);
BootstrapIconAsset::register($this);

/** @var yii\data\ArrayDataProvider $dataProvider */
Icon::map($this);
BootstrapIconAsset::register($this);

/** @var app\models\OurWarehouse[] $warehouses */
$this->title = 'Оборотная ведомость';
$this->params['breadcrumbs'][] = ['label'=>'Склад','url'=>['/wb-doc/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wb-stock-report-turnover">
    <h1><?= Html::encode($this->title) ?></h1>
    <p class="text-muted">За период по складам: нач.остаток, приход/расход, перемещения, инвентаризация, корректировка, кон.остаток.</p>
    <form method="get" class="row g-2 mb-3 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Склад</label>
            <select name="warehouseId" class="form-select">
                <option value="all" <?= $warehouseId==='all'?'selected':'' ?>>Все склады</option>
                <?php foreach($warehouses as $w): ?>
                    <option value="<?= $w->id ?>" <?= (string)$warehouseId===(string)$w->id?'selected':'' ?>><?= Html::encode($w->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">С</label>
            <input type="date" name="dateFrom" value="<?= Html::encode($dateFrom) ?>" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">По</label>
            <input type="date" name="dateTo" value="<?= Html::encode($dateTo) ?>" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Поиск (баркод/vendorCode/наименование/nmID)</label>
            <input type="text" name="q" value="<?= Html::encode($q ?? '') ?>" class="form-control" placeholder="часть...">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Показать</button>
            <a href="<?= Url::to(['turnover']) ?>" class="btn btn-outline-secondary">Сброс</a></div>
    </form>

    <div class="custom-compact-grid">
    <?= GridView::widget([
        'dataProvider'=>$dataProvider,
        'pjax'=>false,
        'bordered'=>true,'striped'=>true,'condensed'=>true,'hover'=>true,
        'toolbar'=>['{export}'],
        'panel'=>[
                'type' => GridView::TYPE_PRIMARY,
                'heading'=>'Оборотка — '.count($dataProvider->allModels).' поз.',
                'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                'after' => false,
                ],
        'export' => [
            'showConfirmAlert' => false,
            'target' => GridView::TARGET_BLANK,
            'batchSize' => 1000,
        ],
        'exportConfig' => [
            GridView::EXCEL => ['label' => 'Сохранить в Excel'],
        ],
        'columns'=>[
            [
                'label'=>'Товар',
                'format'=>'raw',
                'hiddenFromExport'=>true,
                'contentOptions'=>['style'=>'min-width:220px'],
                'value'=>function($m){
                    $art=Html::encode($m['vendorCode']??'—');
                    $nm=Html::encode($m['nmID']??'');
                    $title=Html::encode($m['title']??'');
                    return '<div style="font-weight:bold">'.$art.'</div><div style="font-size:11px;color:#666">'.$nm.' — '.$title.'</div>';
                }
            ],
            ['label'=>'Артикул','attribute'=>'vendorCode','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['vendorCode']??''],
            ['label'=>'Баркод','attribute'=>'sku','contentOptions'=>['style'=>'font-family:monospace;width:120px'],'value'=>fn($m)=> $m['sku']],
            ['label'=>'nmID','attribute'=>'nmID','hidden'=>true,'hiddenFromExport'=>false],
            ['label'=>'Нач.','attribute'=>'startQty','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;background:#f0f6ff;width:70px'],'value'=>fn($m)=> (int)($m['startQty']??0)],
            ['label'=>'Приход','attribute'=>'receipt','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;background:#e8f5e9;width:70px;color:#0a0'],'value'=>fn($m)=> (int)($m['receipt']??0)],
            ['label'=>'Расход','attribute'=>'expense','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;background:#ffebee;width:70px;color:#c00'],'value'=>fn($m)=> (int)($m['expense']??0)],
            ['label'=>'Перем+','attribute'=>'t_in','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;width:70px'],'value'=>fn($m)=> (int)($m['t_in']??0)],
            ['label'=>'Перем-','attribute'=>'t_out','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;width:70px'],'value'=>fn($m)=> (int)($m['t_out']??0)],
            ['label'=>'Инв.','attribute'=>'inv','hAlign'=>'right','format'=>'raw','contentOptions'=>['style'=>'text-align:center;width:70px'],'value'=>function($m){
                $v=(int)($m['inv']??0); return '<span style="color:'.($v>0?'#0a0':($v<0?'#c00':'#666')).'">'.($v>0?'+':'').$v.'</span>';
            }],
            ['label'=>'Корр.','attribute'=>'adj','hAlign'=>'right','format'=>'raw','contentOptions'=>['style'=>'text-align:center;width:70px'],'value'=>function($m){
                $v=(int)($m['adj']??0); return '<span style="color:'.($v>0?'#0a0':($v<0?'#c00':'#666')).'">'.($v>0?'+':'').$v.'</span>';
            }],
            ['label'=>'Кон.','attribute'=>'endQty','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;background:#f0f6ff;width:70px;font-weight:bold'],'value'=>fn($m)=> (int)($m['endQty']??0)],
            ['label'=>'Оборот','attribute'=>'turnover','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;background:#fff8e1;width:70px'],'value'=>fn($m)=> (int)($m['turnover']??0)],
        ],
    ]) ?>
    </div>
<script>
document.getElementById('export-manual-btn')?.addEventListener('click', function(){
  const grid=document.getElementById('turnover-grid');
  const kBtn=grid?.querySelector('a.export-excel');
  if(kBtn){ kBtn.click(); return; }
  const drop=grid?.querySelector('.dropdown-menu a');
  if(drop){ drop.click(); return; }
  const data=<?= json_encode($dataProvider->allModels, JSON_UNESCAPED_UNICODE) ?>;
  if(!data.length){ alert('Нет данных'); return; }
  let csv='\uFEFFvendorCode;title;nmID;sku;Нач;Приход;Расход;Перем+;Перем-;Инв;Корр;Кон;Оборот\n';
  data.forEach(r=>{
    const vc=(r.vendorCode||'').replace(/;/g,','), tl=(r.title||'').replace(/;/g,','), nm=r.nmID||'', sku=r.sku||'', s=r.startQty??0, rec=r.receipt??0, exp=r.expense??0, tin=r.t_in??0, tout=r.t_out??0, inv=r.inv??0, adj=r.adj??0, e=r.endQty??0, t=r.turnover??0;
    csv+=`"${vc}";"${tl}";"${nm}";"${sku}";"${s}";"${rec}";"${exp}";"${tin}";"${tout}";"${inv}";"${adj}";"${e}";"${t}"\n`;
  });
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a'); a.href=url; a.download='turnover_'+new Date().toISOString().slice(0,10)+'.csv'; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
});
</script>
</div>
