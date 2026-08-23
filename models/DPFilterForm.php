<?php

namespace app\models;

use yii\base\Model;

class DPFilterForm extends Model
{
    // 1. Объявляем все возможные поля фильтра как публичные свойства
    public $nmID;        // Для карточек
    public $nm_id;        // Для карточек
    public $campaign_id; // Для рекламных кампаний

    public $phrase;      // Для поисковых фраз
    public $phrase_id;
    public $phrase_text;

    public $tag;         // Для тегов
    
    public $date_from;   // Для даты начала
    public $date_to;     // Для даты конца

    /**
     * 2. Правила валидации
     */
    public function rules()
    {
        return [
            // Разрешаем загружать все эти поля из формы
        [['nmID', 'nm_id', 'phrase', 'phrase_id', 'phrase_text', 'campaign_id', 'date_from', 'date_to'], 'safe'],        ];
    }

    /**
     * Можно также добавить понятные названия для меток (Labels)
     */
    public function attributeLabels()
    {
        return [
            'nmID' => 'Карточка WB',
            'nm_id' => 'Карточка WB',
            'phrase' => 'Поисковая фраза',

            'date_from' => 'Дата с',
            'date_to' => 'Дата по',
        ];
    }
}