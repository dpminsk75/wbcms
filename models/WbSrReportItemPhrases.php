<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int $item_id
 * @property string $phrase
 * @property int $avg_position
 * @property int $clicks
 * @property int $orders
 * @property float $ctr
 */
class WbSrReportItemPhrases extends ActiveRecord
{
    use CompanyScopedTrait;

    public static function tableName()
    {
        return 'wb_sr_report_item_phrases';
    }

    public function rules()
    {
        return [
            [['item_id', 'phrase', 'date', 'nmID'], 'required'],
            [['item_id', 'nmID', 'clicks', 'orders', 'frequency_current', 'median_position_current', 'week_frequency', 'is_card_rated'], 'integer'],
            [['rating', 'feedback_rating', 'avg_position', 'ctr'], 'number'],
            [['date', 'open_card_json', 'add_to_cart_json', 'open_to_cart_json', 'orders_json', 'cart_to_order_json', 'raw_json'], 'safe'],
            [['phrase'], 'string', 'max' => 500],
        ];
    }

    public function getItem()
    {
        return $this->hasOne(WbSrReportItems::class, ['id' => 'item_id']);
    }

public static function getPositionHistory($nmID, $phrases = [])
{
    $query = self::find()
        ->select(['date', 'phrase', 'avg_position'])
        ->where(['nmID' => $nmID])
        ->andWhere(['>', 'avg_position', 0])
        ->orderBy(['date' => SORT_ASC]);

    if (!empty($phrases)) {
        $query->andWhere(['phrase' => $phrases]);
    }

    $data = $query->asArray()->all();
    
    // Группируем для графика: ['фраза' => ['дата' => позиция]]
    $result = [];
    foreach ($data as $row) {
        $result[$row['phrase']][$row['date']] = (int)$row['avg_position'];
    }
    return $result;
}

/**
 * Какие товары и на каких позициях были по конкретной фразе
 */
public static function getPhraseCompetitors($phrase)
{
    return self::find()
        ->select(['date', 'nmID', 'avg_position', 'clicks', 'orders'])
        ->where(['phrase' => $phrase])
        ->orderBy(['date' => SORT_ASC, 'avg_position' => SORT_ASC])
        ->asArray()
        ->all();
}

/**
 * Зависимость CTR от позиции по конкретной фразе
 */
public static function getPtrToPositionRelation($nmID)
{
    return self::find()
        ->select(['phrase', 'AVG(avg_position) as avg_pos', 'AVG(ctr) as avg_ctr', 'SUM(clicks) as total_clicks'])
        ->where(['nmID' => $nmID])
        ->groupBy('phrase')
        ->having(['>', 'total_clicks', 0])
        ->orderBy(['total_clicks' => SORT_DESC])
        ->asArray()
        ->all();
}

public static function getCardPositionMatrix($nmID, $dateFrom, $dateTo)
{
    $data = self::find()
        ->select(['date', 'phrase', 'avg_position'])
        ->where(['nmID' => $nmID])
        ->andWhere(['between', 'date', $dateFrom, $dateTo])
        ->orderBy(['date' => SORT_ASC, 'phrase' => SORT_ASC])
        ->asArray()
        ->all();

    $matrix = [];
    $dates = [];

    foreach ($data as $row) {
        $matrix[$row['phrase']][$row['date']] = $row['avg_position'];
        $dates[$row['date']] = $row['date'];
    }

    return [
        'matrix' => $matrix,
        'dates' => array_values($dates), // Список уникальных дат для заголовков колонок
    ];
}

}