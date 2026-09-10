<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\Url;

/** @var app\models\WbDoc $model */
/** @var app\models\OurWarehouse[] $warehouses */
$isTransfer = $model->type === \app\models\WbDoc::TYPE_TRANSFER;
$isInventory = $model->type === \app\models\WbDoc::TYPE_INVENTORY;
$isAdjustment = $model->type === \app\models\WbDoc::TYPE_ADJUSTMENT;
if ($isTransfer) $this->title = 'Перемещение — редактирование #'.$model->id;
elseif ($isInventory) $this->title = 'Инвентаризация — редактирование #'.$model->id;
elseif ($isAdjustment) $this->title = 'Корректировка — редактирование #'.$model->id;
elseif ($model->type==='RECEIPT') $this->title = 'Приход — редактирование #'.$model->id;
else $this->title = 'Расход — редактирование #'.$model->id;
$this->params['breadcrumbs'][] = ['label'=>'Документы','url'=>['index']];
$this->params['breadcrumbs'][] = ['label'=>'#'.$model->id,'url'=>['view','id'=>$model->id]];
$this->params['breadcrumbs'][] = 'Редактирование';
$whList = [];
foreach ($warehouses as $w) {
    $label = $w->name;
    if ($w->is_central) $label .= ' — центральный';
    if ($w->is_fbs) $label .= ' — для FBS';
    $whList[$w->id] = $label;
}
$dateVal = $model->date ? date('Y-m-d\TH:i', strtotime($model->date)) : date('Y-m-d\TH:i');
// подготовить начальные строки для JS
$initial = [];
foreach ($model->items as $it) {
    $vendorCode=''; $title=''; $nmID=$it->nmID;
    if ($it->sku) {
        $sz = \app\models\WbCardSize::findOne(['sku'=>$it->sku]);
        if ($sz) {
            $nmID = $sz->nmID;
            $card = \app\models\WbCard::findOne(['nmID'=>$sz->nmID]);
            if ($card){ $vendorCode=$card->vendorCode; $title=$card->title; }
        }
    }
    $initial[] = [
        'sku'=>$it->sku,
        'vendorCode'=>$vendorCode,
        'title'=>$title,
        'nmID'=>$nmID,
        'qty'=>(int)$it->qty,
        'qty_before'=> ($it->hasAttribute('qty_before') && $it->qty_before !== null) ? (int)$it->qty_before : null,
        'qty_fact'=> ($it->hasAttribute('qty_fact') && $it->qty_fact !== null) ? (int)$it->qty_fact : (int)$it->qty,
    ];
}
?>
<div class="wb-doc-create">
    <h1><?= Html::encode($this->title) ?></h1>
    <div class="alert alert-warning py-2" style="font-size:13px">Редактировать можно только черновик. После сохранения состав будет перезаписан.</div>
    <?php $form = ActiveForm::begin(['id'=>'doc-form']); ?>
    <?= Html::activeHiddenInput($model,'type',['id'=>'wbdoc-type']) ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="row g-2 mb-2">
                <div class="col-md-6"><?= $form->field($model,'date')->input('datetime-local', ['id'=>'doc-date','value'=>$dateVal])->label('Дата и время') ?></div>
                <?php if ($isTransfer): ?>
                    <div class="col-md-3"><?= $form->field($model,'warehouseId')->dropDownList($whList, ['id'=>'doc-warehouseId','prompt'=>'— откуда —'])->label('С какого') ?></div>
                    <div class="col-md-3"><?= $form->field($model,'to_warehouseId')->dropDownList($whList, ['id'=>'doc-to-warehouseId','prompt'=>'— куда —'])->label('На какой') ?></div>
                <?php else: ?>
                    <div class="col-md-6"><?= $form->field($model,'warehouseId')->dropDownList($whList, ['id'=>'doc-warehouseId'])->label('Склад') ?></div>
                <?php endif; ?>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-12"><?= $form->field($model,'comment')->textInput(['placeholder'=> $isAdjustment ? 'причина: нашли/порча/пересортица *' : 'комментарий'])->label($isAdjustment ? 'Причина *' : 'Комментарий') ?></div>
            </div>
            <div class="row">
                <div class="col-12"><button type="button" class="btn btn-primary btn-sm" id="add-row-btn"><i class="bi bi-plus-lg"></i> Добавить строку</button></div>
            </div>
        </div>
        <div class="col-md-6 d-flex flex-column gap-2 justify-content-start">
            <div class="dropdown w-100">
                <button class="btn btn-success btn-sm dropdown-toggle py-2 px-3 w-100" type="button" id="importDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="d-inline-flex align-items-center gap-2">
                        <span class="me-2"><i class="fas fa-file-excel me-1"></i> Действия с Excel</span>
                        <i class="fas fa-chevron-down small"></i>
                    </div>
                </button>
                <ul class="dropdown-menu w-100" aria-labelledby="importDropdownBtn">
                    <li><button class="dropdown-item" type="button" id="import-doc-btn"><i class="fas fa-file-upload me-2"></i>Загрузить из Excel</button></li>
                    <li><button class="dropdown-item" type="button" id="export-spec-btn"><i class="fas fa-file-download me-2 text-success"></i>Сохранить в Excel</button></li>
                    <?php if($isInventory): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item" type="button" id="fill-inventory-btn"><i class="fas fa-database me-2"></i>Заполнить остатками (все)</button></li>
                    <li><button class="dropdown-item" type="button" id="fill-by-vendor-btn"><i class="fas fa-list-check me-2"></i>Заполнить строки (по vendorCode)</button></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php if($isInventory): ?>
            <button type="button" class="btn btn-outline-primary btn-sm w-100" id="fill-by-vendor-btn2"><i class="fas fa-list-check me-1"></i> Заполнить строки по vendorCode</button>
            <?php endif; ?>
            <div id="doc-totals" class="alert alert-light border py-2 small mb-0">
                Строк: <b id="total-rows">0</b> &nbsp;|&nbsp; Общее количество: <b id="total-qty">0</b>
                <span id="total-delta-wrap" style="display:none"> &nbsp;|&nbsp; Разница: <b id="total-delta">0</b></span>
            </div>
        </div>
    </div>
    <input type="file" id="import-doc-file" accept=".xlsx,.xls" style="display:none">
    <?php if ($isTransfer): ?>
        <div class="alert alert-info py-2" style="font-size:13px">Перемещение — проверка остатка на складе-отправителе.</div>
    <?php elseif($isInventory): ?>
        <div class="alert alert-info py-2" style="font-size:13px">Инвентаризация: <b>По учету</b> — snapshot на момент создания, <b>Факт</b> — редактируйте, <b>Разница</b> рассчитается.</div>
    <?php elseif($isAdjustment): ?>
        <div class="alert alert-info py-2" style="font-size:13px">Корректировка: <b>+</b> — оприходовать излишек, <b>-</b> — списать. Причина обязательна.</div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-condensed" id="doc-spec-table">
            <thead>
                <tr>
                    <th style="min-width:260px">Товар</th>
                    <th style="width:130px">Баркод</th>
                    <?php if($isInventory): ?>
                        <th style="width:90px;text-align:center;background:#f0f6ff">По учету</th>
                        <th style="width:110px;text-align:center;background:#fff8e1">Факт</th>
                        <th style="width:90px;text-align:center;background:#e8f5e9">Разница</th>
                    <?php elseif($isAdjustment): ?>
                        <th style="width:90px;text-align:center;background:#f0f6ff">Остаток</th>
                        <th style="width:110px;text-align:center;background:#fff3cd">Корректировка (+/-)</th>
                        <th style="width:90px;text-align:center;background:#e8f5e9">Станет</th>
                    <?php else: ?>
                        <th style="width:90px;text-align:center;background:#f0f6ff"><?= $isTransfer?'Остаток откуда':'Остаток' ?></th>
                        <th style="width:110px;text-align:center;background:#fff8e1">Количество</th>
                    <?php endif; ?>
                    <th style="width:70px;text-align:center">Действия</th>
                </tr>
            </thead>
            <tbody id="doc-spec-body">
            </tbody>
        </table>
    </div>
    <div id="doc-import-result" class="alert alert-info" style="display:none"></div>
    <div id="doc-transfer-error" class="alert alert-danger" style="display:none"></div>
    <?= Html::submitButton('Сохранить', ['class'=>'btn btn-primary', 'id'=>'save-doc-btn']) ?>
    <?= Html::a('Отмена', ['view','id'=>$model->id], ['class'=>'btn btn-default']) ?>
    <?php ActiveForm::end(); ?>
</div>

<div class="modal fade" id="productPickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Выберите товар</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="text" id="product-search-input" class="form-control" placeholder="sku / артикул / название">
        <div id="product-search-results" style="max-height:300px;overflow:auto;margin-top:10px"></div>
    </div>
  </div></div>
</div>
<div class="modal fade" id="bulkVendorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Заполнить строки — поиск по vendorCode</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="input-group mb-3">
            <input type="text" id="bulk-vendor-input" class="form-control" placeholder="часть vendorCode, напр. ABC или 123">
            <button class="btn btn-primary" type="button" id="bulk-vendor-search"><i class="fas fa-search me-1"></i> Найти</button>
        </div>
        <div class="mb-2 d-flex gap-2">
            <label class="small"><input type="checkbox" id="bulk-check-all"> Выбрать все</label>
            <span id="bulk-found-count" class="text-muted small ms-auto"></span>
        </div>
        <div id="bulk-vendor-results" style="max-height:400px;overflow:auto;border:1px solid #ddd"></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-primary" id="bulk-vendor-confirm" disabled>Добавить выбранные</button>
    </div>
  </div></div>
</div>

<script>
(function(){
  let rowIdx=0;
  let pickerTargetRow=null;
  const isTransfer = <?= $isTransfer ? 'true':'false' ?>;
  const isInventory = <?= $isInventory ? 'true':'false' ?>;
  const isAdjustment = <?= $isAdjustment ? 'true':'false' ?>;
  const initialItems = <?= json_encode($initial, JSON_UNESCAPED_UNICODE) ?>;
  function csrf(){ let m=document.querySelector('meta[name="csrf-token"]'); return m?m.content:null; }
  function csrfParam(){ let m=document.querySelector('meta[name="csrf-param"]'); return m?m.content:'_csrf'; }
  function getStockWarehouseId(){ return document.getElementById('doc-warehouseId').value; }
  function HtmlEncode(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  function addRow(data){
    if(isInventory){ addInvRow(data); return; }
    if(isAdjustment){ addAdjRow(data); return; }
    data=data||{sku:'',vendorCode:'',title:'',nmID:'',qty:''};
    const whId=getStockWarehouseId();
    const tr=document.createElement('tr');
    tr.dataset.idx=rowIdx;
    tr.innerHTML=`
      <td>
        <div style="font-weight:bold">${data.vendorCode?HtmlEncode(data.vendorCode):'—'}</div>
        <div style="font-size:11px;color:#666">${data.nmID?data.nmID+' — ':''}${data.title?HtmlEncode(data.title):''}</div>
        <input type="hidden" name="items[${rowIdx}][sku]" value="${HtmlEncode(data.sku)}" class="row-sku">
        <a href="#" class="small pick-product-link">выбрать/изменить товар</a>
      </td>
      <td style="font-family:monospace;white-space:nowrap">${HtmlEncode(data.sku||'—')}</td>
      <td style="text-align:center;background:#f0f6ff" class="row-stock">—</td>
      <td style="text-align:center;background:#fff8e1"><input type="number" name="items[${rowIdx}][qty]" value="${HtmlEncode(data.qty)}" class="form-control input-sm row-qty" style="width:80px;display:inline;text-align:center"></td>
      <td style="text-align:center"><button type="button" class="btn btn-xs btn-gray delete-row-btn"><i class="bi bi-trash"></i></button></td>`;
    document.getElementById('doc-spec-body').appendChild(tr);
    if(data.sku) fetchStock(tr, data.sku, whId);
    rowIdx++;
  }
  function addInvRow(data){
    data=data||{sku:'',vendorCode:'',title:'',nmID:'',qty_before:0,qty_fact:''};
    const tr=document.createElement('tr');
    tr.dataset.idx=rowIdx;
    const before = (data.qty_before!==undefined && data.qty_before!==null && data.qty_before!=='') ? parseInt(data.qty_before):0;
    const fact = (data.qty_fact!==undefined && data.qty_fact!=='') ? data.qty_fact : (data.qty!==undefined?data.qty:'');
    tr.innerHTML=`
      <td>
        <div style="font-weight:bold">${data.vendorCode?HtmlEncode(data.vendorCode):'—'}</div>
        <div style="font-size:11px;color:#666">${data.nmID?data.nmID+' — ':''}${data.title?HtmlEncode(data.title):''}</div>
        <input type="hidden" name="items[${rowIdx}][sku]" value="${HtmlEncode(data.sku)}" class="row-sku">
        <input type="hidden" name="items[${rowIdx}][qty_before]" value="${before}" class="row-before-input">
        <a href="#" class="small pick-product-link">выбрать/изменить товар</a>
      </td>
      <td style="font-family:monospace;white-space:nowrap">${HtmlEncode(data.sku||'—')}</td>
      <td style="text-align:center;background:#f0f6ff" class="row-stock">${before}</td>
      <td style="text-align:center;background:#fff8e1"><input type="number" name="items[${rowIdx}][qty_fact]" value="${HtmlEncode(fact)}" class="form-control input-sm row-fact" style="width:80px;display:inline;text-align:center"></td>
      <td style="text-align:center;font-weight:bold" class="row-delta">—</td>
      <td style="text-align:center"><button type="button" class="btn btn-xs btn-gray delete-row-btn"><i class="bi bi-trash"></i></button></td>`;
    document.getElementById('doc-spec-body').appendChild(tr);
    updateDelta(tr);
    tr.querySelector('.row-fact').addEventListener('input', ()=>updateDelta(tr));
    rowIdx++;
  }
  function updateDelta(tr){
    const before = parseInt(tr.querySelector('.row-before-input')?.value || tr.querySelector('.row-stock')?.textContent || 0) || 0;
    const factRaw = tr.querySelector('.row-fact')?.value;
    const el = tr.querySelector('.row-delta');
    if(factRaw==='' || factRaw===null){ el.textContent='—'; el.style.color=''; el.style.background=''; return; }
    const fact = parseInt(factRaw)||0;
    const delta = fact - before;
    el.textContent = (delta>0?'+':'')+delta;
    el.style.color = delta===0?'#666':(delta>0?'#0a0':'#c00');
    el.style.background = delta===0?'':(delta>0?'#e8f5e9':'#ffebee');
  }
  function addAdjRow(data){
    data=data||{sku:'',vendorCode:'',title:'',nmID:'',qty:''};
    const whId=getStockWarehouseId();
    const tr=document.createElement('tr');
    tr.dataset.idx=rowIdx;
    const curQty = data.stock!==undefined ? data.stock : '—';
    tr.innerHTML=`
      <td>
        <div style="font-weight:bold">${data.vendorCode?HtmlEncode(data.vendorCode):'—'}</div>
        <div style="font-size:11px;color:#666">${data.nmID?data.nmID+' — ':''}${data.title?HtmlEncode(data.title):''}</div>
        <input type="hidden" name="items[${rowIdx}][sku]" value="${HtmlEncode(data.sku)}" class="row-sku">
        <a href="#" class="small pick-product-link">выбрать/изменить товар</a>
      </td>
      <td style="font-family:monospace;white-space:nowrap">${HtmlEncode(data.sku||'—')}</td>
      <td style="text-align:center;background:#f0f6ff" class="row-stock">${HtmlEncode(curQty)}</td>
      <td style="text-align:center;background:#fff3cd"><input type="number" name="items[${rowIdx}][qty]" value="${HtmlEncode(data.qty)}" class="form-control input-sm row-qty" style="width:80px;display:inline;text-align:center" placeholder="+/-"></td>
      <td style="text-align:center;background:#e8f5e9;font-weight:bold" class="row-new">—</td>
      <td style="text-align:center"><button type="button" class="btn btn-xs btn-gray delete-row-btn"><i class="bi bi-trash"></i></button></td>`;
    document.getElementById('doc-spec-body').appendChild(tr);
    if(data.sku) fetchStockAdj(tr, data.sku, whId);
    tr.querySelector('.row-qty').addEventListener('input', ()=>updateAdj(tr));
    rowIdx++;
  }
  function updateAdj(tr){
    const stockTxt = tr.querySelector('.row-stock').textContent;
    const stock = parseInt(stockTxt)||0;
    const deltaRaw = tr.querySelector('.row-qty').value;
    const el = tr.querySelector('.row-new');
    if(stockTxt==='—' || stockTxt===''){ el.textContent='—'; return; }
    if(deltaRaw==='' || deltaRaw===null){ el.textContent=stock; el.style.color='#666'; return; }
    const delta = parseInt(deltaRaw)||0;
    const newVal = stock + delta;
    el.textContent = newVal;
    el.style.color = delta===0?'#666':(delta>0?'#0a0':'#c00');
    if(newVal<0){ el.style.background='#ffebee'; el.style.color='#c00'; } else { el.style.background=''; }
  }
  function fetchStockAdj(tr, sku, whId){
    if(!sku||!whId) return;
    fetch('<?= Url::to(['get-balance']) ?>?sku='+encodeURIComponent(sku)+'&warehouseId='+whId).then(r=>r.json()).then(d=>{
      const q = d.qty ?? 0;
      tr.querySelector('.row-stock').textContent = q;
      updateAdj(tr);
    });
  }
  function updateTotals(){
    const rows=document.querySelectorAll('#doc-spec-body tr');
    const elRows=document.getElementById('total-rows');
    const elQty=document.getElementById('total-qty');
    if(elRows) elRows.textContent=rows.length;
    let sum=0;
    if(isInventory){
      rows.forEach(tr=>{ const v=parseInt(tr.querySelector('.row-fact')?.value||0); if(!isNaN(v)) sum+=v; });
      if(elQty) elQty.textContent=sum;
      let delta=0;
      rows.forEach(tr=>{ const txt=(tr.querySelector('.row-delta')?.textContent||'').replace('+','').trim(); const d=parseInt(txt)||0; if(tr.querySelector('.row-delta')?.textContent?.trim()!=='—' && tr.querySelector('.row-delta')?.textContent?.trim()!=='') delta+=d; });
      const wrap=document.getElementById('total-delta-wrap');
      const elDelta=document.getElementById('total-delta');
      if(wrap && elDelta){ wrap.style.display='inline'; elDelta.textContent=(delta>0?'+':'')+delta; elDelta.style.color=delta===0?'#666':(delta>0?'#0a0':'#c00'); }
    } else if(isAdjustment){
      rows.forEach(tr=>{ const v=parseInt(tr.querySelector('.row-qty')?.value||0); if(!isNaN(v) && tr.querySelector('.row-qty')?.value!=='') sum+=v; });
      if(elQty) { elQty.textContent=(sum>0?'+':'')+sum; elQty.style.color=sum===0?'#666':(sum>0?'#0a0':'#c00'); }
    } else {
      rows.forEach(tr=>{ const v=parseInt(tr.querySelector('.row-qty')?.value||0); if(!isNaN(v) && tr.querySelector('.row-qty')?.value!=='') sum+=v; });
      if(elQty) elQty.textContent=sum;
    }
  }
  function fetchStock(tr, sku, whId){
    if(!sku||!whId) return;
    fetch('<?= Url::to(['get-balance']) ?>?sku='+encodeURIComponent(sku)+'&warehouseId='+whId).then(r=>r.json()).then(d=>{
      tr.querySelector('.row-stock').textContent = d.qty ?? '—';
    });
  }
  document.getElementById('add-row-btn').addEventListener('click', function(){ pickerTargetRow=null; openPicker(null); });
  function openPicker(targetRow){
    pickerTargetRow=targetRow;
    const modal=new bootstrap.Modal(document.getElementById('productPickerModal'));
    document.getElementById('product-search-results').innerHTML='';
    document.getElementById('product-search-input').value='';
    modal.show();
    setTimeout(()=>document.getElementById('product-search-input').focus(),300);
  }
  document.getElementById('doc-spec-body').addEventListener('click', function(ev){
    if(ev.target.closest('.pick-product-link')){ ev.preventDefault(); openPicker(ev.target.closest('tr')); }
    if(ev.target.closest('.delete-row-btn')){ ev.target.closest('tr').remove(); }
  });
  let searchTimer=null;
  document.getElementById('product-search-input').addEventListener('input', function(){
    clearTimeout(searchTimer);
    let term=this.value.trim();
    if(term.length<2){ document.getElementById('product-search-results').innerHTML=''; return; }
    searchTimer=setTimeout(()=>{
      fetch('<?= Url::to(['search-product']) ?>?term='+encodeURIComponent(term)).then(r=>r.json()).then(list=>{
        let html='';
        list.forEach(it=>{
          html+=`<div class="p-2 border-bottom product-pick" data-sku="${HtmlEncode(it.sku)}" data-vendor="${HtmlEncode(it.vendorCode)}" data-title="${HtmlEncode(it.title)}" data-nmid="${it.nmID}" style="cursor:pointer">
            <b>${HtmlEncode(it.vendorCode)}</b> — ${HtmlEncode(it.title)} <small class="text-muted">${HtmlEncode(it.sku)}</small>
          </div>`;
        });
        document.getElementById('product-search-results').innerHTML= html||'<div class="text-muted">не найдено</div>';
      });
    }, 300);
  });
  document.getElementById('product-search-results').addEventListener('click', function(ev){
    let row=ev.target.closest('.product-pick'); if(!row) return;
    let data={sku:row.dataset.sku, vendorCode:row.dataset.vendor, title:row.dataset.title, nmID:row.dataset.nmid};
    if(isInventory){
        data.qty_before=''; data.qty_fact='';
        if(pickerTargetRow){
          pickerTargetRow.querySelector('.row-sku').value=data.sku;
          pickerTargetRow.cells[0].querySelector('div').innerHTML='<div style="font-weight:bold">'+HtmlEncode(data.vendorCode)+'</div><div style="font-size:11px;color:#666">'+HtmlEncode(data.nmID)+' — '+HtmlEncode(data.title)+'</div>';
          pickerTargetRow.cells[1].textContent=data.sku;
          fetch('<?= Url::to(['get-balance']) ?>?sku='+encodeURIComponent(data.sku)+'&warehouseId='+getStockWarehouseId()).then(r=>r.json()).then(d=>{
            const q=d.qty??0;
            pickerTargetRow.querySelector('.row-stock').textContent=q;
            pickerTargetRow.querySelector('.row-before-input').value=q;
            updateDelta(pickerTargetRow);
          });
        } else {
          fetch('<?= Url::to(['get-balance']) ?>?sku='+encodeURIComponent(data.sku)+'&warehouseId='+getStockWarehouseId()).then(r=>r.json()).then(d=>{
            data.qty_before=d.qty??0; addInvRow(data);
          });
          bootstrap.Modal.getInstance(document.getElementById('productPickerModal')).hide();
          return;
        }
    } else if(isAdjustment){
        data.qty='';
        if(pickerTargetRow){
          pickerTargetRow.querySelector('.row-sku').value=data.sku;
          pickerTargetRow.cells[0].querySelector('div').innerHTML='<div style="font-weight:bold">'+HtmlEncode(data.vendorCode)+'</div><div style="font-size:11px;color:#666">'+HtmlEncode(data.nmID)+' — '+HtmlEncode(data.title)+'</div>';
          pickerTargetRow.cells[1].textContent=data.sku;
          fetchStockAdj(pickerTargetRow, data.sku, getStockWarehouseId());
        } else { addAdjRow(data); }
    } else {
        data.qty='';
        if(pickerTargetRow){
          pickerTargetRow.querySelector('.row-sku').value=data.sku;
          pickerTargetRow.cells[0].querySelector('div').innerHTML='<div style="font-weight:bold">'+HtmlEncode(data.vendorCode)+'</div><div style="font-size:11px;color:#666">'+HtmlEncode(data.nmID)+' — '+HtmlEncode(data.title)+'</div>';
          pickerTargetRow.cells[1].innerHTML=HtmlEncode(data.sku)+' <button type="button" class="copy-sku-btn" data-sku="'+HtmlEncode(data.sku)+'" style="background:transparent;border:none;color:#8a8a8a"><i class="far fa-copy" style="font-size:11px"></i></button>';
          fetch('<?= Url::to(['get-balance']) ?>?sku='+encodeURIComponent(data.sku)+'&warehouseId='+getStockWarehouseId()).then(r=>r.json()).then(d=>{
            pickerTargetRow.querySelector('.row-stock').textContent=d.qty??'—';
          });
        } else { addRow(data); }
    }
    bootstrap.Modal.getInstance(document.getElementById('productPickerModal')).hide();
  });

  // Inventory: заполнение по vendorCode (bulk)
  function openBulkModal(){
    const whId=getStockWarehouseId();
    if(!whId){ alert('Выберите склад'); return; }
    const m = new bootstrap.Modal(document.getElementById('bulkVendorModal'));
    document.getElementById('bulk-vendor-input').value='';
    document.getElementById('bulk-vendor-results').innerHTML='<div class="text-muted p-3">Введите часть vendorCode и нажмите Найти</div>';
    document.getElementById('bulk-found-count').textContent='';
    document.getElementById('bulk-check-all').checked=false;
    document.getElementById('bulk-vendor-confirm').disabled=true;
    m.show();
    setTimeout(()=>document.getElementById('bulk-vendor-input').focus(),300);
  }
  const fillByVendorBtn=document.getElementById('fill-by-vendor-btn');
  if(fillByVendorBtn) fillByVendorBtn.addEventListener('click', openBulkModal);
  const fillByVendorBtn2=document.getElementById('fill-by-vendor-btn2');
  if(fillByVendorBtn2) fillByVendorBtn2.addEventListener('click', openBulkModal);
  function doBulkSearch(){
    const term=document.getElementById('bulk-vendor-input').value.trim();
    if(term.length<2){ alert('Введите минимум 2 символа'); return; }
    const whId=getStockWarehouseId();
    const resultsEl=document.getElementById('bulk-vendor-results');
    const countEl=document.getElementById('bulk-found-count');
    resultsEl.innerHTML='<div class="p-3 text-muted">Поиск...</div>';
    fetch('<?= Url::to(['search-product']) ?>?term='+encodeURIComponent(term)).then(r=>r.json()).then(list=>{
      if(!list.length){ resultsEl.innerHTML='<div class="p-3 text-muted">Ничего не найдено</div>'; countEl.textContent=''; document.getElementById('bulk-vendor-confirm').disabled=true; return; }
      const seen=new Set(); const uniq=[];
      list.forEach(it=>{ if(!seen.has(it.sku)){ seen.add(it.sku); uniq.push(it);} });
      countEl.textContent='Найдено: '+uniq.length;
      let html='<table class="table table-sm table-hover mb-0"><thead><tr><th style="width:30px"><input type="checkbox" disabled></th><th>vendorCode</th><th>Товар</th><th>Баркод</th><th>Остаток</th></tr></thead><tbody>';
      uniq.forEach((it)=>{ html+=`<tr><td><input type="checkbox" class="bulk-chk" data-sku="${HtmlEncode(it.sku)}" data-vendor="${HtmlEncode(it.vendorCode)}" data-title="${HtmlEncode(it.title)}" data-nmid="${it.nmID}"></td><td><b>${HtmlEncode(it.vendorCode)}</b></td><td style="font-size:12px">${HtmlEncode(it.title)}</td><td style="font-family:monospace;font-size:12px">${HtmlEncode(it.sku)}</td><td class="bulk-stock" data-sku="${HtmlEncode(it.sku)}" style="text-align:center">...</td></tr>`; });
      html+='</tbody></table>';
      resultsEl.innerHTML=html;
      uniq.forEach(it=>{
        fetch('<?= Url::to(['get-balance']) ?>?sku='+encodeURIComponent(it.sku)+'&warehouseId='+whId).then(r=>r.json()).then(d=>{
          const cell=resultsEl.querySelector('.bulk-stock[data-sku="'+CSS.escape(it.sku)+'"]');
          if(cell) cell.textContent=d.qty ?? 0;
        });
      });
      document.getElementById('bulk-vendor-confirm').disabled=false;
      document.getElementById('bulk-check-all').onchange=function(){ resultsEl.querySelectorAll('.bulk-chk').forEach(c=>c.checked=this.checked); };
      resultsEl.querySelectorAll('.bulk-chk').forEach(c=>c.addEventListener('change',()=>{ const all=resultsEl.querySelectorAll('.bulk-chk'); const checked=resultsEl.querySelectorAll('.bulk-chk:checked'); document.getElementById('bulk-check-all').checked = all.length===checked.length; }));
    });
  }
  const bulkSearchBtn=document.getElementById('bulk-vendor-search');
  if(bulkSearchBtn) bulkSearchBtn.addEventListener('click', doBulkSearch);
  const bulkInput=document.getElementById('bulk-vendor-input');
  if(bulkInput) bulkInput.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); doBulkSearch(); }});
  const bulkConfirm=document.getElementById('bulk-vendor-confirm');
  if(bulkConfirm) bulkConfirm.addEventListener('click', function(){
    const checks=document.querySelectorAll('#bulk-vendor-results .bulk-chk:checked');
    if(!checks.length){ alert('Выберите хотя бы одну строку'); return; }
    let added=0, skipped=0;
    checks.forEach(chk=>{
      const sku=chk.dataset.sku;
      if([...document.querySelectorAll('.row-sku')].find(inp=>inp.value===sku)){ skipped++; return; }
      const beforeEl=document.querySelector('#bulk-vendor-results .bulk-stock[data-sku="'+CSS.escape(sku)+'"]');
      const before = beforeEl ? parseInt(beforeEl.textContent)||0 : 0;
      addInvRow({sku:sku, vendorCode:chk.dataset.vendor, title:chk.dataset.title, nmID:chk.dataset.nmid, qty_before:before, qty_fact:''});
      added++;
    });
    bootstrap.Modal.getInstance(document.getElementById('bulkVendorModal')).hide();
    const el=document.getElementById('doc-import-result');
    el.style.display='block';
    el.textContent='Добавлено '+added+' строк'+(skipped?' (пропущено уже в документе: '+skipped+')':'')+'. Введите факт.';
  });

  // Excel
  document.getElementById('import-doc-btn').addEventListener('click', ()=>document.getElementById('import-doc-file').click());
  document.getElementById('import-doc-file').addEventListener('change', function(){
    let f=this.files[0]; if(!f) return;
    let fd=new FormData(); fd.append('file',f);
    let p=document.querySelector('meta[name="csrf-param"]')?.content||'_csrf', t=document.querySelector('meta[name="csrf-token"]')?.content;
    if(p&&t) fd.append(p,t);
    let el=document.getElementById('doc-import-result'); el.style.display='block'; el.textContent='Загрузка...';
    fetch('<?= Url::to(['parse']) ?>',{method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd}).then(r=>r.json()).then(data=>{
      this.value='';
      if(!data.success){ el.textContent='Ошибка: '+(data.error||''); return; }
      let html='Найдено '+data.matched.length+' совпало, не найдено '+data.skipped.length;
      if(data.skipped.length) html+='<br><b>Не найдены:</b><div style="max-height:120px;overflow:auto;background:#fff;padding:6px;border:1px solid #ddd;font-size:11px;white-space:pre-wrap">'+data.skipped.join("\n")+'</div>';
      el.innerHTML=html;
      data.matched.forEach(it=>{
        let existing=[...document.querySelectorAll('.row-sku')].find(inp=>inp.value===it.sku);
        if(existing){
          const tr=existing.closest('tr');
          if(isInventory){ tr.querySelector('.row-fact').value=it.qty; updateDelta(tr); }
          else if(isAdjustment){ tr.querySelector('.row-qty').value=it.qty; updateAdj(tr); }
          else { tr.querySelector('.row-qty').value=it.qty; }
        } else {
          if(isInventory){
            fetch('<?= Url::to(['get-balance']) ?>?sku='+encodeURIComponent(it.sku)+'&warehouseId='+getStockWarehouseId()).then(r=>r.json()).then(d=>{
                addInvRow({sku:it.sku, vendorCode:'', title:'', nmID:it.nmID, qty_before:d.qty??0, qty_fact:it.qty});
            });
          } else if(isAdjustment){
            addAdjRow({sku:it.sku, vendorCode:'', title:'', nmID:it.nmID, qty:it.qty});
          } else {
            addRow({sku:it.sku, vendorCode:'', title:'', nmID:it.nmID, qty:it.qty});
          }
        }
      });
    });
  });

  // экспорт спецификации в Excel (как в wb-fbs-virtual kartik)
  const exportBtn=document.getElementById('export-spec-btn');
  if(exportBtn) exportBtn.addEventListener('click', function(){
    const rows=document.querySelectorAll('#doc-spec-body tr');
    if(!rows.length){ alert('Нет строк для экспорта'); return; }
    let csv='\uFEFF';
    if(isInventory){
      csv+='vendorCode;title;nmID;sku;По учету;Факт;Разница\n';
      rows.forEach(tr=>{
        const sku=tr.querySelector('.row-sku')?.value||'';
        const vendor=tr.querySelector('td div')?.textContent?.trim()||'';
        const title=(tr.querySelector('td div+div')?.textContent||'').replace(';',',');
        const nmID=title.split(' — ')[0]||'';
        const before=tr.querySelector('.row-stock')?.textContent?.trim()||'0';
        const fact=tr.querySelector('.row-fact')?.value||'';
        const delta=tr.querySelector('.row-delta')?.textContent?.trim()||'';
        csv+=`"${vendor}";"${title}";"${nmID}";"${sku}";"${before}";"${fact}";"${delta}"\n`;
      });
    } else if(isAdjustment){
      csv+='vendorCode;title;nmID;sku;Остаток;Корректировка;Станет\n';
      rows.forEach(tr=>{
        const sku=tr.querySelector('.row-sku')?.value||'';
        const vendor=tr.querySelector('td div')?.textContent?.trim()||'';
        const title=(tr.querySelector('td div+div')?.textContent||'').replace(';',',');
        const nmID=title.split(' — ')[0]||'';
        const stock=tr.querySelector('.row-stock')?.textContent?.trim()||'';
        const qty=tr.querySelector('.row-qty')?.value||'';
        const nval=tr.querySelector('.row-new')?.textContent?.trim()||'';
        csv+=`"${vendor}";"${title}";"${nmID}";"${sku}";"${stock}";"${qty}";"${nval}"\n`;
      });
    } else {
      csv+='vendorCode;title;nmID;sku;Остаток;Количество\n';
      rows.forEach(tr=>{
        const sku=tr.querySelector('.row-sku')?.value||'';
        const vendor=tr.querySelector('td div')?.textContent?.trim()||'';
        const title=(tr.querySelector('td div+div')?.textContent||'').replace(';',',');
        const nmID=title.split(' — ')[0]||'';
        const stock=tr.querySelector('.row-stock')?.textContent?.trim()||'';
        const qty=tr.querySelector('.row-qty')?.value||'';
        csv+=`"${vendor}";"${title}";"${nmID}";"${sku}";"${stock}";"${qty}"\n`;
      });
    }
    const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
    const url=URL.createObjectURL(blob);
    const a=document.createElement('a'); a.href=url; a.download='spec_'+new Date().toISOString().slice(0,10)+'.csv'; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
  });

  // экспорт спецификации
  const exportBtnUpd=document.getElementById('export-spec-btn');
  if(exportBtnUpd) exportBtnUpd.addEventListener('click', function(){
    const rows=document.querySelectorAll('#doc-spec-body tr');
    if(!rows.length){ alert('Нет строк для экспорта'); return; }
    let csv='\uFEFF';
    if(isInventory){
      csv+='vendorCode;title;nmID;sku;По учету;Факт;Разница\n';
      rows.forEach(tr=>{
        const sku=tr.querySelector('.row-sku')?.value||'';
        const vendor=tr.querySelector('td div')?.textContent?.trim()||'';
        const title=(tr.querySelector('td div+div')?.textContent||'').replace(';',',');
        const nmID=title.split(' — ')[0]||'';
        const before=tr.querySelector('.row-stock')?.textContent?.trim()||'0';
        const fact=tr.querySelector('.row-fact')?.value||'';
        const delta=tr.querySelector('.row-delta')?.textContent?.trim()||'';
        csv+=`"${vendor}";"${title}";"${nmID}";"${sku}";"${before}";"${fact}";"${delta}"\n`;
      });
    } else if(isAdjustment){
      csv+='vendorCode;title;nmID;sku;Остаток;Корректировка;Станет\n';
      rows.forEach(tr=>{
        const sku=tr.querySelector('.row-sku')?.value||'';
        const vendor=tr.querySelector('td div')?.textContent?.trim()||'';
        const title=(tr.querySelector('td div+div')?.textContent||'').replace(';',',');
        const nmID=title.split(' — ')[0]||'';
        const stock=tr.querySelector('.row-stock')?.textContent?.trim()||'';
        const qty=tr.querySelector('.row-qty')?.value||'';
        const nval=tr.querySelector('.row-new')?.textContent?.trim()||'';
        csv+=`"${vendor}";"${title}";"${nmID}";"${sku}";"${stock}";"${qty}";"${nval}"\n`;
      });
    } else {
      csv+='vendorCode;title;nmID;sku;Остаток;Количество\n';
      rows.forEach(tr=>{
        const sku=tr.querySelector('.row-sku')?.value||'';
        const vendor=tr.querySelector('td div')?.textContent?.trim()||'';
        const title=(tr.querySelector('td div+div')?.textContent||'').replace(';',',');
        const nmID=title.split(' — ')[0]||'';
        const stock=tr.querySelector('.row-stock')?.textContent?.trim()||'';
        const qty=tr.querySelector('.row-qty')?.value||'';
        csv+=`"${vendor}";"${title}";"${nmID}";"${sku}";"${stock}";"${qty}"\n`;
      });
    }
    const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
    const url=URL.createObjectURL(blob);
    const a=document.createElement('a'); a.href=url; a.download='spec_'+new Date().toISOString().slice(0,10)+'.csv'; document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
  });

  // итоги
  const tbodyUpd=document.getElementById('doc-spec-body');
  if(tbodyUpd){ new MutationObserver(updateTotals).observe(tbodyUpd, {childList:true, subtree:true}); tbodyUpd.addEventListener('input', updateTotals); }

  // загрузка начальных
  initialItems.forEach(it=>{
    if(isInventory) addInvRow(it);
    else if(isAdjustment) addAdjRow(it);
    else addRow(it);
  });
  if(!isInventory && initialItems.length===0) addRow();
  updateTotals();

  document.getElementById('doc-warehouseId').addEventListener('change', function(){
    const whId=this.value;
    document.querySelectorAll('#doc-spec-body tr').forEach(tr=>{
      let sku=tr.querySelector('.row-sku')?.value;
      if(!sku) return;
      if(isInventory){
        fetch('<?= Url::to(['get-balance']) ?>?sku='+encodeURIComponent(sku)+'&warehouseId='+whId).then(r=>r.json()).then(d=>{
          tr.querySelector('.row-stock').textContent=d.qty??0;
          tr.querySelector('.row-before-input').value=d.qty??0;
          updateDelta(tr);
        });
      } else if(isAdjustment){
        fetchStockAdj(tr, sku, whId);
      } else {
        fetch('<?= Url::to(['get-balance']) ?>?sku='+encodeURIComponent(sku)+'&warehouseId='+whId).then(r=>r.json()).then(d=>{
          tr.querySelector('.row-stock').textContent=d.qty??'—';
        });
      }
    });
  });
})();
</script>
