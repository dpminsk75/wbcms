<?php
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\icons\Icon;
use yii\bootstrap5\BootstrapIconAsset;
Icon::map($this);
BootstrapIconAsset::register($this);

$isStandalone = !Yii::$app->request->isAjax;
if ($isStandalone) {
    $this->title = 'Виртуальные склады — настройки WB';
    $this->params['breadcrumbs'][] = ['label' => 'Справочники', 'url' => '#'];
    $this->params['breadcrumbs'][] = $this->title;
}
?>
<?php if ($isStandalone): ?>
<h1 class="mb-3"><?= Html::encode($this->title) ?></h1>
<p class="text-muted small">Только редактирование складов WB: флаг <b>Виртуальный</b> — куда грузить остатки via PUT, <b>Учитывать</b> — чьи заказы минусуем с оперативного <code>is_fbs</code>. Синхронизация тянет список с WB.</p>
<?php endif; ?>
<table class="table table-condensed table-striped">
<thead><tr><th>ID</th><th>Название</th><th>Виртуал.</th><th>Учитывать</th><th></th><th></th></tr></thead>
<tbody>
<?php foreach ($warehouses as $w): ?>
<tr data-id="<?= $w->id ?>" <?= !$w->is_processing ? 'style="background:#fff3cd"' : '' ?> title="<?= !$w->is_processing ? 'isProcessing=false' : '' ?>">
    <td><?= $w->warehouseId ?></td>
    <td><?= Html::encode($w->name) ?><br><small class="text-muted"><?= Html::encode($w->address) ?></small></td>
    <td class="virt-cell"><?= $w->is_virtual ? '<span class="badge bg-success">Да</span>' : '<span class="badge bg-secondary">Нет</span>' ?></td>
    <td class="consider-cell"><?= $w->consider_orders ? '<span class="badge bg-info">Да</span>' : '<span class="badge bg-secondary">Нет</span>' ?></td>
    <td><button type="button" class="w-100 btn btn-xs virt-toggle-btn <?= $w->is_virtual?'btn-warning':'btn-success' ?> text-nowrap" data-id="<?= $w->id ?>"><?= $w->is_virtual ? 'Снять' : 'Виртуальный' ?></button></td>
    <td><button type="button" class="w-100 btn btn-xs consider-toggle-btn <?= $w->consider_orders?'btn-info':'btn-secondary' ?> text-nowrap" data-id="<?= $w->id ?>" title="<?= $w->consider_orders ? 'Перестать учитывать' : 'Учитывать заказы этого склада в вычете' ?>"><?= $w->consider_orders ? 'Не учитывать' : 'Учитывать' ?></button></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p><?= Html::a('Синхрониз. склады с WB', ['/wb-fbs-virtual/warehouse-list'], ['class'=>'btn btn-default btn-sm','onclick'=>'fetch("'. Url::to(['/wb-fbs/sync-warehouses']).'",{method:"POST",headers:{"X-CSRF-Token":yii.getCsrfToken()}}).then(()=>location.reload()); return false;']) ?></p>
<style>
    .modal-body .btn {
        font-size: 14px;
    }
</style>
<?php if ($isStandalone): ?>
<script>
(function(){
  function csrf(){ var m=document.querySelector('meta[name="csrf-token"]'); return m?m.content:(window.yii&&yii.getCsrfToken?yii.getCsrfToken():null); }
  function csrfParam(){ var m=document.querySelector('meta[name="csrf-param"]'); return m?m.content:(window.yii&&yii.getCsrfParam?yii.getCsrfParam():'_csrf'); }
  document.addEventListener('click', function(ev){
    var btn=ev.target.closest('.virt-toggle-btn');
    if(!btn) return;
    var id=btn.dataset.id; btn.disabled=true;
    var body=new URLSearchParams(); var p=csrfParam(), t=csrf(); if(p&&t) body.append(p,t);
    fetch('<?= Url::to(['toggle-virtual']) ?>?id='+id, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:body})
      .then(r=>r.text().then(txt=>{ try{return JSON.parse(txt);}catch(e){return {success:false,error:txt};}}))
      .then(d=>{ btn.disabled=false; if(!d.success) return; var tr=btn.closest('tr'); var cell=tr.querySelector('.virt-cell');
        if(d.is_virtual){ cell.innerHTML='<span class="badge bg-success">Да</span>'; btn.textContent='Снять'; btn.className='w-100 btn btn-xs virt-toggle-btn btn-warning text-nowrap'; }
        else { cell.innerHTML='<span class="badge bg-secondary">Нет</span>'; btn.textContent='Виртуальный'; btn.className='w-100 btn btn-xs virt-toggle-btn btn-success text-nowrap'; }});
  });
  document.addEventListener('click', function(ev){
    var btn=ev.target.closest('.consider-toggle-btn');
    if(!btn) return;
    var id=btn.dataset.id; btn.disabled=true;
    var body=new URLSearchParams(); var p=csrfParam(), t=csrf(); if(p&&t) body.append(p,t);
    fetch('<?= Url::to(['toggle-consider']) ?>?id='+id, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:body})
      .then(r=>r.text().then(txt=>{ try{return JSON.parse(txt);}catch(e){return {success:false,error:txt};}}))
      .then(d=>{ btn.disabled=false; if(!d.success) return; var tr=btn.closest('tr'); var cell=tr.querySelector('.consider-cell');
        if(d.consider_orders){ cell.innerHTML='<span class="badge bg-info">Да</span>'; btn.textContent='Не учитывать'; btn.className='w-100 btn btn-xs consider-toggle-btn btn-info text-nowrap'; }
        else { cell.innerHTML='<span class="badge bg-secondary">Нет</span>'; btn.textContent='Учитывать'; btn.className='w-100 btn btn-xs consider-toggle-btn btn-secondary text-nowrap'; }});
  });
})();
</script>
<?php endif; ?>