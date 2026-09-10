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
Icon::map($this);
BootstrapIconAsset::register($this);

/** @var string $warehouseId */
Icon::map($this);
BootstrapIconAsset::register($this);

/** @var string|null $date */
Icon::map($this);
BootstrapIconAsset::register($this);

/** @var string|null $q */
$this->title = 'Наличие';
$this->params['breadcrumbs'][] = ['label'=>'Склад','url'=>['/wb-doc/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wb-stock-report-balance">
    <h1><?= Html::encode($this->title) ?></h1>
    <p class="text-muted">Остатки на дату по складам. Поиск по части vendorCode/названия/баркода/nmID. Дата пустая = сейчас. Экспорт — <i class="fas fa-file-excel"></i> в шапке таблицы.</p>
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
            <label class="form-label">Дата</label>
            <input type="datetime-local" name="date" value="<?= $date?Html::encode(date('Y-m-d\TH:i', strtotime($date))):'' ?>" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Поиск (баркод/vendorCode/наименование/nmID)</label>
            <input type="text" name="q" value="<?= Html::encode($q ?? '') ?>" class="form-control" placeholder="часть...">
        </div>
        <div class="col-md-2 d-flex align-items-center pt-4">
            <label class="form-check mb-0">
                <input type="checkbox" name="onlyAvailable" value="1" <?= !empty($onlyAvailable)?'checked':'' ?> class="form-check-input">
                <span class="form-check-label">Только с наличием</span>
            </label>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Показать</button>
            <a href="<?= Url::to(['balance']) ?>" class="btn btn-outline-secondary">Сброс</a></div>
    </form>

    <div class="custom-compact-grid">
    <?= GridView::widget([
        'dataProvider'=>$dataProvider,
        'pjax'=>false,
        'bordered'=>true,'striped'=>true,'condensed'=>true,'hover'=>true,
        'toolbar'=>['{export}'],
        'panel'=>[
                'type' => GridView::TYPE_PRIMARY,
                'heading'=>'Наличие — '.count($dataProvider->allModels).' поз.',
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
                'contentOptions'=>['style'=>'min-width:260px'],
                'value'=>function($m){
                    $art=Html::encode($m['vendorCode']??'—');
                    $nm=Html::encode($m['nmID']??'');
                    $title=Html::encode($m['title']??'');
                    return '<div style="font-weight:bold">'.$art.'</div><div style="font-size:11px;color:#666">'.$nm.' — '.$title.'</div>';
                }
            ],
            ['label'=>'Артикул','attribute'=>'vendorCode','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['vendorCode']??''],
            ['label'=>'Наименование','attribute'=>'title','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['title']??''],
            ['label'=>'nmID','attribute'=>'nmID','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['nmID']??''],
            [
                'label'=>'Баркод',
                'attribute'=>'sku',
                'format'=>'raw',
                'contentOptions'=>['style'=>'font-family:monospace;white-space:nowrap;width:130px'],
                'value'=>fn($m)=> Html::encode($m['sku']),
            ],
            ['label'=>'chrtID','attribute'=>'chrtID','hidden'=>true,'hiddenFromExport'=>false],
            [
                'label'=>'Склад',
                'hiddenFromExport'=>false,
                'value'=>function($m){
                    if(isset($m['warehouseName']) && $m['warehouseName']) return $m['warehouseName'].($m['wid']?' ('.$m['wid'].')':'');
                    if(isset($m['wid']) && $m['wid']) return (string)$m['wid'];
                    return '—';
                }
            ],
            [
                'label'=>'Остаток',
                'attribute'=>'quantity',
                'hAlign'=>'right',
                'contentOptions'=>['style'=>'text-align:center;background:#e8f5e9;width:90px;font-weight:bold'],
                'value'=>fn($m)=> (int)($m['quantity']??0),
            ],
        ],
    ]) ?>
    </div>
<script>
document.getElementById('export-manual-btn')?.addEventListener('click', function(){
  const grid=document.getElementById('balance-grid');
  const kBtn=grid?.querySelector('a.export-excel, .export-excel');
  if(kBtn){ kBtn.click(); return; }
  const drop=grid?.querySelector('.dropdown-menu a');
  if(drop){ drop.click(); return; }
  const data=<?= json_encode($dataProvider->allModels, JSON_UNESCAPED_UNICODE) ?>;
  if(!data.length){ alert('Нет данных'); return; }
  let csv='\uFEFFvendorCode;title;nmID;sku;chrtID;Склад;Остаток\n';
  data.forEach(r=>{
    const vc=(r.vendorCode||'').replace(/;/g,','), tl=(r.title||'').replace(/;/g,','), nm=r.nmID||'', sku=r.sku||'', ch=r.chrtID||'', wh=(r.warehouseName?r.warehouseName+' ('+r.wid+')':r.wid||''), q=r.quantity??0;
    csv+=`"${vc}";"${tl}";"${nm}";"${sku}";"${ch}";"${wh}";"${q}"\n`;
  });
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a'); a.href=url; a.download='balance_'+new Date().toISOString().slice(0,10)+'.csv'; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
});
</script>
</div>
