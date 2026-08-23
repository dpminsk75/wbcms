<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Это модель для таблицы "wb_order".
 *
 * @property int $id
 * @property string $g_number Номер заказа
 * @property string $date Дата и время заказа
 * @property string $last_change_date Дата время последнего изменения статуса
 * @property string|null $supplier_article Артикул продавца
 * @property string|null $tech_size Размер
 * @property string|null $barcode Баркод
 * @property float|null $total_price Цена до скидки/промокодов
 * @property int|null $discount_percent Скидка продавца
 * @property string|null $warehouse_name Склад отгрузки
 * @property string|null $country_name Страна
 * @property string|null $oblast_okrug_name Область
 * @property string|null $region_name Регион
 * @property int|null $income_id Номер поставки
 * @property string|null $sale_id Идентификатор продажи (S...)
 * @property string|null $sale_date Дата реализации (продажи)
 * @property int|null $odid Внутрискладской номер упаковки
 * @property float|null $spp Скидка постоянного покупателя
 * @property float|null $for_pay К выплате
 * @property float|null $finished_price Фактическая цена с учетом всех скидок
 * @property float|null $price_with_disc Цена со скидкой продавца
 * @property int|null $nm_id Артикул WB
 * @property string|null $subject Предмет
 * @property string|null $category Категория
 * @property string|null $brand Бренд
 * @property int|null $is_storno Отмена заказа
 * @property string|null $sticker Цифровой стикер
 * @property string $srid Уникальный идентификатор заказа
 * @property string|null $order_type Тип заказа
 * @property string|null $created_at
 */
class WbOrder extends ActiveRecord
{
    use CompanyScopedTrait;
    /**
     * Имя таблицы в БД
     */
    public static function tableName()
    {
        return 'wb_order';
    }

    /**
     * Правила валидации
     */
    public function rules()
    {
        return [
            [['g_number', 'date', 'last_change_date', 'srid'], 'required'],
            [['date', 'last_change_date', 'created_at', 'cancel_date', 'sale_date'], 'safe'],
            [['total_price', 'spp', 'for_pay', 'finished_price', 'price_with_disc'], 'number'],
            [['discount_percent', 'income_id', 'odid', 'nm_id', 'is_cancel', 'is_supply', 'is_realization'], 'integer'],
            [['g_number', 'tech_size', 'barcode', 'sale_id', 'order_type'], 'string', 'max' => 50],
            [['supplier_article', 'warehouse_name', 'warehouse_type', 'country_name', 'subject', 'category', 'brand', 'srid'], 'string', 'max' => 100],
            [['oblast_okrug_name', 'region_name', 'sticker'], 'string', 'max' => 255],
            [['srid'], 'unique'],
        ];
    }

    public function getCard()
    {
        // Связываем nm_id из текущей таблицы с nm_id из WbCards
        return $this->hasOne(WbCard::class, ['nmID' => 'nm_id']);
    }
    /**
     * Подписи полей (используются в GridView и DetailView)
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'g_number' => '№ Заказа',
            'date' => 'Дата заказа',
            'last_change_date' => 'Обновлен',
            'cancel_date' => 'Отменён',
            'supplier_article' => 'Артикул',
            'tech_size' => 'Размер',
            'barcode' => 'Баркод',
            'total_price' => 'Цена в карт',
            'discount_percent' => 'Скидка %',
            'warehouse_name' => 'Склад',
            'warehouse_type' => 'Склад тип',
            'country_name' => 'Страна',
            'oblast_okrug_name' => 'Округ',
            'region_name' => 'Регион',
            'income_id' => '№ Поставки',
            'sale_id' => 'ID Продажи',
            'sale_date' => 'Дата реализации',
            'odid' => 'ODID',
            'spp' => 'СПП',
            'for_pay' => 'К выплате',
            'finished_price' => 'Цена продажи',
            'price_with_disc' => 'Цена со скидкой',
            'nm_id' => 'WB Артикул',
            'subject' => 'Предмет',
            'category' => 'Категория',
            'brand' => 'Бренд',
            'is_supply' => 'is_supply',
            'is_realization' => 'is_realization',
            'is_cancel' => 'is_cancel',
            'sticker' => 'Стикер',
            'srid' => 'SRID',
            'order_type' => 'Тип',
            'created_at' => 'Дата загрузки',
        ];
    }
}