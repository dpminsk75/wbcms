<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * Шапка документа склада
 * @property int $id
 * @property int $company_id
 * @property string $type RECEIPT/EXPENSE/TRANSFER
 * @property string $status draft/posted/canceled
 * @property int $warehouseId
 * @property int|null $to_warehouseId
 * @property string $date
 * @property string|null $comment
 * @property int|null $user_id
 */
class WbDoc extends ActiveRecord
{
    const TYPE_RECEIPT = 'RECEIPT';
    const TYPE_EXPENSE = 'EXPENSE';
    const TYPE_TRANSFER = 'TRANSFER';
    const TYPE_INVENTORY = 'INVENTORY';
    const TYPE_ADJUSTMENT = 'ADJUSTMENT';
    const STATUS_DRAFT = 'draft';
    const STATUS_POSTED = 'posted';
    const STATUS_CANCELED = 'canceled';

    public static function tableName()
    {
        return '{{%wb_doc}}';
    }

    public function rules()
    {
        return [
            [['company_id','type','warehouseId'],'required'],
            [['company_id','warehouseId','to_warehouseId','user_id'],'integer'],
            [['type','status','comment'],'string'],
            [['date'],'safe'],
            [['type'],'in','range'=>[self::TYPE_RECEIPT,self::TYPE_EXPENSE,self::TYPE_TRANSFER,self::TYPE_INVENTORY,self::TYPE_ADJUSTMENT]],
            [['status'],'in','range'=>[self::STATUS_DRAFT,self::STATUS_POSTED,self::STATUS_CANCELED]],
            [['to_warehouseId'],'required','when'=>fn($m)=>$m->type===self::TYPE_TRANSFER,'whenClient'=>"function(a,v){return $('#wbdoc-type').val()==='TRANSFER';}",'message'=>'Укажите склад "куда"'],
            [['to_warehouseId'],'compare','compareAttribute'=>'warehouseId','operator'=>'!=','message'=>'Склады "откуда" и "куда" должны отличаться','when'=>fn($m)=>$m->type===self::TYPE_TRANSFER],
            [['comment'],'required','when'=>fn($m)=>$m->type===self::TYPE_ADJUSTMENT,'message'=>'Укажите причину корректировки'],
        ];
    }

    public function getItems()
    {
        return $this->hasMany(WbDocItem::class, ['doc_id'=>'id']);
    }

    public function getWarehouse()
    {
        return $this->hasOne(WbFbsWarehouse::class, ['warehouseId'=>'warehouseId','company_id'=>'company_id']);
    }
}
