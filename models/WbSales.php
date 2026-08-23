<?php
namespace app\models;

use Yii;

class WbSales extends \yii\db\ActiveRecord
{
    use CompanyScopedTrait;
    public static function tableName()
    {
        return 'wb_sales';
    }

    public function rules()
    {
        return [
            [['saleID', 'srid'], 'required'],
            [['date', 'lastChangeDate', 'created_at'], 'safe'],
            [['nmId', 'incomeID', 'discountPercent'], 'integer'],
            [['totalPrice', 'spp', 'paymentSaleAmount', 'forPay', 'finishedPrice', 'priceWithDisc'], 'number'],
            [['isSupply', 'isRealization'], 'boolean'],
            [['saleID', 'number', 'barcode', 'techSize', 'orderType', 'gNumber'], 'string', 'max' => 50],
            [['srid', 'warehouseName', 'warehouseType', 'countryName', 'regionName', 'supplierArticle', 'category', 'subject', 'brand'], 'string', 'max' => 100],
            [['oblastOkrugName', 'saleEvents', 'sticker'], 'string', 'max' => 255],
            [['saleID'], 'unique'],
        ];
    }

    public function getCard()
    {
        // Связываем nm_id из текущей таблицы с nm_id из WbCards
        return $this->hasOne(WbCard::class, ['nmID' => 'nmId']);
    }

    public function attributeLabels()
    {
        return [
            'saleID' => 'ID продажи (WB)',
            'srid' => 'ID заказа (SRID)',
            'number' => 'Номер заказа',
            'date' => 'Дата продажи',
            'lastChangeDate' => 'Дата обновления',
            'warehouseName' => 'Склад отгрузки',
            'warehouseType' => 'Тип склада',
            'countryName' => 'Страна',
            'regionName' => 'Округ',
            'oblastOkrugName' => 'Область / Регион',
            'supplierArticle' => 'Артикул',
            'nmId' => 'Артикул WB',
            'barcode' => 'Баркод',
            'category' => 'Категория',
            'subject' => 'Предмет',
            'brand' => 'Бренд',
            'techSize' => 'Размер',
            'incomeID' => 'Номер поставки',
            'isSupply' => 'Поставка',
            'isRealization' => 'Реализация',
            'totalPrice' => 'Цена до скидок',
            'discountPercent' => 'Скидка %',
            'spp' => 'СПП',
            'paymentSaleAmount' => 'Оплачено покупателем',
            'forPay' => 'К оплате',
            'finishedPrice' => 'Цена продажи',
            'priceWithDisc' => 'Цена со скидкой',

            'saleEvents' => 'События продажи',
            'orderType' => 'Тип заказа',
            'sticker' => 'Стикер',
            'gNumber' => 'Номер задания',
            'created_at' => 'Дата загрузки в БД',
        ];
    }
}