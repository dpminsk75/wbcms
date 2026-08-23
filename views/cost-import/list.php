<?php

/** @var yii\web\View $this */
/** @var array $rows */

use app\components\getDPWidget;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Себестоимость: просмотр и редактирование';
?>
<div class="cost-list">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
        <a href="<?= Url::to(['cost-import/index']) ?>" class="btn btn-outline-secondary btn-sm">
            Загрузить из файла
        </a>
    </div>

    <?= getDPWidget::widget([
        'action' => ['cost-import/list'],
        'defaultDays' => 15,
    ]) ?>

    <table class="table table-sm table-striped align-middle mt-3">
        <thead>
        <tr>
            <th>Дата</th>
            <th>nmID</th>
            <th>Товар</th>
            <th style="width:160px;">Себестоимость</th>
        </tr>
        </thead>
        <tbody id="cost-rows">
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= Html::encode($row['load_date']) ?></td>
                <td><?= Html::encode($row['nmID']) ?></td>
                <td>
                    <?= Html::encode($row['product_name'] ?? '—') ?>
                    <?php if (!empty($row['vendorCode'])): ?>
                        <div class="text-muted small"><?= Html::encode($row['vendorCode']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text"
                               class="form-control form-control-sm price-input"
                               data-id="<?= (int)$row['id'] ?>"
                               data-original="<?= Html::encode($row['price']) ?>"
                               value="<?= Html::encode($row['price']) ?>">
                        <span class="price-status small"></span>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
            <tr>
                <td colspan="4" class="text-center text-muted py-4">Нет данных за выбранный период</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$updateUrl = Url::to(['cost-import/update-price']);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;

$js = <<<JS
(function () {
    var container = document.getElementById('cost-rows');
    if (!container) return;

    function setStatus(span, text, cls) {
        span.textContent = text;
        span.className = 'price-status small ' + cls;
        if (text) {
            setTimeout(function () {
                span.textContent = '';
                span.className = 'price-status small';
            }, 2000);
        }
    }

    container.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.classList.contains('price-input')) return;

        var newValue = input.value.trim();
        var original = input.dataset.original;

        if (newValue === original) return;

        var id = input.dataset.id;
        var statusEl = input.parentElement.querySelector('.price-status');

        var formData = new FormData();
        formData.append('id', id);
        formData.append('price', newValue);
        formData.append('{$csrfParam}', '{$csrfToken}');

        input.disabled = true;
        setStatus(statusEl, 'Сохранение...', 'text-muted');

        fetch('{$updateUrl}', {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            input.disabled = false;
            if (data.success) {
                input.value = data.price;
                input.dataset.original = String(data.price);
                setStatus(statusEl, '✓ сохранено', 'text-success');
            } else {
                input.value = original;
                setStatus(statusEl, data.message || 'Ошибка', 'text-danger');
            }
        })
        .catch(function (err) {
            input.disabled = false;
            input.value = original;
            setStatus(statusEl, 'Ошибка запроса', 'text-danger');
        });
    });
})();
JS;

$this->registerJs($js);
?>
