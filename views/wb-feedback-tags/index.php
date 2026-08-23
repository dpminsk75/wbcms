<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var \yii\web\View $this */
/** @var array $tags */
/** @var string $filter */

$this->title = 'Разметка тегов отзывов';
$this->params['breadcrumbs'][] = $this->title;

$sentimentLabels = [
    'positive' => 'Позитивный',
    'negative' => 'Негативный',
    'neutral' => 'Не размечен',
];
$sentimentColors = [
    'positive' => 'success',
    'negative' => 'danger',
    'neutral' => 'secondary',
];
?>

<div class="wb-feedback-tags-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p class="text-muted">
        Теги подтягиваются из поля <code>bables</code> отзывов. Чтобы обновить список
        (подхватить новые теги и пересчитать частоту), запустите на сервере:
        <code>php yii wb-feedback-tags/sync</code>
    </p>

    <div class="mb-3">
        <a href="<?= Url::to(['index', 'filter' => 'unclassified']) ?>"
           class="btn btn-sm <?= $filter === 'unclassified' ? 'btn-primary' : 'btn-outline-primary' ?>">
            Только неразмеченные
        </a>
        <a href="<?= Url::to(['index', 'filter' => 'all']) ?>"
           class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
            Все теги
        </a>
    </div>

    <?php if (empty($tags)): ?>
        <p class="text-muted">
            <?= $filter === 'unclassified' ? 'Все теги уже размечены 🎉' : 'Теги пока не собраны — запустите wb-feedback-tags/sync.' ?>
        </p>
    <?php else: ?>
        <table class="table table-striped table-bordered align-middle">
            <thead>
            <tr>
                <th>Тег</th>
                <th style="width: 100px;">Встречается</th>
                <th style="width: 140px;">Текущий статус</th>
                <th style="width: 320px;">Отметить как</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tags as $tag): ?>
                <tr>
                    <td><?= Html::encode($tag['tag_text']) ?></td>
                    <td><?= (int)$tag['usage_count'] ?></td>
                    <td>
                        <span class="badge bg-<?= $sentimentColors[$tag['sentiment']] ?>">
                            <?= $sentimentLabels[$tag['sentiment']] ?>
                        </span>
                    </td>
                    <td>
                        <?php foreach (['positive' => 'Позитивный', 'negative' => 'Негативный', 'neutral' => 'Сбросить'] as $value => $label): ?>
                            <?= Html::beginForm(['set-sentiment'], 'post', ['style' => 'display:inline-block;margin-right:4px;']) ?>
                            <?= Html::hiddenInput('id', $tag['id']) ?>
                            <?= Html::hiddenInput('sentiment', $value) ?>
                            <?= Html::submitButton($label, [
                                'class' => 'btn btn-sm ' . (
                                    $tag['sentiment'] === $value
                                        ? 'btn-' . $sentimentColors[$value]
                                        : 'btn-outline-' . $sentimentColors[$value]
                                ),
                            ]) ?>
                            <?= Html::endForm() ?>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
