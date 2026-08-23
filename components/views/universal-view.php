<?php
use yii\widgets\ActiveForm;
use yii\helpers\Html;
use kartik\select2\Select2;
use kartik\date\DatePicker;
use yii\web\JsExpression;

use kartik\icons\Icon;
Icon::map($this); 

use kartik\icons\FontAwesomeAsset;
FontAwesomeAsset::register($this);

?>
<div class="row card shadow p-3">
    <?php if (!empty($quickButtons)): ?>
        <div class="row mb-3 panel-btns">
            <?php foreach ($quickButtons as $button): ?>
                <?= $button ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row mb-3 wbcard_filter-section">
        <?php $form = ActiveForm::begin([
            'action' => $action,
            'method' => 'get',
            'options' => ['class' => 'form-inline'],
        ]); ?>

        <div class="row mb-3">
            <label><?= Html::encode($label) ?>: </label>
            <?php
            // 1. Собираем базовые настройки
            $select2Options = [
                'options' => [
                    'placeholder' => $placeholder,
                    'id' => Html::getInputId($model, $attribute),
                ],
                // Объединяем переданные pluginOptions с базовыми (allowClear и т.д.)
                'pluginOptions' => array_merge([
                    'allowClear' => true,
                    'minimumInputLength' => 3, // Значение по умолчанию для AJAX
                ], $pluginOptions),
            ];

            // 2. Если включен AJAX, добавляем специфичные настройки
            if (!empty($ajaxUrl)) {
                $select2Options['initValueText'] = $initValueText;
                $select2Options['pluginOptions']['ajax'] = [
                    'url' => $ajaxUrl,
                    'dataType' => 'json',
                    'delay' => 250,
                    'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                    'processResults' => new JsExpression('function(data) {
                        return { results: data.results };
                    }'),
                    'cache' => true
                ];
            } else {
                // Если AJAX нет — работаем по-старинке через массив data
                $select2Options['data'] = $data;
            }
            ?>

            <?= $form->field($model, $attribute)->widget(Select2::classname(), $select2Options)->label(false) ?>
        </div>

        <div class="row mb-3 d-flex flex-row flex-nowrap justify-content-between align-items-center">
            <div class="form__input-dates col-md-6">
                <label style="display: block;">Период с / по</label>
                <?= $form->field($model, 'date_from')->widget(DatePicker::classname(), [
                    'attribute2' => 'date_to',
                    'type' => DatePicker::TYPE_RANGE,
                    'separator' => ' | ',
                    'options' => ['style' => 'height: 38px;'],
                    'options2' => ['style' => 'height: 38px;'],
                    'pluginOptions' => [
                        'autoclose' => true, 
                        'format' => 'yyyy-mm-dd',
                        'todayHighlight' => true
                    ]
                ])->label(false) ?>
            </div>

            <div class="col-md-6 btn-group" style="height: 38px; margin-top: 8px;">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('quarter')" title="Минус квартал">-Q</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('year')" title="Минус год">-Y</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('last_year')" title="Прошлый год">LY</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('today')" title="По сегодня">TD</button>
            </div>
        </div>

        <div class="row form-group"> <div class="col-auto">
            <?= Html::submitButton('Применить', ['class' => 'btn btn-primary w-auto']) ?>
            <?= Html::a('Сбросить', $action, ['class' => 'btn btn-light w-auto', 'style' => 'margin-left:5px;']) ?>
        </div></div>

        <?php ActiveForm::end(); ?>
    </div>
</div>

<script>
/**
 * Функция быстрой установки дат
 */
/*
function setDateRange(period) {
    let dateToField = $('input[name$="[date_to]"]');
    let dateFromField = $('input[name$="[date_from]"]');
    
    let baseDate = dateToField.val() ? new Date(dateToField.val()) : new Date();
    if (isNaN(baseDate.getTime())) baseDate = new Date();

    let dateFrom = new Date(baseDate.getTime());

    const formatDate = (d) => d.toISOString().split('T')[0];

    if (period === 'year') {
        dateFrom.setFullYear(baseDate.getFullYear() - 1);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'quarter') {
        dateFrom.setMonth(baseDate.getMonth() - 3);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'today') {
        dateFrom = new Date();
        dateToField.val(formatDate(dateFrom)).trigger('change');
        if (typeof dateToField.kvDatepicker === 'function') dateToField.kvDatepicker('update', formatDate(dateFrom));
    }

    dateFromField.val(formatDate(dateFrom)).trigger('change');
    if (typeof dateFromField.kvDatepicker === 'function') dateFromField.kvDatepicker('update', formatDate(dateFrom));
}
*/
</script>
