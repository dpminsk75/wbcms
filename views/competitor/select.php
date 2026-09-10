<?php
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\icons\Icon;
Icon::map($this);

$this->title = 'Выбор фраз — nmID ' . $nmId;
$this->params['breadcrumbs'][] = ['label' => 'Конкуренты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="competitor-select">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($card): ?>
    <div class="card mb-3">
        <div class="card-body d-flex align-items-center gap-3">
            <div>
                <div><b><?= Html::encode($card['title'] ?? '') ?></b></div>
                <div class="small text-muted">nmID <?= $nmId ?> | <?= Html::encode($card['brand'] ?? '') ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($phrases)): ?>
        <div class="alert alert-warning">Нет поисковых фраз для этого товара.</div>
    <?php else: ?>
    <form method="post" action="<?= Url::to(['selected', 'nm_id' => $nmId]) ?>">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-search"></i> Поисковые фразы (<?= count($phrases) ?>)</span>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="select-all">Выбрать все</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all">Снять все</button>
                </div>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($phrases as $phrase): ?>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="phrases[]" value="<?= Html::encode($phrase) ?>"
                        id="phrase-<?= md5($phrase) ?>"
                        <?= in_array($phrase, $selectedPhrases) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="phrase-<?= md5($phrase) ?>">
                        <?= Html::encode($phrase) ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-sort-numeric-down"></i> Позиция в выдаче</div>
            <div class="card-body">
                <input type="hidden" name="position_max" id="position-max-input" value="9">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary btn-sm pos-btn" data-val="6">1–6</button>
                    <button type="button" class="btn btn-primary btn-sm pos-btn" data-val="9">1–9</button>
                    <button type="button" class="btn btn-outline-primary btn-sm pos-btn" data-val="12">1–12</button>
                    <button type="button" class="btn btn-outline-primary btn-sm pos-btn" data-val="0">Все</button>
                </div>
                <div class="small text-muted mt-1">Только конкуренты с позицией в указанном диапазоне</div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Далее — выбрать конкурентов</button>
    </form>
    <?php endif; ?>
</div>

<script>
document.getElementById('select-all')?.addEventListener('click', function() {
    document.querySelectorAll('input[name="phrases[]"]').forEach(function(cb) { cb.checked = true; });
});
document.getElementById('deselect-all')?.addEventListener('click', function() {
    document.querySelectorAll('input[name="phrases[]"]').forEach(function(cb) { cb.checked = false; });
});
document.querySelectorAll('.pos-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('position-max-input').value = this.dataset.val;
        document.querySelectorAll('.pos-btn').forEach(function(b) {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline-primary');
        });
        this.classList.remove('btn-outline-primary');
        this.classList.add('btn-primary');
    });
});
</script>
