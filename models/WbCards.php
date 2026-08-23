<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\helpers\Json;
use yii\helpers\Html;

/**
 * Модель карточки товара Wildberries.
 *
 * @property int $nmID
 * @property int|null $imtID
 * @property string|null $nmUUID
 * @property int|null $subjectID
 * @property string|null $subjectName
 * @property string|null $vendorCode
 * @property string|null $brand
 * @property string|null $title
 * @property string|null $description
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property ProductWbCard[] $productWbCards
 * @property Product[] $products
 */
class WbCards extends ActiveRecord
{
    use CompanyScopedTrait;
    public static function tableName()
    {
        return '{{%wbcards}}';
    }

    public static function primaryKey()
    {
        return ['nmID'];
    }

    public function getProductWbCards()
    {
        return $this->hasMany(ProductWbCard::class, ['wb_nm_id' => 'nmID']);
    }

    public function getProducts()
    {
        return $this->hasMany(Product::class, ['id' => 'product_id'])
            ->via('productWbCards');
    }

    public function afterFind()
    {
        parent::afterFind();
        if (is_string($this->photos)) {
            $this->photos = Json::decode($this->photos);
        }
        if (is_string($this->dimensions)) {
            $this->dimensions = Json::decode($this->dimensions);
        }
        if (is_string($this->characteristics)) {
            $this->characteristics = Json::decode($this->characteristics);
        }
        if (is_string($this->sizes)) {
            $this->sizes = Json::decode($this->sizes);
        }
        if (is_string($this->tags)) {
            $this->tags = Json::decode($this->tags);
        }
    }

    public static function getListForSelect()
    {
        return self::find()
            ->select(["CONCAT(nmID, ' | ', title, ' (', vendorCode, ')') as label", 'nmId'])
            ->indexBy('nmId')
            ->column();
    }
    public function getDimensions($separator = ' × ')
    {
        // Декодируем JSON, если он еще не декодирован (например, через afterFind)
        $data = is_string($this->dimensions) ? Json::decode($this->dimensions) : $this->dimensions;

        if (empty($data)) {
            return '—';
        }

        // В API WB ключи обычно: length, width, height
        $length = $data['length'] ?? 0;
        $width = $data['width'] ?? 0;
        $height = $data['height'] ?? 0;
        $weight = $data['weightBrutto'] ?? 0;

        if ($length == 0 && $width == 0 && $height == 0) {
            return '—';
        }

        return "{$length}{$separator}{$width}{$separator}{$height} см, вес: {$weight} кг";
    }
    public function getPhotosArray()
    {
        if (is_array($this->photos)) {
            return $this->photos;
        }
        try {
            return $this->photos ? \yii\helpers\Json::decode($this->photos) : [];
        } catch (\Exception $e) {
            return [];
        }
    }
/*
    public static function renderGallery(array $card, $height = 180)
    {
        $photos = [];
        
        // 1. Обработка JSON или массива
        if (!empty($card['photos'])) {
            $photos = is_string($card['photos']) ? Json::decode($card['photos']) : $card['photos'];
        }

        if (empty($photos)) {
            return Html::tag('div', 'Нет изображений', ['class' => 'text-muted', 'style' => 'padding: 10px;']);
        }

        // 2. Сборка элементов
        $items = [];
        foreach ($photos as $url) {
            $items[] = Html::img($url, [
                'style' => "height: {$height}px; width: auto; flex: 0 0 auto; margin-right: 10px; border-radius: 4px; border: 1px solid #ddd;",
                'loading' => 'lazy',
            ]);
        }

        // 3. Обертка в контейнер со скроллом
        return Html::tag('div', implode('', $items), [
            'class' => 'wb-card-gallery-scroll',
            'style' => 'display: flex; flex-direction: row; overflow-x: auto; white-space: nowrap; padding: 10px; background: #fdfdfd; border: 1px solid #eee; border-radius: 8px;'
        ]);
    }
*/
    public function renderGallery($height = 180)
    {
        $photos = $this->getPhotosArray();
        $items = [];

        // 1. Добавляем видео, если оно есть
        if (!empty($this->video)) {
            $items[] = Html::tag('div', 
                Html::tag('video', '', [
                    'src' => $this->video,
                    'controls' => true,
                    'poster' => !empty($photos) ? $photos[0] : '', // Первая картинка как обложка
                    'style' => "height: {$height}px; width: auto; border-radius: 4px; background: #000;"
                ]), 
                ['style' => 'flex: 0 0 auto; margin-right: 10px; position: relative;']
            );
        }

        // 2. Добавляем фотографии
        if (!empty($photos)) {
            foreach ($photos as $url) {
                $items[] = Html::img($url, [
                    'style' => "height: {$height}px; width: auto; flex: 0 0 auto; margin-right: 10px; border-radius: 4px; border: 1px solid #ddd;",
                    'loading' => 'lazy',
                ]);
            }
        }

        if (empty($items)) {
            return Html::tag('div', 'Нет медиа-файлов', ['class' => 'text-muted']);
        }

        return Html::tag('div', implode('', $items), [
            'class' => 'wb-card-gallery-scroll',
            'style' => 'display: flex; flex-direction: row; overflow-x: auto; white-space: nowrap; padding: 10px; border: 1px solid #eee; border-radius: 8px; align-items: flex-start;'
        ]);
    }

    public function renderCharacteristics()
    {
        $chars = is_string($this->characteristics) ? \yii\helpers\Json::decode($this->characteristics) : $this->characteristics;

        if (empty($chars)) {
            return Html::tag('div', 'Характеристики не заданы', ['class' => 'text-muted']);
        }
         \yii\helpers\ArrayHelper::multisort($chars, 'id', SORT_ASC);

        $items = [];
        foreach ($chars as $char) {
            $name = Html::tag('dt', Html::encode($char['name']), ['class' => 'card_characteristics__dt']);
            
            // Значение может быть массивом или строкой
            $valRaw = $char['value'];
            $valText = is_array($valRaw) ? implode(', ', $valRaw) : $valRaw;
            
            $value = Html::tag('dd', Html::encode($valText), ['class' => 'card_characteristics__dd']);
            $items[] = '<div class="dl_item">'.$name . $value.'</div>';
        }

        return Html::tag('dl', implode('', $items), ['style' => 'margin-top: 10px;']);
    }

    public function getCharacteristicValue($id, $asArray = false)
    {
        $chars = is_string($this->characteristics) ? \yii\helpers\Json::decode($this->characteristics) : $this->characteristics;

        if (!empty($chars)) {
            foreach ($chars as $char) {
                if ((int)$char['id'] === (int)$id) {
                    $val = $char['value'];
                    if ($asArray) {
                        return is_array($val) ? $val : [$val];
                    }
                    return is_array($val) ? implode(', ', $val) : $val;
                }
            }
        }

        return null;
    }

    public function getSubject()
    {
        // Связываем subject_id из карточки с subject_id в справочнике
        return $this->hasOne(WbSubjectCatalog::class, ['subject_id' => 'subject_id']);
    }

    public function getTags()
    {
        // Связь через промежуточную таблицу tag_card_links
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])
            ->viaTable('tag_card_links', ['nmID' => 'nmID'])
            ->orderBy(['priority' => SORT_DESC]);
    }

    public static function searchForWidget($q)
    {
        // Ищем по названию или по nmID
        return self::ajaxSearch($q, 'title', 'nmID');
    }

    public static function getTextForWidget($id)
    {
        return self::getAjaxText($id, 'title', 'nmID');
    }

}

