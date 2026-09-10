<?php
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Результаты анализа — nmID ' . $nmId;
$this->params['breadcrumbs'][] = ['label' => 'Конкуренты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Группировка по конкуренту (из анализа)
$grouped = [];
foreach ($items as $item) {
    $nid = $item->competitor_nm_id;
    if (!isset($grouped[$nid])) {
        $grouped[$nid] = [
            'detail' => $compDetails[$nid] ?? null,
            'card' => $compCards[$nid] ?? null,
            'items' => [],
            'has_analysis' => false,
            'analysis_id' => null,
        ];
    }
    $grouped[$nid]['items'][] = $item;
    if (!empty($item->ai_result)) {
        $grouped[$nid]['has_analysis'] = true;
    }
    if (!$grouped[$nid]['analysis_id']) {
        $grouped[$nid]['analysis_id'] = $item->id;
    }
}

// Конкуренты без анализа (из wb_competitor_cards, но нет в wb_competitor_analysis)
$unanalyzed = [];
foreach ($allCompNmIds as $nmIdComp) {
    if (!isset($grouped[$nmIdComp])) {
        $unanalyzed[$nmIdComp] = [
            'detail' => $compDetails[$nmIdComp] ?? null,
            'card' => $compCards[$nmIdComp] ?? null,
            'items' => [],
            'has_analysis' => false,
            'analysis_id' => null,
        ];
    }
}

// Определяем has_recommendations для сортировки и скрываем чушь при пустом recommendations
foreach ($grouped as $gid => &$gg) {
    $gg['has_recommendations'] = false;
    if ($gg['has_analysis'] && !empty($gg['items'][0]->ai_result)) {
        $ai = $gg['items'][0]->ai_result;
        $rr = is_string($ai) ? json_decode($ai, true) : $ai;
        if (!is_array($rr) && is_string($ai) && preg_match('/\{.*\}/s', $ai, $mm)) $rr = json_decode($mm[0], true);
        if (isset($rr['raw']) && is_string($rr['raw'])) {
            $tmp = json_decode($rr['raw'], true);
            if (!is_array($tmp) && preg_match('/\{.*\}/s', $rr['raw'], $mm)) $tmp = json_decode($mm[0], true);
            if (is_array($tmp)) $rr = $tmp;
        }
        if (!empty($rr['competitor_better']) || !empty($rr['we_better']) || !empty($rr['recommendations'])) $gg['has_recommendations'] = true;
    }
}
unset($gg);
// Сортировка: 1) с рекомендациями по алфавиту, 2) без рекомендаций по алфавиту
uasort($grouped, function($a, $b) {
    $tierA = $a['has_recommendations'] ? 0 : ($a['has_analysis'] ? 1 : 2);
    $tierB = $b['has_recommendations'] ? 0 : ($b['has_analysis'] ? 1 : 2);
    if ($tierA !== $tierB) return $tierA <=> $tierB;
    $ta = mb_strtolower($a['detail']['title'] ?? $a['card']['title'] ?? (string)$a['items'][0]->competitor_nm_id ?? '');
    $tb = mb_strtolower($b['detail']['title'] ?? $b['card']['title'] ?? (string)$b['items'][0]->competitor_nm_id ?? '');
    return $ta <=> $tb;
});
// unanalyzed тоже по алфавиту
uasort($unanalyzed, function($a, $b) {
    $ta = mb_strtolower($a['detail']['title'] ?? $a['card']['title'] ?? '');
    $tb = mb_strtolower($b['detail']['title'] ?? $b['card']['title'] ?? '');
    return $ta <=> $tb;
});

$csrf = Yii::$app->request->csrfToken;
$analyzeUrl = Url::to(['analyze']);
$analyzeDirectUrl = Url::to(['analyze-direct']);
$summaryUrl = Url::to(['summary']);
?>

<div class="competitor-results">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($card): ?>
    <div class="card mb-3">
        <div class="card-body">
            <div><b><?= Html::encode($card['title'] ?? '') ?></b> <span class="badge bg-secondary" style="font-size:10px;"><?= mb_strlen($card['title'] ?? '') ?> симв.</span></div>
            <div class="small text-muted">nmID <?= $nmId ?> | <?= Html::encode($card['brand'] ?? '') ?> | Описание: <?= mb_strlen($card['description'] ?? '') ?> симв.</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2 mb-3">
        <a href="<?= Url::to(['select', 'nm_id' => $nmId]) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Новый анализ</a>
        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-summary"><i class="fas fa-lightbulb"></i> Общая рекомендация</button>
    </div>

    <?php if (empty($grouped) && empty($unanalyzed)): ?>
        <div class="alert alert-info">Нет конкурентов для отображения.</div>
    <?php else: ?>

        <?php if (!empty($grouped)): ?>
            <?php foreach ($grouped as $compNmId => $group): ?>
                <?= $this->render('_result_card', [
                    'compNmId' => $compNmId,
                    'group' => $group,
                    'csrf' => $csrf,
                    'analyzeUrl' => $analyzeUrl,
                    'sourceNmId' => $nmId,
                ]) ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($unanalyzed)): ?>
            <div class="mt-4 mb-2">
                <h6 class="text-muted"><i class="fas fa-list"></i> Ещё конкуренты (без анализа) — <?= count($unanalyzed) ?></h6>
            </div>
            <?php foreach ($unanalyzed as $compNmId => $group): ?>
                <?= $this->render('_result_card', [
                    'compNmId' => $compNmId,
                    'group' => $group,
                    'csrf' => $csrf,
                    'analyzeUrl' => $analyzeUrl,
                    'sourceNmId' => $nmId,
                ]) ?>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Модалка для сводной рекомендации -->
<div class="modal fade" id="summaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-lightbulb"></i> Общая рекомендация</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="summary-body">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <div class="mt-2">Формирую сводку...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var csrf = '<?= $csrf ?>';

    // Сводная рекомендация
    var summaryBtn = document.getElementById('btn-summary');
    if(summaryBtn){
        summaryBtn.addEventListener('click', function(){
            var modal = new bootstrap.Modal(document.getElementById('summaryModal'));
            var body = document.getElementById('summary-body');
            body.innerHTML = '<div class="d-flex align-items-center gap-2 mb-3"><div class="spinner-border spinner-border-sm text-primary"></div><span>Формирую сводную рекомендацию...</span></div><div id="ai-log" style="max-height:500px;overflow-y:auto;font-size:12px;"></div>';
            modal.show();
            var log = document.getElementById('ai-log');

            function loadSummary(force){
                fetch('<?= $summaryUrl ?>', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                    body: '_csrf=' + csrf + '&nm_id=<?= $nmId ?>' + '&force=' + (force ? 1 : 0)
                }).then(function(r){
                    if(!r.ok) throw new Error('HTTP ' + r.status);
                    return r.text();
                }).then(function(text){
                    try { var d = JSON.parse(text); } catch(e) {
                        body.innerHTML = '<div class="alert alert-danger">Сервер вернул не JSON:<br><pre style="font-size:11px;white-space:pre-wrap;">' + escHtml(text.substring(0, 1000)) + '</pre></div>';
                        return;
                    }
                    if(d.success && d.result){
                        var r = d.result;
                        if(typeof r === 'string'){ try{ r = JSON.parse(r); }catch(e){ r = {raw:r}; } }
                        if(r && r.raw && !r.title && !r.description && Object.keys(r).length === 1){
                            var inner = r.raw; if(typeof inner === 'string'){ try{inner = JSON.parse(inner);}catch(e){} }
                            if(inner && typeof inner === 'object') r = inner;
                        }
                        var html = '';
                        if(d.cached){
                            var cacheInfo = '<i class="fas fa-database"></i> Из кэша (' + d.competitor_count + ' конкурентов)';
                            if(d.cached_at) cacheInfo += ' · ' + escHtml(d.cached_at.substring(0,16).replace('T',' '));
                            if(d.cached_model) cacheInfo += ' · ' + escHtml(d.cached_model);
                            var recalcBtn = d.can_recalc ? '<button class="btn btn-sm btn-outline-primary ms-2" id="btn-summary-force">Пересчитать</button>' : '<span class="badge bg-secondary ms-2" title="Пересчёт доступен админу до 30 дней">только админ</span>';
                            html += '<div class="alert alert-info py-1 mb-2 d-flex align-items-center flex-wrap" style="font-size:11px">' + cacheInfo + ' ' + recalcBtn + '</div>';
                        }

                        if(r.title){
                            html += '<div class="mb-3 p-2 border rounded" style="background:#f8f9fa">';
                            html += '<div class="d-flex justify-content-between align-items-center mb-1"><b class="text-danger"><i class="fas fa-heading"></i> Готовый заголовок:</b>';
                            html += '<button class="btn btn-sm btn-outline-success copy-btn" data-text="' + escHtml(r.title) + '"><i class="fas fa-copy"></i> Копировать</button></div>';
                            html += '<div style="font-size:13px">' + escHtml(r.title) + '</div></div>';
                        }

                        if(r.description){
                            html += '<div class="mb-3 p-2 border rounded" style="background:#f8f9fa">';
                            html += '<div class="d-flex justify-content-between align-items-center mb-1"><b class="text-danger"><i class="fas fa-align-left"></i> Готовое описание:</b>';
                            html += '<button class="btn btn-sm btn-outline-success copy-btn" data-text="' + escHtml(r.description) + '"><i class="fas fa-copy"></i> Копировать</button></div>';
                            html += '<div style="font-size:12px;white-space:pre-wrap;max-height:300px;overflow-y:auto">' + escHtml(r.description) + '</div></div>';
                        }

                        if(r.title_recommendations && r.title_recommendations.length){
                            html += '<h6 class="text-danger"><i class="fas fa-lightbulb"></i> Заголовок — рекомендации:</h6><ul class="mb-2">';
                            r.title_recommendations.forEach(function(t){ html += '<li>' + escHtml(t) + '</li>'; });
                            html += '</ul>';
                        }
                        if(r.description_recommendations && r.description_recommendations.length){
                            html += '<h6 class="text-danger"><i class="fas fa-lightbulb"></i> Описание — рекомендации:</h6><ul class="mb-2">';
                            r.description_recommendations.forEach(function(t){ html += '<li>' + escHtml(t) + '</li>'; });
                            html += '</ul>';
                        }
                        if(r.priority_actions && r.priority_actions.length){
                            html += '<h6 class="text-danger"><i class="fas fa-exclamation-triangle"></i> Приоритетные действия:</h6><ul class="mb-0">';
                            r.priority_actions.forEach(function(t){ html += '<li>' + escHtml(t) + '</li>'; });
                            html += '</ul>';
                        }
                        body.innerHTML = html || '<pre style="white-space:pre-wrap;">' + escHtml(JSON.stringify(r, null, 2)) + '</pre>';

                        body.querySelectorAll('.copy-btn').forEach(function(btn){
                            btn.addEventListener('click', function(){
                                navigator.clipboard.writeText(this.dataset.text).then(function(){
                                    btn.innerHTML = '<i class="fas fa-check"></i> Скопировано';
                                    setTimeout(function(){ btn.innerHTML = '<i class="fas fa-copy"></i> Копировать'; }, 1500);
                                });
                            });
                        });

                        var forceBtn = document.getElementById('btn-summary-force');
                        if(forceBtn) forceBtn.addEventListener('click', function(){
                            body.innerHTML = '<div class="d-flex align-items-center gap-2 mb-3"><div class="spinner-border spinner-border-sm text-primary"></div><span>Пересчитываю...</span></div><div id="ai-log" style="max-height:500px;overflow-y:auto;font-size:12px;"></div>';
                            log = document.getElementById('ai-log');
                            loadSummary(true);
                        });
                    } else {
                        body.innerHTML = '<div class="alert alert-danger">' + escHtml(d.error || 'Ошибка') + '</div>';
                    }
                }).catch(function(e){
                    body.innerHTML = '<div class="alert alert-danger">Ошибка: ' + escHtml(e.message) + '</div>';
                });
            }
            loadSummary(false);
        });
    }

    function renderAiResult(r, container){
        var html = '';
        if(r.competitor_better && r.competitor_better.length){
            html += '<div class="mb-3"><h6 class="text-danger"><i class="fas fa-arrow-up"></i> Лучше у конкурента:</h6><ul class="mb-0" style="font-size:12px;">';
            r.competitor_better.forEach(function(v){ html += '<li>' + escHtml(v) + '</li>'; });
            html += '</ul></div>';
        }
        if(r.we_better && r.we_better.length){
            html += '<div class="mb-3"><h6 class="text-success"><i class="fas fa-arrow-down"></i> Лучше у нас:</h6><ul class="mb-0" style="font-size:12px;">';
            r.we_better.forEach(function(v){ html += '<li>' + escHtml(v) + '</li>'; });
            html += '</ul></div>';
        }
        if(r.recommendations && r.recommendations.length){
            html += '<div class="mb-0"><h6 class="text-primary"><i class="fas fa-lightbulb"></i> Рекомендации:</h6><ul class="mb-0" style="font-size:12px;">';
            r.recommendations.forEach(function(v){ html += '<li>' + escHtml(v) + '</li>'; });
            html += '</ul></div>';
        }
        container.innerHTML = html || '<div class="alert alert-warning py-2 mb-0" style="font-size:12px;">Анализ выполнен, но рекомендаций нет.</div>';
    }
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.btn-analyze-single');
        if(btn){
            var id = btn.dataset.id;
            var nmid = btn.dataset.nmid;
            var container = document.getElementById('ai-result-' + nmid);
            container.innerHTML = '<div class="d-flex align-items-center gap-2"><div class="spinner-border spinner-border-sm text-primary"></div><span class="small">Анализирую...</span></div>';
            fetch('<?= $analyzeUrl ?>', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                body: '_csrf=' + csrf + '&id=' + id
            }).then(function(r){ return r.json(); }).then(function(d){
                if(d.success){ renderAiResult(d.result, container); } else { container.innerHTML = '<div class="alert alert-danger py-1 mb-0" style="font-size:12px;">' + escHtml(d.error || 'Ошибка') + '</div>'; }
            }).catch(function(){ container.innerHTML = '<div class="alert alert-danger py-1 mb-0" style="font-size:12px;">Ошибка сети</div>'; });
            return;
        }
        var btn2 = e.target.closest('.btn-analyze-direct');
        if(btn2){
            var nmid2 = btn2.dataset.nmid;
            var source = btn2.dataset.source;
            var container2 = document.getElementById('ai-result-' + nmid2);
            if(container2) container2.innerHTML = '<div class="d-flex align-items-center gap-2"><div class="spinner-border spinner-border-sm text-primary"></div><span class="small">Анализирую...</span></div>';
            else btn2.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            fetch('<?= $analyzeDirectUrl ?>', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                body: '_csrf=' + csrf + '&source_nm_id=' + source + '&competitor_nm_id=' + nmid2
            }).then(function(r){ return r.json(); }).then(function(d){
                var cont = document.getElementById('ai-result-' + nmid2);
                if(d.success){ if(cont) renderAiResult(d.result, cont); else location.reload(); }
                else { if(cont) cont.innerHTML = '<div class="alert alert-danger py-1 mb-0" style="font-size:12px;">' + escHtml(d.error || 'Ошибка') + '</div>'; else alert(d.error || 'Ошибка'); }
            }).catch(function(){ var cont = document.getElementById('ai-result-' + nmid2); if(cont) cont.innerHTML = '<div class="alert alert-danger py-1 mb-0" style="font-size:12px;">Ошибка сети</div>'; });
            return;
        }
        var btn3 = e.target.closest('.btn-reanalyze');
        if(btn3){
            e.preventDefault();
            var id3 = btn3.dataset.id; var nmid3 = btn3.dataset.nmid;
            var c3 = document.getElementById('ai-result-' + nmid3);
            if(c3) c3.innerHTML = '<div class="d-flex align-items-center gap-2"><div class="spinner-border spinner-border-sm text-primary"></div><span class="small">Анализирую...</span></div>';
            fetch('<?= $analyzeUrl ?>', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                body: '_csrf=' + csrf + '&id=' + id3
            }).then(function(r){ return r.json(); }).then(function(d){
                var cont = document.getElementById('ai-result-' + nmid3);
                if(d.success){ if(cont) renderAiResult(d.result, cont); } else { if(cont) cont.innerHTML = '<div class="alert alert-danger py-1 mb-0" style="font-size:12px;">' + escHtml(d.error || 'Ошибка') + '</div>'; }
            });
        }
    });

    function escHtml(t){
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(t));
        return d.innerHTML;
    }
})();
</script>
