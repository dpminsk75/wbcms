<?php
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array $results */

$this->title = 'Тестирование генерации автоответов';
$this->params['breadcrumbs'][] = ['label' => 'Автоответы', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wb-reply-rules-test-generation">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= Html::encode($this->title) ?></h1>
        <?= Html::a('<i class="fa fa-list"></i> Вернуться к правилам', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <div class="alert alert-warning">
        <strong>Внимание:</strong> На этой странице отображается тестовая сборка ответов для 100 последних отзывов из вашей базы данных. Никакие ответы на Wildberries сейчас <strong>не отправляются</strong>.
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th style="width: 25%;">Товар / Отзыв</th>
                    <th style="width: 10%; text-align: center;">Оценка</th>
                    <th style="width: 20%;">Сработавшее правило</th>
                    <th style="width: 45%;">Сгенерированный текст ответа</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">В таблице `wb_feedbacks` не найдено отзывов для генерации.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $item): ?>
                        <?php 
                        $fb = $item['feedback'];
                        $rule = $item['matched_rule'];
                        $text = $item['generated_text'];
//                        $rating = (int)($fb['valuation'] ?? $fb['rating'] ?? 5);
                        $rating = (int)($fb['productValuation'] ?? 5);

                        switch ($rating) {
                            case 5:
                                $badgeColor = '#198754'; // Насыщенный зеленый
                                break;
                            case 4:
                                $badgeColor = '#ffc107'; // Зеленый (чуть мягче)
                                break;
                            case 3:
                                $badgeColor = '#6c757d'; // Желтый / Оранжевый (внимание)
                                break;
                            case 2:
                            case 1:
                                $badgeColor = '#dc3545'; // Красный (критический)
                                break;
                            default:
                                $badgeColor = '#6c757d'; // Серый дефолт, если что-то пошло не так
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-primary">[<?= Html::encode($fb['nmID'] ?? '—') ?>] <?= Html::encode($fb['card_title'] ?? 'Неизвестный товар') ?></div>
                                <div class="small text-muted mb-2">Бренд: <strong><?= Html::encode($fb['card_brand'] ?? '—') ?></strong></div>
                                <div class="small text-muted mb-2">Имя: <strong><?= Html::encode($fb['userName'] ?? '—') ?></strong></div>
                                
                                <div class="p-2 border rounded bg-white small">
                                    <span class="fw-bold text-secondary"><?= Html::encode($fb['user_name'] ?? $fb['wb_user_name'] ?? 'Покупатель') ?>:</span>
                                    <span class="text-dark"><?= !empty($fb['text']) ? Html::encode($fb['text']) : '<em>(Отзыв без текста)</em>' ?></span>
                                </div>
                            </td>
                            
                            <td class="text-center">
                                <span class="badge d-inline-flex align-items-center justify-content-center" 
                                      style="background-color: <?= $badgeColor ?>; color: #fff; font-size: 14px; padding: 5px 10px; border-radius: 4px; font-weight: bold; min-width: 45px;">
                                    <?= $rating ?> ★
                                </span>
                            </td>
                            
                            <td>
                                <?php if ($rule): ?>
                                    <div class="fw-bold"><?= Html::encode($rule->title) ?></div>
                                    <div class="mt-1">
                                        <?php if ($rule->rule_type === 'general'): ?>
                                            <span class="badge bg-success" style="background-color: #e6f7ed; color: #1e7e34;">Общее</span>
                                        <?php elseif ($rule->rule_type === 'brand'): ?>
                                            <span class="badge bg-info text-dark" style="background-color: #e1f5fe;">По бренду</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark" style="background-color: #fff8e1;">По товару</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-danger small"><i class="fa fa-exclamation-triangle"></i> Правило не найдено</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if ($rule): ?>
                                    <div class="p-2 rounded border bg-light" style="font-size: 13px; font-family: sans-serif; background-color: #fafafa; border-left: 4px solid #28a745 !important;">
                                        <?= Html::encode($text) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small italic" style="color: #999;">Ответ пропущен, так как ни одно из правил не соответствует параметрам (оценка, наличие текста, бренд).</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>