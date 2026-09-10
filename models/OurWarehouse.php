<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * Реальный склад компании
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $code
 * @property int $is_central
 * @property int $is_fbs
 * @property int $is_active
 * @property string|null $address
 */
class OurWarehouse extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%our_warehouse}}';
    }

    public function rules()
    {
        return [
            [['company_id','name'],'required'],
            [['company_id','is_central','is_fbs','is_active'],'integer'],
            [['name'],'string','max'=>255],
            [['code'],'string','max'=>50],
            [['address'],'string','max'=>500],
            [['is_central','is_fbs'],'boolean'],
        ];
    }

    public function getIsCentralLabel()
    {
        return $this->is_central ? 'Да' : 'Нет';
    }
}
