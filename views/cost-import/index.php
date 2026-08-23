<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Загрузка себестоимости';
?>
<div class="cost-import">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
        <a href="<?= Url::to(['cost-import/list']) ?>" class="btn btn-outline-secondary btn-sm">
            Просмотр и редактирование
        </a>
    </div>

    <form id="cost-import-form" class="d-flex align-items-end flex-wrap gap-3 mb-3">
        <div>
            <label for="load-date" class="form-label mb-1">Дата загрузки</label>
            <input type="date" id="load-date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div>
            <label for="cost-file" class="form-label mb-1">Файл</label>
            <input type="file" id="cost-file" class="form-control" accept=".xlsx,.xls" required>
        </div>

        <div>
            <button type="submit" id="btn-preview" class="btn btn-primary">Загрузить и проверить</button>
        </div>
    </form>

    <div id="preview-status"></div>

    <div id="preview-results" style="display:none;">

        <div class="d-flex justify-content-between align-items-center py-2 mb-3 border-top border-bottom bg-white sticky-top">
            <div>
                Найдено позиций: <strong id="count-success">0</strong>
                &nbsp;·&nbsp;
                Ошибок: <strong id="count-errors" class="text-danger">0</strong>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span id="save-status"></span>
                <button id="btn-save" class="btn btn-success">Сохранить в базу</button>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h3 class="h5">Найденные позиции</h3>
                <div style="max-height:400px; overflow-y:auto;">
                    <table class="table table-sm table-striped">
                        <thead>
                        <tr>
                            <th>Строка</th>
                            <th>nmID</th>
                            <th>Товар</th>
                            <th>Цена</th>
                        </tr>
                        </thead>
                        <tbody id="table-success"></tbody>
                    </table>
                </div>
            </div>

            <div class="col-md-6">
                <h3 class="h5">Ошибки</h3>
                <div style="max-height:400px; overflow-y:auto;">
                    <table class="table table-sm table-striped">
                        <thead>
                        <tr>
                            <th>Строка</th>
                            <th>Причина</th>
                            <th>Значение</th>
                        </tr>
                        </thead>
                        <tbody id="table-errors"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
$previewUrl = Url::to(['cost-import/preview']);
$saveUrl = Url::to(['cost-import/save']);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;

$js = <<<JS
(function () {
    var parsedItems = [];

    var \$form = document.getElementById('cost-import-form');
    var \$status = document.getElementById('preview-status');
    var \$results = document.getElementById('preview-results');
    var \$tableSuccess = document.getElementById('table-success');
    var \$tableErrors = document.getElementById('table-errors');
    var \$countSuccess = document.getElementById('count-success');
    var \$countErrors = document.getElementById('count-errors');
    var \$btnPreview = document.getElementById('btn-preview');
    var \$btnSave = document.getElementById('btn-save');
    var \$saveStatus = document.getElementById('save-status');

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    \$form.addEventListener('submit', function (e) {
        e.preventDefault();

        var fileInput = document.getElementById('cost-file');
        if (!fileInput.files.length) return;

        var formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('{$csrfParam}', '{$csrfToken}');

        \$btnPreview.disabled = true;
        \$status.innerHTML = '<div class="alert alert-info">Загрузка и разбор файла...</div>';
        \$results.style.display = 'none';
        \$saveStatus.innerHTML = '';

        fetch('{$previewUrl}', {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            \$btnPreview.disabled = false;

            if (!data.success) {
                \$status.innerHTML = '<div class="alert alert-danger">' + escapeHtml(data.message) + '</div>';
                return;
            }

            \$status.innerHTML = '';
            parsedItems = data.items;

            \$tableSuccess.innerHTML = data.items.map(function (item) {
                return '<tr>'
                    + '<td>' + item.row + '</td>'
                    + '<td>' + escapeHtml(item.nmID) + '</td>'
                    + '<td>' + escapeHtml(item.name) + '</td>'
                    + '<td>' + item.price + '</td>'
                    + '</tr>';
            }).join('');

            \$tableErrors.innerHTML = data.errors.map(function (err) {
                return '<tr>'
                    + '<td>' + err.row + '</td>'
                    + '<td>' + escapeHtml(err.reason) + '</td>'
                    + '<td>' + escapeHtml(err.raw) + '</td>'
                    + '</tr>';
            }).join('');

            \$countSuccess.textContent = data.items.length;
            \$countErrors.textContent = data.errors.length;
            \$results.style.display = 'block';
        })
        .catch(function (err) {
            \$btnPreview.disabled = false;
            \$status.innerHTML = '<div class="alert alert-danger">Ошибка запроса: ' + escapeHtml(err.message) + '</div>';
        });
    });

    \$btnSave.addEventListener('click', function () {
        if (!parsedItems.length) return;

        var date = document.getElementById('load-date').value;
        if (!date) {
            \$saveStatus.innerHTML = '<span class="text-danger">Укажите дату загрузки</span>';
            return;
        }

        \$btnSave.disabled = true;
        \$saveStatus.innerHTML = 'Сохранение...';

        var formData = new FormData();
        formData.append('date', date);
        formData.append('items', JSON.stringify(parsedItems));
        formData.append('{$csrfParam}', '{$csrfToken}');

        fetch('{$saveUrl}', {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            \$btnSave.disabled = false;
            if (data.success) {
                \$saveStatus.innerHTML = '<span class="text-success">' + escapeHtml(data.message) + '</span>';
            } else {
                \$saveStatus.innerHTML = '<span class="text-danger">' + escapeHtml(data.message) + '</span>';
            }
        })
        .catch(function (err) {
            \$btnSave.disabled = false;
            \$saveStatus.innerHTML = '<span class="text-danger">Ошибка запроса: ' + escapeHtml(err.message) + '</span>';
        });
    });
})();
JS;

$this->registerJs($js);
?>
