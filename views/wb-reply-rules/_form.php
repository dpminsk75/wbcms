<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;

use kartik\icons\Icon;
Icon::map($this); 

/** @var yii\web\View $this */
/** @var app\models\WbReplyRule $model */
/** @var app\models\WbReplyTemplatePart[] $greetings */
/** @var app\models\WbReplyTemplatePart[] $bodies */
/** @var app\models\WbReplyTemplatePart[] $signoffs */
/** @var array $selectedBrands Передается из actionUpdate (опционально) */
/** @var array $selectedProducts Передается из actionUpdate (опционально) */
?>

<div class="wb-reply-rule-form">

    <?php $form = ActiveForm::begin(['id' => 'dynamic-form']); ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">Условия применения</h5>
        </div>
        <div class="card-body">
            
            <?= $form->field($model, 'title')->textInput(['maxlength' => true, 'placeholder' => 'Например: Отзыв на 5 звезд без текста']) ?>

            <div class="row">
                <div class="col-md-4">
                    <?= $form->field($model, 'rule_type', [
                        'options' => ['class' => 'form-group rule-type-container']
                    ])->radioList([
                        'general' => 'Общее',
                        'brand' => 'Для брендов',
                        'product' => 'Для товаров',
                    ], [
                        'item' => function($index, $label, $name, $checked, $value) {
                            $chk = $checked ? 'checked' : '';
                            $activeClass = $checked ? 'active btn-secondary' : 'btn-outline-secondary';
                            return "<label class='btn {$activeClass} me-2'><input type='radio' name='{$name}' value='{$value}' {$chk} style='display:none;'> {$label}</label>";
                        },
                        'class' => 'btn-group d-flex',
                    ])->label('Тип правила') ?>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Рейтинг отзыва</label>
                    <div class="d-flex align-items-center">
                        <?= $form->field($model, 'rating_min', ['options' => ['class' => 'mb-0']])->dropDownList(array_combine(range(1, 5), range(1, 5)))->label(false) ?>
                        <span class="mx-2">—</span>
                        <?= $form->field($model, 'rating_max', ['options' => ['class' => 'mb-0']])->dropDownList(array_combine(range(1, 5), range(1, 5)))->label(false) ?>
                    </div>
                </div>

                <div class="col-md-4">
                    <?= $form->field($model, 'text_condition')->radioList([
                        'with_text' => 'С текстом',
                        'no_text' => 'Без текста',
                        'any' => 'Любой',
                    ], [
                        'item' => function($index, $label, $name, $checked, $value) {
                            $chk = $checked ? 'checked' : '';
                            $activeClass = $checked ? 'active btn-secondary' : 'btn-outline-secondary';
                            return "<label class='btn {$activeClass} me-2'><input type='radio' name='{$name}' value='{$value}' {$chk} style='display:none;'> {$label}</label>";
                        },
                        'class' => 'btn-group d-flex',
                    ])->label('Содержимое отзыва') ?>
                </div>
            </div>

            <div id="filter-brand-block" class="mt-4 dynamic-filter-block" style="display: none;">
                <div class="card bg-light border-secondary">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Выберите бренды, для которых действует правило:</label>
                                
                                <select name="selected_brands[]" id="brand-search-select" class="form-control" multiple="multiple" style="width: 100%;">
                                    <?php
                                    if (!empty($selectedBrands)) {
                                        foreach ($selectedBrands as $brandName) {
                                            echo "<option value='" . Html::encode($brandName) . "' selected='selected'>" . Html::encode($brandName) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <div class="small text-muted mt-1"><i class="fa fa-search"></i> Просто начните вводить название бренда — система найдет его автоматически.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="filter-product-block" class="mt-4 dynamic-filter-block" style="display: none;">
                <div class="card bg-light border-secondary">
                    <div class="card-body">
                        <label class="form-label fw-bold">Выберите товары (поиск по nmID или названию):</label>
                        
                        <select name="selected_products[]" id="product-search-select" class="form-control" multiple="multiple" style="width: 100%;">
                            <?php
                            if (!empty($selectedProducts)) {
                                $productIds = array_map('strval', $selectedProducts);
                                
                                $savedProducts = (new \yii\db\Query())
                                    ->select(['nmID', 'title'])
                                    ->from('wbcards')
                                    ->where(['nmID' => $productIds])
                                    ->all();

                                foreach ($savedProducts as $p) {
                                    echo "<option value='" . strval($p['nmID']) . "' selected='selected'>[" . Html::encode($p['nmID']) . "] " . Html::encode($p['title']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="small text-muted mt-1"><i class="fa fa-search"></i> Просто начните вводить название книги, журнала или nmID — система найдет товар автоматически.</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">Текст ответа</h5>
        </div>
        <div class="card-body">
            
            <?php 
            $renderTextGroup = function($title, $parts, $formNameKey) {
                echo "<div class='text-part-group mb-4' data-key='{$formNameKey}'>";
                echo Html::tag('label', $title, ['class' => 'form-label fw-bold mb-2']);
                
                echo "<div class='container-items'>";
                foreach ($parts as $index => $part) {
                    echo "<div class='item d-flex align-items-start mb-2'>";
                    echo Html::textarea("WbReplyTemplatePart[{$formNameKey}][{$index}][text]", $part->text, [
                        'class' => 'form-control me-2', 
                        'rows' => 3,
                        'placeholder' => 'Введите вариант текста...'
                    ]);
                    echo "<button type='button' class='btn btn-outline-danger remove-item'><i class='fa fa-trash'></i></button>";
                    echo "</div>";
                }
                echo "</div>";
                
                echo Html::button('<i class="fa fa-plus"></i> Добавить вариант текста', [
                    'class' => 'btn btn-sm btn-link add-item text-decoration-none p-0 mt-1',
                    'data-type' => $formNameKey
                ]);
                echo "</div>";
            };

            $renderTextGroup('Приветствие', $greetings, 'greetings');
            $renderTextGroup('Основная часть', $bodies, 'bodies');
            $renderTextGroup('Прощание', $signoffs, 'signoffs');
            ?>

            <div class="alert alert-info py-2 small mb-4">
                Чтобы вставить имя пользователя, оставившего отзыв, используйте шаблон <code>{{имя}}</code>. Такой шаблон не будет использован, если имя неизвестно.
            </div>

            <?= $form->field($model, 'part_separator')->radioList([
                'space' => 'Пробел',
                'newline' => 'Новая строка',
                'paragraph' => 'Новый абзац',
            ], [
                'item' => function($index, $label, $name, $checked, $value) {
                    $chk = $checked ? 'checked' : '';
                    $activeClass = $checked ? 'active btn-secondary' : 'btn-outline-secondary';
                    return "<label class='btn {$activeClass} me-2'><input type='radio' name='{$name}' value='{$value}' {$chk} style='display:none;'> {$label}</label>";
                },
                'class' => 'btn-group d-flex',
            ])->label('Разделитель частей') ?>

        </div>
    </div>

    <div class="form-group mt-3">
        <?= Html::submitButton('Сохранить правило', ['class' => 'btn btn-success btn-lg px-5']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php
$js = <<<JS
function toggleFilterBlocks(value) {
    $('.dynamic-filter-block').hide();
    if (value === 'brand') {
        $('#filter-brand-block').fadeIn(200);
    } else if (value === 'product') {
        $('#filter-product-block').fadeIn(200);
    }
}

$('.rule-type-container').on('change', 'input[type="radio"]', function() {
    $(this).closest('.btn-group').find('.btn').removeClass('active btn-secondary').addClass('btn-outline-secondary');
    $(this).closest('label').addClass('active btn-secondary').removeClass('btn-outline-secondary');
    toggleFilterBlocks($(this).val());
});

$('input[name="WbReplyRule[text_condition]"], input[name="WbReplyRule[part_separator]"]').closest('.btn-group').on('change', 'input[type="radio"]', function() {
    $(this).closest('.btn-group').find('.btn').removeClass('active btn-secondary').addClass('btn-outline-secondary');
    $(this).closest('label').addClass('active btn-secondary').removeClass('btn-outline-secondary');
});

var initialType = $('.rule-type-container input[type="radio"]:checked').val();
toggleFilterBlocks(initialType);

if (typeof $.fn.select2 === 'undefined') {
    $('head').append('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />');
    $.getScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', function() {
        initProductSelect2();
        initBrandSelect2();
    });
} else {
    initProductSelect2();
    initBrandSelect2();
}

function initProductSelect2() {
    if ($('#select2-custom-wide-styles').length === 0) {
        $('head').append('<style id="select2-custom-wide-styles">' +
            '.select2-container { width: 100% !important; }' +
            '.select2-container--default .select2-selection--multiple { border: 1px solid #ced4da; border-radius: 0.375rem; min-height: 45px; padding: 4px; }' +
            '.select2-container--default .select2-selection--multiple .select2-selection__choice { max-width: 95%; white-space: normal; background-color: #fff8e1; border: 1px solid #ffe082; color: #000; padding: 2px 8px; font-size: 14px; margin-bottom: 4px; }' +
            '.select2-container--default .select2-selection--multiple .select2-selection__choice__remove { color: #999; margin-right: 5px; }' +
            '.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover { color: #333; }' +
            '</style>');
    }

    var selectObj = $('#product-search-select');
    selectObj.select2({
        placeholder: 'Начните вводить название или nmID...',
        minimumInputLength: 2,
        width: '100%',
        language: {
            inputTooShort: function() { return "Введите еще минимум 2 символа"; },
            searching: function() { return "Ищем..."; },
            noResults: function() { return "Ничего не найдено"; }
        },
        ajax: {
            url: '/wb-reply-rules/product-list',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results }; },
            cache: true
        }
    });
    selectObj.trigger('change');
}

function initBrandSelect2() {
    var selectBrandObj = $('#brand-search-select');
    selectBrandObj.select2({
        placeholder: 'Начните вводить название бренда...',
        minimumInputLength: 1,
        width: '100%',
        language: {
            inputTooShort: function() { return "Введите еще минимум 1 символ"; },
            searching: function() { return "Ищем..."; },
            noResults: function() { return "Ничего не найдено"; }
        },
        ajax: {
            url: '/wb-reply-rules/brand-list',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results }; },
            cache: true
        }
    });
    selectBrandObj.trigger('change');
}

$('.wb-reply-rule-form').on('click', '.add-item', function() {
    var group = $(this).closest('.text-part-group');
    var container = group.find('.container-items');
    var type = $(this).data('type');
    var index = container.find('.item').length;
    
    var newItem = $('<div class="item d-flex align-items-start mb-2">' +
        '<textarea name="WbReplyTemplatePart[' + type + '][' + index + '][text]" class="form-control me-2" rows="3" placeholder="Введите вариант текста..."></textarea>' +
        '<button type="button" class="btn btn-outline-danger remove-item"><i class="fa fa-trash"></i></button>' +
    '</div>');
    
    container.append(newItem);
});

$('.wb-reply-rule-form').on('click', '.remove-item', function() {
    var container = $(this).closest('.container-items');
    if (container.find('.item').length > 1) {
        $(this).closest('.item').remove();
    } else {
        alert('Должен остаться хотя бы один вариант текста для данной секции!');
    }
});
JS;
$this->registerJs($js);
?>

<style>
.select2-container--default .select2-selection--multiple .select2-selection__choice__display {
    cursor: default;
    padding-left: 10px;
    padding-right: 5px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    border: none;
    border-right: none;
}
</style>