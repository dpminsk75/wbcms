<?php

/** @var yii\web\View $this */
/** @var app\models\WbCardSearch $wbSearchModel */
/** @var yii\data\ActiveDataProvider $wbDataProvider */
/** @var app\models\ProductionPlanItem[] $items */
/** @var bool $showCompanyColumn */
/** @var array<int,string> $companyAbbrMap */

use yii\helpers\Html;
use yii\widgets\Pjax;
use kartik\grid\GridView;
use kartik\icons\Icon;

Icon::map($this);

$this->title = 'Список товаров для производственного планирования';
$this->params['breadcrumbs'][] = $this->title;

// дефолты "на случай если в localStorage браузера ещё ничего не сохранено" -
// сами значения по умолчанию для новых товаров задаются и запоминаются в панели сверху (JS + localStorage)
$fallbackDefaults = [
    'production_days' => 10,
    'logistics_smolensk_days' => 2,
    'logistics_wb_days' => 6,
    'buffer_days' => 3,
    'target_coverage_days' => 360,
];
$fallbackDefaultsJson = \yii\helpers\Json::htmlEncode($fallbackDefaults);

// Замыкание, а не обычная function() - иначе при повторном рендере вьюхи в рамках
// одного PHP-процесса (например, при вложенном render) будет фатальная ошибка redeclare.
$renderPlanRow = function ($nmId, $vendorCode, $title, $prod, $logSm, $logWb, $buf, $cov, $companyLabel = null) use ($showCompanyColumn) {
    $n = Html::encode($nmId);
    $vc = Html::encode($vendorCode);
    $t = Html::encode(mb_strimwidth($title, 0, 45, '...'));
    $companyCell = $showCompanyColumn
        ? '<td class="plan-company-cell">' . Html::encode($companyLabel ?? '—') . '</td>'
        : '';
    return <<<HTML
    <tr data-nmid="{$n}">
        <td>
            <div class="plan-item-vendor">{$vc} <span class="text-muted">/ {$n}</span></div>
            <div class="plan-item-title">{$t}</div>
        </td>
        {$companyCell}
        <td><input type="number" class="form-control form-control-sm" name="ProductionPlanItem[{$n}][production_days]" value="{$prod}" min="0"></td>
        <td><input type="number" class="form-control form-control-sm" name="ProductionPlanItem[{$n}][logistics_smolensk_days]" value="{$logSm}" min="0"></td>
        <td><input type="number" class="form-control form-control-sm" name="ProductionPlanItem[{$n}][logistics_wb_days]" value="{$logWb}" min="0"></td>
        <td><input type="number" class="form-control form-control-sm" name="ProductionPlanItem[{$n}][buffer_days]" value="{$buf}" min="0"></td>
        <td><input type="number" class="form-control form-control-sm" name="ProductionPlanItem[{$n}][target_coverage_days]" value="{$cov}" min="0"></td>
        <td><button type="button" class="plan-remove-btn" title="Убрать из плана">&times;</button></td>
    </tr>
    HTML;
};
?>
<div class="plan-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= Html::beginForm(['index'], 'post', ['id' => 'plan-form']) ?>

    <div class="card plan-topbar mb-3">
        <div class="card-body">
            <div class="plan-topbar-row">
                <div class="plan-topbar-info">
                    <div class="plan-topbar-title"><i class="fas fa-industry"></i> Производственный план</div>
                    <p class="text-muted mb-0" style="font-size:13px;">
                        Перетащите товары справа налево. Список полностью заменяется при сохранении.
                    </p>
                </div>

                <div class="plan-stat-inline">
                    <div class="plan-stat-icon"><i class="fas fa-layer-group"></i></div>
                    <div>
                        <div class="plan-stat-value" id="plan-count"><?= count($items) ?></div>
                        <div class="plan-stat-label">Товаров в плане</div>
                    </div>
                </div>

                <div class="plan-topbar-save">
                    <?= Html::submitButton('<i class="fas fa-check"></i> Сохранить список', ['class' => 'btn btn-success', 'encode' => false]) ?>
                </div>
            </div>

            <div class="plan-defaults-row">
                <span class="plan-defaults-label">Значения по умолчанию для новых товаров:</span>

                <label class="plan-default-field">Срок
                    <input type="number" id="default-production_days" min="0">
                </label>
                <label class="plan-default-field">SML
                    <input type="number" id="default-logistics_smolensk_days" min="0">
                </label>
                <label class="plan-default-field">→WB
                    <input type="number" id="default-logistics_wb_days" min="0">
                </label>
                <label class="plan-default-field" title="Запас дней на случай задержек производства/логистики/спроса сверх плана">Буфер
                    <input type="number" id="default-buffer_days" min="0">
                </label>
                <label class="plan-default-field">Период
                    <input type="number" id="default-target_coverage_days" min="0">
                </label>

                <small class="text-muted plan-defaults-note">
                    Применяются только к новым товарам при добавлении. Запоминаются в этом браузере.
                </small>
            </div>
        </div>
    </div>

    <div class="card wb-panel">
        <div class="wb-panel-header">
            <i class="fas fa-link"></i> Подбор товаров из Wildberries
        </div>
        <div class="card-body">

            <div class="wb-search-bar row g-2 mb-3">
                <div class="col-md-2">
                    <?= Html::activeTextInput($wbSearchModel, 'nmID', ['class' => 'form-control form-control-sm', 'id' => 'wbsearch-nmid', 'placeholder' => 'Арт WB']) ?>
                </div>
                <div class="col-md-3">
                    <?= Html::activeTextInput($wbSearchModel, 'vendorCode', ['class' => 'form-control form-control-sm', 'id' => 'wbsearch-vendorcode', 'placeholder' => 'Артикул']) ?>
                </div>
                <div class="col-md-5">
                    <?= Html::activeTextInput($wbSearchModel, 'title', ['class' => 'form-control form-control-sm', 'id' => 'wbsearch-title', 'placeholder' => 'Название']) ?>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary btn-sm w-100" id="wb-search-btn">
                        <i class="fas fa-search"></i> Найти
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-5">
                    <div class="wb-column-header">
                        <span><i class="fas fa-arrows-alt"></i> Доступные</span>
                        <button type="button" id="add-all-visible" class="btn btn-xs btn-outline-primary">
                            <i class="fas fa-plus"></i> Добавить все
                        </button>
                    </div>

                    <div class="wb-available-list">
                        <?php Pjax::begin(['id' => 'wb-grid-pjax', 'enablePushState' => false]); ?>
                        <?php
                        $wbColumns = [
                            [
                                'attribute' => 'nmID', 'label' => 'Арт WB',
                                'headerOptions' => ['style' =>  'text-align: center; vertical-align: middle; width: 1%; white-space: nowrap;'],
                                'contentOptions' => ['style' => 'text-align: left; vertical-align: middle; width: 1%; white-space: nowrap;'],
                            ],
                            [
                                'attribute' => 'vendorCode', 'label' => 'Артикул',
                                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; width: 1%; white-space: nowrap;'],
                                'contentOptions' => ['style' => 'text-align: left; width: 1%; white-space: nowrap;'],
                                'value' => function ($m) { return mb_strimwidth($m->vendorCode, 0, 25, '...'); }
                            ],
                        ];
                        if ($showCompanyColumn) {
                            $wbColumns[] = [
                                'label' => 'Компания',
                                'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; width: 1%; white-space: nowrap;'],
                                'contentOptions' => ['style' => 'text-align: left; width: 1%; white-space: nowrap;'],
                                'value' => function ($m) use ($companyAbbrMap) {
                                    return $companyAbbrMap[$m->company_id] ?? '—';
                                },
                            ];
                        }
                        $wbColumns[] = [
                            'attribute' => 'title',
                            'label' => 'Название',
                            'headerOptions' => ['style' => 'text-align: center; vertical-align: middle; width: 200px;'],
                            'value' => function ($m) { return mb_strimwidth($m->title, 0, 47, '...'); }
                        ];
                        ?>
                        <?= GridView::widget([
                            'dataProvider' => $wbDataProvider,
                            'summary' => false,
                            'tableOptions' => ['class' => 'table table-sm table-hover wb-available-table'],
                            'rowOptions' => function ($m) use ($showCompanyColumn, $companyAbbrMap) {
                                return [
                                    'draggable' => 'true',
                                    'ondragstart' => 'planDrag(event)',
                                    'data-nmid' => $m->nmID,
                                    'data-vendorcode' => $m->vendorCode,
                                    'data-title' => $m->title,
                                    'data-company' => $showCompanyColumn ? ($companyAbbrMap[$m->company_id] ?? '') : '',
                                    'class' => 'wb-draggable-row',
                                ];
                            },
                            'columns' => $wbColumns,
                        ]) ?>
                        <?php Pjax::end(); ?>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="wb-column-header">
                        <span><i class="fas fa-inbox"></i> В плане</span>
                    </div>

                    <div id="plan-drop-zone" class="plan-drop-zone" ondrop="planDrop(event)" ondragover="planAllowDrop(event)">

                        <table class="table table-sm plan-items-table" id="plan-items-table">
                            <thead>
                                <tr>
                                    <th style="width: 100%;">Товар</th>
                                    <?php if ($showCompanyColumn): ?>
                                        <th>Компания</th>
                                    <?php endif; ?>
                                    <th title="Дней на производство">Срок</th>
                                    <th title="Дней логистики до Смоленска">→ SML</th>
                                    <th title="Дней логистики до складов WB">→ WB</th>
                                    <th title="Запас дней на случай задержек производства/логистики/спроса">Буфер</th>
                                    <th title="Целевое покрытие, дней">Период</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="plan-items-body">
                                <?php foreach ($items as $item): ?>
                                    <?= $renderPlanRow(
                                        $item->nm_id,
                                        $item->wbCard->vendorCode ?? '?',
                                        $item->wbCard->title ?? '(карточка не найдена)',
                                        $item->production_days,
                                        $item->logistics_smolensk_days,
                                        $item->logistics_wb_days,
                                        $item->buffer_days,
                                        $item->target_coverage_days,
                                        $item->wbCard ? ($companyAbbrMap[$item->wbCard->company_id] ?? '—') : null
                                    ) ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="plan-drop-placeholder" id="plan-drop-placeholder" style="<?= !empty($items) ? 'display:none;' : '' ?>">
                            <i class="fas fa-arrow-right"></i> Перетащите товары сюда
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?= Html::endForm() ?>
</div>

<style>
.plan-topbar-row {
    display: flex; align-items: center; gap: 24px; flex-wrap: wrap;
}
.plan-topbar-info { flex: 1; min-width: 240px; }
.plan-topbar-title { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
.plan-stat-inline {
    display: flex; align-items: center; gap: 10px;
    background: #eef4ff; border-radius: 10px; padding: 8px 16px;
}
.plan-stat-icon { font-size: 20px; color: #3b6fd6; }
.plan-stat-value { font-size: 20px; font-weight: 700; color: #3b6fd6; line-height: 1; }
.plan-stat-label { font-size: 11px; color: #6c8ac4; margin-top: 1px; }

.plan-defaults-row {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    margin-top: 14px; padding-top: 14px; border-top: 1px dashed #e2e6ea;
}
.plan-defaults-label { font-size: 12px; color: #868e96; font-weight: 600; white-space: nowrap; }
.plan-default-field {
    display: flex; flex-direction: column; font-size: 10.5px; color: #868e96;
    text-transform: uppercase; letter-spacing: .02em; gap: 3px;
}
.plan-default-field input {
    width: 64px; padding: 4px 6px; border: 1px solid #dee2e6; border-radius: 5px; font-size: 13px; text-align: center;
}
.plan-defaults-note { margin-left: auto; font-size: 11.5px; }

.plan-form-panel-header, .wb-panel-header {
    padding: 12px 16px; font-weight: 600; border-bottom: 1px solid #eee;
    background: #f8f9fb; border-radius: 10px 10px 0 0;
}
.plan-topbar, .wb-panel { border: none; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); overflow: hidden; }

.wb-column-header {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 12px; text-transform: uppercase; letter-spacing: .02em; color: #868e96;
    margin-bottom: 6px; font-weight: 600;
}
.wb-available-list { max-height: calc(100vh - 5px); overflow-y: auto; border: 1px solid #eee; border-radius: 8px; }
.wb-available-list td { font-size: 13px; }
.wb-available-table th { font-size: 11px; text-transform: uppercase; color: #868e96; }
.wb-draggable-row { cursor: grab; }
.wb-draggable-row:hover { background: #f5f8ff; }

.plan-drop-zone {
    min-height: 560px; border: 2px dashed #cfd8e8; border-radius: 8px; padding: 8px; position: relative;
}
.plan-drop-zone.drag-over { border-color: #3b6fd6; background: #f5f8ff; }
.plan-items-table { margin-bottom: 0; font-size: 13px; }
.plan-items-table th { font-size: 10.5px; text-transform: uppercase; color: #868e96; text-align: center; }
.plan-items-table td { vertical-align: middle; }
.plan-items-table input[type=number] { width: 55px; text-align: center; padding: 3px 2px; }
.plan-item-vendor { font-weight: 600; font-size: 12.5px; }
.plan-item-title { font-size: 11.5px; color: #868e96; }
.plan-remove-btn {
    border: none; background: none; color: #dc3545; font-size: 18px; line-height: 1; cursor: pointer;
}
.plan-drop-placeholder { text-align: center; color: #adb5bd; padding: 60px 10px; font-size: 13px; }
.plan-company-cell {
    font-size: 11px; font-weight: 600; text-transform: uppercase; color: #495057;
    text-align: center; white-space: nowrap;
}
.plan-row-flash { animation: planFlash 0.6s ease; }
@keyframes planFlash { 0% { background: #fff3cd; } 100% { background: transparent; } }


.wb-available-list td, 
.rwb-available-list th {
    padding-left: 3px !important;
    padding-right: 3px !important;
    font-size: 12px;
}
</style>

<script>
(function () {
    var FALLBACK_DEFAULTS = <?= $fallbackDefaultsJson ?>;
    var SHOW_COMPANY_COLUMN = <?= $showCompanyColumn ? 'true' : 'false' ?>;
    var STORAGE_KEY = 'productionPlanDefaults';
    var FIELDS = ['production_days', 'logistics_smolensk_days', 'logistics_wb_days', 'buffer_days', 'target_coverage_days'];

    function loadStoredDefaults() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            var ok = FIELDS.every(function (f) { return typeof parsed[f] !== 'undefined'; });
            return ok ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function currentDefaults() {
        var result = {};
        FIELDS.forEach(function (f) {
            var el = document.getElementById('default-' + f);
            result[f] = parseInt(el.value, 10) || 0;
        });
        return result;
    }

    function initDefaultInputs() {
        var stored = loadStoredDefaults() || FALLBACK_DEFAULTS;
        FIELDS.forEach(function (f) {
            document.getElementById('default-' + f).value = stored[f];
        });
    }

    function persistDefaults() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(currentDefaults()));
        } catch (e) { /* localStorage недоступен - молча игнорируем */ }
    }

    FIELDS.forEach(function (f) {
        document.getElementById('default-' + f).addEventListener('change', persistDefaults);
    });
    initDefaultInputs();

    window.planDrag = function (ev) {
        ev.dataTransfer.setData('nmid', ev.currentTarget.dataset.nmid);
        ev.dataTransfer.setData('vendorcode', ev.currentTarget.dataset.vendorcode);
        ev.dataTransfer.setData('title', ev.currentTarget.dataset.title);
        ev.dataTransfer.setData('company', ev.currentTarget.dataset.company || '');
    };

    window.planAllowDrop = function (ev) {
        ev.preventDefault();
        document.getElementById('plan-drop-zone').classList.add('drag-over');
    };

    window.planDrop = function (ev) {
        ev.preventDefault();
        document.getElementById('plan-drop-zone').classList.remove('drag-over');
        var nmid = ev.dataTransfer.getData('nmid');
        var vendorcode = ev.dataTransfer.getData('vendorcode');
        var title = ev.dataTransfer.getData('title');
        var company = ev.dataTransfer.getData('company');
        addPlanRow(nmid, vendorcode, title, company);
    };

    function addPlanRow(nmid, vendorcode, title, company) {
        if (!nmid) return;
        var body = document.getElementById('plan-items-body');
        if (body.querySelector('tr[data-nmid="' + nmid + '"]')) {
            var existing = body.querySelector('tr[data-nmid="' + nmid + '"]');
            existing.classList.add('plan-row-flash');
            setTimeout(function () { existing.classList.remove('plan-row-flash'); }, 600);
            return;
        }

        var defaults = currentDefaults();
        var shortTitle = title.length > 45 ? title.slice(0, 45) + '...' : title;
        var tr = document.createElement('tr');
        tr.dataset.nmid = nmid;
        var companyCell = SHOW_COMPANY_COLUMN
            ? '<td class="plan-company-cell">' + escapeHtml(company || '—') + '</td>'
            : '';
        tr.innerHTML =
            '<td><div class="plan-item-vendor">' + escapeHtml(vendorcode) + ' <span class="text-muted">/ ' + escapeHtml(nmid) + '</span></div>' +
            '<div class="plan-item-title">' + escapeHtml(shortTitle) + '</div></td>' +
            companyCell +
            numberCell('production_days', defaults.production_days) +
            numberCell('logistics_smolensk_days', defaults.logistics_smolensk_days) +
            numberCell('logistics_wb_days', defaults.logistics_wb_days) +
            numberCell('buffer_days', defaults.buffer_days) +
            numberCell('target_coverage_days', defaults.target_coverage_days) +
            '<td><button type="button" class="plan-remove-btn" title="Убрать из плана">&times;</button></td>';

        function numberCell(field, val) {
            return '<td><input type="number" class="form-control form-control-sm" name="ProductionPlanItem[' + nmid + '][' + field + ']" value="' + val + '" min="0"></td>';
        }

        body.appendChild(tr);
        updateCountAndPlaceholder();
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function updateCountAndPlaceholder() {
        var rows = document.querySelectorAll('#plan-items-body tr');
        document.getElementById('plan-count').textContent = rows.length;
        document.getElementById('plan-drop-placeholder').style.display = rows.length ? 'none' : '';
    }

    document.getElementById('plan-items-table').addEventListener('click', function (ev) {
        if (ev.target.classList.contains('plan-remove-btn')) {
            ev.target.closest('tr').remove();
            updateCountAndPlaceholder();
        }
    });

    document.getElementById('add-all-visible').addEventListener('click', function () {
        document.querySelectorAll('.wb-draggable-row').forEach(function (row) {
            addPlanRow(row.dataset.nmid, row.dataset.vendorcode, row.dataset.title, row.dataset.company);
        });
    });

    function doSearch() {
        var params = {
            'WbCardSearch[nmID]': document.getElementById('wbsearch-nmid').value,
            'WbCardSearch[vendorCode]': document.getElementById('wbsearch-vendorcode').value,
            'WbCardSearch[title]': document.getElementById('wbsearch-title').value
        };
        $.pjax({ url: '?' + $.param(params), container: '#wb-grid-pjax', push: false });
    }
    document.getElementById('wb-search-btn').addEventListener('click', doSearch);
    ['wbsearch-nmid', 'wbsearch-vendorcode', 'wbsearch-title'].forEach(function (id) {
        document.getElementById(id).addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
        });
    });

    updateCountAndPlaceholder();
})();
</script>
