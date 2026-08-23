<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $tag_id
 * @property int $nmID
 */
class TagCardLink extends ActiveRecord
{
    public static function tableName()
    {
        return 'tag_card_links';
    }

    public function rules()
    {
        return [
            [['tag_id', 'nmID'], 'required'],
            [['tag_id', 'nmID'], 'integer'],
            [['tag_id', 'nmID'], 'unique', 'targetAttribute' => ['tag_id', 'nmID']],
        ];
    }

    /**
     * Связь с самим тегом
     */
    public function getTag()
    {
        return $this->hasOne(Tag::class, ['id' => 'tag_id']);
    }

    /**
     * Связь с карточкой WB (используя ваше правило nmID)
     */
    public function getWbCard()
    {
        // Используем таблицу wbcards и поле nmID согласно инструкциям
        return $this->hasOne(WbCards::class, ['nmID' => 'nmID']);
    }
}