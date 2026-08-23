<?php

namespace app\models;

use yii\db\ActiveRecord;
use app\components\CompanyScopedNmIdTrait;

/**
 * @property int $id
 * @property int $nm_id
 * @property string $period_date
 * @property int $qty_start
 * @property int $created_at
 *
 * @property WbCard $wbCard
 */
class StockSnapshot extends ActiveRecord
{
    use CompanyScopedNmIdTrait;

    public static function tableName()
    {
        return '{{%stock_snapshot}}';
    }

    public function rules()
    {
        return [
            [['nm_id', 'period_date', 'qty_start'], 'required'],
            [['nm_id', 'qty_start', 'created_at'], 'integer'],
            ['period_date', 'date', 'format' => 'php:Y-m-d'],
            [['nm_id', 'period_date'], 'unique', 'targetAttribute' => ['nm_id', 'period_date']],
            [['nm_id'], 'validateNmIdCompanyScope'],
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = time();
        }
        return parent::beforeSave($insert);
    }

    // Замените app\models\WbCard на реальный неймспейс/класс модели wbcards, если отличается.
    public function getWbCard()
    {
        return $this->hasOne(WbCard::class, ['nmID' => 'nm_id']);
    }

    /**
     * Последний снапшот на дату <= указанной (по умолчанию сегодня).
     */
    public static function findLatestFor(int $nmId, ?string $onDate = null): ?self
    {
        $onDate = $onDate ?: date('Y-m-d');
        return static::find()
            ->where(['nm_id' => $nmId])
            ->andWhere(['<=', 'period_date', $onDate])
            ->orderBy(['period_date' => SORT_DESC])
            ->one();
    }
}
