<?php
use yii\helpers\Html;
use yii\helpers\Url;
use kartik\widgets\Select2;
use yii\helpers\ArrayHelper;
use kartik\grid\GridView;
use kartik\daterange\DateRangePicker;

use kartik\icons\Icon;
Icon::map($this); 

/** @var app\models\Tag $tag */
/** @var app\models\Tag[] $allTags */
/** @var string $date_range */
/** @var yii\data\ArrayDataProvider $summaryProvider */
/** @var yii\data\ArrayDataProvider $detailProvider */
/** @var array $chartData */

$this->title = $tag ? "Теги: Заказы по тегу - " . $tag->name : "Теги";
?>

<div class="tag-analytics">
    <div class="row mb-3">
        <div class="col-md-12">

        <div class="card mb-3 tags-card">
            <div class="card-body">
                <div class="row col-md-12">
                <!-- Заголовок + облако тегов -->
                    <div class="d-flex align-items-center flex-wrap mb-3" style="gap: 10px;">
                        <h5 class="m-0" style="flex-shrink: 0; color: #495057;">
                            Теги <span class="text-muted" style="font-weight: 400;">(<?= count($allTags) ?>)</span>
                        </h5>
                        <div class="tag-cloud d-flex flex-wrap" style="gap: 8px;">
                            <?php foreach ($allTags as $t): ?>
                                <?php
                                    $isActive = $tag && $t->id == $tag->id;
                                    $color = $t->color ?? '#6c757d'; // предполагаю, что цвет тега где-то хранится
                                    echo Html::a(
                                        '<span class="tag-dot" style="background:' . $color . '"></span>'
                                        . Html::encode($t->name)
                                        . '<span class="tag-count">' . count($t->tagCardLinks) . '</span>',
                                        ['tag/view', 'id' => $t->id, 'date_range' => $date_range],
                                        [
                                            'class' => 'tag-pill' . ($isActive ? ' tag-pill-active' : ''),
                                            'style' => '--tag-color: ' . $color . ';',
                                            'encode' => false,
                                        ]
                                    );
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="row col-md-6">

                    <!-- Фильтры -->
                    <div class="tags-filters d-flex flex-wrap" style="gap: 16px;">
                        <div style="flex: 1 1 260px;">
                            <label class="filter-label">Выбрать тег</label>
                            <?= Select2::widget([
                                'name' => 'tag_id',
                                'value' => $tag ? $tag->id : null,
                                'data' => ArrayHelper::map($allTags, 'id', 'name'),
                                'options' => ['placeholder' => 'Выберите тег...'],
                                'pluginEvents' => [
                                    "change" => "function() {
                                        var url = new URL(window.location.href);
                                        url.searchParams.set('id', $(this).val());
                                        location.href = url.toString();
                                    }",
                                ]
                            ]) ?>
                        </div>
                        <div style="flex: 1 1 260px;">
                            <label class="filter-label">Период</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                </div>
                                <?= DateRangePicker::widget([
                                    'name' => 'date_range',
                                    'value' => $date_range,
                                    'convertFormat' => true,
                                    'pluginOptions' => [
                                        'locale' => ['format' => 'Y-m-d', 'separator' => ' - '],
                                        'opens' => 'left',
                                        'ranges' => [
                                            "Сегодня" => ["moment().startOf('day')", "moment()"],
                                            "Последние 7 дней" => ["moment().subtract(6, 'days')", "moment()"],
                                            "Последние 30 дней" => ["moment().subtract(29, 'days')", "moment()"],
                                            "Этот месяц" => ["moment().startOf('month')", "moment().endOf('month')"],
                                        ]
                                    ],
                                    'pluginEvents' => [
                                        "apply.daterangepicker" => "function(ev, picker) {
                                            var url = new URL(window.location.href);
                                            url.searchParams.set('date_range', picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
                                            location.href = url.toString();
                                        }",
                                    ],
                                    'options' => ['class' => 'form-control', 'style' => 'cursor:pointer;']
                                ]) ?>
                            </div>
                        </div>
                    </div>

        </div>

    </div>
</div>

        </div>
    </div>


    <?php if ($tag): ?>

    <div class="row mb-3">
        <div class="col-md-12 d-flex flex-row justify-content-between gap-4">
            <div class="card col-md-8 ">
                <div class="card-body">
                    <div id="chartdiv" style="width: 100%; height: 500px; background-color: #fff;"></div>
                    <?= $this->render('include/_lochart', ['chartData' => $chartData]) ?>
                </div>
            </div>


            <div class="col-md-4 me-0">
                <div class="card p-0 tag-cards-panel">
                    <div class="tag-cards-header">
                        <strong>В тег входят</strong>
                        <span class="tag-cards-count"><?= count($relatedCards) ?></span>
                    </div>
                    <ul class="tag-cards-list">
                        <?php foreach ($relatedCards as $card): ?>
                            <li class="tag-card-item">
                                <a href="/wb/detail?DPFilterForm[nm_id]=<?= Html::encode($card['nmId']) ?>"
                                   target="_blank" class="tag-card-nmid">
                                    <?= Html::encode($card['nmId']) ?>
                                </a>
                                <div class="tag-card-info">
                                    <div class="tag-card-name"><?= Html::encode($card['card_name'] ?: 'Без названия') ?></div>
                                    <div class="tag-card-vendor"><?= Html::encode($card['vendorCode']) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

        </div>
    </div>

    <?php
    $commonColumns = [
            [
                'attribute' => 'cnt',
                'label' => 'Кол-во',
                'hAlign' => 'right',
                'format' => ['decimal', 0],
                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => true,
            ],
            [
                'attribute' => 'cns',
                'label' => 'Отмена',
                'hAlign' => 'right',
                'format' => ['decimal', 0],
                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => true,
//                'pageSummary' => number_format($totalLO_CNS, 0, ',', ' '),
            ],
            [
                'attribute' => 'sum_ord',
                'label' => 'Сумма, ₽',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
                'contentOptions' => ['style' => 'font-weight:bold'],
                'pageSummary' => true,
//                'pageSummary' => number_format($totalLO_SUM, 1, ',', ' '),
            ],

            [
                'attribute' => 'tp',
                'label' => 'Цена Рзн, ₽',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
            ],
            [
                'attribute' => 'dsc',
                'label' => 'Скидка, %',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
            ],
            [
                'attribute' => 'apwd',
                'label' => 'Цена со ск, ₽',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
            ],
            [
                'attribute' => 'spp',
                'label' => 'СПП, %',
                'hAlign' => 'right',
                'format' => ['decimal', 2],
            ],
            [
                'attribute' => 'finished_price',
                'label' => 'Цена зкз, ₽',
                'headerOptions'  => ['style' => 'width:80px'],
                'contentOptions' => ['style' => 'width:80px; white-space: nowrap; align-content: center; text-align: right; font-weight:bold;'],
                'hAlign' => 'right',
                'format' => ['decimal', 2],
            ],

    ];

    // date_range приходит из view одной строкой "Y-m-d - Y-m-d" — разбираем для заголовка таблицы
    $rangeParts = explode(' - ', (string)$date_range);
    $rangeFromLabel = !empty($rangeParts[0]) ? Yii::$app->formatter->asDate($rangeParts[0], 'php:d.m.Y') : '';
    $rangeToLabel = !empty($rangeParts[1]) ? Yii::$app->formatter->asDate($rangeParts[1], 'php:d.m.Y') : '';

// Для ссылки (ГГГГ-ММ-ДД)
    $dateFrom = !empty($rangeParts[0]) ? Yii::$app->formatter->asDate($rangeParts[0], 'php:Y-m-d') : '';
    $dateTo = !empty($rangeParts[0]) ? Yii::$app->formatter->asDate($rangeParts[1], 'php:Y-m-d') : '';



    $columnsByProduct = [
        // ---- Товар/Заказ (фото + название + категория/бренд + артикулы), как в feed.php ----
        [
            'label' => 'Товар/Заказ',
            'format' => 'raw',
            'headerOptions' => ['style' => 'width:280px;'],
            'contentOptions' => ['style' => 'width:280px;'],
            'value' => function ($model) use ($dateFrom,$dateTo) {
                // card_photos может быть задвойным JSON-кодированием — см. комментарий в feed.php
                $photos = [];
                if (!empty($model['card_photos'])) {
                    $decoded = json_decode($model['card_photos'], true);
                    if (is_string($decoded)) {
                        $decoded = json_decode($decoded, true);
                    }
                    if (is_array($decoded)) {
                        $photos = $decoded;
                    }
                }
                $img = !empty($photos[0]) ? $photos[0] : '/images/no-photo.png';

                $photoTag = Html::img($img, [
                    'style' => 'width:50px; height:66px; object-fit:cover; border-radius:4px; flex-shrink:0;',
                    'alt' => '',
                ]);

                $title = Html::tag('div', Html::encode($model['card_title'] ?: '(нет карточки)'), [
                    'class' => 'cart-item-title',
                    'style' => 'white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 210px;',
                    'title' => Html::encode($row['card_title'] ?? ''),
                ]);

                $eyeIcon = '<a href="/wb-order/feed?DPFilterForm[nm_id]=' . $model['nm_id'].'&DPFilterForm[date_from]='.$dateFrom.'&DPFilterForm[date_to]='.$dateTo.'" class="icon-link icon-link-hover" target="_blank"><i class="bi bi-eye-fill me-1 lh-1" style="transform: none;"></i></a>'; 
                $content = $eyeIcon . Html::encode($model['card_subject_name']) . ' • ' . Html::encode($model['card_brand']);
                $breadcrumb = Html::tag('div', $content, [
                    'class' => 'cart-item-details d-flex align-items-center', // добавили флексы для идеального выравнивания по центру
                ]);


                $vendor = Html::tag('div', Html::encode($model['card_vendor_code'] ?? ''), [
                    'class' => 'cart-item-details',
                ]);

                $wbLink = Html::a('WB: ' . Html::encode($model['nm_id']), '/wb/detail?DPFilterForm[nm_id]=' . $model['nm_id'], [
                    'title' => 'Перейти в карточку',
                    'target' => '_blank',
                    'data-pjax' => '0',
                    'style' => 'text-decoration: none;',
                ]);
                $wb = Html::tag('div', $wbLink, ['class' => 'cart-item-details']);

                $textBlock = Html::tag('div', $title . $breadcrumb . $vendor . $wb, ['style' => 'min-width:0;']);

                return Html::tag('div', $photoTag . $textBlock, ['style' => 'display:flex; gap:10px; align-items:flex-start;']);
            },
        ],
        [
            'attribute' => 'cnt',
            'label' => 'Количество',
            'hAlign' => 'right',
            'format' => ['decimal', 0],
            'contentOptions' => ['style' => 'font-weight:bold'],
            'pageSummary' => true,
        ],
        [
            'attribute' => 'cns',
            'label' => 'Отменено',
            'hAlign' => 'right',
            'format' => ['decimal', 0],
            'pageSummary' => true,
        ],
        [
            'attribute' => 'byt',
            'label' => 'Выкуплено',
            'hAlign' => 'right',
            'format' => ['decimal', 0],
            'pageSummary' => true,
        ],
        [
            'attribute' => 'tp',
            'label' => 'Цена, ₽',
            'hAlign' => 'right',
            'format' => ['decimal', 2],
        ],
        [
            'attribute' => 'dsc',
            'label' => 'Скидка, %',
            'hAlign' => 'right',
            'format' => ['decimal', 2],
        ],
        [
            'attribute' => 'apwd',
            'label' => 'Цена со ск, ₽',
            'hAlign' => 'right',
            'format' => ['decimal', 2],
        ],
        [
            'attribute' => 'spp',
            'label' => 'СПП, %',
            'hAlign' => 'right',
            'format' => ['decimal', 2],
        ],
        [
            'attribute' => 'finished_price',
            'label' => 'Цена прод, ₽',
            'hAlign' => 'right',
            'format' => ['decimal', 2],
            'contentOptions' => ['style' => 'font-weight:bold'],
        ],
        [
            'attribute' => 'sum_ord',
            'label' => 'Сумма заказов, ₽',
            'hAlign' => 'right',
            'format' => ['decimal', 2],
            'contentOptions' => ['style' => 'font-weight:bold'],
            'pageSummary' => true,
        ],
        [
            'attribute' => 'sum_byt',
            'label' => 'Сумма выкупа, ₽',
            'hAlign' => 'right',
            'format' => ['decimal', 2],
            'contentOptions' => ['style' => 'font-weight:bold'],
            'pageSummary' => true,
        ],
    ];

    $columnsByDate = array_merge([
        [
            'attribute' => 'odate',
            'label' => 'Дата',
            'format' => ['date', 'php:d.m.Y'],
        ],
    ], $commonColumns);


    ?>
<div class="row mb-3 custom-compact-grid">
    <div class="col-md-12 grid_wbstat grid_orderbyproduct">
            <?php
            echo GridView::widget([
                'dataProvider' => $detailAgrProvider,
                    'export' => false, 
                    'toggleData' => false,
                    'pjax' => true,
                    'bordered' => true,
                    'striped' => true,
                    'condensed' => true,
                    'responsive' => true,
                    'hover' => true,
                    'showPageSummary' => true,
                    'showFooter' => false,
                    'panel' => [
                        'type' => GridView::TYPE_PRIMARY,
                        'heading' => 'Заказы по артикулам с ' . $rangeFromLabel . ' по ' . $rangeToLabel,
                        'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                        'footer' => false,
                    ],
                    'containerOptions' => [
                        'class' => 'no-border-class' 
                    ],

                'columns' => $columnsByProduct,
            ]);
            ?>
            </div>
</div>


<div class="row mb-3 custom-compact-grid">
    <div class="col-md-6 rounded">
            <div class="row grid_advstat grid_wbstat expandable-container">
            <?php
            echo GridView::widget([
                'dataProvider' => $summaryProvider,
                    'export' => false, 
                    'toggleData' => false,
                    'pjax' => true,
                    'bordered' => true,
                    'striped' => true,
                    'condensed' => true,
                    'responsive' => true,
                    'hover' => true,
                    'showPageSummary' => true,
                    'showFooter' => false,
                    'panel' => [
                        'type' => GridView::TYPE_PRIMARY,
                        'heading' => 'Заказы по датам',
                        'headingOptions' => ['class' => 'card-header text-white bg-wb'],
                        'footer' => false,
                    ],
                    'containerOptions' => [
                        'class' => 'no-border-class' 
                    ],

                'columns' => $columnsByDate,
            ]);
            ?>
            </div>
            <div class="expand-btn-wrapper">
                <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
            </div>
    </div>
    <div class="col-md-6 rounded">
    </div>
</div>

<?php /*

<div class="row mb-3">
        <div class="nav-tabs-custom card">
            <ul class="nav nav-tabs p-2 bg-light">
                <li class="nav-item">
                    <a class="nav-link active" href="#tab_summary" data-bs-toggle="tab" data-toggle="tab">Сводная по датам</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#tab_details" data-bs-toggle="tab" data-toggle="tab">Детально по артикулам</a>
                </li>
            </ul>
            <div class="tab-content p-3">
                <div class="tab-pane active" id="tab_summary">
                    <?= GridView::widget([
                        'dataProvider' => $summaryProvider,
                        'summary' => false,
                        'columns' => [
                            ['attribute' => 'odate', 'label' => 'Дата', 'format' => 'date'],
                            ['attribute' => 'cnt', 'label' => 'Заказов'],
                            ['attribute' => 'sum', 'label' => 'Сумма', 'format' => ['decimal', 0]],
                        ],
                    ]); ?>
                </div>
                <div class="tab-pane" id="tab_details">
                    <?= GridView::widget([
                        'dataProvider' => $detailProvider,
                        'columns' => [
                            'odate:date:Дата',
                            ['attribute' => 'nm_id', 'label' => 'Арт WB'],
                            ['attribute' => 'cnt', 'label' => 'Заказов'],
                            ['attribute' => 'sum', 'label' => 'Сумма', 'format' => ['decimal', 0]],
                        ],
                    ]); ?>
                </div>
            </div>
        </div>
*/ ?>
    <?php endif; ?>
</div>



</div> 

<style>
.input-group-text { background-color: #f8f9fa; border-right: 0; }
.form-control { border-left: 0; }
</style>

<script>
if (typeof jQuery !== 'undefined') {
        $('.nav-tabs a').on('click', function (e) {
            e.preventDefault();
            $(this).tab('show');
        });
    }
</script>

<style>
.tag-cards_list {list-style-type: none; font-size: 12px;}
.tag-cards_list a { text-decoration: none; }
.tag-cards_list li { margin-bottom: 10px; }
</style>


<style>
    
.tags-card {
    border: none;
    border-top: 3px solid #28a745;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border-radius: 8px;
}

.filter-label {
    display: block;
    font-size: 12px;
    color: #868e96;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.tag-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px 5px 10px;
    border-radius: 20px;
    background: color-mix(in srgb, var(--tag-color) 12%, white);
    color: #343a40;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    border: 1px solid color-mix(in srgb, var(--tag-color) 25%, white);
    transition: transform 0.12s ease, box-shadow 0.12s ease;
}

.tag-pill:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    text-decoration: none;
    color: #212529;
}

.tag-pill-active {
    background: var(--tag-color);
    color: #fff;
    border-color: var(--tag-color);
}

.tag-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.tag-count {
    opacity: 0.65;
    font-size: 11px;
}

.tags-filters {
    padding-top: 12px;
    margin-top: 4px;
    border-top: 1px solid #f1f3f5;
}
    

.tag-cards-panel {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
}

.tag-cards-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f3f5;
    font-size: 14px;
}

.tag-cards-count {
    background: #eef1f4;
    color: #495057;
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
}

.tag-cards-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 420px;
    overflow-y: auto;
}

.tag-card-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 16px;
    border-bottom: 1px solid #f8f9fa;
    font-size: 13px;
    transition: background 0.12s ease;
}

.tag-card-item:last-child { border-bottom: none; }
.tag-card-item:hover { background: #f8f9fb; }

.tag-card-nmid {
    flex-shrink: 0;
    display: inline-block;
    background: #eef4ff;
    color: #3b6fd6;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 12px;
    text-decoration: none;
    white-space: nowrap;
}

.tag-card-nmid:hover {
    background: #dce8ff;
    text-decoration: none;
}

.tag-card-info { min-width: 0; }

.tag-card-name {
    color: #212529;
    line-height: 1.3;
}

.tag-card-vendor {
    color: #868e96;
    font-size: 11px;
    margin-top: 2px;
    word-break: break-word;
}

/* Итоговая строка (page summary) таблицы "Заказы по артикулам" — крупнее и заметнее */
.grid_orderbyproduct .kv-page-summary td,
.grid_orderbyproduct tfoot td {
    font-size: 15px !important;
    font-weight: 700 !important;
    padding-top: 10px !important;
    padding-bottom: 10px !important;
}
</style>