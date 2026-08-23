<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use app\components\CompanyScopedNmIdTrait;

/**
 * @property int $id
 * @property int $nm_id
 * @property string $type
 * @property int $qty
 * @property string $movement_date
 * @property string|null $comment
 * @property string $source
 * @property int|null $created_by
 * @property int $created_at
 * @property int $updated_at
 *
 * @property WbCard $wbCard
 */
class StockMovement extends ActiveRecord
{
    use CompanyScopedNmIdTrait;

    const TYPE_PRODUCTION_IN = 'production_in'; // пришло от производства
    const TYPE_ADJUSTMENT = 'adjustment';        // корректировка по факту сверки (+/-)
    const TYPE_LOSS = 'loss';                    // списание брака/потерь

    const SOURCE_MANUAL = 'manual';
    const SOURCE_EXCEL_IMPORT = 'excel_import';

    public static function tableName()
    {
        return '{{%stock_movement}}';
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_PRODUCTION_IN => 'Приход от производства',
            self::TYPE_ADJUSTMENT => 'Корректировка (сверка)',
            self::TYPE_LOSS => 'Списание / брак',
        ];
    }

    public function rules()
    {
        return [
            [['nm_id', 'type', 'qty', 'movement_date'], 'required'],
            [['nm_id', 'qty', 'created_by'], 'integer'],
            ['type', 'in', 'range' => array_keys(self::typeLabels())],
            ['movement_date', 'date', 'format' => 'php:Y-m-d'],
            ['comment', 'string', 'max' => 255],
            ['source', 'default', 'value' => self::SOURCE_MANUAL],
            ['source', 'in', 'range' => [self::SOURCE_MANUAL, self::SOURCE_EXCEL_IMPORT]],
            [['nm_id'], 'validateNmIdCompanyScope'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'nm_id' => 'Товар (nmID)',
            'type' => 'Тип операции',
            'qty' => 'Количество',
            'movement_date' => 'Дата',
            'comment' => 'Комментарий',
        ];
    }

    public function behaviors()
    {
        return [
            ['class' => TimestampBehavior::class],
            [
                'class' => BlameableBehavior::class,
                'updatedByAttribute' => false, // нет отдельного updated_by в схеме
            ],
        ];
    }

    // Замените app\models\WbCard на реальный неймспейс/класс модели wbcards, если отличается.
    public function getWbCard()
    {
        return $this->hasOne(WbCard::class, ['nmID' => 'nm_id']);
    }

    public function getTypeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    /**
     * Найти карточку для импорта: сначала по nmID, если не найдена - по vendorCode.
     * $companyId - если передан, ищем только среди карточек этой компании (защита от импорта чужих nmID/vendorCode).
     */
    public static function findCardByImportRow(?string $nmId, ?string $vendorCode, ?int $companyId = null): ?WbCard
    {
        $card = null;
        if (!empty($nmId) && ctype_digit((string)$nmId)) {
            $query = WbCard::find()->where(['nmID' => (int)$nmId]);
            if ($companyId !== null) {
                $query->andWhere(['company_id' => $companyId]);
            }
            $card = $query->one();
        }
        if (!$card && !empty($vendorCode)) {
            $query = WbCard::find()->where(['vendorCode' => trim($vendorCode)]);
            if ($companyId !== null) {
                $query->andWhere(['company_id' => $companyId]);
            }
            $card = $query->one();
        }
        return $card;
    }
}
