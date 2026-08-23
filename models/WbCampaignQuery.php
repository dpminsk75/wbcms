<?php
namespace app\models;

use yii\db\ActiveRecord;

class WbCampaignQuery extends ActiveRecord
{
    use CompanyScopedTrait;
    public static function tableName()
    {
        return '{{%wb_campaign_query}}';
    }

    public function rules()
    {
        return [
            [['campaign_id', 'nm_id', 'date', 'query'], 'required'],
            [['campaign_id', 'nm_id', 'views', 'clicks', 'atbs', 'orders', 'shks'], 'integer'],
            [['ctr', 'sum'], 'number'],
            [['query'], 'string', 'max' => 255],
        ];
    }
}