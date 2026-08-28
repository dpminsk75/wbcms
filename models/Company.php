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
