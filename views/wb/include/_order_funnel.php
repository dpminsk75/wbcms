<?php
/**
 * Виджет "воронки заказов" за период: Заказы / Выкупленные / В доставке /
 * Отменённые / Возвраты / Процент выкупа.
 *
 * @var array $OrderFunnel см. WbController::actionDetail()
 */

$total    = (int)($OrderFunnel['total_qty'] ?? 0);
$totalSum = (float)($OrderFunnel['total_sum'] ?? 0);

$bought    = (int)($OrderFunnel['bought_qty'] ?? 0);
$boughtSum = (float)($OrderFunnel['bought_sum'] ?? 0);
$boughtPct = $OrderFunnel['bought_percent'] ?? 0;

$delivery    = (int)($OrderFunnel['delivery_qty'] ?? 0);
$deliverySum = (float)($OrderFunnel['delivery_sum'] ?? 0);
$deliveryPct = $OrderFunnel['delivery_percent'] ?? 0;

$cancel    = (int)($OrderFunnel['cancel_qty'] ?? 0);
$cancelSum = (float)($OrderFunnel['cancel_sum'] ?? 0);
$cancelPct = $OrderFunnel['cancel_percent'] ?? 0;

$returns    = (int)($OrderFunnel['returns_qty'] ?? 0);
$returnsSum = (float)($OrderFunnel['returns_sum'] ?? 0);
$returnsPct = $OrderFunnel['returns_percent'] ?? 0;

$buyoutPct = $OrderFunnel['buyout_percent'] ?? 0;

$fmtMoney = function ($v) {
    return number_format((float)$v, 0, ',', ' ') . ' ₽';
};
$fmtQty = function ($v) {
    return number_format((int)$v, 0, ',', ' ');
};

$buyoutColorClass = ($buyoutPct > 90) ? 'of-buyout-good' : (($buyoutPct > 80) ? 'of-buyout-warn' : 'of-buyout-bad');
?>
<div class="order-funnel-widget">
    <div class="order-funnel-bar">
        <div class="of-seg of-bg-bought"   style="width: <?= $boughtPct ?>%"  title="Выкупленные: <?= $boughtPct ?>%"></div>
        <div class="of-seg of-bg-delivery" style="width: <?= $deliveryPct ?>%" title="В доставке: <?= $deliveryPct ?>%"></div>
        <div class="of-seg of-bg-cancel"   style="width: <?= $cancelPct ?>%"  title="Отменённые: <?= $cancelPct ?>%"></div>
        <div class="of-seg of-bg-returns"  style="width: <?= $returnsPct ?>%" title="Возвраты: <?= $returnsPct ?>%"></div>
    </div>

    <div class="order-funnel-cards">
        <div class="of-card">
            <div class="of-label">Заказы <i class="bi bi-question-circle-fill of-hint" title="Все заказы за выбранный период"></i></div>
            <div class="of-value"><?= $fmtMoney($totalSum) ?></div>
            <div class="of-qty"><b><?= $fmtQty($total) ?></b> шт.</div>
        </div>

        <div class="of-card">
            <div class="of-label"><span class="of-dot of-bg-bought"></span>Выкупленные <i class="bi bi-question-circle-fill of-hint" title="Заказы, по которым дошла продажа"></i></div>
            <div class="of-value"><?= $fmtMoney($boughtSum) ?></div>
            <div class="of-pct"><?= $boughtPct ?>%</div>
            <div class="of-qty"><b><?= $fmtQty($bought) ?></b> шт.</div>
        </div>

        <div class="of-card">
            <div class="of-label"><span class="of-dot of-bg-delivery"></span>В доставке <i class="bi bi-question-circle-fill of-hint" title="Заказы без финального статуса: ещё не выкуплены и не отменены"></i></div>
            <div class="of-value"><?= $fmtMoney($deliverySum) ?></div>
            <div class="of-pct"><?= $deliveryPct ?>%</div>
            <div class="of-qty"><b><?= $fmtQty($delivery) ?></b> шт.</div>
        </div>

        <div class="of-card">
            <div class="of-label"><span class="of-dot of-bg-cancel"></span>Отменённые <i class="bi bi-question-circle-fill of-hint" title="Заказы с признаком отмены (is_cancel)"></i></div>
            <div class="of-value"><?= $fmtMoney($cancelSum) ?></div>
            <div class="of-pct"><?= $cancelPct ?>%</div>
            <div class="of-qty"><b><?= $fmtQty($cancel) ?></b> шт.</div>
        </div>

        <div class="of-card">
            <div class="of-label"><span class="of-dot of-bg-returns"></span>Возвраты <i class="bi bi-question-circle-fill of-hint" title="Продажи с saleID вида R... — возврат товара покупателем"></i></div>
            <div class="of-value"><?= $fmtMoney($returnsSum) ?></div>
            <div class="of-pct"><?= $returnsPct ?>%</div>
            <div class="of-qty"><b><?= $fmtQty($returns) ?></b> шт.</div>
        </div>

        <div class="of-card of-card-total">
            <div class="of-label">Процент выкупа</div>
            <div class="of-buyout <?= $buyoutColorClass ?>"><?= $buyoutPct ?>%</div>
        </div>
    </div>
</div>

<style>
.order-funnel-widget {
    background: #fff;
    border: 1px solid #e6e8eb;
    border-radius: 12px;
    padding: 16px 18px 12px;
    margin-bottom: 0;
    box-shadow: 0 4px 16px rgba(20, 30, 50, 0.08), 0 1px 3px rgba(20, 30, 50, 0.06);
    transition: box-shadow 0.2s ease;
}
.order-funnel-widget:hover {
    box-shadow: 0 6px 20px rgba(20, 30, 50, 0.12), 0 1px 3px rgba(20, 30, 50, 0.08);
}
.order-funnel-bar {
    display: flex;
    width: 100%;
    height: 15px;
    border-radius: 4px;
    overflow: hidden;
    background: #eef0f2;
    margin-bottom: 16px;
}
.of-seg { height: 100%; }
.of-bg-bought   { background: #4c8bf5; }
.of-bg-delivery { background: #9aa1ab; }
.of-bg-cancel   { background: #f7941d; }
.of-bg-returns  { background: #f2637b; }

.order-funnel-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.of-card {
    flex: 1 1 150px;
    min-width: 130px;
    padding-right: 10px;
    border-right: 1px solid #eef0f2;
}
.of-card:last-child { border-right: none; }

.of-label {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 6px;
    white-space: nowrap;
}
.of-hint {
    font-size: 10px;
    color: #b7bcc3;
    cursor: help;
}
.of-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 4px;
}
.of-value {
    font-size: 18px;
    font-weight: 700;
    color: #1f2328;
    line-height: 1.2;
}
.of-pct {
    font-size: 12px;
    color: #6b7280;
}
.of-qty {
    font-size: 12px;
    color: #6b7280;
}
.of-card-total .of-buyout {
    font-size: 26px;
    font-weight: 800;
    line-height: 1.2;
}
.of-buyout-good { color: #1cb56d; }
.of-buyout-warn { color: #f7941d; }
.of-buyout-bad  { color: #f2637b; }

@media (max-width: 991px) {
    .order-funnel-cards { flex-direction: column; }
    .of-card { border-right: none; border-bottom: 1px solid #eef0f2; padding-bottom: 8px; }
    .of-card:last-child { border-bottom: none; }
}
</style>