<?php

use Yii;
use yii\helpers\Url;

/** @var yii\web\View $this */

$this->title = 'Wildberries карточки';
?>
<div class="wb-index">
    <h1><?= htmlspecialchars($this->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>

    <?php if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin'): ?>
        <p>
            <a href="<?= Url::to(['wb/sync-cards']) ?>" class="btn btn-primary">
                Синхронизировать карточки (загрузить/обновить из WB)
            </a>
        </p>
    <?php endif; ?>
</div>

