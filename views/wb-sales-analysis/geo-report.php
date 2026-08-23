<?php
use yii\helpers\Html;
use kartik\grid\GridView;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use kartik\depdrop\DepDrop;
use yii\helpers\Url;

$this->title = 'Анализ продаж по географии (Товары)';
?>

<div class="geo-report container-fluid">
    <div class="card shadow-sm mb-4 card-body">
        <?= Html::beginForm(['geo-report'], 'get') ?>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold">Период</label>
                <?= DatePicker::widget([
                    'name' => 'date_from', 'value' => $params['dateFrom'],
                    'type' => DatePicker::TYPE_RANGE,
                    'name2' => 'date_to', 'value2' => $params['dateTo'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd']
                ]) ?>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Страна</label>
                <?= Select2::widget([
                    'name' => 'country', 'data' => $countries, 'value' => $params['country'],
                    'options' => ['id' => 'country-id', 'placeholder' => 'Страна...'],
                    'pluginOptions' => ['allowClear' => true],
                ]) ?>
            </div>

            <div class="col-md-2">
                <label class="form-label">Округ</label>
                <?= DepDrop::widget([
                    'type' => DepDrop::TYPE_SELECT2,
                    'name' => 'oblast', 'value' => $params['oblast'], 'data' => $selectedOblasts,
                    'options' => ['id' => 'oblast-id'],
                    'pluginOptions' => [
                        'depends' => ['country-id'],
                        'url' => Url::to(['get-districts-dadata']),
                        'placeholder' => 'Для РФ...',
                    ]
                ]) ?>
            </div>

            <div class="col-md-2">
                <label class="form-label">Регион</label>
                <?= DepDrop::widget([
                    'type' => DepDrop::TYPE_SELECT2,
                    'name' => 'region', 'value' => $params['region'], 'data' => $selectedRegions,
                    'options' => ['id' => 'region-id'],
                    'pluginOptions' => [
                        'depends' => ['country-id', 'oblast-id'],
                        'url' => Url::to(['get-regions-dadata']),
                        'placeholder' => 'Регион...',
                    ]
                ]) ?>
            </div>

            <div class="col-md-2">
                <label class="form-label">Город/ПВЗ</label>
                <?= DepDrop::widget([
                    'type' => DepDrop::TYPE_SELECT2,
                    'name' => 'city', 'value' => $params['city'], 'data' => $selectedCities,
                    'pluginOptions' => [
                        'depends' => ['region-id'],
                        'url' => Url::to(['get-cities-dadata']),
                        'placeholder' => 'Город...',
                    ]
                ]) ?>
            </div>

            <div class="col-md-1 d-flex align-items-end">
                <?= Html::submitButton('ОК', ['class' => 'btn btn-primary w-100']) ?>
            </div>
        </div>

<div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label fw-bold">Бренд</label>
            <?= Select2::widget([
                'name' => 'brand', 'data' => $brands, 'value' => $params['brand'],
                'options' => ['placeholder' => 'Все бренды'],
                'pluginOptions' => ['allowClear' => true],
            ]) ?>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-bold">Категория</label>
            <?= Select2::widget([
                'name' => 'category', 'id' => 'cat-id', 'data' => $categories, 'value' => $params['category'],
                'options' => ['placeholder' => 'Все категории'],
                'pluginOptions' => ['allowClear' => true],
            ]) ?>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-bold">Тип товара</label>
            <?= DepDrop::widget([
                'type' => DepDrop::TYPE_SELECT2,
                'name' => 'type', 'value' => $params['type'], 'data' => $selectedTypes,
                'pluginOptions' => [
                    'depends' => ['cat-id'],
                    'url' => Url::to(['get-types-by-category']),
                    'placeholder' => 'Все типы...',
                ]
            ]) ?>
        </div>
    </div>
</div>
        <?= Html::endForm() ?>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'showPageSummary' => true,
        'pjax' => true,
        'panel' => ['type' => GridView::TYPE_PRIMARY, 'heading' => 'Продажи товаров в выбранной локации'],
        'columns' => [
            ['class' => 'kartik\grid\SerialColumn'],
            [
                'attribute' => 'nm_id',
                'label' => 'Арт WB',
                'format' => 'raw',
                'value' => function($m) use ($params) {
                    return Html::a((string)$m['nm_id'], "https://www.wildberries.ru/catalog/{$m['nm_id']}/detail.aspx", ['target' => '_blank']);
                },
            ],
            ['attribute' => 'vendorCode', 'label' => 'Артикул'],
            [
                'attribute' => 'card_name',
                'label' => 'Товар',
                'format' => 'raw',
                'value' => function($m) {
                    return "<b>{$m['card_name']}</b><br><small class='text-muted'>{$m['brand']}</small>";
                },
            ],
            ['attribute' => 'sales_qty', 'label' => 'Кол-во', 'format' => ['decimal', 0], 'pageSummary' => true, 'hAlign' => 'right'],
            ['attribute' => 'finished_sum', 'label' => 'Сумма', 'format' => ['decimal', 2], 'pageSummary' => true, 'hAlign' => 'right'],
            ['attribute' => 'for_pay_sum', 'label' => 'К оплате', 'format' => ['decimal', 2], 'pageSummary' => true, 'hAlign' => 'right'],
            [
                'attribute' => 'aspp',
                'label' => 'СПП %',
                'value' => function($model) { return round($model['aspp'] ?? 0, 2) . '%'; },
                'hAlign' => 'center',
            ],
        ],
    ]); ?>
</div>