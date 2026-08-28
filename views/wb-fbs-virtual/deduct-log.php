<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var string[] $files */
/** @var string|null $selected */
/** @var string $content */

$this->title = 'Лог вычета заказов из виртуал. остатков';
$this->params['breadcrumbs'][] = ['label' => 'FBS Остатки', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wb-fbs-deduct-log">
    <h1><?= Html::encode($this->title) ?></h1>
    <p class="text-muted">Файлы <code>@runtime/logs/wb-fbs-deduct-YYYY-MM-DD.log</code>. Показаны последние <?= count(explode("\n",$content)) ?> строк.</p>

    <?php if (!empty($files)): ?>
        <div class="btn-group" style="margin-bottom:12px">
            <?php foreach ($files as $f): $bn=basename($f); $isSel = $selected && basename($selected)===$bn; ?>
                <a href="<?= Url::to(['deduct-log','date'=>substr($bn,13,10)]) ?>" class="btn btn-sm <?= $isSel?'btn-primary':'btn-default' ?>"><?= Html::encode($bn) ?></a>
            <?php endforeach; ?>
        </div>
        <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;max-height:70vh;overflow:auto;font-size:12px;white-space:pre-wrap"><?= Html::encode($content ?: '(пусто)') ?></pre>
    <?php else: ?>
        <div class="alert alert-warning">Лог-файлов пока нет.</div>
    <?php endif; ?>

    <p><a href="<?= Url::to(['index']) ?>" class="btn btn-default">Назад к остаткам</a></p>
</div>
