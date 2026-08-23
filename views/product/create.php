<?php

/** @var yii\web\View $this */
/** @var \app\models\Product $model */
/** @var array $productTypes */
/** @var array $brands */
/** @var \app\models\WbCardSearch $wbSearchModel */
/** @var \yii\data\ActiveDataProvider $wbDataProvider */

use yii\helpers\Html;
use kartik\form\ActiveForm;
use kartik\builder\Form;
use kartik\widgets\Select2;
use kartik\grid\GridView;

$this->title = $model->isNewRecord ? 'Новый товар' : 'Редактирование товара';
$this->params['breadcrumbs'][] = 'Справочники';
$this->params['breadcrumbs'][] = ['label' => 'Товары', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="product-create">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(['id' => 'product-form']); ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card card-default">
                <div class="card-header">Основные характеристики</div>
                <div class="card-body">
                    <?php
                    echo Form::widget([
                        'model' => $model,
                        'form' => $form,
                        'columns' => 1,
                        'attributes' => [
                            'name' => [
                                'type' => Form::INPUT_TEXTAREA, // Меняем тип на TEXTAREA
                                'label' => 'Название',
                                'options' => [
                                    'placeholder' => 'Введите название товара...',
                                    'rows' => 2, // Устанавливаем высоту в 2 строки
                                    'style' => 'resize: none;' // Опционально: запрещаем растягивать поле вручную
                                ]],
                            'product_type_id' => [
                                'type' => Form::INPUT_WIDGET,
                                'label' => 'Тип товара',
                                'widgetClass' => Select2::class,
                                'options' => [
                                    'data' => $productTypes,
                                    'options' => ['placeholder' => 'Выберите тип...'],
                                    'pluginOptions' => ['allowClear' => true],
                                ],
                            ],
                            'brand_id' => [
                                'type' => Form::INPUT_WIDGET,
                                'label' => 'Бренд',
                                'widgetClass' => Select2::class,
                                'options' => [
                                    'data' => $brands,
                                    'options' => ['placeholder' => 'Выберите бренд...'],
                                    'pluginOptions' => ['allowClear' => true],
                                ],
                            ],
                            'row_prices' => [
                                'attributes' => [
                                    'cost' => [
                                        'type' => Form::INPUT_TEXT,
                                        'label' => 'Цена (руб.)',
                                        'options' => ['placeholder' => 'Цена'],
                                        'columnOptions' => ['colspan' => 1],
                                    ],
                                    'weight' => [
                                        'type' => Form::INPUT_TEXT,
                                        'label' => 'Вес (кг)',
                                        'options' => ['placeholder' => 'Вес'],
                                        'columnOptions' => ['colspan' => 1],
                                    ],
                                    'vat_rate' => [
                                        'type' => Form::INPUT_TEXT, // Или INPUT_DROPDOWN_LIST, если есть выбор %
                                        'label' => 'НДС (%)',
                                        'options' => ['placeholder' => 'НДС'],
                                        'columnOptions' => ['colspan' => 1],
                                    ],
                                ],
                                // Указываем, что в этой "строке" должно быть 3 колонки
                                'columnOptions' => ['colspan' => 1], 
                                'columns' => 3, 
                            ],
                        ]
                    ]);
                    ?>
                </div>
            </div>
    <div class="alert alert-secondary mt-3">
        Выбрано карточек для привязки: <span id="debug-count">0</span>
    </div>

    <div class="form-group mt-3">
        <?= Html::submitButton('Сохранить продукт', ['class' => 'btn btn-success btn-lg']) ?>
    </div>

        </div>

        <div class="col-md-8">
            <div class="card card-primary">
                <div class="card-header">Привязка карточек Wildberries (Перетащите строку в список справа)</div>
                <div class="card-body">

                        <div class="row mb-3">
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
                                <button type="button" class="btn btn-info btn-sm btn-block" id="wb-search-btn">Найти</button>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-7">
                                <h6>Доступные (Drag me)</h6>
                                <div class="row mb-2">
                                    <div class="col-md-7">
                                        <?= Html::activeCheckbox($wbSearchModel, 'onlyUnused', [
                                            'id' => 'wbsearch-unused',
                                            'label' => 'Только свободные карточки',
                                            'checked' => true,
                                        ]) ?>
                                    </div>
                                    <button type="button" id="add-all-visible" class="btn btn-xs btn-primary col-md-5 disabled">
                                        <i class="fas fa-plus"></i> Добавить все
                                    </button>
                                </div>

                                <div style="max-height: 450px; overflow-y: auto; border: 1px solid #ddd; padding: 5px;">
                                    <?php \yii\widgets\Pjax::begin(['id' => 'wb-grid-pjax', 'enablePushState' => false]); ?>
                                        <?= GridView::widget([
                                            'dataProvider' => $wbDataProvider,
                                            'summary' => false,
                                            'tableOptions' => [
                                                    'class' => 'table table-sm table-hover',
                                                    'style' => 'font-size: 11px;'
                                                    ],
                                            
                                            'rowOptions' => function($model) {
                                                return [
                                                    'draggable' => 'true',
                                                    'ondragstart' => 'drag(event)',
                                                    'data-nmid' => $model->nmID,
                                                    'data-vendorCode' => $model->vendorCode,
                                                    'data-title' => $model->title,
                                                    'style' => 'cursor: move;'
                                                ];
                                            },
                                            'columns' => [
                                                [
                                                    'attribute' => 'nmID',
                                                    'label' => 'Арт WB', 
                                                    'headerOptions' => ['style' => 'width: 20%;'],
                                                    'contentOptions' => ['style' => 'font-size: 11px;'],
                                                ],
                                                [
                                                    'attribute' => 'vendorCode',
                                                    'label' => 'Артикул',
                                                    'headerOptions' => ['style' => 'width: 20%;'],
                                                    'contentOptions' => ['style' => 'white-space: normal; word-wrap: break-word; word-break: break-all; font-size: 11px;'],
                                                ],
                                                [
                                                    'attribute' => 'title',
                                                    'label' => 'Название',
                                                    'headerOptions' => ['style' => 'width: 60%;'],
                                                    'contentOptions' => ['style' => 'white-space: normal; word-wrap: break-word; font-size: 11px;'],
                                                    'value' => function($model) { return mb_strimwidth($model->title, 0, 75, "..."); }
                                                ],
                                            ],
                                        ]) ?>
                                    <?php \yii\widgets\Pjax::end(); ?>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <h6>Выбранные (Drop here)</h6>
                                <div id="wb-drop-zone" 
                                     ondrop="drop(event)" 
                                     ondragover="allowDrop(event)"
                                     style="min-height: 450px; border: 2px dashed #337ab7; border-radius: 5px; padding: 10px; background: #f9f9f9;">
                                    
                                    <ul id="wb-selected-list" class="list-unstyled">
                                        <?php if (!empty($selectedCards)): ?>
                                            <?php foreach ($selectedCards as $card): ?>
                                                <li class="alert alert-info p-1 mb-1 d-flex justify-content-between align-items-center result_13px" data-nmid="<?= Html::encode($card->nmID) ?>">
                                                    <small>
                                                        <strong><?= Html::encode($card->nmID) ?></strong> - <?= Html::encode($card->vendorCode) ?><br/>
                                                        <?= Html::encode($card->title) ?>
                                                    </small>
                                                    <button type="button" class="btn btn-xs btn-danger wb-remove-card">&times;</button>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </ul>



    <div id="wb-hidden-inputs">
        <?php if (!empty($model->wbCardIds)): ?>
            <?php foreach ($model->wbCardIds as $nmId): ?>
                <input type="hidden" name="Product[wbCardIds][]" value="<?= $nmId ?>" data-nmid="<?= $nmId ?>">
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
            </div>
        </div>
    </div>


<?php /*
    <div class="alert alert-secondary mt-3">
        Выбрано карточек для привязки: <span id="debug-count">0</span>
    </div>

    <div class="form-group mt-3">
        <?= Html::submitButton('Сохранить продукт', ['class' => 'btn btn-success btn-lg']) ?>
    </div>
*/ ?>
    <?php ActiveForm::end(); ?>
</div>

<?php
$js = <<<JS

// Функция для обновления отладочной информации
function updateDebugInfo() {
    var count = $('#wb-hidden-inputs input').length;
    $('#debug-count').text(count);
    
    // Выводим текущий список ID в консоль браузера (F12 -> Console)
    var values = [];
    $('#wb-hidden-inputs input').each(function() {
        values.push($(this).val());
    });
    console.log("Текущие ID в скрытых полях:", values);
}

// 1. Глобальные функции для Drag and Drop (обязательно через window)
window.allowDrop = function(ev) {
    ev.preventDefault();
}

window.drag = function(ev) {
    var tr = ev.target.closest('tr');
    var data = {
        nmid: tr.getAttribute('data-nmid'),
        vendorCode: tr.getAttribute('data-vendorCode'),
        title: tr.getAttribute('data-title')
    };
    ev.dataTransfer.setData("text", JSON.stringify(data));
}

/*
window.drop = function(ev) {
    ev.preventDefault();
    var dataRaw = ev.dataTransfer.getData("text"); // Исправлено: только getData
    try {
        var data = JSON.parse(dataRaw);
        addCard(data.nmid, data.title);
    } catch(e) {
        console.error("Ошибка парсинга данных Drag-and-Drop", e);
    }
}
*/

window.drop = function(ev) {
    ev.preventDefault();
    var dataRaw = ev.dataTransfer.getData("text");
    if (dataRaw) {
        try {
            var data = JSON.parse(dataRaw);
            addCard(data.nmid, data.vendorCode, data.title);
        } catch(e) {
            console.error("Ошибка парсинга:", e);
        }
    }
}

// 2. Вспомогательная функция добавления карточки
function addCard(nmId, vendorCode, title) {
    var list = $('#wb-selected-list');
    var hidden = $('#wb-hidden-inputs');

    if (list.find('li[data-nmid="' + nmId + '"]').length > 0) {
        console.warn("Карточка " + nmId + " уже добавлена");
        return;
    }

    // Добавляем визуальный элемент
    var li = $('<li class="alert alert-info p-1 mb-1 d-flex justify-content-between align-items-center result_13px" data-nmid="' + nmId + '">' +
        '<small><strong>' + nmId + '</strong> - '+ vendorCode + '<br />'+ title + ' </small>' +
        '<button type="button" class="btn btn-xs btn-danger wb-remove-card">&times;</button>' +
    '</li>');
    list.append(li);

    // Добавляем скрытый инпут для отправки на сервер
    var input = $('<input type="hidden" name="Product[wbCardIds][]" value="' + nmId + '" data-nmid="' + nmId + '">');
    hidden.append(input);

    updateDebugInfo(); // Обновляем проверку
}

// 3. Удаление карточки
$(document).on('click', '.wb-remove-card', function() {
    var li = $(this).closest('li');
    var nmId = li.data('nmid');
    $('input[data-nmid="' + nmId + '"]').remove(); // Удаляем скрытый инпут
    li.remove(); // Удаляем визуальный элемент

    updateDebugInfo(); // Обновляем проверку
});

// 4. Обработка поиска через Pjax
$('#wb-search-btn').on('click', function(e) {
    e.preventDefault();
    $.pjax.reload({
        container: '#wb-grid-pjax',
        timeout: 5000,
        data: {
            'WbCardSearch[nmID]': $('#wbsearch-nmid').val().trim(),
            'WbCardSearch[vendorCode]': $('#wbsearch-vendorcode').val().trim(),
            'WbCardSearch[title]': $('#wbsearch-title').val().trim(),
            'WbCardSearch[onlyUnused]': $('#wbsearch-unused').is(':checked') ? 1 : 0
        }
    });
});

// 5. Блокировка Enter для сохранения формы
$('#product-form').on('keydown', function(e) {
    if (e.keyCode === 13) {
        if ($(e.target).is('#wbsearch-nmid, #wbsearch-vendorcode, #wbsearch-title')) {
            e.preventDefault();
            $('#wb-search-btn').click();
            return false;
        }
        if (e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            return false;
        }
    }
});

$(document).on('click', '#add-all-visible', function() {
    // Ищем все строки в таблице внутри Pjax-контейнера
    $('#wb-grid-pjax table tbody tr').each(function() {
        var row = $(this);
        var nmId = row.data('nmid');
        var title = row.data('title');
        var vendorCode = row.find('td:eq(1)').text().trim(); // Берем артикул из второй колонки

        // Если nmId существует (проверка на пустые строки "Ничего не найдено")
        if (nmId) {
            addCard(nmId, vendorCode, title);
        }
    });
    
    // Опционально: выводим уведомление
    console.log('Все видимые карточки добавлены');
});

$(document).on('pjax:end', function(container) {
    if (container.target.id === 'wb-grid-pjax') {
        // Проверяем, есть ли в таблице данные (не пустая ли она)
        var hasRows = $('#wb-grid-pjax table tbody tr[data-nmid]').length > 0;
        var btn = $('#add-all-visible');
        if (hasRows) {
            btn.prop('disabled', false).removeClass('disabled');
        } else {
            btn.prop('disabled', true).addClass('disabled');
        }
    }
});

$('#wbsearch-nmid, #wbsearch-vendorcode, #wbsearch-title, #wbsearch-unused').on('input change', function() {
    $('#add-all-visible').prop('disabled', true).addClass('disabled');
});

JS;

// Регистрируем функции в глобальной области видимости для inline-событий ondrop/ondrag
$this->registerJs($js, \yii\web\View::POS_END);
?>