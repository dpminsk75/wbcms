<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Кэш складов продавца FBS: GET /api/v3/warehouses
 *
 * @property int $id
 * @property int $company_id
 * @property int $warehouseId
 * @property string $name
 * @property string|null $address
 * @property int|null $officeId
 * @property int $isActive
 * @property int $is_virtual
 * @property int $is_deleting
 * @property int $is_processing
 * @property int $consider_orders
 * @property string|null $raw_json
 */
class WbFbsWarehouse extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wb_fbs_warehouse}}';
    }

    public function rules()
    {
        return [
            [['company_id', 'warehouseId', 'name'], 'required'],
            [['company_id', 'warehouseId', 'officeId', 'isActive', 'is_virtual', 'is_deleting', 'is_processing', 'consider_orders'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['address'], 'string', 'max' => 500],
            [['raw_json'], 'safe'],
            [['company_id', 'warehouseId'], 'unique', 'targetAttribute' => ['company_id', 'warehouseId']],
        ];
    }
}
