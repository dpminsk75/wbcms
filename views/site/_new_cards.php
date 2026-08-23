<?php
use yii\helpers\Html;
use yii\db\Query;
use yii\db\Expression; // Подключаем класс для работы с произвольными SQL-выражениями

// Получаем дату 14 дней назад
$date14DaysAgo = date('Y-m-d 00:00:00', strtotime('-14 days'));

// Запрашиваем новые карточки из таблицы wbcards с приоритетной сортировкой по символам в vendorCode
$newCards = (new Query())
    ->select(['nmID', 'vendorCode', 'title', 'brand', 'photos', 'created_at'])
    ->from('wbcards')
    ->where(['>=', 'created_at', $date14DaysAgo])
    ->orderBy([
        new Expression("
            CASE 
                WHEN vendorCode LIKE '!%' THEN 1
                WHEN vendorCode LIKE '$%' THEN 2
                WHEN vendorCode LIKE '#%' THEN 3
                ELSE 4
            END ASC
        "),
        'created_at' => SORT_DESC // Внутри групп сортируем по дате добавления (сначала новые)
    ])
    ->all();

// Если новинок нет, ничего не выводим
if (empty($newCards)) {
    return;
}
?>

<div class="card mb-4 expandable-container" style="border: 1px solid var(--bs-border-color-translucent); border-radius: 8px; overflow: hidden;">
    <div class="card-header text-white" style="font-size: 13px; font-weight: bold; background-color: #4b4b4b; padding: 10px 15px;">
        Новые карточки за последние 14 дней (<?= count($newCards) ?>)
    </div>
    <div class="card-body p-3 bg-light"> 
        <div class="row g-3"> 
            <?php foreach ($newCards as $index => $card): ?>
                <?php 
                    $imgSrc = null;
                    
                    // Декодируем массив картинок из JSON с учетом возможной двойной сериализации
                    if (!empty($card['photos'])) {
                        $photosList = json_decode($card['photos'], true);
                        if (is_string($photosList)) {
                            $photosList = json_decode($photosList, true);
                        }
                        if (is_array($photosList) && !empty($photosList)) {
                            $imgSrc = $photosList[0];
                        }
                    }
                    
                    // Форматируем дату добавления
                    $dateAdded = !empty($card['created_at']) 
                        ? date('d.m.Y', strtotime($card['created_at'])) 
                        : '—';
                ?>
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="d-flex p-2 h-100" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                        
                        <div class="flex-shrink-0 me-3" style="width: 80px; height: 110px; border-radius: 6px; overflow: hidden; background-color: #f1f1f1; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
                            <?php if ($imgSrc): ?>
                                <img src="<?= Html::encode($imgSrc) ?>" alt="Фото товара" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div class="Image-preview--empty__-zsXJE1SrD">
                                    <svg fill="none" height="24" viewBox="-2 -2 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
                                        <path clip-rule="evenodd" d="M2 0H18C19.1046 0 20 0.89543 20 2V18C20 19.1046 19.1046 20 18 20H2C0.89543 20 0 19.1046 0 18V2C0 0.89543 0.89543 0 2 0ZM2 2V13.5858L6 9.58579L9.5 13.0858L16 6.58579L18 8.58579V2H2ZM2 18V16.4142L6 12.4142L11.5858 18H2ZM18 18H14.4142L10.9142 14.5L16 9.41421L18 11.4142V18ZM12 6C12 4.34315 10.6569 3 9 3C7.34315 3 6 4.34315 6 6C6 7.65685 7.34315 9 9 9C10.6569 9 12 7.65685 12 6ZM8 6C8 5.44772 8.44771 5 9 5C9.55229 5 10 5.44772 10 6C10 6.55228 9.55229 7 9 7C8.44771 7 8 6.55228 8 6Z" fill="#4e4e53" fill-rule="evenodd"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex-grow-1" style="display: flex; flex-direction: column; justify-content: center; min-width: 0;">
                            <div style="font-size: 14px; font-weight: 500; color: #2c3e50; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= Html::encode($card['title'] ?? 'Без названия') ?>">
                                <?= Html::encode($card['title'] ?? 'Без названия') ?>
                            </div>
                            <div style="font-size: 12px; color: #6c757d; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                Журналы · <?= Html::encode($card['brand'] ?? 'Делаем сами. Толока') ?>
                            </div>
                            <div style="font-size: 12px; color: #495057; margin-bottom: 2px;">
                                Арт WB: <a href="/wb/detail?DPFilterForm[nm_id]=<?= $card['nmID'] ?>" target="_blank" style="text-decoration: none; color: #0d6efd; font-weight: 500;"><?= Html::encode($card['nmID']) ?></a>
                            </div>
                            <div style="font-size: 12px; color: #495057; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= Html::encode($card['vendorCode'] ?? '—') ?>">
                                Арт прод: <?= Html::encode($card['vendorCode'] ?? '—') ?>
                            </div>
                            <div style="font-size: 11px; color: #adb5bd; margin-top: 4px; border-top: 1px dashed #e9ecef; padding-top: 4px;">
                                Добавлена: <?= $dateAdded ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="expand-btn-wrapper">
    <button class="btn btn-outline-primary btn-sm btn-toggle-expand">Увидеть больше</button>
</div>