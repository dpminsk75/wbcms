<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * Строка документа
 * @property int $id
 * @property int $doc_id
 * @property string $sku
 * @property int|null $nmID
 * @property int|null $chrtID
 * @property int $qty
 * @property int|null $qty_before
 * @property int|null $qty_fact
 * @property float|null $price
 */
class WbDocItem extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wb_doc_item}}';
    }

    public function rules()
    {
        return [
            [['doc_id','sku','qty'],'required'],
            [['doc_id','nmID','chrtID','qty','qty_before','qty_fact'],'integer'],
            [['price'],'number'],
            [['sku'],'string','max'=>50],
        ];
    }
}
