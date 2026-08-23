<?php
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int $group_id
 * @property int $nmID
 * @property string $vendor_code
 * @property string $name
 * @property string $brand_name
 * @property string $subject_name
 * @property int $is_advertised
 * @property int $is_card_rated
 * @property float $rating
 * @property int $feedback_rating
 * @property array $price_json
 * @property array $metrics_json
 */
class WbSrReportItems extends ActiveRecord
{
    use CompanyScopedTrait;

    public static function tableName()
    {
        return 'wb_sr_report_items';
    }

public function rules()
{
    return [
        [['group_id', 'nmID'], 'required'],
        [['date'], 'safe'], // Поле даты
        [['group_id', 'nmID', 'is_advertised', 'is_card_rated', 'avg_position', 'clicks', 'orders'], 'integer'],
        [['rating', 'feedback_rating', 'ctr'], 'number'],
        [['name'], 'string', 'max' => 500],
        [['vendor_code', 'brand_name', 'subject_name'], 'string', 'max' => 255],
        [['price_json', 'metrics_json'], 'safe'],
    ];
}
    /**
     * Связь с вашей основной таблицей карточек
     * Используем c.nmID согласно вашим инструкциям
     */
    public function getCard()
    {
        // Предполагаем, что модель называется WbCards
        return $this->hasOne(WbCards::class, ['nm_id' => 'nmID']);
    }

    public function getGroup()
    {
        return $this->hasOne(WbSrReportGroups::class, ['id' => 'group_id']);
    }
}