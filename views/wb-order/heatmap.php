<?php
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array|null $card */
/** @var array $matrix [7][24] ['cnt'=>int,'sum'=>float,'cancel_cnt'=>int] */
/** @var int $maxCnt */
/** @var int $totalCnt */
/** @var float $totalSum */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var array $params */
/** @var array $recommend ['byVolume'=>['best'=>arr,'top3'=>[]],'byAvg'=>...,'byReli'=>...] */

$this->title = 'Тепловая карта заказов 7×24';
$this->params['breadcrumbs'][] = ['label' => 'Заказы', 'url' => ['wb-order/feed']];
$this->params['breadcrumbs'][] = $this->title;

$days = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
$dayFull = ['Понедельник','Вторник','Среда','Четверг','Пятница','Суббота','Воскресенье'];
?>

<div class="wb-heatmap">
    <?php if ($card): ?>
        <?= \app\components\PageHeaderWidget::widget(['title' => $card['title'], 'nmId' => $card['nmID']]) ?>
    <?php else: ?>
        <h1><?= Html::encode($this->title) ?></h1>
        <p class="text-muted" style="font-size:13px;">Время добавления заказа по дням и часам — для расписания рекламы. Источник: <code>wb_order.date</code>.</p>
    <?php endif; ?>

    <div class="col-md-6 mb-3">
        <?= \app\components\getDPWidget::widget(['action' => ['wb-order/heatmap'], 'defaultDays' => 14]) ?>
    </div>

    <div class="heatmap-top" style="display:flex; flex-wrap:wrap; gap:16px; margin:12px 0 16px;">
        <div class="heatmap-summary" style="font-size:13px; color:#6b7280;">
            Период: <b><?= Html::encode($dateFrom) ?> — <?= Html::encode($dateTo) ?></b> ·
            Заказов: <b><?= number_format($totalCnt,0,',',' ') ?></b> ·
            Сумма: <b><?= number_format($totalSum,0,',',' ') ?> ₽</b> ·
            Макс в ячейке: <b><?= $maxCnt ?></b>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-9">
            <div class="card" style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
                <div class="card-header d-flex justify-content-between align-items-center" style="background:#fff; font-size:13px; font-weight:600;">
                    <span>Теплокарта 7×24 — заказы</span>
                    <span class="text-muted" style="font-weight:400; font-size:12px;">Клик по ячейке → детали справа</span>
                </div>
                <div class="table-responsive" style="overflow-x:auto;">
                    <table class="heatmap-table" style="width:100%; border-collapse:collapse; font-size:12px;">
                        <thead>
                            <tr>
                                <th style="padding:8px; min-width:60px; background:#f9fafb; border:1px solid #e5e7eb;">День / Час</th>
                                <?php for ($h=0;$h<24;$h++): ?>
                                    <th style="padding:6px 2px; text-align:center; min-width:36px; background:#f9fafb; border:1px solid #e5e7eb;"><?= sprintf('%02d:00',$h) ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php for ($wd=0;$wd<7;$wd++): ?>
                            <tr>
                                <th style="padding:6px 8px; text-align:left; background:#f9fafb; border:1px solid #e5e7eb;"><?= $days[$wd] ?></th>
                                <?php for ($hr=0;$hr<24;$hr++):
                                    $cnt = $matrix[$wd][$hr]['cnt'];
                                    $sum = $matrix[$wd][$hr]['sum'];
                                    $alpha = $maxCnt > 0 ? ($cnt / $maxCnt) : 0;
                                    // шкала #eaf2ff → #2f6bff
                                    if ($cnt==0) { $bg='#ffffff'; $color='#bbb'; }
                                    else {
                                        $r = (int)(234 - $alpha* (234-47));
                                        $g = (int)(242 - $alpha* (242-107));
                                        $b = (int)(255 - $alpha* (255-255));
                                        $bg = sprintf('#%02x%02x%02x',$r,$g,$b);
                                        // альтернативно более контрастная синяя шкала
                                        $bg = sprintf('rgba(47,107,255,%.2f)', 0.08 + $alpha*0.92);
                                        $color = $alpha > 0.5 ? '#fff' : '#1f2937';
                                        // для больших значений инвертируем текст в белый
                                    }
                                ?>
                                <td class="heatmap-cell" data-wd="<?= $wd ?>" data-hr="<?= $hr ?>" data-cnt="<?= $cnt ?>" data-sum="<?= $sum ?>" data-day="<?= $dayFull[$wd] ?>"
                                    style="padding:6px 2px; text-align:center; border:1px solid #e5e7eb; background:<?= $bg ?>; color:<?= $color ?>; cursor:pointer; font-weight:<?= $cnt>0?'600':'400' ?>;">
                                    <?= $cnt>0 ? $cnt : '0' ?>
                                </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div style="display:flex; align-items:center; gap:8px; padding:8px 12px; font-size:11px; color:#6b7280;">
                    <span>0</span><div style="flex:1; height:10px; background:linear-gradient(to right, #ffffff, rgba(47,107,255,1)); border:1px solid #e5e7eb; border-radius:4px;"></div><span><?= $maxCnt ?></span><span style="margin-left:8px;">заказов в ячейке</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div id="heatmap-detail" class="card" style="border:1px solid #e5e7eb; border-radius:10px; position:sticky; top:12px;">
                <div class="card-header" style="background:#fff; font-weight:600; font-size:13px;">Детали часа</div>
                <div class="card-body" style="font-size:13px;">
                    <div class="text-muted" style="font-size:12px; margin-bottom:8px;">Кликните по ячейке, чтобы закрепить и разобрать причины.</div>
                    <div id="hd-title" style="font-weight:700; margin-bottom:10px;">—</div>
                    <div class="row g-2" style="font-size:12px;">
                        <div class="col-6"><div class="border rounded p-2"><div class="text-muted">Заказы</div><div id="hd-cnt" style="font-weight:700; font-size:16px;">—</div></div></div>
                        <div class="col-6"><div class="border rounded p-2"><div class="text-muted">Сумма</div><div id="hd-sum" style="font-weight:700;">—</div></div></div>
                        <div class="col-6"><div class="border rounded p-2"><div class="text-muted">Средний чек</div><div id="hd-avg">—</div></div></div>
                        <div class="col-6"><div class="border rounded p-2"><div class="text-muted">Доля периода</div><div id="hd-share">—</div></div></div>
                    </div>
                    <div id="hd-hint" class="text-muted" style="font-size:11px; margin-top:10px;">Выберите ячейку — подсветка зафиксируется.</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($recommend)): ?>
    <div style="margin-top:18px;">
        <h5 style="font-weight:700; margin-bottom:4px;">Рекомендации лучших окон</h5>
        <p class="text-muted" style="font-size:12px; margin-bottom:12px;">Топ-3 временных окна по объёму, чеку и надёжности — для планирования рекламы. Окно 2 часа, агрегация по всем 7 дням периода.</p>
        <div class="row g-3">
            <?php
            $cards = [
                'byVolume' => ['title'=>'Лучшее окно по объёму','sub'=>'Окно даёт максимум заказов','icon'=>'📊'],
                'byAvg'    => ['title'=>'Лучшее окно по чеку','sub'=>'Максимальный средний чек','icon'=>'%'],
                'byReli'   => ['title'=>'Лучшее окно по надёжности','sub'=>'Минимум отмен','icon'=>'🛡️'],
            ];
            foreach (['byVolume','byAvg','byReli'] as $key):
                $best = $recommend[$key]['best'] ?? null;
                $top3 = $recommend[$key]['top3'] ?? [];
                if (!$best) continue;
                $conf = $best['cnt'] >= 30 ? 'высокая' : ($best['cnt'] >= 10 ? 'средняя' : 'низкая');
                $confColors = ['высокая'=>'#16a34a','средняя'=>'#2f6bff','низкая'=>'#111827'];
                $badgeColor = $confColors[$conf];
            ?>
            <div class="col-lg-4">
                <div class="card h-100" style="border:1px solid #e5e7eb; border-radius:10px;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center" style="margin-bottom:8px;">
                            <div style="font-weight:700; font-size:13px;"><?= $cards[$key]['icon'] ?> <?= Html::encode($cards[$key]['title']) ?></div>
                            <span class="badge" style="font-size:10px; background:<?= $badgeColor ?>; color:#fff;">Уверенность: <?= $conf ?></span>
                        </div>
                        <div class="text-muted" style="font-size:11px; margin-bottom:8px;"><?= Html::encode($cards[$key]['sub']) ?></div>
                        <div class="border rounded p-2 mb-2" style="background:#f8fafc;">
                            <div style="font-weight:800; font-size:14px;"><?= Html::encode($best['label']) ?></div>
                            <div class="text-muted" style="font-size:11px;">Окно даёт <?= number_format($best['share'],2,',',' ') ?>% всех заказов · спрос <?= $best['cnt']>=20?'высокий':($best['cnt']>=10?'средний':'низкий') ?></div>
                        </div>
                        <div class="row g-2" style="font-size:11px;">
                            <div class="col-6"><div class="border rounded p-2"><div class="text-muted">Заказов</div><div style="font-weight:700;"><?= $best['cnt'] ?></div><div class="text-muted">Доля <?= number_format($best['share'],2,',',' ') ?>%</div></div></div>
                            <div class="col-6"><div class="border rounded p-2"><div class="text-muted">Средний чек</div><div style="font-weight:700;"><?= number_format($best['avg'],0,',',' ') ?> ₽</div><div class="text-muted">Отмен <?= number_format($best['cancel_rate'],1,',',' ') ?>%</div></div></div>
                        </div>
                        <div style="font-weight:700; font-size:11px; margin:10px 0 6px;">Топ-3 окна</div>
                        <?php foreach ($top3 as $i=>$w): ?>
                            <div class="border rounded p-2 mb-1" style="font-size:11px; background:<?= $i==0?'#f0f7ff':'#fff' ?>;">
                                <div style="font-weight:600;"><?= ($i+1) ?>. <?= Html::encode($w['label']) ?>: <?= $w['cnt'] ?> зак. · <?= number_format($w['avg'],0,',',' ') ?> ₽ · отмен <?= number_format($w['cancel_rate'],1,',',' ') ?>%</div>
                                <div class="text-muted">Доля <?= number_format($w['share'],2,',',' ') ?>% · <?= $key=='byVolume' ? 'объём' : ($key=='byAvg' ? 'чек' : 'надёжность') ?> за 7 дней</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
.heatmap-table th { white-space:nowrap; }
.heatmap-cell:hover { outline:2px solid #2f6bff; outline-offset:-2px; }
.heatmap-cell.is-active { outline:2px solid #111827 !important; outline-offset:-2px; box-shadow:inset 0 0 0 1px #111827; }
@media (max-width: 992px) { .heatmap-table { font-size:11px; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var total = <?= (int)$totalCnt ?>;
    var cells = document.querySelectorAll('.heatmap-cell');
    var titleEl = document.getElementById('hd-title');
    var cntEl = document.getElementById('hd-cnt');
    var sumEl = document.getElementById('hd-sum');
    var avgEl = document.getElementById('hd-avg');
    var shareEl = document.getElementById('hd-share');
    function fmtMoney(v){ return Number(v).toLocaleString('ru-RU',{minimumFractionDigits:0,maximumFractionDigits:0}) + ' ₽'; }
    cells.forEach(function(td){
        td.addEventListener('click', function(){
            cells.forEach(function(x){ x.classList.remove('is-active'); });
            td.classList.add('is-active');
            var wd = td.getAttribute('data-day');
            var hr = parseInt(td.getAttribute('data-hr'));
            var cnt = parseInt(td.getAttribute('data-cnt'));
            var sum = parseFloat(td.getAttribute('data-sum'));
            var hr2 = (hr+1)%24;
            titleEl.textContent = wd + ' ' + ('0'+hr).slice(-2) + ':00–' + ('0'+hr2).slice(-2) + ':00';
            cntEl.textContent = cnt;
            sumEl.textContent = fmtMoney(sum);
            avgEl.textContent = cnt>0 ? fmtMoney(sum/cnt) : '—';
            shareEl.textContent = total>0 ? (cnt/total*100).toFixed(2) + ' %' : '—';
        });
    });
});
</script>
