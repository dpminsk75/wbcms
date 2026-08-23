<?php

/**
 * Шаблон параметров приложения.
 * Разворачивание: скопировать в config/params.php и подставить реальные ключи.
 * Реальный params.php содержит живые токены и не должен попадать в git (см. .gitignore).
 */

return [
    'bsVersion' => '5.x',
    'icon-framework' => \kartik\icons\Icon::FAS,
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',

    // Токен WB API (JWT). Получается в кабинете WB: Настройки -> Доступ к API
    'wbApiTokenContent' => 'ЗАМЕНИТЕ_НА_РЕАЛЬНЫЙ_JWT_ТОКЕН_WB',

    // DaData (стандартизация адресов): https://dadata.ru/api/#secret
    'dadataApiKey' => 'ЗАМЕНИТЕ_НА_КЛЮЧ_DADATA',
    'dadataSecretApiKey' => 'ЗАМЕНИТЕ_НА_СЕКРЕТ_DADATA',

    'mainMenu' => [
        [
            'label' => '
                <span class="wb-icon"><svg xmlns="http://www.w3.org" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h6"/><path d="M8 11h8"/></svg></span>
                <span class="wb-text">Справочники</span>',
            'url' => ['#'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'Карточки WB', 'url' => ['/wb/cards']],
                ['label' => 'Товары', 'url' => ['/product/index']],
                ['label' => 'Составные товары', 'url' => ['/product-wb-card/index']],
            ],
        ],
        [
            'label' => '
                <span class="wb-icon"><svg xmlns="http://www.w3.org" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-4"/><path d="M8 18v-2"/><path d="M16 18v-6"/></svg></span>
                <span class="wb-text">Отчеты</span>',
            'url' => ['#'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'Воронка продаж', 'url' => ['/wb-get-sales-funnel/wbcard']],
                ['label' => 'Детализация', 'url' => ['/wb-detail-by-period/weekly-report-nmid']],
                ['label' => 'Реклама', 'url' => ['/wb-adv-report/']],
                ['label' => 'Возвраты', 'url' => ['/unclaimed-orders/']],
            ],
        ],
        [
            'label' => '
                <span class="wb-icon"><svg xmlns="http://www.w3.org" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg></span>
                <span class="wb-text">Данные</span>',
            'url' => ['#'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'Заказы', 'url' => ['/wb-order/index']],
                ['label' => 'Продажи', 'url' => ['/wb-sales/index']],
            ],
        ],
    ], // mainMenu

];
