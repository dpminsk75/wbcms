<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * Журнал проводок: wb_stock_ledger (immutable)
 * @property int $id
 * @property int $company_id
 * @property string $doc_type
 * @property int|null $doc_id
 * @property int $warehouseId
 * @property string $sku
 * @property int $qty_delta
 * @property int $qty_before
 * @property int $qty_after
 * @property int|null $user_id
 * @property string $created_at
 */
class WbStockLedger extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wb_stock_ledger}}';
    }

    public function rules()
    {
        return [
            [['company_id','doc_type','warehouseId','sku','qty_delta','qty_before','qty_after'],'required'],
            [['company_id','warehouseId','qty_delta','qty_before','qty_after','user_id','doc_id'],'integer'],
            [['doc_type'],'string','max'=>30],
            [['sku'],'string','max'=>50],
        ];
    }
}
