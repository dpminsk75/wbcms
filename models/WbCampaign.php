<?php
namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;
use yii\helpers\Html;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $name
 * @property int $type
 * @property int $status
 * @property float $daily_budget
 */
class WbCampaign extends ActiveRecord
{
    use CompanyScopedTrait;
    public static function tableName()
    {
        return '{{%wb_campaign}}';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function rules()
    {
        return [
            [['campaign_id', 'name', 'type', 'status'], 'required'],
            [['campaign_id', 'type', 'status'], 'integer'],
            [['daily_budget'], 'number'],
            [['name'], 'string', 'max' => 255],
            [['campaign_id'], 'unique'],
        ];
    }

    public function getItems()
    {
        return $this->hasMany(WbCampaignItem::class, ['campaign_id' => 'campaign_id']);
    }

    public function getStats()
    {
        return $this->hasMany(WbCampaignStats::class, ['campaign_id' => 'campaign_id']);
    }

    public static function getStatusMap()
    {
        return [
            -1 => ['label' => 'Удалена', 'class' => 'label-default'],
            4  => ['label' => 'Готова к запуску', 'class' => 'label-info'],
            7  => ['label' => 'Завершена', 'class' => 'label-primary'],
            8  => ['label' => 'Отклонена', 'class' => 'label-danger'],
            9  => ['label' => 'Активна', 'class' => 'label-success'],
            11 => ['label' => 'Пауза', 'class' => 'label-warning'],
        ];
    }

    public static function renderStatusLabel($statusId)
    {
        $map = self::getStatusMap();
        $config = $map[$statusId] ?? ['label' => 'Неизвестно (' . $statusId . ')', 'class' => 'label-default'];

        return Html::tag('span', $config['label'], [
            'class' => 'label ' . $config['class'],
            'style' => 'font-size: 11px;' // Опционально: чуть уменьшим для таблицы
        ]);
    }

}