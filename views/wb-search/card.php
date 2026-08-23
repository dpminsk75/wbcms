<?php
use yii\helpers\Html;

/** @var $dataProvider yii\data\ArrayDataProvider */
/** @var $uniqueDates array */
/** @var $cardInfo array */
/** @var $dateFrom string */

$this->title = 'Анализ поисковых фраз для карточки';

$myButtons = [];
if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin') {
    $myButtons[] = Html::a('<i class="fas fa-sync-alt"></i> Дневник',       ['/wb-search/card', 'DPFilterForm' => ['nm_id' => 526443466]], ['class' => 'btn btn-panel']);
    $myButtons[] = Html::a('<i class="fas fa-calendar-alt"></i> Календарь', ['/wb-search/card', 'DPFilterForm' => ['nm_id' => 135462932]], ['class' => 'btn btn-panel']);
}
?>
<div class="search-phrase-report">

    <div class="row mb-3">
        <?php if ($cardInfo): ?>
            <?= \app\components\PageHeaderWidget::widget(['title' => $cardInfo['title'], 'nmId' => $cardInfo['nmID']]) ?>
        <?php endif; ?>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <?= \app\components\getDPWidget::widget([
                'action' => ['/wb-search/card'], 
                'quickButtons' => $myButtons,
                'defaultDateFrom' => $dateFrom,
            ]) ?>
        </div>
    </div>

    <div class="row custom-compact-grid mb-5">
        <div class="col-md-12">
            <?= $this->render('_card_table', [
                'dataProvider' => $dataProvider,
                'uniqueDates' => $uniqueDates,
            ]) ?>
        </div>
    </div>

</div>

<style>
    body { overflow-x: hidden; }
</style>