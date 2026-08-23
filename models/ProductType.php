<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Тип продукта.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property Product[] $products
 */
class ProductType extends ActiveRecord
{
    /**
     * Флаг удаления в табличной форме (не хранится в БД).
     *
     * @var bool
     */
    public $_delete = false;

    public static function tableName()
    {
        return '{{%product_type}}';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['description'], 'string'],
            [['name'], 'string', 'max' => 255],
            [['_delete'], 'boolean'],
            [['_delete'], 'default', 'value' => false],
        ];
    }

    public function getProducts()
    {
        return $this->hasMany(Product::class, ['product_type_id' => 'id']);
    }
}

