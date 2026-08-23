<?php
namespace app\repositories;

use yii\db\Query;
use yii\db\Expression;

class WbOrderRepository
{
    /**
     * Универсальный метод получения статистики заказов
     * @param int|array $nmIds Один ID или массив ID
     * @param string $dateFrom
     * @param string $dateTo
     * @param bool $groupByNmId Нужно ли разделять по карточкам
     * @return array
     */

public function getOrdersStats($nmIds, $dateFrom, $dateTo, $groupByDate = true, $groupByNmId = true)
{
    // 1. Базовые агрегаты
    $select = [
        'tp'             => new \yii\db\Expression('AVG([[wb_order.total_price]])'),
        'dsc'            => new \yii\db\Expression('AVG([[wb_order.discount_percent]])'),
        'apwd'           => new \yii\db\Expression('AVG([[wb_order.price_with_disc]])'),
        'spp'            => new \yii\db\Expression('AVG([[wb_order.spp]])'),
        'finished_price' => new \yii\db\Expression('AVG([[wb_order.finished_price]])'),
        // Количество — все заказы за период (не только реализованные)
        'cnt'            => new \yii\db\Expression('COUNT(*)'),
        // Отменено — заказы с is_cancel = 1
        'cns'            => new \yii\db\Expression('SUM(CASE WHEN [[wb_order.is_cancel]] = 1 THEN 1 ELSE 0 END)'),
        // Выкуплено — заказы с непустой sale_date (факт реализации)
        'byt'            => new \yii\db\Expression('SUM(CASE WHEN [[wb_order.sale_date]] IS NOT NULL THEN 1 ELSE 0 END)'),
        // Сумма заказов — сумма "Цены со скидкой" по всем заказам, КРОМЕ отменённых
        'sum_ord'        => new \yii\db\Expression('SUM(CASE WHEN [[wb_order.is_cancel]] = 0 THEN [[wb_order.price_with_disc]] ELSE 0 END)'),
        // Сумма выкупа — сумма "Цены со скидкой" только по выкупленным (sale_date не пусто)
        'sum_byt'        => new \yii\db\Expression('SUM(CASE WHEN [[wb_order.sale_date]] IS NOT NULL THEN [[wb_order.price_with_disc]] ELSE 0 END)'),
    ];

    $groupBy = [];
    $orderBy = [];
    $dimensions = [];

    // 2. Добавляем группировку по артикулу (используем nmID по твоей инструкции)
    if ($groupByNmId) {
        $dimensions['nm_id'] = 'wb_order.nm_id';
        $groupBy[] = 'wb_order.nm_id';
        $orderBy['cnt'] = SORT_DESC;

        // Данные карточки товара для отображения в гриде (фото/название/бренд/категория/артикул).
        // Оборачиваем в ANY_VALUE(), т.к. эти поля однозначно зависят от nmID карточки,
        // но сами по себе не участвуют в группировке (и photos — JSON, его в GROUP BY
        // лучше вообще не тащить).
        $dimensions['card_photos']       = new \yii\db\Expression('ANY_VALUE([[w.photos]])');
        $dimensions['card_title']        = new \yii\db\Expression('ANY_VALUE([[w.title]])');
        $dimensions['card_subject_name'] = new \yii\db\Expression('ANY_VALUE([[w.subjectName]])');
        $dimensions['card_brand']        = new \yii\db\Expression('ANY_VALUE([[w.brand]])');
        $dimensions['card_vendor_code']  = new \yii\db\Expression('ANY_VALUE([[w.vendorCode]])');
    }

    // 3. Добавляем группировку по дате
    if ($groupByDate) {
        $dimensions['odate'] = new \yii\db\Expression('DATE([[wb_order.date]])');
        $groupBy[] = new \yii\db\Expression('DATE([[wb_order.date]])'); // Группируем по выражению, чтобы не было ошибок
        $orderBy['odate'] = SORT_DESC;
    }

    // Собираем итоговый select: измерения в начале, агрегаты в конце
    $finalSelect = array_merge($dimensions, $select);

    $query = (new \yii\db\Query())
        ->select($finalSelect)
        ->from('wb_order')
        ->where(['wb_order.nm_id' => $nmIds]) // Используем nmID здесь тоже
        ->andWhere(['between', 'wb_order.date', $dateFrom, $dateTo]);

    // Джойним карточку только когда группируем по артикулу — иначе (группировка
    // только по дате) на одну строку могло бы приходиться несколько карточек,
    // и джойн просто размножил бы строки без всякого смысла.
    if ($groupByNmId) {
        $query->leftJoin('wbcards w', 'w.nmID = wb_order.nm_id');
    }

    if (!empty($groupBy)) {
        $query->groupBy($groupBy);
    }

    if (!empty($orderBy)) {
        $query->orderBy($orderBy);
    }

    return $query->all();
}


}