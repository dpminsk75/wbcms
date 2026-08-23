<?php
//use Yii;
use yii\helpers\Html;
use yii\grid\GridView;
use kartik\select2\Select2; // Импортируем виджет

use yii\widgets\Pjax;
use yii\web\YiiAsset;


use app\assets\ChartAsset;
ChartAsset::register($this);



$this->title = 'Воронка продаж WB';
$this->params['breadcrumbs'][] = 'Данные';
$this->params['breadcrumbs'][] = 'Воронка продаж';

$QFButton = [];
if (!Yii::$app->user->isGuest && Yii::$app->user->identity->username === 'admin') {
    $QFButton[0] = Html::a('<i class="fas fa-sync-alt"></i>Дневник-шпаргалка', ['/wb-get-sales-funnel/index?productId=1'],  ['class' => 'btn btn-panel'] );
    $QFButton[1] = Html::a('<i class="fas fa-sync-alt"></i>Календарь', ['/wb-get-sales-funnel/index?productId=5'],  ['class' => 'btn btn-panel'] );

    $QFButton[2] = Html::a('Обновить воронку', ['sync'], ['class' => 'btn btn-success', 'data-method' => 'post']);
    $QFButton[3] = Html::a('Догрузить недостающие', ['sync-missing'], ['class' => 'btn btn-warning', 'data-method' => 'post']);
    
}

$PanelButtons = "";
foreach ($QFButton as $str) {
    $PanelButtons .= $str;
}

?>
<div class="wb-sales-index">
    <h1><?= Html::encode($this->title) ?></h1>

<?= Html::tag('div', $PanelButtons, ['class' => 'panel-btns']); ?>


<?php
    $dateFrom = date('Y-m-d', strtotime('-15 days'));
    $dateTo = date('Y-m-d', strtotime('-1 days'));
?>
<div class="row" style="margin-bottom: 20px;">
    <div class="col-md-6">

        <div class="well">
            <?= Html::beginForm(['index'], 'get', ['class' => 'form-inline form__sales-funnel']) ?>
                
                <div class="form-group" style="min-width: 400px; margin-right: 15px;">
                    <label>Товар: </label>
                    <?= Select2::widget([
                        'name' => 'productId',
                        'value' => $productId,
                        'data' => $productsList, // Здесь должны быть пары [product_id => Название_товара]
                        'options' => ['placeholder' => 'Введите название товара ...'],
                        'pluginOptions' => [
                            'allowClear' => true
                        ],
                    ]); ?>
                </div>

                <div class="form-group form__sales-funnel-dates">
                    <label>Период с </label>
                    <?= Html::input('date', 'dateFrom', $dateFrom, ['class' => 'form-control form__date']) ?>
                    <label> по </label>
                    <?= Html::input('date', 'dateTo', $dateTo, ['class' => 'form-control form__date', 'style' => 'margin-right: 10px;']) ?>
                </div>

                <div class="form-group form__sales-funnel-btn">
                    <?= Html::submitButton('Показать', ['class' => 'btn btn-primary btn_200px']) ?>
                    <?= Html::a('Сброс', ['index'], ['class' => 'btn btn-light btn_200px']) ?>
                </div>

            <?= Html::endForm() ?>
        </div>
    </div>
    <div class="col-md-6">
        <?php if (!empty($relatedCards)): ?>
            <div class="alert alert-default" style="background: #f1f1f1; border-left: 5px solid #337ab7; margin-bottom: 20px;">
                <strong>В состав товара входят карточки:</strong><br>
                <div style="margin-top: 10px;"><ul>
                    <?php foreach ($relatedCards as $card): ?>
                        <li><a href="/wb-get-sales-funnel/wbcard?nmId=<?=Html::encode($card['nmId'])?>" target="_blank"><?= Html::encode($card['nmId']) ?></a> | <?= Html::encode($card['card_name'] ?: 'Без названия') ?> | <?= Html::encode($card['vendorCode']) ?> </li>
                    <?php endforeach; ?>
                </ul></div>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="row" style="margin-bottom: 20px;">
    <?php if (!empty($chartData)) {
        echo $this->render('_chart', [
            'chartData' => $chartData,
        ]);
        }
    ?>
<hr>
    <?php if ($productId): ?>
        <?php
            echo $this->render('_table', [
                'dataProvider' => $dataProvider,
            ]);
        ?>
    <?php else: ?>
        <div class="alert alert-info">Выберите товар или период для отображения статистики.</div>
    <?php endif; ?>
</div>
</div>

