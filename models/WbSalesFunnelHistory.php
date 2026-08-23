<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $nmId
 * @property string $date
 * @property int $openCardCount
 * @property int $addToCartCount
 * @property int $ordersCount
 */
class WbSalesFunnelHistory extends ActiveRecord
{
    use CompanyScopedTrait;
    public static function tableName()
    {
        return 'wb_sales_funnel_history';
    }

    public function rules()
{
        return [
            [['date', 'nmId'], 'required'],
            [['date'], 'safe'],
            [['nmId', 'openCount', 'cartCount', 'orderCount', 'buyoutCount'], 'integer'],
            [['orderSum', 'buyoutSum'], 'number'],
            // Уникальный ключ: на одну дату и один nmId — одна запись
            [['date', 'nmId'], 'unique', 'targetAttribute' => ['date', 'nmId']],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'date' => 'Дата',
            'nmId' => 'Артикул WB',
            'openCount' => 'Переходы',
            'cartCount' => 'В корзину',
            'ordersCount' => 'Заказы',
            'orderSum' => 'Сумма заказов',
            'buyoutCount' => 'Выкупы (шт)',
            'buyoutSum' => 'Сумма выкупов',
        ];
    }

    public function getProductCard()
    {
        // Предполагаем, что связь идет через таблицу product_wb_card по полю wb_nm_id
        return $this->hasOne(ProductWbCard::class, ['wb_nm_id' => 'nmId']);
    }
}