<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $date
 * @property string|null $last_change_date
 * @property string|null $warehouse_name
 * @property string|null $supplier_article
 * @property int $nm_id
 * @property string|null $barcode
 * @property int|null $quantity
 * @property int|null $in_way_to_client
 * @property int|null $in_way_from_client
 * @property int|null $quantity_full
 * @property string|null $category
 * @property string|null $subject
 * @property string|null $brand
 * @property string|null $tech_size
 * @property float|null $price
 * @property float|null $discount
 * @property int|null $is_supply
 * @property int|null $is_realization
 * @property string|null $sc_code
 */
class WbStocks extends ActiveRecord
{
    use CompanyScopedTrait;
    public static function tableName()
    {
        return 'wb_stocks';
    }

    public function rules()
    {
        return [
            [['date', 'nm_id'], 'required'],
            [['date', 'last_change_date'], 'safe'],
            [['nm_id', 'quantity', 'in_way_to_client', 'in_way_from_client', 'quantity_full', 'is_supply', 'is_realization'], 'integer'],
            [['price', 'discount'], 'number'],
            [['warehouse_name', 'supplier_article', 'category', 'subject', 'brand', 'sc_code'], 'string', 'max' => 100],
            [['barcode', 'tech_size'], 'string', 'max' => 50],
        ];
    }

    /**
     * Связь с карточками (по твоему конфигу таблицы wbcards)
     */
    public function getCard()
    {
        // Используем nm_id для связи в БД
        return $this->hasOne(WbCards::class, ['nm_id' => 'nm_id']);
    }

/**
 * Получить список складов с ненулевыми остатками
 */
public static function getWarehouseStocks($nmId)
{
    $lastDate = self::find()->select('date')->orderBy(['date' => SORT_DESC])->scalar();

    return self::find()
        ->select(['warehouse_name', 'quantity'])
        ->where(['nm_id' => $nmId, 'date' => $lastDate])
        ->andWhere(['>', 'quantity', 0])
        ->orderBy(['quantity' => SORT_DESC])
        ->asArray()
        ->all();
}

/**
 * Получить список складов, где товар находится в пути (к клиенту или от него)
 */
public static function getInWayStocks($nmId)
{
    $lastDate = self::find()->select('date')->orderBy(['date' => SORT_DESC])->scalar();

    return self::find()
        ->select(['warehouse_name', 'in_way_to_client', 'in_way_from_client'])
        ->where(['nm_id' => $nmId, 'date' => $lastDate])
        ->andWhere([
            'OR',
            ['>', 'in_way_to_client', 0],
            ['>', 'in_way_from_client', 0]
        ])
        ->orderBy(['warehouse_name' => SORT_ASC])
        ->asArray()
        ->all();
}

/**
 * Получить общие итоги по товару (Остаток, В пути туда, В пути обратно)
 */
public static function getTotalStats($nmId)
{
    $lastDate = self::find()->select('date')->orderBy(['date' => SORT_DESC])->scalar();

    return self::find()
        ->select([
            'total_quantity' => 'SUM(quantity)',
            'total_to_client' => 'SUM(in_way_to_client)',
            'total_from_client' => 'SUM(in_way_from_client)'
        ])
        ->where(['nm_id' => $nmId, 'date' => $lastDate])
        ->asArray()
        ->one();
}


/**
 * Получение данных для отчета по оборачиваемости
 */
public static function getTurnoverReport()
{
    $db = Yii::$app->db;
    
    // 1. Находим последнюю дату загрузки остатков
    $lastDate = self::find()->select('date')->orderBy(['date' => SORT_DESC])->scalar();
    
    if (!$lastDate) {
        return [];
    }

    // 2. Период для скорости продаж
    $twoWeeksAgo = date('Y-m-d', strtotime($lastDate . ' -14 days'));

    // 3. Строим запрос продаж (убедись, что wb_order - верное имя таблицы)
    $salesQuery = (new \yii\db\Query())
        ->select(['nm_id', 'COUNT(*) as total_sales'])
        ->from('wb_order') 
        ->where(['>=', 'date', $twoWeeksAgo])
        ->groupBy('nm_id');
    Yii::$app->companyManager->applyToQuery($salesQuery, '');

    $rowsQuery = (new \yii\db\Query())
        ->select([
            's.nm_id',
            'c.title as card_name', 
            'c.brand',
            'SUM(s.quantity) as current_stock',
            'IFNULL(sales.total_sales, 0) as sales_14_days',
            'ROUND(IFNULL(sales.total_sales, 0) / 14, 2) as daily_speed',
            // Тот самый CASE для расчета дней до обнуления
            'CASE 
                WHEN IFNULL(sales.total_sales, 0) > 0 
                THEN ROUND(SUM(s.quantity) / (IFNULL(sales.total_sales, 0) / 14), 1)
                ELSE 999 
             END as days_left'
        ])
        ->from(['s' => 'wb_stocks'])
        // Проверь: в таблице wbcards поле nm_id или nmID? Обычно в SQL nm_id
        ->leftJoin(['c' => 'wbcards'], 'c.nmID = s.nm_id')
        ->leftJoin(['sales' => $salesQuery], 'sales.nm_id = s.nm_id')
        ->where(['s.date' => $lastDate])
        ->groupBy(['s.nm_id', 'c.title', 'c.brand', 'sales.total_sales']);
    Yii::$app->companyManager->applyToQuery($rowsQuery, 's');
    $rows = $rowsQuery->all($db);

    return $rows;
}


public static function getWarehouseTurnover($nmId = null, $daysThreshold = 14)
{
    $db = Yii::$app->db;
    $lastDate = self::find()->select('date')->orderBy(['date' => SORT_DESC])->scalar();
    
    if (!$lastDate) return [];
    
    $weekAgo = date('Y-m-d', strtotime($lastDate . ' -7 days'));

    // 1. Считаем заказы по складам за неделю
    $ordersQuery = (new \yii\db\Query())
        ->select(['nm_id', 'warehouse_name', 'COUNT(*) as week_orders'])
        ->from('wb_order')
        ->where(['>=', 'date', $weekAgo])
        ->groupBy(['nm_id', 'warehouse_name']);
    Yii::$app->companyManager->applyToQuery($ordersQuery, '');

    // 2. Основной запрос
    $query = (new \yii\db\Query())
        ->select([
            's.nm_id',
            'c.title as card_name',
            's.warehouse_name',
            's.quantity as current_stock',
            'IFNULL(o.week_orders, 0) as week_orders',
            'ROUND(IFNULL(o.week_orders, 0) / 7, 2) as daily_speed',
            'CASE 
                WHEN IFNULL(o.week_orders, 0) > 0 THEN ROUND(s.quantity / (o.week_orders / 7), 1)
                ELSE 999 
             END as days_left'
        ])
        ->from(['s' => 'wb_stocks'])
        ->leftJoin(['c' => 'wbcards'], 'c.nmID = s.nm_id')
        ->leftJoin(['o' => $ordersQuery], 'o.nm_id = s.nm_id AND o.warehouse_name = s.warehouse_name')
        ->where(['s.date' => $lastDate]);
/*
    if ($nmId) {
        $query->andWhere(['s.nm_id' => $nmId]);
    } else {
        // Фильтр по умолчанию: только там, где есть продажи и запас меньше порога
        $query->andHaving(['>', 'week_orders', 0])
              ->andHaving(['<', 'days_left', $daysThreshold]);
    }
*/

    if ($nmId) {
        $query->andWhere(['s.nm_id' => $nmId]);
    } else {
        // Фильтр для "умного" дефицита:
        $query->andHaving(['>', 'current_stock', 1]) // Убираем нули, они только шумят
              ->andHaving(['>', 'week_orders', 5])   // Минимум 2 продажи в неделю (отсекаем случайные)
              ->andHaving(['<', 'days_left', $daysThreshold]); // Только то, что скоро кончится
    }
    Yii::$app->companyManager->applyToQuery($query, 's');

    return $query->orderBy(['days_left' => SORT_ASC])->all($db);
}

}