<?php
namespace app\components;

use Yii;
use yii\helpers\Html;

/**
 * Быстрые ссылки-кнопки на карточки WB (видны только пользователю admin).
 * Список кнопок редактируется в одном месте — self::$buttons.
 */
/*

Книги / журналы

fa-book — закрытая книга
fa-book-open — открытая книга
fa-bookmark — закладка
fa-newspaper — газета/журнал (хорошо подходит именно для "журнала")
Рукоделие / выкройки

fa-scroll — свиток (похоже на выкройку/схему)
fa-ruler-combined / fa-ruler — линейка (расчёты, схемы)
fa-pencil-ruler — карандаш + линейка (черчение схемы)
fa-cut — ножницы (рукоделие в целом)
Готовые изделия (тематично к вязанию)

fa-mitten — варежка
fa-socks — носки
fa-hat-wizard — шапка (условно, не идеально)
Нейтральные, но тематически нормальные

fa-palette — палитра (для узоров/цветов)
fa-th / fa-th-large — сетка (похоже на схему вязания крестиком/спицами)
fa-layer-group — слои (для многосерийных выпусков журнала)


//    $myButtons[] = Html::a('<i class="fas fa-calendar-alt"></i> Календарь', ['/wb/detail', 'DPFilterForm' => ['nm_id' => 135462932]], ['class' => 'btn btn-panel']);

*/

class AdminQuickButtons
{
    /**
     * @var array Список кнопок: icon (класс FontAwesome), label (подпись), nm_id (артикул WB)
     */
    public static $buttons = [
        ['icon' => 'fas fa-sync-alt', 'label' => 'Дневник',       'nm_id' => 526443466],
        ['icon' => 'fas fa-th',       'label' => 'Амигуруми',     'nm_id' => 210001374],
        ['icon' => 'fas fa-palette',  'label' => 'Амигуруми ч.2', 'nm_id' => 534186046],
        ['icon' => 'fas fa-cut',      'label' => 'Бум. лоза',     'nm_id' => 264750923],
    ];

    /**
     * Возвращает массив готовых Html::a(...) ссылок.
     * Для не-admin пользователей возвращает пустой массив.
     *
     * @return string[]
     */
    public static function getButtons(): array
    {
        if (Yii::$app->user->isGuest || Yii::$app->user->identity->username !== 'admin') {
            return [];
        }

        $result = [];
        foreach (self::$buttons as $btn) {
            $result[] = Html::a(
                '<i class="' . $btn['icon'] . '"></i> ' . Html::encode($btn['label']),
                ['/wb/detail', 'DPFilterForm' => ['nm_id' => $btn['nm_id']]],
                ['class' => 'btn btn-panel']
            );
        }

        return $result;
    }
}