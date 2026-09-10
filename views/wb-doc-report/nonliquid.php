<?php
use yii\helpers\Html;
use kartik\icons\Icon;
use yii\bootstrap5\BootstrapIconAsset;
use yii\helpers\Url;
use kartik\grid\GridView;

Icon::map($this);
BootstrapIconAsset::register($this);

/** @var yii\data\ArrayDataProvider $dataProvider */
$this->title = 'Неликвиды';
$this->params['breadcrumbs'][] = ['label'=>'Склад','url'=>['/wb-doc/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wb-stock-report-nonliquid">
    <h1><?= Html::encode($this->title) ?></h1>
    <p class="text-muted">Товары с остатком >0 без движения N дней. Сортировка — давно без движения сверху.</p>
    <form method="get" class="row g-2 mb-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Склад</label>
            <select name="warehouseId" class="form-select">
                <option value="all" <?= $warehouseId==='all'?'selected':'' ?>>Все склады</option>
                <?php foreach($warehouses as $w): ?>
                    <option value="<?= $w->id ?>" <?= (string)$warehouseId===(string)$w->id?'selected':'' ?>><?= Html::encode($w->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Дней без движения</label>
            <input type="number" name="days" value="<?= Html::encode($days) ?>" class="form-control" min="1" max="365">
        </div>
        <div class="col-md-4">
            <label class="form-label">Поиск (баркод/vendorCode/наименование/nmID)</label>
            <input type="text" name="q" value="<?= Html::encode($q ?? '') ?>" class="form-control" placeholder="часть...">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Показать</button>
            <a href="<?= Url::to(['nonliquid']) ?>" class="btn btn-outline-secondary">Сброс</a></div>
    </form>

    <div class="custom-compact-grid">
    <?= GridView::widget([
        'dataProvider'=>$dataProvider,
        'pjax'=>false,
        'bordered'=>true,'striped'=>true,'condensed'=>true,'hover'=>true,
        'toolbar'=>['{export}'],
        'panel'=>['type'=>GridView::TYPE_PRIMARY,'heading'=>'Неликвиды — '.count($dataProvider->allModels).' поз. (>'.$days.' дн.)','headingOptions'=>['class'=>'card-header text-white bg-wb'],'before'=>false,'after'=>false],
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
                'contentOptions'=>['style'=>'min-width:240px'],
                'value'=>function($m){
                    $art=Html::encode($m['vendorCode']??'—');
                    $nm=Html::encode($m['nmID']??'');
                    $title=Html::encode($m['title']??'');
                    return '<div style="font-weight:bold">'.$art.'</div><div style="font-size:11px;color:#666">'.$nm.' — '.$title.'</div>';
                }
            ],
            ['label'=>'Артикул','attribute'=>'vendorCode','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['vendorCode']??''],
            ['label'=>'Баркод','attribute'=>'sku','contentOptions'=>['style'=>'font-family:monospace;width:120px'],'value'=>fn($m)=> $m['sku']],
            ['label'=>'Склад','value'=>function($m){
                if(!empty($m['warehouseName'])) return $m['warehouseName'].($m['wid']?' ('.$m['wid'].')':'');
                return $m['wid'] ? (string)$m['wid'] : '—';
            }],
            ['label'=>'Остаток','attribute'=>'quantity','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;background:#e8f5e9;width:80px;font-weight:bold'],'value'=>fn($m)=> (int)($m['quantity']??0)],
            ['label'=>'Последнее движение','attribute'=>'last_move','format'=>['datetime','php:d.m.Y H:i'],'contentOptions'=>['style'=>'white-space:nowrap;width:140px'],'value'=>fn($m)=> $m['last_move']??'никогда'],
        ],
    ]) ?>
    </div>
<script>
document.getElementById('export-manual-btn')?.addEventListener('click', function(){
  const grid=document.getElementById('nonliquid-grid');
  const kBtn=grid?.querySelector('a.export-excel');
  if(kBtn){ kBtn.click(); return; }
  const drop=grid?.querySelector('.dropdown-menu a');
  if(drop){ drop.click(); return; }
  const data=<?= json_encode($dataProvider->allModels, JSON_UNESCAPED_UNICODE) ?>;
  if(!data.length){ alert('Нет данных'); return; }
  let csv='\uFEFFvendorCode;title;nmID;sku;Склад;Остаток;Последнее движение\n';
  data.forEach(r=>{
    const vc=(r.vendorCode||'').replace(/;/g,','), tl=(r.title||'').replace(/;/g,','), nm=r.nmID||'', sku=r.sku||'', wh=(r.warehouseName?r.warehouseName+' ('+r.wid+')':r.wid||''), q=r.quantity??0, lm=r.last_move||'';
    csv+=`"${vc}";"${tl}";"${nm}";"${sku}";"${wh}";"${q}";"${lm}"\n`;
  });
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a'); a.href=url; a.download='nonliquid_'+new Date().toISOString().slice(0,10)+'.csv'; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
});
</script>
</div>
