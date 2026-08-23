<?php
/** @var array $items */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var int|null $nmId */
/** @var string $type */
/** @var float $totalSum */
/** @var int $totalCount */

$typeName = $type === 'shf' ? 'Штрафы' : 'Удержания';
$themeColor = $type === 'shf' ? '#e74c3c' : '#e67e22'; // Красный для штрафов, оранжевый для удержаний
?>

<div class="detail-popup-wrapper" style="padding: 10px 5px;">
    
    <div class="row  text-center" style="display: flex; align-items: center; flex-wrap: wrap; margin-bottom: 20px;">

        <div class="alert alert-danger col-4" style="background-color: #fdf6f5; border-color: #f9e2df; color: <?= $themeColor ?>; border-radius: 6px; margin-bottom: 25px;">
            <span style="font-size: 16px;">
                <i class="glyphicon glyphicon-exclamation-sign"></i> 
                <strong>Детализация: <?= $typeName ?></strong> (<?= date('d.m.Y', strtotime($dateFrom)) ?> — <?= date('d.m.Y', strtotime($dateTo)) ?>)
            </span>
            <?php if ($nmId): ?>
                <span class="pull-right" style="font-size: 16px;">
                    <strong>nmID:</strong> <span class="label label-default" style="background-color: #2c3e50;"><?= htmlspecialchars($nmId) ?></span>
                </span>
            <?php endif; ?>
        </div>

            <div class="col-4" style="margin-bottom: 15px;">
                <div style="background: #ffffff; border: 1px solid #e3e7eb; border-top: 3px solid <?= $themeColor ?>; border-radius: 6px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
                    <div style="font-size: 28px; font-weight: bold; color: <?= $themeColor ?>; line-height: 1.2;">
                        <?= number_format($totalSum, 2, '.', ' ') ?> ₽
                    </div>
                    <div style="font-size: 12px; text-transform: uppercase; color: #7f8c8d; margin-top: 5px; font-weight: 600;">Общая сумма</div>
                </div>
            </div>
            <div class="col-4" style="margin-bottom: 15px;">
                <div style="background: #ffffff; border: 1px solid #e3e7eb; border-top: 3px solid #34495e; border-radius: 6px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
                    <div style="font-size: 28px; font-weight: bold; color: #2c3e50; line-height: 1.2;">
                        <?= $totalCount ?>
                    </div>
                    <div style="font-size: 12px; text-transform: uppercase; color: #7f8c8d; margin-top: 5px; font-weight: 600;">Количество записей</div>
                </div>
            </div>
        </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover" style="background: #ffffff; border: 1px solid #e3e7eb;">
            <thead>
                <tr style="background-color: #f8f9fa; color: #34495e;">
                    <th style="padding: 12px 8px; width: 130px; vertical-align: middle;">Дата</th>
                    <?php if (!$nmId): ?>
                        <th style="padding: 12px 8px; width: 180px; vertical-align: middle;">Товар</th>
                    <?php endif; ?>
                    <th style="padding: 12px 8px; width: 120px; text-align: center; vertical-align: middle;">Сумма</th>
                    <th style="padding: 12px 8px; vertical-align: middle;">Обоснование / Описание</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td style="padding: 12px 8px; color: #7f8c8d; vertical-align: middle; font-size: 12px;">
                                    <?= date('d.m.Y H:i', strtotime($item['row_date'])) ?>
                            </td>
                            <?php if (!$nmId): ?>
                                <td style="padding: 12px 8px; vertical-align: middle; font-size: 13px;">
                                    <div style="font-weight: bold; color: #2c3e50;"><?= $item['nmID'] ?></div>
                                    <div style="font-size: 11px; color: #7f8c8d; max-width: 160px; line-height: 1.2; word-wrap: break-word;">
                                        <?= htmlspecialchars($item['card_title'] ?: 'Название не найдено') ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                            <td style="padding: 12px 8px; text-align: center; vertical-align: middle; font-size: 14px; font-weight: bold; color: <?= $themeColor ?>;">
                                <?= number_format($item['amount'], 2, '.', ' ') ?> ₽
                            </td>
                            <td style="padding: 12px 8px; color: #34495e; font-size: 13px; vertical-align: middle;">
                                <?= htmlspecialchars($item['reason'] ?: '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $nmId ? 3 : 4 ?>" class="text-center" style="padding: 30px; color: #95a5a6; font-style: italic;">
                            Данные за указанный период отсутствуют.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>