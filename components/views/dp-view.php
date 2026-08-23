<?php
use yii\widgets\ActiveForm;
use yii\helpers\Html;
use kartik\select2\Select2;
use kartik\date\DatePicker;

use kartik\daterange\DateRangePicker;
use yii\web\JsExpression;

use kartik\icons\Icon;
Icon::map($this); 

use kartik\icons\FontAwesomeAsset;
FontAwesomeAsset::register($this);
?>
<div class="well div_bordered">
    <?php if (!empty($quickButtons)): ?>
        <div class="panel-btns" style="margin-bottom: 15px;">
            <?php foreach ($quickButtons as $button): ?>
                <?= $button ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <div class="wbcard_filter-section">
        <?php $form = ActiveForm::begin([
            'action' => $action,
            'method' => 'get',
            'options' => ['class' => 'form-inline'],
        ]); ?>
        <div class="form-group" style="min-width: 450px; margin-right: 15px;">
            <label>Карточка WB (артикул): </label>
            <?= $form->field($model, 'nm_id')->widget(Select2::classname(), [
                'data' => $cardsList,
                'options' => ['placeholder' => 'Выберите артикул...'],
                'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
            ])->label(false) ?>
        </div>

    <div class="row" style="align-items: center;">
        <div class="form-group form__input-dates col-md-6">
            <label>Период с / по</label>
            <?= $form->field($model, 'date_from')->widget(DatePicker::classname(), [
                'attribute2' => 'date_to', // Привязываем второе поле модели
                'type' => DatePicker::TYPE_RANGE,
                'separator' => ' | ',
                'options' => [
                    'style' => 'height: 38px;', 
                ],
                'options2' => [
                    'style' => 'height: 38px;', 
                ],
                'pluginOptions' => [
                    'autoclose' => true, 
                    'format' => 'yyyy-mm-dd',
                    'orientation' => 'bottom auto', 
                    'todayHighlight' => true
                ]
            ])->label(false) ?>

        </div>

        <div class="btn-group col-md-6" style="height: 38px; margin-bottom: 8px;">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('quarter')" title="Минус квартал">-Q</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('year')" title="Минус год">-Y</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('last_year')" title="Прошлый год">LY</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('today')" title="По сегодня">TD</button>
        </div>
    </div>

        <div class="form-group" style="margin-left: 10px; vertical-align: bottom; padding-bottom: 5px;">
            <?= Html::submitButton('Применить', ['class' => 'btn btn-primary btn_200px']) ?>
            <?= Html::a('Сбросить', $action,    ['class' => 'btn btn-light btn_200px', 'style' => 'margin-left:5px;']) ?>
        </div>
    </div>
</div>

<script>
    /*
function setDateRange(period) {
    // 1. Берем дату из поля "До". Если пусто — берем текущую
    let valTo = $('#date_to').val();
    let baseDate = valTo ? new Date(valTo) : new Date();

    if (isNaN(baseDate.getTime())) baseDate = new Date();

    let dateFrom = new Date(baseDate.getTime());
    let newTo = null; // Переменная для случая, если нужно изменить и поле "До"

    // Функция форматирования YYYY-MM-DD
    const formatDate = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };

    if (period === 'year') {
        // Ровно минус год + 1 день от текущего #date_to
        dateFrom.setFullYear(baseDate.getFullYear() - 1);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'quarter') {
        // Ровно минус 3 месяца + 1 день от текущего #date_to
        dateFrom.setMonth(baseDate.getMonth() - 3);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'last_year') {
        // ВЕСЬ ПРОШЛЫЙ КАЛЕНДАРНЫЙ ГОД
        const lastYear = baseDate.getFullYear() - 1;
        dateFrom = new Date(lastYear, 0, 1);  // 1 января прошлого года
        newTo = new Date(lastYear, 11, 31);    // 31 декабря прошлого года
    }

    // 2. Обновляем поле "От"
    $('#date_from').val(formatDate(dateFrom)).trigger('change');

    // 3. Если это "Прошлый год", обновляем и поле "До"
    if (newTo) {
        $('#date_to').val(formatDate(newTo)).trigger('change');
        if (typeof $('#date_to').kvDatepicker === 'function') {
            $('#date_to').kvDatepicker('update', formatDate(newTo));
        }
    }

    // Обновляем визуальный календарь для "От"
    if (typeof $('#date_from').kvDatepicker === 'function') {
        $('#date_from').kvDatepicker('update', formatDate(dateFrom));
    }
}
*/
</script>

<?php ActiveForm::end(); ?>
