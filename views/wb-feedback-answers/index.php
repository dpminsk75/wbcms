<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;
use kartik\grid\GridView;
use kartik\select2\Select2;

/** @var \yii\web\View $this */
/** @var \yii\data\SqlDataProvider $dataProvider */
/** @var array $companies */
/** @var array $cardsList */
/** @var array $rulesById */
/** @var array $tagsSentiment */
/** @var array $filter */

/**
 * Разворачивает JSON-поле, устойчиво к двойному кодированию (строка внутри строки).
 */
$decodeJsonField = function ($raw) {
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === 'null') {
        return null;
    }
    $decoded = Json::decode($raw);
    $guard = 0;
    while (is_string($decoded) && $guard < 3) {
        $decoded = Json::decode($decoded);
        $guard++;
    }
    return $decoded;
};

$this->title = 'Отзывы и ответы';
$this->params['breadcrumbs'][] = $this->title;

$this->registerCss('
    .wb-answer-text {
        font-size: 0.9em;
    }
');
?>

<div class="wb-feedback-answers-index">

 <h1><?= Html::encode($this->title) ?></h1>

<div class="bg-light p-3 rounded mb-4">
    <form method="get">
        
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-2">
                <label class="form-label fw-medium text-muted small mb-1">С даты</label>
                <?= Html::input('date', 'dateFrom', $filter['dateFrom'], ['class' => 'form-control']) ?>
            </div>
            
            <div class="col-12 col-md-2">
                <label class="form-label fw-medium text-muted small mb-1">По дату</label>
                <?= Html::input('date', 'dateTo', $filter['dateTo'], ['class' => 'form-control']) ?>
            </div>
            
            <div class="col-12 col-md-3">
                <label class="form-label fw-medium text-muted small mb-1">Магазин</label>
                <?= Html::dropDownList(
                    'company_id',
                    $filter['company_id'],
                    ['' => 'Все магазины'] + \yii\helpers\ArrayHelper::map($companies, 'id', 'name'),
                    ['class' => 'form-select']
                ) ?>
            </div>
            
            <div class="col-12 col-md-5">
                <label class="form-label fw-medium text-muted small mb-1">Товар (артикул)</label>
                <?= Select2::widget([
                    'name' => 'nmID',
                    'value' => $filter['nmID'],
                    'data' => $cardsList,
                    'options' => ['placeholder' => 'Начните вводить название или артикул...'],
                    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                ]) ?>
            </div>
        </div>

        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label fw-medium text-muted small mb-1">Оценка</label>
                <?= Html::dropDownList(
                    'rating',
                    $filter['rating'],
                    ['' => 'Все оценки', 5 => '5 ★', 4 => '4 ★', 3 => '3 ★', 2 => '2 ★', 1 => '1 ★'],
                    ['class' => 'form-select']
                ) ?>
            </div>
            
            <div class="col-6 col-md-2">
                <label class="form-label fw-medium text-muted small mb-1">Статус ответа</label>
                <?= Html::dropDownList(
                    'status',
                    $filter['status'],
                    ['' => 'Все', 'answered' => 'Отвечено', 'not_answered' => 'Без ответа'],
                    ['class' => 'form-select']
                ) ?>
            </div>
            
            <div class="col-12 col-md-4 d-flex align-items-center gap-3 pb-2 mt-3 mt-md-0">
                <div class="form-check mb-0">
                    <?= Html::checkbox('hasMedia', $filter['hasMedia'] === '1', ['id' => 'hasMedia', 'value' => '1', 'class' => 'form-check-input']) ?>
                    <label class="form-check-label user-select-none" for="hasMedia">Только с медиа</label>
                </div>
                <div class="form-check mb-0">
                    <?= Html::checkbox('paidOnly', $filter['paidOnly'] === '1', ['id' => 'paidOnly', 'value' => '1', 'class' => 'form-check-input']) ?>
                    <label class="form-check-label user-select-none" for="paidOnly">Только платные</label>
                </div>
                <div class="form-check mb-0">
                    <?= Html::checkbox('hideAnswers', $filter['hideAnswers'] === '1', ['id' => 'hideAnswers', 'value' => '1', 'class' => 'form-check-input']) ?>
                    <label class="form-check-label user-select-none" for="hideAnswers">Не выводить ответы</label>
                </div>
            </div>

            <div class="col-12 col-md-4 text-md-end mt-4 mt-md-0">
                <button type="submit" class="btn btn-primary px-4">Применить</button>
                <a href="<?= Url::to(['index']) ?>" class="btn btn-outline-secondary px-4">Сбросить</a>
            </div>
        </div>
        
    </form>
</div>


<?php $gridColumns = [
            // 1. КОЛОНКА "ТОВАР" (Название, nmID, Оплачен)
            [
                'attribute' => 'product_title',
                'label' => 'Товар',
                'value' => function ($model) {
                    $title = Html::encode($model['product_title'] ?: '(без названия)');
                    $nmId = Html::encode($model['nmID']);
                    
                    // Формируем вывод названия и nmID
                    $out = "<div class=\"fw-bold mb-1\">{$title}</div>";
                    $out .= "<div class=\"text-muted small\">Арт WB: ". 
                            Html::a((string)$nmId, "/wb/detail?DPFilterForm[nm_id]=".$nmId, ['title' => 'Перейти в карточку', 'target' => '_blank', 'data-pjax' => '0',  'style' => 'text-decoration: none;' ]).
                            "</div>";

                    // Проверяем, есть ли оплата, и добавляем бейдж
                    $fCost = (float)($model['f_cost'] ?? 0);
                    if ($fCost > 0) {
                        $out .= '<div class="mt-2">' . Html::tag('span', 'Оплачен: ' . number_format($fCost, 0, ',', ' ') . ' ₽', [
                            'class' => 'badge bg-warning text-dark',
                            'title' => 'Отзыв по платной акции',
                        ]) . '</div>';
                    }

                    return $out;
                },
                'format' => 'raw',
                'contentOptions' => ['style' => 'width: 25%;'], // Задаем примерную ширину
            ],

            // 2. КОЛОНКА "ОТЗЫВ" (Дата, Рейтинг, Текст, Медиа, Теги)

// 2. КОЛОНКА "ОТЗЫВ" (Дата, Рейтинг, Текст, Медиа, Теги)
            [
                'label' => 'Отзыв',
                'value' => function ($model) use ($decodeJsonField, $tagsSentiment) {
                    // --- Шапка отзыва (Дата + Рейтинг) ---
                    $date = $model['createdDate'] ? date('d.m.Y H:i', strtotime($model['createdDate'])) : '';
                    $ratingNum = (int)$model['productValuation'];
                    
                    $ratingColor = $ratingNum >= 4 ? 'text-warning' : ($ratingNum == 3 ? 'text-secondary' : 'text-danger');
                    $ratingStars = "<span class=\"{$ratingColor} fs-6\">" . str_repeat('★', $ratingNum) . str_repeat('☆', 5 - $ratingNum) . "</span>";
                    
                    $headerHtml = "<div class=\"d-flex justify-content-between align-items-center mb-2\">
                                     <span class=\"text-muted small\">{$date}</span>
                                     <span>{$ratingStars}</span>
                                   </div>";

                    // --- Текст отзыва ---
                    $text = trim((string)$model['text']);
                    $pros = trim((string)$model['pros']);
                    $cons = trim((string)$model['cons']);

                    if ($text === '' && $pros === '' && $cons === '') {
                        $textHtml = '<div class="text-muted fst-italic">без текста</div>';
                    } else {
                        $parts = [];
                        if ($text !== '') $parts[] = Html::encode($text);
                        if ($pros !== '') $parts[] = '<strong>Достоинства:</strong> ' . Html::encode($pros);
                        if ($cons !== '') $parts[] = '<strong>Недостатки:</strong> ' . Html::encode($cons);
                        $textHtml = '<div>' . implode('<br>', $parts) . '</div>';
                    }

                    // --- Медиа (Видео и Фото) ---
                    $mediaOut = [];
                    $height = 120; // Задаем высоту для всех медиа-элементов, можешь поменять на нужную
                    
                    $photos = $decodeJsonField($model['photoLinks']);
                    $video = $decodeJsonField($model['video']);

                    // Собираем плоский список фоток, чтобы вытащить оттуда обложку для видео, если понадобится
                    $photoUrls = [];
                    if (is_array($photos)) {
                        foreach ($photos as $p) {
                            $photoUrls[] = $p['fullSize'] ?? $p['miniSize'] ?? '';
                        }
                        $photoUrls = array_filter($photoUrls);
                    }
/*
                    // 1. Видео идет первым
                    $videoLink = is_array($video) ? ($video['link'] ?? '') : (is_string($video) ? $video : '');
                    if (!empty($videoLink)) {
                        $poster = (is_array($video) && !empty($video['previewImage'])) 
                            ? $video['previewImage'] 
                            : (!empty($photoUrls) ? reset($photoUrls) : '');

                        $mediaOut[] = Html::tag('div', 
                            Html::tag('video', '', [
                                'src' => $videoLink,
                                'controls' => true,
                                'poster' => $poster,
                                'style' => "height: {$height}px; width: auto; border-radius: 4px; background: #000; max-width: 100%;"
                            ]), 
                            ['style' => 'flex: 0 0 auto; margin-right: 10px; margin-bottom: 10px; position: relative;']
                        );
                    }

*/

// 1. Видео идет первым
                    $videoLink = is_array($video) ? ($video['link'] ?? '') : (is_string($video) ? $video : '');
                    if (!empty($videoLink)) {
                        $poster = (is_array($video) && !empty($video['previewImage'])) 
                            ? $video['previewImage'] 
                            : (!empty($photoUrls) ? reset($photoUrls) : '');

                        $mediaOut[] = Html::tag('div', 
                            Html::tag('video', '', [
                                'src' => $videoLink,
                                'controls' => true,
                                'poster' => $poster,
                                'style' => "height: {$height}px; width: auto; border-radius: 4px; background: #000; max-width: 100%; display: block; transition: height 0.2s ease-in-out;"
                            ]) . 
                            // Контейнер с контролами под видео
                            Html::tag('div', 
                                // Ссылка для изменения размера прямо на странице
                                Html::a('+', '#', [
                                    'class' => 'text-decoration-none text-primary fw-medium me-2',
                                    'style' => 'font-size: 12px; cursor: pointer;',
                                    'onclick' => "var v = this.parentElement.previousElementSibling; if(v.style.height === '{$height}px'){ v.style.height = '360px'; this.innerText = 'Уменьшить'; } else { v.style.height = '{$height}px'; this.innerText = '+'; } return false;"
                                ]) . 
                                // Разделительная точка
                                Html::tag('span', '•', ['class' => 'text-muted small me-2']) .
                                // Ссылка для открытия видео в новой вкладке
                                Html::a('>>', $videoLink, [
                                    'class' => 'text-decoration-none text-secondary fw-medium',
                                    'style' => 'font-size: 12px;',
                                    'target' => '_blank',
                                    'rel' => 'noopener'
                                ]),
                                ['class' => 'mt-1 d-flex align-items-center justify-content-center']
                            ), 
                            ['style' => 'flex: 0 0 auto; margin-right: 10px; margin-bottom: 10px; text-align: center;']
                        );
                    }

                    // 2. Фотографии идут следом
                    if (is_array($photos)) {
                        foreach ($photos as $photo) {
                            $mini = $photo['miniSize'] ?? $photo['fullSize'] ?? '';
                            $full = $photo['fullSize'] ?? $mini;
                            
                            if ($mini) {
                                $img = Html::img($mini, [
                                    'style' => "height: {$height}px; width: auto; flex: 0 0 auto; margin-right: 10px; margin-bottom: 10px; border-radius: 4px; border: 1px solid #ddd;",
                                    'loading' => 'lazy',
                                ]);
                                // Оставляем ссылку для увеличения при клике (открытие оригинала в новой вкладке)
                                $mediaOut[] = Html::a($img, $full, ['target' => '_blank', 'rel' => 'noopener']);
                            }
                        }
                    }

                    $mediaHtml = $mediaOut ? '<div class="mt-3" style="display: flex; flex-wrap: wrap;">' . implode('', $mediaOut) . '</div>' : '';

                    // --- Теги ---
                    $tags = $decodeJsonField($model['bables']);
                    $tagsHtml = '';
                    if (is_array($tags) && !empty($tags)) {
                        $colorMap = ['positive' => 'success', 'negative' => 'danger', 'neutral'  => 'secondary'];
                        $tagOut = [];
                        foreach ($tags as $tag) {
                            $tag = trim((string)$tag);
                            if ($tag === '') continue;
                            $sentiment = $tagsSentiment[$tag] ?? 'neutral';
                            $color = $colorMap[$sentiment] ?? 'secondary';
                            $tagOut[] = Html::tag('span', Html::encode($tag), ['class' => "badge bg-{$color}"]);
                        }
                        if (!empty($tagOut)) {
                            $tagsHtml = '<div class="mt-2 d-flex flex-wrap gap-1">' . implode('', $tagOut) . '</div>';
                        }
                    }

                    return $headerHtml . $textHtml . $mediaHtml . $tagsHtml;
                },
                'format' => 'raw',
                'contentOptions' => ['style' => 'width: 45%;'],
            ],
        ];

        // 3. КОЛОНКА "ОТВЕТ" — не добавляется вовсе, если включена галка "Не выводить ответы"
        if ($filter['hideAnswers'] !== '1') {
            $gridColumns[] = [
                'label' => 'Ответ',
                'value' => function ($model) use ($decodeJsonField) {
                    $decoded = $decodeJsonField($model['answer']);
                    if ($decoded === null) {
                        return '<span class="text-muted">—</span>';
                    }

                    if (is_array($decoded) && isset($decoded['text'])) {
                        $answerText = $decoded['text'];
                    } else {
                        $answerText = (string)$model['answer'];
                    }

                    $date = $model['updatedDate'] ? date('d.m.Y H:i', strtotime($model['updatedDate'])) : '';

                    return "<div class=\"text-muted small mb-1\">{$date}</div>
                            <div class=\"wb-answer-text\">" . nl2br(Html::encode($answerText)) . "</div>";
                },
                'format' => 'raw',
                'contentOptions' => ['style' => 'width: 25%;'],
            ];
        }

        // 4. КОЛОНКА "ПРАВИЛО"
        $gridColumns[] = [
            'label' => 'Правило',
            'value' => function ($model) use ($rulesById) {
                if (empty($model['rule_id'])) {
                    return '<span class="text-muted">—</span>';
                }
                $title = $rulesById[$model['rule_id']] ?? 'Неизвестное правило';
                return Html::tag('span', Html::encode($model['rule_id']), [
                    'title' => $title,
                    'style' => 'cursor: help; border-bottom: 1px dotted #999;',
                    'data-bs-toggle' => 'tooltip', // Если подключены тултипы Bootstrap
                ]);
            },
            'format' => 'raw',
            'contentOptions' => ['style' => 'text-align: center; vertical-align: middle; width: 5%;'],
        ];
        ?>

        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'rowOptions' => function ($model) {
                $fCost = (float)($model['f_cost'] ?? 0);
                return $fCost > 0 ? ['style' => 'background-color: #fff8e1;'] : [];
            },
            'columns' => $gridColumns,
        ]) ?>

</div>
