<?php
use yii\helpers\Html;
use kartik\grid\GridView;
use kartik\icons\Icon;

Icon::map($this);

$this->title = 'Теги';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tag-index">

    <div class="tag-index-header">
        <h1 class="m-0">
            <?= Html::encode($this->title) ?>
            <span class="tag-index-count"><?= $dataProvider->getTotalCount() ?></span>
        </h1>
        <?= Html::a('<i class="fas fa-plus"></i> Создать тег', ['create'], [
            'class' => 'btn btn-success',
            'encode' => false,
        ]) ?>
    </div>

    <div class="card tag-index-panel">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'summary' => false,
            'tableOptions' => ['class' => 'table table-hover mb-0 tag-index-table'],
            'columns' => [
                [
                    'attribute' => 'priority',
                    'label' => 'Приоритет',
                    'hAlign' => 'center',
                    'width' => '110px',
                    'format' => 'raw',
                    'value' => function ($model) {
                        return Html::tag('span', Html::encode($model->priority), ['class' => 'priority-pill']);
                    },
                ],
                [
                    'attribute' => 'name',
                    'label' => 'Название',
                    'format' => 'raw',
                    'value' => function ($model) {
                        return Html::tag('span', Html::encode($model->name), [
                            'class' => 'tag-name-pill',
                            'style' => '--tag-color: ' . $model->color . ';',
                        ]);
                    },
                ],
                [
                    'attribute' => 'tag_group',
                    'label' => 'Группа',
                    'format' => 'raw',
                    'value' => function ($model) {
                        return $model->tag_group
                            ? Html::tag('span', Html::encode($model->tag_group), ['class' => 'tag-group-label'])
                            : '<span class="text-muted">—</span>';
                    },
                ],
                [
                    'label' => 'Карточек',
                    'hAlign' => 'center',
                    'width' => '120px',
                    'format' => 'raw',
                    'value' => function ($model) {
                        return Html::tag(
                            'span',
                            '<i class="fas fa-layer-group"></i> ' . count($model->wbCardIds),
                            ['class' => 'cards-count-pill']
                        );
                    },
                ],
                [
                    'class' => 'yii\grid\ActionColumn',
                    'header' => '',
                    'headerOptions' => ['class' => 'text-end'],
                    'contentOptions' => ['class' => 'text-end'],
                    'template' => '{view} {update} {delete}',
                    'buttons' => [
                        'view' => function ($url) {
                            return Html::a('<i class="fas fa-chart-line"></i>', $url, [
                                'class' => 'tag-action-btn tag-action-view',
                                'title' => 'Аналитика',
                            ]);
                        },
                        'update' => function ($url) {
                            return Html::a('<i class="fas fa-pen"></i>', $url, [
                                'class' => 'tag-action-btn tag-action-update',
                                'title' => 'Редактировать',
                            ]);
                        },
                        'delete' => function ($url) {
                            return Html::a('<i class="fas fa-trash"></i>', $url, [
                                'class' => 'tag-action-btn tag-action-delete',
                                'title' => 'Удалить',
                                'data-confirm' => 'Удалить этот тег?',
                                'data-method' => 'post',
                            ]);
                        },
                    ],
                ],
            ],
            'emptyText' => 'Теги пока не созданы',
        ]) ?>
    </div>

</div>

<style>
.tag-index-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.tag-index-count {
    display: inline-block;
    background: #eef1f4;
    color: #495057;
    font-size: 13px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 10px;
    margin-left: 8px;
    vertical-align: middle;
}

.tag-index-panel {
    border: none;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    overflow: hidden;
}

.tag-index-table th {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #868e96;
    background: #f8f9fb;
    border-bottom: 1px solid #eee;
    font-weight: 600;
}

.tag-index-table td {
    vertical-align: middle;
}

.priority-pill {
    display: inline-block;
    min-width: 28px;
    padding: 3px 8px;
    border-radius: 6px;
    background: #f1f3f5;
    color: #495057;
    font-weight: 600;
    font-size: 12px;
    text-align: center;
}

.tag-name-pill {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    background: var(--tag-color);
    color: #fff;
    font-size: 13px;
    font-weight: 500;
}

.tag-group-label {
    color: #495057;
    font-size: 13px;
}

.cards-count-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    background: #eef4ff;
    color: #3b6fd6;
    font-size: 12px;
    font-weight: 600;
}

.tag-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    margin-left: 4px;
    color: #868e96;
    background: #f8f9fb;
    text-decoration: none;
    transition: background 0.12s ease, color 0.12s ease;
}

.tag-action-btn:hover {
    text-decoration: none;
}

.tag-action-view:hover { background: #eef4ff; color: #3b6fd6; }
.tag-action-update:hover { background: #fff6e6; color: #d9822b; }
.tag-action-delete:hover { background: #fbe9ec; color: #dc3545; }
</style>