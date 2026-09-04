<?php
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\grid\GridView;

/** @var $dataProvider yii\data\ActiveDataProvider */
/** @var $status string */
/** @var $counts array */
/** @var $nmID string */

use kartik\icons\Icon;
Icon::map($this); 

use yii\bootstrap5\BootstrapIconAsset;
BootstrapIconAsset::register($this);

$this->title = 'SEO рекомендации';
$this->params['breadcrumbs'][] = $this->title;

$isAdmin = Yii::$app->user->can('admin');
$tabNewClass = $status === 'new' ? 'btn-primary' : 'btn-outline-primary';
$tabViewedClass = $status === 'viewed' ? 'btn-primary' : 'btn-outline-primary';
?>
<div class="seo-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?= Html::encode($this->title) ?></h2>
        <div>
            <?= Html::a("К просмотру <span class='badge bg-white text-primary'>{$counts['new']}</span>", ['index','status'=>'new'], ['class'=>"btn $tabNewClass btn-sm"]) ?>
            <?= Html::a("Просмотренные <span class='badge bg-white text-primary'>{$counts['viewed']}</span>", ['index','status'=>'viewed'], ['class'=>"btn $tabViewedClass btn-sm"]) ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="status" value="<?= Html::encode($status) ?>">
                <div class="col-auto">
                    <label class="form-label small">Поиск (nmID / название / артикул)</label>
                    <input type="text" name="q" value="<?= Html::encode($q ?? '') ?>" class="form-control form-control-sm" placeholder="526443466, Сваты, 11/25 ..." style="width:280px">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Фильтр</button>
                    <a href="<?= Url::to(['index','status'=>$status]) ?>" class="btn btn-sm btn-link">Сброс</a>
                    <a href="<?= Url::to(['index','status'=>$status, 'q'=>$q ?? '', 'unprocessed'=>1]) ?>" class="btn btn-sm btn-outline-warning ms-1" title="Карточки по запросу без рекомендации (если пусто — топ 20)">
                        <i class="fas fa-exclamation-circle"></i> Необработанные
                    </a>
                    <?php if (!empty($unprocessed ?? [])): ?>
                        <a href="<?= Url::to(['index','status'=>$status, 'q'=>$q ?? '']) ?>" class="btn btn-sm btn-link">Скрыть</a>
                    <?php endif; ?>
                </div>
                <div class="col text-end small text-muted">
                    agg_daily_summary 30д → OpenRouter → wb_seo_recommendation
                </div>
            </form>
        </div>
    </div>

    <?php if (Yii::$app->request->get('unprocessed') !== null): ?>
    <div class="card mt-3 mb-3 border-warning">
        <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
            <span><i class="fas fa-exclamation-circle text-warning"></i> Необработанные<?= !empty($q) ? ' по “'.Html::encode($q).'”' : '' ?> — <?= count($unprocessed ?? []) ?> шт <?= !empty($q) ? '(фильтр по запросу)' : '(топ по продажам 30д, без рекомендации 14д)' ?></span>
            <?php if (!empty($unprocessed)): ?><button class="btn btn-sm btn-warning" id="seo-process-unprocessed" data-all='<?= Html::encode(json_encode(array_column($unprocessed,'nmID'))) ?>'><i class="fas fa-robot"></i> Обработать показанные</button><?php endif; ?>
        </div>
        <?php if (empty($unprocessed)): ?>
            <div class="p-3 text-muted">Необработанных<?= !empty($q) ? ' по “'.Html::encode($q).'”' : '' ?> не найдено — все уже в обработке (анти-спам <?= Yii::$app->params['seoAntiSpamDays'] ?? 14 ?>д).</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0" style="font-size:12px">
                <thead class="table-light"><tr><th style="width:50px">Фото</th><th>Товар</th><th>Продажи 30д</th><th style="width:170px"></th></tr></thead>
                <tbody>
                <?php foreach ($unprocessed as $c): ?>
                    <?php
                      // корректное получение фото через WbCard если json кривой
                      $img = '/images/no-photo.png';
                      try {
                        $cardModel = \app\models\WbCard::findOne(['nmID'=>$c['nmID']]);
                        if ($cardModel) { $pa = $cardModel->getPhotosArray(); if(!empty($pa[0])) $img = $pa[0]; }
                        else {
                          $photos = $c['photos'] ? (is_string($c['photos'])?json_decode($c['photos'],true):$c['photos']) : [];
                          if(is_array($photos) && !empty($photos[0]) && is_string($photos[0])) $img = $photos[0];
                        }
                      } catch(\Throwable $e) {}
                    ?>
                    <tr data-nmid="<?= $c['nmID'] ?>">
                        <td><?= Html::img($img, ['style'=>'width:50px;height:66px;object-fit:cover;border-radius:4px']) ?></td>
                        <td>
                            <div class="fw-semibold"><?= Html::encode($c['title']) ?></div>
                            <div class="small text-muted">nmID <?= $c['nmID'] ?> • <?= Html::encode($c['subjectName'] ?? '') ?> • <?= Html::encode($c['brand'] ?? '') ?> • <?= Html::encode($c['vendorCode'] ?? '') ?></div>
                        </td>
                        <td class="text-end"><?= (int)($c['total_qnt'] ?? 0) ?></td>
                        <td class="text-center" style="white-space:nowrap">
                            <button class="btn btn-sm btn-outline-primary seo-process-one" data-nmid="<?= $c['nmID'] ?>" style="white-space:nowrap"><i class="fas fa-robot"></i> Обработать с AI</button>
                            <div class="small text-muted seo-one-status mt-1" style="white-space:normal"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">Топ необработанных по <code>agg_daily_summary 30д</code> • Кнопка обработает видимые (до 20) с ротацией моделей.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php
$columns = [];

// 1. Карточка компактно (как feed-aggregated) + кнопки + админ внизу
$columns[] = [
    'label' => 'Товар',
    'format' => 'raw',
    'headerOptions' => ['style' => 'width:240px'],
    'contentOptions' => ['style' => 'width:240px; vertical-align:top; padding:0'],
    'value' => function($m) use ($isAdmin) {
        $card = $m->card;
        $photos = $card ? $card->getPhotosArray() : [];
        $img = !empty($photos[0]) ? $photos[0] : '/images/no-photo.png';
        $photoTag = Html::img($img, ['style'=>'width:50px;height:66px;object-fit:cover;border-radius:4px;flex-shrink:0','alt'=>'']);
        $title = Html::tag('div', Html::encode($card ? mb_substr($card->title ?? '',0,60) : ''), [
            'class'=>'cart-item-title','style'=>'white-space:normal;font-size:12px;line-height:1.2;max-width:170px','title'=>Html::encode($card->title ?? '')]);
        $subj = Html::tag('div', Html::encode(($card->subjectName ?? '') . ' • ' . ($card->brand ?? '')), ['class'=>'cart-item-details','style'=>'font-size:10px;color:#888']);
        $vendor = Html::tag('div', Html::encode($card->vendorCode ?? ''), ['class'=>'cart-item-details','style'=>'font-size:10px;color:#888']);
        $wbLink = Html::a('WB:'.$m->nmID, ['/wb/detail','DPFilterForm'=>['nm_id'=>$m->nmID]], ['target'=>'_blank','data-pjax'=>0,'style'=>'font-size:11px']);
        $phrasesLink = Html::a('фразы', ['/wb-search/card','DPFilterForm'=>['nm_id'=>$m->nmID]], ['target'=>'_blank','data-pjax'=>0,'style'=>'font-size:11px;margin-left:6px']);
        $textBlock = Html::tag('div', $title . $subj . $vendor . Html::tag('div', $wbLink.$phrasesLink), ['style'=>'min-width:0']);
        $top = Html::tag('div', $photoTag . $textBlock, ['style'=>'display:flex;gap:8px;align-items:flex-start']);
        $btnView = Html::a('<i class="fas fa-eye"></i>', ['view','id'=>$m->id], ['class'=>'btn btn-xs btn-outline-primary','title'=>'Открыть','style'=>'padding:2px 6px;font-size:11px']);
        $btnOk = $m->status==='new'
            ? Html::a('<i class="fas fa-check"></i>', ['mark-viewed','id'=>$m->id], ['class'=>'btn btn-xs btn-success','title'=>'Просмотрено','data-method'=>'post','data-confirm'=>'Отметить как просмотрено?','style'=>'padding:2px 6px;font-size:11px'])
            : '<span class="badge bg-success" style="font-size:10px">просмотрено</span>';
        $btnRe = Html::a('<i class="fas fa-undo"></i>', ['requeue','id'=>$m->id], ['class'=>'btn btn-xs '.($m->status==='viewed'?'btn-warning':'btn-outline-warning'),'title'=>'Вернуть в обработку','data-method'=>'post','style'=>'padding:2px 6px;font-size:11px']);
        $btns = Html::tag('div', $btnView.' '.$btnOk.' '.$btnRe, ['style'=>'margin-top:6px;display:flex;gap:4px;flex-wrap:wrap']);
        $idBadge = Html::tag('div', '#'.$m->id.' • '.Yii::$app->formatter->asDate($m->created_at,'php:d.m H:i'), ['style'=>'font-size:10px;color:#999;margin-top:4px']);
        $main = $top . $btns . $idBadge;

        // админ блок прижат к низу ячейки
        $admin = '';
        if ($isAdmin) {
            $model = Html::encode($m->model ?? '-');
            $pt = $m->prompt_tokens ?? '—';
            $ct = $m->completion_tokens ?? '—';
            $conf = $m->confidence !== null ? round($m->confidence*100).'%' : '—';
            $admin = Html::tag('div',
                "<div style='border-top:1px solid #eee;margin-top:8px;padding-top:6px;font-size:10px;line-height:1.3;color:#666;word-break:break-all'>"
                . "<div>$model</div><div>conf: $conf • prompt: $pt • compl: $ct</div></div>",
                ['style'=>'margin-top:auto']
            );
        }
        // flex-колонка чтобы admin был внизу
        return Html::tag('div', $main . $admin, ['style'=>'display:flex;flex-direction:column;height:100%;min-height:160px;padding:8px']);
    }
];

// 2. Rationale — полностью
$columns[] = [
    'label' => 'Rationale',
    'format' => 'raw',
    'headerOptions' => ['style' => 'width:220px'],
    'contentOptions' => ['style' => 'width:220px;white-space:normal;font-size:11.5px;line-height:1.3;vertical-align:top'],
    'value' => function($m){
        $text = Html::encode($m->rationale ?? '');
        // риски если есть
        $raw = $m->raw_json ? json_decode($m->raw_json, true) : null;
        $risks = $raw['response']['risks'] ?? $raw['risks'] ?? null;
        if (!$risks) {
            // пробуем из raw_content парс
            $tmp = json_decode($m->raw_json, true);
            // risks может быть в parsed
        }
        return "<div style='white-space:pre-wrap'>$text</div>";
    }
];

// 3. Старое: заголовок + описание
$columns[] = [
    'label' => 'Старое',
    'format' => 'raw',
    'headerOptions' => ['style' => 'width:280px'],
    'contentOptions' => ['style' => 'width:280px;white-space:normal;vertical-align:top'],
    'value' => function($m){
        $oldTitle = Html::encode($m->old_title ?? '');
        $oldDesc = Html::encode($m->old_description ?? '');
        $lenT = mb_strlen($m->old_title ?? '');
        $lenD = mb_strlen($m->old_description ?? '');
        $html = "<div class='small text-muted'>Заголовок <span class='badge bg-light text-dark'>$lenT</span></div><div style='font-size:11.5px;background:#f8f9fa;border:1px solid #eee;border-radius:4px;padding:4px 6px;margin-bottom:6px;white-space:normal'>$oldTitle</div>";
        $short = mb_substr($oldDesc,0,500);
        $more = mb_strlen($oldDesc) > 500 ? ' … <a href="'.Url::to(['view','id'=>$m->id]).'" class="small">весь →</a>' : '';
        $html .= "<div class='small text-muted'>Описание <span class='badge bg-light text-dark'>$lenD</span></div><div style='font-size:11.5px;white-space:pre-wrap;line-height:1.3;background:#f8f9fa;border:1px solid #eee;border-radius:4px;padding:6px;max-height:280px;overflow:auto'>$short$more</div>";
        // бейдж фраз для списка
        $raw = $m->raw_json ? json_decode($m->raw_json, true) : null;
        $cnt = count($raw['prompt']['top_phrases_by_clicks'] ?? []);
        if ($cnt===0) $cnt = count($raw['prompt']['phrases_with_orders'] ?? []);
        $html .= "<div class='small text-muted mt-1'>фраз: $cnt" . ($cnt===0 ? " <span class='badge bg-warning text-dark'>нет данных</span>" : "") . "</div>";
        return $html;
    }
];

// 4. Новое: заголовок + описание
$columns[] = [
    'label' => 'Новое',
    'format' => 'raw',
    'headerOptions' => ['style' => 'width:280px'],
    'contentOptions' => ['style' => 'width:280px;white-space:normal;vertical-align:top'],
    'value' => function($m){
        $newTitle = Html::encode($m->new_title ?? '');
        $newDesc = Html::encode($m->new_description ?? '');
        $lenT = mb_strlen($m->new_title ?? '');
        $lenD = mb_strlen($m->new_description ?? '');
        $html = "<div class='small text-success'>Заголовок <span class='badge bg-success'>$lenT</span></div><div style='font-size:11.5px;background:#e8f5e9;border:1px solid #c8e6c9;border-radius:4px;padding:4px 6px;margin-bottom:6px;font-weight:500;white-space:normal'>$newTitle</div>";
        $short = mb_substr($newDesc,0,500);
        $more = mb_strlen($newDesc) > 500 ? ' … <a href="'.Url::to(['view','id'=>$m->id]).'" class="small">весь →</a>' : '';
        $html .= "<div class='small text-success'>Описание <span class='badge bg-success'>$lenD</span></div><div style='font-size:11.5px;white-space:pre-wrap;line-height:1.3;background:#e8f5e9;border:1px solid #c8e6c9;border-radius:4px;padding:6px;max-height:280px;overflow:auto'>$short$more</div>";
        $raw2 = $m->raw_json ? json_decode($m->raw_json, true) : null;
        $cnt2 = count($raw2['prompt']['top_phrases_by_clicks'] ?? []);
        $html .= $cnt2===0 ? "<div class='small text-danger'>фраз нет — проверь</div>" : "";
        return $html;
    }
];

// 5. Keywords
$columns[] = [
    'label' => 'Ключи',
    'format' => 'raw',
    'headerOptions' => ['style' => 'width:160px'],
    'contentOptions' => ['style' => 'width:160px;white-space:normal;vertical-align:top;font-size:11px'],
    'value' => function($m){
        $added = $m->keywords_added ? json_decode($m->keywords_added, true) : [];
        $removed = $m->keywords_removed ? json_decode($m->keywords_removed, true) : [];
        $h = '';
        if ($added) {
            $h .= "<div class='small text-success'>+ добавлены</div><div style='margin-bottom:6px'>".implode(', ', array_map(fn($v)=>'<span class="badge bg-success" style="font-weight:400;margin:1px">'.Html::encode($v).'</span>', $added))."</div>";
        } else {
            $h .= "<div class='small text-muted'>+ нет</div>";
        }
        if ($removed) {
            $h .= "<div class='small text-danger'>− удалены</div><div>".implode(', ', array_map(fn($v)=>'<span class="badge bg-light text-dark" style="margin:1px">'.Html::encode($v).'</span>', $removed))."</div>";
        }
        return $h ?: '<span class="text-muted">—</span>';
    }
];

// админ колонка удалена — теперь внутри Товар внизу (см. выше)
?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class'=>'table table-bordered table-hover align-middle seo-table'],
    'columns' => $columns,
    'summary' => 'Показаны записи {begin}-{end} из {totalCount}',
    'emptyText' => 'Нет рекомендаций. Запусти <code>php yii seo/analyze --limit=20</code>',
]) ?>

<?php if ($status === 'new' && $dataProvider->getTotalCount() === 0 && !empty($q)): ?>
<div class="card mt-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span>Карточки по запросу “<?= Html::encode($q) ?>” — <?= $cardsProvider ? $cardsProvider->getTotalCount() : 0 ?> шт (показано <?= count($cards) ?>)</span>
        <?php if (!empty($cards)): ?><button class="btn btn-sm btn-primary" id="seo-process-all" data-all='<?= Html::encode(json_encode($allIds ?? [])) ?>'><i class="fas fa-robot"></i> Обработать все (<?= $cardsProvider ? $cardsProvider->getTotalCount() : 0 ?>)</button><?php endif; ?>
    </div>
    <?php if (empty($cards)): ?>
        <div class="p-3 text-muted">Карточек не найдено по “<?= Html::encode($q) ?>”. Попробуй другой запрос (поиск по title / vendorCode / subjectName).</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0" style="font-size:12px">
            <thead class="table-light"><tr><th style="width:50px">Фото</th><th>Товар</th><th style="width:170px"></th></tr></thead>
            <tbody>
            <?php foreach ($cards as $c): ?>
                <?php $photos = $c->getPhotosArray(); $img = $photos[0] ?? '/images/no-photo.png'; ?>
                <tr data-nmid="<?= $c->nmID ?>">
                    <td><?= Html::img($img, ['style'=>'width:50px;height:66px;object-fit:cover;border-radius:4px']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= Html::encode($c->title) ?></div>
                        <div class="small text-muted">nmID <?= $c->nmID ?> • <?= Html::encode($c->subjectName) ?> • <?= Html::encode($c->brand) ?> • <?= Html::encode($c->vendorCode) ?></div>
                    </td>
                    <td class="text-center" style="width:170px;white-space:nowrap">
                        <button class="btn btn-sm btn-outline-primary seo-process-one" data-nmid="<?= $c->nmID ?>" style="white-space:nowrap"><i class="fas fa-robot"></i> Обработать с AI</button>
                        <div class="small text-muted seo-one-status mt-1" style="white-space:normal"></div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Нажми “Обработать с AI” на строке или “Обработать все” — создаст рекомендации в фоне (ротация моделей).</div>
    <?php endif; ?>
</div>

<!-- popup для массовой -->
<div class="modal fade" id="seoBatchModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-robot"></i> Обработка</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center py-3">
        <div class="spinner-border text-primary mb-2" role="status" id="seo-batch-spinner"></div>
        <div id="seo-batch-status" class="small">Старт...</div>
        <div class="progress mt-2" style="height:6px"><div id="seo-batch-progress" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div></div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-danger btn-sm" id="seo-batch-abort"><i class="fas fa-stop"></i> Прервать</button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Свернуть (фоном)</button>
      </div>
      <div class="small text-muted px-3 pb-2">Прервать — остановит после текущего nmID. Свернуть — продолжит в фоне. Перезагрузка страницы тоже прервёт.</div>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
.seo-table th { font-size:11px; font-weight:600; color:#333; vertical-align:middle; text-align:center; background:#fafafa; }
.seo-table td { font-size:12px; }
.cart-item-title { font-weight:600; color:#2c3e50; }
.cart-item-details { color:#666; }
.seo-table .btn-xs { line-height:1; }
</style>
</div>

<?php // подсветка длины заголовка
$js = <<<JS
\$(document).on('click', '.seo-table tr', function(e){
  if(\$(e.target).closest('a,button').length) return;
  \$(this).toggleClass('table-active');
});
JS;
$this->registerJs($js);

$processUrl = \yii\helpers\Url::to(['process']);
$csrf = Yii::$app->request->csrfToken;
$js2 = <<<JS
function seoProcessOne(nmID, btn){
  if(btn){ btn.disabled=true; var st = btn.closest('tr')?.querySelector('.seo-one-status'); if(st) st.innerHTML='<span class="spinner-border spinner-border-sm text-primary"></span> отправляю...'; }
  var modalEl = document.getElementById('seoBatchModal');
  // одиночный — без попапа, только inline-статус + спиннер; батч показывает попап
  var isBatch = btn && btn.id === 'seo-process-all';
  var modal = null, statusEl=null, progEl=null;
  if(isBatch){
    modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    statusEl = document.getElementById('seo-batch-status');
    progEl = document.getElementById('seo-batch-progress');
    if(modal){ statusEl.textContent = 'nmID '+nmID+' — отправляю к AI...'; progEl.style.width='30%'; modal.show(); }
  }
  return fetch('$processUrl', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
    body:'_csrf=$csrf&nmID='+encodeURIComponent(nmID)
  }).then(r=>r.json()).then(data=>{
    if(isBatch && modal) try{ modal.hide(); }catch(e){}
    if(btn && !isBatch){
      var st2 = btn.closest('tr')?.querySelector('.seo-one-status');
      if(data.success){
        if(st2) st2.innerHTML = '<span class="text-success">OK #'+data.id+'</span> <a href="'+data.url+'" class="small">открыть</a>';
        btn.innerHTML = '<i class="fas fa-check"></i> Готово';
        btn.classList.remove('btn-outline-primary'); btn.classList.add('btn-success');
      } else {
        if(st2) st2.innerHTML = '<span class="text-danger">'+(data.error||'error')+'</span>';
        btn.disabled=false;
      }
    } else if(isBatch){
      // для батча — обработка в next()
    } else {
      if(data.success) window.location.href = data.url;
      else alert(data.error||'Ошибка');
    }
    return data;
  }).catch(e=>{
    if(isBatch && modal) try{ modal.hide(); }catch(e2){}
    if(btn && !isBatch){ var st3=btn.closest('tr')?.querySelector('.seo-one-status'); if(st3) st3.textContent=e.message; btn.disabled=false; }
    else if(!isBatch) alert(e.message);
  });
}
\$(document).on('click', '.seo-process-one', function(e){
  e.preventDefault();
  seoProcessOne(this.dataset.nmid, this);
});
var seoBatchAbort = false;
$('#seo-process-unprocessed').on('click', function(){
  var allIds = [];
  try{ allIds = JSON.parse(document.getElementById('seo-process-unprocessed').getAttribute('data-all') || '[]'); }catch(e){ allIds=[]; }
  if(!allIds.length) return;
  var modalEl = document.getElementById('seoBatchModal');
  var modal = new bootstrap.Modal(modalEl);
  var statusEl = document.getElementById('seo-batch-status');
  var progEl = document.getElementById('seo-batch-progress');
  seoBatchAbort=false;
  var abortBtn=document.getElementById('seo-batch-abort'); abortBtn.disabled=false; abortBtn.innerHTML='<i class="fas fa-stop"></i> Прервать';
  modal.show();
  var i=0;
  function nextU(){
    if(seoBatchAbort){ statusEl.textContent='Прервано: '+i+'/'+allIds.length; setTimeout(function(){try{modal.hide();}catch(e){}},600); return; }
    if(i>=allIds.length){ statusEl.textContent='Готово: '+allIds.length+' шт'; progEl.style.width='100%'; document.getElementById('seo-batch-spinner').style.display='none'; abortBtn.style.display='none'; setTimeout(function(){location.reload();},800); return; }
    statusEl.textContent='Обрабатываю '+(i+1)+'/'+allIds.length+' nmID '+allIds[i]+'...';
    progEl.style.width=Math.round((i/allIds.length)*100)+'%';
    seoProcessOne(allIds[i], null).then(function(){ i++; setTimeout(nextU,600); });
  }
  nextU();
});
\$('#seo-process-all').on('click', function(){
  var allIds = [];
  try{ allIds = JSON.parse(this.getAttribute('data-all') || '[]'); }catch(e){ allIds=[]; }
  var total = allIds.length || document.querySelectorAll('.seo-process-one').length;
  if(!total) return;
  seoBatchAbort = false;
  var modalEl = document.getElementById('seoBatchModal');
  var modal = new bootstrap.Modal(modalEl);
  var statusEl = document.getElementById('seo-batch-status');
  var progEl = document.getElementById('seo-batch-progress');
  var abortBtn = document.getElementById('seo-batch-abort');
  abortBtn.disabled = false; abortBtn.innerHTML = '<i class="fas fa-stop"></i> Прервать';
  modal.show();
  var i=0;
  var mapBtn = {};
  document.querySelectorAll('.seo-process-one').forEach(function(b){ mapBtn[b.dataset.nmid]=b; });
  abortBtn.onclick = function(){ seoBatchAbort = true; abortBtn.disabled=true; abortBtn.innerHTML='Прервано'; statusEl.textContent='Прервано на '+(i)+'/'+allIds.length; };
  function next(){
    if(seoBatchAbort){
      statusEl.textContent = 'Прервано: '+i+'/'+allIds.length;
      progEl.style.width = Math.round((i/allIds.length)*100)+'%';
      setTimeout(function(){ try{ modal.hide(); }catch(e){} }, 600);
      return;
    }
    if(i>=allIds.length){
      statusEl.textContent = 'Готово: '+allIds.length+' шт';
      progEl.style.width='100%';
      document.getElementById('seo-batch-spinner').style.display='none';
      abortBtn.style.display='none';
      setTimeout(function(){ location.reload(); }, 800);
      return;
    }
    var nmID = allIds[i];
    var b = mapBtn[nmID] || null;
    statusEl.textContent = 'Обрабатываю '+(i+1)+'/'+allIds.length+' nmID '+nmID+'...';
    progEl.style.width = Math.round((i/allIds.length)*100)+'%';
    seoProcessOne(nmID, b).then(function(){ i++; setTimeout(next, 600); });
  }
  next();
});
modalEl = document.getElementById('seoBatchModal');
if(modalEl){
  modalEl.addEventListener('hidden.bs.modal', function(){ seoBatchAbort = true; });
}
JS;
$this->registerJs($js2);
?>
