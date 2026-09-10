<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * Единый остаток по складу: wb_stock_balance
 * @property int $company_id
 * @property int $warehouseId
 * @property string $sku
 * @property int|null $nmID
 * @property int|null $chrtID
 * @property int $quantity
 */
class WbStockBalance extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wb_stock_balance}}';
    }

    public function rules()
    {
        return [
            [['company_id','warehouseId','sku','quantity'],'required'],
            [['company_id','warehouseId','nmID','chrtID','quantity'],'integer'],
            [['sku'],'string','max'=>50],
            [['company_id','warehouseId','sku'],'unique','targetAttribute'=>['company_id','warehouseId','sku']],
        ];
    }
}
