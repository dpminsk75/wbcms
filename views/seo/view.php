<?php
use yii\helpers\Html;


use kartik\icons\Icon;
Icon::map($this); 

use yii\bootstrap5\BootstrapIconAsset;
BootstrapIconAsset::register($this);

/** @var $model app\models\WbSeoRecommendation */
$this->title = "Рекомендация #{$model->id}";
$this->params['breadcrumbs'][] = ['label'=>'SEO рекомендации', 'url'=>['index']];
$this->params['breadcrumbs'][] = "#{$model->id}";

$card = $model->card;
$keywordsAdded = $model->keywords_added ? json_decode($model->keywords_added, true) : [];
$keywordsRemoved = $model->keywords_removed ? json_decode($model->keywords_removed, true) : [];
$raw = $model->raw_json ? json_decode($model->raw_json, true) : null;

function diffText($old, $new) {
    $old = Html::encode($old);
    $new = Html::encode($new);
    if ($old === $new) return "<div class='text-muted'>Без изменений</div>";
    return "<div class='mb-2'><span class='badge bg-secondary'>Старый</span><div class='border p-2 bg-light'>$old</div></div>"
         . "<div><span class='badge bg-success'>Новый</span><div class='border p-2 bg-white fw-bold'>$new</div></div>";
}
?>
<div class="seo-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= Html::encode($this->title) ?> <span class="badge bg-<?= $model->status==='new'?'warning':'success' ?>"><?= $model->status ?></span></h3>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" id="seo-process-btn" data-nmid="<?= $model->nmID ?>"><i class="fas fa-robot"></i> Обработать с AI</button>
            <?php if ($model->status==='new'): ?>
                <?= Html::a('<i class="fas fa-check"></i> Просмотрено', ['mark-viewed','id'=>$model->id], ['class'=>'btn btn-success','data-method'=>'post']) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-undo"></i> Вернуть в обработку', ['requeue','id'=>$model->id], ['class'=>'btn btn-warning','data-method'=>'post','data-confirm'=>'Вернуть в обработку?']) ?>
            <?= Html::a('К списку', ['index','status'=>$model->status], ['class'=>'btn btn-outline-secondary']) ?>
        </div>
    </div>

    <!-- popup хода обработки -->
    <div class="modal fade" id="seoProcessModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title"><i class="fas fa-robot"></i> Обработка с AI</h5></div>
          <div class="modal-body text-center py-4">
            <div class="spinner-border text-primary mb-3" role="status" style="width:3rem;height:3rem"></div>
            <div id="seo-process-status" class="small">Отправляю к OpenRouter...</div>
            <div id="seo-process-detail" class="small text-muted mt-1">nmID <?= $model->nmID ?> • подбираю фразы</div>
            <div class="progress mt-3" style="height:6px"><div id="seo-process-progress" class="progress-bar progress-bar-striped progress-bar-animated" style="width:20%"></div></div>
          </div>
          <div class="modal-footer" style="display:none" id="seo-process-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
          </div>
        </div>
      </div>
    </div>

    <?php if ($card): ?>
    <div class="card mb-3">
        <div class="card-body d-flex gap-3">
            <?php $photos = $card->getPhotosArray(); if (!empty($photos[0])) echo Html::img($photos[0], ['style'=>'width:120px;height:120px;object-fit:cover;border-radius:8px']); ?>
            <div>
                <div><b><?= Html::encode($card->title) ?></b></div>
                <div class="small text-muted">nmID <?= $card->nmID ?> | <?= Html::encode($card->subjectName) ?> | <?= Html::encode($card->brand) ?> | <?= Html::encode($card->vendorCode) ?></div>
                <div class="mt-2 d-flex gap-2">
                    <?= Html::a('Карточка WB', ['/wb/detail','DPFilterForm'=>['nm_id'=>$model->nmID]], ['class'=>'btn btn-sm btn-outline-primary','target'=>'_blank']) ?>
                    <?= Html::a('Фразы → карточки', ['/wb-search/card','DPFilterForm'=>['nm_id'=>$model->nmID]], ['class'=>'btn btn-sm btn-outline-info','target'=>'_blank']) ?>
                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#seoTargetsModal"><i class="fas fa-bullseye"></i> Целевые (<?= count($targets) ?>/10)</button>
                </div>
            </div>
            <div class="ms-auto small text-muted">
                Модель: <?= Html::encode($model->model) ?><br>
                Confidence: <?= $model->confidence ? round($model->confidence*100).'%' : '—' ?><br>
                Создано: <?= Yii::$app->formatter->asDatetime($model->created_at) ?><br>
                <?php if ($model->viewed_at) echo "Просмотрено: ".Yii::$app->formatter->asDatetime($model->viewed_at)." <br>"; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Заголовок</span>
                    <button type="button" class="btn btn-sm btn-outline-success seo-copy" data-copy="<?= Html::encode($model->new_title) ?>" title="Копировать новый заголовок"><i class="fas fa-copy"></i> Копировать</button>
                </div>
                <div class="card-body"><?= diffText($model->old_title, $model->new_title) ?>
                    <div class="small text-muted mt-2">Старый <?= mb_strlen($model->old_title) ?> симв → Новый <?= mb_strlen($model->new_title) ?> симв</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Ключевые слова</span>
                    <span class="badge bg-light text-dark border" title="Целевые идут в промпт с приоритетом">цель</span>
                </div>
                <div class="card-body small">
                    <div>Добавлены: <?= $keywordsAdded ? Html::encode(implode(', ', $keywordsAdded)) : '<span class="text-muted">—</span>' ?></div>
                    <div>Удалены: <?= $keywordsRemoved ? Html::encode(implode(', ', $keywordsRemoved)) : '<span class="text-muted">—</span>' ?></div>
                    <div class="mt-2"><b>Rationale:</b> <?= Html::encode($model->rationale) ?></div>
                    <?php if ($raw['response']['usage'] ?? null): ?>
                        <div class="text-muted mt-1">tokens prompt: <?= $model->prompt_tokens ?>, completion: <?= $model->completion_tokens ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal целевых (триггер в шапке карточки) -->
    <div class="modal fade" id="seoTargetsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title"><i class="fas fa-bullseye text-warning"></i> Целевые запросы — nmID <?= $model->nmID ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body"><?= $this->render('_targets_block', ['nmID'=>$model->nmID, 'targets'=>$targets]) ?></div>
        </div>
      </div>
    </div>

    <div class="small text-muted mb-2">Скопируй <b>Новый</b> текст вручную в кабинет WB → Сохранить. Авто-изменения не делаем.</div>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Описание</span>
            <button type="button" class="btn btn-sm btn-outline-success seo-copy" data-copy="<?= Html::encode($model->new_description) ?>" title="Копировать новое описание"><i class="fas fa-copy"></i> Копировать</button>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="small text-muted mb-1"><span class="badge bg-secondary">Старый</span> <?= mb_strlen($model->old_description) ?> симв</div>
                    <div style="white-space:pre-wrap;line-height:1.4;background:#f8f9fa;border:1px solid #e9ecef;border-radius:6px;padding:10px;max-height:600px;overflow:auto;font-size:13px"><?= Html::encode($model->old_description) ?: '<span class="text-muted">—</span>' ?></div>
                </div>
                <div class="col-md-6">
                    <div class="small text-success mb-1"><span class="badge bg-success">Новый</span> <?= mb_strlen($model->new_description) ?> симв</div>
                    <div style="white-space:pre-wrap;line-height:1.4;background:#e8f5e9;border:1px solid #c8e6c9;border-radius:6px;padding:10px;max-height:600px;overflow:auto;font-size:13px;font-weight:500"><?= Html::encode($model->new_description) ?: '<span class="text-muted">—</span>' ?></div>
                </div>
            </div>
        </div>
    </div>
<?php
$copyJs = <<<JS
document.querySelectorAll('.seo-copy').forEach(function(btn){
  btn.addEventListener('click', function(){
    var text = this.getAttribute('data-copy') || '';
    navigator.clipboard.writeText(text).then(function(){
      var orig = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check"></i> Скопировано';
      btn.classList.remove('btn-outline-success'); btn.classList.add('btn-success');
      setTimeout(function(){ btn.innerHTML = orig; btn.classList.add('btn-outline-success'); btn.classList.remove('btn-success'); }, 1500);
    });
  });
});
JS;
$this->registerJs($copyJs);
?>
<?php
$processUrl = \yii\helpers\Url::to(['process']);
$csrf = Yii::$app->request->csrfToken;
$processJs = <<<JS
document.getElementById('seo-process-btn').addEventListener('click', function(){
  var btn = this;
  var nmID = btn.getAttribute('data-nmid');
  var modalEl = document.getElementById('seoProcessModal');
  var modal = new bootstrap.Modal(modalEl);
  var statusEl = document.getElementById('seo-process-status');
  var detailEl = document.getElementById('seo-process-detail');
  var progressEl = document.getElementById('seo-process-progress');
  var footerEl = document.getElementById('seo-process-footer');
  btn.disabled = true;
  statusEl.textContent = 'Отправляю к OpenRouter...';
  detailEl.textContent = 'nmID ' + nmID + ' • подбираю фразы • ожидание до 60с';
  progressEl.style.width = '30%';
  footerEl.style.display = 'none';
  modal.show();
  var dots = 0;
  var iv = setInterval(function(){
    dots = (dots+1)%4;
    statusEl.textContent = 'Жду ответ от AI' + '.'.repeat(dots);
    var w = parseInt(progressEl.style.width) || 30;
    if(w < 85) progressEl.style.width = (w+2) + '%';
  }, 800);
  fetch('$processUrl', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
    body: '_csrf=$csrf&nmID=' + encodeURIComponent(nmID)
  }).then(function(r){ return r.json(); }).then(function(data){
    clearInterval(iv);
    progressEl.style.width = '100%';
    if(data.success){
      statusEl.textContent = 'Готово! Открываю...';
      detailEl.textContent = 'Рекомендация #' + data.id;
      setTimeout(function(){ window.location.href = data.url; }, 600);
    } else {
      statusEl.textContent = 'Ошибка';
      detailEl.innerHTML = '<span class="text-danger">' + (data.error || 'unknown') + '</span>';
      footerEl.style.display = 'block';
      btn.disabled = false;
    }
  }).catch(function(e){
    clearInterval(iv);
    statusEl.textContent = 'Ошибка сети';
    detailEl.textContent = e.message;
    footerEl.style.display = 'block';
    btn.disabled = false;
  });
});
JS;
$this->registerJs($processJs);
?>

    <?php if ($model->status==='viewed'): ?>
        <div class="alert alert-success">Отмечено как просмотрено <?= $model->viewed_at ?>. <a href="<?= \yii\helpers\Url::to(['index','status'=>'viewed']) ?>">К прошлым</a> | <?= Html::a('Вернуть в обработку', ['requeue','id'=>$model->id], ['data-method'=>'post']) ?></div>
    <?php endif; ?>

    <?php
    // Блок фраз из расчета — показываем на чем ИИ строил рекомендацию
    $prompt = $raw['prompt'] ?? null;
    $phrasesTop = $prompt['top_phrases_by_clicks'] ?? [];
    $phrasesOrders = $prompt['phrases_with_orders'] ?? [];
    $phrasesOpp = $prompt['opportunity_phrases_pos_11_50'] ?? [];
    $period = $prompt['period'] ?? '';
    $hasPhrases = !empty($phrasesTop) || !empty($phrasesOrders) || !empty($phrasesOpp);
    ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Поисковые фразы из расчета <?= $period ? "($period)" : "" ?></span>
            <span class="small text-muted">Источник: wb_sr_report_item_phrases • сгруппировано по phrase, ORDER BY clicks</span>
        </div>
        <div class="card-body">
            <?php if (!$hasPhrases): ?>
                <div class="alert alert-warning mb-0">Нет фраз за период — ИИ строил только по карточке. Проверь <code>wb_sr_report_item_phrases</code> для nmID <?= $model->nmID ?></div>
            <?php else: ?>
                <?php if (!empty($phrasesTop)): ?>
                <h6>Топ по кликам (10)</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered align-middle" style="font-size:12px">
                        <thead class="table-light"><tr><th>Фраза</th><th>Част</th><th>Поз</th><th>Клики</th><th>Заказы</th></tr></thead>
                        <tbody>
                        <?php foreach ($phrasesTop as $r): ?>
                            <tr><td><?= Html::encode($r['phrase']) ?></td><td class="text-end"><?= (int)$r['freq'] ?></td><td class="text-end"><?= Html::encode($r['avg_pos']) ?></td><td class="text-end"><?= (int)$r['clicks'] ?></td><td class="text-end"><?= (int)$r['orders'] ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php if (!empty($phrasesOrders)): ?>
                <h6>С заказами (5)</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered align-middle" style="font-size:12px">
                        <thead class="table-light"><tr><th>Фраза</th><th>Част</th><th>Поз</th><th>Клики</th><th>Заказы</th></tr></thead>
                        <tbody>
                        <?php foreach ($phrasesOrders as $r): ?>
                            <tr class="table-success"><td><?= Html::encode($r['phrase']) ?></td><td class="text-end"><?= (int)$r['freq'] ?></td><td class="text-end"><?= Html::encode($r['avg_pos']) ?></td><td class="text-end"><?= (int)$r['clicks'] ?></td><td class="text-end"><b><?= (int)$r['orders'] ?></b></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php if (!empty($phrasesOpp)): ?>
                <h6>Opportunity поз 11-50 (5)</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered align-middle" style="font-size:12px">
                        <thead class="table-light"><tr><th>Фраза</th><th>Част</th><th>Поз</th><th>Клики</th></tr></thead>
                        <tbody>
                        <?php foreach ($phrasesOpp as $r): ?>
                            <tr class="table-warning"><td><?= Html::encode($r['phrase']) ?></td><td class="text-end"><?= (int)$r['freq'] ?></td><td class="text-end"><?= Html::encode($r['avg_pos']) ?></td><td class="text-end"><?= (int)$r['clicks'] ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <div class="small text-muted">Сырые данные: <code>wb_sr_report_item_phrases WHERE nmID=<?= $model->nmID ?> AND date BETWEEN ...</code> • Если пусто — товар новый/нет отчетов sr.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
