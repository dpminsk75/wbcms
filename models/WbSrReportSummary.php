<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $period_start
 * @property string $period_end
 * @property int $total_products
 * @property string $currency
 * @property array $supplier_rating_json
 * @property array $advertised_products_json
 * @property array $position_info_json
 * @property array $visibility_info_json
 * @property array $raw_json
 */
class WbSrReportSummary extends ActiveRecord
{
    use CompanyScopedTrait;

    public static function tableName()
    {
        return 'wb_sr_report_summary';
    }

    public function rules()
    {
        return [
            [['period_start', 'period_end'], 'required'],
            [['period_start', 'period_end'], 'safe'],
            [['total_products'], 'integer'],
            [['supplier_rating_json', 'advertised_products_json', 'position_info_json', 'visibility_info_json', 'raw_json'], 'safe'],
            [['currency'], 'string', 'max' => 10],
        ];
    }

    public function getGroups()
    {
        return $this->hasMany(WbSrReportGroups::class, ['summary_id' => 'id']);
    }
}