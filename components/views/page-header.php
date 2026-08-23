<?php
use yii\helpers\Html;
/** @var string $title */
/** @var string|int $nmId */
?>
<div class="row mb-3">
    <div class="d-flex col-md-10">
        <h1><?= Html::encode($title) ?></h1>
    </div>

    <div class="nm-id-container col-md-2 d-flex justify-content-end align-items-center" style="color: #555; font-size: 12px;">
        ID:&nbsp;<b><span id="nmId-value"><?= Html::encode($nmId) ?></span></b>
        
        <i class="fas fa-copy" 
           style="cursor: pointer; margin-left: 5px;" 
           onclick="copyToClipboard('nmId-value')" 
           title="Копировать ID">
        </i>

<?= Html::a('<i class="fas fa-eye"></i>', 
    ['/wb/detail', 'DPFilterForm' => ['nm_id' => $nmId]], 
    ['class' => 'btn-eye', 'target' => '_blank']
); ?>


    </div>
</div>