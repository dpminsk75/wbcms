<?php
namespace app\models;

use yii\db\ActiveRecord;

/**
 * Read-only модель для таблицы wb_competitor_details (обогащённые данные конкурента).
 *
 * @property int $id
 * @property int $nm_id
 * @property string|null $title
 * @property string|null $description
 * @property array|null $recommendations
 * @property string|null $url
 * @property string $brand
 * @property string $seller
 * @property string|null $images
 * @property string $collected_at
 */
class WbCompetitorDetail extends ActiveRecord
{
    public static function tableName() { return '{{%wb_competitor_details}}'; }

    public function rules()
    {
        return [
            [['nm_id'], 'required'],
            [['nm_id'], 'integer'],
            [['description','images'], 'safe'],
            [['recommendations'], 'safe'],
            [['title','url'], 'string', 'max' => 500],
            [['brand','seller'], 'string', 'max' => 255],
            [['collected_at'], 'safe'],
        ];
    }

    public function getPhotosArray()
    {
        if (!$this->images) return [];
        $decoded = json_decode($this->images, true);
        return is_array($decoded) ? $decoded : [];
    }
}
