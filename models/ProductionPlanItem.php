<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use app\components\CompanyScopedNmIdTrait;

/**
 * @property int $id
 * @property int $nm_id
 * @property int $production_days
 * @property int $logistics_smolensk_days
 * @property int $logistics_wb_days
 * @property int $buffer_days
 * @property int $target_coverage_days
 * @property int $sort_order
 * @property int $created_at
 * @property int $updated_at
 *
 * @property WbCard $wbCard
 */
class ProductionPlanItem extends ActiveRecord
{
    use CompanyScopedNmIdTrait;

    public static function tableName()
    {
        return '{{%production_plan_item}}';
    }

    public function rules()
    {
        return [
            [['nm_id'], 'required'],
            [['nm_id', 'production_days', 'logistics_smolensk_days', 'logistics_wb_days',
              'buffer_days', 'target_coverage_days', 'sort_order'], 'integer'],
            [['nm_id'], 'unique'],
            [['production_days'], 'default', 'value' => 20],
            [['logistics_smolensk_days', 'logistics_wb_days'], 'default', 'value' => 5],
            [['buffer_days'], 'default', 'value' => 7],
            [['target_coverage_days'], 'default', 'value' => 90],
            [['nm_id'], 'validateNmIdCompanyScope'],
        ];
    }

    public function behaviors()
    {
        return [
            ['class' => TimestampBehavior::class],
        ];
    }

    public function getWbCard()
    {
        return $this->hasOne(WbCard::class, ['nmID' => 'nm_id']);
    }

    /**
     * Суммарный цикл (производство + логистика до Смоленска + до WB) - пригодится для отчёта.
     */
    public function getFullCycleDays(): int
    {
        return $this->production_days + $this->logistics_smolensk_days + $this->logistics_wb_days;
    }
}
