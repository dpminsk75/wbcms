<?php

namespace app\models;

/**
 * Трейт для реализации AJAX-поиска в моделях
 */
trait AjaxSearchTrait
{
    /**
     * Поиск для Select2
     * @param string $q Строка поиска
     * @param string $displayField Поле для отображения (title, phrase и т.д.)
     * @param string $keyField Первичный ключ (id или nmID)
     */
    public static function ajaxSearch($q, $displayField = 'title', $keyField = 'id')
    {
        return self::find()
            ->select(['id' => $keyField, 'text' => $displayField])
            ->where(['like', $displayField, $q])
            ->orWhere(['like', $keyField, $q])
            ->limit(20)
            ->asArray()
            ->all();
    }

    /**
     * Получение текста для инициализации (когда запись уже выбрана)
     */
    public static function getAjaxText($id, $displayField = 'title', $keyField = 'id')
    {
        $model = self::findOne([$keyField => $id]);
        return $model ? $model->$displayField : '';
    }
}