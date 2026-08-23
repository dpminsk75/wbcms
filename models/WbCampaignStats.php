<?php

namespace app\models;

use Yii;

/**
 * Модель для таблицы wb_campaign_stats
 */
class WbCampaignStats extends \yii\db\ActiveRecord
{
    use CompanyScopedTrait;
    public static function tableName()
    {
        return '{{%wb_campaign_stats}}';
    }

    public function rules()
    {
        return [
            [['campaign_id', 'nm_id', 'date'], 'required'],
            [['campaign_id', 'nm_id', 'views', 'clicks', 'atbs', 'orders', 'shks'], 'integer'],
            [['ctr', 'cpc', 'sum', 'cr'], 'number'],
            [['date'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public static function upsertStats(array $rows)
    {
        if (empty($rows)) {
            return 0;
        }

        return \Yii::$app->db->createCommand()
            ->upsert(self::tableName(), $rows, [
                'views', 'clicks', 'ctr', 'cpc', 'sum', 'atbs', 'orders', 'shks', 'cr'
            ])
            ->execute();
    }
    /**
     * Названия полей (человекочитаемые метки)
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'campaign_id' => 'ID Кампании',
            'date' => 'Дата',
            'nmId' => 'Арт WB',
            'views' => 'Показы',
            'clicks' => 'Клики',
            'ctr' => 'CTR (%)',
            'cpc' => 'CPC (р)',
            'sum' => 'Затраты (р)',
            'atbs' => 'В корзину',
            'orders' => 'Заказы',
            'shks' => 'Заказано штук',
            'sum_price' => 'Выручка',
            'cr' => 'CR (Конверсия %)',
            'canceled' => 'Отмены заказов',
        ];
    }

    public static function getAppTypeLabels()
    {
        return [
            '1'  => '🌐 Сайт',
            '32' => '🤖 Android',
            '64' => '🍎 iOS',
            '0'  => '❓ Неизвестно',
            'unknown' => '❓ Неизвестно',
        ];
    }

    // Пример вспомогательного метода для автоматического расчета CTR, если он не пришел из API
    public function calculateMetrics()
    {
        $this->ctr = $this->views > 0 ? round(($this->clicks / $this->views) * 100, 2) : 0;
        $this->cpc = $this->clicks > 0 ? round($this->sum / $this->clicks, 2) : 0;
    }
}