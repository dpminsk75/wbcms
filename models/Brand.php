<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Бренд.
 *
 * @property int $id
 * @property string $name
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property Product[] $products
 */
class Brand extends ActiveRecord
{
    /**
     * Флаг удаления в табличной форме.
     * Не сохраняется в БД.
     *
     * @var bool
     */
    public $_delete = false;

    public static function tableName()
    {
        return '{{%brand}}';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['_delete'], 'boolean'],
            [['_delete'], 'default', 'value' => false],
        ];
    }

    public function getProducts()
    {
        return $this->hasMany(Product::class, ['brand_id' => 'id']);
    }
}

