<?php
use yii\helpers\Html;

$detail = $group['detail'];
$compCard = $group['card'];
$images = [];
if ($detail && !empty($detail['images'])) {
    $images = json_decode($detail['images'], true) ?: [];
}
$hasAnalysis = $group['has_analysis'];
?>
<div class="card mb-4" id="comp-result-<?= $compNmId ?>">
    <div class="card-body p-0">
        <div class="row g-0">
            <!-- Левая колонка: информация о конкуренте -->
            <div class="col-md-4 border-end">
                <div class="p-2 d-flex flex-row align-items-stretch">
                    <div class="text-center w-auto">
                        <?php if (!empty($images)): ?>
                            <img src="<?= Html::encode($images[0]) ?>" alt="Фото конкурента" style="max-width:100%;max-height:200px;object-fit:contain;border-radius:6px;background:#f8f9fa;">
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1" style="font-size:15px;">
                            <a href="https://www.wildberries.ru/catalog/<?= $compNmId ?>/detail.aspx" target="_blank" class="text-decoration-none text-dark">
                                <?= Html::encode($detail['title'] ?? ($compCard['title'] ?? "nmID {$compNmId}")) ?>
                                <i class="fas fa-external-link-alt ms-1" style="font-size:10px;color:#999;"></i>
                            </a>
                            <span class="badge bg-secondary ms-1" style="font-size:10px;"><?= mb_strlen($detail['title'] ?? $compCard['title'] ?? '') ?> симв.</span>
                        </h6>
                        
                        <div class="d-flex flex-row justify-content-between align-items-end mb-1">
                            <div class="small text-muted mb-2"><?= Html::encode($detail['brand'] ?? $compCard['brand'] ?? '') ?></div>

                            <div class="mt-2">
                                <?php if ($group['analysis_id']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success btn-analyze-single"
                                        data-id="<?= $group['analysis_id'] ?>" data-nmid="<?= $compNmId ?>">
                                        <i class="fas fa-robot"></i> <?= $hasAnalysis ? 'Повторить AI' : 'AI анализ' ?>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-analyze-direct"
                                        data-nmid="<?= $compNmId ?>" data-source="<?= $sourceNmId ?? '' ?>">
                                        <i class="fas fa-robot"></i> AI анализ
                                    </button>
                                <?php endif; ?>
                        </div>

                        </div>

                    <!-- Кнопка AI (всегда) -->

                        <?php if ($compCard): ?>
                        <table class="table table-sm table-borderless mb-0" style="font-size:12px;">
                            <tr>
                                <td class="text-muted" style="width:40%;">Цена</td>
                                <td><b><?= $compCard['price'] ? number_format($compCard['price'] / 100, 0, '', ' ') . ' ₽' : '—' ?></b></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Рейтинг</td>
                                <td>
                                    <?php if ($compCard['rating']): ?>
                                        <span class="text-warning">★</span> <?= number_format($compCard['rating'], 1) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Отзывы</td>
                                <td><?= $compCard['feedbacks'] ?? '—' ?></td>
                            </tr>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-2 p-2">
                    <?php if ($detail && !empty($detail['description'])): ?>
                    <div class="small text-muted mb-1">Описание конкурента: <b><?= mb_strlen($detail['description']) ?> симв.</b></div>
                    <div class="mt-1 small" style="max-height:250px;overflow-y:auto;font-size:13px;color:#333;">
                        <?= Html::encode($detail['description']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mb-2 p-2">


                    <!-- Фразы и позиции (только если есть в analysis) -->
                    <?php if (!empty($group['items'])): ?>
                    <div class="mt-2">
                        <div class="small fw-bold text-muted mb-1">Фразы конкурента:</div>
                        <?php foreach ($group['items'] as $item): ?>
                            <span class="badge bg-light text-dark me-1 mb-1" style="font-size:14px;">
                                <?= Html::encode($item->query_phrase) ?>
                                <span class="text-primary">#<?= $item->position ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Правая колонка: результат ИИ -->
            <div class="col-md-8">
                <div class="p-3" id="ai-result-<?= $compNmId ?>">
                    <?php if ($hasAnalysis): ?>
                        <?php
                        $firstItem = $group['items'][0];
                        $aiResult = $firstItem->ai_result;
                        if (is_string($aiResult)) {
                            $r = json_decode($aiResult, true);
                            if (!is_array($r) && preg_match('/\{.*\}/s', $aiResult, $mm)) $r = json_decode($mm[0], true);
                            if (!is_array($r)) $r = ['raw' => $aiResult];
                        } else {
                            $r = $aiResult;
                        }
                        // если пришёл {"raw":"{\"competitor_better\":...}"} — разворачиваем
                        if (isset($r['raw']) && count($r) === 1 && is_string($r['raw'])) {
                            $tmp = json_decode($r['raw'], true);
                            if (!is_array($tmp) && preg_match('/\{.*\}/s', $r['raw'], $mm)) $tmp = json_decode($mm[0], true);
                            if (is_array($tmp)) $r = $tmp;
                        }
                        $hasRec = !empty($r['competitor_better']) || !empty($r['we_better']) || !empty($r['recommendations']);
                        ?>
                        <?php if ($hasRec): ?>
                            <?php if (!empty($r['competitor_better'])): ?>
                                <div class="mb-3">
                                    <h6 class="text-danger"><i class="fas fa-arrow-up"></i> Лучше у конкурента:</h6>
                                    <ul class="mb-0" style="font-size:12px;">
                                        <?php foreach ($r['competitor_better'] as $v): ?>
                                            <li><?= Html::encode($v) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($r['we_better'])): ?>
                                <div class="mb-3">
                                    <h6 class="text-success"><i class="fas fa-arrow-down"></i> Лучше у нас:</h6>
                                    <ul class="mb-0" style="font-size:12px;">
                                        <?php foreach ($r['we_better'] as $v): ?>
                                            <li><?= Html::encode($v) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($r['recommendations'])): ?>
                                <div class="mb-0">
                                    <h6 class="text-primary"><i class="fas fa-lightbulb"></i> Рекомендации:</h6>
                                    <ul class="mb-0" style="font-size:12px;">
                                        <?php foreach ($r['recommendations'] as $v): ?>
                                            <li><?= Html::encode($v) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 mb-0" style="font-size:12px;">
                                <i class="fas fa-info-circle"></i> Анализ выполнен, но рекомендаций нет. Попробуйте <a href="#" class="alert-link btn-reanalyze" data-id="<?= $group['analysis_id'] ?>" data-nmid="<?= $compNmId ?>">повторить AI</a>.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-muted small">Ещё не проанализировано</div>
                    <?php endif; ?>


                </div>
            </div>
        </div>
    </div>
</div>
