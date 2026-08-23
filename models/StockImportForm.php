<?php

namespace app\models;

use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Форма загрузки Excel: nmId | vendorCode | Количество.
 * type = production_in -> количество трактуется как ПРИШЛО (добавляется движением)
 * type = adjustment     -> количество трактуется как ФАКТ на складе (пишется разница с расчётным балансом)
 */
class StockImportForm extends Model
{
    public ?UploadedFile $file = null;
    public string $type = StockMovement::TYPE_PRODUCTION_IN;
    public string $movementDate = '';

    public function rules()
    {
        return [
            [['file'], 'file', 'extensions' => 'xlsx, xls', 'skipOnEmpty' => false],
            [['type'], 'in', 'range' => [StockMovement::TYPE_PRODUCTION_IN, StockMovement::TYPE_ADJUSTMENT]],
            [['movementDate'], 'required'],
            [['movementDate'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'file' => 'Excel-файл (nmId, vendorCode, Количество)',
            'type' => 'Тип операции',
            'movementDate' => 'Дата операции',
        ];
    }
}
