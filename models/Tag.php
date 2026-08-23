<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string|null $tag_group
 * @property string|null $color
 * @property int|null $priority
 * @property string $created_at
 * @property string $updated_at
 */
class Tag extends ActiveRecord
{
    // Виртуальное поле для получения ID из формы (Drag-and-Drop)
    public $wbCardIds = [];
    public $wbCards = [];

    public static function tableName()
    {
        return 'tags';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['priority'], 'integer'],
            [['priority'], 'default', 'value' => 0],
            [['created_at', 'updated_at', 'wbCardIds'], 'safe'],
            [['name'], 'string', 'max' => 255],
            [['tag_group'], 'string', 'max' => 100],
            [['color'], 'string', 'max' => 7],
            [['color'], 'default', 'value' => '#337ab7'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Название тега',
            'tag_group' => 'Группа',
            'color' => 'Цвет',
            'priority' => 'Приоритет',
        ];
    }

    /**
     * Связь с таблицей связей
     */
    public function getTagCardLinks()
    {
        return $this->hasMany(TagCardLink::class, ['tag_id' => 'id']);
    }

    /**
     * Загрузка текущих привязанных nmID в виртуальное поле перед редактированием
     */
    public function afterFind()
    {
        parent::afterFind();
        $this->wbCardIds = $this->getTagCardLinks()->select('nmID')->column();
        $this->wbCards   = $this->getTagCardLinks()->select('nmID', 'vendorCode', 'name');
    }

    /**
     * Сохранение связей с карточками после сохранения тега
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        // Всегда очищаем старые связи перед записью новых (для простоты реализации)
        TagCardLink::deleteAll(['tag_id' => $this->id]);

        if (!empty($this->wbCardIds) && is_array($this->wbCardIds)) {
            $rows = [];
            foreach ($this->wbCardIds as $nmID) {
                $rows[] = [$this->id, $nmID];
            }
            
            // Используем batchInsert для производительности
            Yii::$app->db->createCommand()
                ->batchInsert(TagCardLink::tableName(), ['tag_id', 'nmID'], $rows)
                ->execute();
        }
    }

    public function getTagBadge($count = null)
    {
        $name = \yii\helpers\Html::encode($this->name);
        $displayLabel = ($count !== null) ? "{$name} ({$count})" : $name;
        
        // Определяем цвет текста (черный или белый) для читаемости на цветном фоне
        $textColor = $this->getContrastColor($this->color);

//        return \yii\helpers\Html::tag('span', '• ' . $displayLabel, [
        return \yii\helpers\Html::tag('span', $displayLabel, [
            'class' => 'tag-badge-custom',
            'style' => "background-color: {$this->color}; color: {$textColor};",
            'title' => "Приоритет: {$this->priority}"
        ]);
    }

    private function getContrastColor($hexColor) 
    {
        $hex = str_replace('#', '', $hexColor);
        if (strlen($hex) != 6) return '#ffffff';
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return ($yiq >= 128) ? '#333' : '#ffffff';
    }

}