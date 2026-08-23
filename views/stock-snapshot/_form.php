<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use yii\web\JqueryAsset;
use app\models\WbCard;

/** @var app\models\StockSnapshot $model */

$this->title = $model->isNewRecord ? 'Добавить снапшот' : 'Редактировать снапшот #' . $model->id;

$initialLabel = '';
if ($model->nm_id) {
    $card = WbCard::findOne($model->nm_id);
    if ($card) {
        $initialLabel = $card->vendorCode . ' / nmID ' . $card->nmID . ' — ' . $card->title;
    }
}

$this->registerCssFile('https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css');
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js', [
    'depends' => [JqueryAsset::class],
]);

// поиск карточек переиспользует тот же экшн, что и форма движений
$searchUrl = Url::to(['stock-movement/search-cards']);
$suggestUrl = Url::to(['stock-snapshot/suggest-balance']);

$this->registerJs(<<<JS
$('#stocksnapshot-nm_id').select2({
    placeholder: 'Начните вводить артикул, nmID или название товара',
    allowClear: true,
    minimumInputLength: 1,
    width: '100%',
    ajax: {
        url: '{$searchUrl}',
        dataType: 'json',
        delay: 250,
        data: function (params) { return { q: params.term }; },
        processResults: function (data) { return { results: data }; }
    }
});

$('#suggest-balance-btn').on('click', function (e) {
    e.preventDefault();
    var nmId = $('#stocksnapshot-nm_id').val();
    var onDate = $('#stocksnapshot-period_date').val();
    if (!nmId || !onDate) {
        alert('Сначала выберите товар и дату периода');
        return;
    }
    $.get('{$suggestUrl}', { nm_id: nmId, on_date: onDate }, function (data) {
        $('#stocksnapshot-qty_start').val(data.balance);
    });
});
JS
);
?>
<div class="stock-snapshot-form">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(); ?>

    <div class="form-group field-stocksnapshot-nm_id">
        <?= Html::activeLabel($model, 'nm_id', ['label' => 'Товар (nmID)']) ?>
        <select id="stocksnapshot-nm_id" name="StockSnapshot[nm_id]" class="form-control">
            <?php if ($model->nm_id): ?>
                <option value="<?= $model->nm_id ?>" selected><?= Html::encode($initialLabel) ?></option>
            <?php endif; ?>
        </select>
        <?= Html::error($model, 'nm_id', ['class' => 'help-block']) ?>
    </div>

    <?= $form->field($model, 'period_date')->input('date')
        ->hint('Снапшот всегда фиксируется на 1-е число месяца.') ?>

    <?= $form->field($model, 'qty_start') ?>
    <p>
        <button type="button" id="suggest-balance-btn" class="btn btn-default btn-sm">
            Подставить расчётный баланс на эту дату
        </button>
        <br><small class="text-muted">Посчитает по уже внесённым движениям (приход/сверка/списание) - удобно для проверки перед сохранением.</small>
    </p>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
