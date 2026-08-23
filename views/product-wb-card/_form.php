<?php
use yii\helpers\Html;
use kartik\form\ActiveForm;
use kartik\widgets\Select2;
use app\models\Product;
use app\models\WbCard;
use yii\helpers\ArrayHelper;

use kartik\icons\Icon;
Icon::map($this); 


/** @var app\models\ProductWbCard $model */
/** @var app\models\ProductWbCard[] $existingItems */

$existingItems = $existingItems ?? []; 

$productsData = ArrayHelper::map(
    Product::find()->orderBy(['name' => SORT_ASC])->all(), 
    'id', 
    'name'
);
?>

<div class="product-wb-card-form">

    <?php $form = ActiveForm::begin(['id' => 'dynamic-form']); ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light"><b>1. Выбор карточки WB</b></div>
        <div class="card-body">
            <?= $form->field($model, 'wb_nm_id')->widget(Select2::class, [
                'data' => ArrayHelper::map(WbCard::find()->all(), 'nmID', function($card) {
                    return "{$card->nmID} | {$card->vendorCode} | {$card->title}";
                }),
                'options' => ['placeholder' => 'Введите nmID, артикул или название...'],
                'pluginOptions' => [
                    'allowClear' => true,
                    'minimumInputLength' => 2,
                ],
            ])->label(false) ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <b>2. Привязанные товары</b>
            <button type="button" class="btn btn-success btn-sm add-row">
                <i class="fas fa-plus"></i> Добавить товар
            </button>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0" id="products-table">
                <thead class="thead-light">
                    <tr>
                        <th>Товар</th>
                        <th width="150">Количество</th>
                        <th width="150">Процент</th>
                        <th width="50"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($existingItems)): ?>
                        <tr class="product-row">
                            <td>
                                <?= Select2::widget([
                                    'name' => 'Items[0][product_id]',
                                    'data' => $productsData,
                                    'options' => ['placeholder' => 'Выберите товар...', 'class' => 'product-select-input'],
                                    'pluginOptions' => ['allowClear' => true],
                                ]) ?>
                            </td>
                            <td><?= Html::textInput('Items[0][q]', 0, ['class' => 'form-control', 'type' => 'number']) ?></td>
                            <td><?= Html::textInput('Items[0][p]', 100, ['class' => 'form-control p-input', 'type' => 'number', 'step' => '0.01']) ?></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($existingItems as $index => $item): ?>
                            <tr class="product-row">
                                <td>
                                    <?= Select2::widget([
                                        'name' => "Items[$index][product_id]",
                                        'value' => $item->product_id,
                                        'data' => $productsData,
                                        'options' => ['placeholder' => 'Выберите товар...', 'class' => 'product-select-input'],
                                        'pluginOptions' => ['allowClear' => true],
                                    ]) ?>
                                </td>
                                <td><?= Html::textInput("Items[$index][q]", $item->q, ['class' => 'form-control', 'type' => 'number']) ?></td>
                                <td><?= Html::textInput("Items[$index][p]", $item->p, ['class' => 'form-control p-input', 'type' => 'number', 'step' => '0.01']) ?></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-times"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="form-group mt-4">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-primary btn-lg']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php
$totalExisting = count($existingItems);
$rowIdx = $totalExisting > 0 ? $totalExisting : 1;
$productsJson = json_encode($productsData);

$script = <<< JS
    var rowIdx = {$rowIdx};
    var productsData = {$productsJson};

// --- ЗАЩИТА ОТ ENTER ---
    $('#dynamic-form').on('keyup keypress', function(e) {
        var keyCode = e.keyCode || e.which;
        if (keyCode === 13) { 
            var target = $(e.target);
            // Если мы не в кнопке "Сохранить" и не в многострочном текстовом поле
            if (!target.is(':submit') && !target.is('textarea')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Функция пересчета процентов
    function recalculatePercents() {
        var rows = $('#products-table tbody tr');
        if (rows.length === 0) return;

        var totalOther = 0;
        var allRows = rows.get();
        var lastRow = allRows.pop(); // Забираем последнюю строку из массива

        // Суммируем все строки, кроме последней
        $(allRows).each(function() {
            var val = parseFloat($(this).find('.p-input').val()) || 0;
            totalOther += val;
        });

        // Устанавливаем остаток в последнюю строку
        var finalVal = (100 - totalOther).toFixed(2);
        $(lastRow).find('.p-input').val(finalVal);
    }

    // Слушатель изменения в полях Процент
    $(document).on('input', '.p-input', function() {
        recalculatePercents();
    });

    $('.add-row').on('click', function() {
        var selectId = 'select-product-' + rowIdx;
        
        var newRow = `
            <tr class="product-row">
                <td>
                    <select id="\${selectId}" name="Items[\${rowIdx}][product_id]" class="form-control">
                        <option value="">Выберите товар...</option>
                    </select>
                </td>
                <td><input type="number" name="Items[\${rowIdx}][q]" class="form-control" value="0"></td>
                <td><input type="number" name="Items[\${rowIdx}][p]" class="form-control p-input" value="0" step="0.01"></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-times"></i></button></td>
            </tr>`;
        
        $('#products-table tbody').append(newRow);

        var newSelect = $('#' + selectId);
        $.each(productsData, function(id, name) {
            newSelect.append(new Option(name, id, false, false));
        });

        newSelect.select2({
            theme: "krajee-bs4",
            width: "100%",
            placeholder: "Выберите товар...",
            allowClear: true
        });

        rowIdx++;
        recalculatePercents(); // Пересчитываем при добавлении
    });

    $(document).on('click', '.remove-row', function() {
        $(this).closest('tr').remove();
        recalculatePercents(); // Пересчитываем при удалении
    });
JS;
$this->registerJs($script);
?>