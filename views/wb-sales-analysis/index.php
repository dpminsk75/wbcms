<?php
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\grid\GridView;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use kartik\depdrop\DepDrop;

use kartik\icons\Icon;
Icon::map($this); 

$this->title = 'ТОП Продаж WB';
?>

<div class="wb-sales-analysis-index container-fluid">
    <h1 class="mb-4"><?= Html::encode($this->title) ?></h1>
    <p><i> Данные по продажам с 1/09/2025 </i></p>

    <div class="card shadow-sm mb-4 card-body">
        <?= Html::beginForm(['index'], 'get', ['id' => 'filter-form']) ?>
        <div class="row g-3 mb-3" style="flex-direction: row; align-items: flex-end; justify-content: space-between;">
            <div class="col-md-4">
                <label class="form-label">Период</label>
                <?= DatePicker::widget([
                    'name' => 'date_from',
                    'value' => $params['dateFrom'],
                    'type' => DatePicker::TYPE_RANGE,
                    'name2' => 'date_to',
                    'value2' => $params['dateTo'],
                    'options' => [
                        'id' => 'date_from',
                        'style' => 'height: 38px;', 
                    ],
                    'options2' => [
                        'id' => 'date_to',
                        'style' => 'height: 38px;', 
                    ],

                    'separator' => ' | ',
                    'pluginOptions' => [
                        'autoclose' => true, 
                        'format' => 'yyyy-mm-dd',
                        'orientation' => 'bottom auto', 
                        'todayHighlight' => true
                    ]
                ]); ?>
            </div>

            <div class="btn-group col-md-3" style="height: 38px; margin-bottom: 0px;">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('quarter')">Квартал</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('year')">Год</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('last_year')">Прошлый год</button>
            </div>

            <div class="col-md-2">
                <label class="form-label">Тип ТОПа</label>
                <?= Select2::widget([
                    'name' => 'report_type',
                    'value' => $params['reportType'],
                    'data' => ['revenue' => 'По выручке', 'qty' => 'По количеству'],
                    'pluginOptions' => ['allowClear' => false],
                ]); ?>
            </div>

            <div class="col-md-2">
                <label class="form-label">Показать ТОП</label>
                <?= Select2::widget([
                    'name' => 'top_limit',
                    'value' => $params['topLimit'],
                    'data' => [20 => '20', 30 => '30', 50 => '50', 100 => '100'],
                ]); ?>
            </div>
        </div>

        <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Бренд</label>
                    <?= Select2::widget([
                        'name' => 'brand',
                        'value' => $params['brand'],
                        'data' => $filterData['brands'],
                        'options' => ['placeholder' => 'Все бренды'],
                        'pluginOptions' => ['allowClear' => true],
                    ]); ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Категория</label>
                    <?= Select2::widget([
                        'name' => 'category',
                        'id' => 'category-id', // ID для зависимости
                        'value' => $params['category'],
                        'data' => $filterData['categories'],
                        'options' => ['placeholder' => 'Выберите категорию...'],
                        'pluginOptions' => ['allowClear' => true],
                    ]); ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Тип</label>
                    <?= DepDrop::widget([
                        'name' => 'type',
                        'value' => $params['type'],
                        'data' => $selectedTypes, // Передаем предзагруженные данные
                        'options' => ['placeholder' => 'Ожидание категории...'],
                        'pluginOptions' => [
                            'depends' => ['category-id'],
                            'url' => \yii\helpers\Url::to(['get-types']),
                            'loadingText' => 'Загрузка типов...',
                        ]
                    ]); ?>
                </div>
        </div>

        <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Страна</label>
                    <?= Select2::widget([
                        'name' => 'country',
                        'id' => 'country-id',
                        'value' => $params['country'],
                        'data' => $filterData['countries'],
                        'options' => ['placeholder' => 'Выберите страну...'],
                        'pluginOptions' => ['allowClear' => true],
                    ]); ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Округ (для РФ)</label>
                    <?= DepDrop::widget([
                        'name' => 'oblast',
                        'id' => 'district-id',
                        'value' => $params['oblast'],
                        'data' => $selectedOblasts, // Список, сформированный в actionIndex
                        'options' => ['placeholder' => 'Необязательно...'],
                        'pluginOptions' => [
                            'depends' => ['country-id'],
                            'url' => Url::to(['get-districts']),
                            'loadingText' => 'Загрузка...',
                        ]
                    ]); ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Регион</label>
                    <?= DepDrop::widget([
                        'name' => 'region',
                        'id' => 'region-id',
                        'value' => $params['region'],
                        'data' => $selectedRegions, // Список, сформированный в actionIndex
                        'options' => ['placeholder' => 'Выберите регион...'],
                        'pluginOptions' => [
                            'depends' => ['country-id', 'district-id'], // ЗАВИСИТ ОТ ОБОИХ
                            'url' => Url::to(['get-regions']),
                            'loadingText' => 'Загрузка...',
                        ]
                    ]); ?>
                </div>
        </div>

        <div class="row g-3 mb-3">
                <div class="col-md-3 d-flex align-items-end">
                    <?= Html::submitButton('Сформировать', ['class' => 'btn btn-primary w-100']) ?>
                </div>
        </div>

        <?= Html::endForm() ?>
    </div>
</div>

<div class="row grid_advstat grid_wbstat">
    <div class="custom-compact-grid">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,

        'pjax' => true,
        'bordered' => true,
        'striped' => true,
        'condensed' => true,
        'responsive' => true,
        'hover' => true,

        'showPageSummary' => true,
        
        'panel' => [
            'type' => GridView::TYPE_PRIMARY,
            'heading' => 'Результаты анализа продаж',
            'headingOptions' => ['class' => 'card-header text-white bg-wb'],
        ],
        'columns' => [
            ['class' => 'kartik\grid\SerialColumn'],
            [
                'attribute' => 'nmId',
                'label' => 'Арт WB',
                'format' => 'raw', 
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: center;'],

                'value' => function($m) use ($params) {
                    return Html::a(
                        (string)$m['nm_id'], 
//                        "/wb-sales/index?WbSalesSearch[nmId]=" . $m['nm_id'], 
//                        "/wb-detail-by-period/weekly-report-nmid?nm_id={$m['nm_id']}&date_from={$params['dateFrom']}&date_to={$params['dateTo']}",
                        "/wb/detail?nm_id={$m['nm_id']}&date_from={$params['dateFrom']}&date_to={$params['dateTo']}",

                        [
                            'target' => '_blank',
                            'data-pjax' => '0', 
                            'style' => 'text-decoration: none;'
                        ]
                    );
                },
            ],

            [
                'attribute' => 'vendorCode',
                'label' => 'Артикул',
                'headerOptions'  => ['style' => 'text-align: center;'],
                'contentOptions' => ['style' => 'white-space: nowrap; align-content: center; text-align: center;'],
            ],


            [
                'attribute' => 'card_name',
                'label' => 'Товар',
                'headerOptions'  => ['style' => 'text-align: center;'],

                'format' => 'raw',
                'value' => function($m) {
                        // Верхний уровень: Название товара
                        $title = Html::tag('div', $m['card_name'] ?? '—', [
                            'style' => 'font-weight: bold; font-size: 13px; margin-bottom: 8px; color: #2c3e50;'
                        ]);

                        // Нижний уровень: Цены и склад в ряд
                        $details = Html::tag('div', 
                            "<b>{$m['brand']}</b> | " .
                            "{$m['subject']} | " .
                            "{$m['category']}" ,
                            ['style' => 'color: #666; font-size: 11px;']
                        );
/*
                        $footer = Html::tag('div', 
                            "Склад: <b>{$m['category']}</b>",
                            ['style' => 'color: #666; font-size: 11px;']
                        );


                        return $title . $details. $footer; },
*/
                        return $title . $details; },
            ],
            [
                'attribute' => 'sales_qty',
                'label' => 'Кол-во',
                'headerOptions'  => ['style' => 'text-align: center;'],

                'pageSummary' => true,
                'contentOptions' => ['class' => 'fw-bold'],

                'format' => ['decimal', 0],
                'hAlign' => 'right',
            ],

            [
                'attribute' => 'finished_sum',
                'label' => 'Выручка',
                'headerOptions'  => ['style' => 'text-align: center;'],

                'format' => ['decimal', 2],
                'hAlign' => 'right',
                'pageSummary' => true,
            ],
            [
                'attribute' => 'for_pay_sum',
                'label' => 'К оплате',
                'headerOptions'  => ['style' => 'text-align: center;'],

                'format' => ['decimal', 2],
                'hAlign' => 'right',
                'pageSummary' => true,
            ],
/*


            [
                'attribute' => 'total_sum',
                'label' => 'До скидок',
                'headerOptions'  => ['style' => 'text-align: center;'],

                'format' => ['decimal', 2],
                'hAlign' => 'right',
                'pageSummary' => true,
            ],
            [
                'attribute' => 'disc_sum',
                'label' => 'Со скидкой',
                'headerOptions'  => ['style' => 'text-align: center;'],

                'format' => ['decimal', 2],
                'hAlign' => 'right',
                'pageSummary' => true,
            ],
*/

            [
                'attribute' => 'apwd',
                'label' => 'Цена со ск, ₽',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
            ],
            [
                'attribute' => 'aspp',
                'label' => 'СПП, %',
                'headerOptions'  => ['style' => 'text-align: center;'],

                'format' => ['decimal', 2],
                'hAlign' => 'right',
            ],
            [
                'attribute' => 'afp',
                'label' => 'Цена Прд, ₽',
                'headerOptions'  => ['style' => 'width:80px'],
                'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
            ],
            [
                'attribute' => 'aforPay',
                'label' => 'К оплате, ₽',
                'headerOptions'  => ['style' => 'width:80px'],
                'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right;'],
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
            ],



        ],
    ]); ?>
</div></div>

<script>
function setDateRange(period) {
    // 1. Берем дату из поля "До". Если пусто — берем текущую
    let valTo = $('#date_to').val();
    let baseDate = valTo ? new Date(valTo) : new Date();

    if (isNaN(baseDate.getTime())) baseDate = new Date();

    let dateFrom = new Date(baseDate.getTime());
    let newTo = null; // Переменная для случая, если нужно изменить и поле "До"

    // Функция форматирования YYYY-MM-DD
    const formatDate = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };

    if (period === 'year') {
        // Ровно минус год + 1 день от текущего #date_to
        dateFrom.setFullYear(baseDate.getFullYear() - 1);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'quarter') {
        // Ровно минус 3 месяца + 1 день от текущего #date_to
        dateFrom.setMonth(baseDate.getMonth() - 3);
        dateFrom.setDate(dateFrom.getDate() + 1);
    } else if (period === 'last_year') {
        // ВЕСЬ ПРОШЛЫЙ КАЛЕНДАРНЫЙ ГОД
        const lastYear = baseDate.getFullYear() - 1;
        dateFrom = new Date(lastYear, 0, 1);  // 1 января прошлого года
        newTo = new Date(lastYear, 11, 31);    // 31 декабря прошлого года
    }

    // 2. Обновляем поле "От"
    $('#date_from').val(formatDate(dateFrom)).trigger('change');

    // 3. Если это "Прошлый год", обновляем и поле "До"
    if (newTo) {
        $('#date_to').val(formatDate(newTo)).trigger('change');
        if (typeof $('#date_to').kvDatepicker === 'function') {
            $('#date_to').kvDatepicker('update', formatDate(newTo));
        }
    }

    // Обновляем визуальный календарь для "От"
    if (typeof $('#date_from').kvDatepicker === 'function') {
        $('#date_from').kvDatepicker('update', formatDate(dateFrom));
    }
}

</script>
