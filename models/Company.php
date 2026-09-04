<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string|null $abbreviation
 * @property string|null $inn
 * @property string|null $api_key
 * @property bool $is_active
 * @property string $created_at
 * @property string $updated_at
 * @property int $fbs_deduct_enabled
 * @property int $fbs_deduct_test
 * @property string|null $seo_model
 * @property int|null $seo_daily_limit
 * @property int|null $seo_desc_min
 * @property int|null $seo_desc_max
 * @property int|null $seo_anti_spam_days
 * @property string|null $seo_openrouter_key
 * @property string|null $seo_openrouter_referer
 * @property string|null $seo_openrouter_title
 * @property string|null $seo_prompt
 */
class Company extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%companies}}';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name', 'abbreviation'], 'string', 'max' => 255],
            [['abbreviation'], 'string', 'max' => 50],
            [['inn'], 'string', 'max' => 12],
            [['inn'], 'match', 'pattern' => '/^\d{10,12}$/', 'message' => 'ИНН 10 или 12 цифр', 'skipOnEmpty' => true],
            [['api_key'], 'string'],
            [['seo_model'], 'string', 'max'=>120],
            [['seo_openrouter_key'], 'string'],
            [['seo_openrouter_referer','seo_openrouter_title'], 'string', 'max'=>255],
            [['seo_prompt'], 'string'],
            [['seo_daily_limit','seo_desc_min','seo_desc_max','seo_anti_spam_days'], 'integer', 'min'=>1, 'max'=>5000],
            [['seo_daily_limit','seo_desc_min','seo_desc_max','seo_anti_spam_days'], 'default', 'value'=>null],
            [['is_active', 'fbs_deduct_enabled', 'fbs_deduct_test'], 'boolean'],
            [['is_active', 'fbs_deduct_enabled', 'fbs_deduct_test'], 'default', 'value' => 1],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Название',
            'abbreviation' => 'Аббревиатура',
            'inn' => 'ИНН',
            'api_key' => 'API ключ WB',
            'is_active' => 'Активна',
            'fbs_deduct_enabled' => 'Списание FBS',
            'fbs_deduct_test' => 'Тестовый режим FBS',
            'seo_model' => 'SEO модель (OpenRouter)',
            'seo_daily_limit' => 'SEO лимит/день',
            'seo_desc_min' => 'SEO описание мин',
            'seo_desc_max' => 'SEO описание макс',
            'seo_anti_spam_days' => 'SEO анти-спам дней',
            'seo_openrouter_key' => 'OpenRouter API key',
            'seo_openrouter_referer' => 'OpenRouter Referer',
            'seo_openrouter_title' => 'OpenRouter Title',
            'seo_prompt' => 'SEO промпт (system)',
            'created_at' => 'Создана',
            'updated_at' => 'Обновлена',
        ];
    }

    /**
     * Карта id => abbreviation (или name, если abbreviation не заполнен) для всех активных компаний.
     * Удобно для отображения короткого лейбла компании в гридах при режиме "Все компании".
     *
     * @return array<int, string>
     */
    public static function abbreviationMap(): array
    {
        $rows = static::find()->select(['id', 'abbreviation', 'name'])->asArray()->all();
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row['abbreviation'] !== null && $row['abbreviation'] !== ''
                ? $row['abbreviation']
                : $row['name'];
        }
        return $map;
    }
}
