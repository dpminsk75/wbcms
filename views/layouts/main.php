<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;

use yii\bootstrap\Modal;

AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport',    'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords',    'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon',         'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>

    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <!-- 180x180 - ставим первым для safari --> 
    <link rel="icon" href="/favicon.ico" sizes="any"><!-- 32x32 --> 
    <link rel="icon" href="/icon.svg" type="image/svg+xml"> 
    <link rel="manifest" href="/manifest.webmanifest">

    <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<header id="header">
<?php if (!Yii::$app->user->isGuest): ?>
    <?php $topMenuItems = \app\components\MenuHelper::getMenuItems('top'); ?>

    <!-- Мобильная панель с гамбургером (видна только < 768px), пункты те же, что и в десктопном меню -->
    <div class="mobile-topbar d-md-none">
        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobileNavDrawer">
            <span></span><span></span><span></span>
        </button>
        <span class="mobile-topbar-brand">
            <?= Html::img('@web/_icons/logo_50px.png', [
                'alt' => Yii::$app->name,
                'style' => 'height:24px; vertical-align: middle; margin-right: 8px;'
            ]) ?>Аналитика WB
        </span>
    </div>
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    <div class="mobile-nav-drawer" id="mobileNavDrawer">
        <?= Nav::widget([
            'encodeLabels' => false,
            'options' => ['class' => 'nav mobile-nav-drawer__list'],
            'items' => $topMenuItems,
        ]) ?>
    </div>

    <!-- Десктопная версия меню — без изменений, просто скрыта на мобильных -->
    <div class="d-none d-md-block">
    <?php
    NavBar::begin([
        'brandLabel' => Yii::$app->name,
        'brandUrl' => Yii::$app->homeUrl,
        'brandLabel' => Html::img('@web/_icons/logo_50px.png', [
            'alt' => Yii::$app->name,
            'style' => 'height:30px; vertical-align: middle; margin-right: 10px;' 
        ]) . 'Аналитика WB', 
//        'options' => ['class' => '1']
        'options' => ['class' => 'navbar-nav navbar-expand wb-menu__list bg-wb'],
//        'options' => ['class' => 'wb-menu__list'],

    ]);

    echo Nav::widget([
        'encodeLabels' => false, // ВАЖНО: чтобы HTML в label работал
        'options' => ['class' => 'navbar-nav wb-menu__list'],
        'items' => $topMenuItems, // \Yii::$app->params['mainMenu'],
    ]);
    NavBar::end();
    ?>
    </div>
<?php endif; ?>
</header>

<main id="main" class="flex-shrink-0" role="main">
    <div class="container-xxl "> <?php /* container-xxl  container-flui px-4*/ ?>
            <?php if (!empty($this->params['breadcrumbs'])): ?>
                <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
            <?php endif ?>
            <?= Alert::widget() ?>
            <?= $content ?>
    </div>
</main>

<footer id="footer" class="mt-auto py-3 bg-light">
    <div class="container">
<?php if (!Yii::$app->user->isGuest): ?>
        <div class="row text-muted">
            <div class="col-md-6 text-center text-md-start">&copy; Толока <?= date('Y') ?></div>
        </div>
<?php endif; ?>
    </div>
</footer>

<?php $this->endBody() ?>
<style>
main > .container-xxl {
     padding: 20px 15px 20px;
}

</style>

<?php
$this->registerCssFile('@web/css/mobile-nav.css');
$this->registerJsFile('@web/js/mobile-nav.js');
?>


<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
    <div id="copyToast" class="toast align-items-center text-white bg-dark border-0" 
         role="alert" aria-live="assertive" aria-atomic="true" 
         data-bs-autohide="true">
        <div class="d-flex">
            <div class="toast-body">
                Скопировано в буфер обмена
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php
Modal::begin([
    'header' => '<h3 class="modal-title" style="font-weight: bold; color: #2c3e50;">Детализация</h3>',
    'id' => 'universal-feedback-modal',
    'size' => Modal::SIZE_LARGE,
    // Переопределяем кнопку закрытия, убирая конфликтующий aria-hidden
    'closeButton' => [
        'tag' => 'button',
        'class' => 'close',
        'data-dismiss' => 'modal',
        'aria-label' => 'Close', // Вместо aria-hidden="true" используем понятный для ассистивных технологий label
        'style' => '
                margin: 0px 10px 0px 0px;
                background: #f3f4f6;
                border: none;
                border-radius: 50%;
                width: 32px;
                height: 32px;
                opacity: 0.8;
                font-weight: 300;
                font-size: 22px;
                line-height: 1;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                padding-bottom: 4px;
                outline: none;
            '
    ],
]);
echo '<div id="universal-modal-content"><div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i> Загрузка...</div></div>';
Modal::end();
?>

<script>
// 1. Вызов попапа и загрузка данных по AJAX
$(document).on('click', '.trigger-feedback-popup', function (e) {
    e.preventDefault();
    
    var button = $(this);
    var dateFrom = button.data('from');
    var dateTo = button.data('to');
    var nmId = button.data('nmid');
    
    var modal = $('#universal-feedback-modal');
    var container = $('#universal-modal-content');
    
    container.html('<div class="text-center" style="padding:20px;"><i class="glyphicon glyphicon-refresh spin"></i> Загрузка данных...</div>');
    modal.modal('show');
    
    $.ajax({
        url: '/feedback/get-popup-report',
        type: 'GET',
        data: {
            date_from: dateFrom,
            date_to: dateTo,
            nmID: nmId
        },
        success: function (html) {
            container.html(html);
        },
        error: function () {
            container.html('<div class="alert alert-danger">Ошибка при загрузке данных. Попробуйте позже.</div>');
        }
    });
});

// 2. Исправление ошибки aria-hidden в консоли при открытии окна
$('#universal-feedback-modal').on('shown.bs.modal', function () {
    $(this).find('.close').removeAttr('aria-hidden');
});

// 3. Гарантированное закрытие окна при клике на крестик
$(document).on('click', '#universal-feedback-modal .close', function (e) {
    e.preventDefault();
    $('#universal-feedback-modal').modal('hide');
});

$(document).on('click', '.trigger-detail-popup', function (e) {
    e.preventDefault();
    
    var button = $(this);
    var dateFrom = button.data('from');
    var dateTo = button.data('to');
    var nmId = button.data('nmid');
    var type = button.data('type'); // 'shf' или 'udr'
    
    var modal = $('#universal-feedback-modal');
    var container = $('#universal-modal-content');
    
    container.html('<div class="text-center" style="padding:20px;"><i class="glyphicon glyphicon-refresh spin"></i> Загрузка детализации...</div>');
    modal.modal('show');
    
    $.ajax({
        url: '/detail/get-popup-report', // Обращаемся к новому DetailController
        type: 'GET',
        data: {
            date_from: dateFrom,
            date_to: dateTo,
            nmID: nmId,
            type: type
        },
        success: function (html) {
            container.html(html);
        },
        error: function () {
            container.html('<div class="alert alert-danger">Ошибка при загрузке детализации. Попробуйте позже.</div>');
        }
    });
});
</script>

<style>
@media (min-width: 768px) {
    #universal-feedback-modal .modal-dialog {
        width: 95% !important;
        max-width: 1400px !important;
    }
}
</style>
</body>
</html>
<?php $this->endPage() ?>