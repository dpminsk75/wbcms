<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $nm_id
 * @property string $name
 */
class WbCampaignItems extends ActiveRecord
{
    use CompanyScopedTrait;
    public static function tableName()
    {
        return '{{%wb_campaign_item}}';
    }

    public function rules()
    {
        return [
            [['campaign_id', 'nm_id'], 'required'],
            [['campaign_id', 'nm_id'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['campaign_id', 'nm_id'], 'unique', 'targetAttribute' => ['campaign_id', 'nm_id']],
        ];
    }

    public function getCampaign()
    {
        return $this->hasOne(WbCampaign::class, ['campaign_id' => 'campaign_id']);
    }
}