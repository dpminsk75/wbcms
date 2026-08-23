<?php

namespace app\models;

use yii\base\Model;
use yii\web\UploadedFile;

class StockSnapshotImportForm extends Model
{
    /**
     * @var UploadedFile
     */
    public $file;
    public $period_date;

    public function rules()
    {
        return [
            [['file', 'period_date'], 'required'],
            [['file'], 'file', 'skipOnEmpty' => false, 'extensions' => 'xls, xlsx'],
            [['period_date'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'file' => 'Файл Excel',
            'period_date' => 'Период (дата снапшота)',
        ];
    }
}
