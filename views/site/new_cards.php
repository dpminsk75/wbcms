<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var array $newCards */
/** @var string $dateFrom */
/** @var string $dateTo */
/** @var string $titleFilter */
/** @var string $sort */

$this->title = 'Новые карточки';
$this->params['breadcrumbs'][] = ['label' => 'Главная', 'url' => ['/site/index']];
$this->params['breadcrumbs'][] = $this->title;

$sortOptions = [
    'created_desc' => 'По дате (новые → старые)',
    'created_asc'  => 'По дате (старые → новые)',
    'nmid_asc'     => 'По nmID (↑)',
    'nmid_desc'    => 'По nmID (↓)',
    'title_asc'    => 'По названию (А → Я)',
    'title_desc'   => 'По названию (Я → А)',
];
?>

<div class="site-new-cards">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-2 mb-md-0"><?= Html::encode($this->title) ?></h1>
        <span class="badge bg-secondary" style="font-size:13px;">Найдено: <?= count($newCards) ?></span>
    </div>

    <div class="card mb-4" style="border: 1px solid var(--bs-border-color-translucent);">
        <div class="card-body p-3 bg-light">
            <form method="get" action="<?= Url::to(['site/new-cards']) ?>" class="row g-3 align-items-end">
                <div class="col-12 col-md-2">
                    <label class="form-label" style="font-size:12px; font-weight:600;">Дата с</label>
                    <input type="date" name="dateFrom" value="<?= Html::encode($dateFrom) ?>" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label" style="font-size:12px; font-weight:600;">Дата по</label>
                    <input type="date" name="dateTo" value="<?= Html::encode($dateTo) ?>" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" style="font-size:12px; font-weight:600;">Часть названия</label>
                    <input type="text" name="title" value="<?= Html::encode($titleFilter) ?>" placeholder="например, журнал" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" style="font-size:12px; font-weight:600;">Сортировка</label>
                    <select name="sort" class="form-select form-select-sm">
                        <?php foreach ($sortOptions as $value => $label): ?>
                            <option value="<?= Html::encode($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= Html::encode($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Показать</button>
                    <a href="<?= Url::to(['site/new-cards']) ?>" class="btn btn-outline-secondary btn-sm">Сброс</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($newCards)): ?>
        <div class="alert alert-warning">Карточки за выбранный период не найдены. Попробуйте изменить даты или фильтр по названию.</div>
    <?php else: ?>
        <div class="card mb-4" style="border: 1px solid var(--bs-border-color-translucent); border-radius: 8px; overflow: hidden;">
            <div class="card-header text-white" style="font-size: 13px; font-weight: bold; background-color: #4b4b4b; padding: 10px 15px;">
                Новые карточки: <?= Html::encode($dateFrom) ?> — <?= Html::encode($dateTo) ?> (<?= count($newCards) ?>)
                <?php if ($titleFilter !== ''): ?>
                    · фильтр: «<?= Html::encode($titleFilter) ?>»
                <?php endif; ?>
            </div>
            <div class="card-body p-3 bg-light">
                <div class="row g-3">
                    <?php foreach ($newCards as $card): ?>
                        <?php
                            $imgSrc = null;
                            if (!empty($card['photos'])) {
                                $photosList = json_decode($card['photos'], true);
                                if (is_string($photosList)) {
                                    $photosList = json_decode($photosList, true);
                                }
                                if (is_array($photosList) && !empty($photosList)) {
                                    $imgSrc = $photosList[0];
                                }
                            }
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
                                        Арт WB: <a href="/wb/detail?DPFilterForm[nm_id]=<?= Html::encode($card['nmID']) ?>" target="_blank" style="text-decoration: none; color: #0d6efd; font-weight: 500;"><?= Html::encode($card['nmID']) ?></a>
                                    </div>
                                    <div style="font-size: 12px; color: #495057; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= Html::encode($card['vendorCode'] ?? '—') ?>">
                                        Арт прод: <?= Html::encode($card['vendorCode'] ?? '—') ?>
                                    </div>
                                    <div style="font-size: 11px; color: #adb5bd; margin-top: 4px; border-top: 1px dashed #e9ecef; padding-top: 4px;">
                                        Добавлена: <?= Html::encode($dateAdded) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
