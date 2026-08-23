<?php

/** @var yii\web\View $this */
/** @var app\models\Tag $model */
/** @var app\models\WbCardSearch $wbSearchModel */
/** @var yii\data\ActiveDataProvider $wbDataProvider */
/** @var app\models\WbCard[] $selectedCards */

use yii\helpers\Html;
use kartik\form\ActiveForm;
use kartik\builder\Form;
use kartik\widgets\ColorInput;
use kartik\grid\GridView;
use yii\widgets\Pjax;
use kartik\icons\Icon;
use app\assets\TagFormAsset;

// Регистрируем шрифт иконок (без этого вызова классы fas fa-* рендерятся пустыми)
Icon::map($this);

// Подключаем вынесенные CSS/JS
TagFormAsset::register($this);

$form = ActiveForm::begin(['id' => 'tag-form']);
?>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card tag-form-panel mb-3">
            <div class="tag-form-panel-header">
                <i class="fas fa-tag"></i> Параметры тега
            </div>
            <div class="card-body">
                <?php
                echo Form::widget([
                    'model' => $model,
                    'form' => $form,
                    'columns' => 1,
                    'attributes' => [
                        'name' => [
                            'type' => Form::INPUT_TEXT,
                            'options' => ['placeholder' => 'Название тега...']
                        ],
                        'tag_group' => [
                            'type' => Form::INPUT_TEXT,
                            'options' => ['placeholder' => 'Группа (напр. Сезонные)']
                        ],
                        'color' => [
                            'type' => Form::INPUT_WIDGET,
                            'widgetClass' => ColorInput::class,
                            'options' => ['options' => ['placeholder' => 'Выберите цвет...']],
                        ],
                        'priority' => [
                            'type' => Form::INPUT_TEXT,
                            'options' => ['type' => 'number', 'placeholder' => 'Приоритет (чем выше, тем левее)']
                        ],
                    ]
                ]);
                ?>
            </div>
        </div>

        <div class="tag-stat-card mb-3">
            <div class="tag-stat-icon"><i class="fas fa-layer-group"></i></div>
            <div>
                <div class="tag-stat-value" id="debug-count">0</div>
                <div class="tag-stat-label">Карточек в теге</div>
            </div>
        </div>

        <div class="form-group">
            <?= Html::submitButton('<i class="fas fa-check"></i> Сохранить тег', ['class' => 'btn btn-success btn-lg w-100', 'encode' => false]) ?>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card wb-panel">
            <div class="wb-panel-header">
                <i class="fas fa-link"></i> Привязка карточек Wildberries
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
                    <div class="col-md-7">
                        <div class="wb-column-header">
                            <span><i class="fas fa-arrows-alt"></i> Доступные</span>
                            <button type="button" id="add-all-visible" class="btn btn-xs btn-outline-primary">
                                <i class="fas fa-plus"></i> Добавить все
                            </button>
                        </div>

                        <div class="wb-available-list">
                            <?php Pjax::begin(['id' => 'wb-grid-pjax', 'enablePushState' => false]); ?>
                            <?= GridView::widget([
                                'dataProvider' => $wbDataProvider,
                                'summary' => false,
                                'tableOptions' => ['class' => 'table table-sm table-hover wb-available-table'],
                                'rowOptions' => function ($m) {
                                    return [
                                        'draggable' => 'true',
                                        'ondragstart' => 'drag(event)',
                                        'data-nmid' => $m->nmID, // Используем nmID [cite: 2026-02-17]
                                        'data-vendorcode' => $m->vendorCode,
                                        'data-title' => $m->title,
                                        'class' => 'wb-draggable-row',
                                    ];
                                },
                                'columns' => [
                                    ['attribute' => 'nmID', 'label' => 'Арт WB'],
                                    ['attribute' => 'vendorCode', 'label' => 'Артикул'],
                                    [
                                        'attribute' => 'title',
                                        'label' => 'Название',
                                        'value' => function ($m) { return mb_strimwidth($m->title, 0, 45, '...'); }
                                    ],
                                ],
                            ]) ?>
                            <?php Pjax::end(); ?>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="wb-column-header">
                            <span><i class="fas fa-inbox"></i> Выбранные</span>
                        </div>
                        <div id="wb-drop-zone" class="wb-drop-zone" ondrop="drop(event)" ondragover="allowDrop(event)">

                            <ul id="wb-selected-list" class="wb-selected-list">
                                <?php if (!empty($selectedCards)): ?>
                                    <?php foreach ($selectedCards as $card): ?>
                                        <li class="wb-selected-item" data-nmid="<?= $card->nmID ?>">
                                            <div class="wb-selected-info">
                                                <span class="wb-selected-nmid"><?= $card->nmID ?></span>
                                                <span class="wb-selected-vendor"><?= Html::encode($card->vendorCode) ?></span>
                                                <div class="wb-selected-title"><?= Html::encode($card->title) ?></div>
                                            </div>
                                            <button type="button" class="wb-remove-btn wb-remove-card" title="Удалить">&times;</button>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>

                            <div class="wb-drop-placeholder" id="wb-drop-placeholder" style="<?= !empty($selectedCards) ? 'display:none;' : '' ?>">
                                <i class="fas fa-arrow-left"></i> Перетащите карточки сюда
                            </div>

                            <div id="wb-hidden-inputs">
                                <?php foreach ($model->wbCardIds as $id): ?>
                                    <input type="hidden" name="Tag[wbCardIds][]" value="<?= $id ?>" data-nmid="<?= $id ?>">
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php ActiveForm::end(); ?>