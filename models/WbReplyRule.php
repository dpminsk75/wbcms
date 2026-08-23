<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Модель для таблицы "wb_reply_rules"
 *
 * @property int $id
 * @property string $title
 * @property int $is_active
 * @property string $rule_type
 * @property int $rating_min
 * @property int $rating_max
 * @property string $text_condition
 * @property string $part_separator
 * @property int $created_at
 * @property int $updated_at
 *
 * @property WbReplyTemplatePart[] $templateParts
 */
class WbReplyRule extends ActiveRecord
{
    public static function tableName()
    {
        return 'wb_reply_rules';
    }

    public function behaviors()
    {
        return [
            // Автоматическое заполнение created_at и updated_at таймстемпом
            TimestampBehavior::class,
        ];
    }

    public function rules()
    {
        return [
            [['title'], 'required'],
            [['is_active', 'rating_min', 'rating_max', 'created_at', 'updated_at'], 'integer'],
            [['rule_type', 'text_condition', 'part_separator'], 'string'],
            [['title'], 'string', 'max' => 255],
            [['rating_min', 'rating_max'], 'default', 'value' => 5],
            [['is_active'], 'default', 'value' => 1],
            [['rule_type'], 'default', 'value' => 'general'],
            [['text_condition'], 'default', 'value' => 'any'],
            [['part_separator'], 'default', 'value' => 'newline'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Название правила',
            'is_active' => 'Активно',
            'rule_type' => 'Тип правила',
            'rating_min' => 'Мин. рейтинг',
            'rating_max' => 'Макс. рейтинг',
            'text_condition' => 'Содержимое отзыва',
            'part_separator' => 'Разделитель частей ответа',
            'created_at' => 'Дата создания',
            'updated_at' => 'Дата изменения',
        ];
    }

    /**
     * Связь с частями шаблона текста
     */
    public function getTemplateParts()
    {
        return $this->hasMany(WbReplyTemplatePart::class, ['rule_id' => 'id']);
    }

/**
     * Связь с выбранными брендами
     */
    public function getBrands()
    {
        return $this->hasMany(WbReplyTemplatePart::class, ['rule_id' => 'id']) // Временный костыль, ниже чистый Query, ActiveRecord ManyMany:
            ->from('wb_reply_rule_brands'); 
    }

    /**
     * Прямое получение списков для GridView без усложнения связей
     */
    public function getSelectedBrandsTitles()
    {
        return (new \yii\db\Query())
            ->select('brand_name')
            ->from('wb_reply_rule_brands')
            ->where(['rule_id' => $this->id])
            ->column();
    }

    public function getSelectedProductsTitles()
    {
        return (new \yii\db\Query())
            ->select(['c.title', 'p.nmID'])
            ->from(['p' => 'wb_reply_rule_products'])
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = p.nmID')
            ->where(['p.rule_id' => $this->id])
            ->all();
    }
}