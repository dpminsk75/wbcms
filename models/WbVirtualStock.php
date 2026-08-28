<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Текущие виртуальные остатки - редактируются и выгружаются PUT на виртуальные склады.
 *
 * @property int $id
 * @property int $company_id
 * @property string $sku
 * @property int|null $nmID
 * @property int|null $chrtID
 * @property int $quantity
 */
class WbVirtualStock extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wb_virtual_stock}}';
    }

    public function rules()
    {
        return [
            [['company_id', 'sku', 'quantity'], 'required'],
            [['company_id', 'nmID', 'chrtID', 'quantity'], 'integer'],
            [['sku'], 'string', 'max' => 50],
            [['company_id', 'sku'], 'unique', 'targetAttribute' => ['company_id', 'sku']],
        ];
    }

    public function getCard()
    {
        return $this->hasOne(WbCard::class, ['nmID' => 'nmID']);
    }

    public function getSize()
    {
        return $this->hasOne(WbCardSize::class, ['sku' => 'sku']);
    }
}
