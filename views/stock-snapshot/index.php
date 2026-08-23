<?php

use yii\helpers\Html;
use kartik\grid\GridView;
use kartik\icons\Icon;

/** @var yii\web\View $this */
/** @var app\models\search\StockSnapshotSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $period */

Icon::map($this);

$this->title = 'Остатки на начало периода (Смоленск)';

$periodMonths = [
    1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
    7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
];
[$periodYear, $periodMonthNum] = array_map('intval', explode('-', $period));
$periodLabel = ($periodMonths[$periodMonthNum] ?? '') . ' ' . $periodYear;

$reconcileUrl = \yii\helpers\Url::to(['stock-movement/reconcile']);
?>
<div class="stock-snapshot-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="card snapshot-topbar mb-3">
        <div class="card-body">
            <div class="snapshot-actions-row">
                <?= Html::a('<i class="fas fa-plus"></i> Добавить снапшот', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>

                <span class="snapshot-divider"></span>

                <?= Html::button('<i class="fas fa-file-excel"></i> Импорт из Excel', ['class' => 'btn btn-info btn-sm', 'id' => 'import-trigger-btn', 'encode' => false]) ?>
                <input type="file" id="import-file-input" accept=".xlsx,.xls" style="display:none;">
                <?= Html::button('<i class="fas fa-check-double"></i> Сохранить все наличия', ['class' => 'btn btn-success btn-sm', 'id' => 'bulk-save-btn', 'encode' => false]) ?>

                <span class="snapshot-divider"></span>

                <?= Html::a('<i class="fas fa-exchange-alt"></i> К движениям (приход / сверка)', ['stock-movement/index'], ['class' => 'btn btn-default btn-sm', 'encode' => false]) ?>
            </div>

            <div class="snapshot-period-row">
                <?= Html::beginForm(['index'], 'get', ['class' => 'snapshot-period-form']) ?>
                    <label for="snapshot-period" class="snapshot-period-label">Период</label>
                    <input type="month" id="snapshot-period" name="period" value="<?= Html::encode($period) ?>" class="form-control form-control-sm">
                    <button type="submit" class="btn btn-primary btn-sm">Показать</button>
                <?= Html::endForm() ?>
            </div>
        </div>

        <div id="import-result" class="snapshot-import-banner" style="display:none;"></div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,
        'showPageSummary' => false,
        'toggleData' => false,
        'export' => [
            'showConfirmAlert' => false,
            'target' => GridView::TARGET_BLANK,
        ],
        'exportConfig' => [
            GridView::EXCEL => ['label' => 'Сохранить в Excel'],
        ],
        'toolbar' => [
            '{export}',
        ],
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Остатки на начало периода (Смоленск) — ' . $periodLabel,
            'headingOptions' => ['class' => 'card-header text-white bg-wb-blue-header'],
            'footer' => false,
            'after' => false,
        ],
        'columns' => [ 
             ['attribute' => 'period_date', 'label' => 'Период (1-е число)', 'filter' => false, 'enableSorting' => false], 
             [ 
                 'attribute' => 'wbCard.vendorCode', 
                 'label' => 'Артикул', 
                 'filter' => Html::activeTextInput($searchModel, 'vendorCodeOrTitle', ['class' => 'form-control']), 
                 'value' => function ($model) { 
                     return $model->wbCard ? $model->wbCard->vendorCode : '—'; 
                 }, 
                 'enableSorting' => true, 
             ], 
             [ 
                 'attribute' => 'wbCard.nmID', 
                 'label' => 'nmID', 
                 'filter' => Html::activeTextInput($searchModel, 'vendorCodeOrTitle', ['class' => 'form-control']), 
                 'value' => function ($model) { 
                     return $model->wbCard ? $model->wbCard->nmID : '—'; 
                 }, 
                 'enableSorting' => true, 
             ], 
             [ 
                 'attribute' => 'wbCard.title', 
                 'label' => 'Название', 
                 'filter' => Html::activeTextInput($searchModel, 'vendorCodeOrTitle', ['class' => 'form-control']), 
                 'value' => function ($model) { 
                     return $model->wbCard ? $model->wbCard->title : '—'; 
                 }, 
                 'enableSorting' => true, 
             ],
            [
                'attribute' => 'qty_start',
                'label' => 'Остаток',
                'filter' => false,
                'contentOptions' => ['style' => 'text-align:center;'],
                'headerOptions' => ['style' => 'text-align:center; width:140px;'],
            ],
            [
                'label' => 'Наличие',
                'format' => 'raw',
                'filter' => false,
                'headerOptions' => ['style' => 'text-align:center; width:220px;', 'title' => 'Введите фактическое количество на сегодня - будет создано движение "Корректировка" на разницу с остатком из БД' ],
                'value' => function ($model) use ($period) {
                    $nmId = $model->nm_id;
                    return '<div class="reconcile-cell" data-nmid="' . $nmId . '" data-period="' . $period . '">'
                        . '<input type="number" class="form-control form-control-sm reconcile-input" placeholder="факт">'
                        . '<button type="button" class="btn btn-primary btn-xs reconcile-btn">Сверить</button>'
                        . '<div class="reconcile-result"></div>'
                        . '</div>';
                },
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{update} {delete}',
            ],
        ],
    ]); ?>

</div>

<style>
.snapshot-topbar { border: none; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); overflow: hidden; }
.snapshot-actions-row {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding-bottom: 14px; margin-bottom: 14px; border-bottom: 1px solid #eee;
}
.snapshot-actions-row .btn i { margin-right: 5px; }
.snapshot-divider { width: 1px; height: 22px; background: #dde2e7; margin: 0 4px; }
.snapshot-period-row { display: flex; justify-content: flex-end; }
.snapshot-period-form { display: flex; align-items: center; gap: 8px; }
.snapshot-period-label { font-size: 12px; font-weight: 600; color: #868e96; text-transform: uppercase; letter-spacing: .02em; margin: 0; }
.snapshot-period-form input[type=month] { width: 150px; }

.snapshot-import-banner {
    margin: 0 16px 16px; padding: 10px 14px; border-radius: 6px;
    background: #eef4ff; border: 1px solid #cfe0fa; color: #2c4f82; font-size: 12.5px;
}

.reconcile-cell { display: flex; align-items: center; gap: 6px; justify-content: center; flex-wrap: wrap; }
.reconcile-input { width: 70px; text-align: center; }
.reconcile-result { flex-basis: 100%; text-align: center; font-size: 11px; }
.reconcile-result.ok { color: #2f6e44; }
.reconcile-result.diff-pos { color: #2f6e44; font-weight: 600; }
.reconcile-result.diff-neg { color: #a6362b; font-weight: 600; }
.reconcile-result.error { color: #a6362b; }
</style>

<script>
(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : (window.yii && yii.getCsrfToken ? yii.getCsrfToken() : null);
    }
    function csrfParam() {
        var meta = document.querySelector('meta[name="csrf-param"]');
        return meta ? meta.getAttribute('content') : (window.yii && yii.getCsrfParam ? yii.getCsrfParam() : '_csrf');
    }

    document.addEventListener('click', function (ev) {
        if (!ev.target.classList.contains('reconcile-btn')) return;

        var cell = ev.target.closest('.reconcile-cell');
        var nmId = cell.dataset.nmid;
        var period = cell.dataset.period;
        var input = cell.querySelector('.reconcile-input');
        var resultEl = cell.querySelector('.reconcile-result');
        var actualQty = input.value;

        if (actualQty === '' || isNaN(actualQty)) {
            resultEl.textContent = 'Укажите число';
            resultEl.className = 'reconcile-result error';
            return;
        }

        ev.target.disabled = true;
        resultEl.textContent = 'Сохраняю…';
        resultEl.className = 'reconcile-result';

        var body = new URLSearchParams();
        body.append('nm_id', nmId);
        body.append('period_date', period);
        body.append('actual_qty', actualQty);
        var param = csrfParam();
        var token = csrfToken();
        if (param && token) body.append(param, token);

        fetch('<?= $reconcileUrl ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                ev.target.disabled = false;
                if (!data.success) {
                    resultEl.textContent = data.error || 'Ошибка';
                    resultEl.className = 'reconcile-result error';
                    return;
                }
                if (data.diff === 0) {
                    resultEl.textContent = 'Совпадает';
                    resultEl.className = 'reconcile-result ok';
                } else {
                    resultEl.textContent = (data.diff > 0 ? '+' : '') + data.diff + ' в движения';
                    resultEl.className = 'reconcile-result ' + (data.diff > 0 ? 'diff-pos' : 'diff-neg');
                    input.value = '';
                }
            })
            .catch(function () {
                ev.target.disabled = false;
                resultEl.textContent = 'Ошибка сети';
                resultEl.className = 'reconcile-result error';
            });
    });

    document.getElementById('import-trigger-btn').addEventListener('click', function () {
        document.getElementById('import-file-input').click();
    });

    document.getElementById('import-file-input').addEventListener('change', function () {
        var file = this.files[0];
        var resultEl = document.getElementById('import-result');
        if (!file) return;

        resultEl.style.display = 'block';
        resultEl.textContent = 'Загружаю файл…';

        var formData = new FormData();
        formData.append('file', file);
        var param = csrfParam();
        var token = csrfToken();
        if (param && token) formData.append(param, token);

        fetch('<?= \yii\helpers\Url::to(['stock-snapshot/parse-availability']) ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                this.value = ''; // сбрасываем input, чтобы можно было загрузить тот же файл повторно
                if (!data.success) {
                    resultEl.textContent = 'Ошибка: ' + (data.error || 'не удалось разобрать файл');
                    return;
                }

                var filled = 0;
                var notOnPage = 0;
                data.matched.forEach(function (item) {
                    var cell = document.querySelector('.reconcile-cell[data-nmid="' + item.nm_id + '"]');
                    if (!cell) { notOnPage++; return; }
                    cell.querySelector('.reconcile-input').value = item.qty;
                    filled++;
                });

                var msg = 'Подставлено в ' + filled + ' строк на странице.';
                if (notOnPage > 0) msg += ' ' + notOnPage + ' товаров из файла не найдены на текущей странице (другой период/фильтр).';
                if (data.skipped && data.skipped.length) msg += ' Не распознано строк: ' + data.skipped.length + '.';
                resultEl.textContent = msg;
            }.bind(this))
            .catch(function () {
                resultEl.style.display = 'block';
                resultEl.textContent = 'Ошибка сети при загрузке файла.';
            });
    });

    document.getElementById('bulk-save-btn').addEventListener('click', function () {
        var changes = [];
        document.querySelectorAll('.reconcile-cell').forEach(function (cell) {
            var nmId = cell.dataset.nmid;
            var period = cell.dataset.period;
            var input = cell.querySelector('.reconcile-input');
            if (input && input.value !== '') {
                changes.push({
                    nm_id: nmId,
                    period_date: period,
                    actual_qty: input.value
                });
            }
        });

        if (changes.length === 0) {
            alert('Нет заполненных значений "Наличие" для сохранения.');
            return;
        }

        this.disabled = true;
        var self = this;

        var body = new URLSearchParams();
        body.append('changes', JSON.stringify(changes));
        var param = csrfParam();
        var token = csrfToken();
        if (param && token) body.append(param, token);

        fetch('<?= \yii\helpers\Url::to(['stock-snapshot/reconcile-bulk']) ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                self.disabled = false;
                if (data.success) {
                    alert('Готово. Создано движений: ' + data.processed + '. Без расхождений: ' + data.no_diff + '. Пропущено: ' + data.skipped + '.');
                    document.querySelectorAll('.reconcile-input').forEach(function (i) { i.value = ''; });
                } else {
                    alert('Ошибка при массовой сверке: ' + (data.error || 'Неизвестная ошибка'));
                }
            })
            .catch(function () {
                self.disabled = false;
                alert('Ошибка сети при массовой сверке.');
            });
    });

})();
</script>