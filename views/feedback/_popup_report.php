<?php
/** @var array $stats */
/** @var array $feedbacks */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var int|null $nmId */
?>

<div class="feedback-popup-wrapper" style="padding: 10px 5px;">
    <div class="row text-center mb-1">
        <div class="alert alert-info col-4" style="background-color: #f4f9f8; border-color: #d1eae5; color: #16a085; border-radius: 6px; margin-bottom: 25px;">
            <span style="font-size: 16px; display: block;">
                <i class="glyphicon glyphicon-calendar"></i> 
                Период анализа: <strong><?= date('d.m.Y', strtotime($dateFrom)) ?> — <?= date('d.m.Y', strtotime($dateTo)) ?></strong>
            </span>
            <?php if ($nmId): ?>
                <span class="pull-right" style="font-size: 16px; display: block;">
                    Артикул (nmID): <strong><span class="label label-primary"><?= htmlspecialchars($nmId) ?></strong></span>
                </span>
            <?php endif; ?>
        </div>

        <div class="col-4" style="margin-bottom: 15px;">
            <div style="background: #ffffff; border: 1px solid #e3e7eb; border-top: 3px solid #3498db; border-radius: 6px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
                <div style="font-size: 28px; font-weight: bold; color: #2c3e50; line-height: 1.2;">
                    <?= number_format($stats['total_feedbacks'] ?? 0, 0, '.', ' ') ?>
                </div>
                <div style="font-size: 12px; text-transform: uppercase; color: #7f8c8d; margin-top: 5px; font-weight: 600; letter-spacing: 0.5px;">Всего отзывов</div>
            </div>
        </div>
        
        <div class="col-4" style="margin-bottom: 15px;">
            <div style="background: #ffffff; border: 1px solid #e3e7eb; border-top: 3px solid #f1c40f; border-radius: 6px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
                <div style="font-size: 28px; font-weight: bold; color: #f39c12; line-height: 1.2;">
                    <?= number_format($stats['avg_valuation'] ?? 0, 2) ?> <span style="font-size: 22px;">★</span>
                </div>
                <div style="font-size: 12px; text-transform: uppercase; color: #7f8c8d; margin-top: 5px; font-weight: 600; letter-spacing: 0.5px;">Средняя оценка</div>
            </div>
        </div>
<!--
        <div class="col-xs-12 col-sm-4" style="margin-bottom: 15px;">
            <div style="background: #ffffff; border: 1px solid #e3e7eb; border-top: 3px solid #16a085; border-radius: 6px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
                <div style="font-size: 28px; font-weight: bold; color: #16a085; line-height: 1.2;">
                    <?= number_format($stats['new_count'] ?? 0, 0, '.', ' ') ?>
                </div>
                <div style="font-size: 12px; text-transform: uppercase; color: #7f8c8d; margin-top: 5px; font-weight: 600; letter-spacing: 0.5px;">Новых отзывов</div>
            </div>
        </div>
-->
    </div>
<!--
    <h4 style="font-weight: bold; color: #2c3e50; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #ecf0f1;">
        <i class="glyphicon glyphicon-list-alt"></i> Детальные данные по отзывам
    </h4>
-->
<?php /*
    <div style="margin-bottom: 10px; font-size: 12px; color: #7f8c8d;">
        <span style="display: inline-block; width: 12px; height: 12px; background-color: #fff9e6; border: 1px solid #ffeeba; margin-right: 5px; vertical-align: middle; border-radius: 2px;"></span>
        Подсвечены платные отзывы (`is_pay > 0`)
    </div>
*/ ?>
    <div class="table-responsive">
        <table class="table table-bordered" style="background: #ffffff; border: 1px solid #e3e7eb;">
            <thead>
                <tr style="background-color: #f8f9fa; color: #34495e;">
                    <th style="font-size: 12px; padding: 12px 8px; width: 110px; vertical-align: middle;">Дата</th>
                    <?php if (!$nmId): ?>
                        <th style="font-size: 12px; padding: 12px 8px; width: 300px; vertical-align: middle;">Товар</th>
                    <?php endif; ?>
                    <th style="font-size: 12px; padding: 12px 8px; width: 70px; text-align: center; vertical-align: middle;">Оценка</th>
                    <th style="font-size: 12px; padding: 12px 8px; width: 120px; vertical-align: middle;">Пользователь</th>
                    <th style="font-size: 12px; padding: 12px 8px; width: 90px; text-align: center; vertical-align: middle;">Стоимость</th>
                    <th style="font-size: 12px; padding: 12px 8px; vertical-align: middle;">Содержимое отзыва</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($feedbacks)): ?>
                    <?php foreach ($feedbacks as $fb): ?>
                        <?php 
                            // Проверяем, платный ли отзыв для подсветки строки
                            $isPaid = isset($fb['is_pay']) && $fb['is_pay'] > 0;
                            $rowStyle = $isPaid ? 'background-color: #fff9e6; font-weight: 500;' : '';
                        ?>
                        <?php
                            $valuation = (int)$fb['productValuation']; // Замените на вашу переменную оценки, если имя отличается
                            
                            // Задаем цвет в зависимости от оценки
                            if ($valuation === 4) {
                                $color = '#2980b9'; // Синий цвет
                            } elseif ($valuation <= 3) {
                                $color = '#c0392b'; // Красный цвет
                            } else {
                                $color = '#333'; 
                            }
                        ?>
                        <tr style="<?= $rowStyle ?> transition: background 0.2s;">
                            <td style="padding: 12px 8px; color: <?= $color ?>; vertical-align: middle; font-size: 12px;">
                                <?= date('d.m.Y H:i', strtotime($fb['createdDate'])) ?>
                            </td>

                            <?php if (!$nmId): ?>
                                <td style="padding: 12px 8px; vertical-align: middle; font-size: 13px;">
                                    <?php if (!empty($fb['card_title'])): ?>
                                        <div style="font-size: 11px; color: <?= $color ?>; max-width: 280px; line-height: 1.2; word-wrap: break-word;" title="<?= htmlspecialchars($fb['card_title']) ?>">
                                            <?= htmlspecialchars($fb['card_title']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 11px; color: #bdc3c7; font-style: italic;">Название не найдено</div>
                                    <?php endif; ?>
                                    <div style="font-size: 11px; font-weight: bold; color: #2c3e50; margin-bottom: 3px;">
                                        <?= $fb['nmID'] ?>
                                    </div>
                                </td>
                            <?php endif; ?>

                            <td style="padding: 12px 8px; text-align: center; vertical-align: middle;">
                                <span style="font-size: 11px; padding: .3em .6em .3em; color: <?= $color ?>;">
                                    <?= $fb['productValuation'] ?> ★
                                </span>
                            </td>
                            
                            <td style="padding: 12px 8px; color: #2c3e50; vertical-align: middle; font-size: 13px;">
                                <?= htmlspecialchars($fb['userName'] ?: 'Аноним') ?>
                            </td>

                            <td style="padding: 12px 8px; text-align: center; vertical-align: middle; font-size: 13px;">
                                <?php if (!empty($fb['f_cost'])): ?>
                                    <strong style="color: #e67e22;"><?= number_format($fb['f_cost'], 0, '.', ' ') ?></strong>
                                    <span style="font-size: 11px; color: #7f8c8d;">₽/б</span>
                                <?php else: ?>
                                    <span style="color: #bdc3c7;">0</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="padding: 12px 8px; color: #34495e; font-size: 13px; max-width: 450px; word-wrap: break-word;">
                                <?php if (!empty($fb['text'])): ?>
                                    <div style="margin-bottom: 8px; line-height: 1.4;">
                                        <?= htmlspecialchars($fb['text']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($fb['pros'])): ?>
                                    <div style="margin-top: 5px; padding-left: 5px; border-left: 3px solid #2ecc71; font-size: 12px; background: rgba(46, 204, 113, 0.05); padding: 4px 8px;">
                                        <strong style="color: #27ae60;"><i class="glyphicon glyphicon-plus-sign"></i> Плюсы:</strong> 
                                        <?= htmlspecialchars($fb['pros']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($fb['cons'])): ?>
                                    <div style="margin-top: 5px; padding-left: 5px; border-left: 3px solid #e74c3c; font-size: 12px; background: rgba(231, 76, 60, 0.05); padding: 4px 8px;">
                                        <strong style="color: #c0392b;"><i class="glyphicon glyphicon-minus-sign"></i> Минусы:</strong> 
                                        <?= htmlspecialchars($fb['cons']) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (empty($fb['text']) && empty($fb['pros']) && empty($fb['cons'])): ?>
                                    <span style="color: #bdc3c7; font-style: italic;">Оценка без текста</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $nmId ? 5 : 6 ?>" class="text-center" style="padding: 30px; color: #95a5a6; font-style: italic;">
                            За указанный период отзывов не обнаружено.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>