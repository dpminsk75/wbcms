<?php
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\icons\Icon;
Icon::map($this);

$this->title = 'Конкуренты — nmID ' . $nmId;
$this->params['breadcrumbs'][] = ['label' => 'Конкуренты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$csrf = Yii::$app->request->csrfToken;
$removeUrl = Url::to(['remove']);
$analyzeUrl = Url::to(['analyze']);
$summaryUrl = '/competitor/summary';
?>
<div class="competitor-selected">
    <h1><?= Html::encode($this->title) ?> <span class="badge bg-secondary"><?= count($competitors) ?></span>
        <?php if (!empty($positionMax)): ?>
            <small class="text-muted">| позиция ≤ <?= $positionMax ?></small>
        <?php endif; ?>
    </h1>

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

    <?php if (empty($competitors)): ?>
        <div class="alert alert-warning">Нет конкурентов для выбранных фраз.</div>
    <?php else: ?>

    <div class="d-flex gap-2 mb-3">
        <button type="button" class="btn btn-success" id="btn-analyze-all"><i class="fas fa-robot"></i> AI-анализ всех отмеченных</button>
        <button type="button" class="btn btn-outline-primary" id="btn-summary"><i class="fas fa-lightbulb"></i> Общая рекомендация</button>
        <button type="button" class="btn btn-outline-secondary" id="btn-select-all"><i class="fas fa-check-double"></i> Отметить все</button>
        <a href="<?= Url::to(['results', 'nm_id' => $nmId]) ?>" class="btn btn-outline-secondary"><i class="fas fa-chart-bar"></i> Результаты</a>
        <a href="<?= Url::to(['select', 'nm_id' => $nmId]) ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Назад к фразам</a>
    </div>

    <div class="row" id="competitors-list">
    <?php foreach ($competitors as $comp): ?>
        <?php
            $detail = $details[$comp['nm_id']] ?? null;
            $compTitle = $detail['title'] ?? $comp['title'] ?? '—';
            $compDesc = $detail['description'] ?? '';
            $compBrand = $detail['brand'] ?? $comp['brand'] ?? '';
        ?>
        <div class="col-md-6 col-lg-4 mb-3" id="comp-<?= $comp['nm_id'] ?>">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-start gap-2">
                        <?php
                            $analysisRecord = $analysisMap[$comp['nm_id']] ?? null;
                            $isAnalyzed = !empty($analysisRecord['ai_result']);
                        ?>
                        <input type="checkbox" class="comp-check mt-1" value="<?= $analysisRecord['id'] ?? 0 ?>"
                            <?= !$isAnalyzed ? 'checked' : '' ?>
                            data-nmid="<?= $comp['nm_id'] ?>"
                            style="width:18px;height:18px;cursor:pointer;">
                        <div>
                            <small class="text-muted">nmID <?= $comp['nm_id'] ?> | <?= Html::encode($compBrand) ?></small>
                            <div class="fw-bold" style="font-size:13px;"><?= Html::encode($compTitle) ?></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove" data-nmid="<?= $comp['nm_id'] ?>" title="Удалить">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="card-body" style="font-size:12px;">
                    <?php if ($compDesc): ?>
                        <div class="text-muted mb-2" style="max-height:80px; overflow:hidden;">
                            <?= Html::encode(mb_substr($compDesc, 0, 300)) ?>...
                        </div>
                    <?php endif; ?>

                    <div class="mb-1"><b>Фразы и позиции:</b></div>
                    <?php foreach ($comp['queries'] as $q): ?>
                        <span class="badge bg-light text-dark me-1 mb-1">
                            <?= Html::encode($q['phrase']) ?>
                            <span class="text-primary">#<?= $q['position'] ?></span>
                        </span>
                    <?php endforeach; ?>
                    <div class="small text-muted mt-1">Всего фраз: <?= count($comp['queries']) ?></div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-success btn-analyze"
                        data-id="<?= $analysisRecord['id'] ?? 0 ?>"
                        <?= (!$detail ? 'disabled title="Нет данных конкурента"' : '') ?>>
                        <i class="fas fa-robot"></i> AI
                    </button>
                    <?php if (!$detail): ?>
                        <span class="text-danger small">⚠ нет данных</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- Результат AI-анализа (модалка) -->
    <div class="modal fade" id="aiResultModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-robot"></i> AI-анализ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="ai-result-body">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                        <div class="mt-2">Анализирую...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
(function(){
    var csrf = '<?= $csrf ?>';

    // Отметить все
    var selectAllBtn = document.getElementById('btn-select-all');
    if(selectAllBtn){
        selectAllBtn.addEventListener('click', function(){
            var checks = document.querySelectorAll('.comp-check');
            var allChecked = true;
            checks.forEach(function(c){ if(!c.checked) allChecked = false; });
            checks.forEach(function(c){ c.checked = !allChecked; });
            this.innerHTML = allChecked ? '<i class="fas fa-square"></i> Снять все' : '<i class="fas fa-check-double"></i> Отметить все';
        });
    }

    // Удаление конкурента
    document.querySelectorAll('.btn-remove').forEach(function(btn){
        btn.addEventListener('click', function(){
            var nmid = this.dataset.nmid;
            if(!confirm('Удалить конкурента nmID '+nmid+'?')) return;
            fetch('<?= $removeUrl ?>', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                body:'_csrf='+csrf+'&nm_id='+nmid+'&source_nm_id=<?= $nmId ?>'
            }).then(function(r){return r.json()}).then(function(d){
                if(d.success){
                    var el = document.getElementById('comp-'+nmid);
                    if(el) el.remove();
                } else alert(d.error||'Ошибка');
            });
        });
    });

    // AI-анализ одного конкурента
    document.querySelectorAll('.btn-analyze').forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = this.dataset.id;
            if(!id) return;
            var modal = new bootstrap.Modal(document.getElementById('aiResultModal'));
            document.getElementById('ai-result-body').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><div class="mt-2">Анализирую...</div></div>';
            modal.show();
            fetch('<?= $analyzeUrl ?>', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                body:'_csrf='+csrf+'&id='+id
            }).then(function(r){return r.json()}).then(function(d){
                if(d.success){
                    renderResult(d.result);
                } else {
                    document.getElementById('ai-result-body').innerHTML = '<div class="alert alert-danger">'+(d.error||'Ошибка')+'</div>';
                }
            }).catch(function(e){
                document.getElementById('ai-result-body').innerHTML = '<div class="alert alert-danger">Ошибка сети</div>';
            });
        });
    });

    // AI-анализ всех — поштучно с прогрессом, только отмеченные чекбоксом
    var analyzeAllBtn = document.getElementById('btn-analyze-all');
    if(analyzeAllBtn){
        analyzeAllBtn.addEventListener('click', function(){
            var checked = document.querySelectorAll('.comp-check:checked');
            if(!checked.length){ alert('Отметь конкурентов для анализа'); return; }
            var total = checked.length, done = 0, errors = 0;
            var modal = new bootstrap.Modal(document.getElementById('aiResultModal'));
            var body = document.getElementById('ai-result-body');
            body.innerHTML = '<div class="d-flex align-items-center gap-2 mb-3"><div class="spinner-border spinner-border-sm text-primary"></div><span id="ai-progress-text">0 / '+total+'</span></div><div class="progress mb-3" style="height:6px"><div class="progress-bar" id="ai-progress-bar" style="width:0%"></div></div><div id="ai-log" style="max-height:400px;overflow-y:auto;font-size:12px;"></div>';
            modal.show();
            var log = document.getElementById('ai-log');

            function addLog(text, cls){
                var d = document.createElement('div');
                d.className = cls || '';
                d.textContent = text;
                log.appendChild(d);
                log.scrollTop = log.scrollHeight;
            }

            function next(i){
                if(i >= checked.length){
                    addLog('═══════════════════', 'text-muted');
                    addLog('Готово! Проанализировано: '+done+(errors ? ' Ошибок: '+errors : ''), 'fw-bold text-success');
                    // Сводная рекомендация
                    addLog('', '');
                    addLog('Запрашиваю общую рекомендацию...', 'text-muted');
            fetch('<?= $summaryUrl ?>', {
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                        body:'_csrf='+csrf+'&nm_id=<?= $nmId ?>'
                    }).then(function(r){return r.json()}).then(function(d){
                        if(d.success && d.result){
                            addLog('═══ ОБЩАЯ РЕКОМЕНДАЦИЯ ═══', 'fw-bold text-primary mt-2');
                            var r = d.result;
                            if(typeof r === 'string'){ try{ r = JSON.parse(r); }catch(e){ r = {raw:r}; } }
                            // Распаковка двойной обёртки {"raw":"{...}"}
                            if(r && r.raw && !r.title && !r.description && !r.competitor_better && Object.keys(r).length===1){
                                var inner = r.raw; if(typeof inner==='string'){try{inner=JSON.parse(inner);}catch(e){}}
                                if(inner && typeof inner==='object') r = inner;
                            }
                            if(r.raw){ addLog(r.raw, ''); return; }
                            if(r.title_recommendations && r.title_recommendations.length){
                                addLog('Заголовок:', 'fw-bold text-danger mt-1');
                                r.title_recommendations.forEach(function(t){ addLog('  • '+t, ''); });
                            }
                            if(r.description_recommendations && r.description_recommendations.length){
                                addLog('Описание:', 'fw-bold text-danger mt-1');
                                r.description_recommendations.forEach(function(t){ addLog('  • '+t, ''); });
                            }
                            if(r.priority_actions && r.priority_actions.length){
                                addLog('Приоритетные действия:', 'fw-bold text-danger mt-1');
                                r.priority_actions.forEach(function(t){ addLog('  • '+t, ''); });
                            }
                        } else {
                            addLog('Не удалось получить сводку: '+(d.error||''), 'text-danger');
                        }
                    }).catch(function(){
                        addLog('Ошибка сети при запросе сводки', 'text-danger');
                    });
                    return;
                }
                var id = checked[i].value;
                var nmid = checked[i].dataset.nmid;
                document.getElementById('ai-progress-text').textContent = (i+1)+' / '+total+' — nmID '+nmid;
                document.getElementById('ai-progress-bar').style.width = Math.round((i/total)*100)+'%';
                addLog((i+1)+'/'+total+' nmID '+nmid+' — отправляю к AI...', 'text-muted');
                fetch('<?= $analyzeUrl ?>', {
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                    body:'_csrf='+csrf+'&id='+id
                }).then(function(r){return r.json()}).then(function(d){
                    if(d.success){
                        done++;
                        addLog((i+1)+'/'+total+' nmID '+nmid+' — ✓ готово', 'text-success');
                    } else {
                        errors++;
                        addLog((i+1)+'/'+total+' nmID '+nmid+' — ✗ '+(d.error||'Ошибка'), 'text-danger');
                    }
                    next(i+1);
                }).catch(function(){
                    errors++;
                    addLog((i+1)+'/'+total+' nmID '+nmid+' — ✗ ошибка сети', 'text-danger');
                    next(i+1);
                });
            }
            next(0);
        });
    }

    // Общая рекомендация — отдельный вызов
    var summaryBtn = document.getElementById('btn-summary');
    if(summaryBtn){
        summaryBtn.addEventListener('click', function(){
            var modal = new bootstrap.Modal(document.getElementById('aiResultModal'));
            var body = document.getElementById('ai-result-body');
            body.innerHTML = '<div class="d-flex align-items-center gap-2 mb-3"><div class="spinner-border spinner-border-sm text-primary"></div><span>Формирую сводную рекомендацию...</span></div><div id="ai-log" style="max-height:400px;overflow-y:auto;font-size:12px;"></div>';
            modal.show();
            var log = document.getElementById('ai-log');

            function loadSummary(force){
                fetch('<?= $summaryUrl ?>', {
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                    body:'_csrf='+csrf+'&nm_id=<?= $nmId ?>'+'&force='+(force?1:0)
                }).then(function(r){
                    if(!r.ok) throw new Error('HTTP '+r.status);
                    return r.text();
                }).then(function(text){
                    try { var d = JSON.parse(text); } catch(e) {
                        body.innerHTML = '<div class="alert alert-danger">Сервер вернул не JSON:<br><pre style="font-size:11px;white-space:pre-wrap;">'+escapeHtml(text.substring(0,1000))+'</pre></div>';
                        return;
                    }
                    if(d.success && d.result){
                        var r = d.result;
                        if(typeof r === 'string'){ try{ r = JSON.parse(r); }catch(e){ r = {raw:r}; } }
                        if(r && r.raw && !r.title && !r.description && Object.keys(r).length===1){
                            var inner = r.raw; if(typeof inner==='string'){try{inner=JSON.parse(inner);}catch(e){}}
                            if(inner && typeof inner==='object') r = inner;
                        }
                        var html = '';
                        if(d.cached){
                            var cacheInfo2 = '<i class="fas fa-database"></i> Из кэша ('+d.competitor_count+' конкурентов)';
                            if(d.cached_at) cacheInfo2 += ' · ' + escapeHtml(d.cached_at.substring(0,16).replace('T',' '));
                            if(d.cached_model) cacheInfo2 += ' · ' + escapeHtml(d.cached_model);
                            var recalcBtn2 = d.can_recalc ? '<button class="btn btn-sm btn-outline-primary ms-2" id="btn-summary-force">Пересчитать</button>' : '<span class="badge bg-secondary ms-2" title="Пересчёт доступен админу до 30 дней">только админ</span>';
                            html += '<div class="alert alert-info py-1 mb-2 d-flex align-items-center flex-wrap" style="font-size:11px">' + cacheInfo2 + ' ' + recalcBtn2 + '</div>';
                        }

                        // Отладка: показываем сырой ответ AI
                        if(d.raw_ai_response){
                            html += '<details class="mb-2" style="font-size:11px"><summary class="text-muted" style="cursor:pointer"><i class="fas fa-bug"></i> Сырой ответ AI (отладка)</summary><pre style="white-space:pre-wrap;max-height:300px;overflow-y:auto;background:#f8f9fa;padding:8px;border-radius:4px;">'+escapeHtml(d.raw_ai_response)+'</pre></details>';
                        }

                        // Отладка: что пришло в result
                        html += '<details class="mb-2" style="font-size:11px"><summary class="text-muted" style="cursor:pointer"><i class="fas fa-code"></i> result object (отладка)</summary><pre style="white-space:pre-wrap;max-height:200px;overflow-y:auto;background:#f8f9fa;padding:8px;border-radius:4px;">'+escapeHtml(JSON.stringify(r,null,2))+'</pre></details>';

                        // Готовый заголовок
                        if(r.title){
                            html += '<div class="mb-3 p-2 border rounded" style="background:#f8f9fa">';
                            html += '<div class="d-flex justify-content-between align-items-center mb-1"><b class="text-danger"><i class="fas fa-heading"></i> Готовый заголовок:</b>';
                            html += '<button class="btn btn-sm btn-outline-success copy-btn" data-text="'+escapeHtml(r.title)+'"><i class="fas fa-copy"></i> Копировать</button></div>';
                            html += '<div style="font-size:13px">'+escapeHtml(r.title)+'</div></div>';
                        }

                        // Готовое описание
                        if(r.description){
                            html += '<div class="mb-3 p-2 border rounded" style="background:#f8f9fa">';
                            html += '<div class="d-flex justify-content-between align-items-center mb-1"><b class="text-danger"><i class="fas fa-align-left"></i> Готовое описание:</b>';
                            html += '<button class="btn btn-sm btn-outline-success copy-btn" data-text="'+escapeHtml(r.description)+'"><i class="fas fa-copy"></i> Копировать</button></div>';
                            html += '<div style="font-size:12px;white-space:pre-wrap;max-height:300px;overflow-y:auto">'+escapeHtml(r.description)+'</div></div>';
                        }

                        // Рекомендации
                        if(r.title_recommendations && r.title_recommendations.length){
                            html += '<h6 class="text-danger"><i class="fas fa-lightbulb"></i> Заголовок — рекомендации:</h6><ul class="mb-2">';
                            r.title_recommendations.forEach(function(t){ html+='<li>'+escapeHtml(t)+'</li>'; });
                            html += '</ul>';
                        }
                        if(r.description_recommendations && r.description_recommendations.length){
                            html += '<h6 class="text-danger"><i class="fas fa-lightbulb"></i> Описание — рекомендации:</h6><ul class="mb-2">';
                            r.description_recommendations.forEach(function(t){ html+='<li>'+escapeHtml(t)+'</li>'; });
                            html += '</ul>';
                        }
                        if(r.priority_actions && r.priority_actions.length){
                            html += '<h6 class="text-danger"><i class="fas fa-exclamation-triangle"></i> Приоритетные действия:</h6><ul class="mb-0">';
                            r.priority_actions.forEach(function(t){ html+='<li>'+escapeHtml(t)+'</li>'; });
                            html += '</ul>';
                        }
                        body.innerHTML = html || '<pre style="white-space:pre-wrap;">'+escapeHtml(JSON.stringify(r,null,2))+'</pre>';

                        // Копирование в буфер
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
                            body.innerHTML = '<div class="d-flex align-items-center gap-2 mb-3"><div class="spinner-border spinner-border-sm text-primary"></div><span>Пересчитываю...</span></div>';
                            loadSummary(true);
                        });
                    } else {
                        body.innerHTML = '<div class="alert alert-danger">'+escapeHtml(d.error||'Ошибка')+'</div>';
                    }
                }).catch(function(e){
                    body.innerHTML = '<div class="alert alert-danger">Ошибка: '+escapeHtml(e.message)+'</div>';
                });
            }
            loadSummary(false);
        });
    }

    function renderResult(result){
        document.getElementById('ai-result-body').innerHTML = renderResultHtml(result);
    }

    function renderResultHtml(result){
        if(typeof result === 'string'){ try{ result = JSON.parse(result); }catch(e){ result = {raw:result}; } }
        if(result && result.raw && !result.competitor_better && !result.we_better && !result.recommendations && Object.keys(result).length===1){
            var inner = result.raw; if(typeof inner==='string'){try{inner=JSON.parse(inner);}catch(e){}}
            if(inner && typeof inner==='object') result = inner;
        }
        if(result.raw) return '<pre style="white-space:pre-wrap;font-size:12px;">'+escapeHtml(result.raw)+'</pre>';
        var html = '';
        if(result.competitor_better && result.competitor_better.length){
            html += '<h6 class="text-danger"><i class="fas fa-arrow-up"></i> Лучше у конкурента:</h6><ul>';
            result.competitor_better.forEach(function(i){ html+='<li>'+escapeHtml(i)+'</li>'; });
            html += '</ul>';
        }
        if(result.we_better && result.we_better.length){
            html += '<h6 class="text-success"><i class="fas fa-arrow-down"></i> Лучше у нас:</h6><ul>';
            result.we_better.forEach(function(i){ html+='<li>'+escapeHtml(i)+'</li>'; });
            html += '</ul>';
        }
        if(result.recommendations && result.recommendations.length){
            html += '<h6 class="text-primary"><i class="fas fa-lightbulb"></i> Рекомендации:</h6><ul>';
            result.recommendations.forEach(function(i){ html+='<li>'+escapeHtml(i)+'</li>'; });
            html += '</ul>';
        }
        return html || '<pre>'+escapeHtml(JSON.stringify(result,null,2))+'</pre>';
    }

    function escapeHtml(t){
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(t));
        return d.innerHTML;
    }
})();
</script>
