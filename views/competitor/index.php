<?php
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\icons\Icon;
Icon::map($this);

$this->title = 'Конкуренты — анализ';
$this->params['breadcrumbs'][] = ['label' => 'Данные', 'url' => ['wb/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="competitor-index">
    <h1><?= Html::encode($this->title) ?></h1>
    <p class="text-muted">Товары, по которым собраны данные конкурентов. Выберите товар для анализа.</p>

    <?php if (empty($rows)): ?>
        <div class="alert alert-info">Нет данных о конкурентах.</div>
    <?php else: ?>
    <table class="table table-hover table-sm">
        <thead>
            <tr>
                <th>nmID</th>
                <th>Название</th>
                <th>Бренд</th>
                <th class="text-center">Конкурентов</th>
                <th class="text-center">Фраз</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php $card = $cards[$row['source_nm_id']] ?? null; ?>
            <tr>
                <td><b><?= $row['source_nm_id'] ?></b></td>
                <td><?= Html::encode($card['title'] ?? '—') ?></td>
                <td><?= Html::encode($card['brand'] ?? '—') ?></td>
                <td class="text-center"><span class="badge bg-primary"><?= $row['cnt'] ?></span></td>
                <td class="text-center"><span class="badge bg-secondary"><?= $row['phrases'] ?></span></td>
                <td>
                    <?= Html::a('<i class="fas fa-search"></i> Выбрать фразы', ['select', 'nm_id' => $row['source_nm_id']], ['class' => 'btn btn-sm btn-outline-primary']) ?>
                    <?= Html::a('<i class="fas fa-chart-bar"></i> Результаты', ['results', 'nm_id' => $row['source_nm_id']], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
