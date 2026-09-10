<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int $source_nm_id
 * @property array|null $result
 * @property string|null $raw_ai_response
 * @property string|null $suggested_title
 * @property string|null $suggested_description
 * @property int $competitor_count
 * @property string|null $model
 * @property string $created_at
 */
class WbCompetitorSummary extends ActiveRecord
{
    public static function tableName() { return '{{%wb_competitor_summary}}'; }

    public function rules()
    {
        return [
            [['source_nm_id'], 'required'],
            [['company_id','source_nm_id','competitor_count'], 'integer'],
            [['result'], 'safe'],
            [['raw_ai_response'], 'string'],
            [['suggested_title'], 'string', 'max' => 500],
            [['suggested_description'], 'string'],
            [['model'], 'string', 'max' => 100],
            [['created_at'], 'safe'],
            [['source_nm_id'], 'unique'],
        ];
    }

    public function getCard()
    {
        return $this->hasOne(WbCard::class, ['nmID' => 'source_nm_id']);
    }
}
