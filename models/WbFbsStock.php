<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Кэш остатков FBS: POST /api/v3/stocks/{warehouseId}
 *
 * @property int $company_id
 * @property int $warehouseId
 * @property string $sku
 * @property int $amount
 * @property int|null $nmID
 * @property int|null $chrtID
 */
class WbFbsStock extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wb_fbs_stock}}';
    }

    public static function primaryKey()
    {
        return ['company_id', 'warehouseId', 'sku'];
    }

    public function rules()
    {
        return [
            [['company_id', 'warehouseId', 'sku', 'amount'], 'required'],
            [['company_id', 'warehouseId', 'amount', 'nmID', 'chrtID'], 'integer'],
            [['sku'], 'string', 'max' => 50],
            [['company_id', 'warehouseId', 'sku'], 'unique', 'targetAttribute' => ['company_id', 'warehouseId', 'sku']],
        ];
    }

    public function getCardSize()
    {
        return $this->hasOne(WbCardSize::class, ['sku' => 'sku']);
    }

    public function getWarehouse()
    {
        return $this->hasOne(WbFbsWarehouse::class, ['company_id' => 'company_id', 'warehouseId' => 'warehouseId']);
    }
}
