<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int $source_nm_id
 * @property int $competitor_nm_id
 * @property string $query_phrase
 * @property int|null $position
 * @property string $status
 * @property array|null $ai_result
 * @property string $created_at
 * @property string $updated_at
 */
class WbCompetitorAnalysis extends ActiveRecord
{
    public static function tableName() { return '{{%wb_competitor_analysis}}'; }

    public function rules()
    {
        return [
            [['source_nm_id','competitor_nm_id','query_phrase'], 'required'],
            [['company_id','source_nm_id','competitor_nm_id','position'], 'integer'],
            [['ai_result'], 'safe'],
            [['query_phrase'], 'string', 'max' => 500],
            [['status'], 'string', 'max' => 20],
            [['created_at','updated_at'], 'safe'],
            [['source_nm_id','competitor_nm_id','query_phrase'], 'unique',
                'targetAttribute' => ['source_nm_id','competitor_nm_id','query_phrase'],
                'message' => 'Эта связка уже существует'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'source_nm_id' => 'Наш товар',
            'competitor_nm_id' => 'Конкурент',
            'query_phrase' => 'Фраза',
            'position' => 'Позиция',
            'status' => 'Статус',
            'ai_result' => 'AI-анализ',
        ];
    }

    public function getSourceCard()
    {
        return $this->hasOne(WbCard::class, ['nmID' => 'source_nm_id']);
    }

    public function getCompetitorDetail()
    {
        return $this->hasOne(WbCompetitorDetail::class, ['nm_id' => 'competitor_nm_id']);
    }

    public function getCompany()
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }

    /**
     * Возвращает список уникальных source_nm_id с количеством конкурентов
     */
    public static function getSourceCardsWithCounts($companyId = null)
    {
        $query = self::find()
            ->select(['source_nm_id', 'cnt' => new \yii\db\Expression('COUNT(DISTINCT competitor_nm_id)')])
            ->groupBy('source_nm_id')
            ->orderBy(['source_nm_id' => SORT_ASC]);

        if ($companyId) {
            $query->andWhere(['company_id' => $companyId]);
        }

        return $query->asArray()->all();
    }
}
