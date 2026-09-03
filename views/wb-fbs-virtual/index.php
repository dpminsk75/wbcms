<?php

use yii\helpers\Html;
use yii\helpers\Url;
use kartik\grid\GridView;

use kartik\icons\Icon;
Icon::map($this); 

use yii\bootstrap5\BootstrapIconAsset;
BootstrapIconAsset::register($this);

/** @var yii\web\View $this */
/** @var yii\data\ArrayDataProvider $dataProvider */
/** @var app\models\WbFbsWarehouse[] $virtualWarehouses */
/** @var string $q */

$this->title = 'FBS Остатки: центр + виртуальные';
$this->params['breadcrumbs'][] = $this->title;

$virtualCount = count($virtualWarehouses);
$virtualNames = implode(', ', array_map(fn($w)=> $w->name . ' ('.$w->warehouseId.')', $virtualWarehouses));
?>
<div class="wb-fbs-virtual-index">

    <h1 class="mb-4"><?= Html::encode($this->title) ?></h1>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <form method="get" class="d-flex gap-2 flex-column" id="search-form">
                <input type="text" name="q" value="<?= Html::encode($q ?? '') ?>" placeholder="sku / артикул / название / nmID" class="form-control">
                <button type="submit" class="btn btn-primary btn-sm">Фильтр</button>
                <select name="qty" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= ($qtyFilter??'all')==='all'?'selected':'' ?>>Все</option>
                    <option value="not_found" <?= ($qtyFilter??'')==='not_found'?'selected':'' ?>>Не найдено</option>
                    <option value="zero" <?= ($qtyFilter??'')==='zero'?'selected':'' ?>>Ноль</option>
                    <option value="1_9" <?= ($qtyFilter??'')==='1_9'?'selected':'' ?>>1-9</option>
                </select>
                <?php if (!empty($q) || ($qtyFilter??'all')!=='all'): ?><a href="<?= Url::to(['index']) ?>" class="btn btn-link btn-sm">Сброс</a><?php endif; ?>
            </form>
        </div>
        <div class="col-md-2">
            <div class="d-flex gap-2 flex-column h-100">
                <div class="dropdown w-100">
                  <button class="btn btn-success btn-sm dropdown-toggle py-2 px-3 w-100" type="button" id="importDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="d-inline-flex align-items-center gap-2">
                      <span class="me-2">
                        <i class="fas fa-file-excel me-1"></i> Действия с Excel
                      </span>
                      <i class="fas fa-chevron-down small"></i>
                    </div>
                  </button>
                  <ul class="dropdown-menu" aria-labelledby="importDropdownBtn">
                    <li><button class="dropdown-item" type="button" id="import-virtual-btn"><i class="fas fa-boxes me-2 text-warning"></i>Загрузить виртуальные остатки</button></li>
                    <li><button class="dropdown-item" type="button" id="import-central-btn"><i class="fas fa-warehouse me-2 text-primary"></i>Загрузить центральный склад</button></li>
                  </ul>
                </div>
                <a href="<?= Url::to(['deduct-log']) ?>" class="btn btn-outline-secondary btn-sm mt-auto py-2 px-3 w-100"><i class="bi bi-journal-text"></i> Лог работы робота</a>
            </div>
        </div>
        <div class="col-md-3">
            <span class="d-flex gap-2 flex-column justify-content-end">
              <div class="d-flex">
                <select id="wh-filter" class="form-select me-2" style="width:180px" title="Склад для выгрузки">
                    <option value="all" <?= ($whFilter??'all')==='all'?'selected':'' ?>>Все склады</option>
                    <?php foreach($virtualWarehouses as $wh): ?>
                        <option value="<?= $wh->warehouseId ?>" <?= (string)($whFilter??'all')===(string)$wh->warehouseId?'selected':'' ?>><?= Html::encode($wh->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" id="upload-all-btn"><i class="fas fa-cloud-upload-alt"></i> Выгрузить</button>
              </div>
                <label class="form-check-label"><input type="checkbox" id="test-mode" class="form-check-input ms-1" checked> Тест</label>
            </span>
        </div> 
        <div class="col-md-3 text-end">
            <span class="d-flex gap-2 flex-row w-100">
                <button type="button" class="btn btn-outline-primary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#warehousesModal" id="open-warehouses-btn"><i class="fas fa-warehouse"></i> Вирт. склады</button>
                <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="sync-warehouses-btn"><i class="bi bi-arrow-clockwise"></i> Обновить список</button>
            </span>
            <div id="sync-warehouses-result" class="mt-2 small text-start" style="display:none"></div>
            <div class="mt-2 small text-start" style="line-height:1.4">
                <?php if ($virtualCount): ?>
                    <?php foreach ($virtualWarehouses as $wh): ?>
                        <div><i class="fas fa-check text-success me-1" style="font-size:10px"></i><?= Html::encode($wh->name) ?> <span class="text-muted">(<?= Html::encode($wh->warehouseId) ?>)</span><?php if ($wh->consider_orders): ?><span class="badge bg-info ms-1" style="font-size:9px">учёт заказов</span><?php endif; ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-danger">не выбраны</span>
                <?php endif; ?>
            </div>
        </div>
</div>
</div>
<!--
    <p class="text-muted">Колонки Excel: Баркод / Артикул продавца / nmID + Количество. Центр — справочно, виртуал — редактируется. Фильтр количества — по виртуал. Выгрузка — на выбранный склад или на все.</p>
-->
    <div id="draft-info" class="alert alert-warning" style="display:none;margin-top:10px"></div>
    <div id="central-import-result" class="alert alert-info" style="display:none;margin-top:10px"></div>
    <div id="virtual-import-result" class="alert alert-info" style="display:none;margin-top:10px"></div>
    <div id="upload-result" class="alert" style="display:none;margin-top:10px"></div>

<div class="custom-compact-grid">
    <?= GridView::widget([
        'dataProvider'=>$dataProvider,
        'pjax'=>false,
        'bordered'=>true,'striped'=>true,'condensed'=>true,'hover'=>true,
        'panel'=>[
                'type' => GridView::TYPE_PRIMARY,
                'heading'=>'Виртуальные остатки',
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
                'contentOptions'=>['style'=>'min-width:240px'],
                'hiddenFromExport'=>true,
                'value'=>function($m){
                    $art = Html::encode($m['vendorCode'] ?? '—');
                    $nm = Html::encode($m['nmID'] ?? '');
                    $title = Html::encode($m['title'] ?? '');
                    return '<div style="font-weight:bold">'.$art.'</div><div style="font-size:11px;color:#666">'.$nm.' — '.$title.'</div>';
                }
            ],
            [
                'label'=>'Баркод',
                'format'=>'raw',
                'contentOptions'=>['style'=>'font-family:monospace;white-space:nowrap'],
                'value'=>function($m){
                    $sku = Html::encode($m['sku']);
                    return $sku.' <button type="button" class="copy-sku-btn" data-sku="'.$m['sku'].'" title="Копировать" style="background:transparent;border:none;padding:0 4px;color:#8a8a8a;cursor:pointer;line-height:1"><i class="far fa-copy" style="font-size:13px;color:#8a8a8a"></i></button>';
                }
            ],
            [
                'label'=>'Артикул продавца',
                'attribute'=>'vendorCode',
                'hidden'=>true,
                'hiddenFromExport'=>false,
                'value'=>function($m){ return $m['vendorCode'] ?? ''; },
            ],
            [
                'label'=>'Наименование',
                'attribute'=>'title',
                'hidden'=>true,
                'hiddenFromExport'=>false,
                'value'=>function($m){ return $m['title'] ?? ''; },
            ],
            [
                'label'=>'Остаток',
                'format'=>'raw',
                'contentOptions'=>['style'=>'text-align:center;background:#f0f6ff;width:90px'],
                'value'=>function($m){
                    $v = $m['central_qty'] ?? '';
                    return $v === null || $v === '' ? '<span class="text-muted">—</span>' : Html::encode($v);
                }
            ],
            [
                'label'=>'Количество',
                'format'=>'raw',
                'contentOptions'=>['style'=>'text-align:center;background:#fff8e1;width:110px'],
                'hiddenFromExport'=>true,
                'value'=>function($m){
                    $v = $m['virtual_qty'] ?? '';
                    $val = $v === null ? '' : $v;
                    return '<input type="number" class="form-control input-sm virtual-qty" data-sku="'.$m['sku'].'" value="'.$val.'" style="width:80px;display:inline;text-align:center">';
                }
            ],
            [
                'label'=>'Количество',
                'attribute'=>'virtual_qty',
                'hidden'=>true,
                'hiddenFromExport'=>false,
                'hAlign'=>'right',
                'value'=>function($m){ return $m['virtual_qty'] ?? ''; },
            ],
            [
                'label'=>'Действия',
                'format'=>'raw',
                'contentOptions'=>['style'=>'text-align:center;white-space:nowrap;width:110px'],
                'hiddenFromExport'=>true,
                'value'=>function($m){
                    return Html::button('<i class="bi bi-cloud-upload"></i>', ['class'=>'btn btn-xs btn-gray upload-one-btn button-icon-20px', 'data-sku'=>$m['sku'], 'title'=>'Выгрузить на все виртуал.', 'style'=>'margin-right:4px'])
                         . Html::button('<i class="bi bi-trash"></i>', ['class'=>'btn btn-xs btn-gray delete-virtual-btn button-icon-15px', 'data-sku'=>$m['sku'], 'title'=>'Очистить/удалить']);
                }
            ],
        ],
    ]) ?>
</div>


</div>

    <input type="file" id="import-central-file" accept=".xlsx,.xls" style="display:none">
    <input type="file" id="import-virtual-file" accept=".xlsx,.xls" style="display:none">

    <!-- Modal виртуальные склады - закрытие только крестиком -->
<div class="modal fade" id="warehousesModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h4 class="modal-title">Виртуальные склады</h4><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body" id="warehouses-modal-body">Загрузка...</div>
    </div>
  </div>
</div>

<script>
(function(){
  function csrf(){ var m=document.querySelector('meta[name="csrf-token"]'); return m?m.content:(window.yii&&yii.getCsrfToken?yii.getCsrfToken():null); }
  function csrfParam(){ var m=document.querySelector('meta[name="csrf-param"]'); return m?m.content:(window.yii&&yii.getCsrfParam?yii.getCsrfParam():'_csrf'); }
  function post(url, data, cb){
    var body=new URLSearchParams(); for(var k in data) body.append(k,data[k]); var p=csrfParam(), t=csrf(); if(p&&t) body.append(p,t);
    fetch(url,{method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:body}).then(r=>r.json()).then(cb).catch(()=>cb({success:false,error:'сеть'}));
  }
  // черновики виртуал. остатков в localStorage - переживают поиск/пагинацию
  var DRAFT_KEY='wb_fbs_virtual_draft';
  function getDrafts(){ try{ return JSON.parse(localStorage.getItem(DRAFT_KEY)||'{}'); }catch(e){ return {}; } }
  function setDraft(sku,val){
    var d=getDrafts();
    if(val===''||val===null) delete d[sku]; else d[sku]=String(val);
    localStorage.setItem(DRAFT_KEY, JSON.stringify(d));
    updateDraftInfo();
  }
  function updateDraftInfo(){
    var d=getDrafts(); var cnt=Object.keys(d).length;
    var el=document.getElementById('draft-info');
    if(cnt){ el.style.display='block'; el.innerHTML='Несохранённых правок: '+cnt+' <button class="btn btn-xs btn-default" onclick="if(confirm(\'Очистить черновики?\')){localStorage.removeItem(\'wb_fbs_virtual_draft\');location.reload();}">Очистить</button> <small class="text-muted">сохраняются при поиске/пагинации, уйдут после Выгрузить (автосохранение)</small>'; }
    else el.style.display='none';
  }
  function restoreDrafts(){
    var d=getDrafts();
    document.querySelectorAll('.virtual-qty').forEach(function(inp){
      var sku=inp.dataset.sku;
      if(d.hasOwnProperty(sku)){ inp.value=d[sku]; inp.style.background='#fff3cd'; }
    });
    updateDraftInfo();
  }
  // делегированно ловим изменения виртуал. полей
  document.addEventListener('input', function(ev){
    if(ev.target.classList.contains('virtual-qty')){
      setDraft(ev.target.dataset.sku, ev.target.value);
      ev.target.style.background= ev.target.value!=='' ? '#fff3cd' : '#fff8e1';
    }
  });
  // при загрузке страницы восстановить черновики
  document.addEventListener('DOMContentLoaded', restoreDrafts);
  // если Grid уже отрендерен до DOMContentLoaded (pjax false) - сразу
  restoreDrafts();
  // склад для выгрузки - запоминаем
  (function(){
    var WH_KEY='wb_wh_filter';
    var whEl=document.getElementById('wh-filter');
    if(!whEl) return;
    var saved=localStorage.getItem(WH_KEY);
    if(saved && whEl.value==='all' && !new URLSearchParams(location.search).has('wh')) whEl.value=saved;
    whEl.addEventListener('change', function(){ localStorage.setItem(WH_KEY, this.value); });
  })();

  document.getElementById('open-warehouses-btn').addEventListener('click', function(){
    fetch('<?= Url::to(['warehouse-list']) ?>',{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.text()).then(html=>{
      document.getElementById('warehouses-modal-body').innerHTML=html;
    });
  });
  document.getElementById('sync-warehouses-btn').addEventListener('click', function(){
    var btn=this; var el=document.getElementById('sync-warehouses-result');
    btn.disabled=true; var orig=btn.innerHTML; btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Обновление...';
    el.style.display='block'; el.className='mt-2 small alert alert-info'; el.textContent='Синхронизирую с WB...';
    post('<?= Url::to(['/wb-fbs/sync-warehouses']) ?>', {}, function(d){
      btn.disabled=false; btn.innerHTML=orig;
      console.log('[FBS] sync-warehouses', d);
      if(!d.success){ el.className='mt-2 small alert alert-danger'; el.textContent=d.error||'Ошибка'; return; }
      el.className='mt-2 small alert alert-success';
      el.textContent='Готово: '+d.total+' складов'+(d.errors&&d.errors.length?' (ошибок: '+d.errors.length+')':'')+' — перезагружаю...';
      setTimeout(function(){ location.reload(); }, 900);
    });
  });
  // делегированный обработчик для кнопок в ajax-модалке (script внутри innerHTML не выполняется)
  document.getElementById('warehouses-modal-body').addEventListener('click', function(ev){
    console.log('[warehouse] click target', ev.target, 'class', ev.target.className);
    if(!ev.target.classList.contains('virt-toggle-btn')) {
      console.log('[warehouse] ignore - not virt-toggle-btn');
      return;
    }
    var btn=ev.target;
    var id=btn.dataset.id;
    console.log('[warehouse] toggle id='+id+' start');
    btn.disabled=true;
    var body=new URLSearchParams();
    var p=csrfParam(), t=csrf(); if(p&&t) body.append(p,t);
    var url='<?= Url::to(['toggle-virtual']) ?>?id='+id;
    console.log('[warehouse] fetch POST', url, 'body', body.toString());
    fetch(url, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:body})
      .then(r=>{
        console.log('[warehouse] response status', r.status, r.ok);
        return r.text().then(txt=>{ console.log('[warehouse] raw text', txt.slice(0,1000)); try{ return JSON.parse(txt); } catch(e){ console.log('[warehouse] JSON parse error', e); return {success:false, error:txt}; }});
      })
      .then(d=>{
        console.log('[warehouse] parsed', d);
        btn.disabled=false;
        if(!d.success){ console.log('[warehouse] not success', d); return; }
        var tr=btn.closest('tr');
        var cell=tr.querySelector('.virt-cell');
        if(d.is_virtual){
          cell.innerHTML='<span class="badge bg-success">Да</span>';
          btn.textContent='Снять'; btn.className='btn btn-xs virt-toggle-btn btn-warning';
        } else {
          cell.innerHTML='<span class="badge bg-secondary">Нет</span>';
          btn.textContent='Сделать виртуальным'; btn.className='btn btn-xs virt-toggle-btn btn-success';
        }
        console.log('[warehouse] toggled to', d.is_virtual);
      }).catch(e=>{ console.log('[warehouse] fetch error', e); btn.disabled=false;});
  });
  document.getElementById('warehouses-modal-body').addEventListener('click', function(ev){
    if(!ev.target.classList.contains('consider-toggle-btn')) return;
    var btn=ev.target; var id=btn.dataset.id; btn.disabled=true;
    var body=new URLSearchParams(); var p=csrfParam(), t=csrf(); if(p&&t) body.append(p,t);
    var url='<?= Url::to(['toggle-consider']) ?>?id='+id;
    fetch(url, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:body})
      .then(r=>r.text().then(txt=>{ try{ return JSON.parse(txt);} catch(e){ return {success:false,error:txt};}}))
      .then(d=>{
        btn.disabled=false; if(!d.success) return;
        var tr=btn.closest('tr'); var cell=tr.querySelector('.consider-cell');
        if(d.consider_orders){
          cell.innerHTML='<span class="badge bg-info">Да</span>';
          btn.textContent='Не учитывать'; btn.className='w-100 btn btn-xs consider-toggle-btn btn-info text-nowrap';
        } else {
          cell.innerHTML='<span class="badge bg-secondary">Нет</span>';
          btn.textContent='Учитывать'; btn.className='w-100 btn btn-xs consider-toggle-btn btn-secondary text-nowrap';
        }
        console.log('[warehouse] consider toggled', d);
      }).catch(e=>{ console.log('[warehouse] consider error', e); btn.disabled=false;});
  });

  document.getElementById('import-central-btn').addEventListener('click',()=>document.getElementById('import-central-file').click());
  document.getElementById('import-central-file').addEventListener('change', function(){
    var f=this.files[0]; if(!f) return; var fd=new FormData(); fd.append('file',f); var p=csrfParam(),t=csrf(); if(p&&t) fd.append(p,t);
    var el=document.getElementById('central-import-result'); el.style.display='block'; el.innerHTML='Загрузка...';
    fetch('<?= Url::to(['parse-central']) ?>',{method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd}).then(r=>r.json()).then(data=>{
      this.value='';
      if(!data.success){ el.textContent='Ошибка: '+(data.error||''); return; }
      var html='Найдено '+data.matched.length+' совпало, не найдено '+data.skipped.length;
      if(data.skipped && data.skipped.length){
        html+='<br><b>Не найдены:</b><div style="max-height:200px;overflow:auto;background:#fff;padding:6px;margin-top:6px;border:1px solid #ddd;font-size:11px;white-space:pre-wrap">'+data.skipped.join("\n")+'</div>';
      }
      el.innerHTML=html+'<br>Сохраняю...';
      if(data.matched.length){
        var changes=data.matched.map(it=>({sku:it.sku, qty:it.qty}));
        post('<?= Url::to(['save-central']) ?>',{changes:JSON.stringify(changes)}, function(d){
          var msg=d.success? 'Центр сохранён '+d.processed : 'Ошибка '+(d.error||'');
          if(d.errors && d.errors.length) msg+='\n'+d.errors.join("\n");
          el.innerHTML+='<br>'+msg;
          if(d.success) setTimeout(()=>location.reload(), 800);
        });
      }
    });
  });

  document.getElementById('import-virtual-btn').addEventListener('click',()=>document.getElementById('import-virtual-file').click());
  document.getElementById('import-virtual-file').addEventListener('change', function(){
    var f=this.files[0]; if(!f) return; var fd=new FormData(); fd.append('file',f); var p=csrfParam(),t=csrf(); if(p&&t) fd.append(p,t);
    var el=document.getElementById('virtual-import-result'); el.style.display='block'; el.innerHTML='Загрузка...';
    fetch('<?= Url::to(['parse-virtual']) ?>',{method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd}).then(r=>r.json()).then(data=>{
      this.value='';
      if(!data.success){ el.textContent='Ошибка: '+(data.error||''); return; }
      var html='Найдено '+data.matched.length+' совпало, не найдено '+data.skipped.length;
      if(data.skipped && data.skipped.length){
        html+='<br><b>Не найдены:</b><div style="max-height:200px;overflow:auto;background:#fff;padding:6px;margin-top:6px;border:1px solid #ddd;font-size:11px;white-space:pre-wrap">'+data.skipped.join("\n")+'</div>';
      }
      el.innerHTML=html+'<br>Сохраняю...';
      if(data.matched.length){
        var changes=data.matched.map(it=>({sku:it.sku, qty:it.qty}));
        post('<?= Url::to(['save-virtual']) ?>',{changes:JSON.stringify(changes)}, function(d){
          if(!d.success){ el.innerHTML+='<br>Ошибка сохранения'; return; }
          localStorage.removeItem(DRAFT_KEY); window._virtualMatched=null;
          el.innerHTML+='<br>Сохранено '+d.processed+' — перезагружаю...';
          setTimeout(()=>location.reload(), 800);
        });
      }
    });
  });
  function collectVirtualChanges(){
    var changes=[]; var drafts=getDrafts();
    if(window._virtualMatched && window._virtualMatched.length){
      changes=window._virtualMatched.map(it=>({sku:it.sku, qty:it.qty}));
      document.querySelectorAll('.virtual-qty').forEach(inp=>{
        var sku=inp.dataset.sku; var val=inp.value;
        var idx=changes.findIndex(c=>c.sku===sku);
        if(idx>=0) changes[idx].qty=val; else if(val!=='') changes.push({sku:sku, qty:val});
      });
      Object.keys(drafts).forEach(function(sku){ if(!changes.find(c=>c.sku===sku)) changes.push({sku:sku, qty:drafts[sku]}); });
    } else {
      document.querySelectorAll('.virtual-qty').forEach(inp=>{ if(inp.value!=='') changes.push({sku:inp.dataset.sku, qty:inp.value}); });
      Object.keys(drafts).forEach(function(sku){ if(!changes.find(c=>c.sku===sku)) changes.push({sku:sku, qty:drafts[sku]}); });
    }
    return changes;
  }

  document.addEventListener('click', function(ev){
    var btn=ev.target.closest('.upload-one-btn');
    if(!btn) return;
    var sku=btn.dataset.sku;
    var test=document.getElementById('test-mode').checked ? 1 : 0;
    var wh=document.getElementById('wh-filter') ? document.getElementById('wh-filter').value : 'all';
    var inp=document.querySelector('.virtual-qty[data-sku="'+sku+'"]');
    var amount = inp ? inp.value : (getDrafts()[sku] || '');
    if(amount===''||amount===null){ alert('Введите количество для sku '+sku); return; }
    console.log('[FBS] upload-one sku='+sku+' amount='+amount+' wh='+wh+' test='+test);
    var el=document.getElementById('upload-result'); el.style.display='block'; el.className='alert alert-info'; el.textContent='Выгружаю '+sku+' ('+amount+') на '+(wh==='all'?'все':wh)+'...'+(test?' [ТЕСТ]':'');
    btn.disabled=true;
    post('<?= Url::to(['upload-one']) ?>',{sku:sku, amount:amount, test:test, warehouseId:wh}, function(d){
      btn.disabled=false;
      console.log('[FBS] upload-one', d);
      if(!d.success){ el.className='alert alert-danger'; el.textContent=d.error||'Ошибка'; return; }
      el.className='alert alert-success';
      var prefix = d.dry ? '[ТЕСТ] ' : '';
      el.textContent=prefix+JSON.stringify(d.results).slice(0,1200);
    });
  });
  document.addEventListener('click', function(ev){
    var btn=ev.target.closest('.delete-virtual-btn');
    if(!btn) return;
    var sku=btn.dataset.sku;
    if(!confirm('Очистить количество для '+sku+'? Строка будет удалена из виртуал. остатков.')) return;
    var inp=document.querySelector('.virtual-qty[data-sku="'+sku+'"]');
    if(inp){ inp.value=''; inp.style.background='#fff8e1'; }
    var drafts=getDrafts(); delete drafts[sku]; localStorage.setItem(DRAFT_KEY, JSON.stringify(drafts)); updateDraftInfo();
    // удаляем из БД если есть
    post('<?= Url::to(['delete-virtual']) ?>',{sku:sku}, function(d){
      console.log('[FBS] delete', d);
      if(d.success){
        // визуально сбрасываем центральный столбец не трогаем, виртуал уже очищен
        var row=btn.closest('tr');
        if(row) row.style.opacity='0.5';
      }
    });
  });
  document.getElementById('upload-all-btn').addEventListener('click', function(){
    var test=document.getElementById('test-mode').checked ? 1 : 0;
    var wh=document.getElementById('wh-filter') ? document.getElementById('wh-filter').value : 'all';
    var el=document.getElementById('upload-result'); el.style.display='block'; el.className='alert alert-info'; el.textContent='Проверка черновиков...'+(test?' [ТЕСТ]':'')+' wh='+wh;
    this.disabled=true; var self=this;
    var pending=collectVirtualChanges();
    function doUpload(){
      console.log('[FBS] upload-all test='+test+' wh='+wh);
      el.textContent='Выгружаю все на '+(wh==='all'?'все':wh)+'...'+(test?' [ТЕСТ]':'');
      post('<?= Url::to(['upload-all']) ?>',{test:test, warehouseId:wh}, function(d){
        self.disabled=false;
        console.log('[FBS] upload-all', d);
        if(!d.success){ el.className='alert alert-danger'; el.textContent=d.error||'Ошибка'; return; }
        el.className='alert alert-success';
        el.textContent=(d.dry?'[ТЕСТ] ':'')+'Чанков: '+d.results.length+' '+JSON.stringify(d.results).slice(0,1500);
      });
    }
    if(pending.length){
      console.log('[FBS] auto-save '+pending.length+' before upload');
      post('<?= Url::to(['save-virtual']) ?>',{changes:JSON.stringify(pending)}, function(d){
        if(!d.success){ self.disabled=false; el.className='alert alert-danger'; el.textContent='Ошибка автосохранения'; return; }
        localStorage.removeItem(DRAFT_KEY); window._virtualMatched=null;
        el.textContent='Сохранено '+d.processed+', выгружаю...';
        doUpload();
      });
    } else {
      doUpload();
    }
  });
  document.addEventListener('click', function(ev){
    var btn=ev.target.closest('.copy-sku-btn');
    if(!btn) return;
    var sku=btn.dataset.sku;
    var icon=btn.querySelector('i');
    var orig=icon ? icon.className : '';
    function done(){
      if(icon){ icon.className='fas fa-check text-success'; setTimeout(function(){ icon.className=orig; }, 1200); }
    }
    if(navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(sku).then(done);
    } else {
      var t=document.createElement('textarea'); t.value=sku; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t); done();
    }
    console.log('[FBS] copy sku', sku);
  });
  // перед экспортом сохранить черновики, чтобы в Excel попали актуальные количества
  document.addEventListener('click', function(ev){
    var a=ev.target.closest('a');
    if(!a || a.textContent.trim()!=='Сохранить в Excel') return;
    var pending=collectVirtualChanges();
    if(!pending.length) return;
    ev.preventDefault();
    var origText=a.textContent; a.textContent='Сохранение...'; a.style.pointerEvents='none';
    post('<?= Url::to(['save-virtual']) ?>',{changes:JSON.stringify(pending)}, function(d){
      a.textContent=origText; a.style.pointerEvents='';
      if(d.success){ localStorage.removeItem(DRAFT_KEY); updateDraftInfo(); }
      // повторный клик уже без pending
      setTimeout(function(){ a.click(); }, 300);
    });
  });
})();
</script>


<style>
.custom-compact-grid .btn-group .btn {
    height: 25px;
}

#w1-button.dropdown-toggle {
    display: inline-flex !important;
    align-items: center !important;     /* Центрируем иконку строго по вертикали */
    justify-content: center !important;  /* Центрируем иконку по горизонтали */
    height: 100% !important;             /* Заставляем кнопку растянуться на всю высоту контейнера */
    box-sizing: border-box !important;
}

/* Принудительно задаем правильный размер иконке внутри кнопки, чтобы она не ломала размеры */
#w1-button.dropdown-toggle svg {
    display: block !important;
    width: auto !important;
    vertical-align: middle !important;
    margin: 0 !important;                /* Убираем лишние внешние отступы */
}

.button-icon-20px, .button-icon-15px {
    padding: 3px 8px;   
}

.button-icon-15px .svg-inline--fa{
    height: 15px !important;
    width: 15px !important;
}


.button-icon-20px .svg-inline--fa{
    height: 20px !important;
    width: 20px !important;
}

.btn-gray {
    background-color: #c1c1c1;
}
</style>