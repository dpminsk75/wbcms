<?php
use yii\helpers\Html;
?>
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
<p><?= Html::a('Синхрониз. склады с WB', ['/wb-fbs-virtual/warehouse-list'], ['class'=>'btn btn-default btn-sm','onclick'=>'fetch("'. \yii\helpers\Url::to(['/wb-fbs/sync-warehouses']).'",{method:"POST",headers:{"X-CSRF-Token":yii.getCsrfToken()}}).then(()=>location.reload()); return false;']) ?></p>
<style>
    .modal-body .btn {
        font-size: 14px;
    }
</style>