<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $run_id
 * @property int $company_id
 * @property int $nmID
 * @property string|null $old_title
 * @property string|null $old_description
 * @property string|null $new_title
 * @property string|null $new_description
 * @property string|null $rationale
 * @property array|null $keywords_added
 * @property array|null $keywords_removed
 * @property float|null $confidence
 * @property string|null $model
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property array|null $raw_json
 * @property string $status
 * @property int|null $viewed_by
 * @property string|null $viewed_at
 * @property int $is_requeued
 * @property string|null $requeued_at
 * @property string $created_at
 * @property string $updated_at
 */
class WbSeoRecommendation extends ActiveRecord
{
    public static function tableName() { return '{{%wb_seo_recommendation}}'; }

    public function rules()
    {
        return [
            [['company_id','nmID','created_at','updated_at'], 'required'],
            [['run_id','company_id','nmID','prompt_tokens','completion_tokens','viewed_by','is_requeued'], 'integer'],
            [['old_description','new_description','rationale','raw_json','keywords_added','keywords_removed'], 'safe'],
            [['confidence'], 'number'],
            [['viewed_at','requeued_at','created_at','updated_at'], 'safe'],
            [['old_title','new_title'], 'string', 'max'=>500],
            [['model'], 'string', 'max'=>100],
            [['status'], 'string', 'max'=>20],
        ];
    }

    public function getCard()
    {
        return $this->hasOne(WbCard::class, ['nmID' => 'nmID']);
    }
    public function getRun()
    {
        return $this->hasOne(WbSeoRun::class, ['id' => 'run_id']);
    }
}
