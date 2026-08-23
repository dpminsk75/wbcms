<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Товар.
 *
 * @property int $id
 * @property string $name
 * @property int $product_type_id
 * @property int $brand_id
 * @property float|null $cost
 * @property float|null $weight
 * @property string|null $vat_rate
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property ProductType $productType
 * @property Brand $brand
 * @property ProductWbCard[] $productWbCards
 * @property WbCard[] $wbCards
 */
class Product extends ActiveRecord
{
    /**
     * Выбранные nmID карточек WB при вводе формы.
     *
     * @var array
     */
    public $wbCardIds = [];
    public static function tableName()
    {
        return '{{%product}}';
    }

    public function rules()
    {
        return [
            [['name', 'product_type_id', 'brand_id'], 'required'],
            [['product_type_id', 'brand_id'], 'integer'],
            [['cost'], 'number'],
            [['weight'], 'number'],
            [['name'], 'string', 'max' => 255],
            [['vat_rate'], 'string', 'max' => 16],
            [['wbCardIds'], 'each', 'rule' => ['integer']],
        ];
    }

    public function getProductType()
    {
        return $this->hasOne(ProductType::class, ['id' => 'product_type_id']);
    }

    public function getBrand()
    {
        return $this->hasOne(Brand::class, ['id' => 'brand_id']);
    }

    public function getProductWbCards()
    {
        return $this->hasMany(ProductWbCard::class, ['product_id' => 'id']);
    }

    /**
     * Карточки WB, связанные через промежуточную таблицу.
     *
     * @return \yii\db\ActiveQuery
     */
    public function getWbCards()
    {
        return $this->hasMany(WbCard::class, ['nmID' => 'wb_nm_id'])
            ->via('productWbCards');
    }
}

