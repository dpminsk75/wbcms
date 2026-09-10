<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * Read-only модель для таблицы wb_competitor_cards (сырые данные поиска).
 *
 * @property int $id
 * @property int $source_nm_id
 * @property string $query_phrase
 * @property int $position
 * @property int $nm_id
 * @property string|null $title
 * @property string|null $brand
 * @property int $price
 * @property float $rating
 * @property int $feedbacks
 * @property string $collected_at
 */
class WbCompetitorCard extends ActiveRecord
{
    public static function tableName() { return '{{%wb_competitor_cards}}'; }

    public static function find()
    {
        return parent::find()->orderBy(['position' => SORT_ASC]);
    }

    public function rules()
    {
        return [
            [['source_nm_id','query_phrase','position','nm_id'], 'required'],
            [['source_nm_id','position','nm_id','price','feedbacks'], 'integer'],
            [['rating'], 'number'],
            [['query_phrase','title'], 'string', 'max' => 500],
            [['brand'], 'string', 'max' => 255],
            [['collected_at'], 'safe'],
        ];
    }

    /**
     * Уникальные фразы для данного nmID
     */
    public static function getUniquePhrases($sourceNmId)
    {
        return self::find()
            ->select('query_phrase')
            ->where(['source_nm_id' => $sourceNmId])
            ->groupBy('query_phrase')
            ->orderBy('query_phrase')
            ->column();
    }

    /**
     * Уникальные конкуренты для данных фраз (объединение)
     * @param int $sourceNmId
     * @param array $phrases
     * @param int|null $positionMax максимальная позиция (null = все)
     * Возвращает: [{nm_id, title, brand, queries: [{phrase, position}]}]
     */
    public static function getUniqueCompetitors($sourceNmId, array $phrases, ?int $positionMax = null)
    {
        $query = self::find()
            ->where(['source_nm_id' => $sourceNmId])
            ->andWhere(['in', 'query_phrase', $phrases])
            ->orderBy(['nm_id' => SORT_ASC, 'position' => SORT_ASC]);

        if ($positionMax !== null) {
            $query->andWhere(['<=', 'position', $positionMax]);
        }

        $rows = $query->asArray()->all();

        $grouped = [];
        foreach ($rows as $row) {
            $nmId = $row['nm_id'];
            if (!isset($grouped[$nmId])) {
                $grouped[$nmId] = [
                    'nm_id' => $nmId,
                    'title' => $row['title'],
                    'brand' => $row['brand'],
                    'queries' => [],
                ];
            }
            $grouped[$nmId]['queries'][] = [
                'phrase' => $row['query_phrase'],
                'position' => $row['position'],
            ];
        }

        return array_values($grouped);
    }
}
