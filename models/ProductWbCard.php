<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Связь Товар ↔ Карточка WB.
 *
 * @property int $id
 * @property int $product_id
 * @property int $wb_nm_id
 * @property int $type     // Тип записи (1 цифра)
 * @property int $q        // Количество
 * @property float $p      // Процент
 *
 * @property Product $product
 * @property WbCard $wbCard
 */
class ProductWbCard extends ActiveRecord
{
    // Константы типов (пример, можно изменить под ваши нужды)
    const TYPE_DEFAULT = 1;
    const TYPE_PROMO = 2;

    public static function tableName()
    {
        return '{{%product_wb_card}}';
    }

    public function rules()
    {
        return [
            [['product_id', 'wb_nm_id'], 'required'],
            [['product_id', 'wb_nm_id', 'q'], 'integer'],
            
            // Валидация для поля type (1 цифра: от 0 до 9)
            ['type', 'integer'],
            ['type', 'default', 'value' => self::TYPE_DEFAULT],
            ['type', 'in', 'range' => range(0, 9)],
            
            // Валидация для процента (float/number)
            ['p', 'number'],
            ['p', 'default', 'value' => 0],
            
            // Валидация для количества
            ['q', 'default', 'value' => 0],

            [['product_id', 'wb_nm_id'], 'unique', 'targetAttribute' => ['product_id', 'wb_nm_id']],
        ];
    }

    /**
     * Эти названия будут отображаться в формах и ошибках
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'product_id' => 'Товар',
            'wb_nm_id' => 'Карточка WB',
            'type' => 'Тип',
            'q' => 'Количество',
            'p' => 'Процент',
        ];
    }

    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getWbCard()
    {
        return $this->hasOne(WbCard::class, ['nmID' => 'wb_nm_id']);
    }
}