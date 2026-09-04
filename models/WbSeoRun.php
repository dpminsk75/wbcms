<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $started_at
 * @property string|null $finished_at
 * @property string|null $date_from
 * @property string|null $date_to
 * @property string|null $model
 * @property int $daily_limit
 * @property int $days_window
 * @property int $processed
 * @property int $skipped
 * @property int $errors
 * @property string $status
 */
class WbSeoRun extends ActiveRecord
{
    public static function tableName() { return '{{%wb_seo_run}}'; }
    public function rules()
    {
        return [
            [['started_at'], 'required'],
            [['company_id','daily_limit','days_window','processed','skipped','errors'], 'integer'],
            [['started_at','finished_at','date_from','date_to'], 'safe'],
            [['model','status'], 'string', 'max'=>100],
        ];
    }
}
