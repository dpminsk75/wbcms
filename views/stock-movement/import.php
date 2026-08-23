<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\StockMovement;

/** @var app\models\StockImportForm $form */
/** @var array|null $report */

$this->title = 'Импорт остатков из Excel';
?>
<div class="stock-movement-import">

    <h1><?= Html::encode($this->title) ?></h1>

    <p class="text-muted">
        Файл должен содержать заголовки в первой строке: <code>nmId</code>, <code>vendorCode</code>, <code>Количество</code>
        (порядок колонок не важен, товар матчится сначала по nmId, если не найден — по vendorCode).
    </p>

    <?php $form2 = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <?= $form2->field($form, 'type')->dropDownList([
        StockMovement::TYPE_PRODUCTION_IN => 'Приход от производства (количество = сколько пришло)',
        StockMovement::TYPE_ADJUSTMENT => 'Сверка остатков (количество = факт на складе, разница будет посчитана автоматически)',
    ]) ?>

    <?= $form2->field($form, 'movementDate')->input('date') ?>

    <?= $form2->field($form, 'file')->fileInput() ?>

    <div class="form-group">
        <?= Html::submitButton('Загрузить и обработать', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

    <?php if ($report !== null): ?>
        <hr>
        <h3>Результат импорта</h3>
        <p>Обработано строк: <strong><?= $report['processed'] ?></strong></p>

        <?php if (!empty($report['skipped'])): ?>
            <div class="alert alert-warning">
                <strong>Пропущено / ошибки (<?= count($report['skipped']) ?>):</strong>
                <ul>
                    <?php foreach ($report['skipped'] as $line): ?>
                        <li><?= Html::encode($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="alert alert-success">Все строки обработаны без ошибок.</div>
        <?php endif; ?>

        <p><?= Html::a('Перейти к списку движений', ['index'], ['class' => 'btn btn-default']) ?></p>
    <?php endif; ?>

</div>
