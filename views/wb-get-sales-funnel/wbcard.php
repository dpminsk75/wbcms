<?php
use yii\helpers\Html;
use yii\grid\GridView;
use kartik\select2\Select2;
use app\assets\ChartAsset;

ChartAsset::register($this);

// Подключаем русскую локаль для amCharts
$this->registerJsFile('https://cdn.amcharts.com/lib/5/locales/ru_RU.js', [
    'depends' => [ChartAsset::class]
]);

$this->title = 'Воронка продаж: Карточка WB';
$this->params['breadcrumbs'][] = ['label' => 'Данные', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$myButtons = [];
if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin') {
    $myButtons[] = Html::a('<i class="fas fa-sync-alt"></i> Дневник',       ['/wb-get-sales-funnel/wbcard', 'DPFilterForm' => ['nm_id' => 526443466]], ['class' => 'btn btn-panel']);
    $myButtons[] = Html::a('<i class="fas fa-calendar-alt"></i> Календарь', ['/wb-get-sales-funnel/wbcard', 'DPFilterForm' => ['nm_id' => 135462932]], ['class' => 'btn btn-panel']);
}

?>
<div class="wb-card-index">
<?php if ($card): ?> 
    <?= \app\components\PageHeaderWidget::widget(['title' => $card['title'],'nmId' => $card['nmID'] ]) ?>
<?php else: ?>
    <h1><?= Html::encode($this->title) ?></h1>
<?php endif; ?>
    
    <div class="col-md-6">
        <div class="filter-section"> <?= \app\components\getDPWidget::widget(['action' => ['wbcard'], 'quickButtons' => $myButtons]) ?></div>
    </div>

    <?php if (!empty($chartData)) {
        echo $this->render('_chart', [
            'chartData' => $chartData,
        ]);
        }
    ?>
<hr>
    <?php if ($dataProvider): ?>
        <?php
            echo $this->render('_table', [
                'dataProvider' => $dataProvider,
            ]);
        ?>
    <?php else: ?>
        <div class="alert alert-info">Выберите товар или период для отображения статистики.</div>
    <?php endif; ?>
</div>