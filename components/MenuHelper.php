<?php
namespace app\components;

use Yii;
use yii\bootstrap5\Html;

class MenuHelper 
{
    private static function getIcon($name) 
    {
        $icons = [
//            'home'    => '<svg ...>...</svg>', // SVG для Главной
            'book'    => '<svg xmlns="http://www.w3.org" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M8 7h6"/><path d="M8 11h8"/></svg>',

            'by_search'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
            'by_phrases' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>',

            'reports' => '<svg xmlns="http://www.w3.org" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-4"/><path d="M8 18v-2"/><path d="M16 18v-6"/></svg>',
            'tag'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="m7 7-.01.01"/></svg>',
            'product' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
            'data'    => '<svg xmlns="http://www.w3.org" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>',
//	        'logout'  => '<svg xmlns="http://www.w3.org" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4")/>><polyline points="16 17 21 12 16 7")/>><line x1="21" y1="12" x2="9" y2="12")/></svg>',
//	        'user'    => '<svg xmlns="http://www.w3.org" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2")/>><circle cx="12" cy="7" r="4")/></svg>',

            'reviews' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'chat' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2z"/><path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"/></svg>',
            'feedback' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h.01"/><path d="M16 10h.01"/><path d="M8 14c1.5 2 4.5 2 6 0"/></svg>',

            'company' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'global' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',

            'logout'     => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
            'user'       => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'warehouse'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
            'gear'       => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 9 15a1.65 1.65 0 0 0-1-1.51V13a1.65 1.65 0 0 0 1-1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 15 15a1.65 1.65 0 0 0 1 1.51V17a2 2 0 0 1 4 0v-.09a1.65 1.65 0 0 0 1-1.51Z"/></svg>',
        ];
        return $icons[$name] ?? ''; 
    }
    
    public static function getMenuItems($type = 'top') 
    {
        $menuData = [
/*
            [
                 'label' => 'Главная', 
                 'url' => ['/site/index'], 
//                 'icon' => 'home',
                 'visibleIn' => ['top'],
            ],
*/
        [
            'label' => 'Отчеты',
            'icon' => 'reports',
            'visible' => Yii::$app->user->can('viewReports') || Yii::$app->user->can('admin'),
            'visibleIn' => ['top', 'side'],
            'url' => ['#'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'ТОП продаж', 'url' => ['/wb-sales-analysis/']],
                ['label' => 'Карточка WB', 'url' => ['/wb/detail/']],
                ['label' => 'Заказы', 'url' => ['/wb-order/feed-aggregated']],
                ['label' => 'Тепловая карта', 'url' => ['/wb-order/heatmap']],

                ['label' => '', 'url' => '#', 'divider' => true, 'visibleIn' => ['top', 'side']],
                ['label' => 'Реклама', 'url' => ['/wb-adv-report/']],
                ['label' => 'По ГЕО', 'url' => ['/geo-map-report/index?nm_id=526443466']],
                ['label' => 'Возвраты', 'url' => ['/unclaimed-orders/']],
                ['label' => 'Воронка продаж', 'url' => ['/wb-get-sales-funnel/wbcard/']],
//               ['label' => '', 'url' => '#', 'divider' => true, 'visibleIn' => ['top', 'side']],
                ['label' => '', 'url' => '#', 'divider' => true, 'visibleIn' => ['top', 'side']],
                ['label' => 'ТОП товары', 'url' => ['/wb-profit/top-products']],
                ['label' => 'Маржа', 'url' => ['/wb-profit']],

                ['label' => '', 'url' => '#', 'divider' => true, 'visibleIn' => ['top', 'side']],
                
                ['label' => 'Товары по складам', 'url' => ['/wb-stock/top-warehouse-report']],
                ['label' => 'Критичные остатки', 'url' => ['/wb-stock/warehouse-analytics']],
                ['label' => 'Оборачиваемость', 'url' => ['/wb-stock/analytics']],
                ['label' => 'Детализация', 'url' => ['/wb-detail-by-period/weekly-report-nmid']],
            ],
        ],
        [
            'label' => 'По фразам',
            'icon' => 'by_search',
            'visible' => Yii::$app->user->can('viewReports') || Yii::$app->user->can('admin'),
            'visibleIn' => ['top', 'side'],
            'url' => ['#'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'Карточка -> фразы', 'url' => ['/wb-search/card']],
                ['label' => 'Фраза -> карточки',  'url' => ['/wb-search/phrase']],
                ['label' => 'Анализ фраз',        'url' => ['/wb-search/trend']],
            ],
        ],

        [
            'label' => 'По тегам',
            'icon' => 'tag',
            'visible' => Yii::$app->user->can('viewReports') || Yii::$app->user->can('admin'),
            'visibleIn' => ['top', 'side'],
            'url' => ['#'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'Заказы', 'url' => ['/tag/view']],

                ['label' => '', 'url' => '#', 'divider' => true, 'visibleIn' => ['top', 'side']],
                ['label' => 'Список тегов', 'url' => ['/tag/index']],
            ],
        ],
        [
            'label' => 'По товарам',
            'icon' => 'product',
            'visible' => Yii::$app->user->can('viewReports') || Yii::$app->user->can('admin'),
            'visibleIn' => ['top', 'side'],
            'url' => ['#'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'Детализация', 'url' => ['/wb-detail-by-period/weekly-report']],
            ],
        ],

        [
            'label' => 'Отзывы',
            'icon' => 'chat',
            'visible' => Yii::$app->user->can('viewReports') || Yii::$app->user->can('admin'),
            'visibleIn' => ['top', 'side'],
            'url' => ['#'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'Отзывы и ответы', 'url' => ['/wb-feedback-answers/index']],

                ['label' => '', 'url' => '#', 'divider' => true, 'visibleIn' => ['top', 'side']],
                ['label' => 'Правила', 'url' => ['/wb-reply-rules/index']],
                ['label' => 'Тэги',    'url' => ['/wb-feedback-tags/index']],

            ],
        ],    


        
        [
            'label' => 'Данные',
            'icon' => 'data',
            'visible' => Yii::$app->user->can('viewOrders') || Yii::$app->user->can('admin'),
            'visibleIn' => ['top', 'side'],
            'url' => ['#'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'Лента заказов', 'url' => ['/wb-order/feed']],
                ['label' => '', 'url' => '#', 'divider' => true, 'visibleIn' => ['top', 'side']],
                ['label' => 'Заказы', 'url' => ['/wb-order/index']],
                ['label' => 'Продажи', 'url' => ['/wb-sales/index']],
                ['label' => '', 'url' => '#', 'divider' => true, 'visibleIn' => ['top', 'side']],
                ['label' => 'Себестоимость', 'url' => ['/cost-import/list']],
            ],
        ],    
        [
            'label' => 'Склад',
            'icon' => 'warehouse',
            'visible' => Yii::$app->user->can('manageFbsStocks') || Yii::$app->user->can('admin'),
            'visibleIn' => ['top', 'side'],
            'url' => ['#'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'Управление остатками', 'url' => ['/wb-fbs-virtual/index'], 'visible' => Yii::$app->user->can('manageFbsStocks') || Yii::$app->user->can('admin')],
            ],
        ],

            [
                'label' => 'Справочники',
                'icon' => 'book',
                'visible' => Yii::$app->user->can('viewReports') || Yii::$app->user->can('admin'),
                'visibleIn' => ['top', 'side'],
                'url' => ['#'],
                'options' => ['class' => 'wb-menu__item'],
                'items' => [
                    ['label' => 'Карточки WB',      'url' => ['/wb/cards']],
                    ['label' => 'Теги',             'url' => ['/tag/index']],
                    ['label' => 'Товары',           'url' => ['/product/index']],
                    ['label' => 'Составные товары', 'url' => ['/product-wb-card/index']],
                ],
            ],

        [
            'label' => 'Админка',
            'icon' => 'gear',
            'url' => ['#'],
            'visible' => Yii::$app->user->can('admin') || Yii::$app->user->can('manageUsers'),
            'visibleIn' => ['top', 'side'],
            'options' => ['class' => 'wb-menu__item'],
            'items' => [
                ['label' => 'Пользователи', 'url' => ['/user/admin/index'], 'visible' => Yii::$app->user->can('manageUsers')],
                ['label' => 'Роли', 'url' => ['/admin/assignment'], 'visible' => Yii::$app->user->can('admin')],
                ['label' => 'Компании', 'url' => ['/company/index'], 'visible' => Yii::$app->user->can('manageCompanies')],
                ['label' => '', 'url' => '#', 'divider' => true, 'visibleIn' => ['top', 'side']],
                ['label' => 'Настройки профиля', 'url' => ['/user/profile']],
            ],
        ],    

     ];


if (!Yii::$app->user->isGuest) {
    $isTop = ($type === 'top');
    $userName = Yii::$app->user->identity->username;
    $csrfToken = Yii::$app->request->getCsrfToken();
    $logoutUrl = \yii\helpers\Url::to(['/site/logout']);

    if ($isTop) {
        // ДЛЯ ШАПКИ (TOP)
/*
        $menuData[] = [
            'label' => '
                <form action="' . $logoutUrl . '" method="post" class="wb-logout-form">
                    <input type="hidden" name="' . Yii::$app->request->csrfParam . '" value="' . $csrfToken . '">
                    <button type="submit" class="wb-logout-btn">
                        <span class="wb-icon">' . self::getIcon('logout') . '</span>
                        <span class="wb-text">Выйти (' . $userName . ')</span>
                    </button>
                </form>',

            'url' => null, // ВАЖНО: null отключает генерацию тега <a> виджетом
            'visibleIn' => ['top'],
            'encode' => false, // Чтобы HTML формы не превратился в текст
            'options' => ['class' => 'navbar__logout'],
        ];
*/

        $companyData = null;
        $companyManager = Yii::$app->companyManager;

        $userCompanies = (new \yii\db\Query())
            ->select(['id', 'name'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all();

        if (!empty($userCompanies)) {
            if (!$companyManager->hasSelection()) {
                $companyManager->setCurrentId($userCompanies[0]['id']);
            }

            $currentCompanyId = $companyManager->getCurrentId();
            $isGlobalMode = $companyManager->isGlobalMode();
            $headerLabel = $isGlobalMode
                ? 'Все компании'
                : 'Выберите кабинет';

            $companySubItems = [];
            $companySubItems[] = [
                'label' => '<span>Все компании</span>',
                'url' => \yii\helpers\Url::to(['/site/select-all-companies']),
                'active' => $isGlobalMode,
                'encode' => false,
            ];

            $companySubItems[] = [
                'label' => '',
                'options' => ['class' => 'dropdown-divider'],
            ];

            foreach ($userCompanies as $comp) {
                if (!$isGlobalMode && $comp['id'] == $currentCompanyId) {
                    $headerLabel = $comp['name'];
                }
                $companySubItems[] = [
                    'label' => $comp['name'],
                    'url' => \yii\helpers\Url::to(['/site/select-company', 'id' => $comp['id']]),
                    'active' => (!$isGlobalMode && $comp['id'] == $currentCompanyId),
                ];
            }

            $companyData = [
                'label' => '<span class="wb-icon">' . self::getIcon('company') . '</span><span class="wb-text">' . Html::encode($headerLabel) . '</span>',
                'items' => $companySubItems,
                'visibleIn' => ['top'],
                'options' => ['class' => 'wb-menu__item dropdown nav-item navbar__company-selector  ms-auto'],
                'skip_icon_map' => true,
            ];
        }

        // 2. Создаем массив для кнопки выхода
        $logoutData = [
            'label' => '
                <form action="' . $logoutUrl . '" method="post" class="wb-logout-form">
                    <input type="hidden" name="' . Yii::$app->request->csrfParam . '" value="' . $csrfToken . '">
                    <button type="submit" class="wb-logout-btn">
                        <span class="wb-icon">' . self::getIcon('logout') . '</span>
                        <span class="wb-text">Выйти (' . $userName . ')</span>
                    </button>
                </form>',
            'url' => null,
            'visibleIn' => ['top'],
            'encode' => false,
            'options' => ['class' => 'navbar__logout'],
        ];

        // 3. Добавляем их в конец меню В ЭТОМ ПОРЯДКЕ
        if ($companyData) {
            $menuData[] = $companyData;
        }
        $menuData[] = $logoutData;


    } else {
        // ДЛЯ САЙДБАРА (SIDE)
        // Чтобы избежать 405, используем такой же трюк с формой или кнопкой
        $menuData[] = [
            'label' => '
                <form action="' . $logoutUrl . '" method="post" style="display:block;">
                    <input type="hidden" name="' . Yii::$app->request->csrfParam . '" value="' . $csrfToken . '">
                    <button type="submit" class="border-0 bg-transparent p-0 btn_logout" style="font-size:14px;">
                        Выйти (' . $userName . ')
                    </button>
                </form>',
            'url' => null,
            'visibleIn' => ['side'],
            'encode' => false,
            'options' => ['style' => 'padding: 0px 15px;']
        ];
    }
}



/*
    if (Yii::$app->user->isGuest) {
        $menuData[] = [
            'label' => 'Войти', 
            'url' => ['/site/login'], 
            'icon' => 'user', 
            'visibleIn' => ['top', 'side'],
            'options' => ['class' => 'navbar__login']
        ];
    } else {
        // Кнопка ВЫХОД (с формой для безопасности)
        $menuData[] = [
            'label' => 'Выйти (' . Yii::$app->user->identity->username . ')',
            'url' => ['/site/logout'],
            'visibleIn' => ['top'],
            'options' => ['class' => 'navbar__logout'],
            'icon' => 'logout',
            // Шаблон для вывода кнопки как формы (важно для защиты от CSRF)
            'template' => ($type === 'top') 
	            ? Html::beginForm(['/site/logout'], 'post', ['id' => 'logout-form-top']) 
	              . Html::submitButton('{label}', ['class' => 'wb-logout-btn'])
	              . Html::endForm()
	            // ВАЖНО: data-method="post" для обычной ссылки <a>
	            : '<a href="{url}" data-method="post" class="text-danger">{label}</a>',

        ];
    }
*/

/*
        if (!$withIcons) {
            return $menuData;
        }

        // Добавляем HTML-обертку с иконками для каждого верхнего уровня
        return array_map(function($item) {
            $iconHtml = self::getIcon($item['icon'] ?? '');
            $item['label'] = '<span class="wb-icon">' . $iconHtml . '</span><span class="wb-text">' . $item['label'] . '</span>';
            return $item;
        }, $menuData);
*/

	    // Фильтруем массив: оставляем только те пункты, где в visibleIn есть наш тип
/*
	    $filteredMenu = array_filter($menuData, function($item) use ($type) {
	        return isset($item['visibleIn']) && in_array($type, $item['visibleIn']);
	    });

	    if ($type === 'side') {
	        return $filteredMenu; // Возвращаем чистый список для сайдбара
	    }

	    // Для 'top' добавляем иконки и HTML-обертку
	    return array_map(function($item) {
	        $iconHtml = self::getIcon($item['icon'] ?? '');
	        $item['label'] = '<span class="wb-icon">' . $iconHtml . '</span><span class="wb-text">' . $item['label'] . '</span>';
	        return $item;
	    }, $filteredMenu);
*/

// 1. Обрабатываем вложенные пункты во всех разделах
    foreach ($menuData as &$section) {
        if (isset($section['items']) && is_array($section['items'])) {
            foreach ($section['items'] as &$subItem) {
                if (isset($subItem['divider']) && $subItem['divider'] === true) {
                    if ($type === 'top') {
                        // Верхнее меню: используем стандартный класс Bootstrap 5 для линии
                        $subItem['options'] = ['class' => 'dropdown-divider'];
                        $subItem['label'] = '';
                    } else {
                        // Боковое меню: рисуем небольшую серую линию с отступами
                        // margin: 8px 15px — отступы сверху/снизу и по бокам
                        // border-top: 1px solid #e0e0e0 — сама серая полосочка
//                        $subItem['template'] = '<div style="margin: 5px; border-top: 1px solid #e0e0e0;"></div>';
                        $subItem['template'] = '<div style="width: 70px; margin: 5px 0px; border-top: 1px solid #e0e0e0;"></div>';
                        $subItem['label'] = '';
                        $subItem['url'] = null;

                    }
                }
            }
        }
    }

    // Далее идет твоя фильтрация...
    $filteredMenu = array_filter($menuData, function($item) use ($type) {
        return isset($item['visibleIn']) && in_array($type, $item['visibleIn']);
    });

    // 3. Твой оригинальный возврат для side
    if ($type === 'side') {
        return $filteredMenu;
    }
/*
    // 4. Твой оригинальный возврат для top с иконками
    return array_map(function($item) {
        $iconHtml = self::getIcon($item['icon'] ?? '');
        $item['label'] = '<span class="wb-icon">' . $iconHtml . '</span><span class="wb-text">' . $item['label'] . '</span>';
        return $item;
    }, $filteredMenu);
*/
// 4. Твой оригинальный возврат для top с иконками (с защитой от двойной обертки)
    return array_map(function($item) {
        // Если это наш селектор компаний — возвращаем как есть, не ломая верстку
        if (isset($item['skip_icon_map']) && $item['skip_icon_map'] === true) {
            unset($item['skip_icon_map']); // удаляем временный флаг
            return $item;
        }

        // Для всех остальных пунктов меню оставляем твою стандартную логику
        $iconHtml = self::getIcon($item['icon'] ?? '');
        $item['label'] = '<span class="wb-icon">' . $iconHtml . '</span><span class="wb-text">' . $item['label'] . '</span>';
        return $item;
    }, $filteredMenu);


    }
}