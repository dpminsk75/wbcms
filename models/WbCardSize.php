<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Развёртка JSON поля wbcards.sizes в индексируемую таблицу.
 * 1 строка = 1 sku (баркод) из массива skus.
 *
 * @property int $id
 * @property int $nmID
 * @property int $chrtID
 * @property string|null $techSize
 * @property string|null $wbSize
 * @property string $sku
 */
class WbCardSize extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%wbcards_sizes}}';
    }

    public function rules()
    {
        return [
            [['nmID', 'chrtID', 'sku'], 'required'],
            [['nmID', 'chrtID'], 'integer'],
            [['techSize', 'wbSize'], 'string', 'max' => 50],
            [['sku'], 'string', 'max' => 50],
            [['sku'], 'unique'],
            [['chrtID', 'sku'], 'unique', 'targetAttribute' => ['chrtID', 'sku']],
        ];
    }

    public function getCard()
    {
        return $this->hasOne(WbCard::class, ['nmID' => 'nmID']);
    }

    /**
     * Синхронизация размеров карточки из данных content-api.
     * Вызывается после upsert wbcards.
     *
     * @param int $nmID
     * @param mixed $sizesRaw массив из API или JSON-строка или null
     * @return int количество вставленных строк
     */
    public static function syncForCard(int $nmID, $sizesRaw): int
    {
        $db = Yii::$app->db;

        // Нормализуем к массиву - в БД лежит двойной json_encode (видно на скрине: "[{\"chrtID\":...}]")
        // Поэтому декодируем до тех пор пока получаем строку содержащую JSON
        if (empty($sizesRaw) || $sizesRaw === 'null' || $sizesRaw === '[]') {
            $sizes = [];
        } elseif (is_string($sizesRaw)) {
            $decoded = json_decode($sizesRaw, true);
            // двойное кодирование: первый decode даёт строку "[{...}]", второй - массив
            if (is_string($decoded)) {
                $decoded2 = json_decode($decoded, true);
                if (is_array($decoded2)) {
                    $decoded = $decoded2;
                }
            }
            // на всякий случай ещё одна итерация (тройное)
            if (is_string($decoded)) {
                $tmp = json_decode($decoded, true);
                if (is_array($tmp)) {
                    $decoded = $tmp;
                }
            }
            $sizes = is_array($decoded) ? $decoded : [];
            // если после всех попыток получили не массив, но строка содержит chrtID - пробуем stripslashes
            if (empty($sizes) && is_string($sizesRaw) && strpos($sizesRaw, 'chrtID') !== false) {
                $alt = json_decode(stripslashes($sizesRaw), true);
                if (is_string($alt)) {
                    $alt = json_decode($alt, true);
                }
                if (is_array($alt)) {
                    $sizes = $alt;
                }
            }
        } elseif (is_array($sizesRaw)) {
            $sizes = $sizesRaw;
        } else {
            $sizes = [];
        }

        // Удаляем старые записи для этого nmID
        $db->createCommand()->delete(self::tableName(), ['nmID' => $nmID])->execute();

        if (empty($sizes)) {
            return 0;
        }

        $rows = [];
        foreach ($sizes as $size) {
            $chrtID = $size['chrtID'] ?? null;
            if ($chrtID === null) {
                continue;
            }
            $techSize = (string)($size['techSize'] ?? '');
            $wbSize = (string)($size['wbSize'] ?? '');
            $skus = $size['skus'] ?? [];
            if (empty($skus) || !is_array($skus)) {
                continue;
            }
            foreach ($skus as $sku) {
                $sku = trim((string)$sku);
                if ($sku === '') {
                    continue;
                }
                $rows[] = [$nmID, (int)$chrtID, $techSize, $wbSize, $sku];
            }
        }

        if (empty($rows)) {
            return 0;
        }

        // batchInsert игнорирует дубли sku - UNIQUE(sku) может сработать если баркод встречается в 2 карточках (ошибка данных WB)
        // Используем upsert по sku чтобы не падать, последний выиграет
        foreach (array_chunk($rows, 500) as $chunk) {
            $db->createCommand()->batchInsert(
                self::tableName(),
                ['nmID', 'chrtID', 'techSize', 'wbSize', 'sku'],
                $chunk
            )->execute();
        }

        return count($rows);
    }

    /**
     * Найти nmID/chrtID по sku.
     */
    public static function findBySku(string $sku): ?self
    {
        return self::findOne(['sku' => $sku]);
    }
}
