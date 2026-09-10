<?php
use yii\helpers\Html;
use kartik\icons\Icon;
use yii\bootstrap5\BootstrapIconAsset;
Icon::map($this);
BootstrapIconAsset::register($this);

/** @var app\models\WbDoc $model */
$wh = \app\models\OurWarehouse::findOne((int)$model->warehouseId);
$whName = $wh ? $wh->name : '#'.$model->warehouseId;
$company = \yii\db\Query::createFromConfig([]) ? null : null;
$companyName = (new \yii\db\Query())->select('name')->from('companies')->where(['id'=>$model->company_id])->scalar() ?: 'Компания #'.$model->company_id;
$items = $model->items;
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Акт инвентаризации #<?= $model->id ?></title>
<style>
body{font-family:Arial, sans-serif; font-size:13px; color:#222; padding:20px}
h1{font-size:18px; margin:0 0 6px}
h2{font-size:14px; color:#555; margin:0 0 14px}
table{border-collapse:collapse; width:100%; margin-top:12px}
th,td{border:1px solid #999; padding:6px 8px; text-align:left}
th{background:#f0f0f0}
.right{text-align:right; font-weight:bold}
.delta-pos{color:#0a0}
.delta-neg{color:#c00}
@media print { .no-print{display:none} }
</style></head><body>
<div class="no-print" style="margin-bottom:12px"><button onclick="window.print()">Печать</button> <button onclick="window.close()">Закрыть</button></div>
<h1>Акт инвентаризации № <?= $model->id ?> от <?= Html::encode(date('d.m.Y H:i', strtotime($model->date))) ?></h1>
<h2><?= Html::encode($companyName) ?> — склад <?= Html::encode($whName) ?> — статус <?= Html::encode($model->status) ?></h2>
<?php if($model->comment): ?><p>Комментарий: <?= Html::encode($model->comment) ?></p><?php endif; ?>
<table>
<thead><tr>
<th>#</th><th>Баркод</th><th>Товар</th><th>nmID</th><th>По учету</th><th>Факт</th><th>Разница</th>
</tr></thead>
<tbody>
<?php
$sumBefore=0; $sumFact=0; $sumDelta=0;
$n=1;
foreach($items as $it){
    $fact = ($it->hasAttribute('qty_fact') && $it->qty_fact!==null) ? $it->qty_fact : $it->qty;
    $before = ($it->hasAttribute('qty_before') && $it->qty_before!==null) ? $it->qty_before : 0;
    $delta = (int)$fact - (int)$before;
    $sumBefore+=$before; $sumFact+=$fact; $sumDelta+=$delta;
    $size = \app\models\WbCardSize::findOne(['sku'=>$it->sku]);
    $title = '';
    if($size){
        $card = \app\models\WbCard::findOne(['nmID'=>$size->nmID]);
        $title = $card ? $card->vendorCode.' — '.$card->title : '';
    }
    echo '<tr>';
    echo '<td>'.($n++).'</td>';
    echo '<td>'.Html::encode($it->sku).'</td>';
    echo '<td>'.Html::encode($title).'</td>';
    echo '<td>'.Html::encode($it->nmID).'</td>';
    echo '<td style="text-align:center">'.(int)$before.'</td>';
    echo '<td style="text-align:center">'.(int)$fact.'</td>';
    echo '<td style="text-align:center;font-weight:bold" class="'.($delta>0?'delta-pos':($delta<0?'delta-neg':'')) .'">'.($delta>0?'+':'').$delta.'</td>';
    echo '</tr>';
}
?>
</tbody>
<tfoot><tr>
<th colspan="4" style="text-align:right">Итого</th><th style="text-align:center"><?= $sumBefore ?></th><th style="text-align:center"><?= $sumFact ?></th><th style="text-align:center"><?= ($sumDelta>0?'+':'').$sumDelta ?></th>
</tr></tfoot>
</table>
<br><br>
<table style="border:none; width:100%"><tr style="border:none">
<td style="border:none">Председатель комиссии ______________ / ______________</td>
<td style="border:none">Кладовщик ______________ / ______________</td>
<td style="border:none">Дата __________</td>
</tr></table>
</body></html>
