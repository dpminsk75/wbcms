<?php

use yii\helpers\Html;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Автоответы на отзывы';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="wb-reply-rule-index">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><?= Html::encode($this->title) ?></h1>
        <?= Html::a('<i class="fa fa-plus"></i> Добавить правило', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table table-striped table-bordered align-middle'],
        'columns' => [
            [
                'attribute' => 'is_active',
                'label' => '',
                'format' => 'raw',
                'headerOptions' => ['style' => 'width: 60px;'],
                'value' => function ($model) {
                    return Html::checkbox('is_active', $model->is_active, [
                        'class' => 'rule-status-toggle',
                        'data-id' => $model->id,
                    ]);
                },
            ],
            [
                'attribute' => 'title',
                'format' => 'raw',
                'value' => function ($model) {
                    $date = Yii::$app->formatter->asDatetime($model->updated_at, 'php:d M., H:i');
                    return Html::tag('strong', Html::encode($model->title)) . 
                           Html::tag('div', '<i class="fa fa-pencil text-muted"></i> ' . $date, ['class' => 'small text-muted mt-1']);
                },
            ],

[
                'label' => 'Товары',
                'format' => 'raw',
                'headerOptions' => ['style' => 'width: 40%; min-width: 300px;'],
                'value' => function ($model) {
                    if ($model->rule_type === 'general') {
                        return '<span class="badge bg-success-light text-success" style="background-color: #e6f7ed; padding: 5px 10px;">Общее</span>';
                    }
                    
                    if ($model->rule_type === 'brand') {
                        // Возвращаем исходный метод для брендов
                        $brands = $model->getSelectedBrandsTitles(); 
                        if (empty($brands)) {
                            return '<span class="text-danger small">Для брендов (не выбраны)</span>';
                        }
                        $items = [];
                        foreach ($brands as $b) {
                            $items[] = Html::tag('span', Html::encode($b), [
                                'class' => 'badge bg-info text-dark me-1 mb-1', 
                                'style' => 'background-color: #e1f5fe; padding: 5px 8px; font-size: 13px;'
                            ]);
                        }
                        return implode('', $items);
                    }
                    
                    if ($model->rule_type === 'product') {
                        // Возвращаем твой исходный метод для товаров
                        $products = $model->getSelectedProductsTitles(); 
                        if (empty($products)) {
                            return '<span class="text-danger small">Для товаров (не выбраны)</span>';
                        }
                        $items = [];
                        foreach ($products as $p) {
                            $title = !empty($p['title']) ? $p['title'] : 'Товар ' . $p['nmID'];
                            
                            $items[] = Html::tag('span', '[' . $p['nmID'] . '] ' . Html::encode($title), [
                                'class' => 'badge bg-warning text-dark me-1 mb-1 d-inline-block',
                                'style' => 'background-color: #fff8e1; border: 1px solid #ffe082; padding: 5px 8px; font-size: 13px; text-align: left; white-space: normal; word-break: break-word; max-width: 100%;'
                            ]);
                        }
                        return implode(' ', $items);
                    }
                    
                    return $model->rule_type;
                },
            ],

            [
                'label' => 'Рейтинг',
                'value' => function ($model) {
                    if ($model->rating_min === $model->rating_max) {
                        return $model->rating_min . ' ★';
                    }
                    return $model->rating_min . '–' . $model->rating_max . ' ★';
                },
            ],
            [
                'attribute' => 'text_condition',
                'label' => 'Тип отзыва',
                'format' => 'raw',
                'value' => function ($model) {
                    $labels = [
                        'any' => 'Любой',
                        'with_text' => 'С текстом',
                        'no_text' => '<span class="badge" style="background-color: #fff3cd; color: #856404;">Без текста</span>',
                    ];
                    return $labels[$model->text_condition] ?? $model->text_condition;
                },
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{update} {delete}',
                'buttonOptions' => ['class' => 'btn btn-sm btn-default'],
            ],
        ],
    ]); ?>
</div>