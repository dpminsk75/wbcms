<?php
use yii\helpers\Html;
use kartik\icons\Icon;
use yii\bootstrap5\BootstrapIconAsset;
use yii\helpers\Url;
use kartik\grid\GridView;

Icon::map($this);
BootstrapIconAsset::register($this);

/** @var yii\data\ArrayDataProvider $dataProvider */
$this->title = 'Карточка товара';
$this->params['breadcrumbs'][] = ['label'=>'Склад','url'=>['/wb-doc/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wb-stock-report-card">
    <h1><?= Html::encode($this->title) ?></h1>
    <p class="text-muted">Журнал проводок. Поиск по части баркода, vendorCode, наименования или nmID (как в Наличии). Экспорт — <i class="fas fa-file-excel"></i> в шапке таблицы.</p>
    <form method="get" class="row g-2 mb-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Поиск (баркод / vendorCode / наименование / nmID)</label>
            <input type="text" name="sku" value="<?= Html::encode($sku ?? '') ?>" class="form-control" placeholder="часть... напр. блок или 20458" required>
        </div>
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
            <input type="date" name="dateFrom" value="<?= Html::encode($dateFrom ?? '') ?>" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">По</label>
            <input type="date" name="dateTo" value="<?= Html::encode($dateTo ?? '') ?>" class="form-control">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Показать</button>
            <a href="<?= Url::to(['card']) ?>" class="btn btn-outline-secondary">Сброс</a></div>
    </form>

    <?php if(!empty($sku)): ?>
    <div class="custom-compact-grid">
    <?= GridView::widget([
        'dataProvider'=>$dataProvider,
        'pjax'=>false,
        'bordered'=>true,'striped'=>true,'condensed'=>true,'hover'=>true,
        'toolbar'=>['{export}'],
        'panel'=>['type'=>GridView::TYPE_PRIMARY,'heading'=>'Журнал — '.Html::encode($sku).' ('.count($dataProvider->allModels).' движ.)','headingOptions'=>['class'=>'card-header text-white bg-wb'],'before'=>false,'after'=>false],
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
                    $title=Html::encode($m['title']??'');
                    return '<div style="font-weight:bold">'.$art.'</div><div style="font-size:11px;color:#666">'.$title.'</div>';
                }
            ],
            ['label'=>'Артикул','attribute'=>'vendorCode','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['vendorCode']??''],
            ['label'=>'Наименование','attribute'=>'title','hidden'=>true,'hiddenFromExport'=>false,'value'=>fn($m)=> $m['title']??''],
            ['label'=>'Баркод','attribute'=>'sku','contentOptions'=>['style'=>'font-family:monospace;white-space:nowrap;width:110px'],'value'=>fn($m)=> Html::encode($m['sku'])],
            ['label'=>'Дата','attribute'=>'created_at','format'=>['datetime','php:d.m.Y H:i:s'],'contentOptions'=>['style'=>'white-space:nowrap;width:140px']],
            ['label'=>'Док','attribute'=>'doc_type','contentOptions'=>['style'=>'width:110px'],'value'=>fn($m)=> $m['doc_type'].($m['doc_id']?' #'.$m['doc_id']:'')],
            ['label'=>'Склад','attribute'=>'warehouseName','value'=>function($m){
                return $m['warehouseName'] ? $m['warehouseName'].' ('.$m['warehouseId'].')' : (string)$m['warehouseId'];
            }],
            ['label'=>'Дельта','attribute'=>'qty_delta','hAlign'=>'right','format'=>'raw','contentOptions'=>function($m){
                $v=(int)$m['qty_delta'];
                return ['style'=>'text-align:center;width:70px;color:'.($v>0?'#0a0':'#c00').';font-weight:bold'];
            },'value'=>function($m){
                $v=(int)$m['qty_delta']; return ($v>0?'+':'').$v;
            }],
            ['label'=>'До','attribute'=>'qty_before','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;width:60px;background:#f0f6ff']],
            ['label'=>'После','attribute'=>'qty_after','hAlign'=>'right','contentOptions'=>['style'=>'text-align:center;width:60px;background:#e8f5e9']],
            ['label'=>'Юзер','attribute'=>'username','value'=>fn($m)=> $m['username']??'—'],
        ],
    ]) ?>
    </div>
<script>
document.getElementById('export-manual-btn')?.addEventListener('click', function(){
  const grid=document.getElementById('card-grid');
  const kBtn=grid?.querySelector('a.export-excel, .export-excel');
  if(kBtn){ kBtn.click(); return; }
  const drop=grid?.querySelector('.dropdown-menu a');
  if(drop){ drop.click(); return; }
  // fallback — CSV из данных
  const data=<?= json_encode($dataProvider->allModels, JSON_UNESCAPED_UNICODE) ?>;
  if(!data.length){ alert('Нет данных'); return; }
  let csv='\uFEFFvendorCode;title;nmID;sku;Дата;Док;Склад;Дельта;До;После;Юзер\n';
  data.forEach(r=>{
    const vc=(r.vendorCode||'').replace(/;/g,','), tl=(r.title||'').replace(/;/g,','), nm=r.nmID||r.s_nmID||'', sku=r.sku||'', dt=r.created_at||'', doc=(r.doc_type||'')+(r.doc_id?' #'+r.doc_id:''), wh=(r.warehouseName?r.warehouseName+' ('+r.warehouseId+')':r.warehouseId), d=r.qty_delta??'', b=r.qty_before??'', a=r.qty_after??'', u=r.username||'';
    csv+=`"${vc}";"${tl}";"${nm}";"${sku}";"${dt}";"${doc}";"${wh}";"${d}";"${b}";"${a}";"${u}"\n`;
  });
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a'); a.href=url; a.download='card_'+new Date().toISOString().slice(0,10)+'.csv'; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
});
</script>
    <?php else: ?>
        <div class="alert alert-info">Введите часть баркода, vendorCode, наименования или nmID и нажмите Показать</div>
    <?php endif; ?>
</div>
