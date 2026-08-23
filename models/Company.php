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
 */
class Company extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%companies}}';
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
