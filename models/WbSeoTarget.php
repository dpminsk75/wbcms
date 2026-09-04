<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $nmID
 * @property string $phrase
 * @property int $priority
 * @property int $is_active
 * @property int|null $added_by
 * @property string $created_at
 * @property string $updated_at
 */
class WbSeoTarget extends ActiveRecord
{
    public static function tableName() { return '{{%wb_seo_target}}'; }
    public function rules()
    {
        return [
            [['nmID','phrase'], 'required'],
            [['nmID','priority','is_active','added_by'], 'integer'],
            [['phrase'], 'string', 'max'=>500],
            [['phrase'], 'unique', 'targetAttribute'=>['nmID','phrase'], 'message'=>'Фраза уже добавлена'],
            [['created_at','updated_at'], 'safe'],
        ];
    }
    public function attributeLabels()
    {
        return [
            'phrase' => 'Целевой запрос',
            'priority' => 'Приоритет',
        ];
    }
    public function getCard(){ return $this->hasOne(WbCard::class, ['nmID'=>'nmID']); }
}
