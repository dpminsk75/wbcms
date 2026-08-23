<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int $summary_id
 * @property int $subject_id
 * @property string $subject_name
 * @property string $brand_name
 * @property int $tag_id
 * @property string $tag_name
 * @property array $metrics_json
 */
class WbSrReportGroups extends ActiveRecord
{
    use CompanyScopedTrait;

    public static function tableName()
    {
        return 'wb_sr_report_groups';
    }

    public function rules()
    {
        return [
            [['summary_id'], 'required'],
            [['summary_id', 'subject_id', 'tag_id'], 'integer'],
            [['metrics_json'], 'safe'],
            [['subject_name', 'brand_name', 'tag_name'], 'string', 'max' => 255],
        ];
    }

    public function getItems()
    {
        return $this->hasMany(WbSrReportItems::class, ['group_id' => 'id']);
    }

    public function getSummary()
    {
        return $this->hasOne(WbSrReportSummary::class, ['id' => 'summary_id']);
    }
}